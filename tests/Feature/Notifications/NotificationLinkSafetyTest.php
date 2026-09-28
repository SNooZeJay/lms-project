<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What the write seam refuses, and what it does instead.
 *
 * A notification is the one place in this application where the server composes
 * a link and stores it for later. That makes it worth being strict about. Every
 * refusal here is a case where a more permissive seam would have produced
 * something that looks fine and is not: an off-site address, a notice pointing
 * at a page the reader would be refused, a blank row in the list.
 *
 * The plan's test list includes "a recipient with no access receives nothing".
 * In this slice the seam is where access is decided, because the fan-out that
 * filters whole recipients arrives later and will call in here. What the seam
 * can prove today is the half it owns: it will never store a link the recipient
 * is not allowed to follow. Suppressing a notice entirely is the fan-out's job
 * and is proven with the batched policy when that lands.
 */
class NotificationLinkSafetyTest extends TestCase
{
    use RefreshDatabase;

    private RecordNotification $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->record = new RecordNotification;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function offSiteLinks(): array
    {
        return [
            'absolute https' => ['https://evil.test/steal', 'an absolute address'],
            'absolute http' => ['http://evil.test', 'an absolute address'],
            'protocol relative' => ['//evil.test/path', 'a protocol relative address'],
            'javascript uri' => ['javascript:alert(1)', 'a javascript uri'],
            'data uri' => ['data:text/html,<script>alert(1)</script>', 'a data uri'],
            'no leading slash' => ['student/courses', 'a relative path'],
        ];
    }

