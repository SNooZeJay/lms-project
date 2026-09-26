<?php

namespace App\Services\Reporting;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every dashboard and report number in the application.
 *
 * Each count is a real query over stored records. Nothing is estimated,
 * cached from a request, or hard coded.
 *
 * The dashboard panels below are read only. They exist so an Administrator, an
 * Instructor, and a Student can each see the next useful thing without leaving
 * the page, and they never accept input or decide anything.
 */
class OperationsReport
{
    /**
     * @return array<string, int>
     */
    public function forStudent(User $student): array
    {
        return [
            'courses_enrolled' => Enrollment::query()
                ->where('student_id', $student->id)
                ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
                ->count(),
            'lessons_completed' => LessonProgress::query()
                ->where('student_id', $student->id)
                ->where('status', LessonProgressStatus::Completed)
                ->count(),
            'quizzes_passed' => QuizAttempt::query()
                ->where('student_id', $student->id)
                ->where('passed', true)
                ->distinct()
                ->count('quiz_id'),
            'certificates_earned' => Certificate::query()
                ->where('student_id', $student->id)
                ->where('status', CertificateStatus::Issued)
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function forInstructor(User $instructor): array
    {
        $courseIds = $this->ownedCourseIds($instructor);

        return [
            'courses' => $courseIds->count(),
            'published_courses' => Course::query()
                ->where('instructor_id', $instructor->id)
                ->where('status', CourseStatus::Published)
                ->count(),
            'students_enrolled' => Enrollment::query()
                ->whereIn('course_id', $courseIds)
                ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
                ->distinct()
                ->count('student_id'),
            'quizzes' => Quiz::query()->whereIn('course_id', $courseIds)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function forAdministrator(): array
    {
        return [
            'users' => User::query()->count(),
            'students' => User::query()->whereHas('profile', fn ($q) => $q->where('role', 'student'))->count(),
            'instructors' => User::query()->whereHas('profile', fn ($q) => $q->where('role', 'instructor'))->count(),
            'courses' => Course::query()->count(),
            'enrollments' => Enrollment::query()->count(),
            'active_enrollments' => Enrollment::query()
                ->where('status', EnrollmentStatus::Active)
                ->count(),
            'certificates' => Certificate::query()
                ->where('status', CertificateStatus::Issued)
                ->count(),
            'paid_payments' => Payment::query()->where('status', PaymentStatus::Paid)->count(),
        ];
    }

    /**
     * One row per enrollment, newest first, for the administrator report.
     *
     * @return Collection<int, Collection<string, mixed>>
     */
    public function enrollmentRows(int $limit = 50)
    {
        return Enrollment::query()
            ->with(['student:id,name', 'course:id,title,instructor_id'])
            ->withCount(['quizAttempts as passed_attempts' => fn ($q) => $q->where('passed', true)])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'course' => $enrollment->course,
                'status' => $enrollment->status,
                'passed_attempts' => $enrollment->passed_attempts,
                'payment' => Payment::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->latest('id')
                    ->first(),
            ]);
    }

    /* -------------------------------------------------------- administrator panels */

    /**
     * How many Courses hold each state, for the course status panel.
     *
     * @return array<string, int>
     */
    public function courseStatusCounts(): array
    {
        $counts = Course::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (CourseStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return Collection<int, User>
     */
    public function recentUsers(int $limit = 5): Collection
    {
        return User::query()
            ->with('profile')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Enrollment>
     */
    public function recentEnrollments(int $limit = 5): Collection
    {
        return Enrollment::query()
            ->with(['student:id,name', 'course:id,title'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    public function recentPayments(int $limit = 5): Collection
    {
        return Payment::query()
            ->with(['student:id,name', 'course:id,title'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function recentActivity(int $limit = 5): Collection
    {
        return ActivityLog::query()
            ->with(['actor:id,name', 'targetUser:id,name'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /* ---------------------------------------------------------- instructor panels */

    /**
     * Enrolled Students in owned Courses whose lesson progress is incomplete.
     *
     * "Needs attention" means a real gap: the Student can open the Course and at
     * least one required published Lesson is not yet complete. An enrollment in a
     * Course with no required Lessons is left out, because there is nothing to
     * chase. The rule matches `ProgressCalculator`, so this panel can never
     * disagree with the percentage the Student sees.
     *
     * @return array{enrollments: Collection<int, Enrollment>, lessons: array<int, int>}
     */
    public function studentsNeedingAttention(User $instructor, int $limit = 5): array
    {
        $courseIds = $this->ownedCourseIds($instructor);

        if ($courseIds->isEmpty()) {
            return ['enrollments' => collect(), 'lessons' => []];
        }

        // One query for how many required published Lessons each owned Course
        // holds, so the panel never runs a query per enrollment.
        $requiredByCourse = Course::query()
            ->whereIn('id', $courseIds)
            ->withCount(['lessons as required_published_lessons' => fn ($query) => $query
                ->where('lessons.status', ContentStatus::Published)
                ->where('lessons.is_required', true)
                ->whereHas('module', fn ($module) => $module->where('modules.status', ContentStatus::Published)),
            ])
            ->pluck('required_published_lessons', 'id');

        $candidates = Enrollment::query()
            ->with(['student:id,name', 'course:id,title,status,slug'])
            ->whereIn('course_id', $courseIds)
            ->where('status', EnrollmentStatus::Active)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => (int) ($requiredByCourse[$enrollment->course_id] ?? 0) > 0);

        if ($candidates->isEmpty()) {
            return ['enrollments' => collect(), 'lessons' => []];
        }

        $completedByEnrollment = LessonProgress::query()
            ->whereIn('enrollment_id', $candidates->pluck('id'))
            ->where('status', LessonProgressStatus::Completed)
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, count(*) as aggregate')
            ->pluck('aggregate', 'enrollment_id');

        $behind = $candidates
            ->filter(fn (Enrollment $enrollment): bool => (int) ($completedByEnrollment[$enrollment->id] ?? 0)
                < (int) $requiredByCourse[$enrollment->course_id])
            ->take($limit)
            ->values();

        $remaining = [];

        foreach ($behind as $enrollment) {
            $remaining[$enrollment->id] = max(
                0,
                (int) $requiredByCourse[$enrollment->course_id] - (int) ($completedByEnrollment[$enrollment->id] ?? 0)
            );
        }

        return ['enrollments' => $behind, 'lessons' => $remaining];
    }

    /**
     * The newest submitted quiz attempts on owned Courses.
     *
     * @return Collection<int, QuizAttempt>
     */
    public function recentQuizResults(User $instructor, int $limit = 5): Collection
    {
        $courseIds = $this->ownedCourseIds($instructor);

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return QuizAttempt::query()
            ->with(['student:id,name', 'quiz:id,title,course_id'])
            ->whereIn('quiz_id', Quiz::query()->whereIn('course_id', $courseIds)->select('quizzes.id'))
            ->where('status', '!=', QuizAttemptStatus::InProgress)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /* -------------------------------------------------------------- student panels */

    /**
     * The Student's own enrollments, newest first.
     *
     * @return Collection<int, Enrollment>
     */
    public function studentEnrollments(User $student, int $limit = 4): Collection
    {
        return Enrollment::query()
            ->with(['course:id,title,status,slug,price_minor,currency,course_type,instructor_id', 'course.instructor:id,name'])
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The Student's own newest finished quiz attempts.
     *
     * @return Collection<int, QuizAttempt>
     */
    public function recentStudentAttempts(User $student, int $limit = 5): Collection
    {
        return QuizAttempt::query()
            ->with(['quiz:id,title,course_id', 'quiz.course:id,title'])
            ->where('student_id', $student->id)
            ->where('status', '!=', QuizAttemptStatus::InProgress)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The Student's own newest payment attempts.
     *
     * @return Collection<int, Payment>
     */
    public function studentPayments(User $student, int $limit = 5): Collection
    {
        return Payment::query()
            ->with('course:id,title')
            ->where('student_id', $student->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function ownedCourseIds(User $instructor): Collection
    {
        return Course::query()
            ->where('instructor_id', $instructor->id)
            ->pluck('id');
    }
}
