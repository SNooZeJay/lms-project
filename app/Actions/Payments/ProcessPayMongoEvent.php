<?php

namespace App\Actions\Payments;

use App\Contracts\PayMongoClient;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentEventStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Payments\PayMongoEventEnvelope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes one provider event, exactly once.
 *
 * The envelope is read by PayMongoEventEnvelope, which accepts both shapes
 * PayMongo documents, so this class never has to know where a field sits. The
 * signature is verified against the raw request body, because re-encoding a
 * parsed payload changes the bytes and would make every legitimate event look
 * forged.
 *
 * The provider event id is unique, so a replayed delivery is recorded and
 * changes nothing. An unverified signature is recorded and discarded without
 * touching any state. Activating an enrollment happens in one transaction
 * with the payment.
 */
class ProcessPayMongoEvent
{
    public function __construct(private readonly PayMongoClient $client) {}

    /**
     * Events that settle a payment. Both spellings are listed because the
     * dashboard label and the documented event type have differed.
     */
    private const PAID_EVENTS = [
        'checkout_session.payment.paid',
        'checkout.session.payment.paid',
    ];

    private const FAILED_EVENTS = [
        'payment.failed',
    ];

    private const REFUNDED_EVENTS = [
        'payment.refunded',
        'refund.succeeded',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handle(
        PayMongoEventEnvelope $envelope,
        array $payload,
        array $headers,
        ?string $rawBody = null,
    ): PaymentEvent {
        $verified = $this->client->verifySignature(
            $rawBody ?? (string) json_encode($payload),
            $headers,
        );

        $event = $this->record($envelope, $payload, $verified);

        if (! $verified) {
            $this->mark($event, PaymentEventStatus::Ignored, 'Signature verification failed.');

            return $event;
        }

        if ($event->processing_status === PaymentEventStatus::Processed) {
            return $event;
        }

        if (! $this->livemodeMatches($envelope)) {
            $this->mark(
                $event,
                PaymentEventStatus::Ignored,
                'Event livemode does not match this server, so it was not applied.'
            );

            return $event;
        }

        try {
            $this->apply($event, $envelope);
        } catch (Throwable $exception) {
            Log::error('Payment event failed', [
                'provider_event_id' => $envelope->eventId,
                'event_type' => $envelope->eventType,
                'message' => $exception->getMessage(),
            ]);

            $this->mark($event, PaymentEventStatus::Failed, $exception->getMessage());

            return $event;
        }

        return $event;
    }

    /**
     * A test server must never act on a live event, and the reverse. A payload
     * without the flag is accepted, because a missing flag is not a mismatch.
     */
    private function livemodeMatches(PayMongoEventEnvelope $envelope): bool
    {
        if ($envelope->livemode === null) {
            return true;
        }

        return $envelope->livemode === (bool) config('services.paymongo.expected_livemode', false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(PayMongoEventEnvelope $envelope, array $payload, bool $verified): PaymentEvent
    {
        $existing = PaymentEvent::query()
            ->where('provider', 'paymongo')
            ->where('provider_event_id', $envelope->eventId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $event = new PaymentEvent;
        $event->forceFill([
            'provider' => 'paymongo',
            'provider_event_id' => $envelope->eventId,
            'event_type' => $envelope->eventType,
            'payload' => $payload,
            'signature_verified' => $verified,
            'processing_status' => PaymentEventStatus::Received,
            'received_at' => now(),
        ]);

        try {
            $event->save();
        } catch (QueryException $exception) {
            $raced = PaymentEvent::query()
                ->where('provider', 'paymongo')
                ->where('provider_event_id', $envelope->eventId)
                ->first();

            if ($raced === null) {
                throw $exception;
            }

            return $raced;
        }

        return $event;
    }

    private function apply(PaymentEvent $event, PayMongoEventEnvelope $envelope): void
    {
        $payment = $this->resolvePayment($event, $envelope);

        if ($payment === null) {
            $this->mark($event, PaymentEventStatus::Ignored, 'No matching payment.');

            return;
        }

        if (in_array($envelope->eventType, self::PAID_EVENTS, true)) {
            $this->markPaid($event, $payment, $envelope);
        } elseif (in_array($envelope->eventType, self::FAILED_EVENTS, true)) {
            $this->markFailed($event, $payment, $envelope);
        } elseif (in_array($envelope->eventType, self::REFUNDED_EVENTS, true)) {
            $this->markRefunded($event, $payment);
        } else {
            $this->mark($event, PaymentEventStatus::Ignored, 'Unhandled event type.');
        }
    }

    /**
     * Find the Payment this event belongs to.
     *
     * The reference number is tried first because this application sets it
     * when it creates the checkout. The provider resource id is the fallback,
     * and it covers the session id as well as the payment id.
     */
    private function resolvePayment(PaymentEvent $event, PayMongoEventEnvelope $envelope): ?Payment
    {
        $payment = null;

        if ($envelope->referenceNumber !== null) {
            $payment = Payment::query()->where('idempotency_key', $envelope->referenceNumber)->first();
        }

        if ($payment === null && $envelope->resourceId !== null) {
            $payment = Payment::query()
                ->where('provider_checkout_id', $envelope->resourceId)
                ->orWhere('provider_payment_id', $envelope->resourceId)
                ->first();
        }

        if ($payment !== null) {
            $event->forceFill(['payment_id' => $payment->id])->save();
        }

        return $payment;
    }

    /**
     * A paid event activates the enrollment in the same transaction as the
     * payment, so a Student is never left active without a paid record.
     */
    private function markPaid(PaymentEvent $event, Payment $payment, PayMongoEventEnvelope $envelope): void
    {
        DB::transaction(function () use ($event, $payment, $envelope): void {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);

            if ($locked === null) {
                $this->mark($event, PaymentEventStatus::Ignored, 'Payment vanished.');

                return;
            }

            if ($locked->status === PaymentStatus::Paid) {
                $this->mark($event, PaymentEventStatus::Processed, 'Already paid.');

                return;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'provider_payment_id' => $envelope->providerPaymentId ?? $locked->provider_payment_id,
                'paid_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);
            $locked->save();

            $enrollment = $locked->enrollment;

            if ($enrollment !== null && $enrollment->status === EnrollmentStatus::PendingPayment) {
                $enrollment->forceFill([
                    'status' => EnrollmentStatus::Active,
                    'activated_at' => now(),
                ]);
                $enrollment->save();
            }

            $this->mark($event, PaymentEventStatus::Processed, 'Payment settled and enrollment activated.');
        });
    }

    /**
     * The enrollment stays pending_payment, so the Student can retry.
     */
    private function markFailed(PaymentEvent $event, Payment $payment, PayMongoEventEnvelope $envelope): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_code' => $envelope->failureCode ?? 'failed',
            'failure_message' => $envelope->failureMessage
                ?? 'The provider reported a failed payment.',
        ]);
        $payment->save();

        $this->mark($event, PaymentEventStatus::Processed, 'Payment failed; enrollment stays pending.');
    }

    private function markRefunded(PaymentEvent $event, Payment $payment): void
    {
        DB::transaction(function () use ($event, $payment): void {
            $payment->forceFill([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
            ]);
            $payment->save();

            $enrollment = $payment->enrollment;

            if ($enrollment !== null && $enrollment->status === EnrollmentStatus::Active) {
                $enrollment->forceFill(['status' => EnrollmentStatus::Cancelled, 'cancelled_at' => now()]);
                $enrollment->save();
            }

            $this->mark($event, PaymentEventStatus::Processed, 'Payment refunded; access removed.');
        });
    }

    private function mark(PaymentEvent $event, PaymentEventStatus $status, ?string $error = null): void
    {
        $event->forceFill([
            'processing_status' => $status,
            'processing_error' => $error,
            'processed_at' => now(),
        ]);
        $event->save();
    }
}
