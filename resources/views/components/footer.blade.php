@props([
    /*
     | Which footer this is.
     |
     | "site" is the public pages: the full set of columns, because a visitor
     | who has not signed in has no other way to reach the catalog, their
     | account, or the terms.
     |
     | "workspace" is the signed in shell: a single compact band. Everything in
     | the columns is already in the sidebar, and repeating it there would make
     | the footer a second, worse navigation. What remains is the brand, the
     | legal terms, and the copyright.
     */
    'variant' => 'site',
    'hideOnPrint' => false,
])

@php
    $site = $variant === 'site';

    /*
     | The link groups, in the order they read.
     |
     | A group that would hold a single link is dropped rather than padded out,
     | and the whole row is auth aware, so a signed out visitor is never sent to
     | a page that redirects them straight back here.
     */
    $groups = array_values(array_filter([
        [
            'label' => 'Learn',
            'links' => array_values(array_filter([
                ['route' => 'courses.index', 'label' => 'Course catalog'],
                auth()->check() ? ['route' => 'student.courses.index', 'label' => 'My courses'] : null,
            ])),
        ],
        [
            'label' => 'Account',
            'links' => auth()->check()
                ? [
                    ['route' => 'account.profile', 'label' => 'Your profile'],
                    ['route' => 'account.password', 'label' => 'Password'],
                ]
                // Sign in only. Registration is deliberately not offered here:
                // a guest who cannot sign in yet is sent to the sign in page,
                // which is where the account is created. Adding a registration
                // link to a public page is the easiest thing to do by reflex,
                // so the home page test pins it and this footer respects it.
                : [
                    ['route' => 'login', 'label' => 'Sign in'],
                ],
        ],
        [
            'label' => 'Legal',
            // The two pages the sign in and sign up consent line links to. A
            // page that is linked from somewhere has to exist, and these are the
            // only two the site publishes.
            'links' => [
                ['route' => 'legal.terms', 'label' => 'Terms of use'],
                ['route' => 'legal.privacy', 'label' => 'Privacy'],
            ],
        ],
    ], fn (array $group): bool => $group['links'] !== []));

    $year = now()->year;
@endphp

{{--
    The one footer for the whole application.

    It is a component rather than markup repeated in each layout because the
    two footers had already drifted apart: the public one grew four columns
    while the workspace one stayed two lines, and neither matched the section
    heading style the navigation already uses. One component is the only way
    they stay the same footer.

    Every link is a real route and a real page. Nothing here is a placeholder
    and no social link is invented, because a link to an account that does not
    exist is worse than no link at all.
--}}
<footer
    @if ($hideOnPrint) data-print="hide" @endif
    {{ $attributes->merge(['class' => 'border-t border-line bg-surface']) }}
>
    {{-- The public footer earns its height because it carries navigation. The
         workspace footer carries three small things, so it gets a compact band
         instead of the same generous padding, which used to leave a tall mostly
         empty strip under every signed in page. --}}
    <div class="mx-auto w-full max-w-7xl px-4 {{ $site ? 'py-10 sm:py-12' : 'py-6 sm:py-7' }} sm:px-6 lg:px-8">
        @if ($site)
            {{-- Public pages: the brand sits on its own row so the description
                 gets real width instead of a quarter of the page, and the link
                 groups sit beside each other below it. One column on a phone,
                 three from the tablet up.

                 The link area is sized to its own content rather than to a share
                 of the page. The site publishes a handful of public pages, so the
                 groups hold a few short labels between them, and stretching those
                 across half the width leaves them floating in the middle of the
                 page instead of reading as one block. The brand column takes
                 whatever is left, which is what keeps the two apart. --}}
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start lg:gap-16">
                <div class="max-w-sm">
                    <x-logo size="sm" />
                    <p class="mt-4 text-sm leading-6 text-ink-muted">
                        Courses in information technology, programming, web development,
                        and cybersecurity.
                    </p>
                </div>

                <nav aria-label="Footer">
                    <div class="grid gap-8 sm:grid-cols-3 sm:gap-10">
                        @foreach ($groups as $group)
                            <div>
                                {{-- Same label style as the navigation groups, so
                                     the two read as one system. --}}
                                <h2 class="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                    {{ $group['label'] }}
                                </h2>

                                <ul role="list" class="mt-3 space-y-1">
                                    @foreach ($group['links'] as $link)
                                        <li>
                                            <a href="{{ route($link['route']) }}" class="footer-link">{{ $link['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </nav>
            </div>

            <div class="mt-8 border-t border-line pt-6 sm:mt-10">
                <p class="text-xs text-ink-subtle">
                    &copy; {{ $year }} {{ config('app.name') }}
                </p>
            </div>
        @else
            {{-- Workspace: one row, on one baseline.

                 It used to be two rows split by a hairline, with the brand and
                 legal links on the first and the copyright on the second. At
                 full width that left a wide empty band with a line drawn across
                 it, which read as an unfinished layout rather than a quiet
                 footer. Everything now sits on one line that centres, and
                 wraps to two on a narrow screen.

                 The copyright is separated by a rule rather than more gap, so the
                 two halves read as two things instead of one run-on line. --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
                <x-logo size="sm" />

                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-5">
                    <nav aria-label="Legal">
                        <ul role="list" class="flex flex-wrap items-center gap-x-5 gap-y-1">
                            @foreach ($groups[2]['links'] ?? [] as $link)
                                <li>
                                    <a href="{{ route($link['route']) }}" class="footer-link">{{ $link['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>

                    <p class="text-xs text-ink-subtle sm:border-l sm:border-line sm:pl-5">
                        &copy; {{ $year }} {{ config('app.name') }}
                    </p>
                </div>
            </div>
        @endif
    </div>
</footer>
