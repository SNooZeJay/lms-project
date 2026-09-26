<?php

namespace App\Actions\Courses;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCourse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data): Course
    {
        Gate::forUser($actor)->authorize('create', Course::class);

        $courseType = CourseType::from($data['course_type']);
        $priceMinor = (int) $data['price_minor'];

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

        return DB::transaction(function () use ($actor, $data, $courseType, $priceMinor): Course {
            $course = new Course;
            $course->forceFill([
                'instructor_id' => $actor->id,
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title']),
                'description' => $data['description'] ?? null,
                'learning_objectives' => $data['learning_objectives'] ?? null,
                'category' => $data['category'] ?? null,
                'level' => $data['level'],
                'course_type' => $courseType,
                'price_minor' => $priceMinor,
                'currency' => 'PHP',
                'status' => CourseStatus::Draft,
                'published_at' => null,
                'thumbnail_path' => null,
            ]);
            $course->save();

            return $course;
        });
    }

    private function uniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'course';
        $slug = $baseSlug;
        $suffix = 2;

        while (Course::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
