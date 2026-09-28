@extends('layouts.app-shell')

@section('title', 'Support requests')
@section('workspace-context', 'Support')

@section('content')
    <x-page-header
        title="Support requests"
        description="Everything raised through the help form. Only administrators can see this list."
    />

    @if ($threads->isEmpty())
        <x-empty-state
            class="mt-6"
            title="No requests"
            description="Nobody has asked for help yet."
            icon="shield"
        />
    @else
        <ul role="list" class="mt-6 divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
            @foreach ($threads as $thread)
                <li class="flex flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-5">
                    <div class="min-w-0">
                        <a
                            href="{{ route('conversations.show', $thread) }}"
                            class="text-sm font-semibold text-ink hover:text-primary-text"
                        >{{ $thread->subject ?? 'Support request' }}</a>

                        <p class="mt-1 text-xs text-ink-muted">
                            {{ $thread->requester?->name ?? 'Former account' }}
                            · {{ $thread->message_count }}
                            {{ \Illuminate\Support\Str::plural('message', $thread->message_count) }}
                            @if ($thread->last_message_at)
                                · {{ $thread->last_message_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if (! $thread->isOpen())
                            <x-badge tone="neutral">Closed</x-badge>
                        @else
                            <x-badge tone="warning">Open</x-badge>

                            {{-- An administrator joins a request before they can
                                 read it, because the participant row is the
                                 grant. The button says which of the two things
                                 is about to happen rather than sending them to
                                 a page that will refuse them. --}}
                            @if ($thread->is_participant)
                                <x-btn :href="route('conversations.show', $thread)" variant="secondary" size="sm">
                                    Reply
                                </x-btn>
                            @else
                                <form method="POST" action="{{ route('admin.support.join', $thread) }}">
                                    @csrf
                                    <x-btn type="submit" variant="secondary" size="sm">
                                        Pick up and reply
                                    </x-btn>
                                </form>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $threads->links() }}
        </div>
    @endif
@endsection
