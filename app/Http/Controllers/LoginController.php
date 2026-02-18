<?php

namespace App\Http\Controllers;

use App\Api\SSO\Authentication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class LoginController extends Controller
{
    public function login()
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function redirect(Request $request)
    {
        if (! Auth::check()) {
            try {
                $keycloak = Socialite::driver('keycloak')->user();
            } catch (InvalidStateException $e) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $keycloak = Socialite::driver('keycloak')->stateless()->user();
            }

            $idToken = data_get($keycloak, 'accessTokenResponseBody.id_token');
            if ($idToken) {
                $request->session()->put('id_token_hint', $idToken);
            }

            $response = Authentication::authenticate($keycloak->token);

            if (! $response) {
                return $this->logout($request, route('auth.unauthorized'));
            }

            $user = User::where('keycloak_id', $keycloak->id)->first();

            if ($user) {
                $user->forceFill([
                    'id'    => $response->id,
                    'nik'   => $response->nik,
                    'name'  => $response->name,
                    'email' => $keycloak->getEmail(),
                ])->save();
            } else {
                $user = User::create([
                    'keycloak_id' => $keycloak->id,
                    'id'          => $response->id,
                    'nik'         => $response->nik,
                    'name'        => $response->name,
                    'email'       => $keycloak->getEmail(),
                ]);
            }

            $user->photo_url = $response->photo_url ?? null;
            $user->save();

            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->intended(url('/'));
    }

    public function logout(Request $request, ?string $redirectUri = null)
    {
        $redirectUri ??= route('home');

        $role = Auth::user()?->role;

        $idToken = $request->session()->pull('id_token_hint');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($role === 'SADM') {
            return redirect($redirectUri);
        }

        $clientId = env('KEYCLOAK_CLIENT_ID');

        $logoutUrl = Socialite::driver('keycloak')
            ->getLogoutUrl($redirectUri, $clientId, $idToken);

        return redirect($logoutUrl);
    }

    public function unauthorized()
    {
        abort(401);
    }
}
