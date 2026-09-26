@props([
    'percentage' => 0,
    'label' => 'Progress',
    'detail' => null,
    'showText' => true,
    'size' => 'md',
    'tone' => 'primary',
])

@php
    /*
     | A real progress bar.
     |
     | The element is a native `progress`, so the value never depends on a
     | percentage painted in an inline style, which the content security policy
     | does not allow. The bar is built from CSS widths in `app.css`, and the
     | exact number is always printed beside it, so the meaning survives without
     | colour, without width, and in a screen reader.
     |
     | The value comes from the server. A browser cannot submit a percentage.
     */
    $clamped = max(0, min(100, (int) $percentage));

    // The bar is a visual summary, so it is drawn in five point steps. The
    // figure beside it is the exact value and is what a person relies on.
    $step = (int) (round($clamped / 5) * 5);

    $tones = [
        'primary' => 'progress-primary',
        'accent' => 'progress-accent',
        'success' => 'progress-success',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($showText)
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <p class="text-sm font-semibold text-ink">{{ $label }}</p>
            <p class="text-sm font-semibold text-ink tabular-nums">{{ $clamped }}%</p>
        </div>
    @endif

    <progress
        class="progress-track {{ $tones[$tone] ?? $tones['primary'] }} progress-step-{{ $step }} {{ $size === 'sm' ? 'h-1.5' : 'h-2.5' }} mt-2 w-full"
        value="{{ $clamped }}"
        max="100"
        aria-label="{{ $label }}: {{ $clamped }} percent{{ $detail ? '. '.$detail : '' }}"
    ></progress>

    @if ($detail)
        <p class="mt-2 text-sm leading-6 text-ink-muted">{{ $detail }}</p>
    @endif
</div>
