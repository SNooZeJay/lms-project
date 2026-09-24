<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

class SafePasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(public readonly string $status) {}

    public function toResponse($request)
    {
        $message = 'If an account exists for that email, a password reset link has been sent.';

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message]);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', $message);
    }
}
