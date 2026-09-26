<?php

namespace App\Http\Requests\Courses;

use App\Enums\CourseLevel;
use App\Enums\CourseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'learning_objectives' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'level' => ['required', Rule::enum(CourseLevel::class)],
            'course_type' => ['required', Rule::enum(CourseType::class)],
            'price_minor' => ['required', 'integer', 'min:0', 'max:100000000'],
            'slug' => ['prohibited'],
            'status' => ['prohibited'],
            'currency' => ['prohibited'],
            'published_at' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'thumbnail_path' => ['prohibited'],
        ];
    }
}
