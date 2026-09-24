<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = request()->user()->load('profile');

        return view('account.profile', [
            'user' => $user,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $user->update([
                'name' => $validated['name'],
            ]);

            $user->profile->update([
                'bio' => $validated['bio'] ?? null,
            ]);
        });

        return redirect()
            ->route('account.profile')
            ->with('status', 'Profile updated.');
    }
}
