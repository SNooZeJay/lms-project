<?php

namespace Tests\Feature\Phase16;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\ProgressCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QueryCounter;
use Tests\TestCase;

/**
 * An Instructor can see how each of their learners is doing on a course.
 *
 * `plan.md` lists "Student progress" among the things the Instructor dashboard
 * focuses on. The Instructor had a panel naming learners who were behind and a
 * panel of recent assessment results, and no view of a learner's actual progress
 * at all, so the plan item was not reachable from anywhere.
 *
 * The page belongs on the Course rather than on the dashboard. A dashboard can
 * only show the five worst cases, and the question an Instructor actually has is
 * "how is this whole cohort doing", which is a question about one Course.
 *
 * Progress comes from `ProgressCalculator`, the same place every other figure on
 * every page comes from, so the number here and the number the learner sees are
 * produced by one piece of code and cannot drift apart.
 */
class InstructorStudentProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $stranger;

    private Course $course;

    private Module $module;

    /** @var list<Lesson> */
    private array $lessons;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::factory()->instructor()->create();
        $this->stranger = User::factory()->instructor()->create();

        $this->course = Course::factory()->for($this->instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'published_at' => now(),
        ]);

        // Published throughout, because an unpublished module holds no lessons
        // that count towards progress and the fixture would then report nothing
        // to assert.
        $this->module = Module::factory()->for($this->course, 'course')->published()->create();

        $this->lessons = [];

        foreach (range(1, 4) as $position) {
            $this->lessons[] = Lesson::factory()->for($this->module, 'module')->create([
                'position' => $position,
                'status' => ContentStatus::Published,
                'is_required' => true,
            ]);
        }
    }

    public function test_an_instructor_sees_each_learners_real_progress(): void
    {
        $enrollment = $this->enroll($this->lessons[0], $this->lessons[1]);

        $body = (string) $this->actingAs($this->instructor)
            ->get(route('instructor.courses.students', $this->course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($enrollment->student->name, $body);

        $expected = app(ProgressCalculator::class)->forEnrollment($enrollment);

        $this->assertSame(50, $expected['percentage'], 'The fixture should be exactly half finished.');
        $this->assertStringContainsString('50%', $body);
    }

    public function test_a_learner_who_finished_everything_reads_as_finished(): void
    {
        $this->enroll(...$this->lessons);

        $body = (string) $this->actingAs($this->instructor)
            ->get(route('instructor.courses.students', $this->course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('100%', $body);
    }

    /** A course nobody has enrolled in is an empty state, not an error. */
    public function test_a_course_with_no_learners_has_an_empty_state(): void
    {
        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.students', $this->course))
            ->assertOk()
            ->assertSee('No learners yet');
    }

    /**
     * The page is scoped to the course, so the number of queries must not grow
     * with the number of learners on it.
     */
    public function test_the_page_costs_the_same_however_many_learners_it_shows(): void
    {
        foreach (range(1, 8) as $ignored) {
            $this->enroll($this->lessons[0]);
        }

        $withNine = (new QueryCounter)->measure(
            fn () => $this->actingAs($this->instructor)->get(route('instructor.courses.students', $this->course))
        )['count'];

        foreach (range(1, 20) as $ignored) {
            $this->enroll($this->lessons[0]);
        }

        $withThirty = (new QueryCounter)->measure(
            fn () => $this->actingAs($this->instructor)->get(route('instructor.courses.students', $this->course))
        )['count'];

        $this->assertSame(
            $withNine,
            $withThirty,
            'The learner list cost '.$withNine.' queries with nine learners and '.$withThirty.' with thirty, '
            .'so something is being read per learner.'
        );
    }

    public function test_another_instructor_cannot_see_these_learners(): void
    {
        $this->enroll($this->lessons[0]);

        $this->actingAs($this->stranger)
            ->get(route('instructor.courses.students', $this->course))
            ->assertForbidden();
    }

    public function test_a_student_cannot_see_another_students_progress(): void
    {
        $enrollment = $this->enroll($this->lessons[0]);

        $this->actingAs($enrollment->student)
            ->get(route('instructor.courses.students', $this->course))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->enroll($this->lessons[0]);

        $this->get(route('instructor.courses.students', $this->course))->assertRedirect('/login');
    }

    /** The learner list belongs on the course page, where a teacher would look. */
    public function test_the_course_page_links_to_the_learner_list(): void
    {
        $this->enroll($this->lessons[0]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.show', $this->course))
            ->assertOk()
            ->assertSee(route('instructor.courses.students', $this->course), false);
    }

    /**
     * @param  list<Lesson>  $completed
     */
    private function enroll(Lesson ...$completed): Enrollment
    {
        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        foreach ($completed as $lesson) {
            LessonProgress::factory()->create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
                'status' => LessonProgressStatus::Completed,
            ]);
        }

        return $enrollment;
    }
}
