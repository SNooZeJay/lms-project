@extends('layouts.app-shell')

@section('title', 'My certificates')
@section('workspace-context', 'Learning workspace')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-breadcrumbs :items="[
            ['label' => 'Dashboard', 'href' => route('student.dashboard')],
            ['label' => 'My certificates'],
        ]" class="mb-6" />

        <x-page-header
            eyebrow="Student workspace"
            title="My certificates"
            description="Finish every required lesson and pass every required quiz to earn a certificate."
        >
            <x-slot:actions>
                <x-btn :href="route('student.courses.index')" variant="secondary" size="md">
                    <x-icon name="book-open" size="sm" />
                    My courses
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mt-6" />

        @if ($rows->isEmpty())
            <div class="card mt-8">
                <x-empty-state
                    icon="award"
                    title="No enrollments yet"
                    description="Enroll in a published course to start your learning record. A certificate appears here once every requirement is met."
                >
                    <x-btn :href="route('student.courses.index')" variant="primary" size="md">
                        Open my courses
                    </x-btn>
                    <x-btn :href="route('courses.index')" variant="secondary" size="md">
                        Browse the catalog
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            {{-- Lessons and quizzes are stated as `x of y`, so nothing about the
                 remaining work is a mystery. --}}
            <ul role="list" class="mt-8 grid gap-5 sm:grid-cols-2">
                @foreach ($rows as $row)
                    @php
                        $certificate = $row['certificate'];
                        $summary = $row['summary'];
                    @endphp
                    <li class="card flex flex-col p-5">
                        <h2 class="text-base font-semibold text-ink">{{ $row['course']->title }}</h2>
                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $row['course']->instructor?->name ?? 'IT Learning Hub' }}
                        </p>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="meta-label">Lessons</dt>
                                <dd class="mt-1 font-semibold text-ink tabular-nums">
                                    {{ $summary['lessons_completed'] }} of {{ $summary['lessons_total'] }}
                                </dd>
                            </div>
                            <div>
                                <dt class="meta-label">Quizzes</dt>
                                <dd class="mt-1 font-semibold text-ink tabular-nums">
                                    {{ $summary['quizzes_passed'] }} of {{ $summary['quizzes_total'] }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-1 flex-col">
                            @if ($certificate)
                                <div>
                                    {{-- A Student reads the state as `Valid` or `Revoked`, which is
                                         the plainest word for the record they own. --}}
                                    <x-status
                                        :value="$certificate->isValid() ? 'issued' : 'revoked'"
                                        :label="$certificate->isValid() ? 'Valid' : 'Revoked'"
                                    />
                                    @if (! $certificate->isValid() && $certificate->revocation_reason)
                                        <x-note tone="error" class="mt-3">
                                            {{ $certificate->revocation_reason }}
                                        </x-note>
                                    @endif
                                </div>

                                <x-btn
                                    :href="route('student.certificates.show', $certificate)"
                                    variant="primary"
                                    size="md"
                                    block
                                    class="mt-4"
                                >
                                    <x-icon name="award" size="sm" />
                                    View certificate
                                </x-btn>
                            @elseif ($summary['eligible'])
                                <p class="text-sm leading-6 text-ink-muted">
                                    You meet every requirement. Claim your certificate.
                                </p>

                                <form
                                    method="POST"
                                    action="{{ route('student.courses.complete', $row['course']) }}"
                                    class="mt-4"
                                    data-pending
                                >
                                    @csrf
                                    <x-btn type="submit" variant="primary" size="md" block data-pending-button>
                                        <span data-pending-text>Claim certificate</span>
                                    </x-btn>
                                </form>
                            @else
                                <div>
                                    <p class="text-sm font-semibold text-ink">Still to do</p>
                                    <ul role="list" class="mt-2 space-y-1.5">
                                        @foreach ($summary['reasons'] as $reason)
                                            <li class="flex gap-2 text-sm leading-6 text-ink-muted">
                                                <x-icon name="close" size="sm" class="mt-1 shrink-0 text-ink-subtle" />
                                                <span>{{ $reason }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
