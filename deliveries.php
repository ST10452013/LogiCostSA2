<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$companyId = current_company_id();
$userId = current_user_id();
$message = '';
$allowedStatuses = [
    'pending', 'assigned', 'in_transit', 'delivered', 'cancelled'
];

function delivery_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function delivery_redirect(): void {
    header('Location: deliveries.php');
    exit;
}

function delivery_maps_url(string $address): string {
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(403);
        exit('Security token invalid. Return to Deliveries and try again.');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $reference = strtoupper(trim((string)($_POST['reference_code'] ?? '')));
            $pickup = trim((string)($_POST['pickup_address'] ?? ''));
            $dropoff = trim((string)($_POST['dropoff_address'] ?? ''));
            $package = trim((string)($_POST['package_description'] ?? ''));
            $recipient = trim((string)($_POST['recipient_name'] ?? ''));
            $recipientPhone = trim((string)($_POST['recipient_phone'] ?? ''));
            $scheduled = trim((string)($_POST['scheduled_date'] ?? ''));
            $rawCost = trim((string)($_POST['agreed_cost'] ?? ''));

            if ($reference === '' || strlen($reference) > 30 ||
                !preg_match('/^[A-Z0-9_-]+$/', $reference)) {
                throw new InvalidArgumentException(
                    'Enter a reference using letters, numbers, hyphens or underscores (max 30 characters).'
                );
            }

            if ($pickup === '' || strlen($pickup) > 500 ||
                $dropoff === '' || strlen($dropoff) > 500 ||
                $package === '' || strlen($package) > 500) {
                throw new InvalidArgumentException(
                    'Pickup, drop-off and package details are required (max 500 characters each).'
                );
            }

            if (strlen($recipient) > 160 || strlen($recipientPhone) > 30) {
                throw new InvalidArgumentException('Recipient details are too long.');
            }

            if ($scheduled !== '') {
                $date = DateTime::createFromFormat('!Y-m-d', $scheduled);
                if (!$date || $date->format('Y-m-d') !== $scheduled) {
                    throw new InvalidArgumentException('Enter a valid scheduled date.');
                }
            } else {
                $scheduled = null;
            }

            $cost = null;
            if ($rawCost !== '') {
                if (!is_numeric($rawCost) || (float)$rawCost < 0 ||
                    !is_finite((float)$rawCost) || (float)$rawCost > 9999999999.99) {
                    throw new InvalidArgumentException('Enter a valid non-negative delivery cost.');
                }
                $cost = round((float)$rawCost, 2);
            }

            $conn->begin_transaction();

            $stmt = $conn->prepare(
                'INSERT INTO delivery_jobs
                 (company_id, created_by, reference_code, pickup_address,
                  dropoff_address, package_description, recipient_name,
                  recipient_phone, scheduled_date, agreed_cost)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iisssssssd',
                $companyId, $userId, $reference, $pickup, $dropoff,
                $package, $recipient, $recipientPhone, $scheduled, $cost
            );
            $stmt->execute();

            $deliveryId = (int)$conn->insert_id;

            $history = $conn->prepare(
                'INSERT INTO delivery_status_history
                 (delivery_id, company_id, changed_by, old_status, new_status, note)
                 VALUES (?, ?, ?, NULL, "pending", ?)'
            );
            $note = 'Delivery created';
            $history->bind_param(
                'iiis', $deliveryId, $companyId, $userId, $note
            );
            $history->execute();

            $conn->commit();

            $_SESSION['delivery_flash'] = 'Delivery request created successfully.';
            delivery_redirect();
        }

        if ($action === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $newStatus = (string)($_POST['status'] ?? '');
            $driverName = trim((string)($_POST['driver_name'] ?? ''));
            $driverPhone = trim((string)($_POST['driver_phone'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));

            if (!$id || !in_array($newStatus, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Invalid delivery update.');
            }

            if (strlen($driverName) > 160 || strlen($driverPhone) > 30 ||
                strlen($note) > 500) {
                throw new InvalidArgumentException('Driver details or note are too long.');
            }

            $conn->begin_transaction();

            $stmt = $conn->prepare(
                'SELECT status FROM delivery_jobs
                 WHERE id = ? AND company_id = ? FOR UPDATE'
            );
            $stmt->bind_param('ii', $id, $companyId);
            $stmt->execute();
            $job = $stmt->get_result()->fetch_assoc();

            if (!$job) {
                throw new InvalidArgumentException('Delivery not found in your company.');
            }

            $oldStatus = $job['status'];
            $transitions = [
                'pending' => ['assigned', 'cancelled'],
                'assigned' => ['in_transit', 'cancelled'],
                'in_transit' => ['delivered', 'cancelled'],
                'delivered' => [],
                'cancelled' => []
            ];

            $statusChanged = $newStatus !== $oldStatus;

            if ($statusChanged &&
                !in_array($newStatus, $transitions[$oldStatus], true)) {
                throw new InvalidArgumentException(
                    'That status change is not allowed. Follow the delivery status sequence.'
                );
            }

            if ($newStatus === 'assigned' &&
                ($driverName === '' || $driverPhone === '')) {
                throw new InvalidArgumentException(
                    'Enter the driver name and phone number before assigning the delivery.'
                );
            }

            $stmt = $conn->prepare(
                'UPDATE delivery_jobs
                 SET status = ?, driver_name = ?, driver_phone = ?
                 WHERE id = ? AND company_id = ?'
            );
            $stmt->bind_param(
                'sssii', $newStatus, $driverName, $driverPhone, $id, $companyId
            );
            $stmt->execute();

            if ($statusChanged) {
                $history = $conn->prepare(
                    'INSERT INTO delivery_status_history
                     (delivery_id, company_id, changed_by, old_status, new_status, note)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $history->bind_param(
                    'iiisss',
                    $id, $companyId, $userId, $oldStatus, $newStatus, $note
                );
                $history->execute();
            }

            $conn->commit();

            $_SESSION['delivery_flash'] = 'Delivery details saved.';
            delivery_redirect();
        }

        throw new InvalidArgumentException('Unknown delivery action.');
    } catch (InvalidArgumentException $e) {
        if ($conn->errno === 0) {
            // A transaction may be open; rollback is safe when none is active too.
        }
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $message = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        if ((int)$e->getCode() === 1062) {
            $message = 'That reference code is already used by your company.';
        } else {
            error_log('Delivery database error: ' . $e->getMessage());
            $message = 'The delivery change could not be saved. Please try again.';
        }
    }
}

if (isset($_SESSION['delivery_flash'])) {
    $message = (string)$_SESSION['delivery_flash'];
    unset($_SESSION['delivery_flash']);
}

$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, array_merge([''], $allowedStatuses), true)) {
    $statusFilter = '';
}

