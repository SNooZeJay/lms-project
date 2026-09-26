<?php

namespace App\Support;

use App\Enums\LearningMaterialType;
use Illuminate\Http\UploadedFile;

/**
 * The only allow-list for uploaded learning material.
 *
 * Extensions, real MIME types, and size are all checked. An extension that is
 * not on the list is rejected even when the browser claims a safe MIME type.
 */
class MaterialFileRules
{
    /**
     * Learning Material types that are backed by an uploaded file.
     *
     * @var list<string>
     */
    public const FILE_TYPES = ['image', 'pdf', 'document'];

    /**
     * Maximum stored size, 10 MiB.
     */
    public const MAX_KILOBYTES = 10240;

    /**
     * @return list<string>
     */
    public static function allowedExtensions(LearningMaterialType $type): array
    {
        return match ($type) {
            LearningMaterialType::Image => ['jpg', 'jpeg', 'png', 'webp'],
            LearningMaterialType::Pdf => ['pdf'],
            LearningMaterialType::Document => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function allowedMimeTypes(LearningMaterialType $type): array
    {
        return match ($type) {
            LearningMaterialType::Image => ['image/jpeg', 'image/png', 'image/webp'],
            LearningMaterialType::Pdf => ['application/pdf'],
            LearningMaterialType::Document => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
            ],
            default => [],
        };
    }

    public static function isFileType(?string $value): bool
    {
        return $value !== null && in_array($value, self::FILE_TYPES, true);
    }

    /**
     * A safe download name built from the material title.
     */
    public static function downloadFilename(LearningMaterialType $type, string $title): string
    {
        $extension = match ($type) {
            LearningMaterialType::Image => 'jpg',
            LearningMaterialType::Pdf => 'pdf',
            LearningMaterialType::Document => 'pdf',
            default => 'bin',
        };

        $slug = preg_replace('/[^a-z0-9]+/i', '-', $title) ?? '';
        $slug = trim(strtolower($slug), '-');
        $slug = substr($slug === '' ? 'material' : $slug, 0, 80);

        return $slug.'.'.$extension;
    }

    public static function extensionOf(UploadedFile $file): string
    {
        return strtolower($file->getClientOriginalExtension());
    }
}
