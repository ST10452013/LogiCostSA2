<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$companyId = current_company_id();
$money = static fn($amount) =>
    'R ' . number_format((float)$amount, 2);

function lc_report_query(mysqli $conn, string $sql, int $companyId): array
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: [];
    $stmt->close();

    return $row;
}

function lc_report_rows(mysqli $conn, string $sql, int $companyId): array
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function lc_report_escape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* INVENTORY SUMMARY */
$inventory = lc_report_query($conn, "
    SELECT
        COUNT(*) AS sku_count,
        COALESCE(SUM(quantity), 0) AS total_units,
        COALESCE(SUM(
            CASE WHEN quantity <= low_stock_threshold
            THEN 1 ELSE 0 END
        ), 0) AS low_stock_count,
        COALESCE(SUM(quantity * cost_price), 0) AS stock_cost_value
    FROM inventory_items
    WHERE company_id = ?
", $companyId);

/* LOW-STOCK ITEMS */
$lowStockItems = lc_report_rows($conn, "
    SELECT sku, product_name, quantity, low_stock_threshold
    FROM inventory_items
    WHERE company_id = ?
      AND quantity <= low_stock_threshold
    ORDER BY quantity ASC, product_name ASC
    LIMIT 8
", $companyId);

/* DELIVERY SUMMARY */
$deliveries = lc_report_query($conn, "
    SELECT
        COUNT(*) AS total_jobs,
        COALESCE(SUM(status = 'pending'), 0) AS pending_jobs,
        COALESCE(SUM(status = 'assigned'), 0) AS assigned_jobs,
        COALESCE(SUM(status = 'in_transit'), 0) AS in_transit_jobs,
        COALESCE(SUM(status = 'delivered'), 0) AS delivered_jobs,
        COALESCE(SUM(status = 'cancelled'), 0) AS cancelled_jobs,
        COALESCE(SUM(
            CASE
                WHEN status <> 'cancelled'
                THEN COALESCE(agreed_cost, 0)
                ELSE 0
            END
        ), 0) AS recorded_costs,
        COALESCE(SUM(
            CASE
                WHEN status <> 'cancelled'
                 AND agreed_cost IS NOT NULL
                THEN 1 ELSE 0
            END
        ), 0) AS priced_jobs
    FROM delivery_jobs
    WHERE company_id = ?
", $companyId);

/* MARKETPLACE SUMMARY: THIS COMPANY'S LISTINGS */
$marketplace = lc_report_query($conn, "
    SELECT
        COUNT(*) AS total_listings,
        COALESCE(SUM(price IS NOT NULL), 0) AS priced_listings,
        COALESCE(AVG(price), 0) AS average_price
    FROM marketplace_listings
    WHERE company_id = ?
      AND status = 'published'
", $companyId);

/* RECENT PUBLISHED LISTINGS */
$recentListings = lc_report_rows($conn, "
    SELECT title, listing_type, city, province, price, currency
    FROM marketplace_listings
    WHERE company_id = ?
      AND status = 'published'
    ORDER BY created_at DESC
    LIMIT 5
", $companyId);

/* QUOTATIONS RECEIVED ON THIS COMPANY'S LISTINGS */
$receivedQuotes = lc_report_query($conn, "
    SELECT
        COUNT(*) AS total_quotes,
        COALESCE(SUM(q.status = 'pending'), 0) AS pending_quotes,
        COALESCE(SUM(q.status = 'accepted'), 0) AS accepted_quotes,
        COALESCE(SUM(q.status = 'declined'), 0) AS declined_quotes
    FROM marketplace_quote_requests q
    INNER JOIN marketplace_listings l
        ON l.id = q.listing_id
    WHERE l.company_id = ?
      AND q.buyer_company_id <> l.company_id
", $companyId);

/* QUOTATIONS SENT BY THIS COMPANY */
$sentQuotes = lc_report_query($conn, "
    SELECT
        COUNT(*) AS total_quotes,
        COALESCE(SUM(status = 'pending'), 0) AS pending_quotes,
        COALESCE(SUM(status = 'accepted'), 0) AS accepted_quotes,
        COALESCE(SUM(status = 'declined'), 0) AS declined_quotes
    FROM marketplace_quote_requests
    WHERE buyer_company_id = ?
", $companyId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Business Reports | LogiCost SA 2.0</title>
    <style>
        :root {
            --green: #176b45;
            --green-dark: #104b32;
            --green-light: #e8f5ed;
            --ink: #1c3027;
            --muted: #68766e;
            --border: #e1e9e3;
            --surface: #ffffff;
            --page: #f5f8f5;
            --warning: #fff2d5;
            --warning-ink: #805500;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--page);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.5;
        }

        /* Shared navigation */
        .lc-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 15px 5%;
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .lc-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--green-dark);
            font-size: 19px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .lc-brand small {
            display: block;
            font-size: 11px;
            font-weight: 600;
        }

        .lc-brand-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            color: white;
            background: var(--green);
        }

        .lc-navigation {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .lc-navigation a {
            color: var(--ink);
            text-decoration: none;
            font-size: 14px;
        }

        .lc-navigation a.active {
            color: var(--green);
            font-weight: 700;
        }

        .lc-menu-toggle { display: none; }

        .lc-account {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lc-user-name {
            font-size: 13px;
            color: var(--muted);
        }

        .lc-account form { margin: 0; }

        .lc-logout-button {
            border: 1px solid var(--border);
            background: white;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
        }

        .lc-register-button {
            background: var(--green);
            color: white !important;
            padding: 9px 13px;
            border-radius: 8px;
        }

        .lc-nav-coming-soon {
            color: var(--muted);
            font-size: 14px;
        }

        .lc-nav-coming-soon small { font-size: 10px; }

        .page {
            width: min(1200px, 92%);
            margin: 32px auto 60px;
        }

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0 0 7px;
            font-size: clamp(25px, 4vw, 34px);
            letter-spacing: -0.7px;
        }

        h2 {
            margin: 0 0 16px;
            font-size: 19px;
        }

        .muted { color: var(--muted); }
        .page-heading p { margin: 0; }

        .button {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 9px;
            background: var(--green);
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .button:hover { background: var(--green-dark); }

        .metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric, .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(20, 60, 38, 0.035);
        }

        .metric { padding: 20px; }

        .metric-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 10px;
        }

        .metric-value {
            color: var(--green-dark);
            font-size: clamp(23px, 3vw, 30px);
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .metric-note {
            margin-top: 9px;
            color: var(--muted);
            font-size: 12px;
        }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .panel { padding: 22px; min-width: 0; }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .status-item {
            background: #f7faf7;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 13px;
        }

        .status-item span {
            display: block;
            color: var(--muted);
            font-size: 12px;
        }

        .status-item strong {
            display: block;
            margin-top: 4px;
            font-size: 22px;
        }

        .stock-warning {
            background: var(--warning);
            color: var(--warning-ink);
            padding: 12px 14px;
            border-radius: 9px;
            margin-bottom: 14px;
            font-size: 13px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            text-align: left;
            padding: 12px 9px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        tr:last-child td { border-bottom: 0; }

        .pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            background: var(--green-light);
            color: var(--green-dark);
            font-size: 11px;
            font-weight: 700;
        }

        .empty {
            padding: 15px 0;
            color: var(--muted);
            font-size: 14px;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .quick-links a {
            display: inline-block;
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: white;
            color: var(--green-dark);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .footnote {
            margin-top: 15px;
            font-size: 12px;
            color: var(--muted);
        }

        @media (max-width: 900px) {
            .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .lc-navbar { flex-wrap: wrap; }
            .lc-navigation { gap: 11px; }
        }

        @media (max-width: 640px) {
            .section-grid { grid-template-columns: 1fr; }
            .lc-menu-toggle {
                display: flex;
                flex-direction: column;
                gap: 4px;
                padding: 8px;
                border: 1px solid var(--border);
                background: white;
                border-radius: 8px;
            }
            .lc-menu-toggle span {
                width: 20px;
                height: 2px;
                background: var(--green-dark);
            }
            .lc-navigation {
                display: none;
                width: 100%;
                align-items: flex-start;
                flex-direction: column;
                padding-top: 10px;
            }
            .lc-navigation.is-open { display: flex; }
            .lc-account { flex-wrap: wrap; }
            .page { margin-top: 24px; }
            .metric, .panel { padding: 16px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="page">
    <div class="page-heading">
        <div>
            <h1>Business reports</h1>
            <p class="muted">
                Your inventory, delivery, marketplace and quotation overview.
            </p>
        </div>
        <a class="button" href="dashboard.php">Back to dashboard</a>
    </div>

    <!-- TOP METRICS -->
    <section class="metrics" aria-label="Business overview">
        <article class="metric">
            <div class="metric-label">Inventory products</div>
            <div class="metric-value">
                <?= number_format((int)$inventory['sku_count']) ?>
            </div>
            <div class="metric-note">
                <?= number_format((int)$inventory['total_units']) ?> units in stock
            </div>
        </article>

        <article class="metric">
            <div class="metric-label">Low-stock products</div>
            <div class="metric-value">
                <?= number_format((int)$inventory['low_stock_count']) ?>
            </div>
            <div class="metric-note">At or below their stock thresholds</div>
        </article>

        <article class="metric">
            <div class="metric-label">Delivery jobs</div>
            <div class="metric-value">
                <?= number_format((int)$deliveries['total_jobs']) ?>
            </div>
            <div class="metric-note">
                <?= number_format((int)$deliveries['delivered_jobs']) ?> delivered
            </div>
        </article>

        <article class="metric">
            <div class="metric-label">Published listings</div>
            <div class="metric-value">
                <?= number_format((int)$marketplace['total_listings']) ?>
            </div>
            <div class="metric-note">Your live marketplace offers</div>
        </article>
    </section>

    <div class="section-grid">
        <!-- INVENTORY -->
        <section class="panel">
            <h2>Inventory & stock health</h2>

            <div class="status-grid">
                <div class="status-item">
                    <span>Total units in stock</span>
                    <strong><?= number_format((int)$inventory['total_units']) ?></strong>
                </div>
                <div class="status-item">
                    <span>Estimated stock cost</span>
                    <strong><?= $money($inventory['stock_cost_value']) ?></strong>
                </div>
            </div>

            <h3>Items needing attention</h3>

            <?php if ($lowStockItems): ?>
                <div class="stock-warning">
                    <?= number_format((int)$inventory['low_stock_count']) ?>
                    product(s) are at or below their low-stock threshold.
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Threshold</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr>
                                <td><?= lc_report_escape($item['product_name']) ?></td>
                                <td><?= lc_report_escape($item['sku']) ?></td>
                                <td><strong><?= number_format((int)$item['quantity']) ?></strong></td>
                                <td><?= number_format((int)$item['low_stock_threshold']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty">No low-stock products to report.</p>
            <?php endif; ?>

            <p class="footnote">
                Stock cost is calculated from quantity × recorded cost price.
            </p>
        </section>

        <!-- DELIVERIES -->
        <section class="panel">
            <h2>Delivery performance</h2>

            <div class="status-grid">
                <div class="status-item">
                    <span>Pending</span>
                    <strong><?= number_format((int)$deliveries['pending_jobs']) ?></strong>
                </div>
                <div class="status-item">
                    <span>Assigned</span>
                    <strong><?= number_format((int)$deliveries['assigned_jobs']) ?></strong>
                </div>
                <div class="status-item">
                    <span>In transit</span>
                    <strong><?= number_format((int)$deliveries['in_transit_jobs']) ?></strong>
                </div>
                <div class="status-item">
                    <span>Delivered</span>
                    <strong><?= number_format((int)$deliveries['delivered_jobs']) ?></strong>
                </div>
            </div>

            <h3>Recorded delivery costs</h3>
            <div class="metric-value">
                <?= $money($deliveries['recorded_costs']) ?>
            </div>
            <p class="muted">
                <?= number_format((int)$deliveries['priced_jobs']) ?>
                non-cancelled job(s) have an agreed cost recorded.
            </p>
            <p class="footnote">
                Cancelled jobs are excluded. Jobs without an agreed cost are
                not treated as priced jobs.
            </p>
        </section>

        <!-- MARKETPLACE -->
        <section class="panel">
            <h2>Marketplace activity</h2>

            <div class="status-grid">
                <div class="status-item">
                    <span>Published offers</span>
                    <strong><?= number_format((int)$marketplace['total_listings']) ?></strong>
                </div>
                <div class="status-item">
                    <span>Offers with prices</span>
                    <strong><?= number_format((int)$marketplace['priced_listings']) ?></strong>
                </div>
            </div>

            <h3>Average advertised price</h3>
            <div class="metric-value">
                <?= $money($marketplace['average_price']) ?>
            </div>
            <p class="footnote">
                Average of priced published listings in your company account.
                Different products and services may not be directly comparable.
            </p>

            <h3>Your recent published offers</h3>

            <?php if ($recentListings): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Offer</th>
                            <th>Type</th>
                            <th>Price</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentListings as $listing): ?>
                            <tr>
                                <td>
                                    <strong><?= lc_report_escape($listing['title']) ?></strong>
                                    <br>
                                    <span class="muted">
                                        <?= lc_report_escape(
                                            trim(($listing['city'] ?? '') .
                                            (($listing['city'] ?? '') && ($listing['province'] ?? '') ? ', ' : '') .
                                            ($listing['province'] ?? ''))
                                        ) ?>
                                    </span>
                                </td>
                                <td><?= lc_report_escape(ucfirst($listing['listing_type'])) ?></td>
                                <td>
                                    <?php if ($listing['price'] !== null): ?>
                                        <?= lc_report_escape($listing['currency']) ?>
                                        <?= number_format((float)$listing['price'], 2) ?>
                                    <?php else: ?>
                                        <span class="pill">Quote only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty">You have no published listings yet.</p>
            <?php endif; ?>
        </section>

        <!-- QUOTATIONS -->
        <section class="panel">
            <h2>Quotation activity</h2>

            <h3>Requests received</h3>
            <div class="status-grid">
                <div class="status-item">
                    <span>Total received</span>
                    <strong><?= number_format((int)$receivedQuotes['total_quotes']) ?></strong>
                </div>
                <div class="status-item">
                    <span>Awaiting response</span>
                    <strong><?= number_format((int)$receivedQuotes['pending_quotes']) ?></strong>
                </div>
            </div>

            <h3>Requests sent</h3>
            <div class="status-grid">
                <div class="status-item">
                    <span>Total sent</span>
                    <strong><?= number_format((int)$sentQuotes['total_quotes']) ?></strong>
                </div>
                <div class="status-item">
                    <span>Awaiting response</span>
                    <strong><?= number_format((int)$sentQuotes['pending_quotes']) ?></strong>
                </div>
            </div>

            <p class="footnote">
                These are request counts, not confirmed orders or completed sales.
                Price negotiation will be added in the next stage.
            </p>
        </section>
    </div>

    <section class="panel">
        <h2>Quick access</h2>
        <div class="quick-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="inventory.php">Inventory & stock</a>
            <a href="marketplace.php">Marketplace</a>
            <a href="deliveries.php">Delivery management</a>
            <a href="live-map.php">Live map</a>
        </div>
    </section>
</main>

</body>
</html>
