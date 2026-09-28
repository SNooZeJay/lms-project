@extends('layouts.app-shell')

@section('title', 'Messages')
@section('workspace-context', 'Messages')

@section('content')
    @php
        // The total across every page, not just this one, and free: the
        // controller already grouped the unread counts by thread for the list,
        // so the sum is arithmetic on a collection rather than a second query.
        $totalUnread = (int) $unreadByThread->sum();
    @endphp

    <x-page-header
        title="Messages"
        :description="$totalUnread > 0
            ? $totalUnread.' unread '.Illuminate\Support\Str::plural('message', $totalUnread).' across your conversations.'
            : 'Every conversation you are part of, newest activity first. Nothing is unread.'"
    >
        <x-slot:actions>
            <x-btn
                :href="route('support.create')"
                variant="secondary"
                size="md"
            >
                <x-icon name="shield" size="sm" />
                Ask for help
            </x-btn>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
    @endif

    @if ($conversations->isEmpty())
        <x-empty-state
            class="mt-6"
            title="No conversations yet"
            description="Open a course from your courses page and start a thread with the instructor, or ask for help here."
            icon="mail"
        >
            <x-btn :href="route('courses.index')" variant="secondary" size="md">Browse the catalog</x-btn>
        </x-empty-state>
    @else
        <ul role="list" class="mt-6 divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
            @foreach ($conversations as $conversation)
                @php
                    // The counts come from the controller as one aggregate, not
                    // from a call per row. The first version asked the model per
                    // thread, which is two queries per row and made this page
                    // cost more the more threads there were.
                    $unread = (int) $unreadByThread->get($conversation->id, 0);
                    $title = $conversation->isCourseThread() && $conversation->course
                        ? $conversation->course->title
                        : ($conversation->subject ?? 'Support request');
                    // The last message, eager loaded on the controller, so this is
                    // the same row the topbar panel reads rather than a second
                    // read of the same fact for this page only. A Blade comment
                    // here would be a parse error: this is a @php block, not
                    // markup.
                    $preview = $conversation->lastMessage;
                @endphp

                <li>
                    <a
                        href="{{ route('conversations.show', $conversation) }}"
                        class="flex min-h-11 items-center gap-4 px-4 py-4 transition-colors hover:bg-surface-muted focus-visible:bg-surface-muted focus-visible:outline-none sm:px-5"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $conversation->isCourseThread() ? 'bg-primary-quiet text-primary-text' : 'bg-accent-quiet text-accent-text' }} text-xs font-bold"
                            aria-hidden="true"
                        >{{ mb_substr(mb_strtoupper(mb_substr($title, 0, 1)), 0, 1) }}</span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline gap-x-2">
                                <span class="truncate text-sm font-semibold text-ink">{{ $title }}</span>

                                {{-- info, not accent: the design system has no
                                     accent badge, and a class that does not
                                     exist renders as an unstyled span rather
                                     than as an error. --}}
                                <x-badge :tone="$conversation->isCourseThread() ? 'primary' : 'info'" class="shrink-0">
                                    {{ $conversation->isCourseThread() ? 'Course' : 'Support' }}
                                </x-badge>

                                @unless ($conversation->isOpen())
                                    <x-badge tone="neutral" class="shrink-0">Closed</x-badge>
                                @endunless
                            </span>

                            {{-- The opening words of the last message. A thread
                                 list without a preview tells you a conversation
                                 exists and nothing about what is in it, which
                                 means opening every thread to find the one you
                                 wanted. Cut here rather than left to the layout,
                                 and rendered as plain text because a message body
                                 is never markup. --}}
                            @if ($preview)
                                <span class="mt-1 block truncate text-sm {{ $unread > 0 ? 'font-medium text-ink' : 'text-ink-muted' }}">
                                    {{ \Illuminate\Support\Str::limit($preview->body, 90) }}
                                </span>
                            @endif

                            <span class="mt-1 block text-xs text-ink-muted">
                                {{ $conversation->message_count }}
                                {{ \Illuminate\Support\Str::plural('message', $conversation->message_count) }},
                                {{ $conversation->participant_count }}
                                {{ \Illuminate\Support\Str::plural('person', $conversation->participant_count) }}
                                @if ($conversation->last_message_at)
                                    · last activity {{ $conversation->last_message_at->diffForHumans() }}
                                @endif
                            </span>
                        </span>

                        @if ($unread > 0)
                            <span
                                class="flex min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold leading-5 text-white"
                            >{{ $unread }}</span>
                            <span class="sr-only">{{ $unread }} unread</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $conversations->links() }}
        </div>
    @endif
@endsection
