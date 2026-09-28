<?php

use App\Models\LearningMaterial;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

/*
 | Leaves exactly one file material in the catalog, and no probe duplicates.
 |
 | Proving the private file path means posting a real material, and every run
 | leaves one behind. Two are useful in a demonstration and five are clutter that
 | reads as somebody having clicked upload repeatedly. The newest of each title is
 | kept and the rest are removed with their files, so the catalog keeps a real
 | stored file without the residue of testing.
 */
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$titles = ['Course notes, private download', 'Course notes (private download)'];

echo PHP_EOL.'  the materials with a stored file:'.PHP_EOL;

$rows = LearningMaterial::query()
    ->whereIn('title', $titles)
    ->orderByDesc('id')
    ->get();

$kept = false;
foreach ($rows as $row) {
    if (! $kept) {
        $kept = true;
        $row->forceFill(['title' => 'Course notes (private download)'])->save();
        echo '    #'.$row->id.'  kept'.PHP_EOL;

        continue;
    }
    if ($row->storage_path) {
        Storage::disk('local')->delete($row->storage_path);
    }
    $row->delete();
    echo '    #'.$row->id.'  removed'.PHP_EOL;
}

$total = LearningMaterial::query()->count();
$withFile = LearningMaterial::query()->whereNotNull('storage_path')->where('storage_path', '!=', '')->count();
echo PHP_EOL.'  the catalog has '.$total.' materials, '.$withFile.' with a real stored file.'.PHP_EOL.PHP_EOL;
