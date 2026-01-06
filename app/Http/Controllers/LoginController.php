<?php

namespace App\Http\Controllers;

use App\Api\SSO\Authentication;
use App\Http\Resources\UserResource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    public function login()
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function redirect()
    {
        if (!Auth::check()) {
            $keycloak = Socialite::driver('keycloak')->user();

            Cache::put('id_token_hint', $keycloak->accessTokenResponseBody['id_token']);
            $response = Authentication::authenticate($keycloak->token);

            if (!$response) {
                $redirectUri = route('auth.unauthorized');
                return $this->logout($redirectUri);
            }

            $user = User::where('keycloak_id', $keycloak->id)->first();
            if ($user && $user->id != $response->id) {
                $user->update([
                    'id' => $response->id,
                    'nik' => $response->nik,
                    'name' => $response->name,
                    'email' => $keycloak['email'],
                ]);
            } else if (!$user) {
                $user = User::create([
                    'keycloak_id' => $keycloak->id,
                    'id' => $response->id,
                    'nik' => $response->nik,
                    'name' => $response->name,
                    'email' => $keycloak['email'],
                ]);
            }

            $user->photo_url = $response->photo_url ?? null;
            $user->save();

            Auth::login($user);
        }
        return redirect()->route('filament.app.pages.dashboard');
        // return redirect()->intended(route('filament.app.pages.dashboard'));
    }

    public function logout($redirectUri = null)
    {
        if (auth()->user()?->role === 'SADM') {
            Auth::logout();
            return redirect(route('home'));
        } else {
            if (!$redirectUri) {
                $redirectUri = route('home');
            }

            $client_id = env('KEYCLOAK_CLIENT_ID');
            $id_token = Cache::get('id_token_hint');

            Cache::forget('id_token_hint');

            Auth::logout();

            $logoutUrl = Socialite::driver('keycloak')->getLogoutUrl($redirectUri, $client_id, $id_token);

            return redirect($logoutUrl);
        }
    }

    public function unauthorized()
    {
        abort(401);
    }
}
