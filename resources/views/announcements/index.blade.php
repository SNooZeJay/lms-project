@extends('layouts.app-shell')

@section('title', 'Announcements')
@section('workspace-context', 'Announcements')

@section('content')
    <x-page-header
        title="Announcements"
        description="Everything said to you, and everything said to the courses you are in."
    />

    @if (session('status'))
        <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
    @endif

    @if ($announcements->isEmpty())
        {{-- R-27: the empty state is required for every list, and this one says
             what to do next rather than only that there is nothing. --}}
        <x-empty-state
            class="mt-6"
            title="No announcements yet"
            description="When an instructor publishes something to one of your courses, or an administrator publishes something to everybody, it appears here and in your notifications."
            icon="megaphone"
        />
    @else
        {{-- A flat list with hairline separators, matching docs/design.md. A card
             grid of announcements reads as a blog index rather than as something
             said to a person. --}}
        <ul role="list" class="mt-6 divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
            @foreach ($announcements as $announcement)
                @php
                    // Unread is a weight and a rule, not a colour alone, so it does
                    // not rely on being able to tell two shades apart.
                    $unread = ! isset($readAnnouncementIds[$announcement->id]);
                @endphp

                <li>
                    <a
                        href="{{ route('announcements.show', $announcement) }}"
                        class="flex items-start gap-4 px-4 py-4 transition-colors hover:bg-surface-muted focus-visible:bg-surface-muted focus-visible:outline-none sm:px-5"
                    >
                        <span
                            class="mt-1 size-2 shrink-0 rounded-full {{ $unread ? 'bg-primary' : 'bg-transparent' }}"
                            aria-hidden="true"
                        ></span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-x-2">
                                <span class="text-sm {{ $unread ? 'font-semibold text-ink' : 'font-medium text-ink-muted' }}">
                                    {{ $announcement->title }}
                                </span>

                                <x-badge :tone="$announcement->isCourseScoped() ? 'primary' : 'info'" class="shrink-0">
                                    {{ $announcement->isCourseScoped() ? $announcement->course?->title : 'Everyone' }}
                                </x-badge>
                            </span>

                            <span class="mt-1 block text-sm text-ink-muted">
                                {{ \Illuminate\Support\Str::limit($announcement->body, 150) }}
                            </span>

                            <span class="mt-1 block text-xs text-ink-subtle">
                                {{ $announcement->author?->name ?? 'Former account' }}
                                @if ($announcement->published_at)
                                    · <time datetime="{{ $announcement->published_at->toIso8601String() }}">{{ $announcement->published_at->diffForHumans() }}</time>
                                @endif
                            </span>
                        </span>

                        @if ($unread)
                            <span class="sr-only">Unread</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $announcements->links() }}
        </div>
    @endif
@endsection
