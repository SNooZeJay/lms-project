<?php

namespace Tests\Feature\Phase6D;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LessonProgressFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_progress_table_exists_with_the_documented_columns(): void
    {
        $this->assertTrue(Schema::hasTable('lesson_progress'));

        $this->assertTrue(Schema::hasColumns('lesson_progress', [
            'id',
            'enrollment_id',
            'student_id',
            'lesson_id',
            'status',
            'started_at',
            'completed_at',
            'last_viewed_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_status_defaults_to_not_started_with_null_timestamps(): void
    {
        $progress = LessonProgress::factory()->create();

        $this->assertSame(LessonProgressStatus::NotStarted, $progress->status);
        $this->assertNull($progress->started_at);
        $this->assertNull($progress->completed_at);
        $this->assertNull($progress->last_viewed_at);
    }

    public function test_all_three_status_values_are_accepted(): void
    {
        foreach ([LessonProgressStatus::NotStarted, LessonProgressStatus::InProgress, LessonProgressStatus::Completed] as $status) {
            $progress = LessonProgress::factory()->create(['status' => $status]);
            $this->assertSame($status, $progress->refresh()->status);
        }
    }

    public function test_unknown_status_is_rejected_by_the_model_and_the_database(): void
    {
        $modelRejected = false;

        try {
            LessonProgress::factory()->create(['status' => 'almost_done']);
        } catch (\ValueError) {
            $modelRejected = true;
        }

        $this->assertTrue($modelRejected, 'the model cast should reject an unknown status');

        $progress = LessonProgress::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('lesson_progress')->where('id', $progress->id)->update(['status' => 'almost_done']);
    }

    public function test_one_progress_row_per_enrollment_and_lesson(): void
    {
        [$enrollment, $lesson] = $this->makeEnrolledLesson();

        LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);

        $this->expectException(QueryException::class);

        LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_two_students_can_have_progress_on_the_same_lesson(): void
    {
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);

        foreach ([$this->makeStudent(), $this->makeStudent()] as $student) {
            $enrollment = Enrollment::factory()->active()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
            ]);
            LessonProgress::factory()->create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
            ]);
        }

        $this->assertSame(2, LessonProgress::query()->where('lesson_id', $lesson->id)->count());
    }

    public function test_deleting_an_enrollment_with_progress_is_rejected(): void
    {
        [$enrollment, $lesson] = $this->makeEnrolledLesson();
        LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('enrollments')->where('id', $enrollment->id)->delete();
    }

    public function test_deleting_a_lesson_with_progress_is_rejected(): void
    {
        [$enrollment, $lesson] = $this->makeEnrolledLesson();
        LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('lessons')->where('id', $lesson->id)->delete();
    }

    public function test_deleting_a_student_with_progress_is_rejected(): void
    {
        [$enrollment, $lesson] = $this->makeEnrolledLesson();
        LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $enrollment->student_id)->delete();
    }

    public function test_progress_belongs_to_an_enrollment_student_and_lesson(): void
    {
        [$enrollment, $lesson] = $this->makeEnrolledLesson();
        $progress = LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);

        $this->assertTrue($progress->enrollment->is($enrollment));
        $this->assertTrue($progress->student->is($enrollment->student));
        $this->assertTrue($progress->lesson->is($lesson));
    }

    public function test_enrollment_lesson_and_user_expose_their_progress(): void
    {
        [$enrollment, $lesson] = $this->makeEnrolledLesson();
        $progress = LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);

        $this->assertTrue($enrollment->lessonProgress->contains($progress));
        $this->assertTrue($lesson->progressRecords->contains($progress));
        $this->assertTrue($enrollment->student->lessonProgress->contains($progress));
    }

    public function test_timestamps_are_cast_when_present(): void
    {
        $progress = LessonProgress::factory()->create([
            'status' => LessonProgressStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'last_viewed_at' => now(),
        ]);

        $progress = $progress->refresh();

        $this->assertInstanceOf(Carbon::class, $progress->started_at);
        $this->assertInstanceOf(Carbon::class, $progress->completed_at);
        $this->assertInstanceOf(Carbon::class, $progress->last_viewed_at);
    }

    public function test_unpublishing_a_course_keeps_progress_records(): void
    {
        [$enrollment, $lesson] = $this->makeEnrolledLesson();
        $progress = LessonProgress::factory()->completed()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
        ]);

        $enrollment->course->forceFill(['status' => CourseStatus::Draft])->save();

        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'status' => 'completed',
        ]);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->refresh()->status);
    }

    public function test_phase_six_d_adds_no_progress_ui_or_payment_routes(): void
    {
        $this->assertFalse(Route::has('student.lessons.complete'));
        $this->assertFalse(Route::has('student.progress.index'));
        $this->assertFalse(Route::has('student.payments.checkout'));
        $this->assertFalse(Schema::hasTable('quizzes'));
        $this->assertFalse(Schema::hasTable('certificates'));
    }

    /**
     * @return array{0: Enrollment, 1: Lesson}
     */
    private function makeEnrolledLesson(): array
    {
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $student = $this->makeStudent();

        $enrollment = Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return [$enrollment, $lesson];
    }

    private function makeStudent(): User
    {
        return User::factory()->create();
    }

    private function makeCourse(): Course
    {
        return Course::factory()
            ->for(User::factory()->instructor(), 'instructor')
            ->create([
                'status' => CourseStatus::Published,
                'course_type' => CourseType::Free,
                'price_minor' => 0,
            ]);
    }

    private function makeLesson(Course $course): Lesson
    {
        $module = Module::factory()->for($course, 'course')->create();

        return Lesson::factory()->for($module, 'module')->create();
    }
}
