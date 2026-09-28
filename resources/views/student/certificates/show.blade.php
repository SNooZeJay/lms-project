@extends('layouts.app-shell')

@section('title', 'Certificate')
@section('workspace-context', 'My certificates')

@section('content')
    <div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        {{-- The certificate itself. A double rule and a monospace code, and it
             never claims to be an accredited document. --}}
        <article
            class="print-sheet border-4 border-double border-line bg-surface p-8 sm:p-12"
            aria-labelledby="certificate-heading"
        >
            <header class="text-center">
                <div class="flex justify-center">
                    <x-logo size="sm" :name-class="'text-base'" />
                </div>

                <h1 id="certificate-heading" class="mt-6 text-3xl font-[650] tracking-tight text-balance text-ink">
                    Certificate of Completion
                </h1>

                <p class="mt-3 text-sm text-ink-muted">
                    Issued {{ $certificate->completion_date->format('F j, Y') }}
                </p>
            </header>

            <div class="mt-10 border-t border-line pt-8">
                <p class="text-sm text-ink-muted">This certifies that</p>
                <p class="mt-2 text-2xl font-semibold text-balance text-ink">{{ $certificate->student_name_snapshot }}</p>

                <p class="mt-6 text-sm text-ink-muted">has completed</p>
                <p class="mt-2 text-2xl font-semibold text-balance text-ink">{{ $certificate->course_title_snapshot }}</p>
            </div>

            <footer class="mt-10 border-t border-line pt-6">
                <dl class="grid grid-cols-1 gap-4 text-sm min-[360px]:grid-cols-2">
                    <div>
                        <dt class="meta-label">Certificate code</dt>
                        <dd class="mt-1 font-mono font-semibold text-ink">{{ $certificate->certificate_code }}</dd>
                    </div>
                    <div>
                        <dt class="meta-label">Status</dt>
                        <dd class="mt-1">
                            <x-status :value="$certificate->isValid() ? 'issued' : 'revoked'" />
                        </dd>
                    </div>
                </dl>

                @unless ($certificate->isValid())
                    <x-note tone="error" class="mt-6">
                        <span class="font-semibold">This certificate was revoked.</span>
                        @if ($certificate->revocation_reason)
                            {{ $certificate->revocation_reason }}
                        @endif
                        @if ($certificate->revoked_at)
                            Revoked on {{ $certificate->revoked_at->format('F j, Y') }}.
                        @endif
                    </x-note>
                @endunless

                @if ($certificate->replaces_certificate_id)
                    <p class="mt-4 text-sm text-ink-muted">
                        This certificate replaces an earlier revoked certificate.
                    </p>
                @endif
            </footer>
        </article>

        <x-note tone="info" class="mt-6">
            This certificate is a record of completion inside IT Learning Hub. It is not an accredited academic
            document, and this page is visible only to you while you are enrolled in the course.
        </x-note>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row" data-print="hide">
            <x-btn :href="route('student.certificates.index')" variant="secondary" size="lg">
                <x-icon name="arrow-left" size="sm" />
                Back to my certificates
            </x-btn>

            <button type="button" class="btn btn-secondary btn-lg" data-print-button>
                <x-icon name="clipboard" size="sm" />
                Print this certificate
            </button>
        </div>
    </div>
@endsection
