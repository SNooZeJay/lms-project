<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Courses\SetCourseCover;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\UpdateCourseCoverRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

/**
 * The cover on a Course an Instructor owns.
 *
 * Thin on purpose. It reads the request, hands the answer to the action, and says
 * what happened. The rules about what a cover may be, where it is stored and who may
 * set it are all in the action and the request, so a second way of changing a cover
 * would be a second place to keep them in step.
 */
class CourseCoverController extends Controller
{
    public function update(
        UpdateCourseCoverRequest $request,
        Course $course,
        SetCourseCover $setCover,
    ): RedirectResponse {
        $validated = $request->validated();

        if ($request->boolean('remove_cover')) {
            $setCover->remove($request->user(), $course);

            return redirect()
                ->route('instructor.courses.show', $course)
                ->with('status', 'Cover removed. The course now shows its placeholder.');
        }

        if ($request->hasFile('cover_file')) {
            $setCover->upload($request->user(), $course, $request->file('cover_file'));

            return redirect()
                ->route('instructor.courses.show', $course)
                ->with('status', 'Cover image uploaded.');
        }

        if (filled($validated['cover_photo_id'] ?? null)) {
            $setCover->choosePhotograph($request->user(), $course, $validated['cover_photo_id']);

            return redirect()
                ->route('instructor.courses.show', $course)
                ->with('status', 'Cover chosen from the catalog.');
        }

        /*
         | Nothing was asked for.
         |
         | The route exists to change the cover, so arriving with no choice in the
         | body is a request that does not know what it wants. Redirecting with a
         | message is friendlier than a 422 for a form somebody submitted twice, and
         | it leaves the course untouched either way.
         */
        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'No cover was chosen, so nothing changed.');
    }
}
