<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function show(): View
    {
        return view('account.password');
    }

    public function update(ChangePasswordRequest $request, ChangePassword $changePassword): RedirectResponse
    {
        $changePassword->handle($request->user(), $request->validated());

        return redirect()
            ->route('account.profile')
            ->with('status', 'Your password has been changed.');
    }
}
