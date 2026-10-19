
<?php

function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function verify_csrf(): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $postedToken = $_POST['csrf_token'] ?? null;

    return is_string($sessionToken)
        && is_string($postedToken)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $postedToken);
}
