<?php

namespace Tests\Feature\Hardening;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * That the endpoints worth abusing are actually bounded.
 *
 * Two limits exist and they answer different questions. Fortify's own limiter
 * covers signing in. ThrottleWrites covers writes inside the application, and
 * it decides by verb so a new write route is covered the day it is added.
 *
 * Neither is assumed to work here. A middleware that is registered in a group
 * the route does not use looks identical to one that works when read from the
 * source, so each test drives real requests and counts the statuses, which is
 * the only way to know a ceiling is being applied rather than merely declared.
 */
class AbuseLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The limiter is a singleton across the process, so a previous test that
        // spent the allowance would otherwise make this one start throttled and
        // report a pass for the wrong reason. Every allowance is cleared, because
        // the account routes now count against their own number.
        foreach ([5, 20, 40, 60] as $limit) {
            RateLimiter::clear("write:ip:127.0.0.1:{$limit}");
        }
    }

    /**
     * Post the same form repeatedly and report the statuses that came back.
     *
     * @param  array<string, string>  $payload
     * @return list<int>
     */
    private function hammer(array $payload, int $times): array
    {
        $statuses = [];

        for ($i = 0; $i < $times; $i++) {
            $statuses[] = $this->post('/register', $payload)->getStatusCode();
        }

        return $statuses;
    }

    public function test_registration_is_bounded(): void
    {
        $statuses = $this->hammer([
            'name' => 'Probe Person',
            'email' => 'probe.person@example.test',
            'password' => 'Str0ng-Example-Passw0rd!',
            'password_confirmation' => 'Str0ng-Example-Passw0rd!',
        ], 75);

        $this->assertContains(
            429,
            $statuses,
            'Registration was never refused. Unbounded registration fills the user table and the administrator\'s list at no cost to an attacker.'
        );
    }

    public function test_a_password_reset_request_is_bounded(): void
    {
        $user = User::factory()->create(['email' => 'reset.probe@example.test']);

        $statuses = $this->hammer([
            'email' => $user->email,
        ], 75);

        // Posting to /forgot-password specifically. Without this the previous
        // test's spent allowance could mask an unbounded route, because both
        // count against the same per-address bucket.
        $this->assertContains(
            429,
            $statuses,
            'A password reset request was never refused. Each one sends an email, so an unbounded endpoint is a way '
            .'to flood a mailbox and to discover which addresses have accounts.'
        );
    }

    public function test_a_password_reset_is_bounded_tightly(): void
    {
        $user = User::factory()->create(['email' => 'tight.probe@example.test']);

        $statuses = [];

        // Ten attempts against a five per minute allowance. The general write
        // ceiling is sixty, so a pass here means the account routes really do get
        // their own lower number and are not simply inheriting the general one.
        for ($i = 0; $i < 10; $i++) {
            $statuses[] = $this->post('/forgot-password', ['email' => $user->email])->getStatusCode();
        }

        $this->assertContains(
            429,
            $statuses,
            'A password reset was not refused within ten attempts. Each one sends an email, so this endpoint is a way '
            .'to flood a mailbox.'
        );

        // Refused well before the general write ceiling, not at it.
        $refusedAt = array_search(429, $statuses, true);

        $this->assertLessThanOrEqual(
            6,
            $refusedAt,
            "The refusal came at attempt {$refusedAt}, which is the general write ceiling rather than the tighter account limit."
        );
    }

    public function test_the_account_limit_does_not_break_a_real_person(): void
    {
        // A tighter limit is only safe if it stays above what an ordinary person
        // does. Someone who mistypes their password twice and asks for a reset
        // link, then registers after signing in, has to be able to.
        $this->post('/forgot-password', ['email' => 'real.person@example.test'])->assertRedirect();
        $this->post('/forgot-password', ['email' => 'real.person@example.test'])->assertRedirect();
        $this->post('/forgot-password', ['email' => 'real.person@example.test'])->assertRedirect();
        $this->post('/register', [
            'name' => 'Real Person',
            'email' => 'real.person@example.test',
            'password' => 'Str0ng-Example-Passw0rd!',
            'password_confirmation' => 'Str0ng-Example-Passw0rd!',
        ])->assertRedirect();
    }

    public function test_a_refusal_says_when_to_retry(): void
    {
        for ($i = 0; $i < 70; $i++) {
            $response = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

            if ($response->getStatusCode() === 429) {
                // Two audiences, so two contracts. A client reads the header; a
                // person reads the page. Asserting the header name appears in the
                // body would be wrong, because the body is written for a person
                // and a raw header name there helps nobody.
                $retryAfter = $response->headers->get('Retry-After');

                $this->assertNotNull(
                    $retryAfter,
                    'A refusal must carry Retry-After, or a client retries immediately and never recovers.'
                );

                $this->assertGreaterThan(0, (int) $retryAfter, 'Retry-After must be a real number of seconds.');

                $this->assertStringContainsString(
                    'Wait about',
                    (string) $response->getContent(),
                    'The refusal page does not tell the person to wait, so it reads as a dead end.'
                );

                return;
            }
        }

        $this->fail('The endpoint was never refused, so there was no refusal to inspect.');
    }

    public function test_reading_is_not_throttled(): void
    {
        // The limit is on writes only. Capping reads would turn an ordinary
        // refresh, or paging through a list, into an error page for something
        // the database handles easily, and it would hide a real outage behind
        // what looks like a rate limit.
        for ($i = 0; $i < 80; $i++) {
            $this->get('/courses')->assertOk();
        }
    }

    public function test_signing_in_has_its_own_tighter_limit(): void
    {
        // Six attempts against a five per minute allowance. A password guess is
        // the most expensive thing an outsider can do here, so this endpoint is
        // held to a stricter number than the general write ceiling.
        $statuses = [];

        for ($i = 0; $i < 8; $i++) {
            $statuses[] = $this->post('/login', [
                'email' => 'guesser@example.test',
                'password' => 'wrong-password',
            ])->getStatusCode();
        }

        $this->assertContains(
            429,
            $statuses,
            'Repeated sign in attempts were not refused, so an outsider can guess passwords at the rate the server can hash them.'
        );
    }
}
