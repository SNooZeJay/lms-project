@extends('layouts.app')

@section('title', 'My courses')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="font-mono text-sm font-semibold text-primary-text">Instructor workspace</p>
                <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">My courses</h1>
                <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Create a private Course draft, then review its ordered outline.</p>
            </div>
            <a href="{{ route('instructor.courses.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Create course</a>
        </div>

        @if (session('status'))
            <div role="status" class="mt-6 border-l-4 border-accent bg-success-surface px-4 py-3 text-sm font-semibold text-success-text">{{ session('status') }}</div>
        @endif

        @if ($courses->isEmpty())
            <section class="mt-10 border-t border-line py-16 text-center" aria-labelledby="empty-courses-heading">
                <h2 id="empty-courses-heading" class="text-lg font-semibold text-ink">No courses yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-muted">Create your first Course as a private draft. Nothing becomes public in this phase.</p>
                <a href="{{ route('instructor.courses.create') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Create your first course</a>
            </section>
        @else
            <div class="mt-8 space-y-4 md:hidden">
                @foreach ($courses as $course)
                    @php
                        $pesos = intdiv($course->price_minor, 100);
                        $cents = $course->price_minor % 100;
                    @endphp
                    <article class="border border-line bg-surface p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-ink">{{ $course->title }}</h2>
                                <p class="mt-1 text-sm text-ink-muted">{{ $course->category ?: 'Uncategorized' }}</p>
                            </div>
                            <span class="inline-flex rounded-full bg-surface-muted px-2.5 py-1 text-xs font-semibold text-ink">{{ ucfirst($course->status->value) }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div><dt class="text-ink-muted">Type</dt><dd class="mt-1 font-medium text-ink">{{ ucfirst($course->course_type->value) }}</dd></div>
                            <div><dt class="text-ink-muted">Level</dt><dd class="mt-1 font-medium text-ink">{{ ucfirst($course->level->value) }}</dd></div>
                            <div><dt class="text-ink-muted">Price</dt><dd class="mt-1 font-medium text-ink">PHP {{ number_format($pesos) }}.{{ str_pad($cents, 2, '0', STR_PAD_LEFT) }}</dd></div>
                            <div><dt class="text-ink-muted">Outline</dt><dd class="mt-1 font-medium text-ink">{{ $course->modules_count }} modules</dd></div>
                        </dl>
                        <a href="{{ route('instructor.courses.show', $course) }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">View outline</a>
                        @if ($course->status === \App\Enums\CourseStatus::Draft)
                            <form method="POST" action="{{ route('instructor.courses.publish', $course) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Publish course</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('instructor.courses.unpublish', $course) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Unpublish course</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="mt-8 hidden overflow-x-auto border-t border-line md:block">
                <table class="min-w-full divide-y divide-line text-left text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Course</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Type</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Level</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Price</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Outline</th>
                            <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @foreach ($courses as $course)
                            @php
                                $pesos = intdiv($course->price_minor, 100);
                                $cents = $course->price_minor % 100;
                            @endphp
                            <tr>
                                <td class="px-4 py-5 align-top">
                                    <p class="font-semibold text-ink">{{ $course->title }}</p>
                                    <p class="mt-1 text-ink-muted">{{ $course->category ?: 'Uncategorized' }}</p>
                                </td>
                                <td class="px-4 py-5 align-top"><span class="font-medium text-ink">{{ ucfirst($course->status->value) }}</span></td>
                                <td class="px-4 py-5 align-top text-ink">{{ ucfirst($course->course_type->value) }}</td>
                                <td class="px-4 py-5 align-top text-ink">{{ ucfirst($course->level->value) }}</td>
                                <td class="px-4 py-5 align-top text-ink">PHP {{ number_format($pesos) }}.{{ str_pad($cents, 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-5 align-top text-ink-muted">{{ $course->modules_count }} modules</td>
                                <td class="px-4 py-5 align-top text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        <a href="{{ route('instructor.courses.show', $course) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">View outline</a>
                                        @if ($course->status === \App\Enums\CourseStatus::Draft)
                                            <form method="POST" action="{{ route('instructor.courses.publish', $course) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Publish course</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('instructor.courses.unpublish', $course) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Unpublish course</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $courses->links() }}</div>
        @endif
    </div>
@endsection
