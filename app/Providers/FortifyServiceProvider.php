<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\PasswordResetLinkRequested;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // "No link sent" answers exactly like "link sent": the form never reveals which emails have an account.
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequested::class);
    }

    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * RF_00_1: sign in with email + password (Q32). Emails are compared lowercase; accounts that
     * are deactivated (RF_88) or still waiting for their invitation (no password yet) cannot sign in.
     * Failed attempts fire Laravel's Failed event, which the audit log records.
     *
     * There is no "remember me": the field is dropped here, before Fortify reads it, so a crafted request cannot get
     * the long-lived cookie that would outlive the 120-minute session. An email without a usable account costs one
     * hash too, so the response time does not tell a visitor which emails are registered.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(trim((string) $request->input(Fortify::username())));
            $password = (string) $request->input('password');
            $request->merge(['remember' => false]);

            $user = User::query()->where('email', $email)->first();

            if ($user === null || $user->password === null) {
                Hash::make($password);

                return null;
            }

            if (! Hash::check($password, $user->password)) {
                return null;
            }

            if (! $user->isActive()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('auth.deactivated'),
                ]);
            }

            return $user;
        });
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /** 5 attempts per minute per email + IP (login) and per pending 2FA session (two-factor). */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
