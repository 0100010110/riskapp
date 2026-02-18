<?php

namespace App\Providers;

use App\Http\Responses\FilamentLogoutResponse;
use App\Listeners\SyncMenusOnServingFilament;
use Filament\Events\ServingFilament;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use App\Models\Tmrisk;
use App\Observers\TmriskObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, FilamentLogoutResponse::class);
    }

    public function boot(): void
    {
        Tmrisk::observe(TmriskObserver::class);
        foreach (config('api') as $name => $api) {
            Http::macro($name, function () use ($api) {
                $http = Http::baseUrl($api['url']);

                if (($api['auth'] ?? null) === 'Bearer') {
                    $http = $http->withToken($api['secret']);
                } elseif (($api['auth'] ?? null) === 'Basic') {
                    $secret = explode("|", (string) ($api['secret'] ?? ''));
                    $http = $http->withBasicAuth($secret[0] ?? '', $secret[1] ?? '');
                }

                return $http;
            });
        }

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('keycloak', \SocialiteProviders\Keycloak\Provider::class);
        });

        Event::listen(ServingFilament::class, SyncMenusOnServingFilament::class);
    }
}
