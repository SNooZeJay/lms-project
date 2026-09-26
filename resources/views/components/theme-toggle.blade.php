{{--
    The theme control.

    Both states are named in text, so the control never depends on recognising
    an icon. The current state is exposed through `aria-pressed`, and the label
    says which theme pressing it will select.
--}}
<button
    type="button"
    data-theme-toggle
    aria-label="Switch to dark theme"
    aria-pressed="false"
    {{ $attributes->merge(['class' => 'btn btn-secondary btn-sm gap-1.5 px-2.5']) }}
>
    <span class="hidden dark:block"><x-icon name="moon" size="sm" /></span>
    <span class="block dark:hidden"><x-icon name="sun" size="sm" /></span>
    <span data-theme-label class="hidden sm:inline">Dark</span>
</button>
