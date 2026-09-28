<?php

namespace Tests\Feature\Announcements;

use App\Actions\Announcements\PublishAnnouncement;
use App\Actions\Notifications\MarkNotificationRead;
use App\Enums\AnnouncementScope;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Announcements, and who hears about them.
 *
 * The plan's test plan asks for four things and this file covers all four plus
 * the authorization matrix, because the matrix is where the interesting answers
 * are:
 *
 *   - a course announcement reaches only enrolled students
 *   - a platform announcement reaches active accounts only
 *   - a suspended account receives nothing
 *   - read state is the notification's read state
 *
 * Written before the model, the policy and the action, and every one of them
 * fails first.
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $profile = []): User
    {
        $student = User::factory()->create();

        $student->profile->forceFill(array_merge([
            'role' => UserRole::Student,
            'account_status' => UserAccountStatus::Active,
        ], $profile))->save();

        return $student->fresh();
    }

    private function instructor(): User
    {
        return User::factory()->instructor()->create()->fresh();
    }

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $admin->fresh();
    }

    private function publishedCourse(?User $instructor = null): Course
    {
        $course = Course::factory()->create([
            'instructor_id' => ($instructor ?? $this->instructor())->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Module::factory()->create(['course_id' => $course->id, 'status' => ContentStatus::Published]);

        return $course;
    }

    private function enroll(User $student, Course $course, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => $status,
        ]);
    }

    private function noticesFor(User $user, NotificationType $type): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->count();
    }

    /* --------------------------------------------------------------- creating */

    public function test_an_instructor_publishes_a_course_announcement(): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);

        $announcement = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'The exam is on Friday instead.');

        $this->assertSame(AnnouncementScope::Course, $announcement->scope);
        $this->assertSame($course->id, $announcement->course_id);
        $this->assertSame($instructor->id, $announcement->author_id);
        $this->assertNotNull($announcement->published_at, 'An announcement with no date cannot be listed in order.');
    }

    public function test_an_administrator_publishes_a_platform_announcement_with_no_course(): void
    {
        $admin = $this->administrator();

        $announcement = app(PublishAnnouncement::class)
            ->platformAnnouncement($admin, 'Maintenance on Sunday', 'The site is down from 02:00 to 04:00.');

        $this->assertSame(AnnouncementScope::Platform, $announcement->scope);
        $this->assertNull($announcement->course_id, 'A platform announcement belongs to no course.');
    }

    public function test_a_student_cannot_publish_an_announcement(): void
    {
        $student = $this->student();
        $course = $this->publishedCourse();

        $this->expectException(AuthorizationException::class);

        app(PublishAnnouncement::class)
            ->courseAnnouncement($student, $course, 'Hello', 'Body');
    }

    public function test_an_instructor_cannot_publish_into_a_course_they_do_not_own(): void
    {
        $owner = $this->instructor();
        $impostor = $this->instructor();
        $course = $this->publishedCourse($owner);

        $this->expectException(AuthorizationException::class);

        app(PublishAnnouncement::class)
            ->courseAnnouncement($impostor, $course, 'Hello', 'Body');
    }

    public function test_an_instructor_cannot_publish_a_platform_announcement(): void
    {
        $this->expectException(AuthorizationException::class);

        app(PublishAnnouncement::class)
            ->platformAnnouncement($this->instructor(), 'Hello', 'Body');
    }

    public function test_an_administrator_cannot_publish_a_course_announcement(): void
    {
        // Administration is not teaching. A course announcement is the
        // instructor's to make, and allowing it here would let a platform
        // administrator speak into somebody's course.
        $course = $this->publishedCourse();

        $this->expectException(AuthorizationException::class);

        app(PublishAnnouncement::class)
            ->courseAnnouncement($this->administrator(), $course, 'Hello', 'Body');
    }

    /* ---------------------------------------------------------------- the fan-out */

    public function test_a_course_announcement_reaches_only_the_enrolled_students(): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);

        $enrolled = $this->student();
        $completed = $this->student();
        $cancelled = $this->student();
        $pending = $this->student();
        $notEnrolled = $this->student();
        $otherCourseStudent = $this->student();
        $instructorElsewhere = $this->instructor();

        $this->enroll($enrolled, $course);
        $this->enroll($completed, $course, EnrollmentStatus::Completed);
        $this->enroll($cancelled, $course, EnrollmentStatus::Cancelled);
        $this->enroll($pending, $course, EnrollmentStatus::PendingPayment);

        $other = $this->publishedCourse($instructorElsewhere);
        $this->enroll($otherCourseStudent, $other);

        app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $this->assertSame(1, $this->noticesFor($enrolled, NotificationType::Announcement));
        $this->assertSame(1, $this->noticesFor($completed, NotificationType::Announcement));
        $this->assertSame(0, $this->noticesFor($cancelled, NotificationType::Announcement));
        $this->assertSame(0, $this->noticesFor($pending, NotificationType::Announcement));
        $this->assertSame(0, $this->noticesFor($notEnrolled, NotificationType::Announcement));
        $this->assertSame(0, $this->noticesFor($otherCourseStudent, NotificationType::Announcement));
        $this->assertSame(0, $this->noticesFor($instructor, NotificationType::Announcement));
    }

    public function test_a_suspended_student_receives_nothing(): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);

        $suspended = $this->student(['account_status' => UserAccountStatus::Suspended]);
        $active = $this->student();

        $this->enroll($suspended, $course);
        $this->enroll($active, $course);

        app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $this->assertSame(
            0,
            $this->noticesFor($suspended, NotificationType::Announcement),
            'A suspended account was told about an announcement, and the link would refuse them.'
        );

        $this->assertSame(1, $this->noticesFor($active, NotificationType::Announcement));
    }

    public function test_a_platform_announcement_reaches_every_active_account(): void
    {
        $admin = $this->administrator();
        $instructor = $this->instructor();
        $student = $this->student();
        $suspendedStudent = $this->student(['account_status' => UserAccountStatus::Suspended]);
        $suspendedInstructor = $this->instructor();
        $suspendedInstructor->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();
        $suspendedInstructor = $suspendedInstructor->fresh();

        app(PublishAnnouncement::class)
            ->platformAnnouncement($admin, 'Maintenance', 'Down from 02:00.');

        $this->assertSame(1, $this->noticesFor($student, NotificationType::SystemAnnouncement));
        $this->assertSame(1, $this->noticesFor($instructor, NotificationType::SystemAnnouncement));
        $this->assertSame(0, $this->noticesFor($suspendedStudent, NotificationType::SystemAnnouncement));
        $this->assertSame(0, $this->noticesFor($suspendedInstructor, NotificationType::SystemAnnouncement));
    }

    public function test_publishing_the_same_announcement_twice_cannot_be_done(): void
    {
        // There is no edit and no draft, so the only way to publish twice would
        // be a double submit. The action refuses a second announcement with the
        // same title in the same scope and course, which is what a double submit
        // produces.
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);
        $action = app(PublishAnnouncement::class);

        $action->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $this->expectException(ValidationException::class);

        $action->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');
    }

    /* ------------------------------------------------------------- reading them */

    public function test_the_list_shows_platform_announcements_and_the_courses_this_student_is_in(): void
    {
        $student = $this->student();

        // The instructor has to own both courses, or the publish is refused and
        // the test proves nothing about the list. publishedCourse() makes its own
        // instructor when it is not told whose course this is, and the first
        // version of this test then published as somebody else entirely.
        $instructor = $this->instructor();
        $mine = $this->publishedCourse($instructor);
        $theirs = $this->publishedCourse($instructor);
        $this->enroll($student, $mine);

        $platform = $this->administrator();

        app(PublishAnnouncement::class)
            ->platformAnnouncement($platform, 'Maintenance', 'Down from 02:00.');

        app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $mine, 'About my course', 'Hello.');

        app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $theirs, 'About their course', 'Hello.');

        $html = (string) $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Maintenance', $html);
        $this->assertStringContainsString('About my course', $html);
        $this->assertStringNotContainsString(
            'About their course',
            $html,
            'A student was shown an announcement for a course they are not enrolled in.'
        );
    }

    public function test_the_list_shows_an_instructor_the_announcements_they_published(): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);

        app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Bring a laptop', 'Thursday needs a machine.');

        $html = (string) $this->actingAs($instructor)
            ->get(route('announcements.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'Bring a laptop',
            $html,
            'The instructor published this and cannot see it. Publishing is reachable, the notice reaches the students, and the person who wrote it has no list to read it from or withdraw it with.'
        );
    }

    public function test_the_list_does_not_show_an_instructor_another_instructors_course_announcement(): void
    {
        $mine = $this->instructor();
        $theirs = $this->instructor();
        $theirCourse = $this->publishedCourse($theirs);

        app(PublishAnnouncement::class)
            ->courseAnnouncement($theirs, $theirCourse, 'Their news', 'Not for me.');

        $html = (string) $this->actingAs($mine)
            ->get(route('announcements.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'Their news',
            $html,
            'Widening the list to an instructor must stop at the courses they own, or one instructor reads another instructors communication.'
        );
    }

    /**
     * The list and the policy must answer the same question, which is what
     * AnnouncementController::index says it is for.
     *
     * The controller's own rule is that a list built from a different filter than
     * the policy is two rules, and a hand-typed id is judged by the second one
     * alone. That is exactly the shape of the fault this found: the policy has
     * always let the author and the course owner read a course announcement, while
     * the query behind the list only joined through an enrollment, so an instructor
     * could open the announcement at its own address and then find no list
     * containing it. Checking one actor against many announcements rather than
     * asserting a single page catches that, and catches it again the next time a
     * clause is added to one side and not the other.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function readersAndAnnouncements(): array
    {
        return [
            'the author' => ['author', 'ownCourse'],
            'the instructor of the course' => ['owner', 'ownCourse'],
            'a student enrolled in the course' => ['enrolledStudent', 'ownCourse'],
            'a student in another course' => ['otherStudent', 'ownCourse'],
            'an instructor of another course' => ['otherInstructor', 'ownCourse'],
            'an administrator' => ['administrator', 'ownCourse'],
            'anybody at all, for a platform notice' => ['otherInstructor', 'platform'],
        ];
    }

    #[DataProvider('readersAndAnnouncements')]
    public function test_the_list_agrees_with_the_policy_about_every_announcement(string $reader, string $published): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);

        $announcement = $published === 'platform'
            ? app(PublishAnnouncement::class)->platformAnnouncement($this->administrator(), 'Maintenance', 'Down from 02:00.')
            : app(PublishAnnouncement::class)->courseAnnouncement($instructor, $course, 'Bring a laptop', 'Thursday needs a machine.');

        /*
         * Six readers against two kinds of announcement, chosen so that the answers
         * differ from one row to the next rather than all being true or all being
         * false. A single pair would have passed against the fault: the author and
         * the owner are the two the policy allows and the list omitted.
         */
        $actor = match ($reader) {
            'author', 'owner' => $instructor,
            'enrolledStudent' => $this->student(),
            'otherStudent' => $this->student(),
            'otherInstructor' => $this->instructor(),
            'administrator' => $this->administrator(),
        };

        if ($reader === 'enrolledStudent') {
            $this->enroll($actor, $course);
        }

        $inList = Announcement::query()
            ->visibleTo($actor)
            ->whereKey($announcement->id)
            ->exists();

        $byPolicy = Gate::forUser($actor)->allows('view', $announcement);

        $this->assertSame(
            $byPolicy,
            $inList,
            "The list and the policy disagree for a {$reader} reading a {$published} announcement. The list is built from scopeVisibleTo and the page is judged by the policy, so a disagreement means one of the two can be shown something the other refuses."
        );
    }

    public function test_a_hand_typed_id_for_somebody_elses_course_does_not_resolve(): void
    {
        $student = $this->student();
        // The instructor must own the course, or the publish is refused and this
        // test proves nothing about reading.
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);

        // The student is nowhere near this course, so the id must not reach a page.
        $announcement = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Private to them', 'Hello.');

        $this->actingAs($student)
            ->get(route('announcements.show', $announcement))
            ->assertForbidden();
    }

    public function test_an_enrolled_student_can_open_the_announcement(): void
    {
        $student = $this->student();
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);
        $this->enroll($student, $course);

        $announcement = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $this->actingAs($student)
            ->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Exam moved');
    }

    public function test_the_author_can_withdraw_and_nobody_else_can(): void
    {
        $instructor = $this->instructor();
        $other = $this->instructor();
        $course = $this->publishedCourse($instructor);

        $announcement = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $this->actingAs($other)
            ->delete(route('announcements.destroy', $announcement))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->from(route('announcements.index'))
            ->delete(route('announcements.destroy', $announcement))
            ->assertRedirect();

        $this->assertNull(
            Announcement::query()->find($announcement->id),
            'The announcement survived a withdrawal by its author.'
        );
    }

    /**
     * Withdrawing an announcement takes its notices with it.
     *
     * A notice is a row in notifications pointing at an announcement through
     * subject_type and subject_id. There is no foreign key between them and there
     * cannot be: the same two columns point at a quiz, a certificate and a
     * conversation as well, so the reference is polymorphic and the database has
     * nothing to hang a constraint on. Nothing cascades, and the withdraw action
     * deleted the announcement and nothing else, so every student who had been
     * told kept a notice whose link now answers 404.
     *
     * This is the one place in the schema where the database cannot help and the
     * application has to, which makes the test the mechanism rather than a
     * convenience. It was found by auditing notifications for subjects that no
     * longer exist, on three live rows.
     */
    public function test_withdrawing_an_announcement_withdraws_its_notices(): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);
        $student = $this->student();
        $this->enroll($student, $course);

        $announcement = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $this->assertSame(
            1,
            $this->noticesFor($student, NotificationType::Announcement),
            'The student was not told, so there is nothing for this test to withdraw.'
        );

        $this->actingAs($instructor)
            ->from(route('announcements.index'))
            ->delete(route('announcements.destroy', $announcement))
            ->assertRedirect();

        $this->assertNull(Announcement::query()->find($announcement->id));

        $this->assertSame(
            0,
            $this->noticesFor($student, NotificationType::Announcement),
            'The student still holds a notice about an announcement that no longer exists, and following it answers 404.'
        );

        $this->assertSame(
            0,
            Notification::query()
                ->where('subject_type', 'announcement')
                ->where('subject_id', $announcement->id)
                ->count(),
            'A notice still points at the withdrawn announcement. subject_id is not a foreign key, so nothing else would have removed it.'
        );
    }

    /**
     * Withdrawing one announcement leaves the notices for the others alone.
     *
     * Without this, a fix that removed every notice rather than the ones about
     * this announcement would pass the test above and silently swallow unrelated
     * mail. The subject is what separates them, so the delete is keyed on it.
     */
    public function test_withdrawing_one_announcement_leaves_the_notices_for_another(): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);
        $student = $this->student();
        $this->enroll($student, $course);

        $withdrawn = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $kept = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Room change', 'The annexe this week.');

        $this->assertSame(2, $this->noticesFor($student, NotificationType::Announcement));

        $this->actingAs($instructor)
            ->from(route('announcements.index'))
            ->delete(route('announcements.destroy', $withdrawn))
            ->assertRedirect();

        $remaining = Notification::query()
            ->where('user_id', $student->id)
            ->where('subject_type', 'announcement')
            ->pluck('subject_id')
            ->all();

        $this->assertSame(
            [$kept->id],
            $remaining,
            'Withdrawing one announcement took a notice that was about a different one.'
        );
    }

    /* ------------------------------------------------------------- read state */

    public function test_read_state_is_the_notifications_read_state_and_not_a_second_column(): void
    {
        $instructor = $this->instructor();
        $course = $this->publishedCourse($instructor);
        $student = $this->student();
        $this->enroll($student, $course);

        $announcement = app(PublishAnnouncement::class)
            ->courseAnnouncement($instructor, $course, 'Exam moved', 'Friday instead.');

        $notice = Notification::query()
            ->where('user_id', $student->id)
            ->where('type', NotificationType::Announcement)
            ->firstOrFail();

        $this->assertNull($notice->read_at, 'The notice arrived already read, so nothing was left to read.');

        $this->assertFalse(
            $announcement->isReadBy($student),
            'The announcement reported itself unread before anything was read.'
        );

        app(MarkNotificationRead::class)->handle($student, $notice);

        $this->assertTrue(
            $announcement->isReadBy($student),
            'Marking the notice read did not mark the announcement read, so there are two read states.'
        );

        // And there is no second place for it to live.
        $this->assertFalse(
            Schema::hasColumn('announcements', 'read_at'),
            'The announcements table carries its own read state, which is the duplicate system the plan rules out.'
        );
    }
}
