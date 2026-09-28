<?php

namespace App\Actions\Courses\Curriculum;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Storage\LearningMaterialStorage;
use App\Support\MaterialFileRules;
use App\Support\Position;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateLearningMaterial
{
    public function __construct(private readonly LearningMaterialStorage $storage) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Lesson $lesson, array $data, ?UploadedFile $file = null): LearningMaterial
    {
        Gate::forUser($actor)->authorize('create', [LearningMaterial::class, $lesson]);

        $type = LearningMaterialType::from($data['material_type']);

        return DB::transaction(function () use ($actor, $lesson, $data, $type, $file): LearningMaterial {
            $material = new LearningMaterial;
            $material->forceFill([
                'lesson_id' => $lesson->id,
                'uploaded_by' => $actor->id,
                'title' => $data['title'],
                'material_type' => $type,
                'position' => Position::reserve($lesson, $lesson->learningMaterials()),
                'content_text' => $data['content_text'] ?? null,
                'external_url' => $data['external_url'] ?? null,
                'storage_disk' => null,
                'storage_path' => null,
                'mime_type' => null,
                'byte_size' => null,
            ]);
            $material->save();

            if ($file !== null && MaterialFileRules::isFileType($type->value)) {
                $stored = $this->storage->store($file, $type, $material);

                $material->forceFill([
                    'storage_disk' => LearningMaterialStorage::DISK,
                    'storage_path' => $stored['path'],
                    'mime_type' => $stored['mime_type'],
                    'byte_size' => $stored['byte_size'],
                ]);
                $material->save();
            }

            return $material;
        });
    }
}
