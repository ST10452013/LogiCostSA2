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

    <style>
        .dashboard-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }

        .dashboard-nav form {
            margin: 0;
        }

        .tool-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
        }

        .tool-card h3 {
            margin-top: 0;
        }

        .tool-card p {
            line-height: 1.7;
            flex-grow: 1;
        }

        .tool-card .coming-soon {
            margin-top: 12px;
        }

        .tool-link {
            color: inherit;
            text-decoration: none;
            display: block;
            height: 100%;
        }

        .tool-link:focus-visible {
            outline: 3px solid #16845b;
            outline-offset: 4px;
            border-radius: 6px;
        }

        @media (max-width: 900px) {
            .app-header {
                flex-wrap: wrap;
                gap: 16px;
            }

            .dashboard-nav {
                justify-content: flex-start;
            }
        }
    </style>
</head>

<body class="dashboard-page">

<header class="app-header">
    <a class="brand brand-dark" href="dashboard.php">
        <span class="brand-mark">L</span>
        <span>LogiCost <strong>SA</strong></span>
    </a>

    <div class="header-user">
        <nav class="dashboard-nav" aria-label="Main navigation">
            <a class="btn btn-primary" href="offers-map.php">
                Offers Map
            </a>

            <a class="btn btn-outline-dark" href="deliveries.php">
                Deliveries
            </a>

            <a class="btn btn-outline-dark" href="inventory.php">
                Inventory
            </a>

            <a class="btn btn-outline-dark" href="marketplace.php">
                Marketplace
            </a>

            <a class="btn btn-outline-dark" href="business-reports.php">
                Business Reports
            </a>
        </nav>

        <span>
            <?= htmlspecialchars(current_user_name(), ENT_QUOTES, 'UTF-8') ?>
        </span>

        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn btn-outline-dark" type="submit">
                Log out
            </button>
        </form>
    </div>
</header>

<main class="dashboard-container">

    <section class="welcome-panel">
        <p class="eyebrow eyebrow-light">YOUR BUSINESS WORKSPACE</p>

        <h1>
            Welcome,
            <?= htmlspecialchars(current_user_name(), ENT_QUOTES, 'UTF-8') ?>
        </h1>

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
            <p>
                Access your business tools and explore the features available
                in LogiCost SA.
            </p>
        </div>
    </section>

    <section class="tool-grid">

        <article class="tool-card">
            <h3>Stock &amp; Inventory</h3>

            <p>
                Keep product records organised, monitor stock quantities,
                and identify items that may need replenishment.
            </p>

            <span class="coming-soon">Inventory tools</span>

            <a class="tool-link" href="inventory.php">
                Manage inventory
            </a>
        </article>

        <article class="tool-card">
            <h3>Deliveries &amp; Drivers</h3>

            <p>
                Organise delivery jobs, review transport requirements,
                and keep track of delivery progress.
            </p>

            <span class="coming-soon">Delivery management</span>

            <a class="tool-link" href="deliveries.php">
                View deliveries
            </a>
        </article>

        <article class="tool-card">
            <h3>Business Marketplace</h3>

            <p>
                Explore published products, services, and delivery offers
                from businesses, and request quotations for your needs.
            </p>

            <span class="coming-soon">Business connections</span>

            <a class="tool-link" href="marketplace.php">
                Explore marketplace
            </a>
        </article>

        <article class="tool-card">
            <h3>Costs &amp; Reports</h3>

            <p>
                Access the business reporting section to support expense
                tracking and better understanding of operating costs as
                these features are developed.
            </p>

            <span class="coming-soon">Cost management</span>

            <a class="tool-link" href="business-reports.php">
                View business reports
            </a>
        </article>

        <article class="tool-card">
            <h3>Offers Map</h3>

            <p>
                Explore marketplace offers on a map and use available
                search filters to find relevant business opportunities
                by location.
            </p>

            <span class="coming-soon">Map and offer discovery</span>

            <a class="tool-link" href="offers-map.php">
                Explore offers map
            </a>
        </article>

    </section>

</main>

</body>
</html>
