{{--
    The flash region, and the only reader of Laravel's session message bag.

    Renders whatever `Notify` left in the session and nothing else. One component
    owns the rule, so a page cannot forget to show a message and cannot invent a
    second way of showing one.

    The region is `aria-live="polite"` so a message that arrives after a redirect
    is announced without interrupting whatever the reader was doing. An error
    message overrides that with `role="alert"` on the message itself, because an
    error is the one case where interrupting is correct.

    Fixed to the bottom on a phone and to the bottom right on a wide screen. The
    bottom on a phone is the thumb end of the screen, so a message can be
    dismissed without reaching across the display, and it cannot cover the
    primary action at the top of a page.

    `pointer-events-none` on the region and `pointer-events-auto` on each
    message, so the empty region does not swallow clicks on the page behind it.
    An overlay that intercepts clicks is the most common way a toast system
    breaks a page.
--}}

{{--
    `@props` with nothing in it, and that is the point.

    Without it Blade never binds `$attributes`, so the merge further down throws
    "Undefined variable $attributes" and every page that renders the shell goes
    to a 500. The component still has to declare that it takes attributes, even
    when it declares no properties of its own.
--}}
@props([])

@php
    /*
     | Blade's `@php` form, and not a raw `<?php` block.
     |
     | The first version of this opened with `<?php` and never closed it. Blade
     | then read the rest of the file as PHP, so the `@if` below was never
     | compiled and the view threw `syntax error, unexpected token "if"` on
     | every page that renders the shell. A template that opens a PHP tag
     | without closing it does not just leak into the rest of its own file: it
     | takes the template with it.
     */
    $messages = [];

    foreach (['success', 'error', 'warning', 'info'] as $level) {
        $value = session($level);

        if (filled($value)) {
            $messages[] = [
                'tone' => $level,
                'text' => is_array($value) ? ($value['message'] ?? reset($value)) : $value,
            ];
        }
    }
@endphp

@if ($messages !== [])
    <div
        data-toast-region
        aria-live="polite"
        aria-atomic="false"
        {{ $attributes->merge(['class' => 'pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:inset-x-auto sm:right-0 sm:items-end']) }}
    >
        @foreach ($messages as $message)
            <x-toast :tone="$message['tone']" :data-toast-lifetime="6000">{{ $message['text'] }}</x-toast>
        @endforeach
    </div>
@endif
