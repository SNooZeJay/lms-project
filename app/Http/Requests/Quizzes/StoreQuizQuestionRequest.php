<?php

namespace App\Http\Requests\Quizzes;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizQuestionRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:5000'],
            'points' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.option_text' => ['required', 'string', 'max:500'],
            'options.*.explanation' => ['nullable', 'string', 'max:2000'],
            'options.*.is_correct' => ['nullable'],
            'position' => ['prohibited'],
            'question_type' => ['prohibited'],
            'quiz_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.min' => 'A question needs at least two options.',
            'options.max' => 'A question allows at most six options.',
        ];
    }
}
