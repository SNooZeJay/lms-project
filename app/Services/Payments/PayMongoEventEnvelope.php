<?php

namespace App\Services\Payments;

/**
 * Reads a PayMongo webhook payload.
 *
 * PayMongo documents two envelope shapes for the same webhook endpoint. The
 * events reference nests the type and the resource under data.attributes,
 * while the Hosted Checkout reference puts them directly under data. Reading
 * only one shape means every event of the other product is rejected as
 * malformed, so both documented positions are read here.
 *
 * Nothing is guessed. Each field lists the exact paths the provider uses, and
 * the first non-empty one wins.
 */
final class PayMongoEventEnvelope
{
    /**
     * The event type, in the order the provider documents it.
     */
    private const TYPE_PATHS = [
        'data.attributes.type',
        'data.type',
    ];

    /**
     * The resource the event is about, such as a checkout session or payment.
     */
    private const RESOURCE_ID_PATHS = [
        'data.attributes.data.id',
        'data.data.id',
    ];

    /**
     * The value passed as reference_number when the session was created. This
     * is the correlation key this application relies on.
     *
     * A checkout session event carries it as reference_number. A payment level
     * event carries the same value as external_reference_number instead, so both
     * positions are read. Without the second, every payment.failed event looks
     * unmatched and a declined payment is never reflected anywhere.
     */
    private const REFERENCE_PATHS = [
        'data.attributes.data.attributes.reference_number',
        'data.data.attributes.reference_number',
        'data.attributes.data.attributes.external_reference_number',
        'data.data.attributes.external_reference_number',
    ];

    /**
     * A Hosted Checkout event carries a list of payment attempts instead of a
     * single payment.
     */
    private const PAYMENT_LIST_PATHS = [
        'data.attributes.data.attributes.payments',
        'data.data.attributes.payments',
    ];

    private const LIVEMODE_PATHS = [
        'data.attributes.livemode',
        'data.livemode',
    ];

    /**
     * Why a payment was declined. The provider reports this on the payment
     * resource, so it is read wherever that resource is nested.
     */
    private const FAILURE_CODE_PATHS = [
        'data.attributes.data.attributes.last_error.code',
        'data.data.attributes.last_error.code',
    ];

    private const FAILURE_MESSAGE_PATHS = [
        'data.attributes.data.attributes.last_error.message',
        'data.data.attributes.last_error.message',
    ];

    /**
     * A provider event id looks like evt_xxx. A resource id looks like cs_xxx,
     * pay_xxx or pi_xxx, and must never be stored as the event id or a
     * replayed delivery would collide with a different event.
     */
    private const EVENT_ID_CANDIDATES = [
        'data.id',
        'id',
    ];

    private const RESOURCE_PREFIXES = ['cs_', 'pay_', 'pi_', 'pm_'];

    private function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly ?string $resourceId,
        public readonly ?string $referenceNumber,
        public readonly ?string $providerPaymentId,
        public readonly ?bool $livemode,
        public readonly ?string $failureCode,
        public readonly ?string $failureMessage,
    ) {}

    /**
     * Returns null only when the payload carries no event type at all, which
     * means it is not a PayMongo event.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, string $rawBody): ?self
    {
        $eventType = self::firstString($payload, self::TYPE_PATHS);

        if ($eventType === null) {
            return null;
        }

        $resourceId = self::firstString($payload, self::RESOURCE_ID_PATHS);

        return new self(
            eventId: self::resolveEventId($payload, $rawBody),
            eventType: $eventType,
            resourceId: $resourceId,
            referenceNumber: self::firstString($payload, self::REFERENCE_PATHS),
            providerPaymentId: self::resolveProviderPaymentId($payload, $resourceId),
            livemode: self::resolveLivemode($payload),
            failureCode: self::firstString($payload, self::FAILURE_CODE_PATHS),
            failureMessage: self::firstString($payload, self::FAILURE_MESSAGE_PATHS),
        );
    }

    /**
     * The provider event id, used to reject a replayed delivery.
     *
     * Some documented payloads carry no event id. A retry re-sends the same
     * body byte for byte, so hashing the body gives the same key for a replay
     * and a different key for a different event.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function resolveEventId(array $payload, string $rawBody): string
    {
        foreach (self::EVENT_ID_CANDIDATES as $path) {
            $value = self::firstString($payload, [$path]);

            if ($value === null || self::looksLikeResourceId($value)) {
                continue;
            }

            return $value;
        }

        return 'sha256:'.hash('sha256', $rawBody);
    }

    private static function looksLikeResourceId(string $value): bool
    {
        foreach (self::RESOURCE_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The pay_xxx identifier, which is what belongs in provider_payment_id.
     *
     * A Hosted Checkout event is about a session, so its resource id starts
     * with cs_. The real payment id is inside the payments list.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function resolveProviderPaymentId(array $payload, ?string $resourceId): ?string
    {
        foreach (self::PAYMENT_LIST_PATHS as $path) {
            $payments = data_get($payload, $path);

            if (! is_array($payments)) {
                continue;
            }

            foreach ($payments as $payment) {
                $id = is_array($payment) ? ($payment['id'] ?? null) : null;

                if (is_string($id) && $id !== '') {
                    return $id;
                }
            }
        }

        if ($resourceId !== null && str_starts_with($resourceId, 'pay_')) {
            return $resourceId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveLivemode(array $payload): ?bool
    {
        foreach (self::LIVEMODE_PATHS as $path) {
            $value = data_get($payload, $path);

            if (is_bool($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $paths
     */
    private static function firstString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
