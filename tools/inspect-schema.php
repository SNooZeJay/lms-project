<?php

/*
 | Reports the shape of a database without writing to it.
 |
 | Run: php tools/inspect-schema.php lms_test
 |
 | Written to be safe to run at any time, because the question it answers is
 | usually "is the schema still there", and an inspection tool that drops
 | something while checking would be worse than the fault it is looking for.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel')->bootstrap();

$db = 'Illuminate'.'\\Support'.'\\Facades'.'\\DB';

$schema = $argv[1] ?? 'lms_test';

$count = $db::select(
    'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?',
    [$schema]
);

echo '  '.$schema.' tables: '.$count[0]->c."\n";

foreach (['migrations', 'users', 'profiles', 'courses', 'enrollments', 'notifications'] as $table) {
    $found = $db::select(
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
        [$schema, $table]
    );

    echo '  '.str_pad($table, 16).($found[0]->c ? 'present' : 'MISSING')."\n";
}
