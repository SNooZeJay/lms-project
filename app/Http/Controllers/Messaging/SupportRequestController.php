<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\StartConversation;
use App\Actions\Notifications\RecordNotification;
use App\Enums\ConversationKind;
use App\Enums\ConversationStatus;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Support requests, raised by a person and answered by an administrator.
 *
 * This is the second shape of the same three tables. A support thread has no
 * course, one participant when it is raised, and an administrator is added when
 * they first reply. The separation is enforced by the participant table, so an
 * instructor cannot reach one even about a student they teach.
 */
class SupportRequestController extends Controller
{
    /**
     * Raise a request.
     */
    public function create(Request $request): View
    {
        return view('support.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = app(StartConversation::class)->startSupportThread($request->user(), $validated['subject']);

        app(PostMessage::class)->handle(
            $request->user(),
            $conversation,
            $validated['body'],
            $request->input('client_token'),
        );

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('status', 'Request sent. An administrator will reply here.');
    }

    /**
     * Every open and settled request, for an administrator.
     */
    public function index(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAnySupport', Conversation::class);

        $threads = Conversation::query()
            ->where('kind', ConversationKind::Support->value)
            ->with('requester')
            // Which of these this administrator is already in, so the list can
            // offer Reply or Pick up and reply. Eager loaded rather than asked
            // per row in the view, which would be one query per thread.
            ->withExists(['participants as is_participant' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('support.index', [
            'threads' => $threads,
            'open' => Conversation::query()
                ->where('kind', ConversationKind::Support->value)
                ->where('status', ConversationStatus::Open->value)
                ->count(),
        ]);
    }

    /**
     * An administrator joins a request so they can reply to it.
     *
     * The participant row is what grants the access, and it is created here
     * rather than by the policy, because "may answer a support request" and "is
     * in this request" are the same question asked at two different moments.
     */
    public function join(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('viewAnySupport', Conversation::class);

        if ($conversation->kind !== ConversationKind::Support) {
            abort(404);
        }

        if (! $conversation->participants()->where('user_id', $request->user()->id)->exists()) {
            // forceFill rather than create, because the models here keep an empty
            // fillable list so that a request can never write a column. The
            // relationship sets conversation_id, so only the user is written.
            $participant = new ConversationParticipant;
            $participant->forceFill([
                'user_id' => $request->user()->id,
                'last_read_at' => now(),
                'last_read_message_id' => $conversation->messages()->max('id'),
                'archived_at' => null,
            ]);
            $conversation->participants()->save($participant);
        }

        // The person who raised it should know somebody has picked it up. Their
        // unread count is what makes the badge move, so this is the notification
        // that justifies joining at all.
        app(RecordNotification::class)->handle(
            $conversation->requester,
            NotificationType::SupportReply,
            'Your request is with an administrator',
            'Someone will reply to "'.($conversation->subject ?? 'your request').'" shortly.',
            dedupKey: 'support-joined:'.$conversation->id,
            link: route('conversations.show', $conversation),
            authorizeLink: fn (User $who): bool => $who->id === $conversation->requester_id,
            subjectType: 'conversation',
            subjectId: $conversation->id,
        );

        return redirect()->route('conversations.show', $conversation);
    }
}
