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

/**
 * Processes one provider event, exactly once.
 *
 * The provider event id is unique, so a replayed delivery is recorded as
 * ignored and changes nothing. An unverified signature is recorded and
 * discarded without touching any state. Activating an enrollment happens in
 * one transaction with the payment.
 */
class ProcessPayMongoEvent
{
    public function __construct(private readonly PayMongoClient $client) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function handle(string $providerEventId, string $eventType, array $payload, array $headers): PaymentEvent
    {
        $verified = $this->client->verifySignature(
            (string) json_encode($payload),
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

        try {
            $this->apply($event, $eventType, $payload);
        } catch (\Throwable $exception) {
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

        match ($eventType) {
            'checkout.payment.paid' => $this->markPaid($event, $payment, $payload),
            'checkout.payment.failed' => $this->markFailed($event, $payment, $payload),
            'checkout.payment.cancelled' => $this->markCancelled($event, $payment),
            'refund.paid' => $this->markRefunded($event, $payment),
            default => $this->mark($event, PaymentEventStatus::Ignored, 'Unhandled event type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePayment(PaymentEvent $event, array $payload): ?Payment
    {
        $reference = (string) ($payload['data']['attributes']['reference_number'] ?? $payload['reference_number'] ?? '');

        $payment = null;

        if ($reference !== '') {
            $payment = Payment::query()->where('idempotency_key', $reference)->first();
        }

        $providerId = (string) ($payload['data']['id'] ?? '');

        if ($payment === null && $providerId !== '') {
            $payment = Payment::query()->where('provider_payment_id', $providerId)->first();
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
            $payment = Payment::query()->lockForUpdate()->find($payment->id);

            if ($payment === null) {
                $this->mark($event, PaymentEventStatus::Ignored, 'Payment vanished.');

                return;
            }

            if ($payment->status === PaymentStatus::Paid) {
                $this->mark($event, PaymentEventStatus::Processed, 'Already paid.');

                return;
            }

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'provider_payment_id' => (string) ($payload['data']['id'] ?? $payment->provider_payment_id),
                'paid_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);
            $payment->save();

            $enrollment = $payment->enrollment;

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
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_code' => (string) ($payload['data']['attributes']['failure_code'] ?? 'failed'),
            'failure_message' => (string) ($payload['data']['attributes']['failure_message'] ?? 'The provider reported a failed payment.'),
        ]);
        $payment->save();

        // The enrollment stays pending_payment, so the Student can retry.
        $this->mark($event, PaymentEventStatus::Processed, 'Payment failed; enrollment stays pending.');
    }

    private function markCancelled(PaymentEvent $event, Payment $payment): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        $payment->save();

        $this->mark($event, PaymentEventStatus::Processed, 'Payment cancelled; enrollment stays pending.');
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
