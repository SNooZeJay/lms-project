@props([
    'action',
    'scope',
    'heading',
    'description',
    'audience',
    'submitLabel' => 'Publish announcement',
])

{{--
    The form that publishes an announcement.

    Why this exists as a component rather than as two copies of a form: the shape is
    identical in both places an announcement is written, the field names must match
    what StoreAnnouncementRequest validates, and a second copy is a second thing to
    forget. The only differences are where it posts to and what it says about who
    will read it, so those are the only things passed in.

    Which kind of announcement this is never comes from a field. It comes from which
    form was submitted, because a request that could name its own scope would be a
    request that could announce itself to everybody.

    The audience line is not decoration. Publishing to a course and publishing to
    every account are very different things to do to a stranger, and the person
    pressing the button should be able to see which one they are about to do
    without reading the route.
--}}

<form method="POST" action="{{ $action }}" {{ $attributes->merge(['class' => 'mt-4 space-y-3.5']) }} data-pending>
    @csrf

    <x-form-field
        name="title"
        :label="'Title'"
        :scope="$scope"
        :required="true"
        :maxlength="160"
        placeholder="What is this about?"
    />

    <x-form-field
        name="body"
        type="textarea"
        :label="'Message'"
        :scope="$scope"
        :required="true"
        :rows="4"
        :maxlength="5000"
        placeholder="Write it as you would say it."
    />

    <p class="flex items-start gap-2 text-xs leading-5 text-ink-subtle">
        <x-icon name="info" size="sm" class="mt-0.5 shrink-0" />
        <span>{{ $audience }}</span>
    </p>

    <x-form-errors :errors="$errors" />

    <x-btn type="submit" variant="primary" size="md" data-pending-button>
        <x-icon name="megaphone" size="sm" />
        <span data-pending-text>{{ $submitLabel }}</span>
    </x-btn>
</form>
