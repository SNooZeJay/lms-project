<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a person goes after their address is verified.
 *
 * What happens without this. Following the link in the email marks the address
 * verified and then redirects to the verification notice, which belongs to an
 * account that has not been verified. That page answers a visit from somebody who
 * has with a redirect away from it, so the person is moved on to their profile
 * with nothing said: the account was correct, the redirect was correct, and the
 * one moment the product had somebody's attention produced no acknowledgement at
 * all. The same page could not have hosted the confirmation, because Fortify
 * never lets it render for a verified account.
 *
 * Why this is bound rather than configured. The address is read from
 * config('fortify.redirects.email-verification'), and three separate places read
 * that one value: this redirect, the resend, and the notice. Pointing it at the
 * confirmation would send somebody who has just asked for another email to a page
 * congratulating them on an address they have not confirmed yet. Changing the
 * shared value to fix one of the three is how the other two break.
 *
 * The response contract is the seam for exactly this redirect and nothing else,
 * so binding it changes where a verified person lands and leaves the resend and
 * the notice alone.
 *
 * A JSON client still gets the 204 with no body, because the confirmation is a
 * page for a person and a client that asked for JSON did not ask for one.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * The number of seconds the confirmation waits before continuing by itself.
     *
     * It is passed to the page rather than kept here, because the wait is a
     * presentation decision and the view is where presentation decisions live.
     * This value exists only so the two cannot drift apart silently.
     */
    public const AUTO_CONTINUE_SECONDS = 8;

    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json('', 204);
        }

        return redirect()->route('verification.confirmed', [
            'verified' => 1,
        ]);
    }
}
