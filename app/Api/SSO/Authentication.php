<?php

namespace App\Api\SSO;

class Authentication extends SSO
{
    protected static string $uri = '/authenticate';

    public static function authenticate(string $token): object|array|null
    {
        $response = self::base()
            // tetap pakai header "auth" sesuai implementasi kamu sekarang:
            ->withHeaders([
                'auth' => $token,
            ])
            // optional: kalau SSO kamu sebenarnya pakai Authorization: Bearer
            // ->withToken($token)
            ->get(self::$uri);

        return static::responseHandler($response, null);
    }
}
