@props([
    'heading',
    'description' => null,
    'id' => null,
])

{{--
    A card with a heading and an optional description, and a slot for the body.

    The same two pieces `bar-chart` uses, exposed on their own. It exists because
    three cards on the administrator report now need a heading and a description
    with something other than bars inside, and copying `card-header` and
    `card-body` into each of them is how the padding on two cards ends up being
    different by a pixel and nobody can say which one is right.

    `card-header` and `card-body` are what put the heading twenty one pixels in and
    seventeen down. Using them makes the alignment structural rather than a
    coincidence of padding somebody remembered to add.
--}}
@php
    $headingId = $id ?: 'card-'.substr(md5((string) $heading), 0, 8);
@endphp

<section {{ $attributes->merge(['class' => 'card']) }} aria-labelledby="{{ $headingId }}">
    <div class="card-header">
        <div class="min-w-0">
            <h2 id="{{ $headingId }}" class="text-base font-semibold text-ink">{{ $heading }}</h2>

            @if ($description)
                <p class="mt-1 text-sm leading-6 text-ink-muted">{{ $description }}</p>
            @endif
        </div>
    </div>

    <div class="card-body">
        {{ $slot }}
    </div>
</section>