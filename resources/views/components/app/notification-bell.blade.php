@props([
    'unread' => 0,
    'recent' => [],
    'id' => 'topbar-notifications',
])

{{--
    The notification bell.

    A real count from the notifications table, not a number typed into a mock. If
    there is nothing unread the control is still there and still opens the centre,
    because a bell that appears and disappears with the count is a control that
    moves under the pointer, and a person aiming at it misses.

    The panel is the existing dropdown primitive, so Escape, the click outside,
    focus return and the focus ring all behave the way they already do in the
    account menu. No new JavaScript.
--}}
<div class="relative" data-dropdown>
    <button
        type="button"
        data-dropdown-trigger
        aria-expanded="false"
        aria-controls="{{ $id }}"
        {{ $attributes->merge(['class' => 'btn btn-secondary btn-sm relative px-2.5']) }}
    >
        <x-icon name="bell" size="sm" />

        @if ($unread > 0)
            {{-- The count is the unread total, capped at a round number so a
                 backlog of four thousand does not print four thousand. The real
                 figure is in the accessible name, so nothing is lost.

                 The fill is the error token and the text is the canvas, which
                 inverts with the theme. A fixed red with white text would pass
                 on a light background and fail on the dark one, where the token
                 is a pale red. --}}
            <span
                aria-hidden="true"
                class="absolute -top-1 -right-1 flex min-w-4 items-center justify-center rounded-full bg-error-text px-1 text-[10px] font-bold leading-4 text-canvas"
            >{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif

        <span class="sr-only">
            @if ($unread > 0)
                Notifications, {{ $unread }} unread
            @else
                Notifications, nothing unread
            @endif
        </span>
    </button>

    <div
        id="{{ $id }}"
        data-dropdown-panel
        class="card-raised absolute right-0 z-50 mt-2 w-80 overflow-hidden focus:outline-none"
        hidden
    >
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <p class="text-sm font-semibold text-ink">Notifications</p>

            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-quiet btn-sm px-2 text-xs">Mark all read</button>
                </form>
            @endif
        </div>

        {{-- The list of the most recent notices. The full history, and its
             pagination, live on the centre page this panel links to. --}}
        <div class="max-h-80 overflow-y-auto">
            @forelse ($recent as $notice)
                <a
                    href="{{ $notice->link ?? route('notifications.index') }}"
                    data-dropdown-item
                    class="flex gap-3 border-b border-line px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-surface-muted focus-visible:bg-surface-muted focus-visible:outline-none"
                >
                    <span
                        class="mt-1.5 size-2 shrink-0 rounded-full {{ $notice->isRead() ? 'bg-transparent' : 'bg-primary' }}"
                        aria-hidden="true"
                    ></span>

                    <span class="min-w-0">
                        {{--
                            The kind of notice, on its own line above the title.

                            The reference this was checked against carries a
                            category line on every notification row, and the idea
                            is taken. Their category is a subject code, which this
                            application has no field for; the stored `type` is the
                            same idea and has been written all along without ever
                            being read.

                            Its own line rather than sharing one with the title.
                            Side by side in a panel this narrow they compete, and
                            the title loses: "Scheduled maintenance on Sunday"
                            truncated to "Scheduled maint…" is worse than no title
                            at all, because it is a title nobody can read. The
                            category is short enough to survive on its own.

                            Colour is never the only signal. The words are here in
                            full, and they are the same words the notification
                            centre uses.
                        --}}
                        <span class="block text-xs font-semibold text-ink-subtle">
                            {{ \App\Support\StatusLabel::label($notice->type) }}
                        </span>

                        <span class="mt-0.5 block truncate text-sm font-semibold text-ink">{{ $notice->title }}</span>

                        @if ($notice->body)
                            <span class="mt-0.5 line-clamp-2 block text-xs text-ink-muted">{{ $notice->body }}</span>
                        @endif

                        <span class="mt-1 block text-xs text-ink-subtle">{{ $notice->created_at?->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-muted">
                    Nothing yet. Notices about your courses appear here.
                </p>
            @endforelse
        </div>

        <div class="border-t border-line p-2">
            <a
                href="{{ route('notifications.index') }}"
                data-dropdown-item
                class="btn btn-secondary btn-sm w-full justify-center"
            >
                View all notifications
            </a>
        </div>
    </div>
</div>
