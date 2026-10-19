
<?php

function require_login(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['company_id'])) {
        header('Location: login.php');
        exit;
    }
}



function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_company_id(): int
{
    return (int) ($_SESSION['company_id'] ?? 0);
}

function current_user_name(): string
{
    return (string) ($_SESSION['user_name'] ?? 'Business user');
}

function current_user_role(): string
{
    return (string) ($_SESSION['user_role'] ?? '');
}

