<?php

namespace Tests\Support;

use App\Contracts\PayMongoClient;
use App\Models\Payment;

/**
 * A PayMongo stand-in for tests. It records calls and never touches the
 * network, so the payment state machine is testable without credentials.
 */
class FakePayMongoClient implements PayMongoClient
{
    /** @var list<array{checkout_id: string, redirect_url: string}> */
    public array $checkouts = [];

    public bool $signatureValid = true;

    public function createCheckout(Payment $payment): array
    {
        $checkout = [
            'checkout_id' => 'chk_'.substr(hash('sha256', $payment->idempotency_key), 0, 12),
            'redirect_url' => 'https://checkout.test/'.$payment->idempotency_key,
        ];

        $this->checkouts[] = $checkout;

        return $checkout;
    }

    public function verifySignature(string $rawBody, array $headers): bool
    {
        return $this->signatureValid;
    }
}
