<?php

namespace App\Http\Middlewares;

use Filament\Http\Middleware\Authenticate as BaseAuthenticate;

class Authenticate extends BaseAuthenticate
{

    protected function redirectTo($request): ?string
    {
        return route('auth.login');
    }
}
