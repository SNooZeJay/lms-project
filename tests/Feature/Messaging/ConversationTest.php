<?php

namespace Tests\Feature\Messaging;

use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\StartConversation;
use App\Actions\Messaging\ThreadState;
use App\Actions\Notifications\RecordNotification;
use App\Enums\ConversationStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Threads, and who may see them.
 *
 * The plan's authorization matrix has three answers that are easy to get wrong
 * and that this file pins:
 *
 *   - an instructor cannot read a support thread, even about a student they teach
 *   - an administrator cannot post into a course thread
 *   - a student cannot reach a thread for a course they are not enrolled in
 *
 * The rule underneath all three is the participant table, not a role check, so
 * there is one thing to be right rather than a matrix of special cases.
 */
class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create();
    }

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $user->fresh();
    }

    /**
     * A published course, an instructor who owns it, and an enrolled student.
     *
     * @return array{0: Course, 1: User, 2: User}
     */
    private function courseWithPair(EnrollmentStatus $enrollment = EnrollmentStatus::Active): array
    {
        $instructor = $this->instructor();
        $student = $this->student();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => $enrollment,
        ]);

        return [$course, $instructor, $student];
    }

    private function courseThread(User $requester, User $counterpart): Conversation
    {
        return app(StartConversation::class)->startCourseThread($requester, $counterpart);
    }

    /* ------------------------------------------------------------ the matrix */

    public function test_a_student_and_an_instructor_share_one_thread_per_course(): void
    {
        [$course, $instructor, $student] = $this->courseWithPair();

        $first = $this->courseThread($student, $instructor);
        $second = $this->courseThread($instructor, $student);

        $this->assertSame($first->id, $second->id, 'A second thread was opened for the same pair and course.');
        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(2, $first->participants()->count());
    }

    public function test_a_student_may_open_a_thread_with_their_instructor(): void
    {
        [, $instructor, $student] = $this->courseWithPair();

        $this->actingAs($student)->get(route('conversations.index'))->assertOk()->assertSee('No conversations yet');

        app(PostMessage::class)->handle($student, $this->courseThread($student, $instructor), 'Hello, I have a question about lesson three.');

        $this->actingAs($student)->get(route('conversations.index'))->assertOk()->assertSee('Hello');
    }

    public function test_a_student_cannot_open_a_thread_with_an_instructor_they_do_not_share_a_course_with(): void
    {
        $instructor = $this->instructor();
        $stranger = $this->student();

        // A course exists, but the student is not enrolled in it.
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => CourseStatus::Published]);

        $this->expectException(ValidationException::class);

        app(StartConversation::class)->startCourseThread($stranger, $instructor);
    }

    public function test_a_student_with_a_cancelled_enrollment_cannot_open_a_thread(): void
    {
        [, $instructor, $student] = $this->courseWithPair(EnrollmentStatus::Cancelled);

        $this->expectException(ValidationException::class);

        app(StartConversation::class)->startCourseThread($student, $instructor);
    }

    public function test_a_student_cannot_open_another_students_thread(): void
    {
        [$course, $instructor, $student] = $this->courseWithPair();
        $other = $this->student();
        Enrollment::factory()->create(['student_id' => $other->id, 'course_id' => $course->id]);

        $thread = $this->courseThread($student, $instructor);

        $this->actingAs($other)
            ->get(route('conversations.show', $thread))
            ->assertForbidden();
    }

    public function test_an_instructor_cannot_open_a_thread_in_a_course_they_do_not_own(): void
    {
        $owner = $this->instructor();
        $student = $this->student();
        $impostor = $this->instructor();

        $course = Course::factory()->create(['instructor_id' => $owner->id, 'status' => CourseStatus::Published]);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id]);

        $this->expectException(ValidationException::class);

        app(StartConversation::class)->startCourseThread($impostor, $student);
    }

    public function test_an_administrator_cannot_post_into_a_course_thread(): void
    {
        [$course, $instructor, $student] = $this->courseWithPair();
        $admin = $this->admin();

        $thread = $this->courseThread($student, $instructor);

        $this->actingAs($admin)
            ->from(route('conversations.show', $thread))
            ->post(route('conversations.messages.store', $thread), ['body' => 'Adding my notes.'])
            ->assertForbidden();

        $this->assertSame(0, $thread->messages()->count());
    }

    public function test_an_administrator_cannot_read_a_course_thread_they_are_not_in(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $this->actingAs($this->admin())
            ->get(route('conversations.show', $thread))
            ->assertForbidden();
    }

    /* -------------------------------------------------------- support threads */

    public function test_a_support_request_is_visible_to_its_requester_and_no_instructor(): void
    {
        $student = $this->student();
        $instructor = $this->instructor();
        $admin = $this->admin();

        $thread = app(StartConversation::class)->startSupportThread($student, 'I cannot open my course');
        app(PostMessage::class)->handle($student, $thread, 'The page will not load for me.');

        // The person who raised it.
        $this->actingAs($student)->get(route('conversations.show', $thread))->assertOk();

        // An instructor cannot, even about a student they teach, because they
        // are not a participant. This is the answer the plan calls out and the
        // one a special case would get wrong.
        $this->actingAs($instructor)->get(route('conversations.show', $thread))->assertForbidden();

        // An administrator can, once they have joined it.
        $this->actingAs($admin)->get(route('conversations.index'))->assertOk();
    }

    public function test_only_an_administrator_can_list_support_requests(): void
    {
        $student = $this->student();

        $thread = app(StartConversation::class)->startSupportThread($student, 'Something is wrong');
        app(PostMessage::class)->handle($student, $thread, 'Details here.');

        $this->actingAs($this->admin())
            ->get(route('admin.support.index'))
            ->assertOk()
            ->assertSee('Something is wrong');

        $this->actingAs($this->instructor())->get(route('admin.support.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.support.index'))->assertForbidden();
    }

    /**
     * A guest is sent to sign in.
     *
     * Its own method on purpose. actingAs persists across requests in a test, so
     * a guest assertion made after an actingAs call in the same method is
     * really asserting about the last signed in person. The project's existing
     * boundary test is split the same way.
     */
    public function test_a_guest_is_sent_to_sign_in_rather_than_shown_a_support_list(): void
    {
        $this->get(route('admin.support.index'))->assertRedirect(route('login'));
    }

    public function test_an_administrator_can_close_a_support_thread_and_nobody_else_can(): void
    {
        $student = $this->student();
        $thread = app(StartConversation::class)->startSupportThread($student, 'Please help');
        app(PostMessage::class)->handle($student, $thread, 'It will not load.');

        $this->actingAs($student)
            ->from(route('conversations.show', $thread))
            ->patch(route('conversations.close', $thread))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->from(route('admin.support.index'))
            ->patch(route('conversations.close', $thread))
            ->assertRedirect();

        $this->assertSame(ConversationStatus::Closed, $thread->fresh()->status);
    }

    public function test_a_closed_thread_is_read_only(): void
    {
        $student = $this->student();
        $thread = app(StartConversation::class)->startSupportThread($student, 'Please help');
        app(PostMessage::class)->handle($student, $thread, 'First.');

        $thread->forceFill(['status' => ConversationStatus::Closed])->save();

        $this->expectException(AuthorizationException::class);

        app(PostMessage::class)->handle($student, $thread, 'Second.');
    }

    /* ------------------------------------------------------------ idempotency */

    public function test_the_same_client_token_produces_one_message(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);
        $action = new PostMessage(app(RecordNotification::class));

        $token = (string) Str::uuid();

        $first = $action->handle($student, $thread, 'Sent once.', $token);
        $second = $action->handle($student, $thread, 'Sent once.', $token);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate'], 'A repeated token must be reported as a duplicate.');
        $this->assertSame($first['message']->id, $second['message']->id);
        $this->assertSame(1, $thread->messages()->count());
    }

    public function test_different_tokens_produce_different_messages(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);
        $action = new PostMessage(app(RecordNotification::class));

        $action->handle($student, $thread, 'One.', (string) Str::uuid());
        $action->handle($student, $thread, 'Two.', (string) Str::uuid());

        $this->assertSame(2, $thread->messages()->count());
    }

    public function test_two_simultaneous_first_messages_produce_one_thread(): void
    {
        [$course, $instructor, $student] = $this->courseWithPair();

        /*
         | Inserted past the action on purpose. The point is that the database
         | refuses the second thread, not that the action checks for one first:
         | two requests can both pass a check, so only the unique index settles
         | it. Both racers write the same key, because the key is the pair in a
         | fixed order rather than whoever happened to press the button first.
         */
        $pair = [$instructor->id, $student->id];
        sort($pair, SORT_NUMERIC);
        $key = 'course:'.$pair[0].'-'.$pair[1];

        $accepted = 0;

        foreach ([$student, $instructor] as $requester) {
            try {
                DB::table('conversations')->insert([
                    'kind' => 'course',
                    'course_id' => $course->id,
                    'requester_id' => $requester->id,
                    'status' => 'open',
                    'thread_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $accepted++;
            } catch (QueryException $e) {
                $this->assertStringContainsString('conversations_deterministic_thread_unique', $e->getMessage());
            }
        }

        $this->assertSame(1, $accepted, 'Both racers were allowed to open a thread, so the unique index is not doing its job.');
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_the_thread_key_does_not_depend_on_who_opened_the_thread(): void
    {
        [, $instructor, $student] = $this->courseWithPair();

        $fromStudent = $this->courseThread($student, $instructor);
        $fromInstructor = $this->courseThread($instructor, $student);

        $this->assertSame($fromStudent->thread_key, $fromInstructor->thread_key);
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_two_separate_problems_from_one_person_are_two_support_threads(): void
    {
        $student = $this->student();
        $action = app(StartConversation::class);

        $first = $action->startSupportThread($student, 'One problem');
        $second = $action->startSupportThread($student, 'A different problem');

        $this->assertNotSame($first->id, $second->id, 'Two separate problems were collapsed into one thread.');
        $this->assertSame(2, Conversation::query()->count());
    }

    /* ----------------------------------------------------------- notification */

    public function test_a_message_notifies_the_other_participants_and_nobody_else(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $bystander = $this->student();

        $thread = $this->courseThread($student, $instructor);
        app(PostMessage::class)->handle($student, $thread, 'A question about lesson three.');

        $this->assertSame(1, Notification::query()->where('user_id', $instructor->id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $student->id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $bystander->id)->count());
    }

    public function test_the_notification_link_is_stored_because_the_recipient_is_in_the_thread(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        app(PostMessage::class)->handle($student, $thread, 'A question.');

        $notice = Notification::query()->where('user_id', $instructor->id)->firstOrFail();

        $this->assertSame(NotificationType::CourseMessage, $notice->type);
        $this->assertNotNull($notice->link, 'The link was dropped even though the recipient is in the thread.');
    }

    /* -------------------------------------------------------------- answering */

    public function test_an_administrator_picks_up_a_request_and_can_then_answer_it(): void
    {
        $student = $this->student();
        $admin = $this->admin();

        $thread = app(StartConversation::class)->startSupportThread($student, 'The upload fails');
        app(PostMessage::class)->handle($student, $thread, 'It stops at 90 percent.');

        // Before joining, the administrator cannot open it. The participant row
        // is the whole of the grant, and nobody has written one for them yet.
        $this->actingAs($admin)
            ->get(route('conversations.show', $thread))
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.support.index'))
            ->post(route('admin.support.join', $thread))
            ->assertRedirect(route('conversations.show', $thread));

        $this->actingAs($admin)
            ->get(route('conversations.show', $thread))
            ->assertOk()
            ->assertSee('It stops at 90 percent.');

        app(PostMessage::class)->handle($admin, $thread, 'Thank you, we are looking at it.');

        $this->assertSame(2, $thread->messages()->count());
    }

    public function test_picking_up_a_request_tells_the_person_who_raised_it(): void
    {
        $student = $this->student();
        $thread = app(StartConversation::class)->startSupportThread($student, 'Cannot sign in');
        app(PostMessage::class)->handle($student, $thread, 'It says my account is not active.');

        $this->actingAs($this->admin())
            ->from(route('admin.support.index'))
            ->post(route('admin.support.join', $thread));

        $notice = Notification::query()
            ->where('user_id', $student->id)
            ->where('type', NotificationType::SupportReply)
            ->firstOrFail();

        $this->assertNotNull(
            $notice->link,
            'The notice about the request being picked up was stored without a link the recipient can follow.'
        );
    }

    public function test_picking_up_the_same_request_twice_does_not_duplicate_it(): void
    {
        $student = $this->student();
        $admin = $this->admin();

        $thread = app(StartConversation::class)->startSupportThread($student, 'A question');
        app(PostMessage::class)->handle($student, $thread, 'Details.');

        $this->actingAs($admin)->post(route('admin.support.join', $thread));
        $this->actingAs($admin)->post(route('admin.support.join', $thread));

        $this->assertSame(1, $thread->participants()->where('user_id', $admin->id)->count());
        $this->assertSame(
            1,
            Notification::query()->where('user_id', $student->id)->where('type', NotificationType::SupportReply)->count(),
            'The person who raised the request was told more than once.'
        );
    }

    public function test_nobody_but_an_administrator_can_pick_up_a_request(): void
    {
        $student = $this->student();
        $thread = app(StartConversation::class)->startSupportThread($student, 'A question');
        app(PostMessage::class)->handle($student, $thread, 'Details.');

        $this->actingAs($this->instructor())
            ->post(route('admin.support.join', $thread))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.support.join', $thread))
            ->assertForbidden();

        $this->assertSame(1, $thread->participants()->count());
    }

    public function test_a_course_thread_cannot_be_picked_up_as_a_support_request(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $this->actingAs($this->admin())
            ->post(route('admin.support.join', $thread))
            ->assertNotFound();

        $this->assertSame(2, $thread->participants()->count());
    }

    public function test_a_support_message_is_not_course_scoped_and_a_course_message_is(): void
    {
        /*
         | The pairing has to be right in both directions.
         |
         | A single NEW_MESSAGE type could not be: a course thread has a course
         | and a support thread has none, while isCourseScoped() is a property of
         | the type so that no row can be unclassifiable. Whichever way that
         | single type was declared, one kind of thread became unwritable. The
         | seam caught it by refusing to record the notice, which is the rule
         | working, and these two types are the fix.
         */
        $student = $this->student();
        [, $instructor, $studentInCourse] = $this->courseWithPair();
        $admin = $this->admin();

        $support = app(StartConversation::class)->startSupportThread($student, 'A problem');
        app(PostMessage::class)->handle($student, $support, 'About the platform.');

        // Nobody to notify yet: a support request has one participant until an
        // administrator picks it up, so the first message reaches nobody. That is
        // correct, and it is why the vocabulary split below only became visible
        // once the join flow existed.
        $this->assertSame(0, Notification::query()->where('user_id', $student->id)->count());

        $this->actingAs($admin)->post(route('admin.support.join', $support));
        app(PostMessage::class)->handle($student, $support, 'Still about the platform.');

        $course = $this->courseThread($studentInCourse, $instructor);
        app(PostMessage::class)->handle($studentInCourse, $course, 'About lesson three.');

        // Addressed to the recipient, which for a support thread is the
        // administrator who picked it up. The person who wrote it is not
        // notified about their own message.
        $supportNotice = Notification::query()
            ->where('user_id', $admin->id)
            ->where('type', NotificationType::SupportMessage)
            ->firstOrFail();

        $this->assertNull(
            $supportNotice->course_id,
            'A notice about a support request was pinned to a course.'
        );

        $courseNotice = Notification::query()
            ->where('user_id', $instructor->id)
            ->where('type', NotificationType::CourseMessage)
            ->firstOrFail();

        $this->assertSame($course->course_id, $courseNotice->course_id);
    }

    /* ------------------------------------------------------------- read state */

    public function test_unread_is_counted_from_the_messages_not_stored(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        app(PostMessage::class)->handle($student, $thread, 'One.');
        app(PostMessage::class)->handle($instructor, $thread, 'Two.');
        app(PostMessage::class)->handle($student, $thread, 'Three.');

        $this->assertSame(1, $thread->unreadCountFor($instructor), 'The instructor has not read the reply.');
        $this->assertSame(0, $thread->unreadCountFor($student), 'The student has read their own messages.');

        app(ThreadState::class)->markRead($instructor, $thread);

        $this->assertSame(0, $thread->fresh()->unreadCountFor($instructor));
    }

    /**
     * A suspended account is signed out and sent to sign in.
     *
     * Not a 403. The account.active middleware runs first, logs the session out
     * and explains why, which is the right answer for an account that is no
     * longer permitted in: the session should not survive it. Asserting the
     * policy's 403 here would have been asserting a layer that never runs.
     */
    public function test_a_suspended_account_is_signed_out_of_a_thread(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $student->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();

        $this->actingAs($student->fresh())
            ->get(route('conversations.show', $thread))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /* -------------------------------------------------------------- rendering */

    public function test_a_thread_row_shows_real_figures_rather_than_a_count_of_nothing(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        app(PostMessage::class)->handle($student, $thread, 'One.');
        app(PostMessage::class)->handle($instructor, $thread, 'Two.');
        app(PostMessage::class)->handle($student, $thread, 'Three.');

        $html = (string) $this->actingAs($instructor)
            ->get(route('conversations.index'))
            ->assertOk()
            ->getContent();

        /*
         | The row reads "3 messages, 2 people". The figures come from a subquery
         | count, so an attribute name that does not match what withCount
         | produced arrives as null and the row renders "messages, people" with
         | nothing in front of them, which is a sentence about nobody. The
         | measurement found it and a passing test suite did not, because every
         | other test posts to the route rather than reading the row.
         */
        $this->assertStringContainsString('3', $html, 'The message count is missing from the thread row.');
        $this->assertMatchesRegularExpression(
            '/\b3\s+messages\b/',
            $html,
            'The thread row does not read "3 messages", so the count is not reaching the page.'
        );
        $this->assertMatchesRegularExpression(
            '/\b2\s+people\b/',
            $html,
            'The thread row does not read "2 people", so the participant count is not reaching the page.'
        );
    }

    /* ------------------------------------------------------------- rendering */

    /**
     * The composer is a real textarea.
     *
     * A form that cannot be typed into is the failure this exists for. The
     * thread view built its textarea as a child of x-form-field, and that
     * component renders its own control from its type and takes no slot, so the
     * textarea was discarded and a single line text input was rendered in its
     * place. Every test passed, because a test that posts to the route never
     * loads the page a person types on. Asserting on the markup is the only
     * thing that catches it.
     */
    public function test_the_composer_is_a_textarea_named_body(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $html = (string) $this->actingAs($student)
            ->get(route('conversations.show', $thread))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="body"/',
            $html,
            'The reply box is not a textarea named body.'
        );

        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="body"[^>]*rows="\d+"/',
            $html,
            'The reply box has no rows, so it renders as a single line.'
        );

        // The idempotency token has to be in the form, or a double click posts
        // the same message twice.
        $this->assertMatchesRegularExpression(
            '/name="client_token"/',
            $html,
            'The reply form carries no client token, so a double click would post twice.'
        );
    }

    public function test_the_support_form_has_a_subject_and_a_textarea(): void
    {
        $html = (string) $this->actingAs($this->student())
            ->get(route('support.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="subject"/', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="body"/', $html);
    }

    public function test_a_closed_thread_has_no_composer(): void
    {
        $student = $this->student();
        $thread = app(StartConversation::class)->startSupportThread($student, 'Please help');
        app(PostMessage::class)->handle($student, $thread, 'First.');

        $thread->forceFill(['status' => ConversationStatus::Closed])->save();

        $html = (string) $this->actingAs($student)
            ->get(route('conversations.show', $thread))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'name="body"',
            $html,
            'A closed thread still offers a reply box, which invites a message that will be refused.'
        );

        $this->assertStringContainsString('read only', $html);
    }

    public function test_a_message_body_is_stored_as_written_and_escaped_on_output(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $hostile = '<script>alert(1)</script> & "quoted"';

        app(PostMessage::class)->handle($student, $thread, $hostile);

        $stored = ConversationMessage::query()->firstOrFail();

        $this->assertSame($hostile, $stored->body, 'The body was altered on the way in.');

        $html = (string) $this->actingAs($instructor)
            ->get(route('conversations.show', $thread))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_an_empty_message_is_refused(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $this->expectException(ValidationException::class);

        app(PostMessage::class)->handle($student, $thread, "   \n  ");
    }

    public function test_a_message_over_the_limit_is_refused(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $this->actingAs($student)
            ->from(route('conversations.show', $thread))
            ->post(route('conversations.messages.store', $thread), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $thread->messages()->count());
    }

    public function test_a_guest_cannot_reach_any_thread_route(): void
    {
        [, $instructor, $student] = $this->courseWithPair();
        $thread = $this->courseThread($student, $instructor);

        $this->get(route('conversations.index'))->assertRedirect(route('login'));
        $this->get(route('conversations.show', $thread))->assertRedirect(route('login'));
    }

    public function test_closing_and_reopening_a_support_thread_toggles_the_state(): void
    {
        $admin = $this->admin();
        $thread = app(StartConversation::class)->startSupportThread($this->student(), 'Help');
        $state = app(ThreadState::class);

        $state->close($admin, $thread);
        $this->assertFalse($thread->fresh()->isOpen());

        $state->reopen($admin, $thread);
        $this->assertTrue($thread->fresh()->isOpen());
    }
}
