@extends('layouts.app-shell')

@section('title', 'Notifications')
@section('workspace-context', 'Notifications')

@section('content')
    <x-page-header
        title="Notifications"
        description="Everything this account has been told, newest first."
    >
        <x-slot:actions>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <x-btn type="submit" variant="secondary" size="md">Mark all read</x-btn>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($notifications->isEmpty())
        <x-empty-state
            title="Nothing here yet"
            description="Notices about your courses, your assessments and your certificates will appear here."
        >
            <x-btn :href="route('courses.index')" variant="primary" size="md">Browse the catalog</x-btn>
        </x-empty-state>
    @else
        <ul role="list" class="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
            @foreach ($notifications as $notice)
                <li class="{{ $notice->isRead() ? '' : 'bg-primary-quiet/40' }}">
                    <div class="flex items-start gap-4 p-4 sm:p-5">
                        <span
                            class="mt-2 size-2 shrink-0 rounded-full {{ $notice->isRead() ? 'bg-transparent' : 'bg-primary' }}"
                            aria-hidden="true"
                        ></span>

                        <div class="min-w-0 flex-1">
                            {{-- The kind of notice, above the title, so a long
                                 history can be scanned by kind rather than read. --}}
                            <p class="text-xs font-semibold text-ink-subtle">
                                {{ \App\Support\StatusLabel::label($notice->type) }}
                            </p>

                            <p class="mt-1 text-sm font-semibold text-ink">
                                {{ $notice->title }}

                                @unless ($notice->isRead())
                                    <span class="sr-only">(unread)</span>
                                @endunless
                            </p>

                            @if ($notice->body)
                                <p class="mt-1 text-sm leading-6 text-ink-muted">{{ $notice->body }}</p>
                            @endif

                            <p class="mt-2 text-xs text-ink-subtle">
                                {{ $notice->created_at?->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($notice->link)
                                {{--
    A real control, not a bare word.

    This was a `quiet` button, which draws no border and no background, sitting
    in the same row as a `secondary` button that draws both. Two actions on one
    notification, one of which looks like a link and one of which does not, and
    the one that actually navigates somewhere is the one that looks like text.
    Read down the right hand edge of a long list it reads as though the first
    action is less important than the second, which is the opposite of what it
    does.
--}}
<x-btn :href="$notice->link" variant="secondary" size="sm">Open</x-btn>
                            @endif

                            @unless ($notice->isRead())
                                <form method="POST" action="{{ route('notifications.read', $notice) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-btn type="submit" variant="secondary" size="sm">Mark read</x-btn>
                                </form>
                            @endunless
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
@endsection
