<?php

/*
|--------------------------------------------------------------------------
| Icon map
|--------------------------------------------------------------------------
|
| The single place that decides which Lucide drawing backs each icon name the
| rest of the application asks for. Views say what an icon means, such as
| "mail" or "award", and never name a file in a package. That keeps the
| drawing set swappable: a different library, or a different drawing inside
| this one, is a change to this file alone.
|
| The left side is the project's own vocabulary. The right side is the Lucide
| file name. Run `php artisan icons:sync` after editing either side.
|
| Only icons that something actually renders are listed. An unused entry is
| dead weight, and a missing one falls back to a generic mark, which is
| exactly the kind of quiet wrongness that is hard to spot in review. The
| IconGeometryTest walks every view and fails if a name here has no drawing,
| so a typo becomes a failed test instead of a mystery icon.
|
| Lucide is ISC licensed. Attribution lives in resources/icons/README.md.
|
*/

return [

    /*
    | Navigation and workspace
    */
    'home' => 'house',
    'book-open' => 'book-open',
    'layers' => 'layers',
    'award' => 'award',
    'users' => 'users',
    'user' => 'user',
    'chart' => 'chart-column',
    'activity' => 'activity',
    'log-out' => 'log-out',
    'menu' => 'menu',
    'close' => 'x',
    'folder' => 'folder',
    'target' => 'target',

    /*
    | Fields, security and access
    */
    'mail' => 'mail',

    /*
    | The topbar
    |
    | The reference design puts a bell and a conversation bubble in the topbar
    | with a count on each. The bell is the notification centre and the bubble is
    | course and support messaging. Both are here now because both have real
    | storage behind them: notifications read the notifications table, and
    | messages read the conversation tables.
    |
    | The bubble was absent while the conversation tables did not exist, and
    | IconGeometryTest refusing the unused drawing was the correct answer then.
    */
    'bell' => 'bell',
    'message-square' => 'message-square',

    /*
     | The announcement icon.
     |
     | The plan says type icons come from the existing Lucide pipeline and are
     | added to this file, so a new meaning gets a new drawing rather than
     | borrowing the bell, which already means notifications. A megaphone is the
     | Lucide name for somebody addressing a room, which is exactly what an
     | announcement is.
     */
    'megaphone' => 'megaphone',
    'lock' => 'lock',
    'eye' => 'eye',
    'eye-off' => 'eye-off',
    'shield' => 'shield',

    /*
    | Direction and disclosure
    */
    'arrow-left' => 'arrow-left',
    'arrow-right' => 'arrow-right',
    'chevron-right' => 'chevron-right',
    'chevron-down' => 'chevron-down',
    'external' => 'external-link',

    /*
    | Actions
    */
    'plus' => 'plus',
    'pencil' => 'pencil',
    'search' => 'search',
    'download' => 'download',
    'play' => 'play',
    'clipboard' => 'clipboard',
    'clock' => 'clock',

    /*
    | Status. The three states are named for what they mean rather than for
    | their shape, so a screen reader and a reader of the code both get
    | "success", "problem" and "information".
    */
    'check' => 'check',
    'check-circle' => 'circle-check',
    'x-circle' => 'circle-x',
    'alert' => 'triangle-alert',
    'info' => 'info',

    /*
    | Appearance
    */
    'sun' => 'sun',
    'moon' => 'moon',

];
