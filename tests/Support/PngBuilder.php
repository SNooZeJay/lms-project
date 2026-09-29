<?php

namespace Tests\Support;

/**
 * Builds a real image file for a test, at a size the test asks for.
 *
 * WHY NOT A BASE64 BLOB
 *
 * A fixture that is a hard coded JPEG has whatever dimensions whoever pasted it had,
 * so a test that needs a 120 by 90 image to be refused and a test that needs an
 * 8 megapixel one to be too large would both be asserting about a constant rather
 * than about the rule. The rules here are about size, type and shape, so the fixture
 * has to be able to be any of them on purpose.
 *
 * WHY PNG AND NOT JPEG
 *
 * The GD extension is not installed on the machine this project is developed on, and
 * a test that quietly needs an extension it does not have is a test that fails for a
 * reason nobody reading it would predict. PNG can be written by hand from zlib, which
 * PHP always has, so the fixture does not depend on anything optional.
 *
 * WHAT IS WRITTEN IS A REAL PNG
 *
 * Signature, a header carrying the true dimensions, the pixel rows compressed with
 * zlib, and an end marker. finfo reads it as image/png and getimagesize reads the
 * dimensions out of the header, which is the whole point: the application's checks
 * are given a genuine file rather than a text file with an image extension.
 */
final class PngBuilder
{
    // A static class, so every method here is static and no call site may use $this.
    // The one that did was left over from an earlier version where and was an
    /**
     * A PNG at a chosen size, written to a temporary file and returned with its path.
     *
     * $noise makes the pixels incompressible, so the file really is as large as its
     * dimensions say. A solid rectangle compresses to a few kilobytes at any size,
     * which would make a test of a size limit test nothing.
     *
     * @return array{path: string, bytes: int}
     */
    public static function at(int $width, int $height, bool $noise = false, string $name = 'cover.png'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.png';

        file_put_contents($path, self::encode($width, $height, $noise));

        return ['path' => $path, 'bytes' => (int) filesize($path), 'name' => $name];
    }

    /**
     * The bytes of a PNG at a chosen size.
     *
     * One filter byte per scanline, then RGB triples, which is the simplest form a
     * PNG decoder will accept. The noise is pseudo random rather than truly random,
     * so the same size always produces a file of about the same length and a test
     * that measures a limit does not drift between runs.
     */
    private static function encode(int $width, int $height, bool $noise): string
    {
        $header = pack('NN', $width, $height)
            .chr(8)   // bit depth
            .chr(2)   // colour type: truecolour
            .chr(0)   // compression: deflate
            .chr(0)   // filter method
            .chr(0);  // no interlace

        /*
         | The pixels are compressed a band of rows at a time, not all at once.
         |
         | A whole 4000 by 3000 image is 36 MB of scanlines held as one string before
         | zlib ever sees it, and the first version of this ran out of memory
         | building a fixture for a test. Banding keeps the largest thing in memory
         | proportional to the band rather than to the picture, and zlib still sees
         | a stream it compresses well because a band of a noisy gradient compresses
         | about as badly as the whole thing.
         */
        /*
         | The streaming deflate API, in the zlib encoding rather than the raw one.
         |
         | A PNG's image data is a zlib stream, which begins 0x78, and raw deflate
         | begins with neither of those two bytes. The first version of this asked for
         | raw, produced something with no zlib header, and wrote a file that was not
         | a PNG. Which one gzinflate happens to read back on this build is a quirk
         | of that function; what matters is the header, so the encoding is chosen
         | from the format rather than from what a helper tolerates.
         */
        $deflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 1]);
        $band = max(1, (int) floor(1_048_576 / max(1, $width * 3 + 1)));

        $idat = '';

        for ($first = 0; $first < $height; $first += $band) {
            $idat .= deflate_add($deflate, self::band($width, $height, $first, min($first + $band, $height), $noise), ZLIB_NO_FLUSH);
        }

        $idat .= deflate_add($deflate, '', ZLIB_FINISH);

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', $header)
            .self::chunk('IDAT', $idat)
            .self::chunk('IEND', '');
    }

    /**
     * A band of scanlines: one filter byte per row, then RGB triples.
     *
     * The filter byte is 0, the "none" filter, so each row is stored as it is. The
     * noise is pseudo random rather than truly random, so the same size always
     * produces a file of about the same length and a test that measures a limit does
     * not drift between runs.
     */
    private static function band(int $width, int $height, int $from, int $to, bool $noise): string
    {
        $seed = 12345 + $from * 7919;
        $out = '';

        for ($y = $from; $y < $to; $y++) {
            $out .= "\x00";

            for ($x = 0; $x < $width; $x++) {
                if ($noise) {
                    $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                    $out .= chr(($seed >> 16) & 0xFF).chr(($seed >> 8) & 0xFF).chr($seed & 0xFF);
                } else {
                    // A soft diagonal gradient, so a thumbnail is not a flat
                    // rectangle and a person looking at a preview sees something.
                    $out .= chr((int) (($x * 255) / max(1, $width - 1)))
                        .chr((int) (($y * 255) / max(1, $height - 1)))
                        .chr(128);
                }
            }
        }

        return $out;
    }

    /**
     * One PNG chunk: a length, a type, the data, and a CRC over the two together.
     *
     * The CRC is what a decoder checks, and a file with a wrong one is a corrupt
     * file rather than an image. It is computed here because the fixture has to be
     * a real image for the application's own checks to mean anything.
     */
    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data))
            .$type
            .$data
            .pack('N', crc32($type.$data));
    }
}
