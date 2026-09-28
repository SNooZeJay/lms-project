<?php

namespace App\Actions\Announcements;

use App\Enums\AnnouncementScope;
use App\Events\AnnouncementPublished;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Notification;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saying something out loud.
 *
 * The row is the whole of the record. Read state belongs to the notice it
 * produces, and a draft or an edit does not exist, so there is nothing here that
 * could need one later.
 *
 * The double submit is refused by comparing against an existing row rather than
 * by a unique index, and that is a deliberate departure from the house pattern.
 * The reason to refuse is that two identical announcements read as two different
 * pieces of news, and a unique index on (course_id, title) would also refuse two
 * legitimately different announcements that happen to share a title. The check
 * is inside the transaction, so two requests arriving together cannot both pass
 * it.
 */
class PublishAnnouncement
{
    public function courseAnnouncement(User $actor, Course $course, string $title, string $body): Announcement
    {
        /*
         | Asked directly rather than through Gate.
         |
         | The ability is about a Course, but the Policy is registered against
         | Announcement, so Gate would look for CoursePolicy::createCourse, find
         | nothing and refuse everybody. That is the same reason
         | ConversationPolicy::startCourseThread is called directly: an ability
         | whose subject is not the model the Policy belongs to has no Gate string
         | that means anything.
         */
        if (! app(AnnouncementPolicy::class)->createCourse($actor, $course)) {
            throw new AuthorizationException('You cannot publish an announcement to this course.');
        }

        return $this->publish(
            $actor,
            AnnouncementScope::Course,
            $course,
            $title,
            $body,
        );
    }

    public function platformAnnouncement(User $actor, string $title, string $body): Announcement
    {
        // Asked directly for the same reason as above: there is no model here at
        // all, so Gate would have to guess which Policy to reach for.
        if (! app(AnnouncementPolicy::class)->createPlatform($actor)) {
            throw new AuthorizationException('You cannot publish an announcement to everybody.');
        }

        return $this->publish(
            $actor,
            AnnouncementScope::Platform,
            null,
            $title,
            $body,
        );
    }

    /**
     * Withdraw one, by its author, and the notices that announced it.
     *
     * A notice points at the announcement through subject_type and subject_id,
     * which is a reference no foreign key can hold: the same two columns point at a
     * quiz, a certificate and a conversation, so it is polymorphic and the database
     * has nothing to hang a constraint on. Nothing cascades, so deleting the
     * announcement alone left every student who had been told holding a notice
     * whose link answers 404. Three such rows were live before this was found by
     * auditing notifications for subjects that no longer exist.
     *
     * Both writes are in one transaction, so a withdrawal either takes the notice
     * with it or does not happen. Doing them in order, the notice first, means a
     * failure between the two leaves the announcement without a notice, which is a
     * withdrawal that has not been announced rather than a notice about nothing.
     *
     * The delete is keyed on the subject, not on the type alone, so withdrawing one
     * announcement cannot take a notice that was about a different one.
     */
    public function withdraw(User $actor, Announcement $announcement): void
    {
        Gate::forUser($actor)->authorize('delete', $announcement);

        DB::transaction(function () use ($announcement): void {
            Notification::query()
                ->where('subject_type', 'announcement')
                ->where('subject_id', $announcement->id)
                ->delete();

            $announcement->delete();
        });
    }

    private function publish(
        User $actor,
        AnnouncementScope $scope,
        ?Course $course,
        string $title,
        string $body,
    ): Announcement {
        $title = trim($title);
        $body = trim($body);

        $announcement = DB::transaction(function () use ($actor, $scope, $course, $title, $body): Announcement {
            $this->refuseADuplicate($actor, $scope, $course, $title);

            $announcement = new Announcement;
            $announcement->forceFill([
                'scope' => $scope,
                'course_id' => $course?->id,
                'author_id' => $actor->id,
                'title' => $title,
                'body' => $body,
                'published_at' => now(),
            ]);
            $announcement->save();

            /*
             | Dispatched from inside the transaction, and deferred by the event
             | class rather than by a call here.
             |
             | A notice written before this row commits would point at an
             | announcement that might not exist, and if the transaction rolled
             | back it would be a notice about something that never happened.
             */
            AnnouncementPublished::dispatch($announcement);

            return $announcement;
        });

        return $announcement;
    }

    /**
     * Two identical announcements read as two pieces of news.
     *
     * Scoped to the author, so two instructors can each post a "Welcome" without
     * colliding, and to the course, so a platform announcement and a course one
     * with the same words are two different statements.
     */
    private function refuseADuplicate(User $actor, AnnouncementScope $scope, ?Course $course, string $title): void
    {
        $existing = Announcement::query()
            ->where('author_id', $actor->id)
            ->where('scope', $scope->value)
            ->when($course === null,
                fn ($query) => $query->whereNull('course_id'),
                fn ($query) => $query->where('course_id', $course->id))
            ->where('title', $title)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'title' => 'You have already published an announcement with this title.',
            ]);
        }
    }
}
