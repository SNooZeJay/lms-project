<?php

use App\Models\Announcement;
use App\Models\Notification;
use Illuminate\Contracts\Console\Kernel;

/*
 | Removes the announcements the form probe created, and their notices.
 |
 | The probe posts a real announcement every time it runs, which is the right way to
 | prove the form works and the wrong way to leave a demonstration behind. Three
 | runs of the probe left three identical notices on the announcements page, which
 | reads as somebody having pressed publish three times by accident.
 |
 | Only titles the probe writes are touched, and only the newest of each is kept,
 | because one real notice is worth having in a demonstration and three identical
 | ones are not. The notices that were generated alongside them go with them, for
 | the same reason: a demonstration should show the shape of the feature rather
 | than the residue of testing it.
 */

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$families = [
    'Catalog check on Sunday morning%',
    'Bring your own laptop on Thursday%',
    // The workflow probe writes this one, and it now withdraws what it published
    // through the application's own action. It is listed here as well because a
    // probe that was interrupted between publishing and withdrawing would leave a
    // row behind, and twelve of them read as somebody having pressed publish a dozen
    // times by accident. The tool keeps the newest of each, so an announcement
    // somebody actually published by hand is left alone.
    'Bring a laptop on Thursday%',
];

echo PHP_EOL.'  the announcements the probe left behind:'.PHP_EOL;

$removed = 0;
foreach ($families as $pattern) {
    $rows = Announcement::query()
        ->where('title', 'like', $pattern)
        ->orderByDesc('id')
        ->get();

    if ($rows->isEmpty()) {
        continue;
    }

    // The newest stays; the rest go, newest first, and the notices with them.
    foreach ($rows->skip(1) as $announcement) {
        $notices = Notification::query()
            ->where('subject_type', 'announcement')
            ->where('subject_id', $announcement->id)
            ->get();

        foreach ($notices as $notice) {
            $notice->delete();
        }

        $announcement->delete();
        $removed++;
        echo '    #'.$announcement->id.' removed, and '.$notices->count().' notice(s) with it'.PHP_EOL;
    }

    echo '    kept one: #'.$rows->first()->id.' "'.$rows->first()->title.'"'.PHP_EOL;
}

echo PHP_EOL.'  removed '.$removed.' duplicate(s).'.PHP_EOL;
echo '  the announcements page now shows '.Announcement::query()->count().' notice(s).'.PHP_EOL;
echo PHP_EOL;
