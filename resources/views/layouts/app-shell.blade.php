<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Your IT Learning Hub workspace.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

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
{{-- overflow-x-clip, not overflow-x-hidden. Both stop content pushing the page
     sideways, but a non-visible overflow on an axis makes that element a scroll
     container, and overflow-y then computes to auto. On html and body that
     turns the document into the sidebar's scroll container, so its
     position: sticky anchors to an element that never scrolls and the sidebar
     rides away with the content. Measured: the sidebar moved 1095px on a 1200px
     scroll. clip does not create a scroll container, so sticky keeps working. --}}
<body class="min-h-screen overflow-x-clip bg-canvas font-sans text-ink antialiased">
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

            {{-- No footer control here any more. The theme switch used to sit
                 at the bottom of the sidebar, and it also sat in the drawer and
                 in the topbar, so on a phone the same switch appeared twice on
                 one screen and on a desktop it was duplicated with the topbar
                 once that gained one. It is in the topbar at every width now,
                 where the reference puts it, and this column ends with the
                 navigation so there is nothing left to fill it with. --}}
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
            </div>
        </div>

        {{-- The page column. It is marked so an open drawer can make the content
             behind it inert and a keyboard user cannot tab into it. --}}
        <div data-workspace-column class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur-sm" data-print="hide">
                <div class="flex min-h-16 items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:px-8">
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
                        {{-- The name is dropped on the narrowest phones.

                             This has to be max-[399px]:hidden rather than
                             min-[400px]:inline. The logo component's own name
                             element carries display:block, and a plain width
                             variant cannot switch that off: both are display
                             utilities of equal weight, so which one wins is
                             decided by their order in the stylesheet and not by
                             the order they appear in the attribute. The
                             measurement was the brand name rendering at 320px,
                             clipped to "IT Learning...", which is the exact
                             outcome the rule exists to prevent. A max-width
                             variant is emitted inside a media query after the
                             base utilities, so it wins. --}}
                        <x-logo size="sm" name-class="max-[399px]:hidden" />
                    </a>

                    {{-- The trail, in the topbar's left slot.

                         This replaces a flat string that said "Operations
                         workspace" and went nowhere. The same words are still
                         here, as the first crumb, and they now lead to the
                         dashboard instead of sitting there.

                         It is hidden on a phone, where the drawer button, the
                         brand and the four controls on the right already fill
                         the bar. The page has its own h1 immediately below, so
                         nothing is lost by not repeating the name up here. --}}
                    <div class="hidden min-w-0 flex-1 lg:block">
                        <x-breadcrumbs :items="\App\Support\Navigation::trail(auth()->user())" />
                    </div>

                    {{-- The control cluster, in the reference order: search,
                         notifications, messages, theme, account. Each is real.
                         The theme control used to sit in the sidebar footer and
                         in the drawer, so on a phone the same switch appeared
                         twice on one screen; it lives here now, at every width. --}}
                    <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                        <x-app.topbar-search />

                        <x-app.notification-bell
                            :unread="$unreadNotifications ?? 0"
                            :recent="$recentNotifications ?? []"
                        />

                        <x-app.message-button
                            :unread="$unreadMessages ?? 0"
                            :threads="$recentConversations ?? []"
                            :unread-threads="$unreadThreads ?? []"
                        />

                        <x-app.user-menu :user="auth()->user()" id="workspace-account-menu" />

                        {{-- Last, so it sits against the account button the way
                             the reference has it. --}}
                        <x-theme-toggle />
                    </div>
                </div>
            </header>

            {{--
                The page frame belongs to the shell.

                It used to belong to each of the twenty six views that use this
                layout and had a frame, and they had drifted. The same container
                was written out twenty six times with six different maximum
                widths, and seven more views had no wrapper at all, so their
                content sat against the edge of the window with no padding at all.
                Measured across the signed in pages, the content edge landed at
                five different places: 0, 24, 32, 41 and 64 pixels.

                A page whose left margin depends on which file drew it is not a
                design, and it cannot be corrected by editing those files one at a
                time, because the fault is that every one of them is allowed an
                opinion. So the frame is written once, here.

                The measure is the one thing a view still chooses, because the
                width of a page is a property of its content: a reading page wants
                a narrow column and a table wants the full one. Three named
                measures, declared with @section('measure', 'narrow'), so that "the
                same width as the reading pages" is sayable and two pages which
                should match do match. `wide` is the default, because most pages
                here are a table or a grid, and a 672 pixel column of a data table
                is a table nobody can read.
            --}}
            <main id="main-content" class="min-w-0 flex-1" tabindex="-1">
                <div class="page page-@yield('measure', 'wide') page-body">
                    @yield('content')
                </div>
            </main>
            {{-- The compact band. The workspace sidebar already carries every
                 destination, so repeating those columns here would make the
                 footer a second, worse navigation. What the workspace still
                 needs is the brand, the legal terms, and the copyright.
                 Hidden on paper, because a certificate and a receipt are the
                 only pages meant to be printed. --}}
            <x-footer variant="workspace" hide-on-print />
        </div>
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif

    {{-- Page scripts. A view that pushes here is rendered only if the stack
         exists, so the placeholder is required, not optional. --}}
    @stack('scripts')

<x-toast-region />


</body>
</html>
