@props([
    'label',
    'value',
    'max' => null,
    'hint' => null,
    'href' => null,
    'tone' => 'primary',
])

{{--
    One row in a horizontal bar chart.

    A bar is the right form for a handful of values that share one unit, because
    the comparison is the length. It is drawn with a plain div rather than an SVG
    or a chart library: the whole chart is a list of these, so a screen reader
    reads the numbers as text, the bar is decorative, and there is no script and
    no dependency to keep working.

    The accessible name is the row's own text, so the visual length never has to
    be announced. The bar itself is hidden from assistive technology for that
    reason, and the percentage is written out rather than implied.
--}}
@php
    $max = $max !== null && $max > 0 ? (float) $max : max(1.0, (float) $value);
    $percent = max(0.0, min(100.0, ((float) $value / $max) * 100));
    $isZero = (float) $value <= 0;

    /*
     | The width is a generated class, not a `style` attribute.
     |
     | The content security policy is `style-src 'self'`, which forbids the
     | `style` attribute. An inline `width` was discarded by the browser, the fill
     | fell back to its natural width, and every bar in every chart drew at one
     | hundred percent whatever its value. Three enrollment states holding
     | nothing looked like three states holding everything.
     |
     | `progress-step-*` sets a custom property and `.bar-fill` reads it, which is
     | the arrangement the `<progress>` element already uses in this system, so the
     | two kinds of bar cannot drift apart. Five point steps, for the same reason
     | the progress element uses them: the exact number is printed beside the bar
     | and is what a person relies on.
     */
    $step = (int) (round($percent / 5) * 5);
@endphp

<li {{ $attributes->merge(['class' => 'grid gap-1.5']) }}>
    <div class="flex items-baseline justify-between gap-3">
        @if ($href)
            {{--
                The target, not the text, carries the height.

                A bare text link sits on one 20 pixel line, which is under the 24
                pixels WCAG 2.5.8 asks for, and this link is not exempt the way a
                link inside a sentence is: it is a chart row whose whole purpose
                is to be pressed, and there is nothing else on the row to press.
                The truncation moves to an inner span so the row can be centred
                and given a minimum height without breaking the ellipsis, which
                a flex container would swallow.
            --}}
            <a
                href="{{ $href }}"
                class="row-target rounded-sm text-sm font-medium text-ink hover:text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
            >
                <span class="min-w-0 truncate">{{ $label }}</span>
            </a>
        @else
            <span class="row-target">
                <span class="min-w-0 truncate text-sm font-medium text-ink">{{ $label }}</span>
            </span>
        @endif

        <span class="shrink-0 text-sm font-semibold tabular-nums text-ink">
            {{ $isZero && $max === null ? 0 : $value }}
            @if (! $isZero && $max !== null)
                <span class="font-normal text-ink-subtle">/ {{ (int) $max }}</span>
            @endif
        </span>
    </div>

    {{-- Reserve the track height whether or not there is a value, so a list of
         zeros keeps the same rhythm as a list with data in it. --}}
    <div
        class="h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken"
        role="presentation"
        aria-hidden="true"
    >
        <div
            class="bar-fill progress-step-{{ $step }} h-full rounded-full {{ $tone === 'accent' ? 'bg-accent' : 'bg-primary' }}"
        ></div>
    </div>

    @if ($hint)
        <p class="text-xs text-ink-subtle">{{ $hint }}</p>
    @endif
</li>
