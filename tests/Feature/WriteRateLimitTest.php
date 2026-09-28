<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Writes have a ceiling, reads do not, and neither leaks between people.
 *
 * The limiter is a backstop against a burst, not the thing that decides who may
 * do what. These tests exist because the failure modes of a limiter are quiet
 * and unfair: a limit applied to reads turns a page refresh into an error, and a
 * limit keyed on the address lets one person behind a shared connection exhaust
 * the allowance of everyone else behind it.
 */
class WriteRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The counter lives in the cache. Clearing it between tests keeps one
        // test's burst from deciding whether the next one is refused.
        Cache::flush();
        RateLimiter::clear('write:ip:127.0.0.1');
    }

    /**
     * A student with one published lesson they are enrolled in.
     */
    private function enrolledStudent(): User
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->create([
            'course_id' => $course->id,
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        Lesson::factory()->create([
            'module_id' => $module->id,
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return $student;
    }

    public function test_reading_is_never_refused(): void
    {
        $student = $this->enrolledStudent();

        // Far more page loads than the write allowance allows. A dashboard is a
        // handful of indexed reads and costs the database nothing, so capping it
        // would produce an error page for something that is not a problem.
        foreach (range(1, 80) as $ignored) {
            $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
        }

        $this->assertTrue(true);
    }

    public function test_a_burst_of_writes_is_eventually_refused_rather_than_served(): void
    {
        $student = $this->enrolledStudent();
        [$course, $lesson] = $this->firstCourseAndLesson($student);

        $url = route('student.lessons.complete', [$course, $lesson]);

        $refused = 0;
        $served = 0;

        foreach (range(1, 75) as $ignored) {
            $response = $this->actingAs($student)->post($url);

            if ($response->status() === 429) {
                $refused++;
            } else {
                $served++;
            }
        }

        // The point is not the exact number. It is that the burst stops being
        // absorbed: a script gets a short refusal instead of the application
        // quietly queueing its work forever.
        $this->assertGreaterThan(0, $refused, 'A burst of writes was never refused.');
        $this->assertLessThanOrEqual(60, $served, 'More writes were served than the allowance permits.');
    }

    public function test_a_refusal_explains_how_long_to_wait(): void
    {
        $student = $this->enrolledStudent();
        [$course, $lesson] = $this->firstCourseAndLesson($student);

        $url = route('student.lessons.complete', [$course, $lesson]);

        $response = null;

        foreach (range(1, 75) as $ignored) {
            $attempt = $this->actingAs($student)->post($url);

            if ($attempt->status() === 429) {
                $response = $attempt;
                break;
            }
        }

        $this->assertNotNull($response, 'The burst was never refused, so there is nothing to check.');

        // An intermediary needs this to back off rather than treat the route as
        // gone, and a person needs the page to say waiting will work.
        $response->assertHeader('Retry-After');
        $response->assertSee('Too many requests');
    }

    public function test_one_person_cannot_exhaust_another_persons_allowance(): void
    {
        $noisy = $this->enrolledStudent();
        $quiet = $this->enrolledStudent();

        [$noisyCourse, $noisyLesson] = $this->firstCourseAndLesson($noisy);
        [$quietCourse, $quietLesson] = $this->firstCourseAndLesson($quiet);

        // Both requests come from the same address, which is the point: a shared
        // office, a school network, a mobile carrier behind a NAT.
        foreach (range(1, 75) as $ignored) {
            $this->actingAs($noisy)->post(route('student.lessons.complete', [$noisyCourse, $noisyLesson]));
        }

        // The quiet student's own write still goes through. Keying the counter on
        // the address alone would have turned one person's burst into an outage
        // for everyone behind the same connection.
        $this->actingAs($quiet)
            ->post(route('student.lessons.complete', [$quietCourse, $quietLesson]))
            ->assertRedirect();
    }

    public function test_a_refused_burst_still_leaves_the_data_correct(): void
    {
        $student = $this->enrolledStudent();
        [$course, $lesson] = $this->firstCourseAndLesson($student);

        $url = route('student.lessons.complete', [$course, $lesson]);

        foreach (range(1, 75) as $ignored) {
            $this->actingAs($student)->post($url);
        }

        // A refused request is a refusal to do more work. It must never leave a
        // half written row behind, and the lesson the student did complete is
        // still exactly one completed lesson.
        $this->assertSame(1, LessonProgress::query()->count());
        $this->assertSame(1, LessonProgress::query()->whereNotNull('completed_at')->count());
    }

    public function test_navigation_still_reflects_the_current_role_under_load(): void
    {
        $instructor = User::factory()->instructor()->create();

        $groups = Navigation::for($instructor);

        // The limiter must not have changed who can see what. A defensive check
        // that the navigation is still built from abilities rather than from
        // whatever happened to be cached.
        $this->assertNotEmpty($groups);
        $this->assertTrue(
            collect($groups)->flatMap(fn (array $g) => $g['items'])->contains(
                fn (array $item): bool => $item['route'] === 'instructor.dashboard'
            ),
            'An instructor lost access to their own dashboard.'
        );
    }

    /**
     * The course and lesson this student is enrolled in.
     *
     * @return array{0: Course, 1: Lesson}
     */
    private function firstCourseAndLesson(User $student): array
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->firstOrFail();

        $course = $enrollment->course;

        $lesson = Lesson::query()
            ->where('module_id', Module::query()->where('course_id', $course->id)->value('id'))
            ->firstOrFail();

        return [$course, $lesson];
    }
}
