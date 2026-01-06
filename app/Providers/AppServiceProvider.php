<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (config('api') as $name => $api) {
            Http::macro($name, function () use ($api) {
                $http = Http::baseUrl($api['url']);
                if ($api['auth'] == 'Bearer') {
                    $http = $http->withToken($api['secret']);
                } else if ($api['auth'] == 'Basic') {
                    $secret = explode("|", $api['secret']);
                    $http = $http->withBasicAuth($secret[0], $secret[1]);
                }
                return $http;
            });
        }

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('keycloak', \SocialiteProviders\Keycloak\Provider::class);
        });
    }
}
