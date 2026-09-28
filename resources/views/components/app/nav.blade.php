@props([
    'user',
    'id' => 'workspace-nav',
])

@php
    $groups = \App\Support\Navigation::for($user);

    /*
     | Whether an item is the current page is answered by Navigation, not here.
     |
     | The answer used to be an exact comparison against the item's match list,
     | and that list holds patterns such as admin.users.* rather than literal
     | route names. Exact comparison therefore matched only the dashboard, whose
     | entry carries no wildcard, and every other item sat unhighlighted on every
     | page underneath it.
     */
@endphp

@if ($groups !== [])
    <nav id="{{ $id }}" aria-label="Workspace" {{ $attributes->merge(['class' => 'flex h-full min-h-0 flex-col gap-6 overflow-y-auto px-3 py-4 lg:px-4']) }}>
        @foreach ($groups as $group)
            <div>
                <p class="px-3 text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                    <span class="lg:hidden xl:inline">{{ $group['label'] }}</span>
                    <span class="hidden lg:inline xl:hidden" aria-hidden="true">&nbsp;</span>
                </p>

                <ul role="list" class="mt-2 space-y-1">
                    @foreach ($group['items'] as $item)
                        @php $active = \App\Support\Navigation::isCurrent($item); @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                title="{{ $item['label'] }}"
                                @if ($active) aria-current="page" @endif
                                class="group flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus lg:justify-center xl:justify-start
                                    {{ $active
                                        ? 'bg-primary-quiet text-primary-text'
                                        : 'text-ink-muted hover:bg-surface-muted hover:text-ink' }}"
                            >
                                <x-icon :name="$item['icon']" size="md" />

                                {{-- One label element, not two.

                                     Three widths, three jobs. Below lg the sidebar
                                     is a drawer with room for words, so the label
                                     is painted. Between lg and xl it is the 80px
                                     icon rail, where a painted label would be
                                     clipped, so it becomes screen reader only and
                                     the title attribute names it for a pointer.
                                     At xl the sidebar expands and the label paints
                                     again.

                                     sr-only rather than hidden, deliberately. A
                                     hidden element is removed from the
                                     accessibility tree as well as from the
                                     screen, which would leave the rail's links
                                     with nothing but a title. And not two
                                     elements either: a second copy of the same
                                     words is read out twice in the drawer, which
                                     is worse than one label that changes shape.

                                     Writing this as "hidden xl:inline" got it
                                     wrong, and the mistake was invisible in the
                                     source: the drawer only exists below lg, so
                                     it never reaches xl, and every item rendered
                                     as a bare icon. --}}
                                <span class="lg:sr-only xl:not-sr-only">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
@endif
