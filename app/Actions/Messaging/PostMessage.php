<?php

namespace App\Actions\Messaging;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saying something in a thread.
 *
 * The client token is what makes a double click safe. The composer puts a UUID
 * in a hidden field, and a second request carrying the same token loses the
 * unique index on (conversation_id, author_id, client_token) and is answered with
 * the message the first one stored. A check before the insert would be racy,
 * because two requests can both pass it.
 *
 * The thread is stamped with the time of the last message in the same
 * transaction, so a list ordered by it can never show a thread as quiet when it
 * has just been posted in.
 */
class PostMessage
{
    public function __construct(private readonly RecordNotification $notify) {}

    /**
     * @return array{message: ConversationMessage, duplicate: bool}
     */
    public function handle(User $actor, Conversation $conversation, string $body, ?string $clientToken = null): array
    {
        Gate::forUser($actor)->authorize('reply', $conversation);

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Write something before sending.',
            ]);
        }

        try {
            $message = DB::transaction(function () use ($actor, $conversation, $body, $clientToken): ConversationMessage {
                $message = new ConversationMessage;
                $message->forceFill([
                    'conversation_id' => $conversation->id,
                    'author_id' => $actor->id,
                    'body' => $body,
                    'client_token' => $clientToken,
                ]);
                $message->save();

                $conversation->forceFill(['last_message_at' => now()])->save();

                /*
                 | The author has read their own message, so the thread does not
                 | immediately show as unread to them. Marking read here rather
                 | than on page load means the badge is right the moment they
                 | navigate away.
                 |
                 | The boundary is the new message's id and not a timestamp. A
                 | timestamp column here holds whole seconds, so writing now()
                 | and comparing messages by created_at would miss anything sent
                 | in the same second, which is exactly when somebody is
                 | messaging back and forth.
                 */
                $conversation->participants()
                    ->where('user_id', $actor->id)
                    ->update([
                        'last_read_at' => now(),
                        'last_read_message_id' => $message->id,
                    ]);

                return $message;
            });
        } catch (QueryException $e) {
            if ($clientToken !== null && $this->isDuplicate($e)) {
                $existing = ConversationMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('author_id', $actor->id)
                    ->where('client_token', $clientToken)
                    ->first();

                if ($existing !== null) {
                    return ['message' => $existing, 'duplicate' => true];
                }
            }

            throw $e;
        }

        $this->notifyTheOthers($conversation, $message, $actor);

        return ['message' => $message, 'duplicate' => false];
    }

    /**
     * Tell the other people in the thread, and nobody else.
     *
     * The link is only stored when the recipient may actually follow it, which
     * the seam checks. A notification pointing at a thread somebody cannot open
     * would tell them a conversation exists that they are not allowed to see.
     */
    private function notifyTheOthers(Conversation $conversation, ConversationMessage $message, User $author): void
    {
        $recipientIds = $conversation->participants()
            ->where('user_id', '!=', $author->id)
            ->pluck('user_id');

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        /*
         | The type and the course are decided together, from the kind of thread.
         |
         | They cannot be chosen separately, because the seam checks that a
         | course scoped notice carries a course and a platform one does not, and
         | a support thread has no course to carry. Deriving both from the thread
         | kind means the two can never disagree.
         */
        $isCourseThread = $conversation->isCourseThread() && $conversation->course !== null;
        $course = $isCourseThread ? $conversation->course : null;

        foreach ($recipients as $recipient) {
            $this->notify->handle(
                $recipient,
                $isCourseThread ? NotificationType::CourseMessage : NotificationType::SupportMessage,
                $isCourseThread
                    ? $conversation->course->title
                    : ($conversation->subject ?? 'Support request'),
                $this->preview($message->body),
                course: $course,
                dedupKey: 'message:'.$message->id.':to:'.$recipient->id,
                link: route('conversations.show', $conversation),
                // Checked against the participant table, which is the rule,
                // rather than assumed. The recipient list came from that same
                // table so this is true by construction today, and stating it
                // as a lookup means it stays true if the list is ever built
                // some other way.
                authorizeLink: fn (User $who): bool => $conversation->participants()
                    ->where('user_id', $who->id)
                    ->exists(),
                subjectType: 'conversation',
                subjectId: $conversation->id,
            );
        }
    }

    /**
     * A short, plain preview.
     *
     * The notification list shows a couple of lines, so a full message would be
     * truncated at a length nobody chose. Cut at a sentence and add an ellipsis
     * rather than letting the layout decide.
     */
    private function preview(string $body): string
    {
        $flat = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        if (mb_strlen($flat) <= 140) {
            return $flat;
        }

        return mb_substr($flat, 0, 137).'...';
    }

    private function isDuplicate(QueryException $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'unique')
            && str_contains($e->getMessage(), 'conversation_messages_token_unique');
    }
}
