<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$companyId = current_company_id();

if (!isset($_SESSION['inventory_flash'])) {
    $_SESSION['inventory_flash'] = null;
}

$message = $_SESSION['inventory_flash'];
unset($_SESSION['inventory_flash']);

function inventory_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function inventory_redirect(): void {
    header('Location: inventory.php');
    exit;
}

/* Process inventory actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(403);
        exit('Security token invalid. Please return to Inventory and try again.');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'add') {
            $sku = trim((string)($_POST['sku'] ?? ''));
            $name = trim((string)($_POST['product_name'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $threshold = filter_var(
                $_POST['low_stock_threshold'] ?? null,
                FILTER_VALIDATE_INT
            );
            $cost = filter_var($_POST['cost_price'] ?? null, FILTER_VALIDATE_FLOAT);
            $selling = filter_var(
                $_POST['selling_price'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            if ($sku === '' || strlen($sku) > 80 ||
                $name === '' || strlen($name) > 180) {
                throw new InvalidArgumentException(
                    'Enter a product name and a product code of up to 80 characters.'
                );
            }

            if (strlen($category) > 100) {
                throw new InvalidArgumentException('Category must be 100 characters or fewer.');
            }

            if ($quantity === false || $quantity < 0 ||
                $threshold === false || $threshold < 0 ||
                $cost === false || $cost < 0 ||
                $selling === false || $selling < 0) {
                throw new InvalidArgumentException(
                    'Enter valid non-negative quantities and prices.'
                );
            }

            $stmt = $conn->prepare(
                'INSERT INTO inventory_items
                 (company_id, sku, product_name, category, quantity,
                  low_stock_threshold, cost_price, selling_price)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'isssiidd',
                $companyId, $sku, $name, $category,
                $quantity, $threshold, $cost, $selling
            );
            $stmt->execute();

            $_SESSION['inventory_flash'] = 'Product added successfully.';
            inventory_redirect();
        }

        if ($action === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $threshold = filter_var(
                $_POST['low_stock_threshold'] ?? null,
                FILTER_VALIDATE_INT
            );
            $cost = filter_var($_POST['cost_price'] ?? null, FILTER_VALIDATE_FLOAT);
            $selling = filter_var(
                $_POST['selling_price'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            if (!$id || $quantity === false || $quantity < 0 ||
                $threshold === false || $threshold < 0 ||
                $cost === false || $cost < 0 ||
                $selling === false || $selling < 0) {
                throw new InvalidArgumentException(
                    'Enter valid quantities and non-negative prices.'
                );
            }

            $stmt = $conn->prepare(
                'UPDATE inventory_items
                 SET quantity = ?, low_stock_threshold = ?,
                     cost_price = ?, selling_price = ?
                 WHERE id = ? AND company_id = ?'
            );
            $stmt->bind_param(
                'iiddii',
                $quantity, $threshold, $cost, $selling, $id, $companyId
            );
            $stmt->execute();

            $_SESSION['inventory_flash'] = $stmt->affected_rows >= 0
                ? 'Inventory details saved.'
                : 'No changes were made.';
            inventory_redirect();
        }

        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id) {
                throw new InvalidArgumentException('Invalid product.');
            }

            $stmt = $conn->prepare(
                'DELETE FROM inventory_items
                 WHERE id = ? AND company_id = ?'
            );
            $stmt->bind_param('ii', $id, $companyId);
            $stmt->execute();

            $_SESSION['inventory_flash'] = $stmt->affected_rows
                ? 'Product deleted.'
                : 'Product not found.';
            inventory_redirect();
        }

        throw new InvalidArgumentException('Unknown inventory action.');
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            $message = 'That product code already exists in your inventory.';
        } else {
            error_log('Inventory database error: ' . $e->getMessage());
            $message = 'The inventory change could not be saved. Please try again.';
        }
    } catch (InvalidArgumentException $e) {
        $message = $e->getMessage();
    }
}

/* Search only this company's products */
$search = trim((string)($_GET['q'] ?? ''));
$searchLike = '%' . $search . '%';

