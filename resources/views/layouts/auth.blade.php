<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Sign in to IT Learning Hub to reach your courses and lessons.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>

    {{-- The icon carries a modification stamp, because a browser that has
         already cached the old file would otherwise keep showing it and there
         would be no way to tell a stale tab from a current one. --}}
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

    {{--
        The authentication shell.

        One split: the product on one side, one narrow form on the other. A
        person arriving here has one job, so the form stays narrow, the copy stays
        short, and nothing on the page explains how the application works
        internally.

        Below the large breakpoint the brand panel is dropped and the form takes
        the full width, so on a phone there is nothing between the visitor and the
        fields. The product name stays in the mobile header so the page is still
        identified.
    --}}
    <div class="auth-shell">
        <aside class="auth-aside" aria-labelledby="auth-message">
            <x-logo size="md" name-class="text-white" class="relative z-10" />

    {{--
        The rotating line.

        Three short sentences about the product, typed one after another. They are
        kept to facts the application can actually back: the catalog carries free
        courses, progress is stored against the enrollment, and a certificate is
        issued on completion.

        The visible line is hidden from assistive technology, because rewriting it
        every few dozen milliseconds would read out as noise. The list below it
        carries all three sentences in order, so the same information is available
        without the animation.
    --}}
    @php
        $panelSentences = [
            'Free courses are open to anyone. You pay only for the courses you choose to take.',
            'Your progress is saved, so you can stop and come back to the same lesson.',
            'Finish every lesson in a course and a certificate is issued in your name.',
        ];
    @endphp

    {{--
        The minimum height is what keeps the panel still while the line changes.

        The column is a fixed 440 pixels and the type scales with the viewport, so
        the number of lines a sentence takes is the same at every screen size: the
        longest sentence wraps to four. Reserving exactly four lines means a short
        sentence can never shorten the block and pull the message below it upward,
        which is the shift that would otherwise happen on every swap.

        The figure follows the type, so it stays correct as the type scales: four
        lines at a line height of 1.15 is 4.6 times the font size.
    --}}
    <p
        data-rotate-sentences
        data-sentences="{{ implode('||', $panelSentences) }}"
        aria-hidden="true"
        class="relative z-10 my-auto max-w-[440px] min-h-[calc(4.6*clamp(28px,3vw,38px))] text-[clamp(28px,3vw,38px)] leading-[1.15] font-bold tracking-[-0.025em] text-white"
    >{{ $panelSentences[0] }}</p>

    <ul class="sr-only">
        @foreach ($panelSentences as $sentence)
            <li>{{ $sentence }}</li>
        @endforeach
    </ul>

    {{-- The message sits low in the panel, the way a product line sits under a
         brand. The automatic margin on the line above shares the leftover height,
         so the blocks stay spread instead of leaving a gap under the logo.

         The type sizes are the reference values, with one deliberate change. The
         reference has a single large line, and this panel now has two things
         worth saying at once: the rotating line above and this one. Running both
         at the reference heading size leaves the eye with no single place to
         land, so the rotating line keeps the heading size as the focal
         statement and this one steps down to a size that is still prominent but
         clearly secondary.

         This is a paragraph, not a heading, and that is deliberate. The form
         card carries its own h1, and the project standard is exactly one h1 per
         page, so a marketing line in the decorative panel must not claim to be
         the page heading. A screen reader opening the sign in page would
         otherwise announce the slogan before telling the person what the page
         is for. The visual treatment is unchanged: only the element differs, so
         the panel still reads as the focal statement it was designed to be. --}}
    <div class="relative z-10 max-w-[440px]">
        <p
            id="auth-message"
            class="mb-3.5 text-[24px] leading-[1.2] font-semibold tracking-[-0.015em] text-balance text-white"
        >
            Learn IT. Build practical skills.
        </p>

        <p class="text-[14.5px] leading-[1.6] text-white/85">
            Structured courses with lessons, quizzes, and a certificate at
            the end.
        </p>
    </div>

    {{-- The copyright sits at the foot of the brand panel, where the reference
         design puts it. It names the product and nothing else: no framework, no
         course, no build location.

         The opacity is higher and the size is larger than the reference uses. At
         the reference's 55 percent this measures 2.77 to 1 against the panel,
         which fails the AA minimum of 4.5 to 1 for text this small, and the
         panel is now a gradient rather than a flat fill. At 90 percent it
         measures 4.76 to 1. Ten pixels is also below the smallest size this
         project sets anywhere else, so the caption is one step up at 12. --}}
    <p class="relative z-10 mt-6 pt-6 text-[12px] tracking-[0.06em] text-white/90">
        &copy; {{ date('Y') }} IT Learning Hub
    </p>
</aside>

        <main id="main-content" class="auth-main" tabindex="-1">
            {{-- The top row is a direct child of the column, not part of the form
                 block, so the way back sits at the left edge of the column and the
                 link to the other form sits at the right edge. This is the
                 reference arrangement, and constraining it to the width of the
                 form would pull both links into the middle instead. --}}
            <div class="auth-main-top">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-semibold text-ink-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                >
                    <x-icon name="arrow-left" size="sm" />
                    Back to home
                </a>

                <p class="auth-switch">
                    @yield('auth-switch')
                </p>
            </div>

            {{-- The form block. The automatic top and bottom margins centre it in
                 the space the top row and the consent line leave over, which is
                 the comfortable middle position. It must not grow to fill the
                 column: doing so cancels the centring and sends the whole form to
                 the top edge.

                 Automatic margins also behave correctly on a short screen. Centring
                 on its own clips the top of an over tall form where it can no
                 longer be scrolled to, which on a phone would hide the first
                 field. --}}
            <div class="mx-auto my-auto w-full max-w-[25rem]">
                {{-- The brand stays visible on a phone, where the panel is gone. --}}
                <div class="mb-8 lg:hidden">
                    <x-logo size="md" />
                </div>

                @yield('auth-content')
            </div>

            {{-- The consent line closes the column rather than the form, so it
                 reads as a condition of using the site instead of another field.
                 It shares the width of the form so the two line up. --}}
            <div class="mx-auto w-full max-w-[25rem] text-center">
                @yield('auth-consent')
            </div>
        </main>
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif

    @stack('scripts')
</body>
</html>
