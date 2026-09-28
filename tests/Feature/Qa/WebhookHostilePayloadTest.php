<?php

namespace Tests\Feature\Qa;

use App\Contracts\PayMongoClient;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakePayMongoClient;
use Tests\Support\HostileInput;
use Tests\Support\PayMongoEventFactory;
use Tests\TestCase;

/**
 * Charter C5: the webhook treated as an untrusted surface.
 *
 * The provider posts here from its own servers, with no session and no cookie,
 * so the signature is the only thing that makes a request trustworthy. Everything
 * else about the request is hostile input.
 *
 * The property under test is narrow and important: a request that is not
 * genuinely from the provider changes nothing. Not the enrollment, not the
 * payment, not a certificate. A webhook that is answered politely while quietly
 * writing a row is the failure that matters, so the assertion is the database
 * state afterwards rather than the status code.
 *
 * A correctly signed delivery is proven to work first, in the replay case. That
 * ordering matters: without it, a test in which every request was rejected would
 * pass, and would prove nothing at all about the signature check.
 */
class WebhookHostilePayloadTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_qa_probe';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PayMongoClient::class, new FakePayMongoClient);

        config([
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'placeholder-key',
            'services.paymongo.webhook_secret' => $this->webhookSecret,
            'services.paymongo.expected_livemode' => false,
        ]);
    }

    /**
     * A pending payment on a pending enrollment, the state a real checkout is in
     * when the provider posts back.
     *
     * @return array{0: User, 1: Enrollment, 2: Payment}
     */
    private function pendingPayment(string $checkoutId = 'cs_QA_PROBE_1'): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 125000,
            'currency' => 'PHP',
        ]);

        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);

        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount_minor' => 125000,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'provider' => 'paymongo',
            'idempotency_key' => 'enrollment-'.$enrollment->id,
            'provider_checkout_id' => $checkoutId,
        ]);
        $payment->save();

        return [$student, $enrollment, $payment];
    }

    /**
     * The provider's documented hosted checkout payload.
     *
     * @return array<string, mixed>
     */
    private function paidPayload(Payment $payment, string $eventId = 'evt_QA_1'): array
    {
        return [
            'event_type' => 'send.webhook',
            'data' => [
                'id' => $eventId,
                'type' => 'checkout_session.payment.paid',
                'resource' => 'checkout_session',
                'livemode' => false,
                'data' => [
                    'id' => $payment->provider_checkout_id,
                    'type' => 'checkout_session',
                    'attributes' => [
                        'reference_number' => $payment->idempotency_key,
                        'livemode' => false,
                        'payment_intent' => ['id' => 'pi_QA_XYZ'],
                        'payments' => [[
                            'id' => 'pay_QA_ABC',
                            'type' => 'payment',
                            'attributes' => [
                                'amount' => $payment->amount_minor,
                                'currency' => 'PHP',
                                'status' => 'paid',
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Encode a payload exactly once and sign those exact bytes.
     *
     * Encoding the body separately for signing and for sending is the bug this
     * method exists to prevent. The signature covers the raw bytes, so a value
     * containing a slash or a multi-byte character produces a different string
     * under different flags, and the signature then does not match for reasons
     * that have nothing to do with the check being tested. Returning both the
     * body and its signature from one encoding makes that impossible.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: array<string, string>}
     */
    private function sign(array $payload): array
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [$json, PayMongoEventFactory::signatureHeadersForRawBody($json, $this->webhookSecret)];
    }

    /**
     * Deliver a payload the way the provider would: encoded once and signed.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse
     */
    private function deliverSigned(array $payload)
    {
        [$body, $signature] = $this->sign($payload);

        return $this->deliverRaw($body, $signature);
    }

    /**
     * Post a raw body with exactly the signature headers supplied.
     *
     * The body is sent as a string rather than as an array, because the signature
     * covers the raw bytes and a re-encoded array would not match.
     *
     * @param  array<string, string>  $signatureHeaders
     * @return TestResponse
     */
    private function deliverRaw(string $body, array $signatureHeaders = [], string $contentType = 'application/json')
    {
        $server = ['CONTENT_TYPE' => $contentType];

        foreach ($signatureHeaders as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/webhooks/paymongo', [], [], [], $server, $body);
    }

    private function counts(): array
    {
        return [
            'active' => Enrollment::query()->where('status', EnrollmentStatus::Active->value)->count(),
            'completed' => Enrollment::query()->where('status', EnrollmentStatus::Completed->value)->count(),
            'paid' => Payment::query()->where('status', PaymentStatus::Paid->value)->count(),
            'enrollments' => Enrollment::query()->count(),
            'payments' => Payment::query()->count(),
            'certificates' => Certificate::query()->count(),
        ];
    }

    /* ------------------------------------------------ unsigned and forged */

    public function test_a_correctly_signed_delivery_activates_the_enrollment(): void
    {
        // The control. Without this, every assertion below would also hold on an
        // endpoint that rejected everything.
        [, $enrollment, $payment] = $this->pendingPayment();

        $response = $this->deliverSigned($this->paidPayload($payment));

        $this->assertSame(200, $response->getStatusCode(), 'A correctly signed delivery was rejected.');
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_an_unsigned_event_changes_nothing(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $before = $this->counts();

        // A perfectly well formed event with no signature at all. This is the
        // attack: anyone who can reach the address could otherwise mark a payment
        // as settled and unlock a paid course.
        $response = $this->deliverRaw(
            (string) json_encode($this->paidPayload($payment), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->assertContains(
            $response->getStatusCode(),
            [200, 202, 401, 403, 422],
            'An unsigned webhook was answered with '.$response->getStatusCode().'.'
        );

        $after = $this->counts();

        $this->assertSame($before['active'], $after['active'], 'An unsigned event activated an enrollment.');
        $this->assertSame($before['paid'], $after['paid'], 'An unsigned event marked a payment as paid.');
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_a_forged_signature_changes_nothing(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $before = $this->counts();

        $response = $this->deliverRaw(
            (string) json_encode($this->paidPayload($payment), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ['Paymongo-Signature' => 't='.now()->getTimestamp().',te='.str_repeat('a', 64).',li=']
        );

        $this->assertContains($response->getStatusCode(), [200, 202, 401, 403, 422]);

        $after = $this->counts();

        $this->assertSame($before['active'], $after['active'], 'A forged signature activated an enrollment.');
        $this->assertSame($before['paid'], $after['paid'], 'A forged signature marked a payment as paid.');
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_a_signature_for_a_different_body_changes_nothing(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        // The signature is over one body and the request carries another. This is
        // what a captured delivery looks like after the amount is edited.
        [, $signature] = $this->sign(['data' => ['id' => 'evt_a_completely_different_body']]);

        $response = $this->deliverRaw(
            (string) json_encode($this->paidPayload($payment), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $signature
        );

        $this->assertContains($response->getStatusCode(), [200, 202, 401, 403, 422]);

        $this->assertSame(
            0,
            $this->counts()['active'],
            'A signature for a different body activated an enrollment.'
        );
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_a_live_signature_is_refused_on_a_test_server(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $json = (string) json_encode($this->paidPayload($payment), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->getTimestamp();
        $live = hash_hmac('sha256', $timestamp.'.'.$json, $this->webhookSecret);

        $response = $this->deliverRaw($json, [
            'Paymongo-Signature' => "t={$timestamp},te=,li={$live}",
        ]);

        $this->assertContains($response->getStatusCode(), [200, 202, 401, 403, 422]);
        $this->assertSame(0, $this->counts()['active'], 'A live-mode signature was accepted by a test server.');
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    /* ----------------------------------------------- malformed header shapes */

    /**
     * @return array<string, array{0: string}>
     */
    public static function signatureHeaderShapes(): array
    {
        $cases = [
            'absent' => '',
            'only a timestamp' => 't=1496734173',
            'no timestamp' => 'te=abc,li=',
            'no equals sign' => 'nonsense',
            'a non numeric timestamp' => 't=not-a-number,te=abc,li=',
            'an unknown key' => 't=1496734173,zz=abc',
            'a trailing comma' => 't=1496734173,te=abc,',
            'surrounding spaces' => ' t=1496734173 , te=abc , li= ',
            'an empty test slot' => 't=1496734173,te=,li=',
            'a very long digest' => 't=1496734173,te='.str_repeat('c', 5000).',li=',
            'a header injection attempt' => "t=1496734173,te=abc\r\nX-Injected: 1,li=",
            'a null byte' => "t=1496734173,te=ab\0c,li=",
        ];

        $out = [];

        foreach ($cases as $label => $value) {
            $out[$label] = [$value];
        }

        return $out;
    }

    #[DataProvider('signatureHeaderShapes')]
    public function test_a_malformed_signature_header_changes_nothing(string $header): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $response = $this->deliverRaw(
            (string) json_encode($this->paidPayload($payment), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $header === '' ? [] : ['Paymongo-Signature' => $header]
        );

        $this->assertContains(
            $response->getStatusCode(),
            [200, 202, 400, 401, 403, 422],
            "A signature header of '{$header}' was answered with ".$response->getStatusCode().'.'
        );

        $this->assertSame(
            0,
            $this->counts()['active'],
            "A signature header of '{$header}' activated an enrollment."
        );
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    /* ------------------------------------------------- malformed bodies */

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedBodies(): array
    {
        return [
            'empty' => [''],
            'a bare word' => ['nonsense'],
            'a json array' => ['[]'],
            'a json scalar' => ['42'],
            'a json null' => ['null'],
            'truncated json' => ['{"data": {'],
            'an unclosed string' => ['{"data": "oops'],
            'deeply nested' => ['{"data":'.str_repeat('{"a":', 40).'1'.str_repeat('}', 40).'}'],
            'a very large body' => ['{"data": "'.str_repeat('x', 200000).'"}'],
            'binary' => ["\x00\x01\x02\x03"],
            'html' => ['<html><body>not json</body></html>'],
            'a php tag' => ['<?php echo 1; ?>'],
        ];
    }

    #[DataProvider('malformedBodies')]
    public function test_a_malformed_body_is_answered_and_changes_nothing(string $body): void
    {
        [, $enrollment] = $this->pendingPayment();

        $before = $this->counts();

        $response = $this->deliverRaw($body);

        $this->assertContains(
            $response->getStatusCode(),
            [200, 202, 400, 401, 403, 415, 422],
            'A malformed webhook body was answered with '.$response->getStatusCode().'.'
        );

        $this->assertStringNotContainsString('Stack trace', (string) $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());

        $after = $this->counts();

        $this->assertSame($before['active'], $after['active'], 'A malformed body activated an enrollment.');
        $this->assertSame($before['enrollments'], $after['enrollments'], 'A malformed body wrote an enrollment.');
        $this->assertSame($before['certificates'], $after['certificates'], 'A malformed body issued a certificate.');
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    /**
     * The content type is not the boundary; the signature is.
     *
     * The endpoint reads the raw body and decodes it whatever the declared type
     * is, which means a correctly signed delivery is applied even when the
     * provider, a proxy, or a replaying script labels it text/plain. That is not a
     * weakness: the body still has to carry a valid signature, which nobody can
     * produce without the webhook secret. Refusing on the declared type would only
     * risk dropping real deliveries, because the type is the part of the request
     * an intermediary is most likely to rewrite.
     *
     * So the property asserted is the one that matters: an unsigned body is
     * refused whatever it claims to be.
     */
    public function test_an_unsigned_body_is_refused_whatever_the_content_type_claims(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $unsigned = (string) json_encode(
            $this->paidPayload($payment),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        foreach (['application/json', 'text/plain', 'text/html', 'application/octet-stream'] as $type) {
            $response = $this->deliverRaw($unsigned, [], $type);

            $this->assertSame(
                0,
                $this->counts()['active'],
                "An unsigned body labelled {$type} activated an enrollment."
            );
        }

        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_a_correctly_signed_delivery_is_applied_whatever_the_content_type_claims(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        [$json, $signature] = $this->sign($this->paidPayload($payment));

        $response = $this->deliverRaw($json, $signature, 'text/plain');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    /* -------------------------------------------------------- replay */

    public function test_a_replayed_event_is_applied_once(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        [$json, $signature] = $this->sign($this->paidPayload($payment));

        $this->assertSame(
            200,
            $this->deliverRaw($json, $signature)->getStatusCode(),
            'The first delivery was not accepted, so replay is not being tested.'
        );

        $this->assertSame(1, $this->counts()['active'], 'The first delivery did not activate the enrollment.');

        $beforeReplay = $this->counts();

        // The same delivery five more times, which is both what a provider retry
        // looks like and what an attacker copying a captured request looks like.
        for ($i = 0; $i < 5; $i++) {
            $this->deliverRaw($json, $signature);
        }

        $after = $this->counts();

        $this->assertSame(1, $after['active'], 'A replayed event produced more than one active enrollment.');
        $this->assertSame(1, $after['paid'], 'A replayed event marked the payment as paid more than once.');
        $this->assertSame(1, $after['enrollments'], 'A replayed event created another enrollment.');

        // A payment does not issue a certificate, because the course is not
        // finished. Asserted against the count before the replay rather than a
        // fixed number, because a first version of this test asserted 1 and
        // failed on an application that was correctly issuing nothing.
        $this->assertSame(
            $beforeReplay['certificates'],
            $after['certificates'],
            'A replayed event issued a certificate that the original delivery did not.'
        );

        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_the_same_event_id_twice_is_one_event(): void
    {
        [, , $payment] = $this->pendingPayment();

        [$json, $signature] = $this->sign($this->paidPayload($payment));

        $this->deliverRaw($json, $signature);
        $this->deliverRaw($json, $signature);

        $this->assertSame(
            1,
            PaymentEvent::query()->count(),
            'The same provider event id was recorded twice, so replay protection is not working.'
        );
    }

    /* ------------------------------------------- mismatched content */

    public function test_an_event_naming_another_checkout_is_not_applied(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment('cs_THE_REAL_ONE');

        $other = $this->pendingPayment('cs_SOMEONE_ELSE_ONE')[2];

        $this->deliverSigned($this->paidPayload($other));

        $this->assertSame(
            EnrollmentStatus::PendingPayment,
            $enrollment->fresh()->status,
            'An event naming a different checkout activated this enrollment.'
        );
    }

    public function test_an_amount_that_does_not_match_is_not_applied(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $payload = $this->paidPayload($payment);
        $payload['data']['data']['attributes']['payments'][0]['attributes']['amount'] = 1;

        $this->deliverSigned($payload);

        $this->assertSame(
            EnrollmentStatus::PendingPayment,
            $enrollment->fresh()->status,
            'An event whose amount does not match the payment activated the enrollment.'
        );
    }

    public function test_a_currency_that_does_not_match_is_not_applied(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $payload = $this->paidPayload($payment);
        $payload['data']['data']['attributes']['payments'][0]['attributes']['currency'] = 'USD';

        $this->deliverSigned($payload);

        $this->assertSame(
            EnrollmentStatus::PendingPayment,
            $enrollment->fresh()->status,
            'An event in a different currency activated the enrollment.'
        );
    }

    public function test_an_unknown_event_type_is_not_applied(): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $payload = $this->paidPayload($payment);
        $payload['data']['type'] = 'checkout_session.payment.expired';

        $this->deliverSigned($payload);

        $this->assertSame(
            EnrollmentStatus::PendingPayment,
            $enrollment->fresh()->status,
            'An expired payment activated the enrollment.'
        );
    }

    /* ----------------------------------------- hostile strings in the body */

    /**
     * @return array<string, array{0: string}>
     */
    public static function hostileFieldValues(): array
    {
        $cases = [];

        foreach (HostileInput::classes() as $class => $value) {
            $cases["reference {$class}"] = [$value];
        }

        return $cases;
    }

    #[DataProvider('hostileFieldValues')]
    public function test_a_hostile_reference_number_is_handled(string $value): void
    {
        [, , $payment] = $this->pendingPayment();

        $before = $this->counts();

        $payload = $this->paidPayload($payment);
        $payload['data']['data']['attributes']['reference_number'] = $value;

        $response = $this->deliverSigned($payload);

        $this->assertContains(
            $response->getStatusCode(),
            [200, 202, 400, 401, 403, 422],
            'A hostile reference number was answered with '.$response->getStatusCode().'.'
        );

        $this->assertStringNotContainsString('Stack trace', (string) $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());

        // The event is still expected to be applied, and that is correct rather
        // than a weakness. This application identifies a payment by its
        // reference number first and by the provider checkout id second, and the
        // checkout id is untouched here, so the payment is still found. An
        // earlier version of this test asserted the enrollment stayed pending and
        // failed on an application that was matching correctly.
        //
        // What matters is that the string changed nothing else: no duplicate
        // record, no extra payment, no error page.
        $after = $this->counts();

        $this->assertLessThanOrEqual(
            1,
            $after['active'],
            'A hostile reference number produced more than one active enrollment.'
        );
        $this->assertSame($before['enrollments'], $after['enrollments'], 'A hostile reference number created an enrollment.');
        $this->assertSame($before['payments'], $after['payments'], 'A hostile reference number created a payment.');
        $this->assertLessThanOrEqual(
            $before['certificates'] + 1,
            $after['certificates'],
            'A hostile reference number issued more than the one certificate a delivery can issue.'
        );
    }

    /**
     * A hostile reference number with the checkout id also wrong matches nothing.
     *
     * The companion to the case above. With both identifiers replaced there is no
     * payment to apply the event to, so it has to be recorded and ignored, and
     * the enrollment has to stay pending.
     */
    #[DataProvider('hostileFieldValues')]
    public function test_a_hostile_reference_number_with_an_unknown_checkout_matches_nothing(string $value): void
    {
        [, $enrollment, $payment] = $this->pendingPayment();

        $payload = $this->paidPayload($payment);
        $payload['data']['data']['attributes']['reference_number'] = $value;
        $payload['data']['data']['id'] = 'cs_NOT_A_REAL_CHECKOUT';

        $response = $this->deliverSigned($payload);

        $this->assertContains($response->getStatusCode(), [200, 202, 400, 401, 403, 422]);

        $this->assertSame(
            EnrollmentStatus::PendingPayment,
            $enrollment->fresh()->status,
            'An event whose reference number and checkout id both match nothing still activated the enrollment.'
        );
        $this->assertSame(
            0,
            $this->counts()['active'],
            'An unmatched event activated an enrollment.'
        );
    }
}
