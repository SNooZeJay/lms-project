@props([
    'course',
    'compact' => false,
])

@php
    use App\Support\Money;
    use App\Support\StatusLabel;

    $isPaid = $course->course_type === \App\Enums\CourseType::Paid;
    $lessons = (int) $course->published_lessons_count;
    $modules = (int) $course->published_modules_count;
    $price = Money::course($course->price_minor, $course->course_type, $course->currency);
@endphp

{{--
    A course in a list.

    The card carries the five things a student decides on: what it is called,
    what it covers, who teaches it, how much of it there is, and whether it costs
    anything. Nothing is shown that a student cannot act on.

    The price is read from the course record and formatted from integer minor
    units, so it can never disagree with what checkout would charge.
--}}
<li class="card flex h-full flex-col p-5">
    <div class="flex items-start justify-between gap-3">
        <h3 class="text-base leading-snug font-semibold text-ink">
            <a
                href="{{ route('courses.show', $course) }}"
                class="rounded-sm after:absolute after:inset-0 hover:text-primary-text focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
            >
                {{ $course->title }}
            </a>
        </h3>

        <x-badge :tone="$isPaid ? 'primary' : 'success'" class="shrink-0">
            {{ $price }}
        </x-badge>
    </div>

    @if (! $compact && filled($course->category))
        <p class="mt-2 text-xs font-medium tracking-wide text-ink-subtle uppercase">
            {{ $course->category }}
        </p>
    @endif

    @if (! $compact && filled($course->description))
        <p class="mt-2 line-clamp-3 text-sm leading-6 text-ink-muted">
            {{ $course->description }}
        </p>
    @endif

    {{-- The facts a student weighs, all counted from published content only. --}}
    <dl class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-ink-muted">
        @if ($modules > 0)
            <div class="flex items-center gap-1.5">
                <x-icon name="layers" size="xs" />
                <dt class="sr-only">Modules</dt>
                <dd>{{ $modules }} {{ \Illuminate\Support\Str::plural('module', $modules) }}</dd>
            </div>
        @endif

        @if ($lessons > 0)
            <div class="flex items-center gap-1.5">
                <x-icon name="book-open" size="xs" />
                <dt class="sr-only">Lessons</dt>
                <dd>{{ $lessons }} {{ \Illuminate\Support\Str::plural('lesson', $lessons) }}</dd>
            </div>
        @endif

        <div class="flex items-center gap-1.5">
            <x-icon name="target" size="xs" />
            <dt class="sr-only">Level</dt>
            <dd>{{ StatusLabel::words($course->level->value) }}</dd>
        </div>

        @if ($course->instructor)
            <div class="flex items-center gap-1.5">
                <x-icon name="user" size="xs" />
                <dt class="sr-only">Instructor</dt>
                <dd>{{ $course->instructor->name }}</dd>
            </div>
        @endif
    </dl>

    @unless ($compact)
        <div class="mt-5 pt-1">
            <x-btn :href="route('courses.show', $course)" variant="secondary" size="sm">
                View course
            </x-btn>
        </div>
    @endunless
</li>
