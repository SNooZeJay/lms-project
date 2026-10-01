@props([
    'tone' => 'info',
    'title' => null,
])

{{--
    One flash message.

    ARIA, because a message that only exists visually is a message a screen
    reader never receives. role="status" announces it politely when it arrives,
    which is the right urgency for "your work was saved". A message that blocks
    what the reader was trying to do uses role="alert" instead, because that one
    should interrupt.

    The dismiss control is a real button with a name, not a bare cross. An icon
    only control with no label is announced as "button" and says nothing.

    The icon names are the ones this application defines in config/icons.php. A
    name that is not in that file draws nothing, so alert and close are used
    rather than the Lucide originals alert-circle and x.
--}}

@php
    $palette = [
        'success' => ['check-circle', 'bg-success-surface', 'text-success-text', 'border-success-line', 'status'],
        'error' => ['alert', 'bg-error-surface', 'text-error-text', 'border-error-line', 'alert'],
        'warning' => ['alert', 'bg-warning-surface', 'text-warning-text', 'border-warning-line', 'alert'],
        'info' => ['info', 'bg-info-surface', 'text-info-text', 'border-info-line', 'status'],
    ];

    $chosen = $palette[$tone] ?? $palette['info'];
    [$icon, $surface, $text, $line, $role] = $chosen;
@endphp

<div
    data-toast
    role="{{ $role }}"
    {{ $attributes->merge(['class' => 'pointer-events-auto flex items-start gap-3 border '.$line.' '.$surface.' px-4 py-3 '.$text]) }}
>
    <x-icon :name="$icon" size="sm" class="mt-0.5 shrink-0" />

    <div class="min-w-0 flex-1 text-sm leading-5">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
            <p>{{ $slot }}</p>
        @else
            <p class="font-semibold">{{ $slot }}</p>
        @endif
    </div>

    <button
        type="button"
        data-toast-dismiss
        class="-mr-1 -mt-1 inline-flex size-8 shrink-0 items-center justify-center rounded-sm opacity-70 transition-opacity hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
    >
        <x-icon name="close" size="sm" />
        <span class="sr-only">Dismiss this message</span>
    </button>
</div>
