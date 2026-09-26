<?php

namespace App\Http\Controllers;

use App\Models\LearningMaterial;
use App\Services\Storage\LearningMaterialStorage;
use App\Support\MaterialFileRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every protected file download goes through this controller.
 *
 * No route serves a Storage path directly, and the stored MIME type plus a
 * sanitized filename come from the record, never from the request. The parent
 * Course and Lesson are checked here so a material can never be fetched
 * through a mismatched address.
 *
 * The identifiers are read from the route rather than injected, because the
 * three download routes do not share one parameter order.
 */
class MaterialDownloadController extends Controller
{
    public function __construct(private readonly LearningMaterialStorage $storage) {}

    public function show(Request $request): Response
    {
        $record = LearningMaterial::query()->find($request->route('material'));

        abort_if($record === null, 404);

        $lessonId = $request->route('lesson');
        $courseId = $request->route('course');

        if ($lessonId !== null) {
            abort_unless($record->lesson_id === (int) $lessonId, 404);
        }

        if ($courseId !== null) {
            abort_unless($record->lesson->module->course_id === (int) $courseId, 404);
        }

        Gate::authorize('download', $record);

        abort_unless($record->storage_path !== null && $record->storage_path !== '', 404);

        $contents = $this->storage->contents($record->storage_path);

        abort_if($contents === null, 404);

        $filename = MaterialFileRules::downloadFilename($record->material_type, $record->title);

        return response($contents, 200, [
            'Content-Type' => $record->mime_type ?? 'application/octet-stream',
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
