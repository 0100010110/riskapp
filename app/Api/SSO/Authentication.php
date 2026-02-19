<?php

namespace App\Api\SSO;

class Authentication extends SSO
{
    protected static string $uri = '/authenticate';

    public static function authenticate(string $token): object|array|null
    {
        $response = self::base()
            ->withHeaders([
                'auth' => $token,
            ])
            ->get(self::$uri);

        return static::responseHandler($response, null);
    }
}
