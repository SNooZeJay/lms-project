<?php

namespace App\Services\Reporting;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\ProgressCalculator;
use App\Support\Money;
use Illuminate\Support\Carbon;
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
    public function __construct(private readonly ProgressCalculator $progress) {}

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
            'courses_in_progress' => Enrollment::query()
                ->where('student_id', $student->id)
                ->where('status', EnrollmentStatus::Active)
                ->count(),
            'courses_completed' => Enrollment::query()
                ->where('student_id', $student->id)
                ->where('status', EnrollmentStatus::Completed)
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
     * The mean completion across the Student's active and completed enrollments.
     *
     * This is the same percentage the Student sees on each Course, averaged, so
     * the headline can never disagree with the detail. A cancelled or unpaid
     * enrollment is left out because the Student never started it, and an
     * enrollment with no required published Lessons counts as nothing to do
     * rather than as a failure.
     *
     * The progress for every enrollment is read in one batched pass. Reading it
     * per enrollment made this query count grow with the number of courses the
     * Student had taken, which made it the most expensive number on the page
     * for exactly the users who use the product most.
     */
    public function averageStudentProgress(User $student): int
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->get();

        if ($enrollments->isEmpty()) {
            return 0;
        }

        $percentages = [];

        foreach ($this->progress->forEnrollments($enrollments) as $totals) {
            if ((int) $totals['total'] === 0) {
                continue;
            }

            $percentages[] = (int) $totals['percentage'];
        }

        if ($percentages === []) {
            return 0;
        }

        return (int) round(array_sum($percentages) / count($percentages));
    }

    /**
     * Published quizzes in the Student's reachable courses with no passed attempt.
     *
     * A draft quiz is not something a Student can sit, and a quiz in a course
     * they have not enrolled in is not reachable, so neither is counted.
     */
    public function pendingQuizCount(User $student): int
    {
        $courseIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return 0;
        }

        return Quiz::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', QuizStatus::Published)
            ->whereDoesntHave('attempts', fn ($query) => $query
                ->where('student_id', $student->id)
                ->where('passed', true))
            ->count();
    }

    /**
     * @return array<string, int>
     */
    public function forInstructor(User $instructor): array
    {
        $courseIds = $this->ownedCourseIds($instructor);

        $started = Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->count();

        $completed = Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', EnrollmentStatus::Completed)
            ->count();

        return [
            'courses' => $courseIds->count(),
            'published_courses' => Course::query()
                ->where('instructor_id', $instructor->id)
                ->where('status', CourseStatus::Published)
                ->count(),
            'draft_courses' => Course::query()
                ->where('instructor_id', $instructor->id)
                ->where('status', CourseStatus::Draft)
                ->count(),
            'students_enrolled' => Enrollment::query()
                ->whereIn('course_id', $courseIds)
                ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
                ->distinct()
                ->count('student_id'),
            // Distinct learners with a live enrollment, which is the number that
            // answers "how many people are currently studying my work".
            'active_students' => Enrollment::query()
                ->whereIn('course_id', $courseIds)
                ->where('status', EnrollmentStatus::Active)
                ->distinct()
                ->count('student_id'),
            'quizzes' => Quiz::query()->whereIn('course_id', $courseIds)->count(),
            // Finished out of ever started. A cancelled or unpaid enrollment is
            // excluded, because that learner never began the course.
            'completion_rate' => $started === 0
                ? 0
                : (int) round($completed / $started * 100),
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
            'administrators' => User::query()->whereHas('profile', fn ($q) => $q->where('role', 'administrator'))->count(),
            'courses' => Course::query()->count(),
            'published_courses' => Course::query()->where('status', CourseStatus::Published)->count(),
            'enrollments' => Enrollment::query()->count(),
            'active_enrollments' => Enrollment::query()
                ->where('status', EnrollmentStatus::Active)
                ->count(),
            'completed_enrollments' => Enrollment::query()
                ->where('status', EnrollmentStatus::Completed)
                ->count(),
            'certificates' => Certificate::query()
                ->where('status', CertificateStatus::Issued)
                ->count(),
            'paid_payments' => Payment::query()->where('status', PaymentStatus::Paid)->count(),
        ];
    }

    /**
     * How many enrollments hold each state, for the enrollment state chart.
     *
     * Every state is present even at zero, so the chart never has to decide what
     * to draw for a state that has never been used, and the counts always add up
     * to the total the dashboard reports elsewhere.
     *
     * @return array<string, int>
     */
    public function enrollmentStatusCounts(): array
    {
        $counts = Enrollment::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (EnrollmentStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * One row per enrollment, newest first, for the administrator report.
     *
     * The newest payment for each row is fetched in one query rather than one
     * per row. Fifty rows used to cost fifty three queries, which is the shape
     * of a page that gets slow exactly when an administrator is trying to
     * investigate something. The row still carries its newest payment, so the
     * report does not lose a column in exchange for the speed.
     *
     * @return Collection<int, Collection<string, mixed>>
     */
    public function enrollmentRows(int $limit = 50)
    {
        $enrollments = Enrollment::query()
            ->with(['student:id,name', 'course:id,title,instructor_id'])
            ->withCount(['quizAttempts as passed_attempts' => fn ($q) => $q->where('passed', true)])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $payments = $this->latestPaymentByEnrollment($enrollments->pluck('id')->all());

        return $enrollments
            ->map(fn (Enrollment $enrollment): array => [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'course' => $enrollment->course,
                'status' => $enrollment->status,
                'passed_attempts' => $enrollment->passed_attempts,
                'payment' => $payments[$enrollment->id] ?? null,
            ]);
    }

    /**
     * The newest payment per enrollment, keyed by enrollment id.
     *
     * Two queries: the highest payment id in each group, then those rows. A
     * payment with no id cannot happen, so the group query is never empty for an
     * enrollment that exists.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, Payment>
     */
    private function latestPaymentByEnrollment(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        $ids = Payment::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->groupBy('enrollment_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return Payment::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('enrollment_id')
            ->all();
    }

    /* ------------------------------------------------------------- report figures */

    /**
     * The totals the enrollment report is built from.
     *
     * These are the same figures the Administrator dashboard already shows, read
     * again for the report rather than handed to it by a rendered page, so the
     * report can be opened on its own. Every one is a count over stored rows.
     *
     * @return array<string, int>
     */
    public function reportTotals(): array
    {
        return [
            'courses' => Course::query()->where('status', CourseStatus::Published)->count(),
            'enrollments' => Enrollment::query()->count(),
            'active_enrollments' => Enrollment::query()->where('status', EnrollmentStatus::Active)->count(),
            'completed_enrollments' => Enrollment::query()->where('status', EnrollmentStatus::Completed)->count(),
            'certificates' => Certificate::query()->where('status', CertificateStatus::Issued)->count(),
            'paid_payments' => Payment::query()->where('status', PaymentStatus::Paid)->count(),
        ];
    }

    /**
     * One row per published course, with the progress its learners have made.
     *
     * `plan.md` approves "simple course-level progress" for V1, so this is a
     * count of learners, a count of finishers, and the mean of the percentages
     * the calculator produces. It is deliberately not a forecast, a projection or
     * a trend line, because the plan rules those out for this release.
     *
     * The mean is taken over the same per enrollment percentages a Student sees
     * on their own course page, read through the one calculator that produces
     * them, so a report figure and a course figure cannot disagree. An
     * enrollment with no required published Lessons is left out of the mean
     * rather than counted as zero, because a learner in a course with nothing
     * required has not failed at anything.
     *
     * The query count does not grow with the number of courses or learners,
     * which is what the batched calculator exists for.
     *
     * @return Collection<int, array{course: Course, enrolled: int, completed: int, average: int, counted: int}>
     */
    public function courseProgressRows(): Collection
    {
        $courses = Course::query()
            ->where('status', CourseStatus::Published)
            ->orderBy('title')
            ->get();

        if ($courses->isEmpty()) {
            return collect();
        }

        $enrollments = Enrollment::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->get(['id', 'course_id', 'student_id', 'status']);

        $progress = $this->progress->forEnrollments($enrollments);

        $byCourse = [];

        foreach ($enrollments as $enrollment) {
            $courseId = (int) $enrollment->course_id;

            $byCourse[$courseId]['enrolled'] = ($byCourse[$courseId]['enrolled'] ?? 0) + 1;

            if ($enrollment->status === EnrollmentStatus::Completed) {
                $byCourse[$courseId]['completed'] = ($byCourse[$courseId]['completed'] ?? 0) + 1;
            }

            $totals = $progress->get($enrollment->id);

            // A course with no required Lessons has nothing to measure, and
            // averaging it in as a zero would report a failure that did not
            // happen.
            if ((int) ($totals['total'] ?? 0) === 0) {
                continue;
            }

            $byCourse[$courseId]['sum'] = ($byCourse[$courseId]['sum'] ?? 0) + (int) $totals['percentage'];
            $byCourse[$courseId]['counted'] = ($byCourse[$courseId]['counted'] ?? 0) + 1;
        }

        return $courses
            ->map(function (Course $course) use ($byCourse): array {
                $figures = $byCourse[$course->id] ?? [];
                $counted = (int) ($figures['counted'] ?? 0);

                return [
                    'course' => $course,
                    'enrolled' => (int) ($figures['enrolled'] ?? 0),
                    'completed' => (int) ($figures['completed'] ?? 0),
                    'average' => $counted === 0
                        ? 0
                        : (int) round(((int) ($figures['sum'] ?? 0)) / $counted),
                    // Whether the average means anything. The view has to be able
                    // to say "no enrollments" rather than showing a zero that
                    // reads as a failure.
                    'counted' => $counted,
                ];
            })
            ->values();
    }

    /**
     * One row per learner on a Course, with their real progress.
     *
     * This answers the question an Instructor cannot answer from the dashboard.
     * The dashboard can only name the handful of learners who are furthest
     * behind; a Course is where "how is this whole cohort doing" is a question
     * that has an answer.
     *
     * The percentage is the one the learner sees on their own course page,
     * produced by the same calculator, so the two cannot disagree. Passing
     * assessments are counted separately because finishing the lessons and
     * passing the quizzes are different things, and a learner at a hundred
     * percent of lessons with nothing passed is a real and common state.
     *
     * Cancelled and unpaid enrollments are left out. They are not learners yet,
     * and a row that reads 0 percent for somebody who has not begun says nothing
     * true about anybody.
     *
     * The query count does not grow with the size of the cohort, which is the
     * whole point of the batched calculator and the batched attempt count.
     *
     * @return Collection<int, array{enrollment: Enrollment, student: ?User, status: EnrollmentStatus, completed: int, total: int, percentage: int, passed: int}>
     */
    public function courseStudentRows(Course $course): Collection
    {
        $enrollments = Enrollment::query()
            ->with('student:id,name')
            ->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->orderBy('id')
            ->get();

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $progress = $this->progress->forEnrollments($enrollments);

        // One grouped query for the whole cohort, rather than one per learner.
        $passedByEnrollment = QuizAttempt::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('passed', true)
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, count(*) as aggregate')
            ->pluck('aggregate', 'enrollment_id');

        return $enrollments
            ->map(function (Enrollment $enrollment) use ($progress, $passedByEnrollment): array {
                $totals = $progress->get($enrollment->id, [
                    'completed' => 0,
                    'total' => 0,
                    'percentage' => 0,
                ]);

                return [
                    'enrollment' => $enrollment,
                    'student' => $enrollment->student,
                    'status' => $enrollment->status,
                    'completed' => (int) $totals['completed'],
                    'total' => (int) $totals['total'],
                    'percentage' => (int) $totals['percentage'],
                    'passed' => (int) ($passedByEnrollment[$enrollment->id] ?? 0),
                ];
            })
            ->values();
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

    /* ------------------------------------------------------------- the agenda */

    /**
     * Dated records for the Student, newest first.
     *
     * This application has no scheduling. Nothing stores a due date, a lesson
     * time, or an assessment deadline, so there is no honest "coming up" list to
     * draw. What there is, and what a learner actually wants to see, is what
     * happened to them and when: a certificate issued, a course finished, a
     * payment confirmed, a quiz sat, a lesson finished.
     *
     * Every entry therefore carries a real column value, and the list can never
     * contain an event that has not happened. Each source is limited before the
     * merge so one busy table cannot crowd out the others.
     *
     * @return Collection<int, array{at: Carbon, label: string, detail: ?string, href: ?string, tone: string}>
     */
    public function studentAgenda(User $student, int $limit = 8): Collection
    {
        $take = max(1, $limit);

        $entries = collect();

        $enrollments = Enrollment::query()
            ->with('course:id,title,slug')
            ->where('student_id', $student->id)
            ->whereNotNull('activated_at')
            ->latest('activated_at')
            ->limit($take)
            ->get();

        foreach ($enrollments as $enrollment) {
            $entries->push($this->agendaEntry(
                $enrollment->activated_at,
                'Enrolled in '.$enrollment->course->title,
                null,
                route('student.courses.show', $enrollment->course_id),
                'primary'
            ));
        }

        $finished = Enrollment::query()
            ->with('course:id,title')
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit($take)
            ->get();

        foreach ($finished as $enrollment) {
            $entries->push($this->agendaEntry(
                $enrollment->completed_at,
                'Completed '.$enrollment->course->title,
                null,
                route('student.courses.show', $enrollment->course_id),
                'accent'
            ));
        }

        $certificates = Certificate::query()
            ->with('course:id,title')
            ->where('student_id', $student->id)
            ->where('status', CertificateStatus::Issued)
            ->latest('completion_date')
            ->limit($take)
            ->get();

        foreach ($certificates as $certificate) {
            $entries->push($this->agendaEntry(
                $certificate->completion_date->startOfDay(),
                'Certificate issued for '.$certificate->course->title,
                $certificate->certificate_code,
                route('student.certificates.show', $certificate),
                'accent'
            ));
        }

        $attempts = QuizAttempt::query()
            ->with('quiz:id,title')
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit($take)
            ->get();

        foreach ($attempts as $attempt) {
            $entries->push($this->agendaEntry(
                $attempt->submitted_at,
                ($attempt->passed ? 'Passed ' : 'Sat ').$attempt->quiz->title,
                null,
                null,
                $attempt->passed ? 'accent' : 'primary'
            ));
        }

        $payments = Payment::query()
            ->with('course:id,title')
            ->where('student_id', $student->id)
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->limit($take)
            ->get();

        foreach ($payments as $payment) {
            $entries->push($this->agendaEntry(
                $payment->paid_at,
                'Payment confirmed for '.($payment->course?->title ?? 'a course'),
                Money::format($payment->amount_minor, $payment->currency),
                null,
                'accent'
            ));
        }

        /*
         | Finished lessons.
         |
         | This source was missing while the method's own docblock listed "a
         | lesson finished" among the events the feed is meant to hold. That is
         | not a wording slip, it is the event a learner produces most often: a
         | day spent in the LMS normally contains no new enrollment, no payment
         | and no certificate, only lessons. Every source that did exist fires
         | once on day one, so the feed went quiet exactly when the learner was
         | most active, and the dashboard could report 21 completed lessons two
         | panels above a feed saying nothing had happened.
         |
         | The course is carried as the detail line because the lesson title is
         | only unambiguous inside one course, and a learner takes several. The
         | row links to the lesson itself rather than the course page, since the
         | thing that happened was in that lesson.
         */
        $lessons = LessonProgress::query()
            ->with(['lesson:id,title', 'enrollment.course:id,title'])
            ->where('student_id', $student->id)
            ->where('status', LessonProgressStatus::Completed)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit($take)
            ->get();

        foreach ($lessons as $progress) {
            $courseId = $progress->enrollment?->course_id;

            $entries->push($this->agendaEntry(
                $progress->completed_at,
                'Finished '.($progress->lesson?->title ?? 'a lesson'),
                $progress->enrollment?->course?->title,
                $courseId !== null && $progress->lesson_id !== null
                    ? route('student.lessons.show', ['course' => $courseId, 'lesson' => $progress->lesson_id])
                    : null,
                'primary'
            ));
        }

        return $this->mergeAgenda($entries, $limit);
    }

    /**
     * Dated records for the Instructor, newest first.
     *
     * Scoped to owned Courses, so another Instructor's learners never appear
     * here. The shape answers "what has happened on my courses lately", which is
     * the honest question in a system with no deadlines to count down to.
     *
     * @return Collection<int, array{at: Carbon, label: string, detail: ?string, href: ?string, tone: string}>
     */
    public function instructorAgenda(User $instructor, int $limit = 8): Collection
    {
        $courseIds = $this->ownedCourseIds($instructor);
        $take = max(1, $limit);

        if ($courseIds->isEmpty()) {
            return collect();
        }

        $entries = collect();

        $published = Course::query()
            ->whereIn('id', $courseIds)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->limit($take)
            ->get();

        foreach ($published as $course) {
            $entries->push($this->agendaEntry(
                $course->published_at,
                'Published '.$course->title,
                null,
                route('instructor.courses.show', $course),
                'accent'
            ));
        }

        $enrollments = Enrollment::query()
            ->with(['student:id,name', 'course:id,title'])
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('activated_at')
            ->latest('activated_at')
            ->limit($take)
            ->get();

        foreach ($enrollments as $enrollment) {
            $entries->push($this->agendaEntry(
                $enrollment->activated_at,
                $enrollment->student->name.' enrolled in '.$enrollment->course->title,
                null,
                route('instructor.courses.show', $enrollment->course_id),
                'primary'
            ));
        }

        $attempts = QuizAttempt::query()
            ->with(['student:id,name', 'quiz:id,title'])
            ->whereIn('quiz_id', Quiz::query()->whereIn('course_id', $courseIds)->select('quizzes.id'))
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit($take)
            ->get();

        foreach ($attempts as $attempt) {
            $entries->push($this->agendaEntry(
                $attempt->submitted_at,
                $attempt->student->name.($attempt->passed ? ' passed ' : ' sat ').$attempt->quiz->title,
                null,
                null,
                $attempt->passed ? 'accent' : 'primary'
            ));
        }

        return $this->mergeAgenda($entries, $limit);
    }

    /**
     * Dated records across the whole system, newest first.
     *
     * @return Collection<int, array{at: Carbon, label: string, detail: ?string, href: ?string, tone: string}>
     */
    public function administratorAgenda(int $limit = 8): Collection
    {
        $take = max(1, $limit);
        $entries = collect();

        $users = User::query()->latest('id')->limit($take)->get();
        $newest = User::query()->max('created_at');

        if ($newest !== null) {
            $users = User::query()->where('created_at', '>=', $newest)->latest('id')->limit($take)->get();

            foreach ($users as $user) {
                $entries->push($this->agendaEntry(
                    $user->created_at,
                    $user->name.' created an account',
                    null,
                    route('admin.users.index'),
                    'primary'
                ));
            }
        }

        $enrollments = Enrollment::query()
            ->with(['student:id,name', 'course:id,title'])
            ->whereNotNull('activated_at')
            ->latest('activated_at')
            ->limit($take)
            ->get();

        foreach ($enrollments as $enrollment) {
            $entries->push($this->agendaEntry(
                $enrollment->activated_at,
                $enrollment->student->name.' enrolled in '.$enrollment->course->title,
                null,
                route('admin.reports.index'),
                'primary'
            ));
        }

        $certificates = Certificate::query()
            ->with('course:id,title')
            ->where('status', CertificateStatus::Issued)
            ->latest('completion_date')
            ->limit($take)
            ->get();

        foreach ($certificates as $certificate) {
            $entries->push($this->agendaEntry(
                $certificate->completion_date->startOfDay(),
                'Certificate issued for '.$certificate->course->title,
                $certificate->certificate_code,
                route('admin.certificates.index'),
                'accent'
            ));
        }

        $payments = Payment::query()
            ->with('course:id,title')
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->limit($take)
            ->get();

        foreach ($payments as $payment) {
            $entries->push($this->agendaEntry(
                $payment->paid_at,
                'Payment confirmed for '.($payment->course?->title ?? 'a course'),
                Money::format($payment->amount_minor, $payment->currency),
                null,
                'accent'
            ));
        }

        return $this->mergeAgenda($entries, $limit);
    }

    /**
     * @return array{at: Carbon, label: string, detail: ?string, href: ?string, tone: string}
     */
    private function agendaEntry(
        mixed $at,
        string $label,
        ?string $detail,
        ?string $href,
        string $tone
    ): array {
        return [
            'at' => $at instanceof \DateTimeInterface
                ? Carbon::instance($at)
                : now(),
            'label' => $label,
            'detail' => $detail,
            'href' => $href,
            'tone' => $tone,
        ];
    }

    /**
     * Newest first, then cut to the requested length.
     *
     * @param  Collection<int, array{at: Carbon, label: string, detail: ?string, href: ?string, tone: string}>  $entries
     * @return Collection<int, array{at: Carbon, label: string, detail: ?string, href: ?string, tone: string}>
     */
    private function mergeAgenda(Collection $entries, int $limit): Collection
    {
        return $entries
            ->sortByDesc(fn (array $entry): int => $entry['at']->getTimestamp())
            ->take(max(1, $limit))
            ->values();
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
