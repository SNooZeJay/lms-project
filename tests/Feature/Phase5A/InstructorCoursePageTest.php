<?php

namespace Tests\Feature\Phase5A;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\LearningMaterialType;
use App\Enums\UserAccountStatus;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstructorCoursePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_open_course_list_and_see_only_owned_courses(): void
    {
        $instructor = $this->makeInstructor();
        $otherInstructor = $this->makeInstructor();
        Course::factory()->for($instructor, 'instructor')->create(['title' => 'Owned Course']);
        Course::factory()->for($otherInstructor, 'instructor')->create(['title' => 'Other Course']);

        $response = $this->actingAs($instructor)->get('/instructor/courses');

        $response->assertOk()
            ->assertSee('Owned Course')
            ->assertDontSee('Other Course');
    }

    public function test_instructor_can_open_the_create_course_page(): void
    {
        $instructor = $this->makeInstructor();

        $response = $this->actingAs($instructor)->get('/instructor/courses/new');

        $response->assertOk()
            ->assertSee('Create course')
            ->assertSee('name="title"', false)
            ->assertSee('name="course_type"', false)
            ->assertSee('name="price_minor"', false);
    }

    public function test_instructor_can_create_a_free_course_as_a_private_draft(): void
    {
        $instructor = $this->makeInstructor();

        $response = $this->actingAs($instructor)->post('/instructor/courses', [
            'title' => 'BSIT Foundations',
            'description' => 'A practical introduction to computing.',
            'learning_objectives' => 'Understand the fundamentals.',
            'category' => 'Computing',
            'level' => CourseLevel::Beginner->value,
            'course_type' => CourseType::Free->value,
            'price_minor' => 0,
        ]);

        $course = Course::query()->where('title', 'BSIT Foundations')->firstOrFail();

        $response->assertRedirect(route('instructor.courses.show', $course));
        $this->assertSame($instructor->id, $course->instructor_id);
        $this->assertSame('bsit-foundations', $course->slug);
        $this->assertSame(CourseStatus::Draft, $course->status);
        $this->assertSame('PHP', $course->currency);
        $this->assertSame(0, $course->price_minor);
        $this->assertNull($course->published_at);
    }

    public function test_create_course_generates_a_unique_slug_for_duplicate_titles(): void
    {
        $instructor = $this->makeInstructor();
        $payload = [
            'title' => 'Programming Basics',
            'description' => 'Learn programming.',
            'learning_objectives' => 'Write small programs.',
            'category' => 'Programming',
            'level' => CourseLevel::Beginner->value,
            'course_type' => CourseType::Free->value,
            'price_minor' => 0,
        ];

        $this->actingAs($instructor)->post('/instructor/courses', $payload);
        $this->actingAs($instructor)->post('/instructor/courses', $payload);

        $this->assertCount(2, Course::query()->where('title', 'Programming Basics')->get());
        $this->assertCount(2, Course::query()->where('slug', 'like', 'programming-basics%')->get());
    }

    public function test_create_course_rejects_privileged_fields(): void
    {
        $instructor = $this->makeInstructor();

        $response = $this->actingAs($instructor)
            ->from('/instructor/courses/new')
            ->post('/instructor/courses', [
                'title' => 'Attempted Override',
                'description' => 'Safe description.',
                'learning_objectives' => 'Safe objectives.',
                'category' => 'Computing',
                'level' => CourseLevel::Beginner->value,
                'course_type' => CourseType::Free->value,
                'price_minor' => 0,
                'slug' => 'client-slug',
                'status' => 'published',
                'currency' => 'USD',
                'published_at' => now(),
                'instructor_id' => 999999,
                'thumbnail_path' => 'public/unsafe.png',
            ]);

        $response->assertSessionHasErrors(['slug', 'status', 'currency', 'published_at', 'instructor_id', 'thumbnail_path']);
        $this->assertDatabaseMissing('courses', ['title' => 'Attempted Override']);
    }

    public function test_create_course_rejects_invalid_free_and_paid_price_combinations(): void
    {
        $instructor = $this->makeInstructor();

        $this->actingAs($instructor)
            ->from('/instructor/courses/new')
            ->post('/instructor/courses', [
                'title' => 'Paid Course',
                'description' => 'Safe description.',
                'learning_objectives' => 'Safe objectives.',
                'category' => 'Computing',
                'level' => CourseLevel::Beginner->value,
                'course_type' => CourseType::Paid->value,
                'price_minor' => 0,
            ])
            ->assertSessionHasErrors('price_minor');

        $this->actingAs($instructor)
            ->from('/instructor/courses/new')
            ->post('/instructor/courses', [
                'title' => 'Free Course',
                'description' => 'Safe description.',
                'learning_objectives' => 'Safe objectives.',
                'category' => 'Computing',
                'level' => CourseLevel::Beginner->value,
                'course_type' => CourseType::Free->value,
                'price_minor' => 100,
            ])
            ->assertSessionHasErrors('price_minor');
    }

    public function test_instructor_can_open_an_owned_course_outline_with_curriculum_metadata(): void
    {
        $instructor = $this->makeInstructor();
        $course = Course::factory()->for($instructor, 'instructor')->create(['title' => 'Outline Course']);
        $module = Module::factory()->for($course, 'course')->create(['title' => 'Module One']);
        $lesson = Lesson::factory()->for($module, 'module')->create(['title' => 'Lesson One']);
        LearningMaterial::factory()->for($lesson, 'lesson')->create([
            'title' => 'Reading Material',
            'material_type' => LearningMaterialType::Text->value,
        ]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk()
            ->assertSee('Outline Course')
            ->assertSee('Module One')
            ->assertSee('Lesson One')
            ->assertSee('Reading Material')
            ->assertSee('Text');
    }

    public function test_instructor_cannot_open_another_instructors_course(): void
    {
        $instructor = $this->makeInstructor();
        $otherInstructor = $this->makeInstructor();
        $course = Course::factory()->for($otherInstructor, 'instructor')->create();

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertForbidden();
    }

    public function test_student_cannot_open_instructor_course_pages(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/instructor/courses')->assertForbidden();
        $this->actingAs($student)->get('/instructor/courses/new')->assertForbidden();
    }

    public function test_suspended_instructor_cannot_open_course_pages(): void
    {
        $instructor = $this->makeInstructor();
        $instructor->profile->forceFill([
            'account_status' => UserAccountStatus::Suspended,
        ])->save();

        $this->actingAs($instructor)
            ->get('/instructor/courses')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_phase_five_a_has_no_public_course_or_payment_routes(): void
    {
        $this->assertFalse(Route::has('payments.index'));
        $this->assertFalse(Route::has('student.enrollments.index'));
        $this->assertFalse(Route::has('student.lessons.complete'));
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }
}
