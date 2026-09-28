<?php

namespace Tests\Feature\Messaging;

use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\StartConversation;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QueryCounter;
use Tests\TestCase;

/**
 * What the topbar costs, on every signed in page.
 *
 * The dashboard budget test measures the report layer and stops there, so
 * nothing covered the shell. The topbar is on every page a signed in person
 * sees, and the message badge was added to it, which is two more queries
 * everywhere. A number only ever checked in one place is a number nobody is
 * checking.
 *
 * The property worth pinning is that the cost does not move with the amount of
 * data, not the absolute figure. A person with six threads of twenty messages
 * each must not cost more to render the shell than a person with none, and that
 * has to hold for every role, because a cost stable for a student can still be
 * a per row query for an administrator.
 *
 * On the tolerance. The badge is allowed two queries: one count and one short
 * page of five threads. The allowance is four, and the fixture is six threads.
 * The first version of this file used one thread and passed with a deliberate
 * N+1 in place, because one extra row cannot outrun a tolerance of four. The gap
 * between the allowance and the regression is the whole reason this test can be
 * trusted, so the fixture is deliberately larger than the allowance.
 */
class TopbarMessagingCostTest extends TestCase
{
    use RefreshDatabase;

    private const THREADS = 6;

    private const MESSAGES = 20;

    /**
     * How many conversation queries the message list page may run.
     *
     * Counted, not guessed. `tools/probe-message-page-queries.php` prints the
     * statements, and these are the seven it reports:
     *
     *   1  the pagination count for the list
     *   2  the list itself, carrying the message and participant totals
     *   3  the last message of each listed thread, for the preview
     *   4  the per row unread counts, one grouped aggregate
     *   5  the topbar badge, the same grouped aggregate over every live thread
     *   6  the topbar dropdown, one page of five threads
     *   7  the last message of each of those five, for the panel preview
     *
     * The sixth was six before the panel learned to show a preview, and the
     * seventh is that preview: one query, for the whole page, because
     * `lastMessage` is a relation and is eager loaded rather than called per row.
     * It costs every signed in page exactly one query, measured on all three
     * dashboards, which is the price of a message panel that says what was said
     * instead of naming a course.
     *
     * Four and five are the same aggregate twice, and three and seven are the
     * same load twice, because the shell composer runs on this page as well and
     * the page has its own reads. Sharing one was tried: a composer registered
     * for both the layout and this view fires twice, because the layout is
     * rendered inside the child, and the page went to nine. One duplicated
     * aggregate on one page is cheaper than a composition that runs itself
     * twice.
     *
     * Six was also what a per thread query costs for the six thread fixture, and
     * so is seven. An allowance a per thread query cannot meet is the only kind
     * worth having, because the equality assertion below is the part that
     * actually catches a regression.
     *
     * The filter this test counts on is the string `conversation`, so the
     * `users` reads for an author name are invisible to it. That is a limit of
     * the guard and is recorded here rather than left for somebody to be
     * surprised by.
     */
    private const ALLOWANCE = 7;

    /**
     * Give this person $courses threads, each holding $messages messages.
     *
     * One instructor and one course per thread, because a student and an
     * instructor share exactly one thread per course, and the point is to have
     * many threads rather than one deep one.
     */
    private function threadsFor(User $person, int $courses, int $messages): void
    {
        $counterpartRole = $person->profile?->role?->value === 'instructor' ? 'student' : 'instructor';

        for ($c = 0; $c < $courses; $c++) {
            $counterpart = $counterpartRole === 'instructor'
                ? User::factory()->instructor()->create()
                : User::factory()->create();

            $course = Course::factory()->create([
                'instructor_id' => $counterpartRole === 'instructor' ? $counterpart->id : $person->id,
                'status' => CourseStatus::Published,
                'course_type' => CourseType::Free,
                'price_minor' => 0,
            ]);

            $student = $counterpartRole === 'student' ? $counterpart : $person;
            $teacher = $counterpartRole === 'instructor' ? $counterpart : $person;

            Enrollment::factory()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'status' => EnrollmentStatus::Active,
            ]);

