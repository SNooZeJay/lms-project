@extends('layouts.app-shell')

@section('title', 'Administrator dashboard')
@section('workspace-context', 'Operations workspace')

@section('content')
@section('measure', 'wide')
            <x-page-header
            eyebrow="Administrator workspace"
            :title="'Welcome back, '.$user->name"
            description="Your Administrator access is active. User management, certificates, reports, and audit tools are available below."
        >
            <x-slot:actions>
                <x-btn :href="route('admin.reports.index')" variant="secondary" size="md">
                    <x-icon name="chart" size="sm" />
                    Reports
                </x-btn>
                <x-btn :href="route('admin.users.index')" variant="primary" size="md">
                    <x-icon name="users" size="sm" />
                    Manage users
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        {{-- The operational summary. Four columns on a wide screen, two on a
             tablet, one on a phone. Every label names what is counted. --}}
        <section class="mt-8" aria-labelledby="administrator-summary-heading">
            <h2 id="administrator-summary-heading" class="sr-only">System totals</h2>

            {{-- Six tiles, down from eight, and nothing is lost: the figures that
                 used to take a tile each are folded into the hint of the tile
                 they belong with, so "8 courses" also says how many are live.
                 A dashboard that shows ten numbers shows none of them clearly. --}}
            <dl role="list" class="figures-grid" data-motion="stagger">
                {{-- The account total is the one figure an administrator opens
                     this page to see, so it takes the wide slot and the rest sit
                     in the grid beside it. The two that lead somewhere stay
                     links, because a figure a reader can act on is worth more
                     than a figure they can only look at. --}}
                <div class="stat-featured figures-grid-span" data-motion="box">
                    <p class="text-sm leading-5 font-semibold text-ink">Users</p>
                    <p class="stat-featured-value">{{ $stats['users'] }}</p>
                    <p class="mt-2 text-sm leading-5 text-ink-muted">
                        {{ $stats['instructors'] }} {{ Str::plural('instructor', $stats['instructors']) }},
                        {{ $stats['administrators'] }} {{ Str::plural('administrator', $stats['administrators']) }}
                    </p>
                    <a
                        href="{{ route('admin.users.index') }}"
                        class="mt-4 inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-primary-text hover:underline focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                    >
                        Manage users
                        <x-icon name="chevron-right" size="sm" />
                    </a>
                </div>

                <div class="stat-compact" data-motion="box">
                    <dt class="text-sm leading-5 text-ink-muted">Students</dt>
                    <dd class="stat-compact-value">{{ $stats['students'] }}</dd>
                    <p class="mt-1 text-xs leading-4 text-ink-subtle">Enrolled accounts</p>
                </div>

                <div class="stat-compact" data-motion="box">
                    <dt class="text-sm leading-5 text-ink-muted">Courses</dt>
                    <dd class="stat-compact-value">{{ $stats['courses'] }}</dd>
                    <p class="mt-1 text-xs leading-4 text-ink-subtle">{{ $stats['published_courses'] }} published</p>
                </div>

                <div class="stat-compact" data-motion="box">
                    <dt class="text-sm leading-5 text-ink-muted">Enrollments in progress</dt>
                    <dd class="stat-compact-value">{{ $stats['active_enrollments'] }}</dd>
                    <p class="mt-1 text-xs leading-4 text-ink-subtle">Of {{ $stats['enrollments'] }} in every state</p>
                </div>

                <div class="stat-compact" data-motion="box">
                    <dt class="text-sm leading-5 text-ink-muted">Certificates issued</dt>
                    <dd class="stat-compact-value">{{ $stats['certificates'] }}</dd>
                    <p class="mt-1 text-xs leading-4 text-ink-subtle">Issued and valid</p>
                </div>

                <div class="stat-compact" data-motion="box">
                    <dt class="text-sm leading-5 text-ink-muted">Paid payments</dt>
                    <dd class="stat-compact-value">{{ $stats['paid_payments'] }}</dd>
                    <p class="mt-1 text-xs leading-4 text-ink-subtle">Confirmed by the provider</p>
                </div>
            </dl>
        </section>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-3">
            {{-- The one chart. It answers "what is happening across the LMS",
                 which eight separate totals cannot, and it is one grouped query
                 rather than four counts. The labels are the words an
                 Administrator would use, not the stored state names. --}}
            <x-bar-chart
                class="lg:col-span-2"
                heading="Enrollments by state"
                description="Every enrollment on the system, grouped by where it has reached."
                :rows="$enrollmentRows"
                empty="No enrollments have been created yet."
            />

            <x-activity-agenda
                heading="Latest changes"
                description="What has changed across the system."
                :entries="$agenda"
                empty="Nothing has happened on the system yet."
            />
        </div>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-2">
            {{-- Recent students. --}}
            <section class="card" aria-labelledby="administrator-users-heading">
                <div class="card-header">
                    <div>
                        <h2 id="administrator-users-heading" class="text-base font-semibold text-ink">Recent accounts</h2>
                        <p class="mt-1 text-sm text-ink-muted">The newest registrations.</p>
                    </div>
                    <x-btn :href="route('admin.users.index')" variant="quiet" size="sm">
                        Manage users
                        <x-icon name="chevron-right" size="sm" />
                    </x-btn>
                </div>

                @if ($recentUsers->isEmpty())
                    <p class="card-body text-sm leading-6 text-ink-muted">No account has been registered yet.</p>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($recentUsers as $listedUser)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $listedUser->name }}</p>
                                    <p class="mt-0.5 truncate text-sm text-ink-muted">{{ $listedUser->email }}</p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <x-status
                                        :value="$listedUser->profile?->role?->value"
                                        :label="\App\Support\StatusLabel::words($listedUser->profile?->role?->value)"
                                        tone="primary"
                                    />
                                    <x-status :value="$listedUser->profile?->account_status?->value" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Recent enrollments. --}}
            <section class="card" aria-labelledby="administrator-enrollments-heading">
                <div class="card-header">
                    <div>
                        <h2 id="administrator-enrollments-heading" class="text-base font-semibold text-ink">
                            Recent enrollments
                        </h2>
                        <p class="mt-1 text-sm text-ink-muted">Newest first, with the stored state.</p>
                    </div>
                    <x-btn :href="route('admin.reports.index')" variant="quiet" size="sm">
                        Full report
                        <x-icon name="chevron-right" size="sm" />
                    </x-btn>
                </div>

                @if ($recentEnrollments->isEmpty())
                    <p class="card-body text-sm leading-6 text-ink-muted">No enrollment has been recorded yet.</p>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($recentEnrollments as $enrollment)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">
                                        {{ $enrollment->student?->name ?? 'Unknown student' }}
                                    </p>
                                    <p class="mt-0.5 truncate text-sm text-ink-muted">{{ $enrollment->course?->title ?? 'Unknown course' }}</p>
                                </div>
                                <x-status :value="$enrollment->status->value" class="shrink-0" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Recent payments. --}}
            <section class="card" aria-labelledby="administrator-payments-heading">
                <div class="card-header">
                    <h2 id="administrator-payments-heading" class="text-base font-semibold text-ink">Recent payments</h2>
                </div>

                @if ($recentPayments->isEmpty())
                    <p class="card-body text-sm leading-6 text-ink-muted">
                        No payment attempt has been recorded yet.
                    </p>
                @else
                    <ul role="list" class="divide-y divide-line">
                        @foreach ($recentPayments as $payment)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">
                                        {{ $payment->student?->name ?? 'Unknown student' }}
                                    </p>
                                    <p class="mt-0.5 truncate font-mono text-xs text-ink-muted">
                                        {{ $payment->reference ?? 'No reference yet' }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-amount :minor="$payment->amount_minor" :currency="$payment->currency" class="text-sm font-semibold" />
                                    <x-status :value="$payment->status->value" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Course status. --}}
            <section class="card" aria-labelledby="administrator-course-status-heading">
                <div class="card-header">
                    <div>
                        <h2 id="administrator-course-status-heading" class="text-base font-semibold text-ink">
                            Course status
                        </h2>
                        <p class="mt-1 text-sm text-ink-muted">How many courses hold each state.</p>
                    </div>
                </div>

                <dl class="card-body space-y-3">
                    @foreach (\App\Support\StatusLabel::options(\App\Enums\CourseStatus::class) as $option)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-sm text-ink">
                                <x-status :value="$option['value']" />
                            </dt>
                            <dd class="text-sm font-semibold text-ink tabular-nums">{{ $courseStatusCounts[$option['value']] ?? 0 }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        {{-- Account and role changes. Read only, and it never shows a password, a
             token, an IP address, or browser metadata. --}}
        <section class="mt-8" aria-labelledby="administrator-activity-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="administrator-activity-heading" class="text-xl font-semibold text-ink">
                        Account and role changes
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-ink-muted">
                        Role and account status changes, newest first.
                    </p>
                </div>
                <x-btn :href="route('admin.activity.index')" variant="quiet" size="md">
                    Open the activity log
                    <x-icon name="chevron-right" size="sm" />
                </x-btn>
            </div>

            @if ($recentActivity->isEmpty())
                <div class="card mt-4">
                    <x-empty-state
                        compact
                        icon="activity"
                        title="No recorded activity yet"
                        description="A record appears here whenever an Administrator assigns a role or changes an account status."
                    />
                </div>
            @else
                <div class="card mt-4 overflow-x-auto">
                    <table class="w-full min-w-2xl text-left text-sm">
                        <caption class="sr-only">
                            The five most recent role and account status changes
                        </caption>
                        <thead class="table-head">
                            <tr>
                                <th scope="col">Actor</th>
                                <th scope="col">Target</th>
                                <th scope="col">Change</th>
                                <th scope="col">When</th>
                            </tr>
                        </thead>
                        <tbody role="list" class="divide-y divide-line">
                            @foreach ($recentActivity as $entry)
                                <tr>
                                    <td class="table-cell font-medium">{{ $entry->actor?->name ?? 'System' }}</td>
                                    <td class="table-cell">{{ $entry->targetUser?->name ?? 'Unknown' }}</td>
                                    <td class="table-cell">
                                        <x-status
                                            :value="$entry->event_type->value"
                                            :label="\App\Support\StatusLabel::words($entry->event_type->value)"
                                            tone="info"
                                        />
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-ink-muted">
                                        {{ $entry->created_at->format('M j, Y H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
@endsection
