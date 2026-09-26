@extends('layouts.app')

@section('title', 'Certificates')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('administrator.dashboard') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to dashboard</a>

        <h1 class="mt-6 text-3xl font-[650] tracking-tight text-ink">Certificates</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Revoking keeps the record and the reason. Reissuing creates a linked replacement after a fresh eligibility check.</p>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

        @if ($certificates->isEmpty())
            <section class="mt-10 border-t border-line py-16 text-center" aria-labelledby="no-certificates-admin-heading">
                <h2 id="no-certificates-admin-heading" class="text-lg font-semibold text-ink">No certificates issued yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">Certificates appear here once a student completes a course.</p>
            </section>
        @else
            <ul class="mt-8 divide-y divide-line border-y border-line" role="list">
                @foreach ($certificates as $certificate)
                    <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-ink">{{ $certificate->student?->name ?? 'Unknown student' }}</p>
                            <p class="mt-1 text-sm text-ink-muted">
                                {{ $certificate->course_title_snapshot }} ·
                                <span class="font-mono text-xs">{{ $certificate->certificate_code }}</span> ·
                                {{ $certificate->completion_date->format('M j, Y') }}
                            </p>
                            @if (! $certificate->isValid() && $certificate->revocation_reason)
                                <p class="mt-1 text-sm text-ink-muted">Revoked: {{ $certificate->revocation_reason }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-semibold text-ink-muted">{{ ucfirst($certificate->status->value) }}</span>
                            @if ($certificate->isValid())
                                <form method="POST" action="{{ route('admin.certificates.revoke', $certificate) }}" class="inline-flex">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Revoke</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.certificates.reissue', $certificate) }}" class="inline-flex">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Reissue</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $certificates->links() }}</div>
        @endif
    </div>
@endsection
