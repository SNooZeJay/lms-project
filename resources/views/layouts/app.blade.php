<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'IT Learning Hub, an Information Technology learning platform with courses, lessons, quizzes, and certificates.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ filemtime(public_path('favicon.png')) }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/touch-icon.png') }}?v={{ filemtime(public_path('images/brand/touch-icon.png')) }}">

    {{-- The theme is chosen before the first paint so a dark theme never flashes
         light. It is a small inline script, so it carries the request nonce that
         the security headers publish for exactly this purpose. --}}
    <script nonce="{{ $cspNonce ?? '' }}">
        (() => {
            const storageKey = 'lms-theme';
            let storedTheme = null;

            try {
                storedTheme = window.localStorage.getItem(storageKey);
            } catch {
                storedTheme = null;
            }

            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = storedTheme === 'light' || storedTheme === 'dark'
                ? storedTheme
                : (systemPrefersDark ? 'dark' : 'light');

            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.dataset.theme = theme;
        })();
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
{{-- overflow-x-clip rather than overflow-x-hidden, because a non-visible
     overflow makes this element a scroll container and breaks sticky
     descendants. See the note in layouts/app-shell.blade.php. --}}
<body class="min-h-screen overflow-x-clip bg-canvas font-sans text-ink antialiased">
    <a
        href="#main-content"
        class="fixed left-4 top-4 z-50 inline-flex min-h-11 -translate-y-24 items-center rounded-md bg-primary px-4 text-sm font-semibold text-white transition-transform focus:translate-y-0"
    >
        Skip to main content
    </a>

    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur-sm">
            <div class="mx-auto flex min-h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 py-2 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex min-h-11 min-w-0 items-center rounded-md focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus" aria-label="IT Learning Hub home">
                    {{-- The mark with the name as live text, so the name takes the
                         colour of the current theme and stays readable in both. --}}
                    <x-logo size="md" />
                </a>

                <div class="flex shrink-0 items-center gap-2">
                    @auth
                        <x-app.user-menu :user="auth()->user()" id="public-account-menu" />
                    @endauth

                    {{-- The theme sits to the left of the menu, so the menu button
                         is the last control in the row and the edge of the header
                         is where navigation begins. --}}
                    <x-theme-toggle />

                    {{-- The whole navigation is two items, so it lives behind one
                         control instead of a row that has to reflow or scroll. The
                         menu is a real disclosure: Escape closes it, a click
                         outside closes it, and focus returns to the button. --}}
                    <div class="relative" data-dropdown>
                        <button
                            type="button"
                            data-dropdown-trigger
                            aria-expanded="false"
                            aria-controls="public-menu"
                            class="btn btn-secondary btn-sm px-2.5"
                        >
                            <x-icon name="menu" size="md" />
                            <span class="sr-only">Menu</span>
                        </button>

                        <div
                            id="public-menu"
                            data-dropdown-panel
                            hidden
                            {{-- The panel is sized to its contents rather than to a
                                 fixed width. It holds one or two short labels, and a
                                 224 pixel box around them reads as a long empty
                                 panel. The floor keeps it from becoming a sliver
                                 and the ceiling stops a longer label, such as one
                                 added later, from stretching it across the screen. --}}
                            class="absolute right-0 z-40 mt-2 w-max min-w-44 max-w-64 origin-top-right rounded-lg border border-line bg-surface p-1.5 shadow-lg"
                        >
                            <a
                                href="{{ route('courses.index') }}"
                                data-dropdown-item
                                @if (request()->routeIs('courses.*')) aria-current="page" @endif
                                class="flex min-h-10 items-center gap-2.5 rounded-md px-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus {{ request()->routeIs('courses.*') ? 'bg-primary-quiet text-primary-text' : '' }}"
                            >
                                <x-icon name="book-open" size="sm" class="text-ink-subtle" />
                                Courses
                            </a>

                            @guest
                                {{-- Registration is reached from the sign in page, not
                                     from every page header. --}}
                                <a
                                    href="{{ route('login') }}"
                                    data-dropdown-item
                                    class="flex min-h-10 items-center gap-2.5 rounded-md px-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                >
                                    <x-icon name="lock" size="sm" class="text-ink-subtle" />
                                    Sign in
                                </a>
                            @endguest
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="min-w-0 flex-1" tabindex="-1">
            @yield('content')
        </main>

        {{-- The one footer for the whole site. The public pages get the full
             column set; the workspace uses the compact band. --}}
        <x-footer variant="site" />
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif

    {{-- Page scripts. A view that pushes here is rendered only if the stack
         exists, so the placeholder is required, not optional. --}}
    @stack('scripts')
</body>
</html>
