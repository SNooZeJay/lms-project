@props(['tone' => 'neutral', 'role' => null])

@php
    $resolvedRole = $role ?? 'status';
@endphp

<div
    {{ $attributes->merge(['class' => 'note note-'.$tone]) }}
    @if ($resolvedRole === 'status' || $resolvedRole === 'alert') role="{{ $resolvedRole }}" @endif
>
    {{ $slot }}
</div>
