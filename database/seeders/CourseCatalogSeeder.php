<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\LearningMaterialType;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\CoursePrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the published course catalog.
 *
 * Everything here goes through the same models the Instructor screens use, so a
 * seeded course is an ordinary Instructor owned course. There is no separate
 * hardcoded catalog: the public catalog, the Student course pages, enrollment,
 * progress, quizzes, and the certificate rules all read the same rows an
 * Instructor would create by hand.
 *
 * Running it twice is safe. A course is matched on its slug, so a second run
 * updates nothing and creates nothing.
 */
class CourseCatalogSeeder extends Seeder
{
    /**
     * The account that owns the catalog. Seed data needs a real owner because
     * the instructor_id is a foreign key, and a course cannot exist without one.
     *
     * This is the project's own Instructor account, matched on its email. If it
     * already exists the seeder uses it as it is, so the catalog belongs to the
     * person who actually teaches here rather than to an invented name. If it
     * does not exist yet, a fresh database gets one with the same identity.
     *
     * If the address already belongs to an account with a different role, the
     * seeder stops rather than correcting the mismatch by rewriting that
     * account's permissions. A seeder that silently demotes an Administrator or
     * promotes a Student is a seeder that changes who can do what, and it does
     * it from a command nobody was watching.
     *
     * The password below is a documented demo value rather than a secret, and it
     * is only ever used when the account has to be created from nothing.
     */
    private const INSTRUCTOR_EMAIL = 'bautista.jayzee@ncst.edu.ph';

    private const INSTRUCTOR_NAME = 'Jay Zee Test';

    public function run(): void
    {
        $instructor = $this->instructor();

        // One file per course, so a course can be read, reviewed, or corrected
        // on its own instead of inside one very long array.
        $files = glob(database_path('seeders/data/catalog/*.php')) ?: [];
        sort($files);

        foreach ($files as $file) {
            /** @var array<string, mixed> $definition */
            $definition = require $file;

            $this->seedCourse($instructor, $definition);
        }
    }

