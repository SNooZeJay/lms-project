<?php

namespace App\Actions\Courses\Curriculum;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateLearningMaterial
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Lesson $lesson, array $data): LearningMaterial
    {
        Gate::forUser($actor)->authorize('create', [LearningMaterial::class, $lesson]);

        return DB::transaction(function () use ($actor, $lesson, $data): LearningMaterial {
            $material = new LearningMaterial;
            $material->forceFill([
                'lesson_id' => $lesson->id,
                'uploaded_by' => $actor->id,
                'title' => $data['title'],
                'material_type' => LearningMaterialType::from($data['material_type']),
                'position' => ((int) $lesson->learningMaterials()->max('position')) + 1,
                'content_text' => $data['content_text'] ?? null,
                'external_url' => $data['external_url'] ?? null,
                'storage_disk' => null,
                'storage_path' => null,
                'mime_type' => null,
                'byte_size' => null,
            ]);
            $material->save();

            return $material;
        });
    }
}
