<?php

namespace App\Services\Certificates;

use App\Models\Certificate;

/**
 * Builds an unpredictable certificate code.
 */
class CertificateCodeGenerator
{
    /**
     * Upper-case Crockford-style alphabet with no vowels, so a code cannot be
     * read aloud as a word.
     */
    private const ALPHABET = '0123456789BCDFGHJKLMNPQRSTVWXYZ';

    public function generate(): string
    {
        $body = '';

        for ($i = 0; $i < 16; $i++) {
            $body .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'ITH-'.implode('-', str_split($body, 4));
    }

    /**
     * A code that no other certificate is using.
     */
    public function unique(): string
    {
        do {
            $code = $this->generate();
        } while (Certificate::query()->where('certificate_code', $code)->exists());

        return $code;
    }
}
