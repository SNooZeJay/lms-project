@props([
    'user',
    'id' => 'workspace-nav',
])

@php
    $groups = \App\Support\Navigation::for($user);
    $current = \App\Support\Navigation::activeRoute();

    $isCurrent = static function (array $item) use ($current): bool {
        return in_array($current, $item['matches'], true);
    };
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
                        @php $active = $isCurrent($item); @endphp
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
                                <span class="hidden xl:inline">{{ $item['label'] }}</span>
                                <span class="sr-only xl:hidden">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
@endif
