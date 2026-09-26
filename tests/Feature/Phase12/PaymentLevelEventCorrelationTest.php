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
use App\Services\Payments\PayMongoEventEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePayMongoClient;
use Tests\TestCase;

/**
 * A payment-level event carries no reference_number. It carries the value the
 * application passed at checkout creation under external_reference_number.
 *
 * Reading only reference_number made every payment.failed event look unmatched,
 * so a declined payment left the Student looking at "awaiting" with no
 * explanation and no retry. The payload in this file is the one a real Maya test
 * payment produced, with the identifiers replaced.
 */
class PaymentLevelEventCorrelationTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'test-signing-key-correlation';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PayMongoClient::class, new FakePayMongoClient);

        config([
            'services.paymongo.webhook_secret' => $this->webhookSecret,
            'services.paymongo.expected_livemode' => false,
        ]);
    }

    public function test_a_payment_failed_event_is_matched_by_external_reference_number(): void
    {
        [$student, $enrollment, $payment] = $this->pendingPayment();

        $envelope = PayMongoEventEnvelope::fromPayload(
            $this->failedPayload($payment),
            (string) json_encode($this->failedPayload($payment)),
        );

        $this->assertSame(
            $payment->idempotency_key,
            $envelope->referenceNumber,
            'The correlation key must come from external_reference_number when reference_number is absent.'
        );
    }

    public function test_a_failed_payment_marks_the_payment_and_keeps_access_locked(): void
    {
        [$student, $enrollment, $payment] = $this->pendingPayment();

        $this->postSigned('/webhooks/paymongo', $this->failedPayload($payment))
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $payment->refresh();
        $enrollment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame(
            EnrollmentStatus::PendingPayment,
            $enrollment->status,
            'A failed payment must never grant access.'
        );
    }

    public function test_a_failed_payment_records_the_provider_payment_id(): void
    {
        [$student, $enrollment, $payment] = $this->pendingPayment();

        $this->postSigned('/webhooks/paymongo', $this->failedPayload($payment))->assertOk();

        $this->assertSame('pay_FAILED_XYZ', $payment->fresh()->provider_payment_id);
    }

    public function test_a_paid_payment_level_event_can_also_settle(): void
    {
        // The same correlation key is present on payment.paid, so the endpoint
        // does not depend solely on the checkout session event arriving.
        [$student, $enrollment, $payment] = $this->pendingPayment();

        $payload = $this->failedPayload($payment);
        $payload['data']['attributes']['type'] = 'payment.paid';
        $payload['data']['attributes']['data']['attributes']['status'] = 'paid';

        $this->postSigned('/webhooks/paymongo', $payload)->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_a_processed_event_is_never_rewritten_by_a_later_forgery(): void
    {
        // An earlier version overwrote the recorded outcome of a settled event
        // when an unrelated forged request reused the same event id, which
        // destroys the audit trail.
        [$student, $enrollment, $payment] = $this->pendingPayment();

        $this->postSigned('/webhooks/paymongo', $this->failedPayload($payment))->assertOk();

        $before = PaymentEvent::query()->firstOrFail()->processing_error;

        $this->postForged('/webhooks/paymongo', $this->failedPayload($payment))->assertOk();

        $after = PaymentEvent::query()->firstOrFail();

        $this->assertSame(PaymentEventStatus::Processed, $after->processing_status);
        $this->assertSame($before, $after->processing_error, 'A forged replay must not rewrite history.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(string $uri, array $payload)
    {
        return $this->postRaw($uri, (string) json_encode($payload), FakePayMongoClient::signatureHeaders(
            (string) json_encode($payload),
            $this->webhookSecret,
        )['Paymongo-Signature']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postForged(string $uri, array $payload)
    {
        return $this->postRaw(
            $uri,
            (string) json_encode($payload),
            't='.time().',te='.str_repeat('f', 64).',li=',
        );
    }

    private function postRaw(string $uri, string $raw, string $signature)
    {
        return $this->call(
            'POST',
            $uri,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => $signature,
            ],
            $raw
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function failedPayload(Payment $payment): array
    {
        return [
            'data' => [
                'id' => 'evt_FAILED_1',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.failed',
                    'livemode' => false,
                    'created_at' => 1767225600,
                    'updated_at' => 1767225600,
                    'data' => [
                        'id' => 'pay_FAILED_XYZ',
                        'type' => 'payment',
                        'attributes' => [
                            'amount' => $payment->amount_minor,
                            'currency' => 'PHP',
                            'status' => 'failed',
                            'reference_number' => null,
                            'external_reference_number' => $payment->idempotency_key,
                            'payment_intent_id' => 'pi_FAILED_1',
                            'description' => 'Course enrollment payment',
                            'metadata' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: Payment}
     */
    private function pendingPayment(): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 10000,
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
            'amount_minor' => 10000,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'provider' => 'paymongo',
            'idempotency_key' => 'enrollment-'.$enrollment->id,
            'provider_checkout_id' => 'cs_FAILED_1',
        ]);
        $payment->save();

        return [$student, $enrollment, $payment];
    }
}
