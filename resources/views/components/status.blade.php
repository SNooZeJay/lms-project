@props([
    'value',
    'label' => null,
    'tone' => null,
])

@php
    $resolved = \App\Support\StatusLabel::for($value);
@endphp

<x-badge :tone="$tone ?? $resolved['tone']">{{ $label ?? $resolved['label'] }}</x-badge>
