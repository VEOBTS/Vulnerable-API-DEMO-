<?php
// php-api/src/router.php

function route($method, $path) {
    $uri = rtrim(parse_url($path, PHP_URL_PATH), '/');

    // FORMAT: 'METHOD /path' => 'module_filename_without_.php'
    $routes = [
        'GET /api/bola'                 => 'bola',
        'GET /api/bola/secure'           => 'bola',
        'POST /api/auth/login'           => 'auth',
        'GET /api/auth/profile'          => 'auth',
        'GET /api/auth/profile/secure'   => 'auth',
        'GET /api/bopla/user'            => 'bopla',
        'PUT /api/bopla/user'            => 'bopla',
        'GET /api/bopla/user/secure'     => 'bopla',
        'GET /api/search'                => 'rate',
        'GET /api/search/secure'         => 'rate',
        'GET /api/admin/users'           => 'bfla',
        'GET /api/admin/users/secure'    => 'bfla',
        'POST /api/order'                => 'business',
        'POST /api/order/secure'         => 'business',
        'GET /api/ssrf'                  => 'ssrf',
        'GET /api/ssrf/secure'           => 'ssrf',
        'GET /api/debug'                 => 'misconfig',
        'GET /api/config'                => 'misconfig',
        'GET /api/v1/users'              => 'inventory',
        'GET /api/v2/users'              => 'inventory',
        'GET /api/weather'               => 'unsafe',
        'GET /api/weather/secure'        => 'unsafe',
    ];

    $key = $method . ' ' . $uri;
    if (isset($routes[$key])) {
        require_once __DIR__ . '/modules/' . $routes[$key] . '.php';
        handle_request($method, $uri);
        return;
    }

    json_response(['error' => 'Route not found', 'path' => $uri], 404);
}