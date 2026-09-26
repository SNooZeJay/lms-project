@props([
    'minor' => 0,
    'currency' => 'PHP',
    'type' => null,
    'code' => false,
    'class' => '',
])

@php
    /*
     | A money amount.
     |
     | The amount is an integer count of minor units, so this component is the
     | only way a price reaches the page. A raw `49900` or a float such as
     | `499.0` cannot be printed by accident.
     |
     | Pass `type` only for a course price, which may be the word `Free`. An
     | amount that was actually charged, such as a payment record, has no course
     | type and is always formatted as money.
     */
    $formatted = $type === null
        ? \App\Support\Money::format($minor, $currency)
        : \App\Support\Money::course($minor, $type, $currency);
@endphp

<span {{ $attributes->merge(['class' => 'whitespace-nowrap tabular-nums '.$class]) }}>
    {{ $formatted }}
    @if ($code)
        <span class="ml-1 text-xs font-medium text-ink-muted">{{ \App\Support\Money::code($minor, $currency) }}</span>
    @endif
</span>
