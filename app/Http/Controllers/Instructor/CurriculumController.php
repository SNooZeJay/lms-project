<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Courses\ArchiveContent;
use App\Actions\Courses\Curriculum\CreateLearningMaterial;
use App\Actions\Courses\Curriculum\CreateLesson;
use App\Actions\Courses\Curriculum\CreateModule;
use App\Actions\Courses\Curriculum\ReorderCurriculum;
use App\Actions\Courses\Curriculum\UpdateLearningMaterial;
use App\Actions\Courses\Curriculum\UpdateLesson;
use App\Actions\Courses\Curriculum\UpdateModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\CreateLearningMaterialRequest;
use App\Http\Requests\Courses\CreateLessonRequest;
use App\Http\Requests\Courses\CreateModuleRequest;
use App\Http\Requests\Courses\ReorderLessonsRequest;
use App\Http\Requests\Courses\ReorderModulesRequest;
use App\Http\Requests\Courses\UpdateLearningMaterialRequest;
use App\Http\Requests\Courses\UpdateLessonRequest;
use App\Http\Requests\Courses\UpdateModuleRequest;
use App\Models\Course;
use App\Models\LearningMaterial;
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
        abort_unless($this->lessonBelongsToCourse($course, $module, $lesson), 404);
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
        abort_unless($this->lessonBelongsToCourse($course, $module, $lesson), 404);
        Gate::authorize('update', $lesson);
        $updateLesson->handle($request->user(), $lesson, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Lesson updated.');
    }

    public function storeMaterial(
        CreateLearningMaterialRequest $request,
        Course $course,
        Module $module,
        Lesson $lesson,
        CreateLearningMaterial $createLearningMaterial,
    ): RedirectResponse {
        abort_unless($this->lessonBelongsToCourse($course, $module, $lesson), 404);
        Gate::authorize('create', [LearningMaterial::class, $lesson]);
        $createLearningMaterial->handle($request->user(), $lesson, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Learning Material added.');
    }

    public function editMaterial(Course $course, Module $module, Lesson $lesson, LearningMaterial $material): View
    {
        abort_unless($this->lessonBelongsToCourse($course, $module, $lesson), 404);
        abort_unless($material->lesson_id === $lesson->id, 404);
        Gate::authorize('update', $material);

        return view('instructor.courses.materials.edit', [
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
            'material' => $material,
        ]);
    }

    public function updateMaterial(
        UpdateLearningMaterialRequest $request,
        Course $course,
        Module $module,
        Lesson $lesson,
        LearningMaterial $material,
        UpdateLearningMaterial $updateLearningMaterial,
    ): RedirectResponse {
        abort_unless($this->lessonBelongsToCourse($course, $module, $lesson), 404);
        abort_unless($material->lesson_id === $lesson->id, 404);
        Gate::authorize('update', $material);
        $updateLearningMaterial->handle($request->user(), $material, $request->validated());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Learning Material updated.');
    }

    public function archiveModule(
        Course $course,
        Module $module,
        ArchiveContent $archiveContent,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        $archiveContent->archiveModule(request()->user(), $module);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Module archived. Enrollments and progress are kept.');
    }

    public function restoreModule(
        Course $course,
        Module $module,
        ArchiveContent $archiveContent,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        $archiveContent->restoreModule(request()->user(), $module);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Module restored as a draft. Publish the course again to show it.');
    }

    public function archiveLesson(
        Course $course,
        Module $module,
        Lesson $lesson,
        ArchiveContent $archiveContent,
    ): RedirectResponse {
        abort_unless($this->lessonBelongsToCourse($course, $module, $lesson), 404);
        $archiveContent->archiveLesson(request()->user(), $lesson);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Lesson archived. Enrollments and progress are kept.');
    }

    public function restoreLesson(
        Course $course,
        Module $module,
        Lesson $lesson,
        ArchiveContent $archiveContent,
    ): RedirectResponse {
        abort_unless($this->lessonBelongsToCourse($course, $module, $lesson), 404);
        $archiveContent->restoreLesson(request()->user(), $lesson);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Lesson restored as a draft. Publish the course again to show it.');
    }

    public function reorderModules(
        ReorderModulesRequest $request,
        Course $course,
        ReorderCurriculum $reorderCurriculum,
    ): RedirectResponse {
        $reorderCurriculum->reorderModules($request->user(), $course, $request->order());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Module order saved.');
    }

    public function reorderLessons(
        ReorderLessonsRequest $request,
        Course $course,
        Module $module,
        ReorderCurriculum $reorderCurriculum,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        $reorderCurriculum->reorderLessons($request->user(), $module, $request->order());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Lesson order saved.');
    }

    private function lessonBelongsToCourse(Course $course, Module $module, Lesson $lesson): bool
    {
        return $module->course_id === $course->id && $lesson->module_id === $module->id;
    }
}
