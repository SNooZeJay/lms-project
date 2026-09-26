<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CourseLevel;
use App\Enums\CourseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CourseCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:20'],
            'course_type' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Drop unknown filter values so a hand-typed URL never raises an error.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => $this->cleanText($this->input('q')),
            'category' => $this->cleanText($this->input('category')),
            'level' => $this->cleanEnum($this->input('level'), array_column(CourseLevel::cases(), 'value')),
            'course_type' => $this->cleanEnum($this->input('course_type'), array_column(CourseType::cases(), 'value')),
        ]);
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function cleanEnum(mixed $value, array $allowed): ?string
    {
        $value = $this->cleanText($value);

        if ($value === null) {
            return null;
        }

        return in_array(Str::lower($value), $allowed, true) ? Str::lower($value) : null;
    }

    public function search(): ?string
    {
        return $this->validated('q');
    }

    public function category(): ?string
    {
        return $this->validated('category');
    }

    public function level(): ?string
    {
        return $this->validated('level');
    }

    public function courseType(): ?string
    {
        return $this->validated('course_type');
    }
}
