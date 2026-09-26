<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Your IT Learning Hub workspace.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

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

    {{--
        The authenticated application shell.

        Every signed in role uses this one shell. Only the navigation and the page
        content change by role, so a Student, an Instructor, and an Administrator
        learn the same layout once.

        Desktop: a persistent sidebar beside the content.
        Tablet: the same sidebar collapses to an icon rail, and every icon keeps
        an accessible name and a tooltip so nothing is lost.
        Mobile: the sidebar becomes an off canvas drawer.

        The drawer and the account menu are progressive enhancements over real
        HTML. Every control is a button or a link with an accessible name, Escape
        closes an open layer, focus returns to the control that opened it, and the
        page behind an open drawer does not scroll. All of that lives in
        `resources/js/app.js`, which needs no framework and no inline script.
    --}}
    <div class="min-h-screen lg:flex">
        <aside
            data-workspace-sidebar
            class="hidden shrink-0 border-r border-line bg-surface lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-20 lg:flex-col xl:w-72"
            data-print="hide"
        >
            <div class="flex h-16 shrink-0 items-center border-b border-line px-3 lg:justify-center xl:justify-start xl:px-4">
                <a href="{{ route('home') }}" class="flex min-h-11 min-w-0 items-center rounded-md focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus" aria-label="IT Learning Hub home">
                    <x-logo size="sm" name-class="hidden xl:inline" />
                </a>
            </div>

            <x-app.nav :user="auth()->user()" id="workspace-nav-desktop" class="flex-1" />

            <div class="shrink-0 border-t border-line p-3 lg:px-2 xl:p-4">
                <x-theme-toggle class="lg:mx-auto xl:w-full xl:justify-center" />
            </div>
        </aside>

        {{-- The mobile drawer. It stays hidden until the menu button opens it,
             and the backdrop is what a click outside hits. --}}
        <div data-drawer class="lg:hidden" data-print="hide">
            <div data-drawer-backdrop hidden class="fixed inset-0 z-40 bg-slate-900/50"></div>

            <div
                data-drawer-panel
                id="workspace-drawer"
                hidden
                role="dialog"
                aria-modal="true"
                aria-label="Workspace navigation"
                class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col border-r border-line bg-surface shadow-xl"
            >
                <div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-line px-4">
                    <x-logo size="sm" />
                    <button type="button" data-drawer-close class="btn btn-quiet btn-sm px-2.5">
                        <x-icon name="close" size="md" />
                        <span class="sr-only">Close navigation</span>
                    </button>
                </div>

                <x-app.nav :user="auth()->user()" id="workspace-nav-drawer" class="flex-1" />

                <div class="shrink-0 border-t border-line p-3">
                    <x-theme-toggle class="w-full justify-center" />
                </div>
            </div>
        </div>

        {{-- The page column. It is marked so an open drawer can make the content
             behind it inert and a keyboard user cannot tab into it. --}}
        <div data-workspace-column class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur-sm" data-print="hide">
                <div class="flex min-h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        data-drawer-open
                        aria-controls="workspace-drawer"
                        aria-expanded="false"
                        class="btn btn-secondary btn-sm px-2.5 lg:hidden"
                    >
                        <x-icon name="menu" size="md" />
                        <span class="sr-only">Open navigation</span>
                    </button>

                    <a href="{{ route('home') }}" class="flex min-h-11 min-w-0 items-center rounded-md focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus lg:hidden" aria-label="IT Learning Hub home">
                        <x-logo size="sm" />
                    </a>

                    <p class="hidden min-w-0 flex-1 truncate text-sm text-ink-muted lg:block">
                        @yield('workspace-context', 'IT Learning Hub')
                    </p>

                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <x-theme-toggle class="lg:hidden" />
                        <x-app.user-menu :user="auth()->user()" id="workspace-account-menu" />
                    </div>
                </div>
            </header>

            <main id="main-content" class="min-w-0 flex-1" tabindex="-1">
                @yield('content')
            </main>

            <footer class="border-t border-line bg-surface" data-print="hide">
                <div class="mx-auto flex w-full max-w-7xl flex-col gap-1 px-4 py-6 text-sm text-ink-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <p>{{ config('app.name') }}</p>
                    <p>BSIT academic project</p>
                </div>
            </footer>
        </div>
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif

    {{-- Page scripts. A view that pushes here is rendered only if the stack
         exists, so the placeholder is required, not optional. --}}
    @stack('scripts')
</body>
</html>
