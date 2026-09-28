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

    {{--
        Writing an announcement.

        This page could already read announcements and, for their author, withdraw
        one, and the controller has had a platform publish method with a validated
        request behind it since the feature was approved. What was missing was any
        way to reach it: no form on any page posted to the route, so an
        administrator could read what had been said and had no way to say anything.

        An Instructor writes to one course from that course's own page, which is
        where they already are when something needs saying. An administrator writes
        to everybody, so the form belongs here, on the page that lists what has been
        said to everybody.

        The gate asked is the policy's own createPlatform, not a role test in the
        view. The rule about who may announce to everybody lives in the policy and
        this view only follows it, so the two cannot disagree.
    --}}
    @can('createPlatform', \App\Models\Announcement::class)
        <section class="card mt-6 p-5" aria-labelledby="compose-announcement-heading">
            <h2 id="compose-announcement-heading" class="text-base font-semibold text-ink">
                Tell everybody something
            </h2>
            <p class="mt-1 text-sm leading-6 text-ink-muted">
                Goes to every account, and every account gets a notification.
            </p>

            <x-announcement-composer
                :action="route('admin.announcements.store')"
                scope="platform"
                heading="Tell everybody something"
                description="Goes to every account, and every account gets a notification."
                audience="Everyone with an account will see this. It cannot be unsent, so read it back once before publishing."
                submit-label="Publish to everybody"
            />
        </section>
    @endcan

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
