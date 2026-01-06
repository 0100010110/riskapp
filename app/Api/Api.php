<?php

namespace App\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

abstract class Api
{
    protected static $api = null;

    abstract protected static function responseHandler(Response $response, $default = null);

    /**
     * @return PendingRequest
     */
    public static function base(): PendingRequest
    {
        // Use the macro/service name defined in static::$api
        $http = Http::asJson(); // Defaulting to JSON for better API compatibility

        $apiName = static::$api;

        // Dynamically call the configured HTTP client (e.g., Http::my_service())
        $http = Http::{$apiName}();

        if (config('api')[static::$api]['auth'] == 'Bearer') {
            return $http->withToken(Auth::user()->token);
        }

        return $http;
    }

    private static function fileHandler($file = []): PendingRequest
    {
        $http = self::base();

        if (count($file) > 0) {
            foreach ($file as $key => $value) {
                if (!is_array($value)) {
                    $value = [$value];
                }

                foreach ($value as $item) {
                    $http = $http->attach(
                        $key,
                        file_get_contents($item->getRealPath()),
                        $item->getClientOriginalName()
                    );
                }
            }
        }

        return $http;
    }

    public static function get(string $url, $query = [], $default = null)
    {
        /** @var Response $response */
        $response = self::base()->get($url, $query);
        return static::responseHandler($response, $default);
    }

    public static function post(string $url, $data = [], $file = [])
    {
        /** @var Response $response */
        $http = self::fileHandler($file);
        $response = $http->post($url, $data);

        return static::responseHandler($response);
    }

    public static function patch(string $url, $data = [], $file = [])
    {
        /** @var Response $response */
        $http = self::fileHandler($file);
        $response = $http->patch($url, $data);

        return static::responseHandler($response);
    }

    public static function delete(string $url)
    {
        /** @var Response $response */
        $response = self::base()->delete($url);

        return static::responseHandler($response);
    }
}
