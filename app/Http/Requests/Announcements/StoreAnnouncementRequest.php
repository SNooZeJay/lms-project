<?php

namespace App\Http\Requests\Announcements;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What somebody may say out loud.
 *
 * The length limits mirror the columns so an oversized announcement is refused
 * with an explanation rather than being silently cut by the database or throwing
 * on a write whose author can see the form.
 *
 * The scope is not accepted from the request. Which kind of announcement this is
 * is decided by which form was submitted, because a field would let a request
 * choose to be a platform announcement.
 */
class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Deliberately true. Whether this person may publish here is answered by
        // AnnouncementPolicy inside the Action, which is the only place that knows
        // whether the course is theirs. A guess here would be a second rule.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the announcement a title.',
            'title.min' => 'The title is too short to be useful.',
            'title.max' => 'The title is limited to 160 characters.',
            'body.required' => 'An announcement with no text says nothing.',
            'body.min' => 'Say a little more than that.',
            'body.max' => 'The announcement is limited to 5000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'title' => is_string($this->input('title')) ? trim((string) $this->input('title')) : null,
            'body' => is_string($this->input('body')) ? trim((string) $this->input('body')) : null,
        ], fn ($value): bool => $value !== null));
    }
}
