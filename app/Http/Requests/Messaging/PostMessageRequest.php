<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validating what somebody is about to post.
 *
 * The body is bounded here rather than by the column, so an oversized message
 * is refused with an explanation instead of being silently cut by the database
 * or throwing on a write the person can see the effect of.
 */
class PostMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The Action authorizes against the thread, which is the only place that
        // knows whether this person is in it. Returning true here is deliberate:
        // a refusal in this method could only be a guess, and a guess that
        // disagrees with the Policy is a second rule to keep in step.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            // Optional, so a form that predates idempotency still works, but a
            // token that is present must be a token, because it is the only
            // thing standing between a double click and two messages.
            'client_token' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Write something before sending.',
            'body.max' => 'A message is limited to 5000 characters.',
            'client_token.uuid' => 'The send token is not valid, so the message was not sent.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim((string) $this->input('body'))]);
        }
    }
}
