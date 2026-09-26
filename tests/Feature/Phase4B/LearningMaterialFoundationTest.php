<?php

namespace Tests\Feature\Phase4B;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearningMaterialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_materials_table_contains_the_approved_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('learning_materials'));
        $this->assertTrue(Schema::hasColumns('learning_materials', [
            'id',
            'lesson_id',
            'uploaded_by',
            'title',
            'material_type',
            'position',
            'content_text',
            'external_url',
            'storage_disk',
            'storage_path',
            'mime_type',
            'byte_size',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('learning_materials'));
        $positionIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['lesson_id', 'position']);
        $storageIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['storage_disk', 'storage_path']);

        $this->assertNotNull($positionIndex);
        $this->assertTrue($positionIndex['unique']);
        $this->assertNotNull($storageIndex);
        $this->assertTrue($storageIndex['unique']);
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['material_type']));
    }

    public function test_learning_material_factory_creates_valid_metadata_for_a_lesson(): void
    {
        $lesson = Lesson::factory()->create();
        $material = LearningMaterial::factory()->for($lesson, 'lesson')->create();

        $this->assertSame($lesson->id, $material->lesson_id);
        $this->assertSame(LearningMaterialType::Text, $material->material_type);
        $this->assertSame(1, $material->position);
        $this->assertTrue($lesson->learningMaterials->contains($material));
        $this->assertTrue($material->uploader->profile->role->value === 'instructor');
        $this->assertTrue($material->uploader->uploadedLearningMaterials->contains($material));
    }

    public function test_learning_material_cannot_reference_a_missing_lesson(): void
    {
        $this->expectException(QueryException::class);

        $this->insertMaterial(['lesson_id' => 999999]);
    }

    public function test_learning_material_cannot_reference_a_missing_uploader(): void
    {
        $this->expectException(QueryException::class);

        $this->insertMaterial(['uploaded_by' => 999999]);
    }

    public function test_material_position_must_be_positive(): void
    {
        $this->expectException(QueryException::class);

        $this->insertMaterial(['position' => 0]);
    }

    public function test_invalid_material_type_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->insertMaterial(['material_type' => 'executable']);
    }

    public function test_material_position_is_unique_within_a_lesson(): void
    {
        $lesson = Lesson::factory()->create();
        $this->insertMaterial(['lesson_id' => $lesson->id, 'position' => 1]);

        $this->expectException(QueryException::class);

        $this->insertMaterial([
            'lesson_id' => $lesson->id,
            'title' => 'Second material',
            'position' => 1,
        ]);
    }

    public function test_storage_path_is_unique_within_a_disk(): void
    {
        $this->insertMaterial([
            'storage_disk' => 'private',
            'storage_path' => 'courses/materials/example.pdf',
        ]);

        $this->expectException(QueryException::class);

        $this->insertMaterial([
            'title' => 'Duplicate storage path',
            'storage_disk' => 'private',
            'storage_path' => 'courses/materials/example.pdf',
        ]);
    }

    public function test_server_owned_material_fields_are_not_mass_assignable(): void
    {
        $material = LearningMaterial::factory()->create();
        $originalLesson = $material->lesson_id;
        $originalUploader = $material->uploaded_by;
        $originalPosition = $material->position;
        $originalPath = $material->storage_path;

        $material->fill([
            'lesson_id' => 999999,
            'uploaded_by' => 999999,
            'position' => 99,
            'storage_disk' => 'public',
            'storage_path' => 'unsafe/path.txt',
            'mime_type' => 'text/plain',
            'byte_size' => 999,
        ]);

        $this->assertSame($originalLesson, $material->lesson_id);
        $this->assertSame($originalUploader, $material->uploaded_by);
        $this->assertSame($originalPosition, $material->position);
        $this->assertNull($originalPath);
        $this->assertNull($material->storage_path);
    }

    public function test_phase_four_b_two_does_not_add_upload_or_curriculum_routes(): void
    {
        $this->assertFalse(Route::has('instructor.courses.materials.upload'));
        $this->assertFalse(Schema::hasTable('quizzes'));
        $this->assertFalse(Schema::hasTable('payments'));
    }

    private function insertMaterial(array $overrides = []): void
    {
        $lessonId = $overrides['lesson_id'] ?? Lesson::factory()->create()->id;
        $uploaderId = $overrides['uploaded_by'] ?? User::factory()->instructor()->create()->id;

        DB::table('learning_materials')->insert(array_merge([
            'lesson_id' => $lessonId,
            'uploaded_by' => $uploaderId,
            'title' => 'Learning Material',
            'material_type' => 'text',
            'position' => 1,
            'content_text' => 'Safe material content.',
            'external_url' => null,
            'storage_disk' => null,
            'storage_path' => null,
            'mime_type' => null,
            'byte_size' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
