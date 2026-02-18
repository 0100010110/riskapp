<?php

namespace App\Http\Middlewares;

use Filament\Http\Middleware\Authenticate as BaseAuthenticate;

class Authenticate extends BaseAuthenticate
{
    protected function redirectTo($request): ?string
    {
        if (method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return null;
        }

        return route('auth.login');
    }
}
