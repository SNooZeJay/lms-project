<?php

/**
 * Prints every conversation query the message list page runs.
 *
 * `TopbarMessagingCostTest` allows a fixed number of conversation queries on the
 * messages page and asks that anything beyond the documented set be justified.
 * Justifying it by eye would be a guess, so this prints the actual statements and
 * the count that goes with each.
 *
 * Usage:  php tools/probe-message-page-queries.php
 */

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// A reader who is genuinely in threads, so the page has work to do.
$thread = Conversation::query()->withCount('messages')->get()->sortByDesc('messages_count')->first();

if ($thread === null) {
    echo PHP_EOL.'  no conversations exist, so there is nothing to probe'.PHP_EOL.PHP_EOL;

    return;
}

$user = $thread->participants()->first()?->user ?? User::query()->first();
Auth::setUser($user);

DB::enableQueryLog();
DB::flushQueryLog();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(Request::create('/messages', 'GET'));

$status = $response->getStatusCode();

echo PHP_EOL;
echo '  /messages as '.$user->name.'  ->  '.$status.PHP_EOL;
echo PHP_EOL;

$n = 0;

foreach (DB::getQueryLog() as $entry) {
    $sql = (string) $entry['query'];

    if (! str_contains($sql, 'conversation')) {
        continue;
    }

    $n++;
    $short = preg_replace('/\s+/', ' ', $sql);

    printf("  %2d  %s%s\n", $n, mb_substr($short, 0, 130), strlen($short) > 130 ? ' …' : '');
}

printf("\n  %d conversation queries on the message list page\n\n", $n);
