<?php

namespace App\Providers;

use App\Support\Fallbacks;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach (config('modules.enabled', []) as $provider) {
            $this->app->register($provider);
        }

        Fallbacks::register($this->app);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SRS-RBC-01: registers the "keycloak" Socialite driver used for the
        // SSO MAIS OIDC login (see App\Http\Controllers\Auth\OidcController).
        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);
    }
}
