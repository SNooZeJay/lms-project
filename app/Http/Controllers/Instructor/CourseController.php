<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Courses\ArchiveContent;
use App\Actions\Courses\CreateCourse;
use App\Actions\Courses\PublishCourse;
use App\Actions\Courses\UnpublishCourse;
use App\Actions\Courses\UpdateCourse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\CreateCourseRequest;
use App\Http\Requests\Courses\UpdateCourseRequest;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Services\Reporting\OperationsReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Course::class);

        $courses = Course::query()
            ->where('instructor_id', $request->user()->id)
            ->withCount('modules')
            ->latest()
            ->paginate(15);

        return view('instructor.courses.index', [
            'courses' => $courses,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Course::class);

        return view('instructor.courses.create');
    }

    public function store(CreateCourseRequest $request, CreateCourse $createCourse): RedirectResponse
    {
        Gate::authorize('create', Course::class);

        $course = $createCourse->handle($request->user(), $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Course created as a private draft.');
    }

    public function show(Request $request, Course $course): View
    {
        Gate::authorize('view', $course);

        /*
         | Work set against the lessons, and the count of what is still waiting.
         |
         | Loaded on the outline rather than fetched when a page needs it, because
         | the outline is where an instructor sets work, and a page that renders
         | the whole course tree and then asks the database a second time for the
         | same tree is slower for no reason.
         |
         | The pending count is computed here instead of in the view. A count
         | inside Blade is a query inside a template, which is unseeable, and this
         | is the number an instructor opens the course to read.
         */
        $course->load([
            'modules' => fn ($query) => $query->orderBy('position'),
            'modules.lessons' => fn ($query) => $query->orderBy('position'),
            'modules.lessons.learningMaterials' => fn ($query) => $query->orderBy('position'),
            'modules.lessons.assignments' => fn ($query) => $query
                ->withCount([
                    'submissions as pending_submissions_count' => fn ($submissions) => $submissions
                        ->where('status', AssignmentSubmission::PENDING),
                ])
                ->orderByDesc('created_at'),
        ]);

        return view('instructor.courses.show', [
            'course' => $course,
        ]);
    }

    /**
     * Who is learning on this Course, and how far along each of them is.
     *
     * The Instructor dashboard can only name the handful of learners who are
     * furthest behind, because a panel has room for a panel's worth. This is
     * where the whole cohort is, which is the question a dashboard cannot hold.
     *
     * The policy is asked for `viewStudents` rather than `view`, even though the
     * two answer the same today. The rule is then written in one place, and a
     * later change to who may open a Course does not silently change who may
     * read its roster.
     */
    public function students(Request $request, Course $course, OperationsReport $report): View
    {
        Gate::authorize('viewStudents', $course);

        return view('instructor.courses.students', [
            'course' => $course,
            'learners' => $report->courseStudentRows($course),
        ]);
    }

    public function edit(Request $request, Course $course): View
    {
        Gate::authorize('update', $course);

        return view('instructor.courses.edit', [
            'course' => $course,
        ]);
    }

    public function update(
        UpdateCourseRequest $request,
        Course $course,
        UpdateCourse $updateCourse,
    ): RedirectResponse {
        Gate::authorize('update', $course);

        $updateCourse->handle($request->user(), $course, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Course details updated.');
    }

    public function publish(Course $course, PublishCourse $publishCourse): RedirectResponse
    {
        Gate::authorize('publish', $course);

        $publishCourse->handle(request()->user(), $course);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Course published. It can now appear in the public catalog.');
    }

    public function unpublish(Course $course, UnpublishCourse $unpublishCourse): RedirectResponse
    {
        Gate::authorize('unpublish', $course);

        $unpublishCourse->handle(request()->user(), $course);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Course unpublished. It is hidden from the public catalog.');
    }

    public function archive(Course $course, ArchiveContent $archiveContent): RedirectResponse
    {
        $archiveContent->archiveCourse(request()->user(), $course);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Course archived. Enrollments and progress are kept.');
    }

    public function restore(Course $course, ArchiveContent $archiveContent): RedirectResponse
    {
        $archiveContent->restoreCourse(request()->user(), $course);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Course restored as a draft. Publish it when the outline is ready.');
    }
}
