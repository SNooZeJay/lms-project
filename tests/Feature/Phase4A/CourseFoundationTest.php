<?php

namespace Tests\Feature\Phase4A;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_table_contains_the_approved_columns(): void
    {
        $this->assertTrue(Schema::hasTable('courses'));

        $this->assertTrue(Schema::hasColumns('courses', [
            'id',
            'instructor_id',
            'title',
            'slug',
            'description',
            'learning_objectives',
            'category',
            'level',
            'course_type',
            'price_minor',
            'currency',
            'status',
            'thumbnail_path',
            'published_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_courses_table_contains_the_approved_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('courses'));

        $slugIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['slug']);

        $this->assertNotNull($slugIndex);
        $this->assertTrue($slugIndex['unique']);
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['instructor_id']));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['status', 'course_type']));
        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['columns'] === ['category']));
    }

    public function test_course_factory_creates_a_valid_instructor_owned_course(): void
    {
        $course = Course::factory()->create();

        $this->assertSame(CourseLevel::Beginner, $course->level);
        $this->assertSame(CourseType::Free, $course->course_type);
        $this->assertSame(0, $course->price_minor);
        $this->assertSame('PHP', $course->currency);
        $this->assertSame(CourseStatus::Draft, $course->status);
        $this->assertNull($course->published_at);
        $this->assertSame(UserRole::Instructor, $course->instructor->profile->role);
        $this->assertTrue($course->instructor->ownedCourses->contains($course));
    }

    public function test_free_course_cannot_have_a_positive_price(): void
    {
        $this->expectException(QueryException::class);

        $this->insertCourse([
            'course_type' => 'free',
            'price_minor' => 100,
        ]);
    }

    public function test_paid_course_cannot_have_a_zero_price(): void
    {
        $this->expectException(QueryException::class);

        $this->insertCourse([
            'course_type' => 'paid',
            'price_minor' => 0,
        ]);
    }

    public function test_invalid_level_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->insertCourse(['level' => 'expert']);
    }

    public function test_invalid_course_type_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->insertCourse(['course_type' => 'subscription']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->insertCourse(['status' => 'hidden']);
    }

    public function test_non_php_currency_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->insertCourse(['currency' => 'USD']);
    }

    public function test_course_cannot_reference_a_missing_instructor(): void
    {
        $this->expectException(QueryException::class);

        $this->insertCourse(['instructor_id' => 999999]);
    }

    public function test_course_slug_cannot_be_duplicated(): void
    {
        $instructor = $this->makeInstructor();
        $this->insertCourse([
            'instructor_id' => $instructor->id,
            'slug' => 'bsit-foundations',
        ]);

        $this->expectException(QueryException::class);

        $this->insertCourse([
            'instructor_id' => $instructor->id,
            'title' => 'Another course',
            'slug' => 'bsit-foundations',
        ]);
    }

    public function test_server_owned_fields_are_not_mass_assignable(): void
    {
        $course = Course::factory()->create();
        $originalSlug = $course->slug;
        $originalPrice = $course->price_minor;
        $originalStatus = $course->status;
        $originalInstructorId = $course->instructor_id;

        $course->fill([
            'slug' => 'client-supplied-slug',
            'price_minor' => 999,
            'currency' => 'USD',
            'status' => 'published',
            'published_at' => now(),
            'instructor_id' => 999999,
        ]);

        $this->assertSame($originalSlug, $course->slug);
        $this->assertSame($originalPrice, $course->price_minor);
        $this->assertSame($originalStatus, $course->status);
        $this->assertSame($originalInstructorId, $course->instructor_id);
    }

    public function test_phase_four_a_does_not_create_later_business_tables(): void
    {
        // quizzes arrive in Phase 9 and certificates in Phase 10.
        foreach ([
            'payments',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Unexpected Phase 4A table: {$table}");
        }
    }

    public function test_phase_four_a_does_not_add_course_business_routes(): void
    {
        $this->assertFalse(Route::has('student.progress.index'));
        $this->assertFalse(Route::has('instructor.courses.students'));
    }

    private function makeInstructor(): User
    {
        $user = User::factory()->create();
        $user->profile->forceFill([
            'role' => UserRole::Instructor,
        ])->save();

        return $user->fresh();
    }

    private function insertCourse(array $overrides = []): void
    {
        $instructorId = $overrides['instructor_id'] ?? $this->makeInstructor()->id;

        DB::table('courses')->insert(array_merge([
            'instructor_id' => $instructorId,
            'title' => 'BSIT Foundations',
            'slug' => 'bsit-foundations-'.fake()->unique()->numerify('####'),
            'description' => 'A safe test course.',
            'learning_objectives' => 'Learn the fundamentals.',
            'category' => 'Computing',
            'level' => 'beginner',
            'course_type' => 'free',
            'price_minor' => 0,
            'currency' => 'PHP',
            'status' => 'draft',
            'thumbnail_path' => null,
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
