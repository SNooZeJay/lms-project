@props([
    'code',
    'title',
    'message',
    'icon' => 'info',
])

{{--
    A safe error page.

    The copy is fixed, plain English, and never contains an exception message, a
    file path, a stack trace, or any other internal detail. Whatever the cause,
    the page says the same thing, so an error page cannot leak the server.
--}}
<section
    {{ $attributes->merge(['class' => 'mx-auto flex w-full max-w-3xl items-center px-4 py-16 sm:px-6 lg:px-8']) }}
    aria-labelledby="error-title"
>
    <div class="w-full card-accent-edge p-6 sm:p-10">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-md border border-line bg-surface-muted text-primary-text">
                <x-icon :name="$icon" size="lg" />
            </span>
            <p class="eyebrow">Error {{ $code }}</p>
        </div>

        <h1 id="error-title" class="mt-5 text-3xl font-[650] tracking-tight text-balance text-ink sm:text-4xl">
            {{ $title }}
        </h1>

        <p class="prose-measure mt-4 text-lg leading-8 text-ink-muted">{{ $message }}</p>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <x-btn :href="route('home')" variant="primary" size="lg">
                Return to home
            </x-btn>
            <x-btn :href="route('courses.index')" variant="secondary" size="lg">
                Browse the course catalog
            </x-btn>
        </div>
    </div>
</section>
