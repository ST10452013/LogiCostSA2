<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['company_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!verify_csrf()) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $password === ''
    ) {
        $error = 'Enter a valid email address and password.';
    } else {
        $stmt = $conn->prepare(
            'SELECT u.id, u.company_id, u.full_name, u.password_hash,
                    u.role, u.status AS user_status,
                    c.status AS company_status
             FROM users u
             INNER JOIN companies c ON c.id = u.company_id
             WHERE u.email = ?
             LIMIT 1'
        );

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (
            $user &&
            password_verify($password, $user['password_hash']) &&
            $user['user_status'] === 'active' &&
            $user['company_status'] === 'active'
        ) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['company_id'] = (int) $user['company_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            unset($_SESSION['csrf_token']);

            $update = $conn->prepare(
                'UPDATE users SET last_login_at = NOW() WHERE id = ?'
            );
            $update->bind_param('i', $user['id']);
            $update->execute();

            header('Location: dashboard.php');
            exit;
        }

        $error = 'The email or password is incorrect, or the account is inactive.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In | LogiCost SA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <main class="login-layout">
        <section class="login-brand-panel">
            <a class="brand brand-light" href="index.php">
                <span class="brand-mark">L</span>
                <span>LogiCost <strong>SA</strong></span>
            </a>

            <div class="login-brand-content">
                <p class="eyebrow eyebrow-light">YOUR BUSINESS. BETTER CONNECTED.</p>
                <h1>Move smarter.<br>Spend wisely.<br>Grow together.</h1>
                <p>
                    One place to help manage business operations,
                    connect with service providers and make informed decisions.
                </p>

                <div class="login-benefits">
                    <div><span>✓</span> Inventory and cost management</div>
                    <div><span>✓</span> Business and delivery connections</div>
                    <div><span>✓</span> Tools designed for South Africa</div>
                </div>
            </div>

            <p class="panel-footer">Built for businesses of every size.</p>
        </section>

        <section class="login-form-panel">
            <div class="login-form-wrap">
                <a class="mobile-brand brand brand-dark" href="index.php">
                    <span class="brand-mark">L</span>
                    <span>LogiCost <strong>SA</strong></span>
                </a>

                <p class="eyebrow">WELCOME BACK</p>
                <h2>Log in to your account</h2>
                <p class="form-intro">Enter your details to continue to your workspace.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <?= csrf_field() ?>

                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email"
                        autocomplete="username" maxlength="254" required
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="password">Password</label>
                    <input id="password" name="password" type="password"
                        autocomplete="current-password" required>

                    <button class="btn btn-primary btn-full" type="submit">
                        Log in securely
                    </button>
                </form>

                <p class="form-bottom">
                    New to LogiCost SA?
                    <a href="register.php">Create a business account</a>
                </p>

                <p class="fine-print">Never share your password with anyone.</p>
            </div>
        </section>
    </main>
</body>
</html>
