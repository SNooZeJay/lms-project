<?php

namespace App\Http\Requests\Courses;

use App\Enums\LearningMaterialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLearningMaterialRequest extends FormRequest
{
    /**
     * Material types that need a file. Uploads are not built yet, so these stay blocked.
     */
    private const FILE_TYPES = ['image', 'pdf', 'document'];

    private const CONTENT_TYPES = ['text', 'code'];

    private const LINK_TYPES = ['video_link', 'external_link'];

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
            'title' => ['required', 'string', 'max:255'],
            'material_type' => [
                'required',
                Rule::enum(LearningMaterialType::class),
                Rule::notIn(self::FILE_TYPES),
            ],
            'content_text' => ['nullable', 'string', 'max:100000'],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
            'lesson_id' => ['prohibited'],
            'uploaded_by' => ['prohibited'],
            'position' => ['prohibited'],
            'storage_disk' => ['prohibited'],
            'storage_path' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'byte_size' => ['prohibited'],
            'file' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material_type.not_in' => 'File materials are not available yet. Choose Text, Code, Video link, or External link.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('material_type');

            if (in_array($type, self::CONTENT_TYPES, true) && ! $this->filled('content_text')) {
                $validator->errors()->add('content_text', 'A text or code material needs its content.');
            }

            if (in_array($type, self::LINK_TYPES, true) && ! $this->filled('external_url')) {
                $validator->errors()->add('external_url', 'A link material needs a valid link.');
            }
        });
    }
}
