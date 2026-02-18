<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Exports\RiskRegisterDownloadController;




Route::get('/', function () {
    return redirect()->route('filament.app.pages.dashboard');
})->name('home');


Route::get('/root', function () {
    return redirect('/home');
})->name('root');


Route::controller(LoginController::class)
    ->name('auth.')
    ->group(function () {
        Route::get('/login', 'login')->name('login');

       
        Route::get('/redirect', 'redirect')->name('redirect');
        Route::get('/unauthorized', 'unauthorized')->name('unauthorized');
        
        Route::get('/auth/redirect', function () {
            return redirect()->route('auth.redirect');
        });

        Route::post('/logout', 'logout')->name('logout');
    });

Route::middleware(['web', 'auth'])
    ->get('/exports/risk-register/{token}', RiskRegisterDownloadController::class)
    ->name('exports.risk-register');

Route::middleware(['web'])->group(function () {
    Route::get('/risks/print/{token}', [RiskRegisterPrintController::class, 'download'])
        ->name('risks.print');
});

Route::get('/filament/login', [LoginController::class, 'login'])
    ->name('filament.app.auth.login');

Route::post('/filament/logout', [LoginController::class, 'logout'])
    ->name('filament.app.auth.logout');


Route::get('/filament-login', function () {
    return redirect()->route('auth.login');
})->name('filament-login');
