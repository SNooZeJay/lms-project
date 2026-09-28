<?php

namespace Tests\Feature\Phase16;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Reporting\OperationsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The chart and the agenda as they are drawn.
 *
 * Both are deliberately small, so these tests are about the two ways a chart of
 * real numbers most often goes wrong: showing a full bar for a zero, and
 * showing a grid of nothing on a system that has no data yet.
 */
class DashboardChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chart_with_no_data_shows_the_empty_state_instead_of_empty_bars(): void
    {
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($administrator)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            // No enrollments exist, so four zero-length tracks would be drawn.
            // They would read as four full bars, which is the opposite of the
            // truth, so the empty state is what has to appear.
            ->assertSee('No enrollments have been created yet.')
            ->assertDontSee('Awaiting payment');
    }

    public function test_a_chart_with_data_draws_a_bar_for_each_row(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->for($instructor, 'instructor')->create([
            'title' => 'Charted Course',
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->active()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
        ]);

        $counts = app(OperationsReport::class)->enrollmentStatusCounts();

        $this->assertSame(1, $counts['active']);
        $this->assertGreaterThan(0, array_sum($counts));

        $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('Students by course')
            ->assertSee('Charted Course');
    }

    public function test_the_agenda_writes_a_readable_date(): void
    {
        // isoFormat needs the intl translator, which is not installed here, and
        // silently degrades to "26, 9 2026". A plain date format always works.
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->active()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
        ]);

        $body = (string) $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/\b(Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{1,2} [A-Z][a-z]{2} \d{4}\b/',
            $body,
            'The agenda groups its entries under a readable day.'
        );
    }

    public function test_a_student_sees_the_progress_chart_not_the_enrollment_chart(): void
    {
        $student = User::factory()->create();

        $body = (string) $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Your progress', $body);
        $this->assertStringNotContainsString('Enrollments by state', $body);
        $this->assertStringNotContainsString('Students by course', $body);
    }

    /**
     * A bar's width may not come from an inline style.
     *
     * The content security policy is `style-src 'self'`, which forbids the
     * `style` attribute outright. The fill used to carry
     * `style="width: 0%"`, so the browser threw that declaration away, the fill
     * fell back to its natural width, and every bar in every chart rendered at
     * one hundred percent.
     *
     * The consequence is not cosmetic. "Enrollments by state" showed three
     * states holding nothing drawn as three full bars in a different colour, and
     * a chart of counts encoded no counts at all. The browser reported eight CSP
     * violations on the Administrator dashboard and carried on rendering.
     *
     * The sibling `progress` component already avoids this, using a native
     * `<progress>` and a generated width class. This test is the bar chart's
     * version of that same rule, and it is written as a rule about the markup
     * rather than as a rule about one number, so a new chart cannot reintroduce
     * it.
     */
    public function test_a_bar_width_never_depends_on_an_inline_style(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->active()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
        ]);

        $body = (string) $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<div[^>]*\sstyle="[^"]*width/i',
            $body,
            'A bar width was painted with an inline style. The content security policy is '
            .'"style-src \'self\'", the browser discards it, and the bar silently renders at full width.'
        );
    }

    /**
     * Two different values must produce two different bars.
     *
     * This is the assertion the empty-state test could not make. That test
     * covered a system with no data at all, where a chart of four zeros is
     * replaced by an empty state and nothing is drawn. It said nothing about a
     * system that has data, which is the case where the bars were wrong.
     *
     * Here one enrollment exists, so the chart holds a row at the maximum and
     * three rows at nothing. If every bar renders full width these are
     * indistinguishable, and the chart is decoration rather than a reading.
     */
    public function test_a_zero_row_and_a_full_row_are_drawn_differently(): void
    {
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->active()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
        ]);

        $body = (string) $this->actingAs($administrator)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Enrollments by state', $body);

        // The width is carried by a generated class, so the number in the class
        // name is the width that will actually be painted.
        preg_match_all('/progress-step-(\d+)/', $body, $found);

        $steps = array_map('intval', $found[1]);

        $this->assertContains(
            0,
            $steps,
            'No bar was drawn at zero width, so the states holding nothing are not being '
            .'distinguished from the state holding everything.'
        );

        $this->assertContains(
            100,
            $steps,
            'No bar was drawn at full width, so the chart has no upper bound to compare against.'
        );
    }

    /**
     * The bars in one chart have to share a scale.
     *
     * A row that supplies no maximum fell back to scaling against its own value,
     * so every non-zero bar drew at one hundred percent. On the Instructor
     * dashboard that made a course with two learners and a course with one
     * learner draw as two identical full bars, and the chart answered nothing.
     * The same applied to any state chart whose values were not equal.
     *
     * Comparing lengths is the entire reason a bar chart exists. A chart whose
     * bars are all the same length is decoration, and it is worse than no chart
     * because it looks like a reading.
     *
     * This was a second, separate fault from the blocked inline width, and it
     * survived the fix for that one: with every bar correctly zero or full
     * according to its own scale, this case is still wrong.
     */
    public function test_bars_in_one_chart_are_drawn_against_a_shared_scale(): void
    {
        $instructor = User::factory()->instructor()->create();

        $busy = Course::factory()->for($instructor, 'instructor')->create([
            'title' => 'Busy Course',
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $quiet = Course::factory()->for($instructor, 'instructor')->create([
            'title' => 'Quiet Course',
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        foreach (range(1, 2) as $ignored) {
            Enrollment::factory()->active()->create([
                'student_id' => User::factory()->create()->id,
                'course_id' => $busy->id,
            ]);
        }

        Enrollment::factory()->active()->create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $quiet->id,
        ]);

        $body = (string) $this->actingAs($instructor)
            ->get(route('instructor.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Busy Course', $body);
        $this->assertStringContainsString('Quiet Course', $body);

        preg_match_all('/progress-step-(\d+)/', $body, $found);

        $steps = array_map('intval', $found[1]);

        $this->assertContains(
            100,
            $steps,
            'The largest bar should reach the full width of the track.'
        );

        $this->assertNotSame(
            [100, 100],
            array_values(array_unique($steps)),
            'Every bar was drawn at the same width, so two courses holding two learners and one '
            .'learner are indistinguishable. Bars that do not share a scale do not compare.'
        );

        // One learner out of the chart's maximum of two is about half the track.
        $this->assertContains(
            50,
            $steps,
            'The quieter course should draw at roughly half the width of the busier one. Steps are '
            .'five points, so 50 is the nearest step to 50 percent.'
        );
    }
}
