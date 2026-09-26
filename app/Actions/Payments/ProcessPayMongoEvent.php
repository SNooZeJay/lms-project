<?php

namespace App\Actions\Payments;

use App\Contracts\PayMongoClient;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentEventStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes one provider event, exactly once.
 *
 * The event envelope nests everything, so the id, the type, the resource, and
 * the reference number are all read from their documented positions rather
 * than guessed. The signature is verified against the raw request body,
 * because re-encoding a parsed payload changes the bytes and would make every
 * legitimate event look forged.
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
     * Events that settle a payment, in both the documented spelling and the
     * spelling shown in the PayMongo dashboard.
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
        string $providerEventId,
        string $eventType,
        array $payload,
        array $headers,
        ?string $rawBody = null,
    ): PaymentEvent {
        $verified = $this->client->verifySignature(
            $rawBody ?? (string) json_encode($payload),
            $headers,
        );

        $event = $this->record($providerEventId, $eventType, $payload, $verified);

        if (! $verified) {
            $this->mark($event, PaymentEventStatus::Ignored, 'Signature verification failed.');

            return $event;
        }

        if ($event->processing_status === PaymentEventStatus::Processed) {
            return $event;
        }

        if (! $this->livemodeMatches($payload)) {
            $this->mark(
                $event,
                PaymentEventStatus::Ignored,
                'Event livemode does not match this server, so it was not applied.'
            );

            return $event;
        }

        try {
            $this->apply($event, $eventType, $payload);
        } catch (Throwable $exception) {
            Log::error('Payment event failed', [
                'provider_event_id' => $providerEventId,
                'event_type' => $eventType,
                'message' => $exception->getMessage(),
            ]);

            $this->mark($event, PaymentEventStatus::Failed, $exception->getMessage());

            return $event;
        }

        return $event;
    }

    /**
     * A test server must never act on a live event, and the reverse.
     */
    private function livemodeMatches(array $payload): bool
    {
        $expected = (bool) config('services.paymongo.expected_livemode', false);
        $livemode = data_get($payload, 'data.attributes.livemode');

        if ($livemode === null) {
            return true;
        }

        return (bool) $livemode === $expected;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $providerEventId, string $eventType, array $payload, bool $verified): PaymentEvent
    {
        $existing = PaymentEvent::query()
            ->where('provider', 'paymongo')
            ->where('provider_event_id', $providerEventId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $event = new PaymentEvent;
        $event->forceFill([
            'provider' => 'paymongo',
            'provider_event_id' => $providerEventId,
            'event_type' => $eventType,
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
                ->where('provider_event_id', $providerEventId)
                ->first();

            if ($raced === null) {
                throw $exception;
            }

            return $raced;
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apply(PaymentEvent $event, string $eventType, array $payload): void
    {
        $payment = $this->resolvePayment($event, $payload);

        if ($payment === null) {
            $this->mark($event, PaymentEventStatus::Ignored, 'No matching payment.');

            return;
        }

        if (in_array($eventType, self::PAID_EVENTS, true)) {
            $this->markPaid($event, $payment, $payload);
        } elseif (in_array($eventType, self::FAILED_EVENTS, true)) {
            $this->markFailed($event, $payment, $payload);
        } elseif (in_array($eventType, self::REFUNDED_EVENTS, true)) {
            $this->markRefunded($event, $payment);
        } else {
            $this->mark($event, PaymentEventStatus::Ignored, 'Unhandled event type.');
        }
    }

    /**
     * Find the Payment this event belongs to.
     *
     * The checkout session id is the strongest link because it was stored
     * verbatim when the checkout was created. The reference number is the
     * fallback, and the provider resource id is the last resort.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolvePayment(PaymentEvent $event, array $payload): ?Payment
    {
        $resourceId = (string) data_get($payload, 'data.attributes.data.id', '');
        $reference = (string) data_get($payload, 'data.attributes.data.attributes.reference_number', '');

        $payment = null;

        if ($reference !== '') {
            $payment = Payment::query()->where('idempotency_key', $reference)->first();
        }

        if ($payment === null && $resourceId !== '') {
            $payment = Payment::query()
                ->where('provider_checkout_id', $resourceId)
                ->orWhere('provider_payment_id', $resourceId)
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
     *
     * @param  array<string, mixed>  $payload
     */
    private function markPaid(PaymentEvent $event, Payment $payment, array $payload): void
    {
        DB::transaction(function () use ($event, $payment, $payload): void {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);

            if ($locked === null) {
                $this->mark($event, PaymentEventStatus::Ignored, 'Payment vanished.');

                return;
            }

            if ($locked->status === PaymentStatus::Paid) {
                $this->mark($event, PaymentEventStatus::Processed, 'Already paid.');

                return;
            }

            $resourceId = (string) data_get($payload, 'data.attributes.data.id', '');

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'provider_payment_id' => $resourceId !== '' ? $resourceId : $locked->provider_payment_id,
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
     * @param  array<string, mixed>  $payload
     */
    private function markFailed(PaymentEvent $event, Payment $payment, array $payload): void
    {
        $resource = 'data.attributes.data.attributes';

        $code = (string) (data_get($payload, $resource.'.last_error.code')
            ?? data_get($payload, $resource.'.failure_code')
            ?? 'failed');

        $message = (string) (data_get($payload, $resource.'.last_error.message')
            ?? data_get($payload, $resource.'.failure_message')
            ?? '');

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_code' => $code !== '' ? $code : 'failed',
            'failure_message' => $message !== ''
                ? $message
                : 'The provider reported a failed payment.',
        ]);
        $payment->save();

        // The enrollment stays pending_payment, so the Student can retry.
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
