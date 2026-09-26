<?php

namespace App\Support;

use App\Enums\CourseType;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

class CoursePrice
{
    /**
     * The amount a Student owes, in integer minor units with an ISO currency.
     *
     * This is the only place a chargeable amount is produced, so it can never
     * come from a request.
     *
     * @return array{amount_minor: int, currency: string}
     */
    public static function forCourse(Course $course): array
    {
        return [
            'amount_minor' => (int) $course->price_minor,
            'currency' => (string) $course->currency,
        ];
    }

    /**
     * Keep the free and paid price rules in one place.
     */
    public static function enforce(CourseType $courseType, int $priceMinor): void
    {
        if ($courseType === CourseType::Free && $priceMinor !== 0) {
            throw ValidationException::withMessages([
                'price_minor' => 'A free Course must have a price of zero.',
            ]);
        }

        if ($courseType === CourseType::Paid && $priceMinor <= 0) {
            throw ValidationException::withMessages([
                'price_minor' => 'A paid Course must have a positive price.',
            ]);
        }
    }
}
