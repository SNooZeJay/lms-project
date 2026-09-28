<?php

namespace App\Models;

use App\Enums\ConversationKind;
use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * One thread. A conversation between a student and an instructor about a
 * course, or a person and an administrator about the system.
 *
 * Both are the same row with a different `kind`, and the difference is applied
 * by ConversationPolicy rather than by having two sets of tables.
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'kind' => ConversationKind::class,
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'archived_at'])
            ->withTimestamps();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isCourseThread(): bool
    {
        return $this->kind === ConversationKind::Course;
    }

    /**
     * The last thing anybody said in this thread.
     *
     * A real relation rather than a method that runs its own query, so a list of
     * threads can load every thread's last message in one query.
     *
     * It was `messages()->orderByDesc('id')->first()`, which reads like a
     * property and is not one. Reading it in a loop cost a query per thread, and
     * `ConversationController` carried a comment explaining why it had to fetch
     * message counts separately to avoid it. That was working around a shape the
     * model did not have to be in the first place.
     *
     * Reading `$thread->lastMessage` still returns the message, because a HasOne
     * is an object-or-accessible relation: the property resolves to the model.
     * Nothing that read it before has to change.
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany('id');
    }

    /**
     * How many messages this person has not read in this thread.
     *
     * Counted from the messages rather than stored, so it cannot drift away from
     * the thing it counts. The boundary is the id of the last message they read
     * and not a timestamp, because a timestamp column in this database holds
     * whole seconds and a message posted in the same second as the read would be
     * missed. A null boundary means everything in the thread is unread, which is
     * what somebody who has just been added to it expects.
     */
    public function unreadCountFor(User $user): int
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();

        if ($participant === null) {
            return 0;
        }

        $query = $this->messages()->where('author_id', '!=', $user->id);

        if ($participant->last_read_message_id !== null) {
            $query->where('id', '>', $participant->last_read_message_id);
        }

        return $query->count();
    }

    /**
     * How many messages this person has not read, in every thread they are in.
     *
     * Keyed by conversation id, so one query answers for a whole list of threads.
     * The single thread version is written in terms of the same boundary rule:
     * the id of the last message read rather than a timestamp, because a
     * timestamp column in this database holds whole seconds and a message posted
     * in the same second as the read would be missed. A null boundary means
     * everything in that thread is unread, which is what somebody who has just
     * been added to it expects.
     *
     * This exists so a list of threads can say which of them are unread without
     * a query each. `unreadCountFor` costs two, so calling it in a loop over the
     * five threads in the topbar was ten queries on every page of the
     * application.
     *
     * Archived threads are left out, because the topbar badge and the panel both
     * count what is still live. Summing the returned map gives the same total
     * the badge already shows, so the panel and the badge cannot disagree.
     *
     * Pass conversation ids to narrow it to a subset, which is what a paginated
     * list does. The two callers were separate queries expressing the same rule,
     * and two expressions of one rule is how a badge and the page it sits above
     * come to disagree about how many messages are unread.
     *
     * @param  list<int>|null  $conversationIds
     * @return Collection<int, int>
     */
    public static function unreadCountsFor(User $user, ?array $conversationIds = null): Collection
    {
        return ConversationMessage::query()
            ->join(
                'conversation_participants',
                'conversation_participants.conversation_id',
                '=',
                'conversation_messages.conversation_id'
            )
            ->where('conversation_participants.user_id', $user->id)
            ->whereNull('conversation_participants.archived_at')
            ->where('conversation_messages.author_id', '!=', $user->id)
            ->when(
                $conversationIds !== null,
                fn ($query) => $query->whereIn('conversation_participants.conversation_id', $conversationIds)
            )
            ->where(function ($query): void {
                $query->whereNull('conversation_participants.last_read_message_id')
                    ->orWhereColumn('conversation_messages.id', '>', 'conversation_participants.last_read_message_id');
            })
            ->groupBy('conversation_messages.conversation_id')
            ->selectRaw('conversation_messages.conversation_id as conversation_id, count(*) as aggregate')
            ->pluck('aggregate', 'conversation_id')
            ->map(fn ($count): int => (int) $count);
    }

    /**
     * The threads one person is in, newest activity first.
     *
     * Archived threads are left out by default. They are settled, and a list of
     * settled threads is a list nobody reads.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForParticipant(Builder $query, User $user, bool $includeArchived = false): void
    {
        $query->whereHas(
            'participants',
            fn (Builder $participants) => $participants->where('user_id', $user->id)
        );

        if (! $includeArchived) {
            $query->whereHas(
                'participants',
                fn (Builder $participants) => $participants
                    ->where('user_id', $user->id)
                    ->whereNull('archived_at')
            );
        }
    }
}
