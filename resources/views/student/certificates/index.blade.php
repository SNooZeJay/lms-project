@extends('layouts.app')

@section('title', 'My certificates')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <p class="font-mono text-sm font-semibold text-primary-text">Student workspace</p>
        <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">My certificates</h1>
        <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Finish every required lesson and pass every required quiz to earn a certificate.</p>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

        @if ($rows->isEmpty())
            <section class="mt-10 border-t border-line py-16 text-center" aria-labelledby="no-certificates-heading">
                <h2 id="no-certificates-heading" class="text-lg font-semibold text-ink">No enrollments yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">Enroll in a published free course to start your learning record.</p>
                <a href="{{ route('student.courses.index') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open My courses</a>
            </section>
        @else
            <ul class="mt-8 grid gap-5 sm:grid-cols-2" role="list">
                @foreach ($rows as $row)
                    @php
                        $certificate = $row['certificate'];
                        $summary = $row['summary'];
                    @endphp
                    <li class="border border-line bg-surface p-5">
                        <h2 class="font-semibold text-ink">{{ $row['course']->title }}</h2>
                        <p class="mt-1 text-sm text-ink-muted">{{ $row['course']->instructor?->name ?? 'IT Learning Hub' }}</p>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-ink-muted">Lessons</dt>
                                <dd class="mt-1 font-semibold text-ink">{{ $summary['lessons_completed'] }} of {{ $summary['lessons_total'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Quizzes</dt>
                                <dd class="mt-1 font-semibold text-ink">{{ $summary['quizzes_passed'] }} of {{ $summary['quizzes_total'] }}</dd>
                            </div>
                        </dl>

                        @if ($certificate)
                            <p class="mt-4 inline-flex items-center gap-2 rounded-md border border-success-text bg-success-surface px-3 py-1.5 text-sm font-semibold text-success-text">
                                <span aria-hidden="true">✓</span>
                                {{ $certificate->isValid() ? 'Valid' : 'Revoked' }}
                            </p>
                            <a href="{{ route('student.certificates.show', $certificate) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">View certificate</a>
                        @elseif ($summary['eligible'])
                            <p class="mt-4 text-sm leading-6 text-ink-muted">You meet every requirement. Claim your certificate.</p>
                            <form method="POST" action="{{ route('student.courses.complete', $row['course']) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Claim certificate</button>
                            </form>
                        @else
                            <div class="mt-4 border-l-4 border-line bg-surface-muted px-4 py-3">
                                <p class="text-sm font-semibold text-ink">Still to do</p>
                                <ul class="mt-1 space-y-1" role="list">
                                    @foreach ($summary['reasons'] as $reason)
                                        <li class="text-sm leading-6 text-ink-muted">{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
