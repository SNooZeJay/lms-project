<?php

namespace App\Actions\Payments;

use App\Contracts\PayMongoClient;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Support\CoursePrice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

/**
 * Creates the pending Payment and asks the provider for a checkout.
 *
 * The amount comes from the Course, never from the request. The enrollment
 * stays `pending_payment` until a verified event arrives.
 */
class CreatePayMongoCheckout
{
    public function __construct(
        private readonly PayMongoClient $client,
    ) {}

    public function handle(User $actor, Enrollment $enrollment): Payment
    {
        Gate::forUser($actor)->authorize('pay', $enrollment);

        $payment = $this->pendingPayment($enrollment);

        $checkout = $this->client->createCheckout($payment);

        $payment->forceFill([
            'provider_checkout_id' => $checkout['checkout_id'],
        ]);
        $payment->save();

        return $payment;
    }

    /**
     * The idempotency key is derived from the enrollment, so a double click
     * can never create two charges for the same course.
     */
    private function pendingPayment(Enrollment $enrollment): Payment
    {
        $course = $enrollment->course;
        $key = 'enrollment-'.$enrollment->id;

        $existing = Payment::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $price = CoursePrice::forCourse($course);

        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount_minor' => $price['amount_minor'],
            'currency' => $price['currency'],
            'status' => PaymentStatus::Pending,
            'provider' => 'paymongo',
            'idempotency_key' => $key,
        ]);

        try {
            $payment->save();
        } catch (QueryException $exception) {
            $raced = Payment::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('idempotency_key', $key)
                ->first();

            if ($raced === null) {
                throw $exception;
            }

            return $raced;
        }

        return $payment;
    }
}
