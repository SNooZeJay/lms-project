<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'IT Learning Hub, a practical academic learning management system for BSIT students in the Philippines.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/touch-icon.png') }}">

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
<body class="min-h-screen overflow-x-hidden bg-canvas font-sans text-ink antialiased">
    <a
        href="#main-content"
        class="fixed left-4 top-4 z-50 -translate-y-24 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-transform focus:translate-y-0"
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
                            class="absolute right-0 z-40 mt-2 w-56 origin-top-right rounded-lg border border-line bg-surface p-1.5 shadow-lg"
                        >
                            <a
                                href="{{ route('courses.index') }}"
                                data-dropdown-item
                                @if (request()->routeIs('courses.*')) aria-current="page" @endif
                                class="flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus {{ request()->routeIs('courses.*') ? 'bg-primary-quiet text-primary-text' : '' }}"
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
                                    class="flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
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

        <footer class="border-t border-line bg-surface">
            <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <x-logo size="sm" />
                        <p class="mt-4 max-w-xs text-sm leading-6 text-ink-muted">
                            Courses in information technology, programming, web development,
                            and cybersecurity.
                        </p>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-ink">Learn</h2>
                        <ul role="list" class="mt-3 space-y-1 text-sm">
                            <li><a href="{{ route('courses.index') }}" class="link-quiet text-ink-muted hover:text-ink">Course catalog</a></li>
                            @auth
                                <li><a href="{{ route('student.courses.index') }}" class="link-quiet text-ink-muted hover:text-ink">My courses</a></li>
                            @endauth
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-ink">Account</h2>
                        <ul role="list" class="mt-3 space-y-1 text-sm">
                            @auth
                                <li><a href="{{ route('account.profile') }}" class="link-quiet text-ink-muted hover:text-ink">Your profile</a></li>
                                <li><a href="{{ route('account.password') }}" class="link-quiet text-ink-muted hover:text-ink">Password</a></li>
                            @else
                                <li><a href="{{ route('login') }}" class="link-quiet text-ink-muted hover:text-ink">Sign in</a></li>
                            @endauth
                        </ul>
                    </div>

                    {{-- The terms the sign up and sign in pages link to. A page that
                         is linked from somewhere has to exist, and these are the
                         only two the site publishes. --}}
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Legal</h2>
                        <ul role="list" class="mt-3 space-y-1 text-sm">
                            <li><a href="{{ route('legal.terms') }}" class="link-quiet text-ink-muted hover:text-ink">Terms of use</a></li>
                            <li><a href="{{ route('legal.privacy') }}" class="link-quiet text-ink-muted hover:text-ink">Privacy</a></li>
                        </ul>
                    </div>
                </div>

                <p class="mt-8 border-t border-line pt-6 text-sm leading-6 text-ink-muted">
                    &copy; {{ now()->year }} {{ config('app.name') }}
                </p>
            </div>
        </footer>
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif

    {{-- Page scripts. A view that pushes here is rendered only if the stack
         exists, so the placeholder is required, not optional. --}}
    @stack('scripts')
</body>
</html>
