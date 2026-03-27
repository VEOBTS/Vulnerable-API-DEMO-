<?php
// php-api/src/config.php

// Read from Docker environment variables (set in docker-compose.yml)
define('DB_HOST',   getenv('DB_HOST')   ?: 'mysql');
define('DB_USER',   getenv('DB_USER')   ?: 'labuser');
define('DB_PASS',   getenv('DB_PASS')   ?: 'labpass');
define('DB_NAME',   getenv('DB_NAME')   ?: 'labdb');
define('NODE_URL',  getenv('NODE_URL')  ?: 'http://node-internal:3000');

// Weak JWT secret — intentionally short/guessable for API2 demo
define('JWT_SECRET', 'secret123');

// Admin key stored in plain text — bad practice shown for API8 demo
define('ADMIN_KEY', 'supersecretadminkey');