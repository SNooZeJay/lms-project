@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('administrator.dashboard') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-text hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">← Back to dashboard</a>

        <header class="mt-6">
            <h1 class="text-3xl font-[650] tracking-tight text-ink">Enrollment report</h1>
            <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Every row is read from the database. Amounts come from the stored payment record, never from a request.</p>
        </header>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

        <section class="mt-8" aria-labelledby="report-rows-heading">
            <h2 id="report-rows-heading" class="sr-only">Enrollment rows</h2>

            @if ($rows->isEmpty())
                <div class="border-t border-line py-16 text-center">
                    <p class="text-lg font-semibold text-ink">No enrollments yet</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">Rows appear here as soon as a student enrolls in a course.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-3xl border-collapse text-left text-sm">
                        <caption class="sr-only">One row per enrollment with its student, course, state, and paid amount</caption>
                        <thead>
                            <tr class="border-y border-line">
                                <th scope="col" class="py-3 pr-4 font-semibold text-ink">Student</th>
                                <th scope="col" class="py-3 pr-4 font-semibold text-ink">Course</th>
                                <th scope="col" class="py-3 pr-4 font-semibold text-ink">State</th>
                                <th scope="col" class="py-3 pr-4 font-semibold text-ink">Quizzes passed</th>
                                <th scope="col" class="py-3 font-semibold text-ink">Amount paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="py-3 pr-4 text-ink">{{ $row['student']?->name ?? 'Unknown' }}</td>
                                    <td class="py-3 pr-4 text-ink">{{ $row['course']?->title ?? 'Unknown' }}</td>
                                    <td class="py-3 pr-4 text-ink-muted">{{ ucfirst($row['status']->value) }}</td>
                                    <td class="py-3 pr-4 text-ink-muted">{{ $row['passed_attempts'] }}</td>
                                    <td class="py-3 text-ink">
                                        @if ($row['payment'] !== null && $row['payment']->status->value === 'paid')
                                            {{ $row['payment']->currency }} {{ number_format($row['payment']->amount_minor / 100, 2) }}
                                        @else
                                            <span class="text-ink-muted">Not paid</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-sm text-ink-muted">Showing the {{ $rows->count() }} most recent enrollments.</p>
            @endif
        </section>
    </div>
@endsection
