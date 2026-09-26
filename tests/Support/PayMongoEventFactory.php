<?php

namespace Tests\Support;

use App\Models\Payment;

/**
 * Builds real PayMongo webhook event envelopes.
 *
 * The envelope nests everything, and getting a position wrong is exactly the
 * kind of mistake that passes against a fake and fails against the real
 * provider. Every payload in the suite is built here from the documented
 * shape so there is one place to correct when PayMongo changes it.
 */
class PayMongoEventFactory
{
    /**
     * A checkout_session.payment.paid event, as PayMongo sends it after a
     * Student completes the hosted checkout page.
     *
     * @return array<string, mixed>
     */
    public static function checkoutSessionPaid(Payment $payment, string $eventId = 'evt_test_1'): array
    {
        return self::envelope($eventId, 'checkout_session.payment.paid', $payment, [
            'id' => $payment->provider_checkout_id ?: 'cs_test',
            'type' => 'checkout_session',
            'attributes' => [
                'checkout_url' => 'https://checkout.paymongo.com/test',
                'reference_number' => $payment->idempotency_key,
                'livemode' => false,
                'payment_intent_id' => 'pi_test_1',
            ],
        ]);
    }

    /**
     * A payment.failed event for the same checkout.
     *
     * @return array<string, mixed>
     */
    public static function paymentFailed(Payment $payment, string $eventId = 'evt_test_failed'): array
    {
        return self::envelope($eventId, 'payment.failed', $payment, [
            'id' => $payment->provider_checkout_id ?: 'cs_test',
            'type' => 'checkout_session',
            'attributes' => [
                'reference_number' => $payment->idempotency_key,
                'status' => 'failed',
                'last_error' => ['code' => 'card_declined', 'message' => 'The card was declined.'],
            ],
        ]);
    }

    /**
     * A payment.refunded event.
     *
     * @return array<string, mixed>
     */
    public static function paymentRefunded(Payment $payment, string $eventId = 'evt_test_refunded'): array
    {
        return self::envelope($eventId, 'payment.refunded', $payment, [
            'id' => $payment->provider_checkout_id ?: 'cs_test',
            'type' => 'checkout_session',
            'attributes' => [
                'reference_number' => $payment->idempotency_key,
                'status' => 'refunded',
            ],
        ]);
    }

    /**
     * The same envelope with any event type, for ignored-type and livemode
     * tests.
     *
     * @return array<string, mixed>
     */
    public static function envelope(
        string $eventId,
        string $eventType,
        Payment $payment,
        array $resource,
        bool $livemode = false,
    ): array {
        return [
            'data' => [
                'id' => $eventId,
                'type' => 'event',
                'attributes' => [
                    'type' => $eventType,
                    'livemode' => $livemode,
                    'created_at' => 1767225600,
                    'updated_at' => 1767225600,
                    'data' => $resource,
                ],
            ],
        ];
    }

    /**
     * The signature header PayMongo sends, computed over the raw body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function signatureHeaders(array $payload, ?string $secret = null): array
    {
        $secret ??= (string) config('services.paymongo.webhook_secret');

        return [
            'Paymongo-Signature' => hash_hmac('sha256', (string) json_encode($payload), $secret),
        ];
    }
}
