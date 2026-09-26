<?php

namespace Tests\Support;

use App\Contracts\PayMongoClient;
use App\Models\Payment;

/**
 * A PayMongo stand-in for tests. It never touches the network, so the payment
 * state machine is testable without credentials.
 *
 * Signature verification is real, not a stub. The fake parses and recomputes the
 * same header the provider sends, so a test proves something. A stub that always
 * returned true would make every signature test meaningless, and signing only
 * the body would have hidden the real defect.
 */
class FakePayMongoClient implements PayMongoClient
{
    /** @var list<array{checkout_id: string, redirect_url: string}> */
    public array $checkouts = [];

    /**
     * Force the verification result instead of computing it.
     *
     * null means recompute the real signature.
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
     * Build the header the provider actually sends.
     *
     * The header carries a timestamp and one signature per mode, and the signed
     * message is "<timestamp>.<raw body>".
     *
     * @return array<string, string>
     */
    public static function signatureHeaders(
        string $rawBody,
        ?string $secret = null,
        ?int $timestamp = null,
    ): array {
        $secret ??= (string) config('services.paymongo.webhook_secret');
        $asString = (string) ($timestamp ?? time());

        $signature = hash_hmac('sha256', $asString.'.'.$rawBody, $secret);

        $header = (bool) config('services.paymongo.expected_livemode', false)
            ? "t={$asString},te=,li={$signature}"
            : "t={$asString},te={$signature},li=";

        return ['Paymongo-Signature' => $header];
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

        $parsed = $this->parse($this->header($headers, 'Paymongo-Signature'));

        if ($parsed === null) {
            return false;
        }

        $expected = (bool) config('services.paymongo.expected_livemode', false)
            ? $parsed['li']
            : $parsed['te'];

        if ($expected === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $parsed['t'].'.'.$rawBody, $secret), $expected);
    }

    /**
     * @return array{t: string, te: string, li: string}|null
     */
    private function parse(string $header): ?array
    {
        $header = trim($header);

        if ($header === '') {
            return null;
        }

        $parsed = ['t' => '', 'te' => '', 'li' => ''];

        foreach (explode(',', $header) as $piece) {
            $piece = trim($piece);

            if (! str_contains($piece, '=')) {
                return null;
            }

            [$key, $value] = explode('=', $piece, 2);
            $key = strtolower(trim($key));

            if (! array_key_exists($key, $parsed)) {
                return null;
            }

            $parsed[$key] = trim($value);
        }

        if ($parsed['t'] === '') {
            return null;
        }

        return $parsed;
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
