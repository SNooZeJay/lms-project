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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The provider confirms a payment by webhook, not by the browser coming back.
 * The return page therefore has to keep checking, or a Student who paid sits
 * looking at "waiting" until they think to reload.
 *
 * A page script is only rendered if the layout has the matching stack, so the
 * polling script can disappear without any error. These tests check the rendered
 * output rather than the view file.
 */
class PaymentReturnPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pending_payment_page_polls_for_the_webhook(): void
    {
        [$student, $course, $payment] = $this->pendingPayment();

        $html = $this->actingAs($student)
            ->get(route('student.payments.return', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'data-payment-poll',
            $html,
            'The pending page needs the marker the script looks for.'
        );

        $this->assertStringContainsString(
            'window.location.reload()',
            $html,
            'The polling script never reached the page. Check the scripts stack in the layout.'
        );
    }

    public function test_a_confirmed_payment_page_does_not_poll(): void
    {
        [$student, $course, $payment] = $this->pendingPayment();

        $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
        $payment->enrollment->forceFill([
            'status' => EnrollmentStatus::Active,
            'activated_at' => now(),
        ])->save();

        $html = $this->actingAs($student)
            ->get(route('student.payments.return', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Payment confirmed', $html);
        $this->assertStringNotContainsString(
            'window.location.reload()',
            $html,
            'A settled payment must stop reloading the page.'
        );
    }

    public function test_a_failed_payment_page_offers_a_way_to_pay_again(): void
    {
        [$student, $course, $payment] = $this->pendingPayment();

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_message' => 'The provider reported a failed payment.',
        ])->save();

        $this->actingAs($student)
            ->get(route('student.payments.return', $course))
            ->assertOk()
            ->assertSee('Payment did not go through');
    }

    public function test_the_page_explains_what_the_test_buttons_do(): void
    {
        [$student, $course, $payment] = $this->pendingPayment();

        $this->actingAs($student)
            ->get(route('student.payments.return', $course))
            ->assertOk()
            ->assertSee('Authorize test payment')
            ->assertSee('Fail or expire test payment');
    }

    public function test_the_page_does_not_claim_to_know_which_button_was_pressed(): void
    {
        [$student, $course, $payment] = $this->pendingPayment();

        // The redirect carries no state, so the page must not imply it does.
        $this->actingAs($student)
            ->get(route('student.payments.return', $course))
            ->assertOk()
            ->assertSee('cannot report which button was pressed');
    }

    public function test_another_students_payment_page_is_not_reachable(): void
    {
        [$student, $course, $payment] = $this->pendingPayment();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('student.payments.return', $course))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Course, 2: Payment}
     */
    private function pendingPayment(): array
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
        ]);
        $payment->save();

        return [$student, $course, $payment];
    }
}
