<?php

namespace App\Support;

use App\Enums\CourseType;
use Illuminate\Validation\ValidationException;

class CoursePrice
{
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
