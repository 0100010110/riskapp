<?php

namespace App\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Authenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->routeIs('auth.login', 'auth.redirect', 'logout') || $request->is('logout')) {
                return $next($request);
            }

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('auth.login');
        }

        return $next($request);
    }
}
