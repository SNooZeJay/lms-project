@props(['errors'])

@if ($errors->any())
    <div
        id="form-error-summary"
        data-error-summary
        role="alert"
        tabindex="-1"
        aria-labelledby="form-error-summary-title"
        class="mb-6 border-l-4 border-error-text bg-error-surface px-4 py-4 text-sm text-error-text"
    >
        <h2 id="form-error-summary-title" class="font-semibold">Check the highlighted fields</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
