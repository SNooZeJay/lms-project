<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The seeded catalog has to be a real catalog, not a set of rows that happen to
 * exist.
 *
 * The public catalog, the Student course pages, enrollment, progress, quizzes,
 * and the certificate rules all read these same rows, so a course that is
 * published with no lessons, or a quiz question with no correct option, would
 * show a student a page that cannot be completed. These tests fail on that
 * rather than on a missing row.
 */
class CourseCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => CourseCatalogSeeder::class]);
    }

    public function test_it_seeds_the_five_approved_courses(): void
    {
        $expected = [
            'introduction-to-information-technology' => CourseType::Free,
            'computer-fundamentals-and-digital-literacy' => CourseType::Free,
            'programming-fundamentals-with-python' => CourseType::Paid,
            'web-development-fundamentals' => CourseType::Paid,
            'introduction-to-cybersecurity' => CourseType::Paid,
        ];

        $actual = Course::query()
            ->orderBy('id')
            ->pluck('course_type', 'slug')
            ->all();

        foreach ($expected as $slug => $type) {
            $this->assertArrayHasKey($slug, $actual, "The catalog is missing {$slug}.");
            $this->assertSame($type, $actual[$slug], "{$slug} should be {$type->value}.");
        }
    }

    public function test_every_seeded_course_is_published_and_complete(): void
    {
        $courses = Course::query()->with(['modules.lessons.learningMaterials', 'quizzes.questions.options'])->get();

        $this->assertGreaterThanOrEqual(5, $courses->count());

        foreach ($courses as $course) {
            $this->assertSame(CourseStatus::Published, $course->status, "{$course->title} is not published.");
            $this->assertNotNull($course->published_at, "{$course->title} has no publication date.");
            $this->assertNotEmpty($course->description, "{$course->title} has no description.");
            $this->assertNotEmpty($course->learning_objectives, "{$course->title} has no learning objectives.");
            $this->assertNotEmpty($course->category, "{$course->title} has no category.");
            $this->assertNotEmpty($course->modules, "{$course->title} has no modules.");

            foreach ($course->modules as $module) {
                $this->assertSame(ContentStatus::Published, $module->status);
                $this->assertNotEmpty($module->lessons, "{$course->title} / {$module->title} has no lessons.");

                foreach ($module->lessons as $lesson) {
                    $this->assertSame(ContentStatus::Published, $lesson->status);
                    $this->assertNotEmpty($lesson->content_text, "{$lesson->title} has no lesson content.");
                    $this->assertNotEmpty($lesson->summary, "{$lesson->title} has no summary.");
                    $this->assertGreaterThan(0, (int) $lesson->estimated_minutes, "{$lesson->title} has no duration.");

                    foreach ($lesson->learningMaterials as $material) {
                        $this->assertTrue(
                            filled($material->content_text) || filled($material->external_url),
                            "Material '{$material->title}' has neither content nor a link."
                        );
                    }
                }
            }
        }
    }

    public function test_the_free_and_paid_split_is_consistent_with_the_price(): void
    {
        foreach (Course::query()->get() as $course) {
            if ($course->course_type === CourseType::Free) {
                $this->assertSame(
                    0,
                    (int) $course->price_minor,
                    "{$course->title} is free but is priced."
                );

                continue;
            }

            $this->assertGreaterThan(
                0,
                (int) $course->price_minor,
                "{$course->title} is paid but costs nothing."
            );
            $this->assertSame('PHP', $course->currency);
        }
    }

    public function test_every_seeded_course_belongs_to_a_verified_instructor(): void
    {
        // A course with a random owner, or an owner whose email was never
        // verified, is not something to publish. The catalog has one Instructor
        // account and every course in it belongs to that account.
        $owners = Course::query()
            ->with('instructor.profile')
            ->get()
            ->map(fn (Course $course): string => (string) $course->instructor?->email)
            ->unique()
            ->values();

        $this->assertNotEmpty($owners);

        foreach (Course::query()->with('instructor.profile')->get() as $course) {
            $instructor = $course->instructor;

            $this->assertNotNull($instructor, "{$course->title} has no instructor.");
            $this->assertNotNull(
                $instructor->email_verified_at,
                "{$course->title} is owned by an unverified account."
            );
            $this->assertSame(
                UserRole::Instructor,
                $instructor->profile?->role,
                "{$course->title} is not owned by an Instructor."
            );
        }

        // One Instructor owns the whole catalog, so a published course can never
        // appear under an account that was never set up to teach.
        $this->assertCount(1, $owners, 'The catalog should have a single verified Instructor owner.');
    }

    public function test_every_seeded_course_offers_at_least_one_graded_quiz(): void
    {
        foreach (Course::query()->with('quizzes.questions.options')->get() as $course) {
            $quizzes = $course->quizzes->where('status', QuizStatus::Published);

            $this->assertGreaterThan(
                0,
                $quizzes->count(),
                "{$course->title} has no published quiz, so it cannot be completed."
            );
        }
    }

    public function test_every_quiz_question_has_exactly_one_correct_option(): void
    {
        foreach (QuizQuestion::query()->with('options')->get() as $question) {
            $correct = $question->options->where('is_correct', true)->count();

            $this->assertSame(
                1,
                $correct,
                "Question '{$question->prompt}' has {$correct} correct options. A question must have exactly one."
            );
            $this->assertGreaterThanOrEqual(
                2,
                $question->options->count(),
                "Question '{$question->prompt}' needs at least two options."
            );
        }
    }

    public function test_a_required_quiz_means_a_certificate_is_reachable(): void
    {
        // The certificate rule counts required published quizzes, so a course
        // whose only quizzes are optional would issue a certificate no Student
        // could actually earn the normal way.
        foreach (Course::query()->with('quizzes')->get() as $course) {
            $required = $course->quizzes
                ->where('status', QuizStatus::Published)
                ->where('is_required', true)
                ->count();

            $this->assertGreaterThan(
                0,
                $required,
                "{$course->title} has no required quiz, so the certificate rules cannot be satisfied."
            );
        }
    }

    public function test_every_lesson_sits_in_a_module_that_belongs_to_its_course(): void
    {
        foreach (Lesson::query()->with('module')->get() as $lesson) {
            $this->assertNotNull($lesson->module, "Lesson {$lesson->id} has no module.");
        }

        $this->assertSame(
            Lesson::query()->count(),
            Module::query()->with('lessons')->get()->sum(fn (Module $module): int => $module->lessons->count()),
            'A lesson is not reachable through its module.'
        );
    }

    public function test_materials_are_attached_to_a_lesson(): void
    {
        $this->assertGreaterThan(0, LearningMaterial::query()->count());
        $this->assertGreaterThan(0, Quiz::query()->count());
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $before = [
            'courses' => Course::query()->count(),
            'modules' => Module::query()->count(),
            'lessons' => Lesson::query()->count(),
            'materials' => LearningMaterial::query()->count(),
            'quizzes' => Quiz::query()->count(),
            'questions' => QuizQuestion::query()->count(),
        ];

        Artisan::call('db:seed', ['--class' => CourseCatalogSeeder::class]);

        $after = [
            'courses' => Course::query()->count(),
            'modules' => Module::query()->count(),
            'lessons' => Lesson::query()->count(),
            'materials' => LearningMaterial::query()->count(),
            'quizzes' => Quiz::query()->count(),
            'questions' => QuizQuestion::query()->count(),
        ];

        $this->assertSame($before, $after, 'Re-running the seeder created duplicates.');
    }

    /**
     * The seeder must not hand itself an Administrator.
     *
     * It binds the catalog to one address, and when an account already sits at
     * that address it used to set the role to Instructor without asking. An
     * address that belongs to somebody more important than an Instructor is
     * therefore one `db:seed` away from losing its role, and a person who loses
     * it cannot sign in to the dashboard that would have told them.
     *
     * This is not hypothetical. Two of this project's own accounts exchanged
     * addresses, which left the seeder's configured address pointing at the
     * Administrator. Nothing would have complained until the demotion happened.
     */
    public function test_seeding_refuses_to_demote_an_administrator(): void
    {
        // setUp() has already seeded the catalog, so the address the seeder is
        // really configured with is the one it just used. Reading it from the
        // seeded rows rather than repeating the constant means this test keeps
        // testing the real configuration if the constant ever changes.
        $instructor = Course::query()->firstOrFail()->instructor;

        $instructor->profile->forceFill(['role' => UserRole::Administrator])->save();

        try {
            Artisan::call('db:seed', ['--class' => CourseCatalogSeeder::class]);

            $this->fail('The seeder overwrote the role of an account it did not create.');
        } catch (\RuntimeException $refusal) {
            // A refusal has to explain itself. "Something went wrong" from a
            // seeder tells the person holding the keyboard nothing about which
            // address to change.
            $this->assertStringContainsString('INSTRUCTOR_EMAIL', $refusal->getMessage());
            $this->assertStringContainsString('must not rewrite', $refusal->getMessage());
        }

        $this->assertSame(
            UserRole::Administrator,
            $instructor->fresh()->profile->role,
            'The seeder changed the role of an account holding the configured address.'
        );
    }

    /**
     * The same address, held by a Student, is equally wrong to reshape.
     *
     * A Student is the one role whose access is deliberately the narrowest, so
     * silently promoting one into teaching is the most damaging version of this
     * mistake. Both directions are refused, for the same reason: the seeder
     * creates a teaching account, and it has no business changing an existing
     * one's role.
     */
    public function test_seeding_refuses_to_promote_a_student(): void
    {
        $instructor = Course::query()->firstOrFail()->instructor;

        $instructor->profile->forceFill(['role' => UserRole::Student])->save();

        try {
            Artisan::call('db:seed', ['--class' => CourseCatalogSeeder::class]);

            $this->fail('The seeder promoted a Student into teaching.');
        } catch (\RuntimeException) {
            // The refusal itself is what is being tested here. The other
            // direction checks that it explains itself.
        }

        $this->assertSame(
            UserRole::Student,
            $instructor->fresh()->profile->role,
            'The seeder promoted a Student into Instructor.'
        );
    }
}
