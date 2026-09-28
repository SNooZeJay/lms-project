<?php

namespace App\Models;

use App\Enums\AnnouncementScope;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Something said out loud, to everybody or to one course.
 *
 * There is no read_at, and that is the design rather than an omission. Read state
 * is the notification row's read_at, which is one place to mark read and one
 * index to query. A second column here would be a second answer to "has this
 * person seen it", and the two would eventually disagree.
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'scope' => AnnouncementScope::class,
            'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isCourseScoped(): bool
    {
        return $this->scope === AnnouncementScope::Course;
    }

    /**
     * The announcements one person may be shown.
     *
     * Course announcements arrive through an enrollment rather than through the
     * announcement, which is what makes a hand-typed id resolve to a 403 instead
     * of to somebody else's course. A student who is not enrolled has no course
     * to join the query through, so the condition is false for them rather than
     * true and then filtered afterwards.
     *
     * Course announcements also need their course published. An announcement in a
     * draft course is about content the reader cannot open, and a notice about it
     * would lead to a page that refuses.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $query->where(function (Builder $outer) use ($viewer): void {
            $outer->where('scope', AnnouncementScope::Platform->value);

            $outer->orWhere(function (Builder $course) use ($viewer): void {
                $course->where('scope', AnnouncementScope::Course->value)
                    /*
                     * The two sub-queries are typed against the query builder,
                     * not the Eloquent one. whereExists hands the closure a plain
                     * Illuminate\Database\Query\Builder, and hinting it as the
                     * Eloquent class is a TypeError on the first platform
                     * announcement anybody reads.
                     */
                    ->whereExists(function (QueryBuilder $enrollment) use ($viewer): void {
                        $enrollment->selectRaw('1')
                            ->from('enrollments')
                            ->whereColumn('enrollments.course_id', 'announcements.course_id')
                            ->where('enrollments.student_id', $viewer->id)
                            ->whereIn('enrollments.status', [
                                EnrollmentStatus::Active,
                                EnrollmentStatus::Completed,
                            ]);
                    })
                    ->whereExists(function (QueryBuilder $published): void {
                        $published->selectRaw('1')
                            ->from('courses')
                            ->whereColumn('courses.id', 'announcements.course_id')
                            ->where('courses.status', CourseStatus::Published);
                    });
            });
        });
    }

    /**
     * Whether this person has read it, which is whether their notice is read.
     *
     * Asked of the notification rather than stored here, so the answer and the
     * thing that marks it read can never disagree.
     */
    public function isReadBy(User $viewer): bool
    {
        $notice = Notification::query()
            ->where('user_id', $viewer->id)
            ->where('type', $this->isCourseScoped() ? NotificationType::Announcement : NotificationType::SystemAnnouncement)
            ->where('subject_type', 'announcement')
            ->where('subject_id', $this->id)
            ->first();

        return $notice !== null && $notice->read_at !== null;
    }

    /**
     * The dedup key this announcement's notice carries, for one recipient.
     *
     * Built here so the fan-out and the read check cannot disagree about which
     * row belongs to whom.
     */
    public function dedupKeyFor(int $userId): string
    {
        return "announcement:{$this->id}:user:{$userId}";
    }
}
