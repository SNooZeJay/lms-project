<?php

namespace Tests\Feature\Notifications;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Policies\LessonPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QueryCounter;
use Tests\TestCase;

/**
 * The batched student query must agree with the single student check.
 *
 * The plan puts this batched sibling on the policy rather than on
 * StudentCourseAccess, and the reason is a correction the plan records from a
 * measurement: `allows()` reads enrollment status and nothing else, so of the 96
 * students it allowed, 13 were suspended accounts holding an active enrollment.
 * A fan-out written against `allows()` alone notifies suspended people and the
 * link lands on a page that refuses them.
 *
 * So the two answers are compared directly, on a data set that contains every
 * way the composite can say no: a pending payment, a cancellation, a suspended
 * account, an instructor holding an enrollment, and unpublished content. If
 * either side drifts, this fails.
 */
class AuthorizedStudentFanoutTest extends TestCase
{
    use RefreshDatabase;

    private function course(LessonPolicy $policy, array $overrides = []): Course
    {
        $course = Course::factory()->create(array_merge([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ], $overrides));

        $module = Module::factory()->create([
            'course_id' => $course->id,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'status' => ContentStatus::Published,
            'position' => 1,
        ]);

        unset($policy);

        $this->module = $module;
        $this->lesson = $lesson;

        return $course;
    }

    private Module $module;

    private Lesson $lesson;

    private function studentWith(array $enrollment, array $profile = []): User
    {
        $student = User::factory()->create();

        $student->profile->forceFill(array_merge([
            'role' => UserRole::Student,
            'account_status' => UserAccountStatus::Active,
        ], $profile))->save();

        $student = $student->fresh();

        if ($enrollment !== []) {
            Enrollment::factory()->create(array_merge([
                'student_id' => $student->id,
                'course_id' => $this->course->id,
            ], $enrollment));
        }

        return $student;
    }

    private function teacher(): User
    {
        $instructor = User::factory()->instructor()->create();

        return $instructor;
    }

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = $this->course(app(LessonPolicy::class));
    }

    public function test_the_batched_answer_agrees_with_the_single_check_on_every_exclusion(): void
    {
        $course = $this->course;
        $lesson = $this->lesson;

        // Every way the composite can say yes, and every way it can say no.
        $active = $this->studentWith(['status' => EnrollmentStatus::Active]);
        $completed = $this->studentWith(['status' => EnrollmentStatus::Completed]);
        $pending = $this->studentWith(['status' => EnrollmentStatus::PendingPayment]);
        $cancelled = $this->studentWith(['status' => EnrollmentStatus::Cancelled]);
        $suspended = $this->studentWith(
            ['status' => EnrollmentStatus::Active],
            ['account_status' => UserAccountStatus::Suspended]
        );
        $instructorHoldingOne = $this->teacher();
        $notEnrolled = $this->studentWith([]);

        $policy = app(LessonPolicy::class);

        $batched = $policy->authorizedStudentIdsForCourse($course);

        $expected = [$active->id, $completed->id];
        sort($expected);
        sort($batched);

        $this->assertSame(
            $expected,
            $batched,
            'The batched answer is not the set of students the single check allows: '
                .'active and completed only, with pending, cancelled, suspended, '
                .'a non-student and an unenrolled student all left out.'
        );

        // And the single check, asked about every one of them, agrees.
        foreach ([$active, $completed, $pending, $cancelled, $suspended, $instructorHoldingOne, $notEnrolled] as $person) {
            $single = $policy->viewForStudent($person, $lesson);

            $this->assertSame(
                in_array($person->id, $batched, true),
                $single,
                "The two answers disagree for user {$person->id} (role "
                    .$person->profile?->role?->value.', status '
                    .$person->profile?->account_status?->value.').'
            );
        }
    }

    public function test_a_suspended_student_is_left_out_even_with_an_active_enrollment(): void
    {
        // The specific correction the plan records, on its own.
        $suspended = $this->studentWith(
            ['status' => EnrollmentStatus::Active],
            ['account_status' => UserAccountStatus::Suspended]
        );

        $batched = app(LessonPolicy::class)->authorizedStudentIdsForCourse($this->course);

        $this->assertNotContains(
            $suspended->id,
            $batched,
            'A suspended account holding an active enrollment was included, so a notification would point at a page that refuses them.'
        );
    }

    public function test_an_unpublished_lesson_has_no_authorized_students(): void
    {
        $this->studentWith(['status' => EnrollmentStatus::Active]);

        $draft = Lesson::factory()->create([
            'module_id' => $this->module->id,
            'status' => ContentStatus::Draft,
            'position' => 2,
        ]);

        $this->assertSame(
            [],
            app(LessonPolicy::class)->authorizedStudentIdsForLesson($draft),
            'A draft lesson has students who could open it, so the fan-out would announce something nobody can read.'
        );
    }

    public function test_a_draft_module_hides_its_published_lessons(): void
    {
        $this->studentWith(['status' => EnrollmentStatus::Active]);

        $this->module->forceFill(['status' => ContentStatus::Draft])->save();

        $this->assertSame(
            [],
            app(LessonPolicy::class)->authorizedStudentIdsForLesson($this->lesson),
            'A published lesson inside a draft module has authorized students, so the two checks disagree.'
        );
    }

    public function test_the_batched_answer_costs_one_query_and_does_not_grow_with_the_students(): void
    {
        $counter = new QueryCounter;

        $one = $this->studentWith(['status' => EnrollmentStatus::Active]);
        $small = $counter->measure(
            fn (): array => app(LessonPolicy::class)->authorizedStudentIdsForCourse($this->course)
        );

        $this->assertSame([$one->id], $small['result']);

        for ($i = 0; $i < 11; $i++) {
            $this->studentWith(['status' => EnrollmentStatus::Active]);
        }

        $large = $counter->measure(
            fn (): array => app(LessonPolicy::class)->authorizedStudentIdsForCourse($this->course)
        );

        $this->assertSame(12, count($large['result']));

        $this->assertSame(
            $small['count'],
            $large['count'],
            "The batched answer ran {$small['count']} queries for one student and {$large['count']} for twelve. "
                .'It is a fixed cost or it is an N+1.'
        );

        $this->assertSame(1, $small['count'], 'A fan-out recipient set must be one query, not one per student.');
    }
}
