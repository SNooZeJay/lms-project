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
                            'description' => 'Course enrollment payment',
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
            $this->reportMismatch($rawBody, 'no secret is configured', []);

            return false;
        }

        $signature = trim($this->header($headers, 'paymongo-signature'), " \t\n\r\0\x0B");

        if ($signature === '') {
            $this->reportMismatch($rawBody, 'the Paymongo-Signature header was absent or empty', []);

            return false;
        }

        // The same HMAC in the encodings a signature could plausibly arrive in.
        // Accepting more than one encoding is not a loosening: each of them
        // still requires the secret, so a forgery stays just as impossible.
        $candidates = [
            'hex' => hash_hmac('sha256', $rawBody, $secret),
            'base64' => base64_encode(hash_hmac('sha256', $rawBody, $secret, true)),
        ];

        foreach ($candidates as $candidate) {
            if (hash_equals($candidate, $signature)) {
                return true;
            }
        }

        $this->reportMismatch($rawBody, 'the signature did not match', [
            'received' => $signature,
            ...$candidates,
        ]);

        return false;
    }

    /**
     * Explain a failed verification in the log, because a webhook that silently
     * rejects every delivery cannot be diagnosed from the database alone.
     *
     * Only short prefixes of the digests are written. A digest prefix cannot be
     * used to forge anything without the secret, and the first few characters
     * are all that is needed to tell the causes apart. The body digest is
     * included so a body altered in transit can be spotted, which is one of the
     * causes the provider documents.
     */
    private function reportMismatch(string $rawBody, string $reason, array $candidates): void
    {
        $short = static fn (?string $value): ?string => $value === null ? null : substr($value, 0, 16);

        Log::warning('PayMongo signature verification failed', [
            'reason' => $reason,
            'received_prefix' => isset($candidates['received']) ? $short($candidates['received']) : null,
            'hex_prefix' => isset($candidates['hex']) ? $short($candidates['hex']) : null,
            'base64_prefix' => isset($candidates['base64']) ? $short($candidates['base64']) : null,
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