    #[DataProvider('offSiteLinks')]
    public function test_a_link_that_leaves_this_application_is_refused(string $link, string $description): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'Finished.',
            course: $course,
            link: $link,
            authorizeLink: fn (): bool => true,
        );
    }

    public function test_a_link_containing_control_characters_is_refused(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'Finished.',
            course: $course,
            link: "/student/courses\nX-Injected: 1",
            authorizeLink: fn (): bool => true,
        );
    }

    public function test_a_link_with_no_authorization_check_is_refused(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        // The omission is the point. If a link could be stored without anybody
        // deciding whether the reader may follow it, then some listener would
        // eventually do exactly that.
        $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'Finished.',
            course: $course,
            link: '/student/courses/1/lessons/2',
        );
    }

    /**
     * A path may contain a quote, and it is stored, because refusing it would be
     * refusing a legitimate title that happens to include one.
     *
     * What matters is that it cannot break out of the href when it is rendered.
     * Blade escapes the quote, so the attribute closes where it should. This was
     * checked rather than assumed, because a link is the one value in this
     * feature that a person types into a form and a browser later follows.
     *
     * A backtick, by contrast, is left alone by the escaper and is harmless
     * inside a double quoted attribute. It was briefly asserted as a threat here
     * and that assertion was wrong.
     */
    public function test_a_path_containing_a_quote_is_stored_and_cannot_break_out_of_the_attribute(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $stored = $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'Finished.',
            course: $course,
            link: '/student/courses/'.$course->id.'" onmouseover="alert(1)',
            authorizeLink: fn (): bool => true,
        );

        $this->assertNotNull($stored);

        $rendered = '<a href="'.e($stored->link).'">Open</a>';

        $this->assertStringNotContainsString('"', substr($rendered, 10, -12), 'No raw quote may survive inside the attribute value.');
        $this->assertStringContainsString('&quot;', $rendered, 'The quote must be present in escaped form.');
        $this->assertStringNotContainsString('onmouseover="alert', $rendered);
    }

    public function test_a_link_the_recipient_may_not_follow_is_dropped_and_the_notice_is_still_delivered(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $stored = $this->record->handle(
            $student,
            NotificationType::CourseCompleted,
            'You finished the course.',
            course: $course,
            link: '/student/courses/'.$course->id,
            // The reader cannot open this, which is the situation the check exists for.
            authorizeLink: fn (): bool => false,
        );

        $this->assertNotNull($stored, 'Losing a destination is not a reason to lose the information.');
        $this->assertNull($stored->link, 'A notice must not carry a link its reader would be refused.');
        $this->assertSame('You finished the course.', $stored->title);
    }

    public function test_a_link_the_recipient_may_follow_is_kept(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $stored = $this->record->handle(
            $student,
            NotificationType::CourseCompleted,
            'You finished the course.',
            course: $course,
            link: '/student/courses/'.$course->id,
            authorizeLink: fn (): bool => true,
        );

        $this->assertSame('/student/courses/'.$course->id, $stored->link);
    }

    public function test_the_authorization_check_receives_the_recipient_not_the_caller(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create();

        $seen = null;

        $this->record->handle(
            $student,
            NotificationType::CourseCompleted,
            'Done.',
            course: $course,
            link: '/student/courses/'.$course->id,
            authorizeLink: function (User $who) use (&$seen): bool {
                $seen = $who->id;

                return true;
            },
        );

        $this->assertSame($student->id, $seen);
        $this->assertNotSame($other->id, $seen);
    }

    /* ------------------------------------------------------------- refusals */

    public function test_a_blank_title_is_refused(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->record->handle($student, NotificationType::LessonStarted, "   \n  ", course: $course);
    }

    public function test_a_title_over_the_column_length_is_refused(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->record->handle($student, NotificationType::LessonStarted, str_repeat('a', 161), course: $course);
    }

    public function test_a_course_scoped_type_without_a_course_is_refused(): void
    {
        $student = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->record->handle($student, NotificationType::LessonCompleted, 'Finished something.');
    }

    public function test_a_platform_type_pinned_to_a_course_is_refused(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->record->handle($student, NotificationType::SystemAnnouncement, 'Maintenance.', course: $course);
    }

    public function test_a_blank_dedup_key_is_refused(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        // An empty string is not "no key". Left alone it would collide with
        // every other empty string for that recipient and suppress unrelated
        // notices.
        $this->record->handle($student, NotificationType::LessonStarted, 'x', course: $course, dedupKey: '  ');
    }

    public function test_a_refused_notice_writes_nothing(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        try {
            $this->record->handle($student, NotificationType::LessonCompleted, 'x', course: $course, link: 'https://evil.test');
        } catch (\InvalidArgumentException) {
            // expected
        }

        // Scoped to this student, who is the only recipient anybody in this test
        // could have written for. The notifications table has other writers now
        // that the automation listeners exist, and a total across it measures
        // them rather than this.
        $this->assertSame(0, Notification::query()->where('user_id', $student->id)->count());
    }

    /* ---------------------------------------------------------- the vocabulary */

    public function test_the_vocabulary_holds_eighteen_types(): void
    {
        // Seventeen in the plan. The eighteenth is SupportMessage, which split
        // the plan's single NEW_MESSAGE in two so that a course thread and a
        // support thread each have a type whose course scope is fixed. The
        // count is pinned so a type cannot be added without somebody saying so.
        $this->assertCount(18, NotificationType::cases());
    }

    public function test_only_the_platform_and_support_types_are_not_course_scoped(): void
    {
        $notScoped = array_values(array_map(
            static fn (NotificationType $t): string => $t->value,
            array_filter(NotificationType::cases(), static fn (NotificationType $t): bool => ! $t->isCourseScoped()),
        ));

        // A support request is raised by a person about the system, so it has no
        // course to point at. ANNOUNCEMENT is on this side of the line the other
        // way: a course announcement belongs to a course, and a platform one is
        // SYSTEM_ANNOUNCEMENT. It was listed here by mistake and slice 11 found
        // it, when the seam refused to store the notice for a course
        // announcement.
        //
        // Listed in declaration order, and pinned exactly: a new type in this list
        // changes the test, and one left out by accident is refused at runtime by
        // the seam rather than silently misfiled.
        $this->assertSame([
            'support_message',
            'support_reply',
            'system_announcement',
        ], $notScoped);
    }

    /* ------------------------------------------ the host a request actually arrived on */

    /**
     * A link built by route() during a request is this application, whatever host
     * the request arrived on.
     *
     * route() returns an absolute address taken from the request in flight, so a
     * link composed by any of the eleven listeners is on the host the person doing
     * the work is using. The guard only compared that against config('app.url'),
     * which is the address the application believes it is published at, and the
     * two are not the same thing whenever the application is reached by another
     * name: localhost against a tunnel, 127.0.0.1 against localhost, or any other
     * port.
     *
     * That is not hypothetical. The whole suite agreed with itself about this:
     * the test environment sets APP_URL to http://127.0.0.1:8000, and the test
     * client requests http://127.0.0.1:8000, so every notification test compared
     * two identical hosts and the refusal was never reached. Posting an
     * announcement to a course over localhost while the published address was the
     * ngrok tunnel returned HTTP 500 with "A notification link must stay on this
     * application" raised from inside a notice that was entirely safe.
     *
     * The tests below make the two hosts disagree on purpose, which is the only
     * way to reach the branch.
     */
    public function test_a_link_on_the_host_the_request_arrived_on_is_this_application(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->arriveAt('http://localhost:8000');

        $this->assertNotSame(
            request()->getHost(),
            parse_url((string) config('app.url'), PHP_URL_HOST),
            'This test is only meaningful while the two hosts disagree. If they match, the guard is never reached and the test passes for the wrong reason.'
        );

        $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'Finished.',
            course: $course,
            link: route('announcements.index'),
            authorizeLink: fn (): bool => true,
        );

        $notification = Notification::query()->where('user_id', $student->id)->sole();

        $this->assertSame('/announcements', $notification->link);
    }

    public function test_the_stored_link_is_a_path_even_when_the_request_host_was_spoofed(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        // The reason accepting the request's host does not weaken the guard.
        // A Host header an attacker chose is this application's host as far as the
        // guard can tell, but the value is reduced to a path before it is stored,
        // so nothing off-site can reach the column and no notice can point at it.
        $this->arriveAt('https://attacker.example');

        $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'Finished.',
            course: $course,
            link: route('announcements.index'),
            authorizeLink: fn (): bool => true,
        );

        $this->assertSame('/announcements', Notification::query()->where('user_id', $student->id)->sole()->link);
    }

    public function test_a_third_host_is_still_refused_while_a_request_is_in_flight(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->arriveAt('http://localhost:8000');

        $this->expectException(\InvalidArgumentException::class);

        // Same scheme, same port, neither configured nor in flight. This is the
        // case the guard exists for, and it must keep failing now that two hosts
        // are allowed rather than one.
        $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'Finished.',
            course: $course,
            link: 'http://elsewhere.test:8000/steal',
            authorizeLink: fn (): bool => true,
        );
    }

    public function test_an_instructor_can_publish_to_a_course_when_the_two_hosts_disagree(): void
    {
        // The fault as it was actually met: a real request, a real publish, and a
        // notice with no reason to exist. The controller and the listener are
        // exercised together, because fixing the guard while the listener kept
        // building absolute links would move the failure rather than end it.
        $instructor = User::factory()->instructor()->create();
        $student = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        // The factory, because Enrollment only accepts student_id and course_id
        // from a caller. A hand built row here is silently pending_payment, which
        // the recipient query excludes, and the notice then goes to nobody for a
        // reason that has nothing to do with the host being tested.
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->arriveAt('http://localhost:8000');

        $this->actingAs($instructor)
            ->post(route('instructor.courses.announcements.store', $course), [
                'title' => 'Bring a laptop',
                'body' => 'The workshop needs a machine you can install things on.',
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            Notification::query()->where('user_id', $student->id)->count(),
            'The enrolled student was not told, because the notice was raised as a fault rather than written.'
        );
    }

    /**
     * Put a request with a chosen host in the container, as the framework does
     * when a request arrives.
     */
    private function arriveAt(string $url): void
    {
        $this->app->instance('request', Request::create($url));
    }
}
