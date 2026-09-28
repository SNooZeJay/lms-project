@props([
    'unread' => 0,
    'threads' => [],
    'unreadThreads' => [],
    'id' => 'topbar-messages',
])

{{--
    The messages control.

    Present at every width because there is real storage behind it. It was left
    out on purpose before, when the conversation tables did not exist and a
    message button would have gone nowhere; the project's own IconGeometryTest
    refused the unused drawing, which was the right answer at the time.

    The count is the unread total across every thread the reader is in, from one
    aggregate. The panel is the existing dropdown primitive, so Escape, the click
    outside, focus return and the focus ring behave the way they already do
    everywhere else. No new JavaScript.

    A button and not a link. This was a link to /messages, and the measurement
    probe found that clicking it never showed the panel: the click both opened
    the panel and navigated away, and the page it landed on had nothing to show
    because the navigation won. A control cannot be a link to a page and a
    disclosure for a panel at the same time, and the bell next to it is a button
    for the same reason. The panel carries a View all link, so the list is still
    one click away.
--}}
<div class="relative" data-dropdown>
    <button
        type="button"
        data-dropdown-trigger
        aria-expanded="false"
        aria-controls="{{ $id }}"
        {{ $attributes->merge(['class' => 'btn btn-secondary btn-sm relative px-2.5']) }}
    >
        <x-icon name="message-square" size="sm" />

        @if ($unread > 0)
            {{-- The accent token as the fill and the canvas as the text, which is
                 how the bell uses the error token. The accent is teal in the light
                 theme and a pale teal in the dark one, so a fixed colour with white
                 text would pass on a light background and fail on the dark. It is
                 also what tells the two counts apart at a glance, which is the
                 point of having both controls. --}}
            <span
                aria-hidden="true"
                class="absolute -top-1 -right-1 flex min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold leading-4 text-canvas"
            >{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif

        <span class="sr-only">
            @if ($unread > 0)
                Messages, {{ $unread }} unread
            @else
                Messages, nothing unread
            @endif
        </span>
    </button>

    <div
        id="{{ $id }}"
        data-dropdown-panel
        class="card-raised absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-1rem)] overflow-hidden focus:outline-none"
        hidden
    >
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <p class="text-sm font-semibold text-ink">Messages</p>

            <a
                href="{{ route('conversations.index') }}"
                data-dropdown-item
                class="btn btn-quiet btn-sm px-2 text-xs"
            >View all</a>
        </div>

        {{--
            Wider than the notification panel, because this row carries more.

            A course title and a time fit comfortably in eighty. A preview of what
            was actually said does not, and truncating the only sentence that says
            what the thread is about would make the panel no more use than the one
            line it replaced.
        --}}
        <div class="max-h-96 overflow-y-auto">
            @forelse ($threads as $thread)
                @php
                    $title = $thread->isCourseThread() && $thread->course
                        ? $thread->course->title
                        : ($thread->subject ?? 'Support request');
                    $last = $thread->lastMessage;
                    $unreadHere = (int) ($unreadThreads[$thread->id] ?? 0);
                @endphp

                <a
                    href="{{ route('conversations.show', $thread) }}"
                    data-dropdown-item
                    class="flex items-start gap-3 border-b border-line px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-surface-muted focus-visible:bg-surface-muted focus-visible:outline-none"
                >
                    <span
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full {{ $thread->isCourseThread() ? 'bg-primary-quiet text-primary-text' : 'bg-accent-quiet text-accent-text' }} text-xs font-bold"
                        aria-hidden="true"
                    >{{ mb_substr(mb_strtoupper(mb_substr($title, 0, 1)), 0, 1) }}</span>

                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline gap-2">
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-ink">{{ $title }}</span>

                            <span class="shrink-0 text-xs text-ink-subtle">
                                {{ $thread->last_message_at?->diffForHumans() ?? 'No messages' }}
                            </span>
                        </span>

                        @if ($last)
                            {{--
                                What was said. The author is not named here on
                                purpose: this is a five row shortcut whose job is to
                                answer "which thread do I open", and the preview
                                answers that. Naming the writer costs a query on
                                every page of the application, and the message list
                                already shows it.
                            --}}
                            <span class="mt-0.5 block truncate text-xs text-ink-muted">{{ $last->body }}</span>
                        @endif

                        @if ($unreadHere > 0)
                            {{--
                                The count for this thread, not the total. The total is
                                on the button, and a total does not say which of five
                                conversations is the one waiting.
                            --}}
                            <span class="mt-1.5 inline-flex items-center gap-1.5">
                                <span
                                    class="inline-flex min-w-5 items-center justify-center rounded-full bg-accent px-1.5 text-[10px] font-bold leading-5 text-canvas"
                                >{{ $unreadHere > 99 ? '99+' : $unreadHere }}</span>
                                <span class="text-xs text-ink-muted">
                                    {{ $unreadHere === 1 ? 'unread message' : 'unread messages' }}
                                </span>
                            </span>
                        @endif
                    </span>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-muted">
                    No conversations yet. Open a course to message its instructor.
                </p>
            @endforelse
        </div>
    </div>
</div>
