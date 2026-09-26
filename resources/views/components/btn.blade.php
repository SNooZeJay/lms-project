@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'block' => false,
])

@php
    $classes = trim('btn btn-'.$variant.' btn-'.$size.($block ? ' btn-block' : ''));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
