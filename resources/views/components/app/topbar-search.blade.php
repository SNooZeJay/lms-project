@props([
    /*
     | The topbar search.
     |
     | A real form that really searches, pointed at the course catalog, which is
     | the only searchable collection in the application. It is not a command
     | palette and does not pretend to be one: there is nothing for it to run
     | except a query, and a control that opens an empty overlay is worse than
     | a search box that works.
     |
     | The keyboard hint advertises the slash shortcut, which app.js binds. It
     | is hidden on the narrowest screens because there is no room for it and
     | the shortcut is not discoverable there anyway.
     */
    'value' => null,
])

<form
    action="{{ route('courses.index') }}"
    method="GET"
    role="search"
    data-topbar-search
    {{ $attributes->merge(['class' => 'hidden min-w-0 sm:block']) }}
>
    <label for="topbar-search" class="sr-only">Search the course catalog</label>

    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-subtle">
            <x-icon name="search" size="sm" />
        </span>

        <input
            id="topbar-search"
            type="search"
            name="q"
            value="{{ $value }}"
            placeholder="Search courses"
            autocomplete="off"
            class="block w-full rounded-md border border-line bg-canvas py-2 pr-16 pl-9 text-sm text-ink placeholder:text-ink-subtle focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus lg:w-72"
        />

        {{-- The shortcut. Decorative in the sense that a person who does not
             know the shortcut is not blocked, because the box is a form and can
             be typed into. Hidden from assistive technology so the shortcut is
             not announced as if it were part of the field's name. --}}
        <kbd
            aria-hidden="true"
            class="pointer-events-none absolute inset-y-0 right-0 hidden items-center pr-3 text-ink-subtle lg:flex"
        >
            <span class="rounded border border-line px-1.5 py-0.5 font-mono text-[10px] leading-none">/</span>
        </kbd>
    </div>
</form>
