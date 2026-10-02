<?php

namespace App\Http\Requests\Instructor;

use App\Models\Assignment;
use App\Support\AssignmentFileRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Setting work against a lesson.
 *
 * The instructions are required and the rest is optional, which is the shape of
 * a real brief: someone has to be able to ask a question with three sentences
 * and no attachment.
 *
 * The Google Form URL is checked as a URL and nothing more. Whether the form
 * exists, belongs to the instructor, or is collecting anything is not something
 * this application can know, and pretending otherwise would be a check that
 * passes on nonsense.
 */
class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is proved in the controller against the course in the route,
        // where the lesson is resolved. Doing it here would mean re-resolving the
        // chain and getting a second, weaker answer.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:180',
                // One assignment per title per lesson, because the database says so
                // and a validation message that arrives before the constraint is
                // better than an integrity error.
                Rule::unique('assignments', 'title')
                    ->where('lesson_id', $this->route('lesson')->id)
                    ->ignore($this->route('assignment')),
            ],

            'instructions' => ['required', 'string', 'min:10', 'max:20000'],

            'form_url' => [
                'nullable',
                'url:http,https',
                'max:255',
                /*
                 | The host is named, because a form the instructor cannot open is
                 | worse than no form at all.
                 |
                 | Written as the string rule rather than as a `Rule::` builder,
                 | because there is no `Rule::regex` — the fluent form of a regular
                 | expression rule does not exist, and calling it throws a
                 | BadMethodCallException the first time a form is submitted rather
                 | than when the file is read.
                 |
                 | This checks the shape of the address and nothing more. Whether
                 | the form exists, belongs to this instructor, or is collecting
                 | anything is not knowable from here, and a check that appeared to
                 | be would pass on any URL containing the right two words.
                 */
                'regex:/^https:\/\/(docs\.google\.com|forms\.gle)\//i',
            ],

            'briefing' => [
                'nullable',
                'file',
                ...AssignmentFileRules::briefingRules(),
            ],

            /*
             | The mark scale.
             |
             | Nullable, because a brief may be set before the instructor has
             | decided how it is marked. A student may still submit against an
             | unmarked brief, and the instructor can fill the scale in later.
             */
            'max_score' => ['nullable', 'integer', 'min:1', 'max:10000'],

            'due_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instructions.min' => 'Describe what is being asked. A student should be able to answer from this alone.',
            'form_url.regex' => 'Use a Google Form link, which starts with https://docs.google.com/forms/ or https://forms.gle/',
            'max_score.min' => 'A mark has to be at least 1.',
            'max_score.max' => 'A mark above 10000 is almost certainly a typo.',
            'title.unique' => 'This lesson already has an assignment with that title.',
        ];
    }

    /*
     | No `validated()` override, and this note is here because there was one and
     | it took the whole feature down.
     |
     | `FormRequest::validated($key = null, $default = null)` is the framework's
     | method for reading validated data, and redeclaring it as
     | `validated(): array` is not a refinement of it, it is a different
     | signature for the same name. PHP rejects that at class load, which is
     | before any of this code runs, so the whole class fails to load and the
     | request dies with no message a test can read.
     |
     | The override existed to fold `publish` into the validated data. It was
     | never needed: the service reads the checkbox itself, so nothing has to be
     | added to the validated set, and `status` cannot be smuggled in through it
     | because `status` is not in `validated()` at all — it is derived in the
     | action, from the one field the form actually renders.
     */
}
