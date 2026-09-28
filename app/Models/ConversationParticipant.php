<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's place in a thread, and how far through it they are.
 *
 * This row is the whole of conversation authorization. Being a participant is
 * what grants access, so there is no per-thread exception to get wrong and no
 * special case for a role in a policy.
 */
class ConversationParticipant extends Model
{
    use HasFactory;

    protected $table = 'conversation_participants';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'last_read_message_id' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
