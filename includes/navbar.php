<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';

$isLoggedIn = !empty($_SESSION['user_id'])
    && !empty($_SESSION['company_id']);

$userName = htmlspecialchars(
    (string)($_SESSION['user_name'] ?? 'Business user'),
    ENT_QUOTES,
    'UTF-8'
);

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>

<header class="lc-navbar">
    <a class="lc-brand" href="<?= $isLoggedIn ? 'dashboard.php' : 'index.php' ?>">
        <span class="lc-brand-icon">L</span>
        <span>LogiCost <small>SA 2.0</small></span>
    </a>

    <button class="lc-menu-toggle"
            type="button"
            aria-label="Toggle navigation"
            aria-expanded="false"
            aria-controls="lc-navigation">
        <span></span><span></span><span></span>
    </button>

    <nav class="lc-navigation" id="lc-navigation">
        <?php if ($isLoggedIn): ?>
            <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
               href="dashboard.php">Dashboard</a>

            <a class="<?= $currentPage === 'inventory.php' ? 'active' : '' ?>"
               href="inventory.php">Inventory</a>

            <a class="<?= $currentPage === 'deliveries.php' ? 'active' : '' ?>"
               href="deliveries.php">Deliveries</a>

            <a class="<?= $currentPage === 'marketplace.php' ? 'active' : '' ?>"
               href="marketplace.php">Marketplace</a>

            <a class="<?= $currentPage === 'live-map.php' ? 'active' : '' ?>"
               href="live-map.php">Live map</a>

            <a class="<?= $currentPage === 'business-reports.php' ? 'active' : '' ?>"
               href="business-reports.php">Reports</a>

            <div class="lc-account">
                <span class="lc-user-name"><?= $userName ?></span>
                <form action="logout.php" method="POST">
                    <?= csrf_field() ?>
                    <button class="lc-logout-button" type="submit">
                        Log out
                    </button>
                </form>
            </div>
        <?php else: ?>
            <a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
               href="index.php">Home</a>

            <a href="index.php#about">About us</a>

            <a class="<?= $currentPage === 'login.php' ? 'active' : '' ?>"
               href="login.php">Log in</a>

            <a class="lc-register-button" href="register.php">
                Create account
            </a>
        <?php endif; ?>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.lc-menu-toggle');
    const navigation = document.querySelector('.lc-navigation');

    if (!toggle || !navigation) return;

    toggle.addEventListener('click', function () {
        const isOpen = navigation.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });
});
</script>
