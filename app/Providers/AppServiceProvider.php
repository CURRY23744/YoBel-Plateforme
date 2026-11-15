<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // API seule : le lien de réinitialisation pointe vers l'interface (à créer), pas vers une route Laravel.
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('platform.frontend_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));
    }
}
