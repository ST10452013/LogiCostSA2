
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';

$error = '';
$businessName = '';
$businessType = 'small_business';
$fullName = '';
$email = '';
$phone = '';
$province = '';
$city = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = trim($_POST['business_name'] ?? '');
    $businessType = $_POST['business_type'] ?? 'small_business';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $allowedTypes = [
        'small_business',
        'manufacturer',
        'supplier',
        'driver',
        'transport_company',
        'warehouse',
        'retailer',
        'other'
    ];

    if (!verify_csrf()) {
        $error = 'Your form session expired. Refresh the page and try again.';
    } elseif (
        $businessName === '' ||
        $fullName === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Enter a business name, your full name and a valid email address.';
    } elseif (!in_array($businessType, $allowedTypes, true)) {
        $error = 'Please select a valid business type.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must contain at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Your passwords do not match.';
    } else {
        try {
            $conn->begin_transaction();

            $companyStmt = $conn->prepare(
                'INSERT INTO companies
                (business_name, business_type, email, phone, province, city)
                VALUES (?, ?, ?, ?, ?, ?)'
            );

            $companyStmt->bind_param(
                'ssssss',
                $businessName,
                $businessType,
                $email,
                $phone,
                $province,
                $city
            );

            $companyStmt->execute();
            $companyId = (int) $conn->insert_id;

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $userStmt = $conn->prepare(
                'INSERT INTO users
                (company_id, full_name, email, password_hash, role)
                VALUES (?, ?, ?, ?, "owner")'
            );

            $userStmt->bind_param(
                'isss',
                $companyId,
                $fullName,
                $email,
                $passwordHash
            );

            $userStmt->execute();
            $userId = (int) $conn->insert_id;

            $conn->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['company_id'] = $companyId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_role'] = 'owner';

            unset($_SESSION['csrf_token']);

            header('Location: dashboard.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log('Registration failed: ' . $e->getMessage());

            if ((string) $e->getCode() === '1062') {
                $error = 'This email address is already registered. Please log in or use another email address.';
            } else {
                $error = 'We could not complete registration. Please check your details and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create a Business Account | LogiCost SA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <main class="form-page">
        <a class="brand brand-dark" href="index.php">
            <span class="brand-mark">L</span>
            <span>LogiCost <strong>SA</strong></span>
        </a>

        <section class="form-card">
            <p class="eyebrow">JOIN LOGICOST SA</p>
            <h1>Create your business account</h1>
            <p class="form-intro">
                Start building a smarter, more connected business.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <?= csrf_field() ?>

                <label for="business_name">Business or trading name</label>
                <input id="business_name" name="business_name"
                    maxlength="180" required
                    value="<?= htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8') ?>">

                <label for="business_type">What best describes you?</label>
                <select id="business_type" name="business_type" required>
                    <?php
                    $types = [
                        'small_business' => 'Small business',
                        'manufacturer' => 'Manufacturer',
                        'supplier' => 'Supplier',
                        'driver' => 'Independent driver',
                        'transport_company' => 'Transport company',
                        'warehouse' => 'Warehouse',
                        'retailer' => 'Retailer',
                        'other' => 'Other'
                    ];
                    foreach ($types as $value => $label):
                    ?>
                        <option value="<?= $value ?>"
                            <?= $businessType === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="full_name">Your full name</label>
                <input id="full_name" name="full_name"
                    autocomplete="name" maxlength="160" required
                    value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>">

                <label for="email">Business email address</label>
                <input id="email" name="email" type="email"
                    autocomplete="email" maxlength="254" required
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">

                <label for="phone">Phone number <span class="optional">(optional)</span></label>
                <input id="phone" name="phone" type="tel"
                    autocomplete="tel" maxlength="30"
                    value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>">

                <div class="field-row">
                    <div>
                        <label for="province">Province</label>
                        <select id="province" name="province">
                            <option value="">Select province</option>
                            <?php foreach ([
                                'Eastern Cape', 'Free State', 'Gauteng',
                                'KwaZulu-Natal', 'Limpopo', 'Mpumalanga',
                                'Northern Cape', 'North West', 'Western Cape'
                            ] as $provinceOption): ?>
                                <option value="<?= htmlspecialchars($provinceOption, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $province === $provinceOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($provinceOption, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="city">City or town</label>
                        <input id="city" name="city" maxlength="100"
                            value="<?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <label for="password">Password</label>
                <input id="password" name="password" type="password"
                    autocomplete="new-password" minlength="8" required>

                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" name="confirm_password"
                    type="password" autocomplete="new-password"
                    minlength="8" required>

                <button class="btn btn-primary btn-full" type="submit">
                    Create business account
                </button>
            </form>

            <p class="form-bottom">
                Already registered?
                <a href="login.php">Log in</a>
            </p>
        </section>

        <p class="fine-print">Your business data is designed to remain private to your company.</p>
    </main>
</body>
</html>
