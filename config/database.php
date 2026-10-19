
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

try {
    $conn = new mysqli(
        'localhost',
        'root',
        '',
        'logiscost_sa2'
    );

    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('LogiCost database connection failed: ' . $e->getMessage());

    http_response_code(500);
    exit('LogiCost is temporarily unavailable. Please try again later.');
}
