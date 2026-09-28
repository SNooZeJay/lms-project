@props([
    'name',
    'size' => 'md',
])

@php
    /*
     | One icon component for the whole application.
     |
     | Views ask for an icon by meaning, such as "mail" or "award", and this
     | component decides which drawing that is. The pairing lives in
     | config/icons.php and the drawings themselves are baked into
     | resources/icons/lucide.php by `php artisan icons:sync`, so a deployed
     | server needs no node_modules to draw anything.
     |
     | The size scale, and where each step is meant to be used:
     |
     |   xs  14px  inside dense rows, a card footer, a table cell
     |   sm  16px  the default for controls, fields, buttons, menus
     |   md  20px  navigation items, so a label reads at a glance
     |   lg  24px  an empty or error state, where the icon is the message
     |
     | There is no larger step. A 36px icon was defined once and never used,
     | and an unused size is one more value to keep coherent.
     |
     | Every icon is a 24 unit stroke drawing on one 24 unit grid at one stroke
     | weight, so icons share an optical weight wherever they appear.
     */
    $sizes = [
        'xs' => 'size-3.5',
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-6',
    ];

    $drawings = require base_path('resources/icons/lucide.php');

    $known = array_key_exists($name, $drawings);

    $drawing = $known ? $drawings[$name] : ($drawings['info'] ?? '');

    $boxClass = $sizes[$size] ?? $sizes['md'];
@endphp

{{--
    An unknown name falls back to a neutral mark rather than rendering nothing,
    but it says so to the developer in the console, because a wrong icon name
    is otherwise invisible: the page still looks finished.

    Icons are decorative by default and carry an empty alternative text, since
    the label beside them already names the control. A control with no visible
    text passes its own accessible name and sets aria-hidden="false".
--}}
@if (! $known && app()->environment('local'))
    @once
        @php
            \Illuminate\Support\Facades\Log::warning('Unknown icon name: '.$name);
        @endphp
    @endonce
@endif

<svg
    {{ $attributes->merge(['class' => $boxClass.' shrink-0']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>{!! $drawing !!}</svg>
