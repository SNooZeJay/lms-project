<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="IT Learning Hub, a practical academic learning management system for BSIT students.">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <script>
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
        <header class="sticky top-0 z-30 border-b border-line bg-canvas">
            <div class="mx-auto flex min-h-16 w-full max-w-7xl flex-wrap items-center justify-between gap-x-3 gap-y-2 px-4 py-2 sm:flex-nowrap sm:py-0 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex min-w-0 min-h-11 items-center rounded-md" aria-label="IT Learning Hub home">
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-sm font-semibold text-ink">IT Learning Hub</span>
                        <span class="hidden truncate text-xs text-ink-muted sm:block">Academic learning platform</span>
                    </span>
                </a>

                <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                    <a href="{{ route('courses.index') }}" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-md border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus sm:px-3 sm:py-2 sm:text-sm">Courses</a>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-md border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus sm:px-3 sm:py-2 sm:text-sm">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-md border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus sm:px-3 sm:py-2 sm:text-sm">Sign in</a>
                    @endauth
                    <x-theme-toggle />
                </div>
            </div>
        </header>

        <main id="main-content" class="min-w-0 flex-1" tabindex="-1">
            @yield('content')
        </main>

        <footer class="border-t border-line bg-surface">
            <div class="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-ink-muted sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <p>IT Learning Hub</p>
                <p>Enrollment, lesson reading, progress, quizzes, certificates, and course payments are live. A test payment uses the provider's test mode and moves no real money.</p>
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
