@props(['errors'])

@if ($errors->any())
    <div
        id="form-error-summary"
        data-error-summary
        role="alert"
        tabindex="-1"
        aria-labelledby="form-error-summary-title"
        class="note note-error items-start"
    >
        <x-icon name="alert" size="md" class="mt-0.5" />
        <div>
            <h2 id="form-error-summary-title" class="font-semibold">Check the highlighted fields</h2>
            <p class="mt-1">Fix each item below, then submit the form again.</p>
            <ul role="list" class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
