<?php

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\Client;
use App\Models\TeamMember;
use App\Models\Workspace;
use App\Policies\TeamMemberPolicy;
use App\Policies\WorkspacePolicy;
use App\Console\Commands\CreateClientPermission;
use Laravel\Passport\Passport;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(TeamMember::class, TeamMemberPolicy::class);

        Passport::useClientModel(Client::class);

        Passport::authorizationView('auth.oauth.authorize');

        // Enable password grants for testing
        Passport::enablePasswordGrant();

        Vite::prefetch(concurrency: 3);

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        VerifyEmail::createUrlUsing(function ($notifiable) {
            return URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                    ...request()->session()->pull('client', [])
                ]
            );
        });

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
                ...request()->session()->pull('client', [])
            ], false));
        });
    }
}
