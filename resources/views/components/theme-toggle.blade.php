{{--
    The theme control.

    An icon on its own, with no text beside it. The control carries an
    accessible name that says which theme pressing it will select, and the state
    through `aria-pressed`, so nothing depends on recognising the icon or on
    seeing a word. That keeps the header to a brand and three controls.
--}}
<button
    type="button"
    data-theme-toggle
    aria-label="Switch to dark theme"
    aria-pressed="false"
    {{ $attributes->merge(['class' => 'btn btn-secondary btn-sm size-11 p-0']) }}
>
    <span class="hidden dark:block"><x-icon name="moon" size="sm" /></span>
    <span class="block dark:hidden"><x-icon name="sun" size="sm" /></span>
</button>
