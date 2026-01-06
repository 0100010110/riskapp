<?php
$apis = [];
$api_name = explode(",", env('API_NAME'));
$api_host = explode(",", env('API_HOST'));
$api_auth = explode(",", env('API_AUTH'));
$api_secret = explode(",", env('API_SECRET'));

foreach ($api_name as $i => $api) {
    $apis[$api] = [
        'url' => $api_host[$i],
        'auth' => $api_auth[$i],
        'secret' => $api_secret[$i] == 'null' ? null : $api_secret[$i],
    ];
}
return $apis;
