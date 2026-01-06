<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('filament.app.pages.dashboard');
})->name('home');

Route::controller(LoginController::class)
    ->name('auth.')
    ->group(function () {
        Route::get('/login', 'login')->name('login');
        Route::get('/redirect', 'redirect')->name('redirect');
        Route::get('/unauthorized', 'unauthorized')->name('unauthorized');

        Route::get('/logout', 'logout')->name('logout');
    });
