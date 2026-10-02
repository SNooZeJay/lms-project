<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\GradeSubmissionRequest;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Services\Assignments\AssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Reading one hand-in, and recording what was made of it.
 *
 * "Show" and "grade" are separate requests on purpose. Marking is a decision
 * with consequences and it deserves its own POST, its own validation and its own
 * transaction, rather than being a field on the same page that saved the last
 * thing the instructor clicked.
 */
class SubmissionController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    /**
     * One hand-in, next to the brief it answers.
     *
     * The brief is loaded with the submission rather than fetched separately,
     * because an instructor deciding between a mark and a hand-back is deciding
     * by comparing the two, and a page that shows only the work makes them go
     * and remember what was asked.
     */
    public function show(Request $request, Course $course, AssignmentSubmission $submission): View
    {
        Gate::authorize('viewSubmission', $submission);

        abort_unless($submission->assignment->lesson->module->course_id === $course->id, 404);

        $submission->load([
            'assignment.lesson.module',
            'student:id,name,email',
            'grader:id,name',
        ]);

        return view('instructor.submissions.show', [
            'course' => $course,
            'assignment' => $submission->assignment,
            'submission' => $submission,
        ]);
    }

    /**
     * Record a mark, or hand the work back.
     *
     * One transaction, because the submission's state, its mark, who marked it
     * and when are one fact and not four. A half-written mark — graded with no
     * score, or scored with no grader — is a row that lies to the student's
     * dashboard.
     */
    public function grade(
        GradeSubmissionRequest $request,
        Course $course,
        AssignmentSubmission $submission,
    ): RedirectResponse {
        Gate::authorize('grade', $submission);

        abort_unless($submission->assignment->lesson->module->course_id === $course->id, 404);

        $outcome = $request->outcome();

        $submission->loadMissing('student:id,name');

        DB::transaction(function () use ($submission, $request, $outcome): void {
            if ($outcome['status'] === AssignmentSubmission::RETURNED) {
                $this->assignments->returnForRedo(
                    $submission,
                    $request->user(),
                    (string) $request->validated('feedback'),
                );

                return;
            }

            $this->assignments->grade(
                $submission,
                $request->user(),
                (int) $request->validated('score'),
                $request->validated('feedback'),
            );
        });

        return redirect()
            ->route('instructor.courses.assignments.show', [$course, $submission->assignment])
            ->with('status', $outcome['status'] === AssignmentSubmission::RETURNED
                ? 'Handed back to the student with your note. They can submit again.'
                : "Mark recorded. {$submission->student->name} can see it now.");
    }
}
