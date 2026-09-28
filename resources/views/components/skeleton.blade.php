@props([
    'lines' => 3,
    'class' => '',
])

{{--
    A placeholder shaped like the content it stands in for.

    This is the one idea worth taking from a skeleton generator, and it is a
    constraint rather than a technique: a placeholder must be derived from the
    same component that renders the real thing, or it drifts. The first version
    of a skeleton is always drawn by hand to look about right, and within a
    release the card gains a line and the skeleton does not, and the loading
    state starts promising a layout the page will not deliver. That is worse than
    no skeleton, because the content then jumps into a shape the person was not
    expecting.

    So nothing here measures a box. It uses the same tokens as the content it
    replaces, at the same type sizes, and it is rendered by the components that
    render the real card. There is no separate shape to keep in step.
--}}
<div class="{{ $class }}" aria-hidden="true">
    @foreach (range(1, max(1, (int) $lines)) as $line)
        <div class="skeleton-line {{ $loop->last ? 'w-2/3' : 'w-full' }}"></div>
    @endforeach
</div>
