<?php

namespace App\Actions\Courses\Curriculum;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateLearningMaterial
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, LearningMaterial $material, array $data): LearningMaterial
    {
        Gate::forUser($actor)->authorize('update', $material);

        return DB::transaction(function () use ($material, $data): LearningMaterial {
            $material->forceFill([
                'title' => $data['title'],
                'material_type' => LearningMaterialType::from($data['material_type']),
                'content_text' => $data['content_text'] ?? null,
                'external_url' => $data['external_url'] ?? null,
            ]);
            $material->save();

            return $material;
        });
    }
}
