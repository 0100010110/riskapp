<?php

/**
 * Support multi API via comma-separated env:
 * API_NAME=sso,foo
 * API_HOST=https://...,https://...
 * API_AUTH=none,bearer
 * API_SECRET=null,xxxx
 */

$namesRaw   = (string) env('API_NAME', 'sso');
$hostsRaw   = (string) env('API_HOST', '');
$authsRaw   = (string) env('API_AUTH', 'none');
$secretsRaw = (string) env('API_SECRET', ''); // <- casting string biar tidak null

$names   = array_values(array_filter(array_map('trim', explode(',', $namesRaw))));
$hosts   = array_values(array_map('trim', explode(',', $hostsRaw)));
$auths   = array_values(array_map('trim', explode(',', $authsRaw)));
$secrets = array_values(array_map('trim', explode(',', $secretsRaw)));

$apis = [];

foreach ($names as $i => $name) {
    $host = $hosts[$i] ?? $hosts[0] ?? '';
    $auth = strtolower($auths[$i] ?? $auths[0] ?? 'none');
    $secret = $secrets[$i] ?? $secrets[0] ?? null;

    if (is_string($secret)) {
        $secretTrim = trim($secret);
        if ($secretTrim === '' || strtolower($secretTrim) === 'null') {
            $secret = null;
        }
    }

    $host = rtrim((string) $host, '/');

    $apis[$name] = [
        'url'    => $host,
        'host'   => $host,

        'auth'   => $auth,    
        'secret' => $secret,   
    ];
}

return $apis;
