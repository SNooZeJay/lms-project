<?php

namespace Tests\Feature\Phase5B;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CurriculumAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_add_a_module_to_an_owned_course(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();

        $response = $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/modules", [
            'title' => 'Module One',
            'description' => 'The first module.',
        ]);

        $module = Module::query()->where('title', 'Module One')->firstOrFail();

        $response->assertRedirect(route('instructor.courses.show', $course));
        $this->assertSame($course->id, $module->course_id);
        $this->assertSame(1, $module->position);
        $this->assertSame(ContentStatus::Draft, $module->status);
    }

    public function test_server_assigns_increasing_module_positions(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();

        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/modules", [
            'title' => 'Module One',
        ]);
        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/modules", [
            'title' => 'Module Two',
        ]);

        $this->assertSame([1, 2], $course->modules()->orderBy('position')->pluck('position')->all());
    }

    public function test_instructor_can_add_a_lesson_to_an_owned_module(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $module = Module::factory()->for($course, 'course')->create();

        $response = $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/modules/{$module->id}/lessons", [
            'title' => 'Introduction to Computing',
            'summary' => 'Learn the basics.',
            'content_text' => 'Lesson content.',
            'is_required' => true,
            'estimated_minutes' => 30,
        ]);

        $lesson = Lesson::query()->where('title', 'Introduction to Computing')->firstOrFail();

        $response->assertRedirect(route('instructor.courses.show', $course));
        $this->assertSame($module->id, $lesson->module_id);
        $this->assertSame('introduction-to-computing', $lesson->slug);
        $this->assertSame(1, $lesson->position);
        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertTrue($lesson->is_required);
        $this->assertSame(30, $lesson->estimated_minutes);
    }

    public function test_server_generates_unique_lesson_slugs_inside_a_module(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $module = Module::factory()->for($course, 'course')->create();
        $payload = [
            'title' => 'Programming Basics',
            'summary' => 'Learn programming.',
            'is_required' => true,
        ];

        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/modules/{$module->id}/lessons", $payload);
        $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/modules/{$module->id}/lessons", $payload);

        $this->assertCount(2, $module->lessons()->get());
        $this->assertCount(2, $module->lessons()->where('slug', 'like', 'programming-basics%')->get());
    }

    public function test_authoring_rejects_privileged_fields(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $module = Module::factory()->for($course, 'course')->create();

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post("/instructor/courses/{$course->id}/modules", [
                'title' => 'Attempted module',
                'course_id' => 999999,
                'position' => 99,
                'status' => 'published',
            ])
            ->assertSessionHasErrors(['course_id', 'position', 'status']);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post("/instructor/courses/{$course->id}/modules/{$module->id}/lessons", [
                'title' => 'Attempted lesson',
                'module_id' => 999999,
                'position' => 99,
                'status' => 'published',
                'slug' => 'client-slug',
            ])
            ->assertSessionHasErrors(['module_id', 'position', 'status', 'slug']);

        $this->assertDatabaseMissing('modules', ['title' => 'Attempted module']);
        $this->assertDatabaseMissing('lessons', ['title' => 'Attempted lesson']);
    }

    public function test_instructor_cannot_author_content_for_another_instructor(): void
    {
        $instructor = $this->makeInstructor();
        $otherInstructor = $this->makeInstructor();
        $course = Course::factory()->for($otherInstructor, 'instructor')->create();
        $module = Module::factory()->for($course, 'course')->create();

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/modules", ['title' => 'Forbidden module'])
            ->assertForbidden();

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/modules/{$module->id}/lessons", ['title' => 'Forbidden lesson'])
            ->assertForbidden();
    }

    public function test_student_cannot_author_curriculum(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $module = Module::factory()->for($course, 'course')->create();

        $this->actingAs($student)
            ->post("/instructor/courses/{$course->id}/modules", ['title' => 'Forbidden module'])
            ->assertForbidden();

        $this->actingAs($student)
            ->post("/instructor/courses/{$course->id}/modules/{$module->id}/lessons", ['title' => 'Forbidden lesson'])
            ->assertForbidden();
    }

    public function test_outline_page_exposes_add_module_and_add_lesson_forms(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $module = Module::factory()->for($course, 'course')->create();

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Add module')
            ->assertSee('Add lesson')
            ->assertSee('name="title"', false);
    }

    public function test_failed_lesson_form_keeps_input_and_stays_open(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $module = Module::factory()->for($course, 'course')->create();

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post("/instructor/courses/{$course->id}/modules/{$module->id}/lessons", [
                'form_context' => "lesson:{$module->id}",
                'title' => '',
                'content_text' => 'Long lesson draft text.',
            ])
            ->assertSessionHasErrors('title');

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Long lesson draft text.', false)
            ->assertSee('<details class="border-t border-line bg-surface-muted px-5 py-4" open>', false);
    }

    public function test_failed_module_form_keeps_input(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();

        $this->actingAs($instructor)
            ->from(route('instructor.courses.show', $course))
            ->post("/instructor/courses/{$course->id}/modules", [
                'form_context' => 'module',
                'title' => '',
                'description' => 'Module draft description.',
            ])
            ->assertSessionHasErrors('title');

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Module draft description.', false);
    }

    public function test_phase_five_b_does_not_add_public_or_upload_routes(): void
    {
        $this->assertFalse(Route::has('courses.index'));
        $this->assertFalse(Route::has('instructor.courses.materials.store'));
        $this->assertFalse(Route::has('student.materials.download'));
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }
}
