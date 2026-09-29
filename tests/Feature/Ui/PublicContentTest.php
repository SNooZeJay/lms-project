<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * The public pages are written for readers, not for the repository.
 *
 * Written after the About page shipped with a table naming the framework, the
 * programming language, the database and the build tool. Every row was true and
 * every row was the wrong answer: a reader who opened "About" to find out what a
 * certificate is, or whether they could learn Python on the site, was given a list
 * of packages instead. The page was accurate and useless at the same time, which
 * is the hardest kind of wrong to notice from the inside.
 *
 * The words below are the ones that mean "this was written by somebody showing
 * their work" rather than "this was written to somebody who has to use it".
 *
 * WHAT IS DELIBERATELY NOT HERE
 *
 * The name of a payment processor. A privacy notice has to say who receives a
 * customer's name, email and amount, because that is a fact about a person's data
 * rather than a fact about the build, and a notice that concealed it would be
 * worse than one that reads technically. The legal pages are written for people
 * with rights, not for developers, and the overlap between those two audiences is
 * not the overlap this test is about.
 *
 * The same goes for a currency code or a file format: a student is told a course
 * costs 1,200 pesos, not that amounts are held in minor units.
 */
class PublicContentTest extends TestCase
{
    /**
     * The public pages, and the routes that reach them.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function publicPages(): array
    {
        return [
            ['home', route('home')],
            ['about', route('about')],
            ['terms', route('legal.terms')],
            ['privacy', route('legal.privacy')],
        ];
    }

    /**
     * Words that belong in a repository and not on a page a student reads.
     *
     * @return list<string>
     */
    private function technicalWords(): array
    {
        return [
            'Laravel',
            'PHP 8',
            'Blade component',
            'Tailwind',
            'MySQL',
            'Vite',
            'npm',
            'Composer',
            'Eloquent',
            'migration',
            'middleware',
            'repository',
            'source code',
            'this application is built',
            'built with',
            'built on',
            'tech stack',
            'architecture',
            'web framework',
            'programming language',
            'database technology',
        ];
    }

    public function test_no_public_page_shows_technical_build_information(): void
    {
        $offenders = [];

        foreach ($this->publicPages() as [$name, $url]) {
            $body = (string) $this->get($url)->assertOk()->getContent();

            foreach ($this->technicalWords() as $word) {
                if (str_contains($body, $word)) {
                    $offenders[] = $name.': '.$word;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These words are on a page a student or an instructor reads. Somebody reading '
            ."\"About\" is asking what the platform is, not what it is made of:\n  "
            .implode("\n  ", $offenders)
        );
    }

    /**
     * The rendered text only, so a word inside a Blade comment cannot satisfy this.
     *
     * The About page explains in a comment that it used to contain a build table.
     * That comment must never reach a reader, and this test is what proves it
     * does not: the comments are stripped before the words are looked for.
     */
    public function test_the_build_words_are_absent_from_the_rendered_text_not_just_the_source(): void
    {
        $about = (string) $this->get(route('about'))->assertOk()->getContent();

        // Anything a reader can actually see. Comments, scripts and styles are not
        // read aloud and are not read by a person deciding whether to sign up.
        $visible = preg_replace(
            ['/<!--.*?-->/s', '/<script\b.*?<\/script>/is', '/<style\b.*?<\/style>/is'],
            ' ',
            $about
        );

        $this->assertIsString($visible);

        foreach (['Laravel', 'Tailwind', 'MySQL', 'framework', 'built with'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase(
                $word,
                $visible,
                "The word `{$word}` is visible on the About page."
            );
        }
    }

    /**
     * The About page has to actually be about the thing it is named after.
     *
     * A page can pass every test above by being short. These are the subjects it
     * has to cover for the name to be honest, and they are the questions a reader
     * opened the page to have answered.
     */
    public function test_the_about_page_answers_the_questions_its_name_promises(): void
    {
        $body = (string) $this->get(route('about'))->assertOk()->getContent();

        foreach ([
            // What it is, and for whom.
            'Information Technology',
            'Programming',
            'Web Development',
            'Cybersecurity',
            // What a student does.
            'student',
            'certificate',
            'quiz',
            'progress',
            // What an instructor does.
            'instructor',
            // Communication.
            'message',
        ] as $subject) {
            $this->assertStringContainsStringIgnoringCase(
                $subject,
                $body,
                "The About page says nothing about `{$subject}`, which is one of the reasons "
                .'somebody would open it.'
            );
        }
    }

    /**
     * The About page is reachable, and the navigation says so.
     */
    public function test_the_about_page_is_public_and_linked(): void
    {
        $this->get(route('about'))->assertOk();

        $this->assertGuest();

        $home = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('about'), $home, 'The home page does not link to About.');
    }
}
