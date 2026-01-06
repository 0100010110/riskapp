<?php

namespace App\Api\SSO;

class Application extends SSO
{
    protected static $uri = '/applications';

    public static function find($clientId)
    {
        return self::get(self::$uri . '/' . $clientId);
    }
}