$search = trim((string)($_GET['q'] ?? ''));
$searchLike = '%' . $search . '%';

$stmt = $conn->prepare(
    'SELECT id, reference_code, pickup_address, dropoff_address,
            package_description, recipient_name, recipient_phone,
            scheduled_date, agreed_cost, driver_name, driver_phone,
            status, created_at, updated_at
     FROM delivery_jobs
     WHERE company_id = ?
       AND (? = "" OR status = ?)
       AND (? = "" OR reference_code LIKE ? OR pickup_address LIKE ?
            OR dropoff_address LIKE ? OR package_description LIKE ?)
     ORDER BY created_at DESC'
);
$stmt->bind_param(
    'isssssss',
    $companyId, $statusFilter, $statusFilter, $search,
    $searchLike, $searchLike, $searchLike, $searchLike
);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$historyByJob = [];
if ($jobs) {
    $historyStmt = $conn->prepare(
        'SELECT delivery_id, old_status, new_status, note, changed_at
         FROM delivery_status_history
         WHERE company_id = ?
         ORDER BY changed_at DESC'
    );
    $historyStmt->bind_param('i', $companyId);
    $historyStmt->execute();
    $historyRows = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($historyRows as $row) {
        $historyByJob[(int)$row['delivery_id']][] = $row;
    }
}

