@props([
    'courses',
])

{{--
    A row of course cards.

    Three columns at every size, and the column count is deliberately not
    adjusted to the number of cards. Letting a group of two spread across two
    columns made those cards 598 pixels wide on a laptop, which pushed the
    description out to about ninety five characters a line. A course card is a
    repeated object on this page, and one that changes width between the section
    above it and the section below is more noticeable than a row that is not
    completely full.

    The gap is the same at every size. A grid that is tighter on a phone looks
    deliberate, and a grid that is wider on a desktop separates cards that
    belong together.
--}}
<ul {{ $attributes->merge(['class' => 'grid gap-5 sm:grid-cols-2 lg:grid-cols-3']) }} role="list">
    @foreach ($courses as $course)
        <x-course-card :course="$course" />
    @endforeach
</ul>
