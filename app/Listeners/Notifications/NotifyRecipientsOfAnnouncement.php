<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\AnnouncementScope;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Events\AnnouncementPublished;
use App\Models\Announcement;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\LessonPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Tells the people an announcement was published to.
 *
 * The recipient set is the whole of the design, and it comes from one query per
 * kind rather than a loop with a check inside.
 *
 * A course announcement goes to the students the batched policy already
 * identifies as authorized for that course, which includes the account-status
 * check that the enrollment alone does not do. A platform announcement goes to
 * every active account, because "everybody" is what a platform announcement
 * means and filtering it to enrolled students would make it a course
 * announcement in disguise.
 *
 * In both cases a suspended account is absent, and that is the requirement the
 * specification states twice: nobody hears about something they could not open.
 */
class NotifyRecipientsOfAnnouncement
{
    public function __construct(
        private readonly RecordNotification $notify,
        private readonly AnnouncementPolicy $policy,
    ) {}

    public function handle(AnnouncementPublished $event): void
    {
        $announcement = $event->announcement;

        $recipients = $announcement->scope === AnnouncementScope::Course
            ? $this->courseStudents($announcement)
            : $this->everyActiveAccount();

        if ($recipients === []) {
            return;
        }

        $link = route('announcements.show', $announcement);
        $type = $announcement->scope === AnnouncementScope::Course
            ? NotificationType::Announcement
            : NotificationType::SystemAnnouncement;

        foreach ($recipients as $recipient) {
            $this->notify->handle(
                $recipient,
                $type,
                $announcement->title,
                $this->preview($announcement->body),
                // A platform notice is not course scoped and the seam refuses a
                // course on one, so the course travels only when there is one.
                course: $announcement->isCourseScoped() ? $announcement->course : null,
                dedupKey: $announcement->dedupKeyFor($recipient->id),
                link: $link,
                // Asked again per recipient even though the set was already
                // filtered, because this is the rule the seam enforces for
                // everything and a link nobody can open is dropped rather than
                // stored.
                authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('view', $announcement),
                subjectType: 'announcement',
                subjectId: $announcement->id,
            );
        }
    }

    /**
     * The students of one course, from the batched query.
     *
     * @return list<User>
     */
    private function courseStudents(Announcement $announcement): array
    {
        $course = $announcement->course;

        if ($course === null) {
            return [];
        }

        $ids = app(LessonPolicy::class)->authorizedStudentIdsForCourse($course);

        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->get()->all();
    }

    /**
     * Everybody with an active account, in one query.
     *
     * Students, instructors and administrators, because a platform announcement
     * is addressed to the institution and an instructor who is never told about a
     * maintenance window finds out the hard way.
     *
     * @return list<User>
     */
    private function everyActiveAccount(): array
    {
        return User::query()
            ->whereHas('profile', fn ($query) => $query
                ->where('account_status', UserAccountStatus::Active)
                ->whereIn('role', [
                    UserRole::Student,
                    UserRole::Instructor,
                    UserRole::Administrator,
                ]))
            ->get()
            ->all();
    }

    /**
     * The opening words, because a notice list shows a couple of lines.
     */
    private function preview(string $body): string
    {
        $flat = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        if (mb_strlen($flat) <= 140) {
            return $flat;
        }

        return mb_substr($flat, 0, 137).'...';
    }
}
