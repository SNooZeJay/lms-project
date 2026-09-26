@props(['user', 'id' => 'account-menu'])

@php
    $role = $user->profile?->role;
    $status = $user->profile?->account_status;
    $initials = collect(preg_split('/\s+/', trim($user->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : mb_strtoupper(mb_substr($user->name, 0, 1));
@endphp

{{--
    The account menu.

    A real button and a real popup, so the control works with a keyboard and
    reports its state. Escape closes it, focus returns to the button, and a
    click outside closes it. The script that does this lives in
    `resources/js/app.js` and reads only the data attributes below.
--}}
<div class="relative" data-dropdown>
    <button
        type="button"
        data-dropdown-trigger
        aria-expanded="false"
        aria-controls="{{ $id }}"
        {{ $attributes->merge(['class' => 'flex min-h-11 items-center gap-2 rounded-md border border-line bg-surface py-1.5 pr-2 pl-1.5 text-left transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus']) }}
    >
        <span
            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white"
            aria-hidden="true"
        >{{ $initials }}</span>

        <span class="hidden min-w-0 leading-tight sm:block">
            <span class="block max-w-32 truncate text-sm font-semibold text-ink">{{ $user->name }}</span>
            <span class="block text-xs text-ink-muted">{{ \App\Support\StatusLabel::words($role?->value) }}</span>
        </span>

        <x-icon name="chevron-down" size="sm" class="hidden text-ink-muted sm:block" />
        <span class="sr-only">Open account menu</span>
    </button>

    <div
        id="{{ $id }}"
        data-dropdown-panel
        hidden
        class="card-raised absolute right-0 z-50 mt-2 w-72 overflow-hidden"
    >
        <div class="border-b border-line px-4 py-4">
            <p class="truncate text-sm font-semibold text-ink">{{ $user->name }}</p>
            <p class="mt-1 truncate text-sm text-ink-muted">{{ $user->email }}</p>

            <div class="mt-3 flex flex-wrap gap-2">
                <x-status :value="$role?->value" :label="\App\Support\StatusLabel::words($role?->value)" tone="primary" />
                <x-status :value="$status?->value" />
            </div>
        </div>

        <ul role="list" class="p-2">
            <li>
                <a href="{{ route('account.profile') }}" data-dropdown-item class="flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                    <x-icon name="user" size="sm" class="text-ink-muted" />
                    Your profile
                </a>
            </li>
            <li>
                <a href="{{ route('account.password') }}" data-dropdown-item class="flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                    <x-icon name="lock" size="sm" class="text-ink-muted" />
                    Password
                </a>
            </li>
            <li>
                <a href="{{ route('courses.index') }}" data-dropdown-item class="flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">
                    <x-icon name="book-open" size="sm" class="text-ink-muted" />
                    Course catalog
                </a>
            </li>
        </ul>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-line p-2">
            @csrf
            <button
                type="submit"
                data-dropdown-item
                class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
            >
                <x-icon name="log-out" size="sm" class="text-ink-muted" />
                Sign out
            </button>
        </form>
    </div>
</div>
