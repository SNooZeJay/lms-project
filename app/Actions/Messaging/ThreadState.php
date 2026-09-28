<?php

namespace App\Actions\Messaging;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Marking a thread read, archiving it, and closing it.
 *
 * All three are per person except closing, which belongs to the thread. Keeping
 * them here rather than in the controller means the same rule applies however
 * the route is reached, and each one authorizes for itself.
 */
class ThreadState
{
    public function markRead(User $actor, Conversation $conversation): void
    {
        Gate::forUser($actor)->authorize('view', $conversation);

        /*
         | The boundary is the id of the newest message in the thread, so every
         | message that exists at this moment is read and every message that
         | arrives after it is not. Comparing against a timestamp would be less
         | exact, because the column holds whole seconds.
         */
        $conversation->participants()
            ->where('user_id', $actor->id)
            ->update([
                'last_read_at' => now(),
                'last_read_message_id' => $conversation->messages()->max('id'),
            ]);
    }

    public function archive(User $actor, Conversation $conversation): void
    {
        Gate::forUser($actor)->authorize('view', $conversation);

        $conversation->participants()
            ->where('user_id', $actor->id)
            ->update(['archived_at' => now()]);
    }

    public function unarchive(User $actor, Conversation $conversation): void
    {
        Gate::forUser($actor)->authorize('view', $conversation);

        $conversation->participants()
            ->where('user_id', $actor->id)
            ->update(['archived_at' => null]);
    }

    /**
     * Close a support thread.
     *
     * A close, not a delete. The record of what was said is the part that has
     * to survive, which is why archiving and closing both set a timestamp and
     * neither removes a row.
     */
    public function close(User $actor, Conversation $conversation): void
    {
        Gate::forUser($actor)->authorize('close', $conversation);

        $conversation->forceFill(['status' => ConversationStatus::Closed])->save();
    }

    public function reopen(User $actor, Conversation $conversation): void
    {
        Gate::forUser($actor)->authorize('close', $conversation);

        $conversation->forceFill(['status' => ConversationStatus::Open])->save();
    }
}