$stmt = $conn->prepare(
    'SELECT id, sku, product_name, category, quantity,
            low_stock_threshold, cost_price, selling_price
     FROM inventory_items
     WHERE company_id = ?
       AND (? = "" OR product_name LIKE ? OR sku LIKE ? OR category LIKE ?)
     ORDER BY product_name ASC'
);
$stmt->bind_param(
    'issss',
    $companyId, $search, $searchLike, $searchLike, $searchLike
);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalProducts = count($items);
$totalUnits = 0;
$lowStockCount = 0;

foreach ($items as $item) {
    $totalUnits += (int)$item['quantity'];
    if ((int)$item['quantity'] <= (int)$item['low_stock_threshold']) {
        $lowStockCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory | LogiCost SA 2.0</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .inventory-wrap { max-width: 1200px; margin: 32px auto; padding: 0 20px 40px; }
        .inventory-top { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
        .inventory-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:16px; margin:24px 0; }
        .inventory-stat, .inventory-panel { background:#fff; border:1px solid #e1e9e3; border-radius:14px; padding:20px; }
        .inventory-stat strong { display:block; font-size:26px; color:#176b45; margin-top:8px; }
        .inventory-panel { margin:20px 0; }
        .inventory-form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; }
        .inventory-panel label { display:block; font-weight:600; margin-bottom:6px; }
        .inventory-panel input { width:100%; box-sizing:border-box; padding:10px; border:1px solid #ccd8cf; border-radius:8px; }
        .inventory-panel button { cursor:pointer; }
        .inventory-table-wrap { overflow-x:auto; }
        .inventory-table { width:100%; border-collapse:collapse; min-width:850px; }
        .inventory-table th, .inventory-table td { padding:12px 10px; text-align:left; border-bottom:1px solid #e5ebe6; vertical-align:top; }
        .inventory-table th { background:#f3f7f4; }
        .inventory-table input { min-width:75px; max-width:110px; }
        .inventory-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .inventory-notice { padding:12px 16px; background:#edf7ef; border-radius:8px; margin:16px 0; }
        .inventory-low { color:#a33a25; font-weight:700; }
        .inventory-muted { color:#617067; font-size:14px; }
        .inventory-search { display:flex; gap:10px; margin-bottom:16px; }
        .inventory-search input { flex:1; min-width:0; }
    </style>
</head>
<body class="dashboard-page">

<header class="app-header">
    <a class="brand brand-dark" href="dashboard.php">
        <span class="brand-mark">L</span>
        <span>LogiCost <strong>SA</strong></span>
    </a>
    <div class="header-user">
        <a class="btn btn-outline-dark" href="dashboard.php">Dashboard</a>
        <a class="btn btn-outline-dark" href="live-map.php">Live Map</a>
        <span><?= inventory_h(current_user_name()) ?></span>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn btn-outline-dark" type="submit">Log out</button>
        </form>
    </div>
</header>

<main class="inventory-wrap">
    <div class="inventory-top">
        <div>
            <h1>Inventory &amp; Stock</h1>
            <p class="inventory-muted">Manage your products, quantities and prices.</p>
        </div>
        <a class="btn btn-outline-dark" href="dashboard.php">Back to dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="inventory-notice" role="status">
            <?= inventory_h($message) ?>
        </div>
    <?php endif; ?>

    <section class="inventory-stats">
        <div class="inventory-stat">
            <span>Products shown</span>
            <strong><?= $totalProducts ?></strong>
        </div>
        <div class="inventory-stat">
            <span>Total units in stock</span>
            <strong><?= number_format($totalUnits) ?></strong>
        </div>
        <div class="inventory-stat">
            <span>Low-stock products</span>
            <strong><?= $lowStockCount ?></strong>
        </div>
    </section>

    <section class="inventory-panel">
        <h2>Add a product</h2>
        <p class="inventory-muted">Product codes must be unique within your business.</p>

        <form method="post" action="inventory.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="inventory-form-grid">
                <div>
                    <label for="sku">Product code / SKU *</label>
                    <input id="sku" name="sku" maxlength="80" required>
                </div>
                <div>
                    <label for="product_name">Product name *</label>
                    <input id="product_name" name="product_name" maxlength="180" required>
                </div>
                <div>
                    <label for="category">Category</label>
                    <input id="category" name="category" maxlength="100">
                </div>
                <div>
                    <label for="quantity">Quantity *</label>
                    <input id="quantity" name="quantity" type="number" min="0" step="1" value="0" required>
                </div>
                <div>
                    <label for="low_stock_threshold">Low-stock alert at</label>
                    <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" step="1" value="5" required>
                </div>
                <div>
                    <label for="cost_price">Cost price (R) *</label>
                    <input id="cost_price" name="cost_price" type="number" min="0" step="0.01" value="0.00" required>
                </div>
                <div>
                    <label for="selling_price">Selling price (R) *</label>
                    <input id="selling_price" name="selling_price" type="number" min="0" step="0.01" value="0.00" required>
                </div>
            </div>

            <p style="margin-top:18px">
                <button class="btn btn-primary" type="submit">Add product</button>
            </p>
        </form>
    </section>

    <section class="inventory-panel">
        <h2>Your products</h2>

        <form class="inventory-search" method="get" action="inventory.php">
            <input name="q" aria-label="Search inventory"
                   placeholder="Search product name, code or category..."
                   value="<?= inventory_h($search) ?>">
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a class="btn btn-outline-dark" href="inventory.php">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (!$items): ?>
            <p>No products found. Add your first product above.</p>
        <?php else: ?>
            <div class="inventory-table-wrap">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Alert at</th>
                            <th>Cost (R)</th>
                            <th>Selling (R)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= inventory_h($item['product_name']) ?></strong><br>
                                <span class="inventory-muted"><?= inventory_h($item['sku']) ?></span>
                            </td>
                            <td><?= inventory_h($item['category'] ?: '—') ?></td>
                            <td class="<?= (int)$item['quantity'] <= (int)$item['low_stock_threshold'] ? 'inventory-low' : '' ?>">
                                <?= number_format((int)$item['quantity']) ?>
                                <?php if ((int)$item['quantity'] <= (int)$item['low_stock_threshold']): ?>
                                    <br><span class="inventory-muted">Low stock</span>
                                <?php endif; ?>
                            </td>
                            <td colspan="4">
                                <form method="post" action="inventory.php" class="inventory-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                                    <label>
                                        <span class="inventory-muted">Qty</span>
                                        <input aria-label="Quantity" name="quantity" type="number" min="0" step="1"
                                               value="<?= (int)$item['quantity'] ?>" required>
                                    </label>
                                    <label>
                                        <span class="inventory-muted">Alert</span>
                                        <input aria-label="Low stock threshold" name="low_stock_threshold" type="number" min="0" step="1"
                                               value="<?= (int)$item['low_stock_threshold'] ?>" required>
                                    </label>
                                    <label>
                                        <span class="inventory-muted">Cost R</span>
                                        <input aria-label="Cost price" name="cost_price" type="number" min="0" step="0.01"
                                               value="<?= inventory_h($item['cost_price']) ?>" required>
                                    </label>
                                    <label>
                                        <span class="inventory-muted">Sell R</span>
                                        <input aria-label="Selling price" name="selling_price" type="number" min="0" step="0.01"
                                               value="<?= inventory_h($item['selling_price']) ?>" required>
                                    </label>
                                    <button class="btn btn-primary" type="submit">Save</button>
                                </form>

                                <form method="post" action="inventory.php"
                                      onsubmit="return confirm('Delete this product from your inventory?');"
                                      style="margin-top:8px">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button class="btn btn-outline-dark" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
