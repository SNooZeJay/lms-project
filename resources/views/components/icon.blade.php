@props([
    'name',
    'size' => 'md',
])

@php
    /*
     | A small, hand written icon set.
     |
     | Every icon is a 24 unit stroke drawing on a 24 unit grid, so icons share
     | one optical weight. Using a real icon instead of a typed symbol keeps
     | line weight consistent across platforms and survives a font change.
     |
     | Icons are decorative by default and carry an empty alternative text. A
     | control that has no visible text names the icon itself.
     */
    $paths = [
        'home' => '<path d="M3 10.6 12 3l9 7.6"/><path d="M5.5 9.5V20a1 1 0 0 0 1 1H10v-5.5h4V21h3.5a1 1 0 0 0 1-1V9.5"/>',
        'book-open' => '<path d="M12 6.5C10.5 5 8.4 4.3 6 4.3c-.9 0-1.8.1-2.5.3v13.1c.7-.2 1.6-.3 2.5-.3 2.4 0 4.5.7 6 2.2 1.5-1.5 3.6-2.2 6-2.2.9 0 1.8.1 2.5.3V4.6c-.7-.2-1.6-.3-2.5-.3-2.4 0-4.5.7-6 2.2Z"/><path d="M12 6.5v13.1"/>',
        'layers' => '<path d="m12 3 9 4.5-9 4.5L3 7.5 12 3Z"/><path d="m3 12.5 9 4.5 9-4.5"/><path d="m3 17 9 4.5 9-4.5"/>',
        'award' => '<circle cx="12" cy="9" r="5.2"/><path d="m8.4 13.4-1.3 7.3L12 18.2l4.9 2.5-1.3-7.3"/>',
        'certificate' => '<rect x="3.5" y="4" width="17" height="13" rx="1.5"/><path d="M7 8.5h6M7 12h9"/><circle cx="16.5" cy="16" r="3"/><path d="m14.4 18.4-.6 2.6 2.7-1.3 2.7 1.3-.6-2.6"/>',
        'users' => '<path d="M15.5 20v-1.6a3.4 3.4 0 0 0-3.4-3.4H7.4A3.4 3.4 0 0 0 4 18.4V20"/><circle cx="9.75" cy="8" r="3.3"/><path d="M20 20v-1.6a3.4 3.4 0 0 0-2.6-3.3"/><path d="M15.3 4.9a3.4 3.4 0 0 1 0 6.4"/>',
        'chart' => '<path d="M4 20h16"/><path d="M7 20v-6"/><path d="M12 20V6"/><path d="M17 20v-9"/>',
        'activity' => '<path d="M3 12h3.5l2.5-7 4 14 2.5-7H21"/>',
        'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="1.8"/><path d="M8 10.5V7.6a4 4 0 0 1 8 0v2.9"/><path d="M12 14.5v2.2"/>',
        'log-out' => '<path d="M9.5 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3.5"/><path d="M15.5 16.5 20 12l-4.5-4.5"/><path d="M20 12H9"/>',
        'menu' => '<path d="M4 6.5h16"/><path d="M4 12h16"/><path d="M4 17.5h16"/>',
        'close' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2"/><path d="M12 19.5v2"/><path d="M2.5 12h2"/><path d="M19.5 12h2"/><path d="m5.2 5.2 1.4 1.4"/><path d="m17.4 17.4 1.4 1.4"/><path d="m18.8 5.2-1.4 1.4"/><path d="m6.6 17.4-1.4 1.4"/>',
        'moon' => '<path d="M20.5 14.3A8.5 8.5 0 0 1 9.7 3.5a8.5 8.5 0 1 0 10.8 10.8Z"/>',
        'monitor' => '<rect x="3" y="4.5" width="18" height="12" rx="1.8"/><path d="M8.5 20.5h7"/><path d="M12 16.5v4"/>',
        'check' => '<path d="m4.5 12.5 5 5 10-11"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.6"/><path d="m8.2 12.2 2.6 2.6 5-5.4"/>',
        'alert' => '<path d="M12 4.5 2.8 20h18.4L12 4.5Z"/><path d="M12 10v4.2"/><path d="M12 17.2h.01"/>',
        'info' => '<circle cx="12" cy="12" r="8.6"/><path d="M12 11v5.5"/><path d="M12 8h.01"/>',
        'x-circle' => '<circle cx="12" cy="12" r="8.6"/><path d="m9.3 9.3 5.4 5.4"/><path d="m14.7 9.3-5.4 5.4"/>',
        'arrow-right' => '<path d="M4.5 12h15"/><path d="m13.5 6 6 6-6 6"/>',
        'arrow-left' => '<path d="M19.5 12h-15"/><path d="m10.5 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9.5 5.5 6.5 6.5-6.5 6.5"/>',
        'chevron-down' => '<path d="m5.5 9.5 6.5 6.5 6.5-6.5"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'pencil' => '<path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="m14.5 6.5 3 3"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'credit-card' => '<rect x="2.8" y="5.5" width="18.4" height="13" rx="2"/><path d="M2.8 10h18.4"/><path d="M6.5 14.5h3.5"/>',
        'clipboard' => '<rect x="5" y="4.5" width="14" height="16" rx="1.8"/><path d="M9 4.5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 4.5"/><path d="M9 11h6"/><path d="M9 15h4"/>',
        'clock' => '<circle cx="12" cy="12" r="8.6"/><path d="M12 7.2V12l3.2 2"/>',
        'download' => '<path d="M12 4v10.5"/><path d="m8 11 4 4 4-4"/><path d="M4.5 19.5h15"/>',
        'external' => '<path d="M14 4.5h5.5V10"/><path d="m19.5 4.5-7.5 7.5"/><path d="M18 14.5v4a1.5 1.5 0 0 1-1.5 1.5H6a1.5 1.5 0 0 1-1.5-1.5V8A1.5 1.5 0 0 1 6 6.5h4"/>',
        'play' => '<circle cx="12" cy="12" r="8.6"/><path d="m10.3 8.8 5.2 3.2-5.2 3.2V8.8Z"/>',
        'target' => '<circle cx="12" cy="12" r="8.4"/><circle cx="12" cy="12" r="4.6"/><circle cx="12" cy="12" r="1"/>',
        'folder' => '<path d="M3.5 7.2a1.7 1.7 0 0 1 1.7-1.7h3.3l2 2.4h8.3a1.7 1.7 0 0 1 1.7 1.7v8.7a1.7 1.7 0 0 1-1.7 1.7H5.2a1.7 1.7 0 0 1-1.7-1.7V7.2Z"/>',
        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="1.8"/><path d="m3.8 7 8.2 6 8.2-6"/>',
        'eye' => '<path d="M2.2 12S5 5.6 12 5.6 21.8 12 21.8 12 19 18.4 12 18.4 2.2 12 2.2 12Z"/><circle cx="12" cy="12" r="3.1"/>',
        'eye-off' => '<path d="M9.9 5.7A9.9 9.9 0 0 1 12 5.5c7 0 9.8 6.4 9.8 6.4a17 17 0 0 1-2.6 3.5"/><path d="M6.3 6.8A17 17 0 0 0 2.2 11.9S5 18.3 12 18.3a9.6 9.6 0 0 0 3.5-.65"/><path d="M9.9 9.9a3.1 3.1 0 0 0 4.3 4.3"/><path d="m3.5 3.5 17 17"/>',
        'shield' => '<path d="M12 3.2 5 6v6c0 4.2 2.8 7.4 7 8.8 4.2-1.4 7-4.6 7-8.8V6l-7-2.8Z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'calendar' => '<rect x="3.8" y="5.2" width="16.4" height="15" rx="1.8"/><path d="M3.8 10h16.4"/><path d="M8.5 3.5v3.2"/><path d="M15.5 3.5v3.2"/>',
        'spark' => '<path d="M12 3.5 13.9 9l5.6 1.9-5.6 1.9L12 18.4l-1.9-5.6L4.5 10.9 10.1 9 12 3.5Z"/>',
        'dots' => '<circle cx="12" cy="5.5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="18.5" r="1.4"/>',
    ];

    $sizes = [
        'xs' => 'size-3.5',
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-6',
        'xl' => 'size-9',
    ];

    $drawing = $paths[$name] ?? $paths['info'];
    $boxClass = $sizes[$size] ?? $sizes['md'];
@endphp

<svg
    {{ $attributes->merge(['class' => $boxClass.' shrink-0']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>{!! $drawing !!}</svg>
