<?php

namespace App\Http\Requests\Courses;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A Module that belongs to another Course is a missing resource, not a
 * validation error, so this is checked before any rule runs.
 */
class ReorderLessonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $course = $this->route('course');
        $module = $this->route('module');

        if ($course instanceof Course && $module instanceof Module) {
            abort_unless($module->course_id === $course->id, 404);
        }
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
            'positions.required' => 'Send a position for every lesson.',
            'positions.min' => 'A module needs at least one lesson to reorder.',
            'positions.*.distinct' => 'Two lessons cannot share the same position.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['positions.*' => 'lesson position'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('positions')) {
                return;
            }

            $module = $this->module();

            if ($module === null) {
                return;
            }

            $reorderable = $module->lessons()
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
                    'Every active lesson in this module needs exactly one position.'
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
     * The canonical Lesson ID order for the Action.
     *
     * @return array<int, int>
     */
    public function order(): array
    {
        return collect($this->input('positions', []))
            ->map(fn ($value, $id) => ['id' => (int) $id, 'position' => (int) $value])
            ->sortBy('position')
            ->pluck('id')
            ->all();
    }

    private function module(): ?Module
    {
        $module = $this->route('module');

        return $module instanceof Module ? $module : null;
    }
}
