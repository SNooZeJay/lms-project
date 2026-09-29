@props([
    'id',
    'title',
    'description' => null,
])

{{--
    One band of a public page.

    The page is a stack of these, and they are the same component because they
    were once four near identical <section> elements that had each been given
    their own vertical padding. One was py-14 and another py-12, so the gaps
    between sections did not match each other and nothing recorded whether that
    was deliberate.

    The rule this encodes: a vertical step on these pages is either the space
    between two sections or the space inside one, never both. The hairline is the
    boundary and the padding is the space around it, so the rhythm is decided in
    one place, which is the `.band` class, rather than at every call site.

    The heading block is `.band-head` for the same reason, and the action sits
    inside it, bottom aligned with the lead line. It was below the card grid to
    begin with, which left a small link stranded under the cards and away from
    the heading it belongs to.

    `mt-8` on the body is the one gap inside a band, and it is the same in every
    band on the site. The lead line is capped by `.lead` rather than by the width
    of the grid it sits in, because these paragraphs sit beside nothing and a two
    line explanation allowed to run the full width of a large screen becomes a
    hundred and thirty character line.
--}}
<section {{ $attributes->merge(['class' => 'band']) }} aria-labelledby="{{ $id }}">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between sm:gap-10">
        <div class="band-head">
            <h2 id="{{ $id }}">{{ $title }}</h2>

            @if ($description)
                <p class="lead">{{ $description }}</p>
            @endif
        </div>

        @isset($action)
            {{-- `shrink-0` so the action keeps its own width and the heading is
                 what the space is taken from. --}}
            <div class="flex shrink-0 items-center">
                {{ $action }}
            </div>
        @endisset
    </div>

    <div class="mt-8">
        {{ $slot }}
    </div>
</section>
