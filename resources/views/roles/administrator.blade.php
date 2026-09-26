@extends('layouts.app')

@section('title', 'Administrator home')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <p class="font-mono text-sm font-semibold text-primary-text">Administrator workspace</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Welcome, {{ $user->name }}</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Your Administrator access is active. User management, certificates, reports, and audit tools are available below.</p>

        <dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4" role="list">
            <div class="border-t-4 border-primary bg-surface p-5">
                <dt class="text-sm text-ink-muted">Users</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['users'] }}</dd>
            </div>
            <div class="border-t-4 border-primary bg-surface p-5">
                <dt class="text-sm text-ink-muted">Students</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['students'] }}</dd>
            </div>
            <div class="border-t-4 border-primary bg-surface p-5">
                <dt class="text-sm text-ink-muted">Instructors</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['instructors'] }}</dd>
            </div>
            <div class="border-t-4 border-primary bg-surface p-5">
                <dt class="text-sm text-ink-muted">Courses</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['courses'] }}</dd>
            </div>
            <div class="border-t-4 border-accent bg-surface p-5">
                <dt class="text-sm text-ink-muted">Enrollments</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['enrollments'] }}</dd>
            </div>
            <div class="border-t-4 border-accent bg-surface p-5">
                <dt class="text-sm text-ink-muted">Active enrollments</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['active_enrollments'] }}</dd>
            </div>
            <div class="border-t-4 border-accent bg-surface p-5">
                <dt class="text-sm text-ink-muted">Certificates earned</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['certificates'] }}</dd>
            </div>
            <div class="border-t-4 border-accent bg-surface p-5">
                <dt class="text-sm text-ink-muted">Paid payments</dt>
                <dd class="mt-2 text-3xl font-[650] tracking-tight text-ink">{{ $stats['paid_payments'] }}</dd>
            </div>
        </dl>

        <section class="mt-8 border border-line bg-surface-muted p-6" aria-labelledby="administrator-tools-heading">
            <h2 id="administrator-tools-heading" class="text-lg font-semibold text-ink">Administration tools</h2>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Manage users</a>
                <a href="{{ route('admin.certificates.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Manage certificates</a>
                <a href="{{ route('admin.reports.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">View reports</a>
                <a href="{{ route('admin.activity.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">View activity</a>
            </div>
        </section>

        <section class="mt-8 border border-line bg-surface p-6" aria-labelledby="administrator-summary-heading">
            <h2 id="administrator-summary-heading" class="text-lg font-semibold text-ink">Account summary</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-ink-muted">Role</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->role->value) }}</dd></div>
                <div><dt class="text-ink-muted">Status</dt><dd class="font-medium text-ink">{{ ucfirst($user->profile->account_status->value) }}</dd></div>
                <div><dt class="text-ink-muted">Email</dt><dd class="break-words font-medium text-ink">{{ $user->email }}</dd></div>
            </dl>
        </section>
    </div>
@endsection