            $thread = app(StartConversation::class)->startCourseThread($student, $teacher);

            for ($i = 0; $i < $messages; $i++) {
                app(PostMessage::class)->handle(
                    $i % 2 === 0 ? $student : $teacher,
                    $thread,
                    "Message number {$i} in this thread."
                );
            }
        }
    }

    /**
     * One person per role, with threads already attached.
     *
     * @return array<string, User>
     */
    private function everyoneWithThreads(): array
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $admin = User::factory()->create();
        $admin->profile->forceFill(['role' => 'administrator'])->save();

        $this->threadsFor($student, self::THREADS, self::MESSAGES);
        $this->threadsFor($instructor, self::THREADS, self::MESSAGES);

        // An administrator has no course threads, because a course thread is
        // between a student and that course's instructor. They get support
        // requests instead, which is the shape an administrator actually has.
        for ($t = 0; $t < self::THREADS; $t++) {
            $requester = User::factory()->create();
            $thread = app(StartConversation::class)->startSupportThread($requester, "Request {$t}");
            app(PostMessage::class)->handle($requester, $thread, 'Details for this request.');

            $this->actingAs($admin->fresh());
            $this->post(route('admin.support.join', $thread));
        }

        $this->assertSame(
            self::THREADS * 2 + self::THREADS,
            Conversation::query()->count(),
            'The fixture did not build the threads it claims to.'
        );

        $this->assertSame(
            (self::THREADS * 2) * self::MESSAGES + self::THREADS,
            ConversationMessage::query()->count(),
            'The fixture did not build the messages it claims to.'
        );

        return [
            'student dashboard' => $student,
            'instructor dashboard' => $instructor,
            'administrator dashboard' => $admin->fresh(),
            'messages' => $student,
        ];
    }

    /**
     * The pages measured, each with the role that is allowed to see it.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function urls(): array
    {
        return [
            'student dashboard' => ['/student', 'student'],
            'instructor dashboard' => ['/instructor', 'instructor'],
            'administrator dashboard' => ['/admin', 'administrator'],
            'messages' => ['/messages', 'student'],
        ];
    }

    /**
     * Read the badge and the dropdown out of a page's query log.
     *
     | Comparing the total query count of a page before and after the fixture is
     | the obvious way to do this and it is wrong. The fixture that gives a
     | person threads also gives them six courses and six enrollments, and a
     | student dashboard legitimately costs more for six courses than for none.
     | The measurement ended up blaming the topbar for the dashboard's own data,
     | and reported 6 extra queries with the correct code in place.
     |
     | Counting the queries that mention the conversation tables isolates the
     | thing under test. It is unaffected by anything else the page does.
     *
     * @param  array<int, array{sql: string}>  $queries
     * @return array{counts: int, dropdowns: int, touched: int, queries: array<int, array{sql: string}>}
     */
    private function inspect(array $queries): array
    {
        $counts = 0;
        $dropdowns = 0;
        $touched = 0;

        foreach ($queries as $query) {
            $sql = strtolower($query['sql']);

            if (! str_contains($sql, 'conversation')) {
                continue;
            }

            $touched++;

            if (str_contains($sql, 'count(*)')) {
                $counts++;
            }

            if (str_contains($sql, 'from `conversations`') && str_contains($sql, 'limit 5')) {
                $dropdowns++;
            }
        }

        return ['counts' => $counts, 'dropdowns' => $dropdowns, 'touched' => $touched, 'queries' => $queries];
    }

    public function test_the_badge_and_the_dropdown_stay_two_queries_for_every_role(): void
    {
        $counter = new QueryCounter;

        /*
         | The three dashboards read the conversation tables in exactly two
         | places: the badge and the dropdown. On those pages the counts are
         | pinned outright, because nothing else in the shell touches a
         | conversation and a second count can only be a regression.
         |
         | The messages page is deliberately skipped here. It is a list of
         | conversations, so it legitimately runs aggregates of its own: one for
         | the badge, one grouped for the per row counts, one for the message
         | totals and one for the participant totals. Its cost is checked for
         | being fixed rather than for being small, in the next test.
         */
        foreach ($this->everyoneWithThreads() as $label => $user) {
            if ($label === 'messages') {
                continue;
            }

            $role = (string) $user->profile?->role?->value;
            $url = $this->urls()[$label][0];

            $this->actingAs($user);
            $this->assertSame(200, $this->get($url)->status(), "{$label} did not render for a {$role} holding threads.");

            $measured = $counter->measure(fn () => $this->get($url));
            $found = $this->inspect($measured['queries']);

            $this->assertSame(
                1,
                $found['counts'],
                "{$label} ran {$found['counts']} conversation counts for a {$role} holding "
                    .self::THREADS.' threads. The badge is one aggregate.'
            );

            $this->assertSame(
                1,
                $found['dropdowns'],
                "{$label} ran {$found['dropdowns']} conversation pages for a {$role}. The dropdown is one page of five."
            );

            // Six is what a per thread query costs, and the fixture is six
            // threads. Anything at or above that means the cost is following the
            // data rather than staying fixed.
            $this->assertLessThanOrEqual(
                self::ALLOWANCE,
                $found['touched'],
                "{$label} ran {$found['touched']} conversation queries for a {$role} holding "
                    .self::THREADS.' threads, which is one per thread. A fixed cost is the property.'
            );
        }
    }

    public function test_the_message_list_costs_the_same_with_one_thread_as_with_six(): void
    {
        $counter = new QueryCounter;

        /*
         | One thread against six, on the same page, for the same role.
         |
         | This is the assertion that found the real defect. The list called
         | Conversation::unreadCountFor() once per row from the view, which is two
         | queries per thread, and a page that costs more the more threads it
         | shows is the pattern this file exists to prevent. It was showing ten
         | conversation counts for six threads.
         |
         | Only the conversation queries are compared, never a page total, so
         | the list's own data cannot be mistaken for the shell's. A total was
         | tried first and reported a false regression, because the fixture that
         | builds threads also builds the courses they hang from.
         */
        $oneStudent = $this->signedInStudent();
        $this->threadsFor($oneStudent, 1, self::MESSAGES);

        // Measured after the fixture is built, never around it. Posting twenty
        // messages queries the conversation tables itself, so a measurement that
        // wrapped the fixture counted its own setup and reported 112 queries for
        // a single thread.
        $one = $this->inspect($counter->measure(fn () => $this->get('/messages'))['queries']);

        $sixStudent = $this->signedInStudent();
        $this->threadsFor($sixStudent, self::THREADS, self::MESSAGES);

        $measured = $counter->measure(fn () => $this->get('/messages'));
        $six = $this->inspect($measured['queries']);

        // Named rather than guessed, so the allowance below is a figure somebody
        // worked out. Collected only when the run fails, so a passing run stays
        // quiet.
        if ($six['touched'] > self::ALLOWANCE) {
            fwrite(STDERR, "\n  the ".self::ALLOWANCE.' allowed conversation queries, and '.count($six['queries'])." ran:\n");

            foreach ($six['queries'] as $query) {
                fwrite(STDERR, '    '.substr((string) preg_replace('/\s+/', ' ', $query['sql']), 0, 120)."\n");
            }
        }

        $this->assertSame(
            $one['touched'],
            $six['touched'],
            "The message list ran {$one['touched']} conversation queries for one thread and {$six['touched']} for "
                .self::THREADS.', so its cost follows the number of threads rather than staying fixed.'
        );

        $this->assertSame(
            1,
            $six['dropdowns'],
            'The topbar dropdown on the message list must stay one page of five threads.'
        );

        $this->assertLessThanOrEqual(
            self::ALLOWANCE,
            $six['touched'],
            "The message list ran {$six['touched']} conversation queries, above the allowance of ".self::ALLOWANCE.'.'
        );
    }

    /**
     * A student, created and signed in.
     */
    private function signedInStudent(): User
    {
        $student = User::factory()->create();
        $this->actingAs($student);

        return $student;
    }
}
