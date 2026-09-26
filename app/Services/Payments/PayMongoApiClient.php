<?php

namespace App\Services\Payments;

use App\Contracts\PayMongoClient;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The live PayMongo client.
 *
 * Credentials stay in server-only configuration and are never rendered,
 * logged, or written to a payment record. Signature verification is a constant
 * time comparison, and a failure is never treated as a pass.
 */
class PayMongoApiClient implements PayMongoClient
{
    private const BASE_URL = 'https://api.paymongo.com/v2';

    public function createCheckout(Payment $payment): array
    {
        $this->guardEnabled();

        $methods = (array) config('services.paymongo.payment_method_types', ['qrph', 'card']);

        try {
            $response = Http::withBasicAuth($this->secretKey(), '')
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post(self::BASE_URL.'/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            // The hosted page renders this under the amount,
                            // which makes it the one place the merchant can be
                            // named. The name in the corner comes from the
                            // provider account profile. The create endpoint
                            // accepts a branding block and a merchant name
                            // without complaint and then stores neither, so
                            // neither of those can be overridden.
                            'description' => 'IT Learning Hub — course enrollment',
                            'line_items' => [
                                [
                                    'currency' => $payment->currency,
                                    'amount' => (int) $payment->amount_minor,
                                    'name' => (string) $payment->course?->title,
                                    'quantity' => 1,
                                ],
                            ],
                            'payment_method_types' => $methods !== [] ? $methods : ['qrph'],
                            // The provider requires a real boolean here. A string
                            // "true" is rejected with invalid_request_body.
                            'show_description' => true,
                            'success_url' => route('student.payments.return', $payment->course),
                            'cancel_url' => route('student.payments.return', $payment->course),
                            // This is the correlation key the webhook echoes back.
                            'reference_number' => $payment->idempotency_key,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::error('PayMongo checkout connection failed', ['message' => $exception->getMessage()]);

            throw new RuntimeException('The payment provider could not be reached.', 0, $exception);
        }

        if (! $response->successful()) {
            Log::error('PayMongo checkout rejected', ['status' => $response->status()]);

            throw new RuntimeException('The payment provider rejected the checkout.');
        }

        $data = (array) $response->json('data', []);
        $id = (string) ($data['id'] ?? '');
        $url = (string) ($data['attributes']['checkout_url'] ?? '');

        if ($id === '' || $url === '') {
            throw new RuntimeException('The payment provider returned an incomplete checkout.');
        }

        return ['checkout_id' => $id, 'redirect_url' => $url];
    }

    public function verifySignature(string $rawBody, array $headers): bool
    {
        $secret = $this->webhookSecret();

        if ($secret === '') {
            $this->reportMismatch($rawBody, 'no secret is configured');

            return false;
        }

        $parts = $this->parseSignatureHeader($this->header($headers, 'paymongo-signature'));

        if ($parts === null) {
            $this->reportMismatch($rawBody, 'the Paymongo-Signature header was absent or malformed');

            return false;
        }

        // One slot is populated per mode: te for test, li for live. The slot
        // matching this server has to be the one that matches, so a live
        // signature is never accepted by a test deployment, or the reverse.
        $expected = (bool) config('services.paymongo.expected_livemode', false)
            ? $parts['li']
            : $parts['te'];

        if ($expected === '') {
            $this->reportMismatch($rawBody, 'the signature slot for this mode was empty');

            return false;
        }

        // The signed message is the timestamp, a period, then the untouched
        // request body.
        $computed = hash_hmac('sha256', $parts['t'].'.'.$rawBody, $secret);

        if (! hash_equals($computed, $expected)) {
            $this->reportMismatch($rawBody, 'the signature did not match', $expected, $computed);

            return false;
        }

        return true;
    }

    /**
     * Split the signature header into its timestamp and its two signature slots.
     *
     * The header looks like: t=1496734173,te=<hex>,li=
     *
     * The timestamp is deliberately not checked for freshness. PayMongo retries
     * a failed delivery up to twelve times with backoff, and every retry carries
     * the timestamp of the original event, so a freshness window would reject
     * exactly the deliveries an endpoint most needs to accept. Replay is already
     * prevented by recording the provider event id once.
     *
     * @return array{t: string, te: string, li: string}|null
     */
    private function parseSignatureHeader(string $header): ?array
    {
        $header = trim($header, " \t\n\r\0\x0B");

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

        if ($parsed['t'] === '' || ! ctype_digit($parsed['t'])) {
            return null;
        }

        return $parsed;
    }

    /**
     * Explain a failed verification in the log, because a webhook that silently
     * rejects every delivery cannot be diagnosed from the database alone.
     *
     * Only short prefixes of the digests are written. A digest prefix cannot be
     * used to forge anything without the secret, and the first few characters
     * are enough to tell the causes apart. The body digest is included so a body
     * altered in transit can be spotted, which the provider lists as a cause.
     */
    private function reportMismatch(
        string $rawBody,
        string $reason,
        ?string $received = null,
        ?string $computed = null,
    ): void {
        $short = static fn (?string $value): ?string => $value === null ? null : substr($value, 0, 16);

        Log::warning('PayMongo signature verification failed', [
            'reason' => $reason,
            'received_prefix' => $short($received),
            'computed_prefix' => $short($computed),
            'body_bytes' => strlen($rawBody),
            'body_sha256_prefix' => substr(hash('sha256', $rawBody), 0, 16),
            'secret_bytes' => strlen($this->webhookSecret()),
        ]);
    }

    private function guardEnabled(): void
    {
        if (! config('services.paymongo.enabled')) {
            throw new RuntimeException('Payments are not enabled on this server.');
        }
    }

    private function secretKey(): string
    {
        $key = (string) config('services.paymongo.secret_key');

        if ($key === '') {
            throw new RuntimeException('The payment provider secret key is not configured.');
        }

        return $key;
    }

    private function webhookSecret(): string
    {
        return (string) config('services.paymongo.webhook_secret');
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
