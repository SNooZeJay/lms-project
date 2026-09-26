<?php

namespace App\Services\Reporting;

use App\Enums\CertificateStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
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
        $courseIds = Course::query()->where('instructor_id', $instructor->id)->pluck('id');

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
}
