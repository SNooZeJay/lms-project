<?php

namespace Tests\Feature\Messaging;

use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\StartConversation;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\QueryCounter;
use Tests\TestCase;

/**
 * The topbar message panel has to say what a thread is about.
 *
 * The panel listed each thread as a course title and a time. Two threads on two
 * courses looked identical apart from those two facts, and the only unread
 * figure anywhere was the total on the button, so a reader holding five threads
 * with three unread could not tell which three.
 *
 * Both facts were already on the model. `Conversation` has a `lastMessage`
 * relation and an `unreadCountFor` method, and neither was in the query the shell
 * ran. The panel was not showing data the application could not produce; it was
 * not asking for it.
 *
 * A preview is a row of somebody else's writing, so the scoping matters more
 * here than anywhere else in the topbar. A leak would put a private message on
 * every page of the application, so the cross thread case is asserted rather
 * than assumed.
 */
class TopbarMessagePanelTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;

    private User $instructor;

    private Conversation $thread;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = User::factory()->create();
        $this->instructor = User::factory()->instructor()->create();

        $this->thread = $this->courseThread('Structured Programming');
    }

    public function test_the_panel_shows_the_last_message_that_was_sent(): void
    {
        $this->say($this->instructor, 'Could you explain the second loop again?');
        $this->say($this->reader, 'Yes, I was stuck on the counter.');

        $panel = $this->panelFor($this->reader);

        $this->assertStringContainsString(
            'Yes, I was stuck on the counter.',
            $panel,
            'The panel named a course and a time and nothing about what had been said, so two threads '
            .'on two courses were indistinguishable.'
        );
    }

    public function test_the_panel_counts_what_is_unread_in_each_thread(): void
    {
        $this->say($this->instructor, 'First question.');
        $this->say($this->instructor, 'Second question.');

        $this->assertSame(2, $this->thread->unreadCountFor($this->reader), 'The fixture should hold two unread.');

        $panel = $this->panelFor($this->reader);

        $this->assertMatchesRegularExpression(
            '/>\s*2\s*</',
            $panel,
            'The panel did not say that this thread holds two unread messages. The button said two '
            .'unread in total, which does not say where they are.'
        );
    }

    /** A thread the reader has caught up on is not marked unread. */
    public function test_a_thread_with_nothing_unread_is_not_counted(): void
    {
        $this->say($this->instructor, 'First question.');
        $this->say($this->reader, 'Read.');

        // The reader has read everything the instructor sent.
        $last = DB::table('conversation_messages')
            ->where('conversation_id', $this->thread->id)
            ->orderByDesc('id')
            ->value('id');

        DB::table('conversation_participants')
            ->where('conversation_id', $this->thread->id)
            ->where('user_id', $this->reader->id)
            ->update(['last_read_message_id' => $last]);

        $this->assertSame(0, $this->thread->unreadCountFor($this->reader), 'The fixture should hold nothing unread.');

        $panel = $this->panelFor($this->reader);

        $this->assertStringContainsString('Structured Programming', $panel);
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*1\s*</',
            $panel,
            'A thread with nothing unread was still marked as holding one.'
        );
    }

    /** A thread with no messages at all must not render an empty preview. */
    public function test_a_thread_with_no_messages_shows_no_preview(): void
    {
        $panel = $this->panelFor($this->reader);

        $this->assertStringContainsString('Structured Programming', $panel);
        $this->assertStringNotContainsString('undefined', $panel);
    }

    /**
     * The preview is somebody else's writing, so a thread the reader is not in
     * must contribute nothing at all.
     */
    public function test_another_thread_is_never_previewed_in_the_panel(): void
    {
        $this->say($this->instructor, 'A message the reader is allowed to see.');

        $stranger = User::factory()->create();
        $other = $this->courseThread('Networking Basics', $stranger);
        $this->say($this->instructor, 'A message meant for somebody else entirely.', $other);

        $panel = $this->panelFor($this->reader);

        $this->assertStringContainsString('A message the reader is allowed to see.', $panel);
        $this->assertStringNotContainsString(
            'A message meant for somebody else entirely.',
            $panel,
            'A message from a thread the reader is not in was rendered in the topbar. The topbar is on '
            .'every page, so this would repeat the leak on every request.'
        );
    }

    /**
     * A preview and a per thread unread count must not become a loop.
     */
    public function test_the_panel_costs_the_same_with_one_thread_as_with_several(): void
    {
        $this->say($this->instructor, 'Something to show.');

        $one = $this->conversationQueriesFor($this->reader);

        foreach (range(1, 5) as $ignored) {
            $this->say($this->instructor, 'More to show.', $this->courseThread('Course number '.$ignored));
        }

        $six = $this->conversationQueriesFor($this->reader);

        $this->assertSame(
            $one,
            $six,
            "The panel cost {$one} conversation queries with one thread and {$six} with six, so the "
            .'preview or the unread count is being read per thread.'
        );
    }

    /**
     * A published course, an enrolled learner, and the thread those two share.
     *
     * The enrollment is not optional. `startCourseThread` derives the course from
     * the reader's enrollment rather than taking one, which is what stops a
     * thread being started for a course somebody is not in.
     */
    private function courseThread(string $title, ?User $student = null): Conversation
    {
        $student ??= $this->reader;

        $course = Course::factory()->for($this->instructor, 'instructor')->create([
            'title' => $title,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return app(StartConversation::class)->startCourseThread($student, $this->instructor);
    }

    private function say(User $author, string $body, ?Conversation $thread = null): void
    {
        app(PostMessage::class)->handle($author, $thread ?? $this->thread, $body);
    }

    /**
     * The message panel markup, with the rest of the page removed so an
     * assertion cannot pass on a word that happens to appear elsewhere.
     */
    private function panelFor(User $user): string
    {
        $body = (string) $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk()
            ->getContent();

        $at = strpos($body, 'id="topbar-messages"');

        $this->assertNotFalse($at, 'The topbar message panel was not rendered at all.');

        // From the panel's own id to the end of its markup, which the component
        // closes with the two nested divs that follow the list.
        $panel = substr($body, $at, 6000);

        $this->assertStringContainsString('id="topbar-messages"', $panel);

        return $panel;
    }

    /**
     * The conversation queries one render of a page with the topbar costs.
     */
    private function conversationQueriesFor(User $user): int
    {
        $queries = (new QueryCounter)->measure(
            fn () => $this->actingAs($user)->get(route('conversations.index'))
        )['queries'];

        return count(array_filter(
            $queries,
            fn (array $query): bool => str_contains($query['sql'], 'conversation')
        ));
    }
}
