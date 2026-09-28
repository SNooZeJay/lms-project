@extends('layouts.app-shell')

@section('title', $announcement->title)
@section('workspace-context', 'Announcements')

@section('content')
    <x-page-header :title="$announcement->title">
        <x-slot:description>
            {{ $announcement->isCourseScoped() ? $announcement->course?->title : 'Published to everybody' }}
            · by {{ $announcement->author?->name ?? 'Former account' }}
            @if ($announcement->published_at)
                · <time datetime="{{ $announcement->published_at->toIso8601String() }}">{{ $announcement->published_at->format('j M Y, H:i') }}</time>
            @endif
        </x-slot:description>

        <x-slot:actions>
            {{-- Only the author. The Policy refuses anybody else, and this is the
                 convenience rather than the control: the server is the authority
                 and a hidden link is not a guard. --}}
            @can('delete', $announcement)
                <form method="POST" action="{{ route('announcements.destroy', $announcement) }}">
                    @csrf
                    @method('DELETE')
                    <x-btn type="submit" variant="quiet" size="md">Withdraw</x-btn>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- The body is stored as written and escaped here, never the other way
         round, because the same text also appears in a notification. --}}
    <article class="mt-6 max-w-3xl">
        <div class="card-body whitespace-pre-wrap text-sm leading-6 text-ink">
            {{ $announcement->body }}
        </div>
    </article>

    <p class="mt-6">
        <a href="{{ route('announcements.index') }}" class="row-target text-sm font-medium text-primary-text">
            Back to announcements
        </a>
    </p>
@endsection
