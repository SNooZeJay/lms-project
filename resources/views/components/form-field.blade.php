@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
    'rows' => null,
    'maxlength' => null,
    'inputmode' => null,
    'disabled' => false,
    'options' => null,
    'emptyLabel' => null,
    'fieldClass' => '',
    'scope' => null,
    'accept' => null,
])

@php
    /*
     | The identifier is the field name, optionally inside a caller-supplied scope.
     |
     | A page that renders the same form more than once cannot use the bare name.
     | The Instructor course outline renders an add form for every module, every
     | lesson and every material, and with the bare name the page carried
     | field-title nineteen times. A label's `for` matches the first element with
     | that identifier, so eighteen of those fields were announced with the wrong
     | name, and any script looking one up edited the wrong row.
     |
     | The scope is a prefix rather than a whole identifier, so a caller cannot
     | supply it and forget the part that makes it unique. Handing over the whole
     | id would allow the field name to be dropped along with it.
     */
    $id = 'field-'.($scope ? $scope.'-' : '').$name;
    $describedBy = array_filter([
        $hint ? $id.'-hint' : null,
        $errors->has($name) ? $id.'-error' : null,
    ]);

    $current = old($name, $value);

    $controlClass = trim('field-control '.(($type === 'select' || $type === 'textarea') ? 'field-control-surface ' : '').$fieldClass);
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <label for="{{ $id }}" class="field-label">
        {{ $label }}
        @if ($required)
            <span class="ml-1 text-xs font-normal text-ink-subtle">required</span>
        @endif
    </label>

    @if ($type === 'select')
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            class="{{ $controlClass }}"
            @required($required)
            @disabled($disabled)
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
        >
            @if ($emptyLabel !== null)
                <option value="">{{ $emptyLabel }}</option>
            @endif
            @foreach ($options ?? [] as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
            {{ $slot }}
        </select>
    @elseif ($type === 'textarea')
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            class="{{ $controlClass }}"
            @required($required)
            @disabled($disabled)
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
            @if ($rows) rows="{{ $rows }}" @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        >{{ $current }}</textarea>
    @else
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            class="{{ $controlClass }}"
            @required($required)
            @disabled($disabled)
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
            @if (! in_array($type, ['checkbox', 'radio', 'file'], true)) value="{{ $current }}" @endif
            {{-- `accept` narrows the picker, and is a convenience and nothing more.
                 The server's rule is the one that decides, and it reads the bytes
                 rather than the extension, so a file that arrives anyway is still
                 checked. Announcing this would be noise: it restates the hint. --}}
            @if ($accept && $type === 'file') accept="{{ $accept }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($inputmode) inputmode="{{ $inputmode }}" @endif
        >
    @endif

    @if ($hint)
        <p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="field-error">
            <x-icon name="alert" size="sm" class="mt-1" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
