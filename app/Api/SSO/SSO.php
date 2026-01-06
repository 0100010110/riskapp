<?php

namespace App\Api\SSO;

use App\Api\Api;
use Illuminate\Http\Client\Response;

class SSO extends Api
{
    protected static $api = 'sso';

    protected static function responseHandler(Response $response, $default = null)
    {
        $result = $response->object();
        $callerFunction = debug_backtrace()[1]['function'];
        if ($callerFunction == 'get' || $callerFunction == 'authenticate') {
            if ($response->status() === 200) {
                return $result->data;
            } else {
                return $default;
            }
        } else {
            return $result;
        }
    }
}
