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
     | The brand lockup.
     |
     | The mark carries an empty alternative text because the product name sits
     | right beside it, so a screen reader announces the name once instead of
     | twice. When the name is hidden, the caller supplies the accessible name
     | through the wrapping link.
     */
    $scales = [
        'sm' => ['mark' => 'size-7', 'name' => 'text-sm', 'tagline' => 'text-xs', 'pixels' => 28],
        'md' => ['mark' => 'size-9', 'name' => 'text-base', 'tagline' => 'text-xs', 'pixels' => 36],
        'lg' => ['mark' => 'size-12', 'name' => 'text-lg', 'tagline' => 'text-sm', 'pixels' => 48],
        'xl' => ['mark' => 'size-16', 'name' => 'text-xl', 'tagline' => 'text-sm', 'pixels' => 64],
    ];

    $scale = $scales[$size] ?? $scales['md'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-center gap-2.5']) }}>
    <img
        src="{{ asset('images/brand/lms-mark.png') }}"
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
