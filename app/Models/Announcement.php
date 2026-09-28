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
     * THIS QUERY AND AnnouncementPolicy::view ARE ONE RULE
     *
     * The controller says it builds the list from here so that a list filtered one
     * way and a page judged another would not be two rules, and for a while they
     * were. The policy has always let two people read a course announcement: the
     * one who wrote it, and the instructor who owns the course. This query joined
     * only through an enrollment, so an instructor could open the announcement at
     * its own address and then find no list containing it. Publishing was reachable,
     * the notice reached the students, and the author had nothing to read it from
     * and nothing to withdraw it with.
     *
     * The two claims are added here rather than loosened in the policy, because both
     * are claims to your own content rather than to somebody else's, and neither
     * reaches a course the reader has no part in. The policy is the authority and
     * this is the same question asked of many rows; a test asserts the two agree for
     * every combination of reader and announcement, so a clause added to one side
     * alone is caught rather than shipped.
     *
     * Course announcements also need their course published. An announcement in a
     * draft course is about content the reader cannot open, and a notice about it
     * would lead to a page that refuses. The author is exempt, matching the policy,
     * which answers for the author before it looks at the course at all.
     *
     * The active account check is not repeated here. AnnouncementPolicy::viewAny
     * requires it and EnsureAccountIsActive stops a suspended account before a
     * controller runs, so a third copy in the query would be a rule that could
     * disagree with those two rather than one that agrees with them.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $query->where(function (Builder $outer) use ($viewer): void {
            // What this person wrote, at either scope.
            $outer->where('author_id', $viewer->id);

            $outer->orWhere('scope', AnnouncementScope::Platform->value);

            $outer->orWhere(function (Builder $course) use ($viewer): void {
                $course->where('scope', AnnouncementScope::Course->value)
                    /*
                     * The sub-queries are typed against the query builder, not the
                     * Eloquent one. whereExists hands the closure a plain
                     * Illuminate\Database\Query\Builder, and hinting it as the
                     * Eloquent class is a TypeError on the first platform
                     * announcement anybody reads.
                     *
                     * Enrolled, or the instructor who owns it. These are grouped
                     * rather than left as two siblings, because an ungrouped pair
                     * would let the published check below bind to the owner branch
                     * alone and hand a student in a draft course the announcement.
                     */
                    ->where(function (Builder $who) use ($viewer): void {
                        $who->whereExists(function (QueryBuilder $enrollment) use ($viewer): void {
                            $enrollment->selectRaw('1')
                                ->from('enrollments')
                                ->whereColumn('enrollments.course_id', 'announcements.course_id')
                                ->where('enrollments.student_id', $viewer->id)
                                ->whereIn('enrollments.status', [
                                    EnrollmentStatus::Active,
                                    EnrollmentStatus::Completed,
                                ]);
                        });

                        $who->orWhereExists(function (QueryBuilder $owned) use ($viewer): void {
                            $owned->selectRaw('1')
                                ->from('courses')
                                ->whereColumn('courses.id', 'announcements.course_id')
                                ->where('courses.instructor_id', $viewer->id);
                        });
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
