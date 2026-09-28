@extends('layouts.app-shell')

@section('title', $conversation->isCourseThread() && $conversation->course ? $conversation->course->title : ($conversation->subject ?? 'Support request'))
@section('workspace-context', 'Messages')

@section('content')
    <x-page-header
        :title="$conversation->isCourseThread() && $conversation->course ? $conversation->course->title : ($conversation->subject ?? 'Support request')"
        :description="$conversation->isCourseThread() ? 'A conversation about this course.' : 'A request for help with the platform.'"
    >
        <x-slot:actions>
            @if ($canClose)
                <form method="POST" action="{{ route('conversations.close', $conversation) }}">
                    @csrf
                    @method('PATCH')
                    <x-btn type="submit" variant="secondary" size="md">
                        {{ $conversation->isOpen() ? 'Close thread' : 'Reopen thread' }}
                    </x-btn>
                </form>
            @endif

            <form method="POST" action="{{ route('conversations.archive', $conversation) }}">
                @csrf
                @method('PATCH')
                <x-btn type="submit" variant="quiet" size="md">Archive</x-btn>
            </form>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
    @endif

    @unless ($conversation->isOpen())
        <x-note tone="neutral" class="mt-6">
            This thread is closed, so it is read only. Reopen it to reply.
        </x-note>
    @endunless

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
        <div class="min-w-0">
            {{-- The transcript. One card, messages in order, each labelled with
                 its author and its time so the thread can be read without
                 scrolling back up for who said what. --}}
            <ol role="list" class="grid gap-4">
                @forelse ($messages as $message)
                    @php
                        $mine = $message->author_id === auth()->id();
                    @endphp

                    <li class="flex gap-3 {{ $mine ? 'flex-row-reverse' : '' }}">
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $mine ? 'bg-primary text-white' : 'bg-surface-muted text-ink' }} text-xs font-bold"
                            aria-hidden="true"
                        >{{ mb_substr(mb_strtoupper(mb_substr($message->author?->name ?? '?', 0, 1)), 0, 1) }}</span>

                        {{-- Shrinks to the text rather than filling the column.
                             flex-1 was here first and made every bubble the same
                             width, so a two word reply sat in a box as wide as a
                             paragraph. The cap is what stops a long message
                             running off the side. --}}
                        <div class="min-w-0 max-w-[42rem]">
                            <p class="flex flex-wrap items-baseline gap-x-2 text-xs text-ink-subtle {{ $mine ? 'justify-end' : '' }}">
                                <span class="font-semibold text-ink-muted">{{ $mine ? 'You' : ($message->author?->name ?? 'Former account') }}</span>
                                <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->diffForHumans() }}</time>
                            </p>

                            {{-- The body is stored as written and escaped here. Never
                                 the other way round, because the same text is also
                                 read in a notification and an export. --}}
                            <div class="mt-1 rounded-lg border border-line {{ $mine ? 'bg-primary-quiet' : 'bg-surface' }} px-4 py-3">
                                <p class="text-sm leading-6 whitespace-pre-wrap text-ink">{{ $message->body }}</p>
                            </div>
                        </div>
                    </li>
                @empty
                    <li>
                        <x-empty-state
                            title="Nothing said yet"
                            description="This thread is open. Send the first message below."
                            icon="mail"
                            compact
                        />
                    </li>
                @endforelse
            </ol>

            @if ($messages->hasPages())
                <div class="mt-6">
                    {{ $messages->links() }}
                </div>
            @endif

            @if ($conversation->isOpen())
                <section class="mt-8" aria-labelledby="composer-heading">
                    <h2 id="composer-heading" class="text-base font-semibold text-ink">Reply</h2>

                    <form
                        method="POST"
                        action="{{ route('conversations.messages.store', $conversation) }}"
                        class="mt-3"
                    >
                        @csrf

                        <x-form-errors :errors="$errors" class="mb-4" />

                        {{-- The token is generated here and sent with the form. A
                             double click, a refresh, or a browser retry sends the
                             same one, and the unique index on
                             (conversation_id, author_id, client_token) drops the
                             second insert. A check before the insert would be
                             racy, because two requests can both pass it. --}}
                        <input
                            type="hidden"
                            name="client_token"
                            value="{{ (string) Str::uuid() }}"
                            data-client-token
                        >

                        {{-- x-form-field renders its own control from its type and
                             takes no slot, so a textarea passed as a child is
                             discarded and a plain text input is rendered in its
                             place. The first version of this form did that and
                             shipped a composer nobody could type a paragraph
                             into. --}}
                        <x-form-field
                            name="body"
                            label="Your message"
                            type="textarea"
                            :rows="4"
                            :maxlength="5000"
                            required
                            hint="Up to 5000 characters."
                        />

                        <div class="mt-4">
                            <x-btn type="submit" variant="primary" size="md">
                                <x-icon name="mail" size="sm" />
                                Send message
                            </x-btn>
                        </div>
                    </form>
                </section>
            @endif
        </div>

        <aside class="card w-full shrink-0 lg:w-64" aria-labelledby="people-heading">
            <div class="card-header">
                <h2 id="people-heading" class="text-base font-semibold text-ink">In this thread</h2>
            </div>

            <ul role="list" class="card-body grid gap-3">
                @foreach ($others as $person)
                    <li class="flex items-center gap-3">
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-bold text-ink"
                            aria-hidden="true"
                        >{{ mb_substr(mb_strtoupper(mb_substr($person->name, 0, 1)), 0, 1) }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-ink">{{ $person->name }}</span>
                            <span class="block text-xs text-ink-subtle">{{ \App\Support\StatusLabel::words($person->profile?->role?->value) }}</span>
                        </span>
                    </li>
                @endforeach

                @if ($others->isEmpty())
                    <li class="text-sm text-ink-muted">
                        Nobody else is in this thread yet. An administrator joins when they reply.
                    </li>
                @endif
            </ul>
        </aside>
    </div>
@endsection
