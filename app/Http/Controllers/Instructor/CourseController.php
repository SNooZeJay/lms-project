<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Courses\CreateCourse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\CreateCourseRequest;
use App\Models\Course;
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

        $course->load([
            'modules' => fn ($query) => $query->orderBy('position'),
            'modules.lessons' => fn ($query) => $query->orderBy('position'),
            'modules.lessons.learningMaterials' => fn ($query) => $query->orderBy('position'),
        ]);

        return view('instructor.courses.show', [
            'course' => $course,
        ]);
    }
}
