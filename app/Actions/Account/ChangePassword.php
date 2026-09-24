<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePassword
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input): void
    {
        if (! Hash::check((string) $input['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'password' => $input['password'],
            ])->save();

            $user->profile?->forceFill([
                'must_change_password' => false,
            ])?->save();
        });
    }
}
