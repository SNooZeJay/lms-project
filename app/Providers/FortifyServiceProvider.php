<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\UserAccountStatus;
use App\Http\Responses\RoleBasedLoginResponse;
use App\Http\Responses\SafePasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Both branches of Fortify's password reset controller are pointed at the
        // same class. It is bound twice on purpose: the controller chooses
        // between a success and a failure response based on what the broker said,
        // and giving the two different classes is what lets the form reveal
        // which addresses have an account. One class answers both, so there is
        // nothing left to differ.
        $this->app->singleton(
            FailedPasswordResetLinkRequestResponseContract::class,
            SafePasswordResetLinkResponse::class,
        );
        $this->app->singleton(
            SuccessfulPasswordResetLinkRequestResponseContract::class,
            SafePasswordResetLinkResponse::class,
        );
        $this->app->singleton(LoginResponseContract::class, RoleBasedLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(trim((string) $request->input(Fortify::username())));
            $password = (string) $request->input('password');
            $user = User::where('email', $email)->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                return null;
            }

            if ($user->profile?->account_status !== UserAccountStatus::Active) {
                return null;
            }

            return $user;
        });

        Fortify::loginView('auth.login');
        Fortify::registerView('auth.register');
        Fortify::requestPasswordResetLinkView('auth.forgot-password');
        Fortify::resetPasswordView('auth.reset-password');
        Fortify::verifyEmailView('auth.verify-email');
        // The route is registered whenever views are enabled, so the response it
        // returns has to be bound. Left unbound, the container is asked to build
        // an interface and every signed-in person who opened the page got a 500.
        Fortify::confirmPasswordView('auth.confirm-password');

        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower((string) $request->input(Fortify::username())))
                .'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }
}
