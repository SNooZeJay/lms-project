@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'level' => 1,
])

@php
    $heading = 'h'.$level;
@endphp

{{--
    The page header: an optional eyebrow, the page's own heading, an optional
    description, and optional actions on the right at wide widths.

    The space below it is on the header, as `pb-8`, rather than applied to
    whatever element happens to come next. A sibling rule has to guess three
    things: that the next element is a sibling, that the header is its
    immediately previous sibling, and that nothing else sits between them. The
    first version of this was a `.page-header + *` rule in the stylesheet, and
    the `page-header` class it keyed on was never on this element, so the rule
    matched nothing and every signed in page had its first card touching the
    description above it. A spacing rule written against an imagined selector is
    a rule that does nothing and looks like it does something.

    Owning the space means the header is separated from the content below it
    whatever that content is.
--}}
<header {{ $attributes->merge(['class' => 'flex flex-col gap-4 pb-8 lg:flex-row lg:items-end lg:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <{{ $heading }}
            class="page-title mt-2"
            data-motion="heading"
        >
            {{ $title }}
        </{{ $heading }}>

        @if ($description)
            <p class="mt-3 max-w-2xl leading-7 text-ink-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            {{ $actions }}
        </div>
    @endisset
</header>
