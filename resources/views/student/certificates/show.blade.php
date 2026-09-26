@extends('layouts.app')

@section('title', 'Certificate')

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('student.certificates.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to my certificates</a>

        <article class="mt-6 border-4 border-double border-line bg-surface p-8 sm:p-12" aria-labelledby="certificate-heading">
            <header class="text-center">
                <p class="font-mono text-sm font-semibold text-primary-text">IT Learning Hub</p>
                <h1 id="certificate-heading" class="mt-3 text-3xl font-[650] tracking-tight text-ink">Certificate of Completion</h1>
                <p class="mt-3 text-sm text-ink-muted">Issued {{ $certificate->completion_date->format('F j, Y') }}</p>
            </header>

            <div class="mt-8 border-t border-line pt-8">
                <p class="text-sm text-ink-muted">This certifies that</p>
                <p class="mt-2 text-2xl font-semibold text-ink">{{ $certificate->student_name_snapshot }}</p>
                <p class="mt-4 text-sm text-ink-muted">has completed</p>
                <p class="mt-2 text-2xl font-semibold text-ink">{{ $certificate->course_title_snapshot }}</p>
            </div>

            <footer class="mt-10 border-t border-line pt-6">
                <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-ink-muted">Certificate code</dt>
                        <dd class="mt-1 font-mono font-semibold text-ink">{{ $certificate->certificate_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Status</dt>
                        <dd class="mt-1 font-semibold {{ $certificate->isValid() ? 'text-success-text' : 'text-ink' }}">{{ $certificate->isValid() ? 'Valid' : 'Revoked' }}</dd>
                    </div>
                </dl>

                @if (! $certificate->isValid())
                    <div class="mt-6 border-l-4 border-line bg-surface-muted px-4 py-3">
                        <p class="text-sm font-semibold text-ink">This certificate was revoked</p>
                        @if ($certificate->revocation_reason)
                            <p class="mt-1 text-sm leading-6 text-ink-muted">{{ $certificate->revocation_reason }}</p>
                        @endif
                        <p class="mt-1 text-sm text-ink-muted">Revoked {{ $certificate->revoked_at?->format('F j, Y') }}.</p>
                    </div>
                @endif

                @if ($certificate->replaces_certificate_id)
                    <p class="mt-4 text-sm text-ink-muted">This certificate replaces an earlier revoked certificate.</p>
                @endif
            </footer>
        </article>

        <p class="mt-6 text-sm leading-6 text-ink-muted">This page is only visible to you while you are enrolled in the course. It is not a public document.</p>
    </div>
@endsection
