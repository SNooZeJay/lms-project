<?php

namespace App\Http\Requests\Courses;

use App\Enums\LearningMaterialType;
use App\Support\MaterialFileRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateLearningMaterialRequest extends FormRequest
{
    /**
     * Material types whose body is text.
     */
    private const CONTENT_TYPES = ['text', 'code'];

    /**
     * Material types whose body is a link.
     */
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
            'material_type' => ['required', Rule::enum(LearningMaterialType::class)],
            'content_text' => ['nullable', 'string', 'max:100000'],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
            'form_context' => ['nullable', 'string', 'max:64'],
            'file' => ['nullable', 'file', 'max:'.MaterialFileRules::MAX_KILOBYTES],
            'lesson_id' => ['prohibited'],
            'uploaded_by' => ['prohibited'],
            'position' => ['prohibited'],
            'storage_disk' => ['prohibited'],
            'storage_path' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'byte_size' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'A file must be 10 MB or smaller.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->enumType();

            if ($type === null) {
                return;
            }

            if (in_array($type->value, self::CONTENT_TYPES, true) && ! $this->filled('content_text')) {
                $validator->errors()->add('content_text', 'A text or code material needs its content.');
            }

            if (in_array($type->value, self::LINK_TYPES, true) && ! $this->filled('external_url')) {
                $validator->errors()->add('external_url', 'A link material needs a valid link.');
            }

            $this->validateFile($validator, $type);
        });
    }

    private function validateFile(Validator $validator, LearningMaterialType $type): void
    {
        $needsFile = MaterialFileRules::isFileType($type->value);
        $hasFile = $this->hasFile('file');

        if ($needsFile && ! $hasFile) {
            $validator->errors()->add('file', 'This material type needs a file.');

            return;
        }

        if (! $needsFile && $hasFile) {
            $validator->errors()->add('file', 'This material type does not take a file.');

            return;
        }

        if (! $hasFile) {
            return;
        }

        $file = $this->file('file');
        $extension = MaterialFileRules::extensionOf($file);

        if (! in_array($extension, MaterialFileRules::allowedExtensions($type), true)) {
            $validator->errors()->add('file', 'That file type is not allowed for this material.');

            return;
        }

        $detected = $this->detectMimeType($file);

        if (! in_array($detected, MaterialFileRules::allowedMimeTypes($type), true)) {
            $validator->errors()->add('file', 'That file content is not allowed for this material.');
        }
    }

    /**
     * The real MIME type comes from the file, never from the browser.
     */
    private function detectMimeType(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            return '';
        }

        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';
    }

    private function enumType(): ?LearningMaterialType
    {
        $value = $this->input('material_type');

        return is_string($value) ? LearningMaterialType::tryFrom($value) : null;
    }
}
