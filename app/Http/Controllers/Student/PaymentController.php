<?php

namespace App\Http\Controllers\Student;

use App\Actions\Payments\CreatePayMongoCheckout;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Support\CoursePrice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function checkout(
        Request $request,
        Course $course,
        CreatePayMongoCheckout $createCheckout,
    ): RedirectResponse {
        $enrollment = $this->pendingEnrollment($request, $course);

        Gate::authorize('pay', $enrollment);

        $checkout = $createCheckout->handle($request->user(), $enrollment);

        // The Student has to reach the provider page to pay. Sending them back
        // to the waiting page instead would leave the checkout created and the
        // payment impossible.
        return redirect()->away($checkout->redirectUrl);
    }

    /**
     * The provider sends the Student back here. This page never confirms a
     * payment by itself: only a verified webhook can do that.
     */
    public function return(Request $request, Course $course): View
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        Gate::authorize('view', $enrollment);

        $payment = Payment::query()
            ->where('enrollment_id', $enrollment->id)
            ->latest('id')
            ->first();

        $price = CoursePrice::forCourse($course);

        return view('student.payments.return', [
            'course' => $course,
            'enrollment' => $enrollment,
            'payment' => $payment,
            'amount' => $price,
            'paid' => $payment?->status === PaymentStatus::Paid,
            'failed' => $payment !== null && in_array($payment->status, [
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true),
            'isPaidCourse' => $course->course_type === CourseType::Paid,
        ]);
    }

    private function pendingEnrollment(Request $request, Course $course): Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->where('status', EnrollmentStatus::PendingPayment)
            ->firstOrFail();
    }
}