$statusLabels = [
    'pending' => 'Pending',
    'assigned' => 'Assigned',
    'in_transit' => 'In Transit',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deliveries | LogiCost SA 2.0</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .delivery-wrap{max-width:1200px;margin:32px auto;padding:0 20px 44px}
        .delivery-panel{background:#fff;border:1px solid #e1e9e3;border-radius:14px;padding:22px;margin:22px 0}
        .delivery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
        .delivery-panel label{display:block;font-weight:600;margin-bottom:6px}
        .delivery-panel input,.delivery-panel select,.delivery-panel textarea{box-sizing:border-box;width:100%;padding:10px;border:1px solid #ccd8cf;border-radius:8px}
        .delivery-panel textarea{min-height:75px;resize:vertical}
        .delivery-muted{color:#637168;font-size:14px}
        .delivery-notice{padding:12px 16px;background:#edf7ef;border-radius:8px;margin:16px 0}
        .delivery-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px}
        .delivery-card{border:1px solid #e0e8e1;border-radius:12px;padding:18px;min-width:0}
        .delivery-card h3{margin-top:0}
        .delivery-status{display:inline-block;padding:5px 10px;border-radius:20px;background:#edf5ef;color:#176b45;font-size:13px;font-weight:700}
        .delivery-address{overflow-wrap:anywhere}
        .delivery-actions{display:grid;gap:10px;margin-top:16px}
        .delivery-history{padding-left:20px}
        .delivery-history li{margin:8px 0}
        .delivery-filter{display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-bottom:18px}
        @media(max-width:650px){.delivery-filter{grid-template-columns:1fr}}
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
        <a class="btn btn-outline-dark" href="inventory.php">Inventory</a>
        <a class="btn btn-outline-dark" href="marketplace.php">Marketplace</a>
        <a class="btn btn-outline-dark" href="live-map.php">Live Map</a>
        <span><?= delivery_h(current_user_name()) ?></span>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn btn-outline-dark" type="submit">Log out</button>
        </form>
    </div>
</header>

<main class="delivery-wrap">
    <h1>Deliveries &amp; Transport</h1>
    <p class="delivery-muted">
        Create delivery jobs and record progress. Statuses are entered by your business;
        they are not independently verified GPS tracking.
    </p>

    <?php if ($message !== ''): ?>
        <div class="delivery-notice" role="status"><?= delivery_h($message) ?></div>
    <?php endif; ?>

    <section class="delivery-panel">
        <h2>Create a delivery</h2>
        <form method="post" action="deliveries.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="delivery-grid">
                <div>
                    <label for="reference_code">Reference code *</label>
                    <input id="reference_code" name="reference_code" maxlength="30"
                           placeholder="e.g. JOB-1001" required>
                </div>
                <div>
                    <label for="scheduled_date">Scheduled date</label>
                    <input id="scheduled_date" name="scheduled_date" type="date">
                </div>
                <div>
                    <label for="agreed_cost">Agreed cost (R)</label>
                    <input id="agreed_cost" name="agreed_cost" type="number"
                           min="0" max="9999999999.99" step="0.01" placeholder="Optional">
                </div>
                <div>
                    <label for="recipient_name">Recipient name</label>
                    <input id="recipient_name" name="recipient_name" maxlength="160">
                </div>
                <div>
                    <label for="recipient_phone">Recipient phone</label>
                    <input id="recipient_phone" name="recipient_phone" maxlength="30">
                </div>
            </div>

            <p>
                <label for="pickup_address">Pickup address *</label>
                <input id="pickup_address" name="pickup_address" maxlength="500" required
                       placeholder="Street, area, city, province">
            </p>
            <p>
                <label for="dropoff_address">Drop-off address *</label>
                <input id="dropoff_address" name="dropoff_address" maxlength="500" required
                       placeholder="Street, area, city, province">
            </p>
            <p>
                <label for="package_description">Package / goods description *</label>
                <textarea id="package_description" name="package_description" maxlength="500"
                          required placeholder="Items, number of packages, handling instructions"></textarea>
            </p>
            <button class="btn btn-primary" type="submit">Create delivery</button>
        </form>
    </section>

    <section class="delivery-panel">
        <h2>Your delivery jobs</h2>
        <form class="delivery-filter" method="get" action="deliveries.php">
            <input name="q" value="<?= delivery_h($search) ?>"
                   placeholder="Search reference, address or package"
                   aria-label="Search deliveries">
            <select name="status" aria-label="Filter by status">
                <option value="">All statuses</option>
                <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= delivery_h($key) ?>"
                        <?= $statusFilter === $key ? 'selected' : '' ?>>
                        <?= delivery_h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
        </form>

        <?php if (!$jobs): ?>
            <p>No delivery jobs found. Create your first delivery above.</p>
        <?php else: ?>
            <div class="delivery-list">
                <?php foreach ($jobs as $job): ?>
                    <article class="delivery-card">
                        <h3><?= delivery_h($job['reference_code']) ?></h3>
                        <span class="delivery-status">
                            <?= delivery_h($statusLabels[$job['status']] ?? $job['status']) ?>
                        </span>

                        <p><strong>Package:</strong> <?= delivery_h($job['package_description']) ?></p>
                        <p class="delivery-address">
                            <strong>Pickup:</strong><br>
                            <?= delivery_h($job['pickup_address']) ?><br>
                            <a target="_blank" rel="noopener noreferrer"
                               href="<?= delivery_h(delivery_maps_url($job['pickup_address'])) ?>">
                                Open pickup in maps
                            </a>
                        </p>
                        <p class="delivery-address">
                            <strong>Drop-off:</strong><br>
                            <?= delivery_h($job['dropoff_address']) ?><br>
                            <a target="_blank" rel="noopener noreferrer"
                               href="<?= delivery_h(delivery_maps_url($job['dropoff_address'])) ?>">
                                Open drop-off in maps
                            </a>
                        </p>

                        <?php if ($job['scheduled_date']): ?>
                            <p><strong>Scheduled:</strong> <?= delivery_h($job['scheduled_date']) ?></p>
                        <?php endif; ?>

                        <?php if ($job['agreed_cost'] !== null): ?>
                            <p><strong>Agreed cost:</strong> R <?= number_format((float)$job['agreed_cost'], 2) ?></p>
                        <?php endif; ?>

                        <?php if ($job['recipient_name']): ?>
                            <p><strong>Recipient:</strong> <?= delivery_h($job['recipient_name']) ?>
                                <?= $job['recipient_phone'] ? ' · ' . delivery_h($job['recipient_phone']) : '' ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($job['driver_name']): ?>
                            <p><strong>Driver:</strong> <?= delivery_h($job['driver_name']) ?>
                                <?= $job['driver_phone'] ? ' · ' . delivery_h($job['driver_phone']) : '' ?>
                            </p>
                        <?php endif; ?>

                        <?php
                        $nextStatuses = [
                            'pending' => ['assigned', 'cancelled'],
                            'assigned' => ['in_transit', 'cancelled'],
                            'in_transit' => ['delivered', 'cancelled'],
                            'delivered' => [],
                            'cancelled' => []
                        ];
                        ?>
                        <?php if (!empty($nextStatuses[$job['status']])): ?>
                            <details class="delivery-actions">
                                <summary>Update delivery</summary>
                                <form method="post" action="deliveries.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">

                                    <p>
                                        <label for="status-<?= (int)$job['id'] ?>">New status</label>
                                        <select id="status-<?= (int)$job['id'] ?>" name="status" required>
                                            <?php foreach ($nextStatuses[$job['status']] as $next): ?>
                                                <option value="<?= delivery_h($next) ?>">
                                                    <?= delivery_h($statusLabels[$next]) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p>
                                        <label for="driver-<?= (int)$job['id'] ?>">Driver name</label>
                                        <input id="driver-<?= (int)$job['id'] ?>" name="driver_name"
                                               maxlength="160" value="<?= delivery_h($job['driver_name']) ?>">
                                    </p>
                                    <p>
                                        <label for="phone-<?= (int)$job['id'] ?>">Driver phone</label>
                                        <input id="phone-<?= (int)$job['id'] ?>" name="driver_phone"
                                               maxlength="30" value="<?= delivery_h($job['driver_phone']) ?>">
                                    </p>
                                    <p>
                                        <label for="note-<?= (int)$job['id'] ?>">Status note (optional)</label>
                                        <textarea id="note-<?= (int)$job['id'] ?>" name="note"
                                                  maxlength="500" placeholder="Reason or delivery update"></textarea>
                                    </p>
                                    <button class="btn btn-primary" type="submit">Save update</button>
                                </form>
                            </details>
                        <?php endif; ?>

                        <details class="delivery-actions">
                            <summary>Status history</summary>
                            <ul class="delivery-history">
                                <?php foreach (($historyByJob[(int)$job['id']] ?? []) as $entry): ?>
                                    <li>
                                        <strong>
                                            <?= delivery_h($entry['old_status'] ?? 'Created') ?>
                                            &rarr;
                                            <?= delivery_h($entry['new_status']) ?>
                                        </strong><br>
                                        <span class="delivery-muted">
                                            <?= delivery_h($entry['changed_at']) ?>
                                        </span>
                                        <?php if ($entry['note']): ?>
                                            <br><?= delivery_h($entry['note']) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
