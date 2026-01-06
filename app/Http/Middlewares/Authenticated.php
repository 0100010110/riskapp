<?php

namespace App\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class Authenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Cache::get('redirect') && Auth::user()->role !== 'SADM') {
            Cache::set('redirect', $request->fullUrl());
            return Socialite::driver('keycloak')->redirect();
        } else {
            Cache::delete('redirect');
        }

        return $next($request);
    }
}
