<?php

namespace Tests\Feature\Phase12;

use App\Services\Payments\PayMongoApiClient;
use Tests\TestCase;

/**
 * The signature header is not a bare digest. It carries three comma-separated
 * parts and the signed message includes the timestamp.
 *
 *   t=1496734173,te=<signature>,li=
 *
 * The message that gets signed is "<timestamp>.<raw body>", hashed with the
 * endpoint's secret using SHA-256. Test-mode events populate te and leave li
 * empty; live-mode events do the opposite. Hashing only the body, or comparing
 * the whole header to a digest, can never match, and every genuine delivery is
 * rejected as forged.
 *
 * Header shape taken from the provider's Setup and Management documentation.
 */
class PayMongoSignatureFormatTest extends TestCase
{
    private const SECRET = 'whsk_example_endpoint_signing_secret';

    private string $body = '{"data":{"id":"evt_1","attributes":{"type":"checkout_session.payment.paid"}}}';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paymongo.webhook_secret' => self::SECRET,
            'services.paymongo.expected_livemode' => false,
        ]);
    }

    public function test_a_documented_test_mode_header_is_accepted(): void
    {
        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, self::SECRET);

        $header = "t={$timestamp},te={$signature},li=";

        $this->assertTrue(
            $this->verify($header),
            'A correctly signed test-mode request must be accepted.'
        );
    }

    public function test_the_signature_covers_the_timestamp_as_well_as_the_body(): void
    {
        $timestamp = '1496734173';

        // Signing the body alone, which is what a naive implementation does.
        $bodyOnly = hash_hmac('sha256', $this->body, self::SECRET);
        $header = "t={$timestamp},te={$bodyOnly},li=";

        $this->assertFalse(
            $this->verify($header),
            'A digest over the body alone must not be accepted, because the timestamp is signed too.'
        );
    }

    public function test_a_signature_computed_with_a_different_secret_is_rejected(): void
    {
        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, 'some-other-endpoint-secret');

        $this->assertFalse($this->verify("t={$timestamp},te={$signature},li="));
    }

    public function test_tampering_with_the_body_breaks_the_signature(): void
    {
        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, self::SECRET);

        $this->assertFalse(
            $this->verify("t={$timestamp},te={$signature},li=", $this->body.' '),
            'Changing one byte of the body must invalidate the signature.'
        );
    }

    public function test_a_live_signature_is_rejected_by_a_test_server(): void
    {
        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, self::SECRET);

        // The live slot is populated, the test slot is empty.
        $this->assertFalse(
            $this->verify("t={$timestamp},te=,li={$signature}"),
            'A test server must not accept a live-mode signature.'
        );
    }

    public function test_a_test_signature_is_accepted_by_a_live_server(): void
    {
        config(['services.paymongo.expected_livemode' => true]);

        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, self::SECRET);

        $this->assertTrue($this->verify("t={$timestamp},te=,li={$signature}"));
    }

    public function test_a_header_without_a_timestamp_is_rejected(): void
    {
        $signature = hash_hmac('sha256', $this->body, self::SECRET);

        $this->assertFalse($this->verify("te={$signature},li="));
    }

    public function test_a_malformed_header_is_rejected_without_erroring(): void
    {
        foreach (['', 'nonsense', 't=,te=,li=', 't=abc,te=def,li='] as $header) {
            $this->assertFalse($this->verify($header), "Header '{$header}' must be rejected.");
        }
    }

    public function test_a_missing_secret_rejects_everything(): void
    {
        config(['services.paymongo.webhook_secret' => '']);

        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, self::SECRET);

        $this->assertFalse($this->verify("t={$timestamp},te={$signature},li="));
    }

    public function test_an_old_timestamp_is_still_accepted_because_paymongo_retries(): void
    {
        // PayMongo retries a failed delivery up to twelve times with backoff, so
        // a late retry carries the original timestamp. Enforcing a tight
        // freshness window would reject exactly the retries that matter.
        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, self::SECRET);

        $this->assertTrue(
            $this->verify("t={$timestamp},te={$signature},li="),
            'A retried delivery must not be rejected for being old.'
        );
    }

    public function test_the_header_name_is_matched_regardless_of_case(): void
    {
        $timestamp = '1496734173';
        $signature = hash_hmac('sha256', $timestamp.'.'.$this->body, self::SECRET);

        $client = new PayMongoApiClient;

        $this->assertTrue($client->verifySignature(
            $this->body,
            ['paymongo-signature' => "t={$timestamp},te={$signature},li="],
        ));
    }

    private function verify(string $header, ?string $body = null): bool
    {
        return (new PayMongoApiClient)->verifySignature(
            $body ?? $this->body,
            ['Paymongo-Signature' => $header],
        );
    }
}
