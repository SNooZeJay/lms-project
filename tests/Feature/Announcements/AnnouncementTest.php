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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
