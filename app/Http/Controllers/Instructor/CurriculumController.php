<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Courses\Curriculum\CreateLesson;
use App\Actions\Courses\Curriculum\CreateModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\CreateLessonRequest;
use App\Http\Requests\Courses\CreateModuleRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
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
}
