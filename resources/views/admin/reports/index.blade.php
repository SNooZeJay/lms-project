@extends('layouts.app-shell')

@section('title', 'Reports')
@section('workspace-context', 'Operations workspace')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-breadcrumbs :items="[
            ['label' => 'Administrator', 'href' => route('administrator.dashboard')],
            ['label' => 'Reports'],
        ]" class="mb-6" />

        <x-page-header
            eyebrow="Administration"
            title="Enrollment report"
            description="Every row is read from the database. Amounts come from the stored payment record, never from a request."
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

        <section class="mt-8" aria-labelledby="report-rows-heading">
            <h2 id="report-rows-heading" class="sr-only">Enrollment rows</h2>

            @if ($rows->isEmpty())
                <div class="card">
                    <x-empty-state
                        icon="chart"
                        title="No enrollments yet"
                        description="A row appears here as soon as a student enrolls in a course."
                    >
                        <x-btn :href="route('administrator.dashboard')" variant="primary" size="md">
                            Back to dashboard
                        </x-btn>
                    </x-empty-state>
                </div>
            @else
                {{-- The table scrolls inside its own container rather than
                     widening the page, which is the behaviour the design system
                     requires for a wide administrator report. --}}
                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-3xl text-left text-sm">
                            <caption class="sr-only">
                                One row per enrollment, with the student, the course, the enrollment state, the
                                number of passed quizzes, and the amount paid
                            </caption>
                            <thead class="table-head">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Course</th>
                                    <th scope="col">State</th>
                                    <th scope="col">Quizzes passed</th>
                                    <th scope="col">Amount paid</th>
                                </tr>
                            </thead>
                            <tbody role="list" class="divide-y divide-line">
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="table-cell font-medium text-ink">
                                            {{ $row['student']?->name ?? 'Unknown' }}
                                        </td>
                                        <td class="table-cell">{{ $row['course']?->title ?? 'Unknown' }}</td>
                                        <td class="table-cell">
                                            <x-status :value="$row['status']->value" />
                                        </td>
                                        <td class="table-cell tabular-nums text-ink-muted">{{ $row['passed_attempts'] }}</td>
                                        <td class="table-cell">
                                            @if ($row['payment'] !== null && $row['payment']->status->value === 'paid')
                                                <x-amount
                                                    :minor="$row['payment']->amount_minor"
                                                    :currency="$row['payment']->currency"
                                                    class="font-semibold"
                                                />
                                            @else
                                                <span class="text-ink-muted">Not paid</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="mt-4 text-sm text-ink-muted">
                    Showing the {{ $rows->count() }} most recent {{ Str::plural('enrollment', $rows->count()) }}.
                </p>
            @endif
        </section>
    </div>
@endsection
