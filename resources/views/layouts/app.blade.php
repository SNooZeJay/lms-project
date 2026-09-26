<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'IT Learning Hub, a practical academic learning management system for BSIT students in the Philippines.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

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
            <div class="mx-auto flex min-h-16 w-full max-w-7xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 sm:px-6 sm:py-0 lg:px-8">
                <a href="{{ route('home') }}" class="flex min-h-11 min-w-0 items-center rounded-md focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus" aria-label="IT Learning Hub home">
                    <x-logo size="md" tagline="Academic learning platform" />
                </a>

                <div class="flex shrink-0 items-center gap-2">
                    <nav aria-label="Main" class="hidden md:block">
                        <ul role="list" class="flex items-center gap-1">
                            <li>
                                <a
                                    href="{{ route('courses.index') }}"
                                    @if (request()->routeIs('courses.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center rounded-md px-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus {{ request()->routeIs('courses.*') ? 'bg-primary-quiet text-primary-text' : 'text-ink-muted hover:bg-surface-muted hover:text-ink' }}"
                                >Courses</a>
                            </li>
                        </ul>
                    </nav>

                    @auth
                        <x-app.user-menu :user="auth()->user()" id="public-account-menu" />
                    @else
                        {{-- Registration is reached from the sign in page, not from
                             every page header. A public header should offer the
                             one action a returning visitor wants. --}}
                        <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Sign in</a>
                    @endauth

                    <x-theme-toggle />
                </div>
            </div>

            {{-- On a narrow screen the public header keeps only the brand and the
                 theme, and the navigation and account actions move into a
                 drawer, so no row of controls can push the page sideways. --}}
            <div class="border-t border-line px-4 py-2 md:hidden">
                <nav aria-label="Main">
                    <ul role="list" class="flex items-center gap-1 overflow-x-auto scrollbar-none">
                        <li>
                            <a href="{{ route('courses.index') }}" class="flex min-h-11 items-center whitespace-nowrap rounded-md px-3 text-sm font-semibold text-ink-muted transition-colors hover:bg-surface-muted hover:text-ink">Courses</a>
                        </li>
                        @guest
                            <li>
                                <a href="{{ route('login') }}" class="flex min-h-11 items-center whitespace-nowrap rounded-md px-3 text-sm font-semibold text-ink-muted transition-colors hover:bg-surface-muted hover:text-ink">Sign in</a>
                            </li>
                        @endguest
                    </ul>
                </nav>
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
                            An academic learning platform for BSIT students, built with Laravel.
                        </p>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-ink">Learn</h2>
                        <ul role="list" class="mt-3 space-y-1 text-sm">
                            <li><a href="{{ route('courses.index') }}" class="link-quiet text-ink-muted hover:text-ink">Course catalog</a></li>
                            <li><a href="{{ route('login') }}" class="link-quiet text-ink-muted hover:text-ink">Sign in</a></li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-ink">Account</h2>
                        <ul role="list" class="mt-3 space-y-1 text-sm">
                            @auth
                                <li><a href="{{ route('account.profile') }}" class="link-quiet text-ink-muted hover:text-ink">Your profile</a></li>
                                <li><a href="{{ route('account.password') }}" class="link-quiet text-ink-muted hover:text-ink">Password</a></li>
                            @else
                                <li><span class="text-ink-muted">Sign in to manage your account</span></li>
                            @endauth
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold text-ink">Payments</h2>
                        <p class="mt-3 text-sm leading-6 text-ink-muted">
                            Paid courses are charged once and confirmed by the provider.
                        </p>
                    </div>
                </div>

                <p class="mt-8 border-t border-line pt-6 text-sm leading-6 text-ink-muted">
                    IT Learning Hub. Built as a student academic project.
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
