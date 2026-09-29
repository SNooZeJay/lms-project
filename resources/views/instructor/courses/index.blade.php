@extends('layouts.app-shell')

@section('title', 'My courses')
@section('workspace-context', 'Teaching workspace')

@section('content')
@section('measure', 'wide')
            <x-page-header
            eyebrow="Instructor workspace"
            title="My courses"
            description="Create a private course draft, build its ordered outline, then publish it when it is ready."
        >
            <x-slot:actions>
                <x-btn :href="route('instructor.courses.create')" variant="primary" size="md">
                    <x-icon name="plus" size="sm" />
                    Create course
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <x-note tone="success" class="mt-6">{{ session('status') }}</x-note>
        @endif

        <x-form-errors :errors="$errors" class="mt-6" />

        @if ($courses->isEmpty())
            <div class="card mt-8">
                <x-empty-state
                    icon="book-open"
                    title="No courses yet"
                    description="Create your first course as a private draft. Only you can see it until you publish it."
                >
                    <x-btn :href="route('instructor.courses.create')" variant="primary" size="md">
                        <x-icon name="plus" size="sm" />
                        Create your first course
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            <p class="mt-6 text-sm text-ink-muted" role="status">
                {{ $courses->total() }} {{ Str::plural('course', $courses->total()) }}
            </p>

            {{-- Stacked cards on a phone, a table from a tablet up. The table
                 scrolls inside its own container rather than widening the page. --}}
            <div class="mt-4 space-y-4 md:hidden">
                @foreach ($courses as $course)
                    @include('instructor.courses.course-card', ['course' => $course])
                @endforeach
            </div>

            <div class="card mt-4 hidden overflow-hidden md:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-4xl text-left text-sm">
                        <caption class="sr-only">
                            One row per course you own, with its status, type, level, price, outline size, and the
                            available actions
                        </caption>
                        <thead class="table-head">
                            <tr>
                                <th scope="col">Course</th>
                                <th scope="col">Status</th>
                                <th scope="col">Type</th>
                                <th scope="col">Level</th>
                                <th scope="col">Price</th>
                                <th scope="col">Outline</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody role="list" class="divide-y divide-line">
                            @foreach ($courses as $course)
                                <tr>
                                    <td class="table-cell">
                                        <p class="font-semibold text-ink">
                                            <a
                                                href="{{ route('instructor.courses.show', $course) }}"
                                                class="rounded-sm hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                                            >{{ $course->title }}</a>
                                        </p>
                                        <p class="mt-1 text-ink-muted">{{ $course->category ?: 'Uncategorized' }}</p>
                                    </td>
                                    <td class="table-cell">
                                        <x-status :value="$course->status->value" />
                                    </td>
                                    <td class="table-cell">
                                        {{ \App\Support\StatusLabel::words($course->course_type->value) }}
                                    </td>
                                    <td class="table-cell">
                                        {{ \App\Support\StatusLabel::words($course->level->value) }}
                                    </td>
                                    <td class="table-cell font-semibold">
                                        <x-amount
                                            :minor="$course->price_minor"
                                            :type="$course->course_type"
                                            :currency="$course->currency"
                                        />
                                    </td>
                                    <td class="table-cell text-ink-muted tabular-nums">
                                        {{ $course->modules_count }} {{ Str::plural('module', $course->modules_count) }}
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex flex-col items-end gap-2">
                                            <x-btn
                                                :href="route('instructor.courses.show', $course)"
                                                variant="secondary"
                                                size="sm"
                                            >View outline</x-btn>

                                            @if ($course->status === \App\Enums\CourseStatus::Draft)
                                                <form
                                                    method="POST"
                                                    action="{{ route('instructor.courses.publish', $course) }}"
                                                    data-pending
                                                >
                                                    @csrf
                                                    <x-btn type="submit" variant="primary" size="sm" data-pending-button>
                                                        <span data-pending-text>Publish course</span>
                                                    </x-btn>
                                                </form>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('instructor.courses.unpublish', $course) }}"
                                                    data-confirm="Unpublish this course? It leaves the catalog, and existing access is kept."
                                                    data-pending
                                                >
                                                    @csrf
                                                    <x-btn type="submit" variant="secondary" size="sm" data-pending-button>
                                                        <span data-pending-text>Unpublish course</span>
                                                    </x-btn>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $courses->links() }}</div>
        @endif
@endsection
