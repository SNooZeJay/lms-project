<?php

namespace Tests\Support;

use App\Contracts\PayMongoClient;
use App\Models\Payment;

/**
 * A PayMongo stand-in for tests. It never touches the network, so the payment
 * state machine is testable without credentials.
 *
 * Signature verification is real, not a stub. The fake computes the same
 * HMAC-SHA256 the provider computes, so a test can prove that a tampered body
 * or a missing signature is rejected. A stub that always returned true would
 * make every signature test meaningless.
 */
class FakePayMongoClient implements PayMongoClient
{
    /** @var list<array{checkout_id: string, redirect_url: string}> */
    public array $checkouts = [];

    /**
     * Force the verification result instead of computing it.
     *
     * null means compute the real HMAC.
     */
    public ?bool $forceSignatureResult = null;

    public ?string $signatureSecret = null;

    /**
     * @return array{checkout_id: string, redirect_url: string}
     */
    public function createCheckout(Payment $payment): array
    {
        $checkout = [
            'checkout_id' => 'cs_'.substr(hash('sha256', $payment->idempotency_key), 0, 24),
            'redirect_url' => 'https://checkout.test/'.$payment->idempotency_key,
        ];

        $this->checkouts[] = $checkout;

        return $checkout;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function verifySignature(string $rawBody, array $headers): bool
    {
        if ($this->forceSignatureResult !== null) {
            return $this->forceSignatureResult;
        }

        $secret = $this->signatureSecret ?? (string) config('services.paymongo.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signature = $this->header($headers, 'Paymongo-Signature');

        if ($signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), trim($signature));
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return '';
    }
}
