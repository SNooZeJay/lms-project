<?php

namespace App\Actions\Fortify;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $input['email'] = Str::lower(trim((string) $input['email']));
        $input['name'] = trim((string) $input['name']);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'role' => ['prohibited'],
            'account_status' => ['prohibited'],
            'must_change_password' => ['prohibited'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $profile = $user->profile()->create();
            $profile->role = UserRole::Student;
            $profile->account_status = UserAccountStatus::Active;
            $profile->must_change_password = false;
            $profile->save();

            return $user->load('profile');
        });
    }
}
