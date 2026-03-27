<?php

function handle_request($method, $uri) {

    // ═══ VULNERABLE: fetches any user-supplied URL ════════════════
    if ($uri === '/api/ssrf') {
        $url = $_GET['url'] ?? null;
        if (!$url) json_response(['error' => 'url parameter required'], 400);
        // FLAW: No validation — caller controls the fetch destination.
        // PHP is inside Docker and can reach node-internal.
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        json_response([
            'fetched_url' => $url,
            'status'      => $status,
            'body'        => json_decode($resp, true) ?? $resp
        ]);
    }

    // ═══ SECURE: whitelist of allowed hosts + block private IPs ══
    if ($uri === '/api/ssrf/secure') {
        $url = $_GET['url'] ?? null;
        if (!$url) json_response(['error' => 'url required'], 400);

        // FIX 1: Only allow specific external domains
        $allowed = ['api.openweathermap.org', 'api.github.com'];
        $parsed  = parse_url($url);
        $host    = $parsed['host'] ?? '';
        if (!in_array($host, $allowed, true)) {
            json_response(['error' => 'Host not in whitelist: '.$host], 400);
        }

        // FIX 2: Block private/internal IP ranges
        $ip      = gethostbyname($host);
        $private = ['10.','172.','192.168.','127.','0.','169.254.'];
        foreach ($private as $prefix) {
            if (strpos($ip, $prefix) === 0) {
                json_response(['error' => 'Internal addresses blocked'], 400);
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $resp = curl_exec($ch);
        curl_close($ch);
        json_response(['body' => json_decode($resp, true) ?? $resp]);
    }
}