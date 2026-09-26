<?php

namespace Database\Factories;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningMaterial>
 */
class LearningMaterialFactory extends Factory
{
    protected $model = LearningMaterial::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'uploaded_by' => User::factory()->instructor(),
            'title' => fake()->sentence(3),
            'material_type' => LearningMaterialType::Text->value,
            'position' => 1,
            'content_text' => fake()->paragraph(),
            'external_url' => null,
            'storage_disk' => null,
            'storage_path' => null,
            'mime_type' => null,
            'byte_size' => null,
        ];
    }
}
