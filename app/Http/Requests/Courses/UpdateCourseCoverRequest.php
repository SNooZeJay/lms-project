<?php

namespace App\Http\Requests\Courses;

use App\Services\Storage\CourseCoverStorage;
use App\Support\CourseCoverCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * The cover on a course, when it is being set or cleared on its own.
 *
 * WHY A REQUEST OF ITS OWN RATHER THAN THREE FIELDS ON THE COURSE FORM
 *
 * A cover is chosen after the course exists, changed months later, and removed on
 * its own. Putting it on the create and edit forms would mean a file input on two
 * pages that each have to explain which of the three ways of setting a cover is in
 * play, and a create form that cannot show a preview of something the course does not
 * have yet. One route with one request keeps the control in one place, which is where
 * somebody will look for it.
 *
 * WHAT IS REFUSED
 *
 * A photograph identifier that is not in the catalog. The form offers a fixed list and
 * posts which entry was picked, so a value outside the list is not a choice anybody
 * was made, and accepting it would store a string in the path column that no view can
 * render and no credit can describe.
 *
 * The cover's own columns are prohibited, as they are on the course requests. A
 * request must not be able to say "this cover is on that disk" or "this photograph
 * was taken by such and such": both are decided by the action from what was uploaded
 * or which catalog entry was chosen, and a request that could set them would be a way
 * to point a course at an address of the caller's choosing.
 */
class UpdateCourseCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The action asks the policy as well. This is here so an unauthorized
        // request is refused before a file is read.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cover_photo_id' => ['nullable', 'string', 'max:64'],
            'cover_file' => ['nullable', 'file', 'max:'.CourseCoverStorage::MAX_KILOBYTES],
            'remove_cover' => ['nullable', 'boolean'],
            'cover_alt' => ['nullable', 'string', 'max:160'],
            'thumbnail_path' => ['prohibited'],
            'cover_disk' => ['prohibited'],
            'cover_mime_type' => ['prohibited'],
            'cover_byte_size' => ['prohibited'],
            'cover_source' => ['prohibited'],
            'cover_credit_name' => ['prohibited'],
            'cover_credit_url' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateChoice($validator);
            $this->validateFile($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cover_file.max' => 'A cover image must be 2 MB or smaller.',
            'cover_photo_id.max' => 'That is not a cover on offer.',
        ];
    }

    /**
     * At most one of the three ways of setting a cover, and each one on its own terms.
     *
     * Two at once is a request that does not know what it is asking for, and letting
     * the action guess would mean the order of two fields in a form decides which one
     * a course ends up with.
     */
    private function validateChoice(Validator $validator): void
    {
        $chosen = [
            'an uploaded image' => $this->hasFile('cover_file'),
            'a photograph from the catalog' => filled($this->input('cover_photo_id')),
            'removing the current cover' => $this->boolean('remove_cover'),
        ];

        $given = array_keys(array_filter($chosen));

        if (count($given) > 1) {
            $validator->errors()->add(
                'cover_file',
                'Choose one of these, not several: '.implode(', ', $given).'.'
            );

            return;
        }

        $identifier = $this->input('cover_photo_id');

        if (filled($identifier) && ! CourseCoverCatalog::offers((string) $identifier)) {
            $validator->errors()->add(
                'cover_photo_id',
                'That photograph is not one of the covers on offer.'
            );
        }
    }

    /**
     * The type is read from the file, and the size and shape are checked on it.
     *
     * The extension on the name and the type the browser sends are both claims about
     * the file rather than facts about it, so neither is used. What is checked is
     * what the bytes say they are, which is the only thing that decides whether a
     * browser will treat the result as an image.
     *
     * The shape is checked because a cover is displayed in a fixed box. A photograph
     * narrower than the smallest box it appears in is stretched to fill it and looks
     * broken rather than cropped, and refusing it here says so in words the
     * instructor can act on, where a stretched image says nothing at all.
     */
    private function validateFile(Validator $validator): void
    {
        if (! $this->hasFile('cover_file')) {
            return;
        }

        $file = $this->file('cover_file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            $validator->errors()->add('cover_file', 'That image did not upload. Try again.');

            return;
        }

        $storage = app(CourseCoverStorage::class);
        $detected = $storage->detectMimeType($file);

        if (! in_array($detected, CourseCoverStorage::ALLOWED_MIME_TYPES, true)) {
            $validator->errors()->add(
                'cover_file',
                'A cover must be a JPEG, PNG or WebP image. That file is '.($detected === '' ? 'not an image this application can read' : 'a '.$detected).'.'
            );

            return;
        }

        [$width, $height] = $this->dimensionsOf($file);

        if ($width === null || $height === null) {
            $validator->errors()->add('cover_file', 'That image could not be read.');

            return;
        }

        if ($width < CourseCoverStorage::MIN_WIDTH || $height < CourseCoverStorage::MIN_HEIGHT) {
            $validator->errors()->add(
                'cover_file',
                sprintf(
                    'A cover must be at least %d by %d pixels. That one is %d by %d.',
                    CourseCoverStorage::MIN_WIDTH,
                    CourseCoverStorage::MIN_HEIGHT,
                    $width,
                    $height,
                )
            );
        }
    }

    /**
     * The real dimensions, read from the file.
     *
     * getimagesize reads the header rather than decoding the whole picture, so a
     * large upload is measured cheaply. It reads only what is in the bytes, so a
     * renamed file answers with its true shape or with nothing.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensionsOf(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            return [null, null];
        }

        return [(int) $size[0], (int) $size[1]];
    }
}
