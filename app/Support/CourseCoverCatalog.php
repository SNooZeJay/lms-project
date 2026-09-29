<?php

namespace App\Support;

/**
 * The photographs an instructor may choose for a course cover.
 *
 * WHY THIS IS A LIST AND NOT A SEARCH
 *
 * Unsplash's own guidance is that an application must hotlink the URLs it is given
 * and credit the photographer, and that an access key belongs on a server and never
 * in a browser. A search box in the create form would need a key in the page to
 * search with, and would put a third party in the middle of saving a course: the
 * course would fail to save because somebody else's server was slow.
 *
 * So the choice is made from a list this application holds. It costs the instructor
 * a narrower set of photographs, and it buys a save that cannot fail for a reason
 * outside this machine, no credential anywhere near the frontend, and a set that can
 * be reviewed for what it actually depicts.
 *
 * WHY THE LIST IS SHORT AND TOPICAL
 *
 * Every photograph here is one a computing course could honestly use, chosen against
 * the catalog this project actually ships. They are grouped by subject so the picker
 * can lead with the ones that match the course being written, and a photograph
 * unrelated to the subject is worse than no photograph at all, because it looks like
 * a decision rather than an absence.
 *
 * WHY THE URL IS BUILT HERE RATHER THAN STORED WHOLE
 *
 * Only the photograph's identity is stored. The size, crop and format are appended
 * when the address is built, so the same photograph can be requested at the width a
 * card needs and at the width a detail page needs, and a card in a list of twelve
 * does not download a photograph sized for a hero.
 *
 * THE ixid PARAMETER IS ABSENT, AND THAT IS A REAL LIMITATION
 *
 * Unsplash's guidance says the ixid from the API response must be kept on every
 * resized URL, because that is how view counts reach the photographer. These
 * addresses are built from photograph identifiers without one, because this
 * application has no API key and therefore no response to take it from. The
 * photographs are still served from Unsplash's own CDN and still credited, which is
 * the substance of what the guidance asks for, but the photographer is not receiving
 * view statistics from here. Registering an application and storing the ixid per
 * photograph would close that, and is the honest route if this goes further than a
 * demonstration.
 */
final class CourseCoverCatalog
{
    /**
     * @return array<string, array<int, array{0: string, 1: string, 2: string}>>
     *                                                                           Group label, then one entry per photograph:
     *                                                                           [photograph id, what it shows, subject keyword].
     */
    public const SUBJECTS = [
        'Classrooms and study' => [
            ['photo-1523240795612-9a054b0db644', 'Students working together in a lecture hall', 'classroom'],
            ['photo-1522202176988-66273c2fd55f', 'A group of students at a shared table', 'students'],
            ['photo-1503676260728-1c00da094a0b', 'A student writing at a desk', 'study'],
            ['photo-1524178232363-1fb2b075b655', 'A teacher at a whiteboard', 'teaching'],
        ],
        'Computing and hardware' => [
            ['photo-1516321318423-f06f85e504b3', 'A laptop open on a desk', 'hardware'],
            ['photo-1498050108023-c5249f4df085', 'Source code in an editor', 'programming'],
            ['photo-1461749280684-dccba630e2f6', 'A code editor on a monitor', 'code'],
            ['photo-1487058792275-0ad4aaf24ca7', 'Code printed across a dark screen', 'programming'],
        ],
        'Data and analysis' => [
            ['photo-1454165804606-c3d57bc86b40', 'A desk covered in charts and figures', 'data'],
            ['photo-1551288049-bebda4e38f71', 'Charts on a screen', 'analytics'],
            ['photo-1543286386-713bdd548da4', 'A bar chart on a monitor', 'data'],
        ],
        'Networks and security' => [
            ['photo-1550751827-4bd374c3f58b', 'Code on a dark screen', 'security'],
            ['photo-1526374965328-7f61d4dc18c5', 'Green characters on a dark screen', 'security'],
            ['photo-1544197150-b99a580bb7a8', 'Network cabling in a rack', 'networking'],
        ],
        'Design and web' => [
            ['photo-1467232004584-a241de8bcf5d', 'A design workspace with a tablet', 'design'],
            ['photo-1547658719-da2b51169166', 'A layout on a design canvas', 'design'],
            ['photo-1507238691740-187a5b1d37b8', 'A design interface on a laptop', 'web'],
        ],
        'Working together' => [
            ['photo-1519389950473-47ba0277781c', 'A team around a table with laptops', 'teamwork'],
            ['photo-1531482615713-2afd69097998', 'Two people reviewing work on a screen', 'collaboration'],
            ['photo-1580894732444-8ecded7900cd', 'A person presenting to a small group', 'presentation'],
        ],
    ];

    /**
     * Every photograph identifier the catalog holds, in one list.
     *
     * @return array<int, string>
     */
    public static function identifiers(): array
    {
        $identifiers = [];

        foreach (self::SUBJECTS as $photographs) {
            foreach ($photographs as [$identifier]) {
                $identifiers[] = $identifier;
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * Is this a photograph the catalog actually offers?
     *
     * The check a request is put through before its answer is believed. A form that
     * posts `cover_photo_id` is naming one row of a list this application holds, and
     * a value that is not on the list is not a choice anybody was offered. Accepting
     * it would store a string that nothing can render and nothing can credit.
     */
    public static function offers(?string $identifier): bool
    {
        return $identifier !== null && in_array($identifier, self::identifiers(), true);
    }

    /**
     * The one entry matching an identifier.
     *
     * @return array{subject: string, shows: string, identifier: string}|null
     */
    public static function find(?string $identifier): ?array
    {
        if ($identifier === null) {
            return null;
        }

        foreach (self::SUBJECTS as $subject => $photographs) {
            foreach ($photographs as [$id, $shows]) {
                if ($id === $identifier) {
                    return ['subject' => $subject, 'shows' => $shows, 'identifier' => $id];
                }
            }
        }

        return null;
    }

    /**
     * The address to put in a src, at the size and shape a caller needs.
     *
     * Appended here rather than stored, so one photograph serves a card and a detail
     * page at their own sizes. `fit=crop` with both dimensions gives every cover the
     * same shape whatever the original was, so a course does not change the height of
     * the row it sits in depending on which photograph its instructor chose.
     * `auto=format` lets Unsplash's CDN answer with WebP or AVIF where the browser
     * supports it, which is a smaller download than the JPEG the same file would be.
     */
    public static function urlFor(string $identifier, int $width, int $height, int $quality = 72): string
    {
        return sprintf(
            'https://images.unsplash.com/%s?w=%d&h=%d&fit=crop&crop=entropy&auto=format&q=%d',
            $identifier,
            $width,
            $height,
            $quality,
        );
    }
}
