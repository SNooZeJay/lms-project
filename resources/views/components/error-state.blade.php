@props([
    'code',
    'title',
    'message',
])

<section class="mx-auto flex min-h-[32rem] w-full max-w-4xl items-center px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="error-title">
    <div class="w-full border-t-4 border-primary bg-surface p-6 shadow-sm sm:p-10">
        <p class="font-mono text-sm font-semibold text-primary-text">Error {{ $code }}</p>
        <h1 id="error-title" class="mt-4 text-3xl font-[650] tracking-tight text-ink sm:text-4xl">{{ $title }}</h1>
        <p class="mt-4 max-w-2xl text-lg leading-8 text-ink-muted">{{ $message }}</p>
        <a
            href="{{ route('home') }}"
            class="mt-8 inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus"
        >
            Return to home
        </a>
    </div>
</section>
