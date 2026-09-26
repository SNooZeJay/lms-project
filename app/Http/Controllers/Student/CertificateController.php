<?php

namespace App\Http\Controllers\Student;

use App\Actions\Certificates\ListStudentCertificates;
use App\Actions\Completion\CompleteCourse;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CertificateController extends Controller
{
    public function index(Request $request, ListStudentCertificates $listStudentCertificates): View
    {
        return view('student.certificates.index', [
            'rows' => $listStudentCertificates->handle($request->user()),
        ]);
    }

    public function show(Certificate $certificate): View
    {
        Gate::authorize('view', $certificate);

        return view('student.certificates.show', [
            'certificate' => $certificate,
        ]);
    }

    public function complete(
        Request $request,
        Course $course,
        CompleteCourse $completeCourse,
    ): RedirectResponse {
        $enrollment = Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $result = $completeCourse->handle($request->user(), $enrollment);

        return redirect()
            ->route('student.certificates.show', $result['certificate'])
            ->with('status', $result['created']
                ? 'Course completed. Your certificate is ready.'
                : 'You already completed this course.');
    }
}
