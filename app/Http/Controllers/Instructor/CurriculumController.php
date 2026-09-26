<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Courses\Curriculum\CreateLesson;
use App\Actions\Courses\Curriculum\CreateModule;
use App\Actions\Courses\Curriculum\UpdateLesson;
use App\Actions\Courses\Curriculum\UpdateModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\CreateLessonRequest;
use App\Http\Requests\Courses\CreateModuleRequest;
use App\Http\Requests\Courses\UpdateLessonRequest;
use App\Http\Requests\Courses\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CurriculumController extends Controller
{
    public function storeModule(
        CreateModuleRequest $request,
        Course $course,
        CreateModule $createModule,
    ): RedirectResponse {
        Gate::authorize('create', [Module::class, $course]);
        $createModule->handle($request->user(), $course, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Module added as a private draft.');
    }

    public function storeLesson(
        CreateLessonRequest $request,
        Course $course,
        Module $module,
        CreateLesson $createLesson,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        Gate::authorize('create', [Lesson::class, $module]);
        $createLesson->handle($request->user(), $module, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Lesson added as a private draft.');
    }

    public function editModule(Course $course, Module $module): View
    {
        abort_unless($module->course_id === $course->id, 404);
        Gate::authorize('update', $module);

        return view('instructor.courses.modules.edit', [
            'course' => $course,
            'module' => $module,
        ]);
    }

    public function updateModule(
        UpdateModuleRequest $request,
        Course $course,
        Module $module,
        UpdateModule $updateModule,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        Gate::authorize('update', $module);
        $updateModule->handle($request->user(), $module, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Module updated.');
    }

    public function editLesson(Course $course, Module $module, Lesson $lesson): View
    {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($lesson->module_id === $module->id, 404);
        Gate::authorize('update', $lesson);

        return view('instructor.courses.lessons.edit', [
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
        ]);
    }

    public function updateLesson(
        UpdateLessonRequest $request,
        Course $course,
        Module $module,
        Lesson $lesson,
        UpdateLesson $updateLesson,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($lesson->module_id === $module->id, 404);
        Gate::authorize('update', $lesson);
        $updateLesson->handle($request->user(), $lesson, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Lesson updated.');
    }
}
