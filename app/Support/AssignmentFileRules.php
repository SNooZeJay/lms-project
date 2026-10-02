<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * What an instructor may attach as a brief, and what a student may hand in.
 *
 * Written beside the learning material rules rather than inside a controller,
 * because these limits are a rule about the school, not about one screen, and
 * two controllers holding different copies of the same rule is how they drift.
 *
 * THE DIFFERENCE BETWEEN THE TWO ROLES
 *
 * An instructor sends work *out*. A student sends work *in*, and anything a
 * student uploads is untrusted by definition: it is a file that arrived from a
 * browser with no server involvement in what produced it. The student's list is
 * therefore narrower than it might be, and both extension and real media type
 * are checked rather than trusting either alone.
 *
 * The media type is read from the file's own contents rather than the browser's
 * declared type, because the declared type is a string the sender chose. A file
 * named `essay.pdf` that is really a `.php` web shell is rejected here rather
 * than stored and served later.
 */
final class AssignmentFileRules
{
    /**
     * What a student may hand in.
     *
     * A written answer as a document, or as a picture of one. A screenshot is
     * explicitly allowed, because for a screenshot-based task it is the correct
     * answer and refusing it would be pedantic.
     */
    public const SUBMISSION_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'odt',
        'rtf',
        'txt',
        'png',
        'jpg',
        'jpeg',
        'webp',
    ];

    /**
     * The largest thing a student may hand in.
     *
     * Ten mebibytes, the same ceiling as a learning material. A scanned essay
     * is comfortably inside it and a video of a screen is not, which is correct:
     * a video is not a submission.
     */
    public const MAX_SUBMISSION_KILOBYTES = 10240;

    /**
     * What an instructor may attach as a brief.
     *
     * A document or a picture of one. Not a spreadsheet or a presentation,
     * because those belong as learning materials, where they already are.
     */
    public const BRIEFING_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'odt',
        'rtf',
        'txt',
        'png',
        'jpg',
        'jpeg',
        'webp',
    ];

    public const MAX_BRIEFING_KILOBYTES = 10240;

    /**
     * The rules for a student's hand-in.
     *
     * @return array<int, mixed>
     */
    public static function submissionRules(): array
    {
        return [
            'required',
            'file',
            'max:'.self::MAX_SUBMISSION_KILOBYTES,
            // The extension list is checked by the name, the media list by the
            // bytes. Both, because either alone can be fooled.
            'extensions:'.implode(',', self::SUBMISSION_EXTENSIONS),
            'mimetypes:image/jpeg,image/png,image/webp,text/plain,'
                .'application/pdf,application/msword,'
                .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                .'application/vnd.oasis.opendocument.text,application/rtf',
        ];
    }

    /**
     * The rules for an instructor's brief.
     *
     * @return array<int, mixed>
     */
    public static function briefingRules(): array
    {
        return [
            'max:'.self::MAX_BRIEFING_KILOBYTES,
            'extensions:'.implode(',', self::BRIEFING_EXTENSIONS),
            'mimetypes:image/jpeg,image/png,image/webp,text/plain,'
                .'application/pdf,application/msword,'
                .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                .'application/vnd.oasis.opendocument.text,application/rtf',
        ];
    }

    /**
     * Is this filename something we will accept?
     *
     * Used by the validation message, which has to name the files that would have
     * worked. "The file failed validation" sends somebody to guess.
     */
    public static function describe(string $role = 'submission'): string
    {
        $list = $role === 'briefing'
            ? self::BRIEFING_EXTENSIONS
            : self::SUBMISSION_EXTENSIONS;

        return implode(', ', $list);
    }

    /**
     * A sentence for the error, built from the rule rather than written twice.
     */
    public static function message(string $attribute, string $role = 'submission'): array
    {
        $what = $role === 'briefing' ? 'A briefing document' : 'Your file';

        return [
            $attribute.'.max' => $what.' must be under '.round(self::MAX_SUBMISSION_KILOBYTES / 1024).' MB.',
            $attribute.'.extensions' => $what.' must be one of: '.self::describe($role).'.',
            $attribute.'.mimetypes' => $what.' does not look like a '.$what.' we accept. '
                .'Accepted types: '.self::describe($role).'.',
            $attribute.'.required' => $role === 'briefing'
                ? 'Attach a briefing document, or remove this and keep the instructions.'
                : 'Choose a file to hand in.',
        ];
    }

    /**
     * A generated name, never the name that arrived.
     */
    public static function storedName(UploadedFile $file): string
    {
        return $file->hashName();
    }
}
