@extends('layouts.app-shell')

@section('title', 'Certificates')
@section('workspace-context', 'Operations workspace')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Administration"
            title="Certificates"
            description="Revoking keeps the record and the reason. Reissuing creates a linked replacement after a fresh eligibility check."
        >
            <x-slot:actions>
                <x-btn :href="route('administrator.dashboard')" variant="secondary" size="md">
                    <x-icon name="arrow-left" size="sm" />
                    Back to dashboard
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mt-6" />

        @if ($certificates->isEmpty())
            <div class="card mt-8">
                <x-empty-state
                    icon="award"
                    title="No certificates issued yet"
                    description="A certificate appears here once a student completes a course and claims it."
                >
                    <x-btn :href="route('administrator.dashboard')" variant="primary" size="md">
                        Back to dashboard
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            <ul role="list" class="mt-8 space-y-4">
                @foreach ($certificates as $certificate)
                    <li class="card p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <p class="text-base font-semibold text-ink">
                                        {{ $certificate->student?->name ?? 'Unknown student' }}
                                    </p>
                                    <x-status :value="$certificate->status->value" />
                                </div>

                                <p class="mt-2 text-ink-muted">{{ $certificate->course_title_snapshot }}</p>

                                <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                    <div>
                                        <dt class="meta-label">Certificate code</dt>
                                        <dd class="mt-0.5 font-mono text-ink">{{ $certificate->certificate_code }}</dd>
                                    </div>
                                    <div>
                                        <dt class="meta-label">Completed</dt>
                                        <dd class="mt-0.5 text-ink">{{ $certificate->completion_date->format('M j, Y') }}</dd>
                                    </div>
                                </dl>

                                @unless ($certificate->isValid())
                                    <x-note tone="error" class="mt-4">
                                        Revoked{{ $certificate->revocation_reason ? ': '.$certificate->revocation_reason : '.' }}
                                    </x-note>
                                @endunless
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-3">
                                @if ($certificate->isValid())
                                    <form
                                        method="POST"
                                        action="{{ route('admin.certificates.revoke', $certificate) }}"
                                        data-confirm="Revoke this certificate? The record and the reason are kept."
                                        data-pending
                                    >
                                        @csrf
                                        <x-btn type="submit" variant="danger" size="md" data-pending-button>
                                            <span data-pending-text>Revoke</span>
                                        </x-btn>
                                    </form>
                                @else
                                    <form
                                        method="POST"
                                        action="{{ route('admin.certificates.reissue', $certificate) }}"
                                        data-confirm="Reissue a replacement certificate? A fresh eligibility check runs first."
                                        data-pending
                                    >
                                        @csrf
                                        <x-btn type="submit" variant="primary" size="md" data-pending-button>
                                            <span data-pending-text>Reissue</span>
                                        </x-btn>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $certificates->links() }}</div>
        @endif
    </div>
@endsection
