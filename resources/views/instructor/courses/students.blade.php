@extends('layouts.app-shell')

@section('title', 'Learners')
@section('workspace-context', 'Teaching workspace')

@section('content')
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="{{ $course->title }}"
            title="Learners"
            description="Every learner enrolled on this course, with the progress they have actually made."
        >
            <x-slot:actions>
                <x-btn :href="route('instructor.courses.show', $course)" variant="secondary" size="md">
                    <x-icon name="arrow-left" size="sm" />
                    Back to course
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        <x-form-errors :errors="$errors" class="mt-6" />

        {{--
            Progress counts lessons and passing counts assessments, so the two
            columns can disagree and both be right: somebody can have read every
            lesson and have not passed the quiz yet. Showing only one of them
            would misrepresent every learner in that state.
        --}}
        <section class="mt-8" aria-labelledby="learners-heading">
            <h2 id="learners-heading" class="sr-only">Learners on this course</h2>

            @if ($learners->isEmpty())
                <div class="card">
                    <x-empty-state
                        icon="users"
                        title="No learners yet"
                        description="A row appears here as soon as somebody enrolls on this course."
                    >
                        <x-btn :href="route('instructor.courses.show', $course)" variant="primary" size="md">
                            Back to course
                        </x-btn>
                    </x-empty-state>
                </div>
            @else
                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-2xl text-left text-sm">
                            <caption class="sr-only">
                                One row per enrolled learner, with their state, the lessons they have
                                finished out of the required lessons, and how many assessments they
                                have passed
                            </caption>
                            <thead class="table-head">
                                <tr>
                                    <th scope="col">Learner</th>
                                    <th scope="col">State</th>
                                    <th scope="col">Progress</th>
                                    <th scope="col" class="text-right">Quizzes passed</th>
                                </tr>
                            </thead>
                            <tbody role="list" class="divide-y divide-line">
                                @foreach ($learners as $row)
                                    <tr>
                                        <th scope="row" class="table-cell font-medium text-ink">
                                            {{ $row['student']?->name ?? 'Unknown learner' }}
                                        </th>
                                        <td class="table-cell">
                                            <x-status :value="$row['status']->value" />
                                        </td>
                                        <td class="table-cell">
                                            @if ($row['total'] === 0)
                                                {{--
                                                    Enrolled on a course with no required published
                                                    Lessons, so there is nothing to have finished. A zero
                                                    percent bar here would read as a learner who has
                                                    failed to start.
                                                --}}
                                                <span class="text-ink-muted">Nothing required yet</span>
                                            @else
                                                <x-progress
                                                    :percentage="$row['percentage']"
                                                    label="Progress"
                                                    :detail="$row['completed'].' of '.$row['total'].' required lessons'"
                                                    size="sm"
                                                />
                                            @endif
                                        </td>
                                        <td class="table-cell text-right tabular-nums text-ink-muted">
                                            {{ $row['passed'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="mt-4 text-sm text-ink-muted">
                    {{ $learners->count() }} enrolled {{ Str::plural('learner', $learners->count()) }}.
                    Cancelled and unpaid enrollments are not listed, because they have not started.
                </p>
            @endif
        </section>
    </div>
@endsection
