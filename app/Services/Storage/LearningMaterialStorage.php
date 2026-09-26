<?php

namespace App\Services\Storage;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Support\MaterialFileRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores Learning Material files on a private disk under a generated path.
 *
 * The original filename is never used as a path, and the stored MIME type is
 * read from the file itself rather than taken from the request.
 */
class LearningMaterialStorage
{
    public const DISK = 'local';

    /**
     * @return array{path: string, mime_type: string, byte_size: int}
     */
    public function store(UploadedFile $file, LearningMaterialType $type, LearningMaterial $material): array
    {
        $path = $this->pathFor($file, $type, $material);

        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        return [
            'path' => $path,
            'mime_type' => $this->detectMimeType($file, $type),
            'byte_size' => (int) $file->getSize(),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    public function contents(?string $path): ?string
    {
        if (! $this->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->get($path);
    }

    private function pathFor(UploadedFile $file, LearningMaterialType $type, LearningMaterial $material): string
    {
        $extension = MaterialFileRules::extensionOf($file);

        if (! in_array($extension, MaterialFileRules::allowedExtensions($type), true)) {
            $extension = MaterialFileRules::allowedExtensions($type)[0];
        }

        return sprintf(
            'learning-materials/%d/%s.%s',
            $material->lesson_id,
            Str::uuid()->toString(),
            $extension,
        );
    }

    /**
     * The stored MIME type comes from the file, with the allow-list as the ceiling.
     */
    private function detectMimeType(UploadedFile $file, LearningMaterialType $type): string
    {
        $contents = file_get_contents($file->getRealPath());
        $detected = $contents === false ? '' : ((new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '');
        $allowed = MaterialFileRules::allowedMimeTypes($type);

        if ($detected !== '' && in_array($detected, $allowed, true)) {
            return $detected;
        }

        return $allowed[0] ?? 'application/octet-stream';
    }
}
