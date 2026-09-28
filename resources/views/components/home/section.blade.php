@props([
    'id',
    'title',
    'description' => null,
])

{{--
    One band of the home page.

    The page is a stack of these, separated by a hairline, and they are the same
    component because they were four near-identical <section> elements that had
    each been given their own vertical padding. One was py-14 and another py-12,
    so the gaps between sections did not match each other, and nothing recorded
    whether that was deliberate.

    The rule this encodes: a vertical step on this page is either the space
    between two sections or the space inside one, never both. The hairline is the
    boundary and the padding is the space around it, so the rhythm is decided in
    one place rather than at every call site.

    The padding grows with the viewport rather than staying at one value. A phone
    needs less air above a heading than a desktop does, because the column is
    narrower and the heading already fills it, and a fixed 56 pixels on a 320
    pixel screen reads as a gap rather than as breathing room.
--}}
<section {{ $attributes->merge(['class' => 'border-t border-line py-12 sm:py-14 lg:py-16']) }} aria-labelledby="{{ $id }}">
    {{--
        The heading block. lg:max-w-2xl caps the measure, because these
        paragraphs sit beside a grid of cards and a two line explanation allowed
        to run the full width of a large screen becomes a 140 character line,
        which is well past the point where the eye loses its place on the return
        sweep.

        The action is optional and sits inside the heading block, bottom aligned
        with it. It was below the card grid to begin with, which left a small
        link stranded under the cards and away from the heading it belongs to.
        shrink-0 keeps the action at its own width, so the heading is what gives
        way when the row is tight.
    --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between lg:gap-8">
        <div class="min-w-0 lg:max-w-2xl">
            <h2 id="{{ $id }}" class="text-2xl font-semibold text-balance text-ink">
                {{ $title }}
            </h2>

            @if (filled($description))
                <p class="mt-3 leading-7 text-ink-muted">
                    {{ $description }}
                </p>
            @endif
        </div>

        @isset($action)
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                {{ $action }}
            </div>
        @endisset
    </div>

    {{ $slot }}
</section>
