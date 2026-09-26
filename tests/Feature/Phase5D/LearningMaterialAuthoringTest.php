<?php

namespace Tests\Feature\Phase5D;

use App\Enums\LearningMaterialType;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LearningMaterialAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_add_a_text_material_to_an_owned_lesson(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        $response = $this->actingAs($instructor)->post($this->materialsUrl($course, $module, $lesson), [
            'title' => 'Reading Notes',
            'material_type' => 'text',
            'content_text' => 'Key points for this lesson.',
        ]);

        $material = LearningMaterial::query()->where('title', 'Reading Notes')->firstOrFail();

        $response->assertRedirect(route('instructor.courses.show', $course));
        $this->assertSame($lesson->id, $material->lesson_id);
        $this->assertSame($instructor->id, $material->uploaded_by);
        $this->assertSame(LearningMaterialType::Text, $material->material_type);
        $this->assertSame(1, $material->position);
        $this->assertNull($material->storage_path);
        $this->assertNull($material->storage_disk);
        $this->assertNull($material->mime_type);
        $this->assertNull($material->byte_size);
    }

    public function test_instructor_can_add_a_link_material(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        $this->actingAs($instructor)->post($this->materialsUrl($course, $module, $lesson), [
            'title' => 'PHP Manual',
            'material_type' => 'external_link',
            'external_url' => 'https://www.php.net/manual/en/',
        ])->assertRedirect(route('instructor.courses.show', $course));

        $material = LearningMaterial::query()->where('title', 'PHP Manual')->firstOrFail();

        $this->assertSame(LearningMaterialType::ExternalLink, $material->material_type);
        $this->assertSame('https://www.php.net/manual/en/', $material->external_url);
    }

    public function test_server_assigns_increasing_material_positions(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        $this->actingAs($instructor)->post($this->materialsUrl($course, $module, $lesson), [
            'title' => 'First Material',
            'material_type' => 'text',
            'content_text' => 'First content.',
        ]);
        $this->actingAs($instructor)->post($this->materialsUrl($course, $module, $lesson), [
            'title' => 'Second Material',
            'material_type' => 'text',
            'content_text' => 'Second content.',
        ]);

        $this->assertSame([1, 2], $lesson->learningMaterials()->orderBy('position')->pluck('position')->all());
    }

    public function test_material_requires_content_for_text_and_code(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post($this->materialsUrl($course, $module, $lesson), [
                'title' => 'Empty Text Material',
                'material_type' => 'text',
            ])
            ->assertSessionHasErrors('content_text');

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post($this->materialsUrl($course, $module, $lesson), [
                'title' => 'Empty Code Material',
                'material_type' => 'code',
            ])
            ->assertSessionHasErrors('content_text');

        $this->assertDatabaseMissing('learning_materials', ['title' => 'Empty Text Material']);
    }

    public function test_material_requires_a_valid_link_for_link_types(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post($this->materialsUrl($course, $module, $lesson), [
                'title' => 'Missing Link',
                'material_type' => 'video_link',
            ])
            ->assertSessionHasErrors('external_url');

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post($this->materialsUrl($course, $module, $lesson), [
                'title' => 'Bad Link',
                'material_type' => 'external_link',
                'external_url' => 'not-a-real-link',
            ])
            ->assertSessionHasErrors('external_url');

        $this->assertDatabaseMissing('learning_materials', ['title' => 'Missing Link']);
    }

    public function test_file_material_types_need_a_file(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        foreach (['image', 'pdf', 'document'] as $type) {
            $this->actingAs($instructor)
                ->from(route('instructor.courses.show', $course))
                ->post($this->materialsUrl($course, $module, $lesson), [
                    'title' => "Attempted {$type} material",
                    'material_type' => $type,
                ])
                ->assertSessionHasErrors('file');
        }

        $this->assertDatabaseMissing('learning_materials', ['title' => 'Attempted image material']);
    }

    public function test_material_upload_and_storage_fields_are_rejected(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post($this->materialsUrl($course, $module, $lesson), [
                'title' => 'Attempted upload material',
                'material_type' => 'text',
                'content_text' => 'Content.',
                'lesson_id' => 999999,
                'uploaded_by' => 999999,
                'position' => 9,
                'storage_disk' => 'public',
                'storage_path' => 'materials/secret.pdf',
                'mime_type' => 'application/pdf',
                'byte_size' => 12345,
                'file' => UploadedFile::fake()->create('notes.pdf', 10),
            ])
            ->assertSessionHasErrors([
                'lesson_id',
                'uploaded_by',
                'position',
                'storage_disk',
                'storage_path',
                'mime_type',
                'byte_size',
            ]);

        $this->assertDatabaseMissing('learning_materials', ['title' => 'Attempted upload material']);
    }

    public function test_instructor_can_edit_an_owned_material(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);
        $material = LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'Old Material',
            'material_type' => LearningMaterialType::Text,
            'position' => 1,
        ]);

        $response = $this->actingAs($instructor)->patch($this->materialUrl($course, $module, $lesson, $material), [
            'title' => 'Updated Material',
            'material_type' => 'text',
            'content_text' => 'Updated content.',
        ]);

        $response->assertRedirect(route('instructor.courses.show', $course));

        $material->refresh();

        $this->assertSame('Updated Material', $material->title);
        $this->assertSame('Updated content.', $material->content_text);
        $this->assertSame(1, $material->position);
        $this->assertSame($lesson->id, $material->lesson_id);
    }

    public function test_editing_a_material_keeps_server_owned_fields(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);
        $material = LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'position' => 2,
            'material_type' => LearningMaterialType::ExternalLink,
            'external_url' => 'https://example.com/old',
        ]);
        $originalUploader = $material->uploaded_by;

        $this->actingAs($instructor)->patch($this->materialUrl($course, $module, $lesson, $material), [
            'title' => 'Updated Link Material',
            'material_type' => 'external_link',
            'external_url' => 'https://example.com/new',
        ])->assertRedirect(route('instructor.courses.show', $course));

        $material->refresh();

        $this->assertSame(2, $material->position);
        $this->assertSame($originalUploader, $material->uploaded_by);
        $this->assertNull($material->storage_path);
    }

    public function test_edit_material_page_shows_current_values(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);
        $material = LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'Editable Material',
            'material_type' => LearningMaterialType::VideoLink,
            'external_url' => 'https://example.com/watch',
        ]);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.materials.edit', [$course, $module, $lesson, $material]))
            ->assertOk()
            ->assertSee('value="Editable Material"', false)
            ->assertSee('value="https://example.com/watch"', false);
    }

    public function test_failed_material_form_keeps_input(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post($this->materialsUrl($course, $module, $lesson), [
                'form_context' => "material:{$lesson->id}",
                'title' => '',
                'material_type' => 'text',
                'content_text' => 'Unsaved material content.',
            ]);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Check the highlighted fields')
            ->assertSee('Unsaved material content.', false);
    }

    public function test_instructor_cannot_author_materials_for_another_instructor(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($this->makeInstructor());
        $material = LearningMaterial::factory()->for($lesson, 'lesson')->create();

        $this->actingAs($instructor)
            ->post($this->materialsUrl($course, $module, $lesson), [
                'title' => 'Forbidden material',
                'material_type' => 'text',
                'content_text' => 'Content.',
            ])
            ->assertForbidden();

        $this->actingAs($instructor)
            ->patch($this->materialUrl($course, $module, $lesson, $material), [
                'title' => 'Forbidden material',
                'material_type' => 'text',
                'content_text' => 'Content.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('learning_materials', ['title' => 'Forbidden material']);
    }

    public function test_student_cannot_author_materials(): void
    {
        $student = User::factory()->create();
        [$course, $module, $lesson] = $this->makeLesson($this->makeInstructor());

        $this->actingAs($student)
            ->post($this->materialsUrl($course, $module, $lesson), [
                'title' => 'Forbidden material',
                'material_type' => 'text',
                'content_text' => 'Content.',
            ])
            ->assertForbidden();
    }

    public function test_material_forms_reject_content_from_another_lesson(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);
        $otherLesson = Lesson::factory()->for($module, 'module')->create(['position' => 2]);
        $material = LearningMaterial::factory()->for($otherLesson, 'lesson')->create();

        $this->actingAs($instructor)
            ->get(route('instructor.courses.materials.edit', [$course, $module, $lesson, $material]))
            ->assertNotFound();

        $this->actingAs($instructor)
            ->patch($this->materialUrl($course, $module, $lesson, $material), [
                'title' => 'Cross lesson material',
                'material_type' => 'text',
                'content_text' => 'Content.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('learning_materials', ['title' => 'Cross lesson material']);
    }

    public function test_outline_page_shows_material_forms_and_edit_links(): void
    {
        $instructor = $this->makeInstructor();
        [$course, $module, $lesson] = $this->makeLesson($instructor);
        $material = LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'Outline Material',
            'position' => 1,
        ]);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Outline Material')
            ->assertSee('Add material')
            ->assertSee('Material 1')
            ->assertSee('Material '.$material->position)
            ->assertSee(route('instructor.courses.materials.edit', [$course, $module, $lesson, $material]), false);
    }

    public function test_phase_five_d_adds_no_delete_routes(): void
    {
        // Uploads and downloads arrive in Phase 7C. Deleting is never added.
        $this->assertFalse(Route::has('instructor.courses.materials.destroy'));
        $this->assertFalse(Route::has('student.materials.show'));
        $this->assertFalse(Route::has('student.materials.destroy'));
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    /**
     * @return array{0: Course, 1: Module, 2: Lesson}
     */
    private function makeLesson(User $instructor): array
    {
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        return [$course, $module, $lesson];
    }

    private function materialsUrl(Course $course, Module $module, Lesson $lesson): string
    {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/materials";
    }

    private function materialUrl(Course $course, Module $module, Lesson $lesson, LearningMaterial $material): string
    {
        return $this->materialsUrl($course, $module, $lesson)."/{$material->id}";
    }
}
