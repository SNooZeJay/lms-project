<?php

/*
 | Reports what a material actually holds.
 |
 | The download controller refuses a material that has no stored file, and
 | answers 404 rather than an error. That is correct behaviour, and it looks
 | identical from the outside to a material that was hidden on purpose. So the
 | question "is this 404 a defect or the rule working" is answered by looking at
 | the record, not by guessing.
 |
 | Run: php tools/inspect-material.php [id]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel')->bootstrap();

$m = 'App'.'\\Models'.'\\LearningMaterial';
$db = 'Illuminate'.'\\Support'.'\\Facades'.'\\DB';

$id = isset($argv[1]) ? (int) $argv[1] : $db::table('learning_materials')->orderBy('id')->value('id');

echo "  material $id\n\n";

$row = $db::table('learning_materials')->where('id', $id)->first();

if ($row === null) {
    echo "  no such material\n";
    exit(0);
}

foreach (['id', 'lesson_id', 'material_type', 'title', 'storage_path', 'mime_type', 'external_url'] as $column) {
    $value = $row->{$column} ?? null;
    echo '  '.str_pad($column, 14).(is_null($value) ? 'NULL' : '"'.substr((string) $value, 0, 60).'"')."\n";
}

echo "\n  counts across every material\n";
echo '  '.sprintf("total %d, with a stored file %d, external link %d, text only %d\n",
    $db::table('learning_materials')->count(),
    $db::table('learning_materials')->whereNotNull('storage_path')->where('storage_path', '!=', '')->count(),
    $db::table('learning_materials')->whereNotNull('external_url')->count(),
    $db::table('learning_materials')
        ->where(fn ($q) => $q->whereNull('storage_path')->orWhere('storage_path', '=', ''))
        ->whereNotNull('content_text')
        ->count()
);

if ($row->storage_path !== null && $row->storage_path !== '') {
    $full = storage_path('app/private/'.$row->storage_path);

    echo "\n  resolved path : ".$full."\n";
    echo '  file exists   : '.(is_file($full) ? 'yes' : 'NO')."\n";
}
