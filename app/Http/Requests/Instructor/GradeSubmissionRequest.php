<?php

namespace App\Http\Requests\Instructor;

use App\Models\AssignmentSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An instructor recording a mark.
 *
 * Two shapes share this form, because a mark and a hand-back are the same act
 * seen from opposite ends, and separating them would mean two screens for one
 * decision:
 *
 *   mark   a number, and optionally a note. Becomes `graded`.
 *   return no number, a note that is required. Becomes `returned`.
 *
 * The mark is checked against this assignment's own scale rather than against a
 * constant, so an instructor working in a 5-point scale is not asked to fit a
 * percentage and one working in 100 points is not capped at 10.
 */
class GradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Whether this instructor owns the course the work was set on is proved
        // in the controller, against the submission's own course.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maximum = (int) ($this->route('submission')?->assignment?->max_score ?? 0);

        return [
            'intent' => ['required', Rule::in(['grade', 'return'])],

            'score' => [
                // Required to mark, absent to hand back. Conditional on intent
                // rather than on presence, because a field that becomes required
                // the instant it gains a value is impossible to empty again in a
                // browser form.
                Rule::requiredIf($this->input('intent') === 'grade'),
                Rule::prohibitedIf($this->input('intent') !== 'grade'),
                'nullable',
                'numeric',
                'min:0',
                // Zero when the assignment has no scale yet is caught here rather
                // than producing `max:0`, which reads as nonsense.
                $maximum > 0 ? 'max:'.$maximum : 'max:10000',
            ],

            'feedback' => [
                // "Try again" with no reason is not feedback, so a hand-back
                // must say why.
                Rule::requiredIf($this->input('intent') === 'return'),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * The mark must sit inside this assignment's own scale.
     *
     * Written as an after-check rather than as a rule, because the maximum lives
     * on the assignment and expressing "at most this assignment's score" as a
     * string rule would read as a constant in the rule list and quietly stop
     * being true when the scale changes.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('intent') !== 'grade') {
                    return;
                }

                $assignment = $this->route('submission')?->assignment;

                if ($assignment === null || ! $assignment->isMarkable()) {
                    $validator->errors()->add(
                        'score',
                        'This assignment has no mark scale yet, so it cannot be marked.'
                    );

                    return;
                }

                $score = (float) $this->input('score');

                if ($score > (float) $assignment->max_score) {
                    $validator->errors()->add(
                        'score',
                        "This assignment is marked out of {$assignment->max_score}."
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.required' => 'Enter the mark you are giving.',
            'score.prohibited' => 'Leave the mark empty when handing work back.',
            'score.max' => 'That is above the mark this assignment is set out of.',
            'feedback.required' => 'Say what needs changing, or handing it back teaches nothing.',
            'intent.in' => 'Choose whether you are marking this or handing it back.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'score' => 'the mark',
            'feedback' => 'your note',
        ];
    }

    /**
     * The state this decision produces.
     *
     * Read from the validated data rather than from the raw input, so a hander
     * upper cannot send `intent=return` and a score and have one quietly win.
     *
     * @return array<string, mixed>
     */
    public function outcome(): array
    {
        $validated = $this->validated();

        if (($validated['intent'] ?? null) === 'return') {
            return [
                'status' => AssignmentSubmission::RETURNED,
                'score' => null,
            ];
        }

        return [
            'status' => AssignmentSubmission::GRADED,
            // Stored as a string on purpose: the column is DECIMAL(8,2), and
            // handing it a float for a whole-number mark produces 12.00 in the
            // database and 12.0 in every read of it.
            'score' => is_numeric($validated['score'] ?? null)
                ? number_format((float) $validated['score'], 2, '.', '')
                : null,
        ];
    }
}
