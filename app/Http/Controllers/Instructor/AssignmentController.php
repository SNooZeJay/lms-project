<?php

namespace App\Http\Controllers\Instructor;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\Assignments\AssignmentService;
use App\Support\AssignmentFileRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The instructor's side of setting work.
 *
 * Deliberately small. There is no index of every assignment across every course
 * because an instructor does not have that question; they have one course and
 * the question is what is outstanding on it. That is the queue, and it lives on
 * the course.
 *
 * Assignment status is decided by the action and never by the request. A POST
 * carrying `status=published` is ignored in favour of the `publish` checkbox the
 * form actually renders, because a field nobody sees is a field nobody set.
 */
class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    /**
     * The form for setting work against a lesson.
     *
     * `form_url` accepts a Google Form address. The check is the shape of the
     * address and nothing more: whether the form exists, belongs to this
     * instructor, or is collecting anything is not something this application
     * can know, and a check that pretends to would pass on nonsense.
     */
    public function create(Request $request, Course $course, Lesson $lesson): View
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        Gate::authorize('create', [Assignment::class, $course]);

        return view('instructor.assignments.create', [
            'course' => $course,
            'lesson' => $lesson,
            'briefingTypes' => AssignmentFileRules::BRIEFING_EXTENSIONS,
        ]);
    }

    /**
     * Store it.
     *
     * The whole write is inside one transaction, because the assignment row and
     * the briefing file have to agree: a brief stored without its row is a file
     * nothing points at, and a row pointing at a file that failed to write is a
     * download that 404s at the moment an instructor clicks it.
     */
    public function store(
        StoreAssignmentRequest $request,
        Course $course,
        Lesson $lesson,
    ): RedirectResponse {
        abort_unless($lesson->module->course_id === $course->id, 404);

        Gate::authorize('create', [Assignment::class, $course]);

        /*
         | A brief on a lesson that is not published is a note to self, not work
         | for a student. Refusing it here means the form cannot quietly produce
         | something no student will ever see.
         */
        if ($lesson->status !== ContentStatus::Published && $request->boolean('publish')) {
            return redirect()
                ->route('instructor.courses.show', [$course])
                ->with('error', 'Publish this lesson before publishing work on it. A student cannot reach a draft lesson.');
        }

        $assignment = $this->assignments->create($course, $lesson, $request);

        return redirect()
            ->route('instructor.courses.assignments.show', [$course, $assignment])
            ->with('status', $assignment->isPublished()
                ? 'Assignment published. Students can read it and hand in work.'
                : 'Assignment saved as a draft. Nobody can see it yet.');
    }

    /**
     * One assignment, and everything handed in against it.
     *
     * The queue is pending work, oldest first. Marked work is below it under a
     * separate heading rather than mixed in, because the question an instructor
     * opens this page to answer is "what still needs me" and a mixed list makes
     * that question slower to answer.
     */
    public function show(Request $request, Course $course, Assignment $assignment): View
    {
        Gate::authorize('view', $assignment);

        // The lesson is loaded because the address of the page must agree with
        // the course it is being reached through.
        abort_unless($assignment->lesson->module->course_id === $course->id, 404);

        $assignment->load([
            'lesson.module',
            'author:id,name',
            'submissions' => fn ($query) => $query
                ->with('student:id,name,email')
                ->latest('submitted_at'),
        ]);

        $pending = $assignment->submissions
            ->where('status', AssignmentSubmission::PENDING)
            ->sortBy('submitted_at')
            ->values();

        $marked = $assignment->submissions
            ->reject(fn ($submission) => $submission->isPending())
            ->sortByDesc('submitted_at')
            ->values();

        return view('instructor.assignments.show', [
            'course' => $course,
            'assignment' => $assignment,
            'pending' => $pending,
            'marked' => $marked,
        ]);
    }

    /**
     * Release a draft, or stop taking work on a released brief.
     *
     * Two outcomes, one control, because the question is "should this be live" and
     * yes and no are its only answers. Closing does not delete: work already
     * handed in is evidence that work happened, and removing the brief would
     * remove the question those answers were written against.
     */
    public function updateStatus(Request $request, Course $course, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        abort_unless($assignment->lesson->module->course_id === $course->id, 404);

        $publishing = $request->boolean('publish');

        $this->assignments->changeStatus(
            $assignment,
            $publishing ? Assignment::PUBLISHED : Assignment::CLOSED,
        );

        return redirect()
            ->route('instructor.courses.assignments.show', [$course, $assignment])
            ->with('status', $publishing
                ? 'Assignment published. Students can read it and hand in work.'
                : 'Assignment closed. No more work will be accepted against it.');
    }
}
