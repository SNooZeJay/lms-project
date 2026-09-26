@props([
    'href' => null,
    'size' => 'md',
    'showName' => true,
    'tagline' => null,
    'markClass' => '',
    'nameClass' => '',
])

@php
    /*
     | The brand: the mark, and the product name beside it as live text.
     |
     | The name is text rather than part of the artwork on purpose. Text takes
     | the colour of the current theme, so it stays readable in both, and it can
     | be selected, searched, and read by a screen reader. A lockup image with
     | the name baked in was tried and had to be carried at 2.4 MB, and its dark
     | navy lettering disappeared against the dark theme.
     |
     | The mark carries an empty alternative text because the product name sits
     | right beside it, so a screen reader announces the name once instead of
     | twice. When the name is hidden, the caller supplies the accessible name
     | through the wrapping link.
     */
    $scales = [
        'sm' => ['mark' => 'size-7', 'name' => 'text-sm', 'tagline' => 'text-xs', 'pixels' => 28],
        'md' => ['mark' => 'size-9', 'name' => 'text-base', 'tagline' => 'text-xs', 'pixels' => 36],
        'lg' => ['mark' => 'size-11', 'name' => 'text-lg', 'tagline' => 'text-sm', 'pixels' => 44],
        'xl' => ['mark' => 'size-14', 'name' => 'text-xl', 'tagline' => 'text-sm', 'pixels' => 56],
    ];

    // The mark source is chosen by the size it is drawn at, so a small mark is
    // never shipped as a large file.
    $sources = [
        'sm' => 'images/brand/lms-mark-64.png',
        'md' => 'images/brand/lms-mark-128.png',
        'lg' => 'images/brand/lms-mark-128.png',
        'xl' => 'images/brand/lms-mark-256.png',
    ];

    $scale = $scales[$size] ?? $scales['md'];
    $source = $sources[$size] ?? $sources['md'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-center gap-2.5']) }}>
    <img
        src="{{ asset($source) }}"
        alt=""
        width="{{ $scale['pixels'] }}"
        height="{{ $scale['pixels'] }}"
        data-brand-mark
        class="{{ $scale['mark'] }} {{ $markClass }}"
    >

    @if ($showName)
        <span class="min-w-0 leading-tight">
            <span class="block truncate font-[650] tracking-tight text-ink {{ $scale['name'] }} {{ $nameClass }}">IT Learning Hub</span>
            @if ($tagline)
                <span class="mt-0.5 block truncate text-ink-muted {{ $scale['tagline'] }}">{{ $tagline }}</span>
            @endif
        </span>
    @endif
</span>
