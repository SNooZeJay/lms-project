<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * The one answer the Forgot Password form gives, whether or not an account exists.
 *
 * Fortify's controller picks between two response classes based on what the
 * password broker said, and by default it tells the difference: the success one
 * says the link was emailed, the failure one says no account was found. Those
 * two sentences are enough to discover who studies here, one address at a time,
 * from a form that needs no account to use.
 *
 * So this class implements both contracts and returns the same sentence for
 * either. Binding the success contract to a class of its own would leave two
 * places holding this sentence, and two places holding a sentence is two places
 * that can drift apart and reopen the leak the next time one of them is edited.
 * One class, one message, no branch to get wrong.
 *
 * The wording is deliberate. It does not confirm and does not deny, which is the
 * only way to keep the answer identical. "We have emailed your password reset
 * link" would be friendlier and would undo the whole point.
 */
class SafePasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * The neutral sentence, in one place.
     *
     * Both the session flash and the JSON body read it, so the two formats a
     * caller might use cannot say different things either.
     */
    public const MESSAGE = 'If an account exists for that email, a password reset link has been sent.';

    public function __construct(public readonly string $status) {}

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => self::MESSAGE]);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', self::MESSAGE);
    }
}
