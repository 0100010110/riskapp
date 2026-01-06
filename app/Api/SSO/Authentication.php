<?php

namespace App\Api\SSO;

use Illuminate\Support\Facades\Http;

class Authentication extends SSO
{
    protected static $uri = "/authenticate";

    public static function authenticate($token)
    {
        $response = self::base()->withHeader('auth', $token)->get(self::$uri);
        return static::responseHandler($response, null);
    }
}
