<?php

namespace App\Http\Requests\Student;

use App\Support\AssignmentFileRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A student handing in work.
 *
 * One file, nothing else. There is no free-text answer field and no "I would
 * rather not upload" checkbox, because a student who answers in prose has not
 * submitted anything and a submission with no file in it is a row that looks
 * like work and is not.
 *
 * The file is the only thing accepted, and it is checked on both its name and
 * its contents. Both checks are needed: the name is what the interface will
 * show the instructor, and the contents are what will be opened.
 */
class SubmitAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Whether this student may be given this assignment at all, and whether
        // they may replace a previous hand-in, is decided in the controller where
        // the assignment is resolved. Authorising on a boolean here would only
        // prove that the route middleware ran.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'submission' => [
                ...AssignmentFileRules::submissionRules(),
                // The name is a label for the instructor, so it is trimmed of
                // control characters and capped, but it is never used as a path.
                // The stored name is generated. See AssignmentService::submit.
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AssignmentFileRules::message('submission', 'submission');
    }

    /**
     * Names, for the instructor's reading.
     *
     * `attributes` rather than a message per rule, because the rules already
     * read correctly once the field is called what it is.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'submission' => 'your file',
        ];
    }
}
