<?php

namespace Tests\Feature\Phase11B;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Support\StudentPaymentState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Student who has paid needs to see, in one place, whether the money landed,
 * what went wrong if it did not, and what to press next. The list used to show a
 * raw enum value and told a Student with a declined card to contact an
 * administrator, which is the opposite of useful.
 */
class StudentPaymentStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paid_course_with_no_payment_yet_is_awaiting_payment(): void
    {
        $state = StudentPaymentState::for($this->enrollment(EnrollmentStatus::PendingPayment), null, $this->paidCourse());

        $this->assertSame('awaiting_payment', $state->key);
        $this->assertSame('Awaiting payment', $state->label);
        $this->assertTrue($state->offersPayment);
    }

    public function test_a_fresh_pending_payment_is_still_awaiting(): void
    {
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::PendingPayment),
            $this->payment(PaymentStatus::Pending, now()->subMinutes(5)),
            $this->paidCourse(),
        );

        $this->assertSame('awaiting_payment', $state->key);
        $this->assertTrue($state->offersPayment);
    }

    public function test_a_stale_pending_payment_is_expired(): void
    {
        // A checkout left open for a day cannot be completed, so telling the
        // Student it is merely pending is a lie that never resolves itself.
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::PendingPayment),
            $this->payment(PaymentStatus::Pending, now()->subDays(2)),
            $this->paidCourse(),
        );

        $this->assertSame('expired', $state->key);
        $this->assertSame('Payment expired', $state->label);
        $this->assertTrue($state->offersPayment, 'An expired payment must offer a fresh checkout.');
    }

    public function test_a_failed_payment_says_failed_and_offers_a_retry(): void
    {
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::PendingPayment),
            $this->payment(PaymentStatus::Failed, now()->subHour()),
            $this->paidCourse(),
        );

        $this->assertSame('failed', $state->key);
        $this->assertSame('Payment failed', $state->label);
        $this->assertTrue($state->offersPayment);
        $this->assertStringContainsString('again', $state->actionLabel);
    }

    public function test_a_failed_payment_never_tells_the_student_to_contact_an_administrator(): void
    {
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::PendingPayment),
            $this->payment(PaymentStatus::Failed, now()),
            $this->paidCourse(),
        );

        $this->assertStringNotContainsStringIgnoringCase('administrator', $state->message);
    }

    public function test_a_refunded_payment_says_refunded(): void
    {
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::PendingPayment),
            $this->payment(PaymentStatus::Refunded, now()),
            $this->paidCourse(),
        );

        $this->assertSame('refunded', $state->key);
        $this->assertSame('Refunded', $state->label);
    }

    public function test_a_settled_enrollment_is_paid_regardless_of_the_latest_payment(): void
    {
        // The enrollment is the source of truth for access. A stale pending
        // payment row must not make a paying Student look unpaid.
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::Active),
            $this->payment(PaymentStatus::Pending, now()->subDays(3)),
            $this->paidCourse(),
        );

        $this->assertSame('paid', $state->key);
        $this->assertFalse($state->offersPayment, 'A Student who already paid must not be charged again.');
    }

    public function test_a_free_active_enrollment_is_included_not_paid(): void
    {
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::Active),
            null,
            $this->freeCourse(),
        );

        $this->assertSame('included', $state->key);
        $this->assertSame('Included', $state->label);
        $this->assertFalse($state->offersPayment);
    }

    public function test_a_free_enrollment_is_never_asked_for_money(): void
    {
        $state = StudentPaymentState::for($this->enrollment(EnrollmentStatus::Active), null, $this->freeCourse());

        $this->assertNull($state->actionLabel);
    }

    public function test_the_label_is_human_readable_not_an_enum_value(): void
    {
        $state = StudentPaymentState::for(
            $this->enrollment(EnrollmentStatus::PendingPayment),
            null,
            $this->paidCourse(),
        );

        $this->assertStringNotContainsString('_', $state->label);
    }

    // ---- the surfaces a Student actually reads ----

    public function test_my_courses_shows_the_payment_state_not_a_raw_enum(): void
    {
        [$student, $enrollment] = $this->pendingOnPaidCourse();

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Awaiting payment')
            ->assertDontSee('Pending_payment');
    }

    public function test_my_courses_shows_failed_and_a_retry(): void
    {
        [$student, $enrollment, $payment] = $this->pendingOnPaidCourse();

        $payment->forceFill(['status' => PaymentStatus::Failed])->save();

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Payment failed')
            ->assertSee('Try payment again');
    }

    public function test_my_courses_shows_expired_for_a_stale_payment(): void
    {
        [$student, $enrollment, $payment] = $this->pendingOnPaidCourse();

        $payment->forceFill([
            'status' => PaymentStatus::Pending,
            'created_at' => now()->subDays(3),
        ])->save();

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Payment expired');
    }

    public function test_my_courses_shows_paid_and_offers_the_course(): void
    {
        [$student, $enrollment, $payment] = $this->pendingOnPaidCourse();

        $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
        $enrollment->forceFill(['status' => EnrollmentStatus::Active, 'activated_at' => now()])->save();

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Paid')
            ->assertSee('Open course');
    }

    public function test_my_courses_does_not_offer_a_second_payment_after_paying(): void
    {
        [$student, $enrollment, $payment] = $this->pendingOnPaidCourse();

        $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
        $enrollment->forceFill(['status' => EnrollmentStatus::Active, 'activated_at' => now()])->save();

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertDontSee('Try payment again');
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: Payment}
     */
    private function pendingOnPaidCourse(): array
    {
        $course = $this->paidCourse();
        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);

        $payment = $this->payment(PaymentStatus::Pending, now(), $enrollment);

        return [$student, $enrollment, $payment];
    }

    private function paidCourse(): Course
    {
        return Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 125000,
            'currency' => 'PHP',
        ]);
    }

    private function freeCourse(): Course
    {
        return Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'currency' => 'PHP',
        ]);
    }

    private function enrollment(EnrollmentStatus $status): Enrollment
    {
        $enrollment = new Enrollment;
        $enrollment->forceFill(['status' => $status]);

        return $enrollment;
    }

    private function payment(PaymentStatus $status, mixed $createdAt, ?Enrollment $enrollment = null): Payment
    {
        $payment = new Payment;

        $payment->forceFill([
            'status' => $status,
            'amount_minor' => 125000,
            'currency' => 'PHP',
            'created_at' => $createdAt,
        ]);

        if ($enrollment !== null) {
            $payment->forceFill([
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id,
                'provider' => 'paymongo',
                'idempotency_key' => 'enrollment-'.$enrollment->id,
            ]);
        }

        return $payment;
    }
}
