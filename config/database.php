<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    session_start();
}

/*
 * On Azure App Service the connection details come from App Settings
 * (environment variables). Locally, XAMPP defaults are used.
 */
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'logiscost_sa2';
$dbSsl  = getenv('DB_SSL') === 'true';

try {
    $conn = mysqli_init();

    // Azure Database for MySQL requires an encrypted connection.
    if ($dbSsl) {
        $conn->ssl_set(null, null, '/etc/ssl/certs/ca-certificates.crt', null, null);
    }

    $conn->real_connect(
        $dbHost,
        $dbUser,
        $dbPass,
        $dbName,
        3306,
        null,
        $dbSsl ? MYSQLI_CLIENT_SSL : 0
    );

    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('LogiCost database connection failed: ' . $e->getMessage());

    http_response_code(500);
    exit('LogiCost is temporarily unavailable. Please try again later.');
}
