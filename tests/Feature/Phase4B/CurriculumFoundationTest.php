<?php

namespace Tests\Feature\Phase4B;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CurriculumFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_table_contains_the_approved_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('modules'));
        $this->assertTrue(Schema::hasColumns('modules', [
            'id',
            'course_id',
            'title',
            'description',
            'position',
            'status',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('modules'));
        $positionIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['course_id', 'position']);

        $this->assertNotNull($positionIndex);
        $this->assertTrue($positionIndex['unique']);
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['status']));
    }

    public function test_lessons_table_contains_the_approved_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('lessons'));
        $this->assertTrue(Schema::hasColumns('lessons', [
            'id',
            'module_id',
            'title',
            'slug',
            'summary',
            'content_text',
            'position',
            'status',
            'is_required',
            'estimated_minutes',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('lessons'));
        $slugIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['module_id', 'slug']);
        $positionIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['module_id', 'position']);

        $this->assertNotNull($slugIndex);
        $this->assertTrue($slugIndex['unique']);
        $this->assertNotNull($positionIndex);
        $this->assertTrue($positionIndex['unique']);
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['status']));
    }

    public function test_module_factory_creates_a_valid_draft_module_for_a_course(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course, 'course')->create();

        $this->assertSame($course->id, $module->course_id);
        $this->assertSame(ContentStatus::Draft, $module->status);
        $this->assertSame(1, $module->position);
        $this->assertTrue($course->modules->contains($module));
    }

    public function test_lesson_factory_creates_a_valid_required_draft_lesson_for_a_module(): void
    {
        $module = Module::factory()->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        $this->assertSame($module->id, $lesson->module_id);
        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertTrue($lesson->is_required);
        $this->assertNull($lesson->estimated_minutes);
        $this->assertSame(1, $lesson->position);
        $this->assertTrue($module->lessons->contains($lesson));
    }

    public function test_module_cannot_reference_a_missing_course(): void
    {
        $this->expectException(QueryException::class);

        $this->insertModule(['course_id' => 999999]);
    }

    public function test_lesson_cannot_reference_a_missing_module(): void
    {
        $this->expectException(QueryException::class);

        $this->insertLesson(['module_id' => 999999]);
    }

    public function test_module_position_must_be_positive(): void
    {
        $this->expectException(QueryException::class);

        $this->insertModule(['position' => 0]);
    }

    public function test_lesson_position_must_be_positive(): void
    {
        $this->expectException(QueryException::class);

        $this->insertLesson(['position' => 0]);
    }

    public function test_estimated_minutes_must_be_positive_when_present(): void
    {
        $this->expectException(QueryException::class);

        $this->insertLesson(['estimated_minutes' => 0]);
    }

    public function test_invalid_module_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->insertModule(['status' => 'hidden']);
    }

    public function test_invalid_lesson_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->insertLesson(['status' => 'hidden']);
    }

    public function test_module_position_is_unique_within_a_course(): void
    {
        $course = Course::factory()->create();
        $this->insertModule(['course_id' => $course->id, 'position' => 1]);

        $this->expectException(QueryException::class);

        $this->insertModule([
            'course_id' => $course->id,
            'title' => 'Second module',
            'slug' => 'second-module',
            'position' => 1,
        ]);
    }

    public function test_lesson_slug_and_position_are_unique_within_a_module(): void
    {
        $module = Module::factory()->create();
        $this->insertLesson([
            'module_id' => $module->id,
            'slug' => 'introduction',
            'position' => 1,
        ]);

        try {
            $this->insertLesson([
                'module_id' => $module->id,
                'title' => 'Duplicate slug',
                'slug' => 'introduction',
                'position' => 2,
            ]);
            $this->fail('Duplicate Lesson slug should be rejected.');
        } catch (QueryException) {
            // Expected.
        }

        $this->expectException(QueryException::class);

        $this->insertLesson([
            'module_id' => $module->id,
            'title' => 'Duplicate position',
            'slug' => 'another-slug',
            'position' => 1,
        ]);
    }

    public function test_server_owned_module_and_lesson_fields_are_not_mass_assignable(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        $module->fill([
            'course_id' => 999999,
            'position' => 99,
            'status' => ContentStatus::Published,
        ]);
        $lesson->fill([
            'module_id' => 999999,
            'slug' => 'client-slug',
            'position' => 99,
            'status' => ContentStatus::Published,
            'is_required' => false,
        ]);

        $this->assertSame($course->id, $module->course_id);
        $this->assertSame(1, $module->position);
        $this->assertSame(ContentStatus::Draft, $module->status);
        $this->assertSame($module->id, $lesson->module_id);
        $this->assertNotSame('client-slug', $lesson->slug);
        $this->assertSame(1, $lesson->position);
        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertFalse($lesson->is_required);
    }

    public function test_curriculum_slice_has_no_routes_or_later_workflows(): void
    {
        $this->assertFalse(Route::has('instructor.courses.modules.index'));
        $this->assertFalse(Route::has('student.learn.index'));
        $this->assertFalse(Schema::hasTable('enrollments'));
        $this->assertFalse(Schema::hasTable('payments'));
    }

    private function insertModule(array $overrides = []): void
    {
        $courseId = $overrides['course_id'] ?? Course::factory()->create()->id;

        DB::table('modules')->insert(array_merge([
            'course_id' => $courseId,
            'title' => 'Course Module',
            'description' => 'A safe module.',
            'position' => 1,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function insertLesson(array $overrides = []): void
    {
        $moduleId = $overrides['module_id'] ?? Module::factory()->create()->id;

        DB::table('lessons')->insert(array_merge([
            'module_id' => $moduleId,
            'title' => 'Course Lesson',
            'slug' => 'course-lesson-'.fake()->unique()->numerify('####'),
            'summary' => 'A safe lesson.',
            'content_text' => null,
            'position' => 1,
            'status' => 'draft',
            'is_required' => true,
            'estimated_minutes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
