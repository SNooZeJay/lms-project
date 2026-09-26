<?php

namespace App\Http\Requests\Courses;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The outline form posts one position per Module. This Request turns that into
 * the canonical `order` array of Module IDs, so the Action only ever receives
 * IDs and still renumbers every position itself.
 */
class ReorderModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'positions' => ['required', 'array', 'min:1'],
            'positions.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'positions.required' => 'Send a position for every module.',
            'positions.min' => 'A course needs at least one module to reorder.',
            'positions.*.distinct' => 'Two modules cannot share the same position.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['positions.*' => 'module position'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('positions')) {
                return;
            }

            $course = $this->course();

            if ($course === null) {
                return;
            }

            $reorderable = $course->modules()
                ->where('status', '!=', ContentStatus::Archived)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $submitted = collect($this->input('positions', []))
                ->keys()
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($reorderable !== $submitted) {
                $validator->errors()->add(
                    'positions',
                    'Every active module in this course needs exactly one position.'
                );

                return;
            }

            $positions = collect($this->input('positions'))->map(fn ($v) => (int) $v);

            if ($positions->sort()->values()->all() !== range(1, $positions->count())) {
                $validator->errors()->add(
                    'positions',
                    'Positions must run from 1 with no gaps and no repeats.'
                );
            }
        });
    }

    /**
     * The canonical Module ID order for the Action.
     *
     * @return array<int, int>
     */
    public function order(): array
    {
        $positions = collect($this->input('positions', []))
            ->map(fn ($value, $id) => ['id' => (int) $id, 'position' => (int) $value])
            ->sortBy('position')
            ->pluck('id')
            ->all();

        return $positions;
    }

    private function course(): ?Course
    {
        $course = $this->route('course');

        return $course instanceof Course ? $course : null;
    }
}
