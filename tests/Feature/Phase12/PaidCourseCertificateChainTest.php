<?php

namespace Tests\Feature\Phase12;

use App\Contracts\PayMongoClient;
use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePayMongoClient;
use Tests\Support\PayMongoEventFactory;
use Tests\TestCase;

/**
 * The whole chain a paying Student walks, from money to certificate.
 *
 * The Phase 10 tests all use a free Course, and the Phase 12 tests stop at an
 * active enrollment. Neither covers the join, so nothing asserted that a paid
 * Course actually produces a certificate, or that an unpaid enrollment cannot
 * produce one by asking. Both halves matter: the first is what a panel clicks
 * through, the second is the only thing standing between a Student and a
 * certificate they did not pay for.
 */
class PaidCourseCertificateChainTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_paid_certificate_chain';

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

    public function test_a_paid_course_issues_a_certificate_only_after_payment_settles(): void
    {
        [$student, $course, $lesson] = $this->paidCourse();

        // 1. The Student enrolls. Nothing is unlocked yet.
        $this->actingAs($student)
            ->from('/courses')
            ->post("/student/courses/{$course->id}/enroll")
            ->assertRedirect();

        $enrollment = Enrollment::query()->firstOrFail();

        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->status);

        // The content and the claim are both refused while the money is owed.
        // The courses list never offers a link to the locked page, so this is
        // only reachable by typing the URL, and refusal is the right answer.
        $this->actingAs($student)
            ->get("/student/courses/{$course->id}")
            ->assertForbidden();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/complete")
            ->assertForbidden();

        $this->assertSame(0, Certificate::query()->count());

        // 2. The Student starts a checkout and is sent to the provider.
        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/checkout")
            ->assertRedirect('https://checkout.test/enrollment-'.$enrollment->id);

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentStatus::Pending, $payment->status);

        // 3. The provider confirms the payment out of band.
        $this->postProviderEvent($this->paidEvent($payment));

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);

        // 4. Now the content opens, and the Student can finish the lesson.
        $this->actingAs($student)
            ->get("/student/courses/{$course->id}")
            ->assertOk()
            ->assertSee($lesson->title);

        $this->actingAs($student)
            ->from("/student/courses/{$course->id}/lessons/{$lesson->id}")
            ->post("/student/courses/{$course->id}/lessons/{$lesson->id}/complete")
            ->assertRedirect();

        $this->assertSame(
            LessonProgressStatus::Completed,
            LessonProgress::query()->firstOrFail()->status
        );

        // 5. The Student claims the certificate.
        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/complete")
            ->assertRedirect();

        $certificate = Certificate::query()->firstOrFail();

        $this->assertSame($student->id, $certificate->student_id);
        $this->assertSame($course->id, $certificate->course_id);
        $this->assertSame(CertificateStatus::Issued, $certificate->status);
        $this->assertTrue($certificate->isValid());
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->fresh()->status);

        // 6. And it is readable, once, by its owner.
        $this->actingAs($student)
            ->get("/student/certificates/{$certificate->id}")
            ->assertOk()
            ->assertSee($certificate->certificate_code)
            ->assertSee($course->title);

        $this->actingAs($student)
            ->get('/student/certificates')
            ->assertOk()
            ->assertSee($course->title);
    }

    public function test_a_failed_payment_never_reaches_a_certificate(): void
    {
        [$student, $course, $lesson] = $this->paidCourse();

        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");

        $enrollment = Enrollment::query()->firstOrFail();

        $this->actingAs($student)->post("/student/courses/{$course->id}/checkout");

        $payment = Payment::query()->firstOrFail();

        $this->postProviderEvent($this->failedEvent($payment));

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);

        // A failed payment leaves the enrollment pending, not cancelled, so the
        // Student can retry. Either way it grants nothing, so no certificate.
        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->fresh()->status);

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}")
            ->assertForbidden();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/complete")
            ->assertForbidden();

        $this->assertSame(0, Certificate::query()->count());

        // Retrying is allowed, and a later success still completes the chain.
        $this->actingAs($student)->post("/student/courses/{$course->id}/checkout");
        $retry = Payment::query()->latest('id')->firstOrFail();

        $this->postProviderEvent($this->paidEvent($retry));

        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);

        $this->actingAs($student)->post($this->lessonCompleteUrl($course, $lesson->id));
        $this->actingAs($student)->post("/student/courses/{$course->id}/complete")->assertRedirect();

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_a_student_cannot_claim_by_sending_certificate_fields_directly(): void
    {
        [$student, $course] = $this->paidCourse();

        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");
        $enrollment = Enrollment::query()->firstOrFail();
        $this->actingAs($student)->post("/student/courses/{$course->id}/checkout");
        $this->postProviderEvent($this->paidEvent(Payment::query()->firstOrFail()));

        // The Student marks progress and then forges the issuance itself.
        $this->actingAs($student)->post($this->lessonCompleteUrl($course, $course->lessons()->firstOrFail()->id));

        $this->actingAs($student)->post("/student/courses/{$course->id}/complete", [
            'certificate_code' => 'FORGED',
            'status' => 'issued',
            'student_id' => 999999,
            'completion_date' => '1999-01-01',
        ])->assertRedirect();

        $certificate = Certificate::query()->firstOrFail();

        $this->assertNotSame('FORGED', $certificate->certificate_code);
        $this->assertSame($student->id, $certificate->student_id);
        $this->assertSame($student->id, $certificate->issued_by);
    }

    public function test_a_second_claim_returns_the_same_certificate(): void
    {
        [$student, $course, $lesson] = $this->paidCourse();

        $this->actingAs($student)->post("/student/courses/{$course->id}/enroll");
        $this->actingAs($student)->post("/student/courses/{$course->id}/checkout");
        $this->postProviderEvent($this->paidEvent(Payment::query()->firstOrFail()));
        $this->actingAs($student)->post($this->lessonCompleteUrl($course, $lesson->id));

        $this->actingAs($student)->post("/student/courses/{$course->id}/complete");
        $code = Certificate::query()->firstOrFail()->certificate_code;

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/complete")
            ->assertRedirect()
            ->assertSessionHas('status', 'You already completed this course.');

        $this->assertSame(1, Certificate::query()->count());
        $this->assertSame($code, Certificate::query()->firstOrFail()->certificate_code);
    }

    /**
     * @return array{0: User, 1: Course, 2: Lesson}
     */
    private function paidCourse(): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 125000,
            'currency' => 'PHP',
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'position' => 1,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);

        return [User::factory()->create(), $course, $lesson];
    }

    private function lessonCompleteUrl(Course $course, int $lessonId): string
    {
        return "/student/courses/{$course->id}/lessons/{$lessonId}/complete";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postProviderEvent(array $payload): void
    {
        $raw = (string) json_encode($payload);

        $this->call(
            'POST',
            '/webhooks/paymongo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => PayMongoEventFactory::signatureHeadersForRawBody($raw, $this->webhookSecret)['Paymongo-Signature'],
            ],
            $raw
        )->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function paidEvent(Payment $payment): array
    {
        return $this->event($payment, 'checkout_session.payment.paid', 'paid');
    }

    /**
     * @return array<string, mixed>
     */
    private function failedEvent(Payment $payment): array
    {
        return $this->event($payment, 'payment.failed', 'failed');
    }

    /**
     * @return array<string, mixed>
     */
    private function event(Payment $payment, string $eventType, string $status): array
    {
        return [
            'event_type' => 'send.webhook',
            'data' => [
                'type' => $eventType,
                'resource' => $eventType === 'payment.failed' ? 'payment' : 'checkout_session',
                'livemode' => false,
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
                'data' => [
                    'id' => $payment->provider_checkout_id,
                    'type' => $eventType === 'payment.failed' ? 'payment' : 'checkout_session',
                    'attributes' => [
                        'livemode' => false,
                        'reference_number' => $payment->idempotency_key,
                        'external_reference_number' => $payment->idempotency_key,
                        'payments' => [
                            [
                                'id' => 'pay_CHAIN_ABC',
                                'type' => 'payment',
                                'attributes' => [
                                    'amount' => $payment->amount_minor,
                                    'currency' => 'PHP',
                                    'status' => $status,
                                    'source' => ['type' => 'qrph'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
