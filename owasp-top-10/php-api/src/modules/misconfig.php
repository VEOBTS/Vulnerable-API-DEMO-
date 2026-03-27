<?php
function handle_request($method, $uri) {

    // ═══ VULNERABLE: debug endpoint exposes everything ═══════════
    if ($uri === '/api/debug') {
        // FLAW: Endpoint should not exist in any deployed environment
        // Exposes: PHP version, DB host, DB user, DB password,
        //          Node URL, all environment variables, loaded files
        json_response([
            'php_version'     => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'n/a',
            'db_host'         => DB_HOST,
            'db_user'         => DB_USER,
            'db_pass'         => DB_PASS,
            'db_name'         => DB_NAME,
            'node_url'        => NODE_URL,
            'environment'     => $_ENV,
            'loaded_files'    => get_included_files(),
            'server_vars'     => $_SERVER,
        ]);
    }

    // ═══ VULNERABLE: verbose error with credentials in message ════
    if ($uri === '/api/config') {
        try {
            $db = new mysqli('wronghost', DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) {
                // FLAW: Full connection details in the exception message
                throw new Exception(
                    'Failed connecting to ' . DB_HOST .
                    ' as ' . DB_USER .
                    ' with pass ' . DB_PASS .
                    ': ' . $db->connect_error
                );
            }
        } catch (Exception $e) {
            // FLAW: Stack trace and file paths sent to client
            json_response([
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
