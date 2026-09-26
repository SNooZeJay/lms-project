@props([
    'title',
    'description' => null,
    'icon' => 'folder',
    'compact' => false,
    'level' => 2,
])

@php
    $heading = 'h'.$level;
@endphp

<div
    {{ $attributes->merge(['class' => 'flex flex-col items-center text-center '.($compact ? 'py-8' : 'py-14')]) }}
>
    <span class="flex size-11 items-center justify-center rounded-full border border-line bg-surface-muted text-ink-muted">
        <x-icon :name="$icon" size="lg" />
    </span>

    <{{ $heading }} class="mt-4 text-lg font-semibold text-ink">{{ $title }}</{{ $heading }}>

    @if ($description)
        <p class="mt-2 max-w-md text-sm leading-6 text-ink-muted">{{ $description }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
            {{ $slot }}
        </div>
    @endif
</div>
