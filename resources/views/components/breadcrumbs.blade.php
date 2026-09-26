@props(['items' => []])

@php
    /*
     | A trail of where the current page sits.
     |
     | The last item is the current page and is marked with `aria-current`, so it
     | is not a link. A single item renders without the trail, because a
     | one-step breadcrumb is noise.
     */
    $trail = array_values(array_filter(
        $items,
        fn (mixed $item): bool => is_array($item) && ($item['label'] ?? '') !== ''
    ));
@endphp

@if (count($trail) > 1)
    <nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'min-w-0']) }}>
        <ol role="list" class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
            @foreach ($trail as $position => $item)
                @php
                    $isLast = $position === count($trail) - 1;
                @endphp
                <li class="flex min-w-0 items-center gap-1">
                    @if (! $isLast && ! empty($item['href']))
                        <a
                            href="{{ $item['href'] }}"
                            class="rounded-sm font-medium text-ink-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                        >{{ $item['label'] }}</a>
                    @else
                        <span @if ($isLast) aria-current="page" @endif class="truncate font-semibold text-ink">
                            {{ $item['label'] }}
                        </span>
                    @endif

                    @unless ($isLast)
                        <x-icon name="chevron-right" size="sm" class="text-ink-subtle" />
                    @endunless
                </li>
            @endforeach
        </ol>
    </nav>
@endif
