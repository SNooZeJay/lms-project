@extends('layouts.app')

@section('title', 'My courses')

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="font-mono text-sm font-semibold text-primary-text">Student workspace</p>
                <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">My courses</h1>
                <p class="mt-3 max-w-2xl leading-7 text-ink-muted">These are your own enrollments. No one else can see this list.</p>
            </div>
            <a href="{{ route('courses.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Browse catalog</a>
        </div>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        <x-form-errors :errors="$errors" />

        @if ($enrollments->isEmpty())
            <section class="mt-10 border-t border-line py-16 text-center" aria-labelledby="empty-enrollments-heading">
                <h2 id="empty-enrollments-heading" class="text-lg font-semibold text-ink">No enrollments yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">Enroll in a published free course to start your learning record.</p>
                <a href="{{ route('courses.index') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Browse published courses</a>
            </section>
        @else
            <ul class="mt-8 grid gap-5 sm:grid-cols-2" role="list">
                @foreach ($enrollments as $enrollment)
                    @php
                        $course = $enrollment->course;
                        $paymentState = $paymentStates[$enrollment->id] ?? null;
                        $toneClasses = [
                            'success' => 'bg-success-surface text-success-text',
                            'warning' => 'bg-accent-surface text-accent-text',
                            'danger' => 'bg-danger-surface text-danger-text',
                            'neutral' => 'bg-surface-muted text-ink',
                        ][$paymentState?->tone ?? 'neutral'] ?? 'bg-surface-muted text-ink';
                    @endphp
                    <li class="flex flex-col border border-line bg-surface p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="text-lg font-semibold text-ink">{{ $course?->title ?? 'Removed course' }}</h2>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $toneClasses }}">{{ $paymentState?->label ?? 'Unknown' }}</span>
                        </div>

                        <p class="mt-2 text-sm text-ink-muted">{{ $course?->category ?: 'Uncategorized' }}</p>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-ink-muted">Type</dt>
                                <dd class="mt-1 font-medium text-ink">{{ $course ? ucfirst($course->course_type->value) : 'Unknown' }}</dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Enrolled</dt>
                                <dd class="mt-1 font-medium text-ink">{{ $enrollment->activated_at?->format('M j, Y') ?? 'Pending' }}</dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Progress</dt>
                                <dd class="mt-1 font-semibold text-ink">
                                    @if ($progressByEnrollment[$enrollment->id]['visible'])
                                        {{ $progressByEnrollment[$enrollment->id]['percentage'] }}%
                                    @else
                                        Hidden
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">Instructor</dt>
                                <dd class="mt-1 font-medium text-ink">{{ $course?->instructor?->name ?? 'IT Learning Hub' }}</dd>
                            </div>
                        </dl>

                        @if ($paymentState)
                            <p class="mt-4 border-l-4 px-3 py-2 text-sm leading-6 text-ink {{ $paymentState->tone === 'danger' ? 'border-danger-text bg-danger-surface' : ($paymentState->tone === 'success' ? 'border-accent bg-success-surface' : 'border-line bg-surface-muted') }}">
                                {{ $paymentState->message }}
                            </p>
                        @endif

                        @if ($enrollment->grantsAccess() && $course)
                            <a href="{{ route('student.courses.show', $course) }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Open course</a>
                        @elseif ($paymentState?->offersPayment && $course)
                            <form method="POST" action="{{ route('student.payments.checkout', $course) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">{{ $paymentState->actionLabel }}</button>
                            </form>
                        @endif

                        @if ($course?->status === \App\Enums\CourseStatus::Published)
                            <a href="{{ route('courses.show', $course) }}" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Public page</a>
                        @else
                            <p class="mt-3 border-l-4 border-line bg-surface-muted px-3 py-2 text-sm leading-6 text-ink-muted">This course is no longer published. Your enrollment is kept.</p>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $enrollments->links() }}</div>
        @endif
    </div>
@endsection
