{{--
    One owned course, as a stacked card.

    The Instructor course list uses this layout below the tablet breakpoint, so
    a phone is never handed a wide table. A free course shows the word `Free`
    instead of a zero peso amount, and every control keeps a 44 pixel target.
--}}
<article class="card overflow-hidden p-0">
    {{--
        The instructor's own list shows the cover too, and through the same component
        as every other list, so a course looks the same here as it does in the
        catalog. This is the one card that is not the shared one, because it carries
        controls the public card does not, and a second card is exactly where a cover
        would otherwise be forgotten.

        Lazy rather than eager: this is a management list of the instructor's own
        courses, which is the last place a person is deciding on a photograph, and it
        can be twenty rows long.
    --}}
    <x-course-cover
        :course="$course"
        :width="480"
        :height="270"
        rounded="rounded-none"
        class="aspect-[16/9] w-full"
    />

    <div class="p-5">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-balance text-ink">
                <a
                    href="{{ route('instructor.courses.show', $course) }}"
                    class="rounded-sm hover:text-primary focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
                >{{ $course->title }}</a>
            </h2>
            <p class="mt-1 text-sm text-ink-muted">{{ $course->category ?: 'Uncategorized' }}</p>
        </div>
        <x-status :value="$course->status->value" class="shrink-0" />
    </div>

    <dl class="mt-4 grid grid-cols-1 gap-3 text-sm min-[360px]:grid-cols-2">
        <div>
            <dt class="meta-label">Type</dt>
            <dd class="mt-1 font-medium text-ink">{{ \App\Support\StatusLabel::words($course->course_type->value) }}</dd>
        </div>
        <div>
            <dt class="meta-label">Level</dt>
            <dd class="mt-1 font-medium text-ink">{{ \App\Support\StatusLabel::words($course->level->value) }}</dd>
        </div>
        <div>
            <dt class="meta-label">Price</dt>
            <dd class="mt-1 font-semibold text-ink">
                <x-amount :minor="$course->price_minor" :type="$course->course_type" :currency="$course->currency" />
            </dd>
        </div>
        <div>
            <dt class="meta-label">Outline</dt>
            <dd class="mt-1 font-medium text-ink tabular-nums">
                {{ $course->modules_count }} {{ Str::plural('module', $course->modules_count) }}
            </dd>
        </div>
    </dl>

    <div class="mt-5 flex flex-col gap-3">
        <x-btn :href="route('instructor.courses.show', $course)" variant="secondary" size="md" block>
            View outline
        </x-btn>

        @if ($course->status === \App\Enums\CourseStatus::Draft)
            <form method="POST" action="{{ route('instructor.courses.publish', $course) }}" data-pending>
                @csrf
                <x-btn type="submit" variant="primary" size="md" block data-pending-button>
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
                <x-btn type="submit" variant="secondary" size="md" block data-pending-button>
                    <span data-pending-text>Unpublish course</span>
                </x-btn>
            </form>
        @endif
    </div>
</article>
