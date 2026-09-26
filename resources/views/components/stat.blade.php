@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'primary',
    'href' => null,
])

@php
    /*
     | One counted number.
     |
     | The label always says exactly what is counted, in words. A tile without a
     | plain label is a number nobody can trust, so `label` is required and no
     | abbreviation is accepted here.
     |
     | The coloured rule is the only decoration. It never carries meaning, so
     | the value and its label are always the same for every reader.
     */
    $edges = [
        'primary' => 'border-t-primary',
        'accent' => 'border-t-accent',
        'neutral' => 'border-t-line',
    ];

    $edge = $edges[$tone] ?? $edges['primary'];
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => 'group flex flex-col border border-line border-t-4 '.$edge.' bg-surface p-5 transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus']) }}
    >
        <dt class="text-sm leading-5 text-ink-muted">{{ $label }}</dt>
        <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink tabular-nums">{{ $value }}</dd>
        @if ($hint)
            <p class="mt-2 text-sm leading-5 text-ink-muted">{{ $hint }}</p>
        @endif
        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary-text group-hover:underline">
            {{ $slot->isEmpty() ? 'Open' : $slot }}
            <x-icon name="chevron-right" size="sm" />
        </span>
    </a>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-col border border-line border-t-4 '.$edge.' bg-surface p-5']) }}>
        <dt class="text-sm leading-5 text-ink-muted">{{ $label }}</dt>
        <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink tabular-nums">{{ $value }}</dd>
        @if ($hint)
            <p class="mt-2 text-sm leading-5 text-ink-muted">{{ $hint }}</p>
        @endif
        {{ $slot }}
    </div>
@endif
