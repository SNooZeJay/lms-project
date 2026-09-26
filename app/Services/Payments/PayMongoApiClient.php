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
    private const BASE_URL = 'https://api.paymongo.com/v1';

    public function createCheckout(Payment $payment): array
    {
        $this->guardEnabled();

        try {
            $response = Http::withBasicAuth($this->secretKey(), '')
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post(self::BASE_URL.'/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'billing' => [
                                'email' => $payment->student?->email,
                                'name' => $payment->student?->name,
                            ],
                            'description' => 'Course enrollment payment',
                            'line_items' => [
                                [
                                    'currency' => $payment->currency,
                                    'amount' => (int) $payment->amount_minor,
                                    'name' => (string) $payment->course?->title,
                                    'quantity' => 1,
                                ],
                            ],
                            'payment_method_types' => ['card'],
                            // The provider requires a real boolean here. A string
                            // "true" is rejected with invalid_request_body.
                            'show_description' => true,
                            'success_url' => route('student.payments.return', $payment->course),
                            'cancel_url' => route('student.payments.return', $payment->course),
                            'metadata' => [
                                'reference_number' => $payment->idempotency_key,
                            ],
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
            return false;
        }

        $signature = $this->header($headers, 'paymongo-signature');

        if ($signature === '') {
            return false;
        }

        $signature = trim($signature, " \t\n\r\0\x0B");

        if ($signature === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($computed, $signature);
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
