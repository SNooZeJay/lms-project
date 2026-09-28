<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\StartConversation;
use App\Actions\Messaging\ThreadState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\PostMessageRequest;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Threads: the list, one thread, and saying something in it.
 *
 * Every route here is scoped to the signed in person by the Policy, so a
 * hand-typed thread id resolves to a 403 rather than to somebody else's
 * conversation. Nothing in this class decides who may see what; that is one
 * question answered in one place.
 */
class ConversationController extends Controller
{
    /**
     * The threads this person is in, newest activity first.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forParticipant($user)
            // The last message and who wrote it, eager loaded, so every row on the
            // page can show a preview for two queries rather than two per row.
            //
            // This was `withMax('messages', 'id')` plus a second read keyed on that
            // id, driven by a private `previewsFor` helper. That pair existed only
            // because `lastMessage` was not a relation and so could not be eager
            // loaded. It is a HasOne now, and the workaround is what the topbar
            // panel and this page were each paying for separately.
            ->with(['course', 'requester', 'lastMessage.author:id,name'])
            // Aliased, because the defaults are messages_count and
            // participants_count and the row reads "3 messages, 2 people", not
            // two columns called after the relation. The first version of this
            // dropped the aliases and the view kept asking for the old names, so
            // both counts arrived as null and the row said "messages, people"
            // with no figures in front of them.
            ->withCount([
                'messages as message_count',
                'participants as participant_count',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('messages.index', [
            'conversations' => $conversations,
            'unreadByThread' => Conversation::unreadCountsFor(
                $user,
                $conversations->pluck('id')->filter()->values()->all()
            ),
        ]);
    }

    /**
     * One thread, marked read on the way in.
     */
    public function show(Request $request, Conversation $conversation): View
    {
        Gate::forUser($request->user())->authorize('view', $conversation);

        app(ThreadState::class)->markRead($request->user(), $conversation);

        $messages = $conversation->messages()
            ->with('author')
            ->orderBy('id')
            ->paginate(50);

        return view('messages.show', [
            'conversation' => $conversation->fresh(['course', 'requester']),
            'messages' => $messages,
            'others' => $conversation->participants()->with('user')->get()
                ->pluck('user')
                ->reject(fn (User $member): bool => $member->id === $request->user()->id)
                ->values(),
            'canClose' => Gate::forUser($request->user())->allows('close', $conversation),
        ]);
    }

    /**
     * Say something.
     *
     * Redirects rather than returning JSON, because the composer is a plain
     * form and a page reload is the simplest honest outcome. The token makes the
     * reload safe.
     */
    public function store(PostMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $result = app(PostMessage::class)->handle(
            $request->user(),
            $conversation,
            (string) $request->validated('body'),
            $request->validated('client_token'),
        );

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('status', $result['duplicate']
                ? 'That message was already sent.'
                : 'Message sent.');
    }

    /**
     * Open a thread with the instructor of a course, or hand back the one that
     * already exists.
     */
    public function storeCourseThread(Request $request, Course $course): RedirectResponse
    {
        $counterpart = User::query()
            ->whereKey($course->instructor_id)
            ->firstOrFail();

        $conversation = app(StartConversation::class)->startCourseThread($request->user(), $counterpart);

        return redirect()->route('conversations.show', $conversation);
    }

    public function close(Request $request, Conversation $conversation): RedirectResponse
    {
        app(ThreadState::class)->close($request->user(), $conversation);

        return back()->with('status', 'Thread closed.');
    }

    public function reopen(Request $request, Conversation $conversation): RedirectResponse
    {
        app(ThreadState::class)->reopen($request->user(), $conversation);

        return back()->with('status', 'Thread reopened.');
    }

    public function archive(Request $request, Conversation $conversation): RedirectResponse
    {
        app(ThreadState::class)->archive($request->user(), $conversation);

        return redirect()->route('conversations.index')->with('status', 'Thread archived.');
    }
}
