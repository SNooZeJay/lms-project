{{--
    The error summary for a form.

    The attributes are merged, and they used to be thrown away. Twenty eight
    call sites pass a margin, such as class="mt-6", and every one of them was
    discarded because the class was written literally on the div. So an error
    summary sat flush against the thing above it on every form in the
    application, and the spacing that had been asked for was simply absent with
    nothing to indicate it.

    That is the worst kind of layout fault: the code reads as though the spacing
    is handled, and the rendered page disagrees. ComponentAttributeBag exists so
    a caller can always add a class, and a component that refuses it turns every
    future call site into a silent no-op.
--}}
@props(['errors'])

@if ($errors->any())
    <div
        id="form-error-summary"
        data-error-summary
        role="alert"
        tabindex="-1"
        aria-labelledby="form-error-summary-title"
        {{ $attributes->merge(['class' => 'note note-error items-start']) }}
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
