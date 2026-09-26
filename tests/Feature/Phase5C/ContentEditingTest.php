<?php

namespace Tests\Feature\Phase5C;

use App\Enums\ContentStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ContentEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_edit_an_owned_course(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor, ['title' => 'Old Title', 'price_minor' => 0]);

        $response = $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}", [
            'title' => 'New Title',
            'description' => 'Updated description.',
            'learning_objectives' => 'Updated objectives.',
            'category' => 'Networking',
            'level' => 'intermediate',
            'course_type' => 'free',
            'price_minor' => 0,
        ]);

        $response->assertRedirect(route('instructor.courses.show', $course));

        $course->refresh();

        $this->assertSame('New Title', $course->title);
        $this->assertSame('Updated description.', $course->description);
        $this->assertSame('Updated objectives.', $course->learning_objectives);
        $this->assertSame('Networking', $course->category);
        $this->assertSame('intermediate', $course->level->value);
        $this->assertSame(CourseType::Free, $course->course_type);
    }

    public function test_editing_a_course_keeps_server_owned_fields(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor, ['title' => 'Stable Slug Course', 'price_minor' => 0]);
        $originalSlug = $course->slug;
        $originalOwner = $course->instructor_id;

        $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}", [
            'title' => 'Completely Different Title',
            'level' => 'beginner',
            'course_type' => 'free',
            'price_minor' => 0,
        ])->assertRedirect(route('instructor.courses.show', $course));

        $course->refresh();

        $this->assertSame($originalSlug, $course->slug);
        $this->assertSame($originalOwner, $course->instructor_id);
        $this->assertSame('draft', $course->status->value);
        $this->assertNull($course->published_at);
        $this->assertSame('PHP', $course->currency);
    }

    public function test_editing_a_course_rejects_privileged_fields(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.edit', $course))
            ->patch("/instructor/courses/{$course->id}", [
                'title' => 'Attempted course edit',
                'level' => 'beginner',
                'course_type' => 'free',
                'price_minor' => 0,
                'slug' => 'client-slug',
                'status' => 'published',
                'currency' => 'USD',
                'instructor_id' => 999999,
                'published_at' => '2030-01-01 00:00:00',
            ])
            ->assertSessionHasErrors(['slug', 'status', 'currency', 'instructor_id', 'published_at']);

        $this->assertDatabaseMissing('courses', ['title' => 'Attempted course edit']);
    }

    public function test_editing_a_course_keeps_the_price_rules(): void
    {
        $instructor = $this->makeInstructor();
        $free = $this->makeCourse($instructor, ['course_type' => CourseType::Free, 'price_minor' => 0]);
        $paid = $this->makeCourse($instructor, ['course_type' => CourseType::Paid, 'price_minor' => 50000]);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.edit', $free))
            ->patch("/instructor/courses/{$free->id}", [
                'title' => $free->title,
                'level' => 'beginner',
                'course_type' => 'free',
                'price_minor' => 25000,
            ])
            ->assertSessionHasErrors('price_minor');

        $this->actingAs($instructor)
            ->from(route('instructor.courses.edit', $paid))
            ->patch("/instructor/courses/{$paid->id}", [
                'title' => $paid->title,
                'level' => 'beginner',
                'course_type' => 'paid',
                'price_minor' => 0,
            ])
            ->assertSessionHasErrors('price_minor');

        $this->assertSame(0, $free->refresh()->price_minor);
        $this->assertSame(50000, $paid->refresh()->price_minor);
    }

    public function test_instructor_can_edit_an_owned_module(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);
        $module = Module::factory()->for($course, 'course')->create([
            'title' => 'Old Module',
            'position' => 1,
        ]);

        $response = $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}/modules/{$module->id}", [
            'title' => 'Renamed Module',
            'description' => 'Updated module description.',
        ]);

        $response->assertRedirect(route('instructor.courses.show', $course));

        $module->refresh();

        $this->assertSame('Renamed Module', $module->title);
        $this->assertSame('Updated module description.', $module->description);
        $this->assertSame(1, $module->position);
        $this->assertSame(ContentStatus::Draft, $module->status);
        $this->assertSame($course->id, $module->course_id);
    }

    public function test_editing_a_module_rejects_privileged_fields(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);
        $module = Module::factory()->for($course, 'course')->create();

        $this->actingAs($instructor)
            ->from(route('instructor.courses.modules.edit', [$course, $module]))
            ->patch("/instructor/courses/{$course->id}/modules/{$module->id}", [
                'title' => 'Attempted module edit',
                'course_id' => 999999,
                'position' => 9,
                'status' => 'published',
            ])
            ->assertSessionHasErrors(['course_id', 'position', 'status']);

        $this->assertDatabaseMissing('modules', ['title' => 'Attempted module edit']);
    }

    public function test_instructor_can_edit_an_owned_lesson(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create([
            'title' => 'Old Lesson',
            'position' => 1,
            'is_required' => true,
        ]);

        $response = $this->actingAs($instructor)->patch(
            "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}",
            [
                'title' => 'Renamed Lesson',
                'summary' => 'Updated summary.',
                'content_text' => 'Updated lesson content.',
                'is_required' => false,
                'estimated_minutes' => 45,
            ]
        );

        $response->assertRedirect(route('instructor.courses.show', $course));

        $lesson->refresh();

        $this->assertSame('Renamed Lesson', $lesson->title);
        $this->assertSame('Updated summary.', $lesson->summary);
        $this->assertSame('Updated lesson content.', $lesson->content_text);
        $this->assertFalse($lesson->is_required);
        $this->assertSame(45, $lesson->estimated_minutes);
        $this->assertSame(1, $lesson->position);
        $this->assertSame(ContentStatus::Draft, $lesson->status);
        $this->assertSame($module->id, $lesson->module_id);
    }

    public function test_editing_a_lesson_keeps_its_slug(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();
        $originalSlug = $lesson->slug;

        $this->actingAs($instructor)->patch(
            "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}",
            ['title' => 'Totally New Lesson Title']
        )->assertRedirect(route('instructor.courses.show', $course));

        $this->assertSame($originalSlug, $lesson->refresh()->slug);
    }

    public function test_editing_a_lesson_rejects_privileged_fields(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        $this->actingAs($instructor)
            ->from(route('instructor.courses.modules.lessons.edit', [$course, $module, $lesson]))
            ->patch("/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'title' => 'Attempted lesson edit',
                'module_id' => 999999,
                'position' => 9,
                'status' => 'published',
                'slug' => 'client-slug',
            ])
            ->assertSessionHasErrors(['module_id', 'position', 'status', 'slug']);

        $this->assertDatabaseMissing('lessons', ['title' => 'Attempted lesson edit']);
    }

    public function test_edit_pages_show_current_values(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor, [
            'title' => 'Editable Course',
            'description' => 'Editable description.',
            'category' => 'Databases',
            'course_type' => CourseType::Paid,
            'price_minor' => 12500,
        ]);
        $module = Module::factory()->for($course, 'course')->create(['title' => 'Editable Module']);
        $lesson = Lesson::factory()->for($module, 'module')->create([
            'title' => 'Editable Lesson',
            'estimated_minutes' => 25,
        ]);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.edit', $course))
            ->assertOk()
            ->assertSee('value="Editable Course"', false)
            ->assertSee('value="12500"', false);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.modules.edit', [$course, $module]))
            ->assertOk()
            ->assertSee('value="Editable Module"', false);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.modules.lessons.edit', [$course, $module, $lesson]))
            ->assertOk()
            ->assertSee('value="Editable Lesson"', false)
            ->assertSee('value="25"', false);
    }

    public function test_failed_course_edit_keeps_typed_text(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);

        $this->actingAs($instructor)
            ->from(route('instructor.courses.edit', $course))
            ->patch("/instructor/courses/{$course->id}", [
                'title' => '',
                'description' => 'Unsaved description.',
                'level' => 'beginner',
                'course_type' => 'free',
                'price_minor' => 0,
            ]);

        $this->actingAs($instructor)
            ->get(route('instructor.courses.edit', $course))
            ->assertOk()
            ->assertSee('Check the highlighted fields')
            ->assertSee('The title field is required.')
            ->assertSee('Unsaved description.', false);
    }

    public function test_instructor_cannot_edit_another_instructors_content(): void
    {
        $instructor = $this->makeInstructor();
        $other = $this->makeInstructor();
        $course = $this->makeCourse($other);
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        $this->actingAs($instructor)->get(route('instructor.courses.edit', $course))->assertForbidden();
        $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}", [
            'title' => 'Forbidden edit',
            'level' => 'beginner',
            'course_type' => 'free',
            'price_minor' => 0,
        ])->assertForbidden();

        $this->actingAs($instructor)->get(route('instructor.courses.modules.edit', [$course, $module]))->assertForbidden();
        $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}/modules/{$module->id}", [
            'title' => 'Forbidden edit',
        ])->assertForbidden();

        $this->actingAs($instructor)->get(route('instructor.courses.modules.lessons.edit', [$course, $module, $lesson]))->assertForbidden();
        $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
            'title' => 'Forbidden edit',
        ])->assertForbidden();

        $this->assertDatabaseMissing('courses', ['title' => 'Forbidden edit']);
        $this->assertDatabaseMissing('modules', ['title' => 'Forbidden edit']);
        $this->assertDatabaseMissing('lessons', ['title' => 'Forbidden edit']);
    }

    public function test_student_cannot_edit_content(): void
    {
        $student = User::factory()->create();
        $course = $this->makeCourse($this->makeInstructor());
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        $this->actingAs($student)->get(route('instructor.courses.edit', $course))->assertForbidden();
        $this->actingAs($student)->get(route('instructor.courses.modules.edit', [$course, $module]))->assertForbidden();
        $this->actingAs($student)->get(route('instructor.courses.modules.lessons.edit', [$course, $module, $lesson]))->assertForbidden();
    }

    public function test_edit_forms_reject_a_module_from_another_course(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);
        $otherCourse = $this->makeCourse($instructor);
        $module = Module::factory()->for($otherCourse, 'course')->create();

        $this->actingAs($instructor)
            ->get(route('instructor.courses.modules.edit', [$course, $module]))
            ->assertNotFound();

        $this->actingAs($instructor)
            ->patch("/instructor/courses/{$course->id}/modules/{$module->id}", ['title' => 'Cross course edit'])
            ->assertNotFound();
    }

    public function test_phase_five_c_adds_no_delete_routes(): void
    {
        // Archiving arrives in Phase 7B. Deleting is never added.
        $this->assertFalse(Route::has('instructor.courses.destroy'));
        $this->assertFalse(Route::has('instructor.courses.modules.destroy'));
        $this->assertFalse(Route::has('instructor.courses.modules.lessons.destroy'));
    }

    public function test_outline_page_links_to_edit_pages(): void
    {
        $instructor = $this->makeInstructor();
        $course = $this->makeCourse($instructor);
        $module = Module::factory()->for($course, 'course')->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee(route('instructor.courses.edit', $course), false)
            ->assertSee(route('instructor.courses.modules.edit', [$course, $module]), false)
            ->assertSee(route('instructor.courses.modules.lessons.edit', [$course, $module, $lesson]), false);
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCourse(User $instructor, array $attributes = []): Course
    {
        return Course::factory()->for($instructor, 'instructor')->create($attributes);
    }
}
