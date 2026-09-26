<?php

namespace App\Http\Requests\Quizzes;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuizRequest extends FormRequest
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
        $course = $this->route('course');

        $moduleIds = $course instanceof Course
            ? $course->modules()->pluck('id')->all()
            : [];

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'module_id' => ['nullable', 'integer', Rule::in($moduleIds)],
            'is_required' => ['nullable', 'boolean'],
            'passing_score_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:3'],
            'status' => ['prohibited'],
            'position' => ['prohibited'],
            'created_by' => ['prohibited'],
            'course_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'module_id.in' => 'That module does not belong to this course.',
            'max_attempts.max' => 'A quiz allows at most three attempts.',
        ];
    }
}
