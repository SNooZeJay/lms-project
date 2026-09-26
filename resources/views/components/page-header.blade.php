@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'level' => 1,
])

@php
    $heading = 'h'.$level;
@endphp

<header {{ $attributes->merge(['class' => 'flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <{{ $heading }} class="mt-2 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
            {{ $title }}
        </{{ $heading }}>

        @if ($description)
            <p class="mt-3 max-w-2xl leading-7 text-ink-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            {{ $actions }}
        </div>
    @endisset
</header>
