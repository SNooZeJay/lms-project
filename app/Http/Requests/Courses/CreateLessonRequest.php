<?php

namespace App\Http\Requests\Courses;

use Illuminate\Foundation\Http\FormRequest;

class CreateLessonRequest extends FormRequest
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
            'summary' => ['nullable', 'string', 'max:5000'],
            'content_text' => ['nullable', 'string', 'max:100000'],
            'is_required' => ['sometimes', 'boolean'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'form_context' => ['nullable', 'string', 'max:64'],
            'module_id' => ['prohibited'],
            'position' => ['prohibited'],
            'status' => ['prohibited'],
            'slug' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_required')) {
            $this->merge(['is_required' => true]);
        }
    }
}
