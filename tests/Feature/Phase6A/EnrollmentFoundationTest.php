<?php

namespace Tests\Feature\Phase6A;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnrollmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollments_table_exists_with_the_documented_columns(): void
    {
        $this->assertTrue(Schema::hasTable('enrollments'));

        $this->assertTrue(Schema::hasColumns('enrollments', [
            'id',
            'student_id',
            'course_id',
            'status',
            'activated_at',
            'completed_at',
            'cancelled_at',
            'last_accessed_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_status_defaults_to_pending_payment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->assertSame(EnrollmentStatus::PendingPayment, $enrollment->status);
        $this->assertNull($enrollment->activated_at);
        $this->assertNull($enrollment->completed_at);
        $this->assertNull($enrollment->cancelled_at);
        $this->assertNull($enrollment->last_accessed_at);
    }

    public function test_all_four_status_values_are_accepted(): void
    {
        $values = [
            EnrollmentStatus::PendingPayment->value,
            EnrollmentStatus::Active->value,
            EnrollmentStatus::Completed->value,
            EnrollmentStatus::Cancelled->value,
        ];

        foreach ($values as $value) {
            $enrollment = Enrollment::factory()->create(['status' => $value]);
            $this->assertSame($value, $enrollment->refresh()->status->value);
        }
    }

    public function test_unknown_status_value_is_rejected_by_the_model(): void
    {
        $this->expectException(\ValueError::class);

        Enrollment::factory()->create(['status' => 'not_a_status']);
    }

    public function test_unknown_status_value_is_rejected_by_the_database(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('enrollments')->where('id', $enrollment->id)->update(['status' => 'not_a_status']);
    }

    public function test_a_student_can_have_only_one_enrollment_per_course(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->expectException(QueryException::class);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_two_students_can_enroll_in_the_same_course(): void
    {
        $course = Course::factory()->create();

        Enrollment::factory()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
        ]);
        Enrollment::factory()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
        ]);

        $this->assertSame(2, Enrollment::query()->where('course_id', $course->id)->count());
    }

    public function test_deleting_a_course_with_enrollments_is_rejected(): void
    {
        $course = Course::factory()->create();
        Enrollment::factory()->create(['course_id' => $course->id]);

        $this->expectException(QueryException::class);

        DB::table('courses')->where('id', $course->id)->delete();
    }

    public function test_deleting_a_user_with_enrollments_is_rejected(): void
    {
        $student = User::factory()->create();
        Enrollment::factory()->create(['student_id' => $student->id]);

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $student->id)->delete();
    }

    public function test_enrollment_belongs_to_a_student_and_a_course(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->assertTrue($enrollment->student->is($student));
        $this->assertTrue($enrollment->course->is($course));
    }

    public function test_user_and_course_expose_their_enrollments(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $otherCourse = Course::factory()->create();

        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id]);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $otherCourse->id]);

        $this->assertCount(2, $student->enrollments);
        $this->assertCount(1, $course->enrollments);
    }

    public function test_active_scope_returns_only_active_enrollments(): void
    {
        $course = Course::factory()->create();

        $active = Enrollment::factory()->create([
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);
        Enrollment::factory()->create([
            'course_id' => $course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);
        Enrollment::factory()->create([
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Cancelled,
        ]);

        $activeIds = Enrollment::query()->active()->pluck('id')->all();

        $this->assertSame([$active->id], $activeIds);
    }

    public function test_timestamps_are_cast_to_dates_when_present(): void
    {
        $enrollment = Enrollment::factory()->create([
            'status' => EnrollmentStatus::Active,
            'activated_at' => now(),
            'last_accessed_at' => now(),
        ]);

        $enrollment = $enrollment->refresh();

        $this->assertInstanceOf(Carbon::class, $enrollment->activated_at);
        $this->assertInstanceOf(Carbon::class, $enrollment->last_accessed_at);
    }

    public function test_free_enrollment_keeps_the_documented_price_expectations(): void
    {
        $course = Course::factory()->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $enrollment = Enrollment::factory()->create([
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->assertSame(0, $enrollment->course->price_minor);
        $this->assertSame(CourseType::Free, $enrollment->course->course_type);
    }

    public function test_phase_six_a_adds_no_lesson_access_or_payment_routes(): void
    {
        $this->assertFalse(Route::has('student.progress.index'));
        $this->assertFalse(Route::has('student.payments.checkout'));
        $this->assertFalse(Schema::hasTable('payments'));
    }
}
