<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Foundation for a BSIT academic learning management system.">
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
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <a
        href="#main-content"
        class="fixed left-4 top-4 z-50 -translate-y-24 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-transform focus:translate-y-0"
    >
        Skip to main content
    </a>

    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-30 border-b border-line bg-canvas">
            <div class="mx-auto flex min-h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex min-h-11 items-center rounded-md" aria-label="BSIT Academic LMS home">
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold text-ink">BSIT Academic LMS</span>
                        <span class="block text-xs text-ink-muted">Academic learning foundation</span>
                    </span>
                </a>

                <x-theme-toggle />
            </div>
        </header>

        <main id="main-content" class="flex-1" tabindex="-1">
            @yield('content')
        </main>

        <footer class="border-t border-line bg-surface">
            <div class="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-ink-muted sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <p>BSIT Academic LMS</p>
                <p>Phase 1 foundation. Student and payment workflows are not enabled.</p>
            </div>
        </footer>
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif
</body>
</html>
