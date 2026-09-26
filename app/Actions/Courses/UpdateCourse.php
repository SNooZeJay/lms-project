<?php

namespace App\Actions\Courses;

use App\Enums\CourseType;
use App\Models\Course;
use App\Models\User;
use App\Support\CoursePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateCourse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Course $course, array $data): Course
    {
        Gate::forUser($actor)->authorize('update', $course);

        $courseType = CourseType::from($data['course_type']);
        $priceMinor = (int) $data['price_minor'];

        CoursePrice::enforce($courseType, $priceMinor);

        return DB::transaction(function () use ($course, $data, $courseType, $priceMinor): Course {
            $course->forceFill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'learning_objectives' => $data['learning_objectives'] ?? null,
                'category' => $data['category'] ?? null,
                'level' => $data['level'],
                'course_type' => $courseType,
                'price_minor' => $priceMinor,
            ]);
            $course->save();

            return $course;
        });
    }
}
