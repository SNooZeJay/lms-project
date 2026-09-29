@props([
    'name',
    'label' => null,
    'id' => null,
    'autocomplete' => 'current-password',
    'hint' => null,
    'required' => true,
    'maxlength' => null,
    'value' => null,
])

@php
    $id = $id ?? $name;
    $describedBy = $hint ? $id.'-hint' : null;
@endphp

{{--
    A password field with a reveal control.

    The control is a real button rather than a click handler on the icon, so it
    is reachable by keyboard and announced. Its accessible name changes with the
    state, because "show password" and "hide password" are different actions and
    a single static name would leave a screen reader user guessing which one
    they just pressed.

    Nothing is stored and nothing is sent: the field is still a password field,
    the value stays in the form, and the autocomplete attribute is left alone so
    a password manager keeps working. The behaviour lives in app.js, so the page
    needs no inline script.
--}}
<div {{ $attributes->merge(['class' => 'field flex min-w-0 flex-col']) }}>
    {{-- The label is optional because the sign in page pairs it with a forgot
         password link on one row, and an empty label element would be announced
         as an unlabelled field. The input still gets its name from the row. --}}
    @if ($label)
        <label class="field-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="input-icon">
        <span class="input-icon-mark" aria-hidden="true">
            <x-icon name="lock" size="sm" />
        </span>

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            class="field-control"
            autocomplete="{{ $autocomplete }}"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($value !== null) value="{{ $value }}" @endif
            @required($required)
        >

        {{--
            The reveal control.

            `input-icon-action` sizes this at thirty six by thirty six, which
            clears the twenty four pixel minimum in WCAG 2.5.8 but is the one
            control on a sign in form that a finger has to find without reading
            it. The reveal is a button rather than a decoration, and it is the
            only way to see what you have typed, so it gets the same forty four
            pixels as every other control on the form.
        --}}
        <button
            type="button"
            class="input-icon-action size-11"
            data-password-reveal="{{ $id }}"
            aria-label="Show password"
            aria-controls="{{ $id }}"
            aria-pressed="false"
        >
            <span data-password-icon="show" class="hidden">
                <x-icon name="eye" size="sm" />
            </span>
            <span data-password-icon="hide">
                <x-icon name="eye-off" size="sm" />
            </span>
            <span class="sr-only" data-password-status>Password is hidden</span>
        </button>
    </div>

    @if ($hint)
        <p id="{{ $describedBy }}" class="field-hint">{{ $hint }}</p>
    @endif
</div>
