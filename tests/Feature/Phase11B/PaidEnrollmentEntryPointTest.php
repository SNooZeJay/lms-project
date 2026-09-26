<?php

namespace Tests\Feature\Phase11B;

use App\Contracts\PayMongoClient;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePayMongoClient;
use Tests\TestCase;

/**
 * The payment state machine was built and tested, but nothing a Student can
 * click leads into it. The catalog refused to offer a paid enrollment, so no
 * pending_payment enrollment was ever created, so the checkout could not start,
 * and even if it had started the controller sent the Student back to the
 * waiting page instead of to the provider.
 *
 * These tests cover the path a Student actually walks.
 */
class PaidEnrollmentEntryPointTest extends TestCase
{
    use RefreshDatabase;

    private FakePayMongoClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakePayMongoClient;
        $this->app->instance(PayMongoClient::class, $this->client);

        config([
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'placeholder-key',
            'services.paymongo.webhook_secret' => 'test-signing-key-entry',
        ]);
    }

    public function test_a_student_can_enroll_in_a_paid_course_and_get_a_pending_enrollment(): void
    {
        [$student, $course] = $this->paidCourse();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/enroll")
            ->assertRedirect(route('student.payments.checkout', $course));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment->value,
        ]);
    }

    public function test_enrolling_in_a_paid_course_continues_straight_to_the_provider(): void
    {
        [$student, $course] = $this->paidCourse();

        // Enrolling then checkout are two hops, which a browser follows on its
        // own. The Student must not have to find a second button, and must not
        // be dropped on a page with nothing to pay with.
        $enroll = $this->actingAs($student)
            ->post("/student/courses/{$course->id}/enroll");

        $enroll->assertRedirect(route('student.payments.checkout', $course));

        $checkout = $this->actingAs($student)
            ->post("/student/courses/{$course->id}/checkout");

        $this->assertStringStartsWith(
            'https://',
            (string) $checkout->headers->get('Location'),
            'Enrolling a paid course must end on the provider page.'
        );
    }

    public function test_a_paid_course_offers_a_pay_control_instead_of_a_dead_end_notice(): void
    {
        [$student, $course] = $this->paidCourse();

        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Enroll to pay')
            ->assertDontSee('opens in a later release');
    }

    public function test_the_pay_control_posts_to_the_enroll_route_first(): void
    {
        [$student, $course] = $this->paidCourse();

        // Enrolling is what creates the pending enrollment the checkout needs,
        // so this button cannot post to the checkout route directly.
        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee(route('student.enrollments.store', $course), false);
    }

    public function test_a_pending_enrollment_offers_a_way_to_continue_paying(): void
    {
        [$student, $course] = $this->paidCourse();
        $this->enrollPending($student, $course);

        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Continue payment')
            ->assertSee(route('student.payments.checkout', $course), false);
    }

    public function test_a_student_who_already_paid_sees_enrolled_not_the_dead_end_notice(): void
    {
        [$student, $course] = $this->paidCourse();
        $enrollment = $this->enrollPending($student, $course);

        $enrollment->forceFill([
            'status' => EnrollmentStatus::Active,
            'activated_at' => now(),
        ])->save();

        // The course is still a paid course, so a check that looks at the
        // course type before the enrollment would show the placeholder here
        // and hide a real purchase from the person who made it.
        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('Enrolled')
            ->assertDontSee('opens in a later release');
    }

    public function test_the_checkout_sends_the_student_to_the_provider(): void
    {
        [$student, $course] = $this->paidCourse();
        $this->enrollPending($student, $course);

        $response = $this->actingAs($student)
            ->post("/student/courses/{$course->id}/checkout");

        $response->assertRedirect();

        $target = $response->headers->get('Location');

        $this->assertNotSame(
            url("/student/courses/{$course->id}/checkout/return"),
            $target,
            'The Student must land on the provider page, not back on the waiting page.'
        );

        $this->assertStringStartsWith('https://', (string) $target);
    }

    public function test_the_checkout_creates_exactly_one_pending_payment(): void
    {
        [$student, $course] = $this->paidCourse();
        $this->enrollPending($student, $course);

        $url = "/student/courses/{$course->id}/checkout";

        $this->actingAs($student)->post($url);
        $this->actingAs($student)->post($url);

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_the_price_shown_matches_the_course_price(): void
    {
        [$student, $course] = $this->paidCourse();
        $this->enrollPending($student, $course);

        $this->actingAs($student)
            ->get("/courses/{$course->slug}")
            ->assertOk()
            ->assertSee('1,250');
    }

    public function test_a_free_course_still_enrolls_without_payment(): void
    {
        [$student, $course] = $this->freeCourse();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/enroll")
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
        ]);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_guest_still_cannot_start_a_paid_enrollment(): void
    {
        [, $course] = $this->paidCourse();

        $this->post("/student/courses/{$course->id}/enroll")->assertRedirect(route('login'));
    }

    public function test_another_students_purchase_cannot_be_used_by_someone_else(): void
    {
        [$owner, $course] = $this->paidCourse();
        $this->enrollPending($owner, $course);

        $intruder = User::factory()->create();

        // The lookup is scoped to the requesting Student, so the enrollment is
        // not found and the request is answered with 404. That is deliberate:
        // a 403 would confirm the purchase exists.
        $this->actingAs($intruder)
            ->post("/student/courses/{$course->id}/checkout")
            ->assertNotFound();
    }

    public function test_enrolling_in_a_paid_course_never_grants_access_by_itself(): void
    {
        [$owner, $course] = $this->paidCourse();
        $ownerEnrollment = $this->enrollPending($owner, $course);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post("/student/courses/{$course->id}/enroll")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // A Student may start their own payment, which creates their own
        // pending enrollment. What must never happen is access being granted,
        // because only a verified payment event may do that.
        $intruderEnrollment = Enrollment::query()
            ->where('student_id', $intruder->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertNotNull($intruderEnrollment);
        $this->assertSame(EnrollmentStatus::PendingPayment, $intruderEnrollment->status);
        $this->assertFalse($intruderEnrollment->grantsAccess());

        // The other Student's purchase is untouched.
        $this->assertSame(
            EnrollmentStatus::PendingPayment,
            $ownerEnrollment->fresh()->status,
        );
    }

    /**
     * @return array{0: User, 1: Course}
     */
    private function paidCourse(): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 125000,
            'currency' => 'PHP',
        ]);

        return [User::factory()->create(), $course];
    }

    /**
     * @return array{0: User, 1: Course}
     */
    private function freeCourse(): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'currency' => 'PHP',
        ]);

        return [User::factory()->create(), $course];
    }

    private function enrollPending(User $student, Course $course): Enrollment
    {
        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);
    }
}
