<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Sign in to IT Learning Hub.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>

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

    {{--
        The authentication shell.

        One split: the product on one side, a single narrow form on the other.
        The form stays narrow on purpose. These pages have one job, and a wide
        form makes a two field sign in look like a page that should have more on
        it.

        The brand panel is dropped below the large breakpoint and the form takes
        the full width, so on a phone there is nothing between the visitor and
        the fields. The product name stays in the mobile header so the page is
        still identified.
    --}}
    <div class="auth-shell">
        <aside class="auth-aside" aria-labelledby="auth-brand-heading">
            <x-logo size="lg" name-class="text-white" />

            <div class="max-w-md">
                <h1 id="auth-brand-heading" class="text-3xl leading-tight font-[650] tracking-tight text-balance text-white xl:text-4xl">
                    One place for enrollment, lessons, and results.
                </h1>

                <p class="mt-5 leading-7 text-white/85">
                    Courses, quizzes, and certificates stay tied to the same
                    records, so a student can see where they stand without asking.
                </p>
            </div>

            <p class="text-sm text-white/70">
                BSIT academic project · Laravel and Blade
            </p>
        </aside>

        <main id="main-content" class="auth-main" tabindex="-1">
            <div class="auth-card">
                <div class="auth-main-top">
                    <a
                        href="{{ route('home') }}"
                        class="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-semibold text-primary-text transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                    >
                        <x-icon name="arrow-left" size="sm" />
                        Back
                    </a>

                    <p class="auth-switch">
                        @yield('auth-switch')
                    </p>
                </div>

                <div class="mb-6 lg:hidden">
                    <x-logo size="sm" />
                </div>

                @yield('auth-content')
            </div>
        </main>
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif

    @stack('scripts')
</body>
</html>
