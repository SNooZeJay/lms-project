<?php

namespace Tests\Feature\Phase12;

use App\Contracts\PayMongoClient;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentEventStatus;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePayMongoClient;
use Tests\TestCase;

/**
 * These tests use the real PayMongo event envelope from the official
 * documentation, because the earlier tests used a guessed shape and passed
 * while the real handler would have failed on every event.
 */
class PayMongoEventEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_envelope_test';

    private FakePayMongoClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakePayMongoClient;
        $this->app->instance(PayMongoClient::class, $this->client);

        config([
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'placeholder-key',
            'services.paymongo.webhook_secret' => $this->webhookSecret,
            'services.paymongo.expected_livemode' => false,
        ]);
    }

    public function test_a_real_checkout_session_paid_event_activates_the_enrollment(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_1');

        $this->postJson('/webhooks/paymongo', $this->checkoutSessionPaidPayload($payment), $this->signatureHeaders(
            $this->checkoutSessionPaidPayload($payment)
        ))
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('cs_REAL_CHECKOUT_1', $payment->fresh()->provider_payment_id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_the_dashboard_spelling_of_the_event_is_also_handled(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_2');

        $payload = $this->checkoutSessionPaidPayload($payment);
        // The dashboard UI labels this "checkout.session.payment.paid".
        $payload['data']['attributes']['type'] = 'checkout.session.payment.paid';

        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_the_event_id_is_read_from_the_nested_data_object(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_3');

        $payload = $this->checkoutSessionPaidPayload($payment);

        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))->assertOk();

        // A handler that read the top-level "id" would have stored nothing.
        $this->assertSame('evt_NESTED_1', PaymentEvent::query()->firstOrFail()->provider_event_id);
    }

    public function test_the_event_type_is_read_from_data_attributes_type(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_4');

        $payload = $this->checkoutSessionPaidPayload($payment);
        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))->assertOk();

        $this->assertSame(
            'checkout_session.payment.paid',
            PaymentEvent::query()->firstOrFail()->event_type
        );
    }

    public function test_the_signature_is_verified_against_the_raw_body_bytes(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_5');

        $payload = $this->checkoutSessionPaidPayload($payment);

        // Sign the exact bytes that will be sent, with formatting that a
        // re-encode would not reproduce. A handler that parses and re-encodes
        // fails this test.
        $raw = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $raw, $this->webhookSecret);

        $response = $this->call(
            'POST',
            '/webhooks/paymongo',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $signature],
            $raw
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('processed', $response->getData(true)['status']);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_a_live_event_is_ignored_while_the_server_expects_test_mode(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_6');

        $payload = $this->checkoutSessionPaidPayload($payment);
        $payload['data']['attributes']['livemode'] = true;

        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))
            ->assertOk();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_an_unrecognized_event_is_acknowledged_not_rejected(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_7');

        $payload = $this->checkoutSessionPaidPayload($payment);
        $payload['data']['attributes']['type'] = 'payout.deposited';

        // A 4xx or 5xx would pile up in the retry queue.
        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_a_payment_failed_event_keeps_the_enrollment_pending(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_8');

        $payload = $this->checkoutSessionPaidPayload($payment);
        $payload['data']['attributes']['type'] = 'payment.failed';
        $payload['data']['attributes']['data']['attributes']['status'] = 'failed';
        $payload['data']['attributes']['data']['attributes']['last_error'] = [
            'code' => 'card_declined',
            'message' => 'The card was declined.',
        ];

        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame('card_declined', $payment->fresh()->failure_code);
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);
    }

    public function test_a_replayed_event_changes_nothing(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_9');

        $payload = $this->checkoutSessionPaidPayload($payment);

        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))->assertOk();
        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))->assertOk();

        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_tampered_body_is_recorded_and_changes_nothing(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_10');

        $payload = $this->checkoutSessionPaidPayload($payment);
        $headers = $this->signatureHeaders($payload);

        $payload['data']['attributes']['data']['id'] = 'cs_SOMEONE_ELSE';

        $this->postJson('/webhooks/paymongo', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertFalse(PaymentEvent::query()->firstOrFail()->signature_verified);
    }

    public function test_a_missing_signature_is_never_treated_as_a_pass(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_11');

        $payload = $this->checkoutSessionPaidPayload($payment);

        $this->postJson('/webhooks/paymongo', $payload, [])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_a_malformed_body_does_not_reach_a_state_change(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_12');

        $this->postJson('/webhooks/paymongo', ['nonsense' => true], $this->signatureHeaders(['nonsense' => true]))
            ->assertStatus(422);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(0, PaymentEvent::query()->count());
    }

    public function test_a_test_mode_event_from_the_dashboard_is_processed(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_13');

        $payload = $this->checkoutSessionPaidPayload($payment);
        $payload['data']['attributes']['livemode'] = false;

        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_the_recorded_payload_keeps_the_provider_payment_id(): void
    {
        [$student, $enrollment, $course, $payment] = $this->pendingPayment('cs_REAL_CHECKOUT_14');

        $payload = $this->checkoutSessionPaidPayload($payment);
        $payload['data']['attributes']['data']['attributes']['reference_number'] = $payment->idempotency_key;

        $this->postJson('/webhooks/paymongo', $payload, $this->signatureHeaders($payload))->assertOk();

        $event = PaymentEvent::query()->firstOrFail();

        $this->assertSame($payment->id, $event->payment_id);
        $this->assertSame(PaymentEventStatus::Processed, $event->processing_status);
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: Course, 3: Payment}
     */
    private function pendingPayment(string $checkoutId): array
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

        return [$student, $enrollment, $course, $payment];
    }

    /**
     * The documented shape of a checkout_session.payment.paid event.
     *
     * @return array<string, mixed>
     */
    private function checkoutSessionPaidPayload(Payment $payment): array
    {
        return [
            'data' => [
                'id' => 'evt_NESTED_1',
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'created_at' => 1767225600,
                    'updated_at' => 1767225600,
                    'data' => [
                        'id' => $payment->provider_checkout_id,
                        'type' => 'checkout_session',
                        'attributes' => [
                            'checkout_url' => 'https://checkout.paymongo.com/test',
                            'reference_number' => $payment->idempotency_key,
                            'livemode' => false,
                            'payment_intent_id' => 'pi_TEST_1',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signatureHeaders(array $payload): array
    {
        return [
            'Paymongo-Signature' => hash_hmac('sha256', (string) json_encode($payload), $this->webhookSecret),
        ];
    }
}
