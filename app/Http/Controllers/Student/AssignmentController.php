<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Assignments\AssignmentService;
use App\Support\AssignmentFileRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A student's side of an assignment: read the brief, hand in work, see the mark.
 *
 * Three things are checked before a byte is read, and all three are checked here
 * rather than trusted from the address:
 *
 *   the lesson belongs to this course, or the address is wrong and it is a 404
 *   the student has an enrollment that grants access, or they cannot read it
 *   the assignment is published, or it is a note to the instructor
 *
 * The third one is the reason an unpublished brief is a 403 rather than a 404.
 * A student who cannot see the assignment at all cannot tell it apart from one
 * that does not exist, and a 404 for "not for you" teaches the wrong lesson.
 */
class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    /**
     * Read the brief, and see where the student's own work has got to.
     */
    public function show(Request $request, Course $course, Lesson $lesson, Assignment $assignment): View
    {
        abort_unless($assignment->lesson_id === $lesson->id, 404);
        abort_unless($lesson->module->course_id === $course->id, 404);

        Gate::authorize('readForStudent', $assignment);

        // Enrolled, or it is not this student's course to read.
        $this->grantingEnrollment($request, $course);

        $assignment->load(['lesson.module']);

        /*
         | The student's own submission, and only their own.
         |
         | Scoped by `student_id` in the query rather than found-then-checked,
         | because the alternative is a record that exists and is not yours, which
         | is a 404 in the interface and an authorisation gap in the controller.
         */
        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $request->user()->id)
            ->with('grader:id,name')
            ->first();

        return view('student.assignments.show', [
            'course' => $course,
            'lesson' => $lesson,
            'assignment' => $assignment,
            'submission' => $submission,
            'accepted' => AssignmentFileRules::SUBMISSION_EXTENSIONS,
            'maxKilobytes' => AssignmentFileRules::MAX_SUBMISSION_KILOBYTES,
        ]);
    }

    /**
     * Hand the work in.
     *
     * One row per student per assignment, so this replaces rather than adds. A
     * student who has been handed work back and re-uploads goes back to pending,
     * and the old mark comes off the screen: work that is being looked at again
     * should not still be displaying the last person's mark.
     */
    public function submit(
        SubmitAssignmentRequest $request,
        Course $course,
        Lesson $lesson,
        Assignment $assignment,
    ): RedirectResponse {
        abort_unless($assignment->lesson_id === $lesson->id, 404);
        abort_unless($lesson->module->course_id === $course->id, 404);

        Gate::authorize('readForStudent', $assignment);

        $this->grantingEnrollment($request, $course);

        /*
         | A closed assignment takes no more work, and this is checked here
         | rather than in the Policy because "closed" is a state that changes
         | after the Policy was written, not a question about who is asking.
         */
        abort_if($assignment->status === Assignment::CLOSED, 410);

        if ($assignment->due_at !== null && $assignment->due_at->isPast()) {
            return redirect()
                ->route('student.assignments.show', [$course, $lesson, $assignment])
                ->with('error', 'The date for this assignment has passed. Ask your instructor if you need more time.');
        }

        $wasResubmission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $request->user()->id)
            ->exists();

        $this->assignments->submit(
            $assignment,
            $request->user(),
            $request->file('submission'),
        );

        return redirect()
            ->route('student.assignments.show', [$course, $lesson, $assignment])
            ->with(
                'status',
                $wasResubmission
                    ? 'Your new file was submitted. Waiting for checking or scoring.'
                    : 'Your file was submitted. Waiting for checking or scoring.',
            );
    }

    /**
     * The enrollment that lets this student read this course.
     *
     * `firstOrFail` rather than a redirect to an enrolment page: a student who
     * arrives here by typing a URL has been told they have access to something
     * they do not, and the honest answer is that the thing is not there.
     */
    private function grantingEnrollment(Request $request, Course $course): Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->firstOrFail();
    }
}
