<?php

namespace App\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class Api
{
    protected static $api = null;

    abstract protected static function responseHandler(Response $response, $default = null);

    /**
     * @return PendingRequest
     */
    public static function base(): PendingRequest
    {
        $apiName = static::$api;

        if (! $apiName) {
            throw new RuntimeException('Nama API (static::$api) belum di-set.');
        }

        $cfg = config("api.$apiName");

        if (! is_array($cfg)) {
            throw new RuntimeException("Config api.$apiName tidak ditemukan. Cek config/api.php");
        }

        $baseUrl = $cfg['host'] ?? $cfg['url'] ?? null;

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new RuntimeException("API host untuk api.$apiName kosong. Cek API_HOST di .env / config/api.php");
        }

        $http = Http::asJson()
            ->acceptJson()
            ->baseUrl(rtrim($baseUrl, '/'))
            ->timeout(30);

        $auth = strtolower((string) ($cfg['auth'] ?? 'none'));
        $secret = (string) ($cfg['secret'] ?? '');

        if (strtolower(trim($secret)) === 'null') {
            $secret = '';
        }

        if ($auth === 'bearer') {
            $token = trim($secret);
            $token = preg_replace('/^bearer\s+/i', '', $token) ?? $token;

            if ($token === '' && Auth::check() && ! empty(Auth::user()->token)) {
                $token = (string) Auth::user()->token;
            }

            if ($token !== '') {
                $http = $http->withToken($token);
            }
        }

        if ($auth === 'basic') {
            $parts = explode('|', $secret, 2);
            $username = $parts[0] ?? '';
            $password = $parts[1] ?? '';

            if ($username !== '' || $password !== '') {
                $http = $http->withBasicAuth($username, $password);
            }
        }

        return $http;
    }

    private static function fileHandler($file = []): PendingRequest
    {
        $http = self::base();

        if (count($file) > 0) {
            foreach ($file as $key => $value) {
                if (! is_array($value)) {
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
        $response = self::base()->get($url, $query);
        return static::responseHandler($response, $default);
    }

    public static function post(string $url, $data = [], $file = [])
    {
        $http = self::fileHandler($file);
        $response = $http->post($url, $data);

        return static::responseHandler($response);
    }

    public static function patch(string $url, $data = [], $file = [])
    {
        $http = self::fileHandler($file);
        $response = $http->patch($url, $data);

        return static::responseHandler($response);
    }

    public static function delete(string $url)
    {
        $response = self::base()->delete($url);
        return static::responseHandler($response);
    }
}
