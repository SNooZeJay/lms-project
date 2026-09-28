@props([
    'value',
    'label' => null,
    'tone' => null,
])

@php
    $resolved = \App\Support\StatusLabel::for($value);
@endphp

{{--
    A status, which is a badge with the wording already resolved.

    The attributes are forwarded to the badge, because this component is a thin
    wrapper and a caller that passes class="shrink-0" expects it to reach the
    element. It did not: the badge was rendered with no attributes at all, so
    every class handed to a status was dropped. x-badge does merge them, so
    forwarding is all that is needed.
--}}
<x-badge
    :tone="$tone ?? $resolved['tone']"
    {{ $attributes }}
>{{ $label ?? $resolved['label'] }}</x-badge>
