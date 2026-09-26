<?php

namespace App\Http\Requests\Quizzes;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitQuizAttemptRequest extends FormRequest
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
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
            'score_points' => ['prohibited'],
            'score_percent' => ['prohibited'],
            'total_points' => ['prohibited'],
            'passed' => ['prohibited'],
            'status' => ['prohibited'],
            'student_id' => ['prohibited'],
            'enrollment_id' => ['prohibited'],
            'attempt_number' => ['prohibited'],
            'submitted_at' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Answer every question, then submit.',
        ];
    }

    /**
     * A mismatched Quiz or Attempt is a missing resource, not a form error.
     */
    protected function prepareForValidation(): void
    {
        $quiz = $this->route('quiz');
        $attempt = $this->route('attempt');

        if ($quiz instanceof Quiz && $attempt instanceof QuizAttempt) {
            abort_unless($attempt->quiz_id === $quiz->id, 404);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $quiz = $this->route('quiz');

            if (! $quiz instanceof Quiz || $validator->errors()->has('answers')) {
                return;
            }

            $questionIds = $quiz->questions()->pluck('id')->all();

            foreach (array_keys($this->input('answers', [])) as $questionId) {
                if (! in_array((int) $questionId, $questionIds, true)) {
                    $validator->errors()->add('answers', 'One or more answers do not belong to this quiz.');

                    return;
                }
            }
        });
    }

    /**
     * @return array<int, int>
     */
    public function selections(): array
    {
        return collect($this->input('answers', []))
            ->map(fn ($value) => (int) $value)
            ->all();
    }
}
