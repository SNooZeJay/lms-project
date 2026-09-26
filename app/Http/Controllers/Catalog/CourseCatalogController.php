<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CourseCatalogRequest;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Contracts\View\View;

class CourseCatalogController extends Controller
{
    private const PER_PAGE = 12;

    public function index(CourseCatalogRequest $request): View
    {
        $courses = Course::query()
            ->where('courses.status', CourseStatus::Published)
            ->when($request->search(), function ($query, string $search): void {
                $query->where('courses.title', 'like', '%'.$search.'%');
            })
            ->when($request->category(), fn ($query, string $category) => $query->where('courses.category', $category))
            ->when($request->level(), fn ($query, string $level) => $query->where('courses.level', $level))
            ->when($request->courseType(), fn ($query, string $type) => $query->where('courses.course_type', $type))
            ->with('instructor:id,name')
            ->withCount([
                'modules as published_modules_count' => fn ($query) => $query->where('modules.status', ContentStatus::Published),
                'lessons as published_lessons_count' => fn ($query) => $query->where('lessons.status', ContentStatus::Published),
            ])
            ->orderByDesc('courses.published_at')
            ->orderBy('courses.title')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $categories = Course::query()
            ->where('status', CourseStatus::Published)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('catalog.index', [
            'courses' => $courses,
            'categories' => $categories,
            'search' => $request->search(),
            'category' => $request->category(),
            'level' => $request->level(),
            'courseType' => $request->courseType(),
        ]);
    }

    public function show(Course $course): View
    {
        abort_unless($course->status === CourseStatus::Published, 404);

        $course->load([
            'instructor:id,name',
            'modules' => fn ($query) => $query
                ->where('modules.status', ContentStatus::Published)
                ->orderBy('modules.position'),
            'modules.lessons' => fn ($query) => $query
                ->where('lessons.status', ContentStatus::Published)
                ->orderBy('lessons.position'),
        ]);

        return view('catalog.show', [
            'course' => $course,
            'enrollmentState' => $this->enrollmentState($course),
        ]);
    }

    /**
     * Show the right enrollment control for the current viewer.
     */
    private function enrollmentState(Course $course): string
    {
        $viewer = auth()->user();

        if ($viewer === null) {
            return 'guest';
        }

        if ($viewer->profile?->role !== UserRole::Student) {
            return 'other_role';
        }

        if ($course->course_type !== CourseType::Free) {
            return 'paid';
        }

        $enrollment = Enrollment::query()
            ->where('student_id', $viewer->id)
            ->where('course_id', $course->id)
            ->first();

        if ($enrollment === null) {
            return 'can_enroll';
        }

        return in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true)
            ? 'enrolled'
            : 'inactive';
    }
}
