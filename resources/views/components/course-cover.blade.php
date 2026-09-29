@props([
    'course',
    'width' => 640,
    'height' => 360,
    'eager' => false,
    'rounded' => 'rounded-t-xl',
])

@php
    /*
     | One place that decides how a course cover is shown, so every card, list and
     | header in the application shows the same course the same way.
     |
     | Four states, and the difference is not decoration:
     |
     |   - an upload, served from this application's public disk
     |   - a photograph chosen from the catalog, served from Unsplash's CDN at
     |     whatever width this card needs, which is why the width and height are
     |     parameters rather than fixed
     |   - no cover, which is a placeholder rather than a gap, because a list of
     |     courses with a hole in one of them reads as a fault
     |   - a cover whose file could not be loaded, which is the same placeholder
     |
     | THE PLACEHOLDER IS ALWAYS IN THE DOCUMENT, AND THE IMAGE SITS ON TOP OF IT
     |
     | The first version chose between the two, so there was nothing underneath an
     | image to fall back to. When the image failed, the markup could only hide it,
     | and a hidden image over an empty box is a grey rectangle with no explanation
     | on it, which is the least informative thing the card could show.
     |
     | Rendering the placeholder first and the image over it means the fallback is
     | already there. Hiding the image is all that a failure has to do, and there is
     | no second copy of the same markup to keep in step with the first.
     |
     | Every call site sets its own aspect ratio, so the box has a height whether or
     | not an image is present, and the placeholder resolves against it rather than
     | deciding the shape.
     |
     | THE PLACEHOLDER IS NOT ANNOUNCED ALONGSIDE A REAL IMAGE
     |
     | It carries a role and a label, because on its own it is the only description
     | of the box. With a real image present that would be a second description of
     | the same picture, and a screen reader would read both. So it is hidden from
     | assistive technology whenever there is an image, and the script puts it back
     | when the image fails and the placeholder becomes the description again.
     |
     | The credit is rendered for a chosen photograph and for nothing else. An
     | instructor's own upload is not somebody else's work and must not be labelled
     | as though it were.
     */
    $uploaded = $course->uploadedCoverUrl();
    $chosen = $course->chosenCoverUrl($width, $height);
    $src = $uploaded ?? $chosen;
    $alt = $course->coverAltText();
    $creditName = $course->coverCreditName();
    $creditUrl = $course->coverCreditUrl();
@endphp

<div {{ $attributes->merge(['class' => 'relative isolate overflow-hidden bg-surface-muted']) }}>
    <div
        class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-quiet to-surface-muted p-4 {{ $rounded }}"
        data-cover-placeholder="true"
        @if ($src) aria-hidden="true" @else role="img" aria-label="No cover image for {{ $course->title }}" @endif
    >
        <span class="text-center">
            <x-icon name="book-open" size="lg" class="text-primary-text/60" />
            <span class="mt-2 block text-sm font-semibold text-ink-subtle">
                {{ \Illuminate\Support\Str::limit($course->title, 28) }}
            </span>
        </span>
    </div>

    @if ($src)
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            width="{{ $width }}"
            height="{{ $height }}"
            @if ($eager)
                loading="eager"
                fetchpriority="high"
            @else
                loading="lazy"
                decoding="async"
            @endif
            {{-- object-cover with a fixed box is what keeps every card the same
                 height whatever aspect ratio the original photograph was. Without it
                 a tall photograph makes its own row taller and the grid stops being
                 a grid.

                 `cover-zoom` is inert on its own. The scale is declared against an
                 ancestor carrying `.lift`, so this only moves inside a card that
                 answers the pointer, and a cover on a course page stays still. The
                 box clips it, and object-cover means a scale cannot expose an edge.

                 `data-cover-image` is how the bundle finds this element. There is
                 deliberately no `onerror` attribute: the content security policy
                 permits scripts from this origin and from a per request nonce, and
                 not inline ones, so an event handler written into the markup is
                 refused by the browser and the fallback it was written to provide
                 silently never happens. The handler is in app.js, which the policy
                 does permit. --}}
            class="cover-zoom absolute inset-0 h-full w-full object-cover {{ $rounded }}"
            data-cover-image
        />
    @endif

    {{--
        THE CREDIT IS NOT DRAWN HERE

        A dark "Photo: Unsplash" badge sat over the bottom left of every cover, and
        it was the first thing wrong with a card. Six covers in a grid meant six
        identical black labels laid over six different photographs, none of them
        at the same contrast against its background, and the label competed with
        the picture it was printed on. It read as a watermark that nobody asked
        for.

        Attribution is still recorded and still reachable, in the two places where
        somebody is actually reading about the photograph rather than scanning a
        list: the course's own page, and the About page. The name and the address
        are columns on the course, so the credit travels with the cover through a
        re-theme or an export instead of being reassembled inside a template.

        The cover component is the one thing every card in the application shares,
        so anything drawn here is drawn everywhere. If a credit is ever wanted
        back on a card it belongs as an optional prop, off by default, rather than
        as part of the shared surface.
    --}}
</div>