    private function instructor(): User
    {
        $existing = User::query()->where('email', self::INSTRUCTOR_EMAIL)->first();

        if ($existing !== null) {
            /*
             | Only the role is enforced. An existing account keeps its own name,
             | password, and biography, because this is a real account rather than
             | a fixture the seeder is allowed to reshape.
             |
             | But an account that is already something more important than an
             | Instructor is not reshaped at all. Setting the role to Instructor
             | here is how an Administrator silently loses the ability to sign in
             | to the dashboard that would have reported the loss, and how a
             | Student silently gains the ability to publish.
             |
             | The seeder is bound to one address. If that address turns out to
             | belong to somebody else, the correct thing is to stop and say so,
             | not to correct the mismatch by editing a person's permissions.
             */
            $role = $existing->profile?->role;

            if ($role !== null && $role !== UserRole::Instructor) {
                throw new \RuntimeException(sprintf(
                    'The address this seeder is configured with, %s, belongs to an account whose '
                    .'role is %s. Refusing to change it, because a seeder must not rewrite a '
                    .'person\'s permissions. Point INSTRUCTOR_EMAIL at the Instructor account, or '
                    .'give that account its own address.',
                    self::INSTRUCTOR_EMAIL,
                    $role->value,
                ));
            }

            $existing->profile->forceFill(['role' => UserRole::Instructor])->save();

            return $existing->fresh();
        }

        $user = User::factory()->create([
            'name' => self::INSTRUCTOR_NAME,
            'email' => self::INSTRUCTOR_EMAIL,
            'password' => Hash::make((string) env('SEED_INSTRUCTOR_PASSWORD', 'ChangeMe!Catalog2026')),
        ]);

        $user->profile->forceFill([
            'role' => UserRole::Instructor,
            'bio' => 'Teaches the introductory computing subjects on the BSIT program.',
        ])->save();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedCourse(User $instructor, array $definition): void
    {
        $course = Course::query()->firstOrNew(['slug' => $definition['slug']]);

        $courseType = $definition['course_type'] === 'paid'
            ? CourseType::Paid
            : CourseType::Free;

        // The price rules are the same ones the Instructor form applies, so a
        // seeded course can never end up as a paid course priced at zero.
        CoursePrice::enforce($courseType, (int) $definition['price_minor']);

        $course->forceFill([
            'instructor_id' => $instructor->id,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'learning_objectives' => $definition['learning_objectives'],
            'category' => $definition['category'],
            'level' => $definition['level'],
            'course_type' => $courseType,
            'price_minor' => (int) $definition['price_minor'],
            'currency' => 'PHP',
            'status' => CourseStatus::Published,
            'published_at' => now()->subDays(30),
        ])->save();

        $quizPosition = 0;

        foreach ($definition['modules'] as $modulePosition => $moduleDefinition) {
            $module = Module::query()->firstOrNew([
                'course_id' => $course->id,
                'position' => $modulePosition + 1,
            ]);

            $module->forceFill([
                'course_id' => $course->id,
                'title' => $moduleDefinition['title'],
                'description' => $moduleDefinition['description'] ?? null,
                'position' => $modulePosition + 1,
                'status' => ContentStatus::Published,
            ])->save();

            foreach ($moduleDefinition['lessons'] as $lessonPosition => $lessonDefinition) {
                $lesson = $this->seedLesson($instructor, $module, $lessonPosition + 1, $lessonDefinition);

                foreach ($lessonDefinition['quizzes'] ?? [] as $quizDefinition) {
                    $this->seedQuiz($instructor, $course, $module, $lesson, $quizPosition + 1, $quizDefinition);

                    $quizPosition++;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedLesson(User $instructor, Module $module, int $position, array $definition): Lesson
    {
        $lesson = Lesson::query()->firstOrNew([
            'module_id' => $module->id,
            'position' => $position,
        ]);

        $lesson->forceFill([
            'module_id' => $module->id,
            'title' => $definition['title'],
            'slug' => $definition['slug'] ?? Str::slug($definition['title']),
            'summary' => $definition['summary'] ?? null,
            'content_text' => $definition['content'] ?? null,
            'position' => $position,
            'status' => ContentStatus::Published,
            'is_required' => $definition['is_required'] ?? true,
            'estimated_minutes' => $definition['estimated_minutes'] ?? 15,
        ])->save();

        foreach ($definition['materials'] ?? [] as $materialPosition => $materialDefinition) {
            $this->seedMaterial($instructor, $lesson, $materialPosition + 1, $materialDefinition);
        }

        return $lesson;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedMaterial(User $instructor, Lesson $lesson, int $position, array $definition): void
    {
        $material = LearningMaterial::query()->firstOrNew([
            'lesson_id' => $lesson->id,
            'position' => $position,
        ]);

        $material->forceFill([
            'lesson_id' => $lesson->id,
            'uploaded_by' => $instructor->id,
            'title' => $definition['title'],
            'material_type' => LearningMaterialType::from($definition['type']),
            'position' => $position,
            'content_text' => $definition['content'] ?? null,
            'external_url' => $definition['url'] ?? null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedQuiz(
        User $instructor,
        Course $course,
        Module $module,
        ?Lesson $lesson,
        int $position,
        array $definition,
    ): void {
        $quiz = Quiz::query()->firstOrNew([
            'course_id' => $course->id,
            'position' => $position,
        ]);

        $quiz->forceFill([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'lesson_id' => $lesson?->id,
            'created_by' => $instructor->id,
            'title' => $definition['title'],
            'description' => $definition['description'] ?? null,
            'instructions' => $definition['instructions'] ?? null,
            'position' => $position,
            'status' => QuizStatus::Published,
            'is_required' => $definition['is_required'] ?? true,
            'passing_score_percent' => $definition['passing_score_percent'] ?? 80.00,
            'max_attempts' => $definition['max_attempts'] ?? 3,
        ])->save();

        foreach ($definition['questions'] as $questionPosition => $questionDefinition) {
            $question = QuizQuestion::query()->firstOrNew([
                'quiz_id' => $quiz->id,
                'position' => $questionPosition + 1,
            ]);

            $question->forceFill([
                'quiz_id' => $quiz->id,
                'prompt' => $questionDefinition['prompt'],
                'question_type' => 'multiple_choice',
                'position' => $questionPosition + 1,
                'points' => $questionDefinition['points'] ?? 1.00,
                'explanation' => $questionDefinition['explanation'] ?? null,
            ])->save();

            $correctCount = 0;

            foreach ($questionDefinition['options'] as $optionPosition => $optionDefinition) {
                $option = QuizOption::query()->firstOrNew([
                    'question_id' => $question->id,
                    'position' => $optionPosition + 1,
                ]);

                $isCorrect = (bool) ($optionDefinition['correct'] ?? false);
                $correctCount += $isCorrect ? 1 : 0;

                $option->forceFill([
                    'question_id' => $question->id,
                    'option_text' => $optionDefinition['text'],
                    'position' => $optionPosition + 1,
                    'is_correct' => $isCorrect,
                    'explanation' => $optionDefinition['explanation'] ?? null,
                ])->save();
            }

            // A question with no correct option, or more than one, can never be
            // graded fairly. Better to refuse to seed it than to publish a quiz
            // a student cannot pass on purpose.
            if ($correctCount !== 1) {
                throw new \RuntimeException(sprintf(
                    'Question "%s" in quiz "%s" must have exactly one correct option, found %d.',
                    $questionDefinition['prompt'],
                    $definition['title'],
                    $correctCount,
                ));
            }
        }
    }
}
