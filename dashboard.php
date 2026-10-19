
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$companyStmt = $conn->prepare(
    'SELECT business_name, business_type, city, province
     FROM companies
     WHERE id = ? AND status = "active"
     LIMIT 1'
);

$companyId = current_company_id();
$companyStmt->bind_param('i', $companyId);
$companyStmt->execute();
$company = $companyStmt->get_result()->fetch_assoc();

if (!$company) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$businessName = $company['business_name'];
$location = trim(
    ($company['city'] ?? '') .
    (!empty($company['city']) && !empty($company['province']) ? ', ' : '') .
    ($company['province'] ?? '')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | LogiCost SA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
    <header class="app-header">
        <a class="brand brand-dark" href="dashboard.php">
            <span class="brand-mark">L</span>
            <span>LogiCost <strong>SA</strong></span>
        </a>

        
        <div class="header-user">
            <a href="live-map.php" class="btn btn-outline-dark">
                <a href="offers-map.php" class="btn btn-primary">Offers Map</a>
                <a class="btn btn-outline-dark" href="deliveries.php">Deliveries</a>
                <a class="btn btn-outline-dark" href="inventory.php">Inventory</a>
                <a class="btn btn-outline-dark" href="marketplace.php">Marketplace</a>
                
                
<a class="btn btn-outline-dark" href="business-reports.php">
    Business Reports
</a>

            </a>

            <span><?= htmlspecialchars(current_user_name(), ENT_QUOTES, 'UTF-8') ?></span>

            <form method="post" action="logout.php">
                <?= csrf_field() ?>
                <button class="btn btn-outline-dark" type="submit">Log out</button>
            </form>
        </div>
    </header>


    <main class="dashboard-container">
        <section class="welcome-panel">
            <p class="eyebrow eyebrow-light">YOUR BUSINESS WORKSPACE</p>
            <h1>Welcome, <?= htmlspecialchars(current_user_name(), ENT_QUOTES, 'UTF-8') ?></h1>
            <p>
                <?= htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($location !== ''): ?>
                    · <?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </p>
        </section>

        <section class="section-heading">
            <div>
                <h2>Your business tools</h2>
                <p>We'll activate each module as its features are built and tested.</p>
            </div>
        </section>

        <section class="tool-grid">
            <article class="tool-card">
                <span class="tool-icon">📦</span>
                <h3>Stock & inventory</h3>
                <p>Manage products, stock levels and stock movements.</p>
                <span class="coming-soon">Coming in the next stage</span>
            </article>

            <article class="tool-card">
                <span class="tool-icon">🚚</span>
                <h3>Deliveries & drivers</h3>
                <p>Plan deliveries and organise transport requirements.</p>
                <span class="coming-soon">Coming in the next stage</span>
            </article>

            <article class="tool-card">
                <span class="tool-icon">🤝</span>
                <h3>Business marketplace</h3>
                <p>Discover suppliers, compare offers and request quotations.</p>
                <span class="coming-soon">Coming in the next stage</span>
            </article>

            <article class="tool-card">
                <span class="tool-icon">📊</span>
                <h3>Costs & reports</h3>
                <p>Record business expenses and understand operating costs.</p>
                <span class="coming-soon">Coming in the next stage</span>
            </article>

            <article class="tool-card">
                <span class="tool-icon">🗺️</span>
                <h3>Live map</h3>
                <p>Explore the map foundation before enabling real tracking.</p>
                <span class="coming-soon">Coming in the next stage</span>
            </article>
        </section>
    </main>
</body>
</html>
