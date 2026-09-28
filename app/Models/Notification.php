<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One notice addressed to one account.
 *
 * The text is a snapshot. It is written when the notice is raised and never
 * recomputed, so renaming a course afterwards does not rewrite what somebody
 * was already told.
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'subject_id' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeRead(Builder $query): void
    {
        $query->whereNotNull('read_at');
    }

    /**
     * How many notices a person has not read.
     *
     * This is a count and never a load. A student with four thousand
     * notifications must not pull four thousand rows to draw a badge, so the
     * only thing that leaves the database here is a number.
     */
    public static function unreadCountFor(User $user): int
    {
        return self::query()
            ->where('user_id', $user->id)
            ->unread()
            ->count();
    }

    /**
     * A page of a person's notices, newest first.
     *
     * Ordering by id rather than created_at is deliberate. Ids ascend with
     * insertion, so this is stable when two notices share a timestamp, which
     * happens whenever one transaction raises several at once.
     *
     * @return LengthAwarePaginator<int, static>
     */
    public static function pageFor(User $user, int $perPage = 20)
    {
        return self::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
