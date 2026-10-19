
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$companyId = current_company_id();
$message = '';
$allowedTypes = ['product', 'service', 'delivery'];

function market_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function market_redirect(): void {
    header('Location: marketplace.php');
    exit;
}

/* =========================================================
   HANDLE FORM ACTIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(403);
        exit('Security token invalid. Return to the marketplace and try again.');
    }

    $action = (string)($_POST['action'] ?? '');

    try {

        /* Publish a new marketplace offer */
        if ($action === 'publish') {
            $type = (string)($_POST['listing_type'] ?? '');
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $province = trim((string)($_POST['province'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $rawPrice = trim((string)($_POST['price'] ?? ''));

            if (!in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException('Choose a valid listing type.');
            }

            if ($title === '' || strlen($title) > 180) {
                throw new InvalidArgumentException(
                    'Enter a listing title of up to 180 characters.'
                );
            }

            if (
                strlen($description) > 10000 ||
                strlen($province) > 80 ||
                strlen($city) > 100
            ) {
                throw new InvalidArgumentException('One or more fields are too long.');
            }

            $price = null;

            if ($rawPrice !== '') {
                if (
                    !is_numeric($rawPrice) ||
                    !is_finite((float)$rawPrice) ||
                    (float)$rawPrice < 0 ||
                    (float)$rawPrice > 9999999999.99
                ) {
                    throw new InvalidArgumentException(
                        'Enter a valid price in rand or leave it blank for quote-only offers.'
                    );
                }

                $price = round((float)$rawPrice, 2);
            }

            $stmt = $conn->prepare(
                'INSERT INTO marketplace_listings
                (company_id, listing_type, title, description,
                 province, city, price, currency, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, "ZAR", "published")'
            );

            $stmt->bind_param(
                'isssssd',
                $companyId,
                $type,
                $title,
                $description,
                $province,
                $city,
                $price
            );

            $stmt->execute();

            $_SESSION['market_flash'] = 'Your listing is now published.';
            market_redirect();
        }

        /* Send a quotation request to another business */
        if ($action === 'request_quote') {
            $listingId = filter_var(
                $_POST['listing_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $quoteMessage = trim((string)($_POST['message'] ?? ''));

            if (
                !$listingId ||
                $quoteMessage === '' ||
                strlen($quoteMessage) > 5000
            ) {
                throw new InvalidArgumentException(
                    'Choose a listing and enter a message of up to 5,000 characters.'
                );
            }

            $check = $conn->prepare(
                'SELECT id
                 FROM marketplace_listings
                 WHERE id = ?
                   AND status = "published"
                   AND company_id <> ?
                 LIMIT 1'
            );

            $check->bind_param('ii', $listingId, $companyId);
            $check->execute();

            if (!$check->get_result()->fetch_assoc()) {
                throw new InvalidArgumentException(
                    'That listing is unavailable or belongs to your own company.'
                );
            }

            $stmt = $conn->prepare(
                'INSERT INTO marketplace_quote_requests
                (listing_id, buyer_company_id, message)
                VALUES (?, ?, ?)'
            );

            $stmt->bind_param(
                'iis',
                $listingId,
                $companyId,
                $quoteMessage
            );

            $stmt->execute();

            $_SESSION['market_flash'] = 'Your quotation request has been sent.';
            market_redirect();
        }

        /* Seller sends a price proposal and reply */
        if ($action === 'quote_reply') {
            $quoteId = filter_var(
                $_POST['quote_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $rawPrice = trim((string)($_POST['proposed_price'] ?? ''));
            $reply = trim((string)($_POST['reply_message'] ?? ''));

            if (
                !$quoteId ||
                $rawPrice === '' ||
                !is_numeric($rawPrice) ||
                !is_finite((float)$rawPrice) ||
                (float)$rawPrice < 0 ||
                (float)$rawPrice > 9999999999.99
            ) {
                throw new InvalidArgumentException(
                    'Enter a valid proposed price in rand.'
                );
            }

            if (strlen($reply) > 3000) {
                throw new InvalidArgumentException(
                    'Your reply must be 3,000 characters or fewer.'
                );
            }

            $price = number_format((float)$rawPrice, 2, '.', ',');

            $response = "\n\n--- SELLER PRICE PROPOSAL ---\n"
                . "Proposed price: R " . $price . "\n"
                . "Seller reply: "
                . ($reply !== '' ? $reply : 'No additional message.');

            /*
             * Only the company that owns the listing can respond.
             * The request must still be pending.
             * The existing message field stores the conversation.
             */
            $stmt = $conn->prepare(
                'UPDATE marketplace_quote_requests qr
                 INNER JOIN marketplace_listings ml
                    ON ml.id = qr.listing_id
                 SET qr.message = CONCAT(qr.message, ?)
                 WHERE qr.id = ?
                   AND ml.company_id = ?
                   AND qr.status = "pending"'
            );

            $stmt->bind_param(
                'sii',
                $response,
                $quoteId,
                $companyId
            );

            $stmt->execute();

            $_SESSION['market_flash'] = $stmt->affected_rows > 0
                ? 'Your price proposal has been sent.'
                : 'Request not found or it has already been handled.';

            market_redirect();
        }

        /* Accept or decline a quotation request */
        if ($action === 'quote_status') {
            $quoteId = filter_var(
                $_POST['quote_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $newStatus = (string)($_POST['status'] ?? '');

            if (
                !$quoteId ||
                !in_array($newStatus, ['accepted', 'declined', 'closed'], true)
            ) {
                throw new InvalidArgumentException(
                    'Invalid quotation update.'
                );
            }

            $stmt = $conn->prepare(
                'UPDATE marketplace_quote_requests qr
                 INNER JOIN marketplace_listings ml
                    ON ml.id = qr.listing_id
                 SET qr.status = ?
                 WHERE qr.id = ?
                   AND ml.company_id = ?
                   AND qr.status = "pending"'
            );

            $stmt->bind_param(
                'sii',
                $newStatus,
                $quoteId,
                $companyId
            );

            $stmt->execute();

            $_SESSION['market_flash'] = $stmt->affected_rows > 0
                ? 'Quotation request status updated.'
                : 'Request not found or it has already been handled.';

            market_redirect();
        }

        throw new InvalidArgumentException('Unknown marketplace action.');

    } catch (mysqli_sql_exception $e) {
        error_log('Marketplace database error: ' . $e->getMessage());
        $message = 'The request could not be saved. Please try again.';
    } catch (InvalidArgumentException $e) {
        $message = $e->getMessage();
    }
}

/* Display one-time confirmation messages */
if (isset($_SESSION['market_flash'])) {
    $message = (string)$_SESSION['market_flash'];
    unset($_SESSION['market_flash']);
}

/* =========================================================
   SEARCH AND FILTERS
   ========================================================= */

$search = trim((string)($_GET['q'] ?? ''));
$typeFilter = (string)($_GET['type'] ?? '');
$provinceFilter = trim((string)($_GET['province'] ?? ''));

if (!in_array($typeFilter, ['', 'product', 'service', 'delivery'], true)) {
    $typeFilter = '';
}

$searchLike = '%' . $search . '%';
$provinceLike = '%' . $provinceFilter . '%';

$stmt = $conn->prepare(
    'SELECT
        ml.id,
        ml.company_id,
        ml.listing_type,
        ml.title,
        ml.description,
        ml.province,
        ml.city,
        ml.price,
        ml.currency,
        ml.created_at,
        c.business_name
     FROM marketplace_listings ml
     INNER JOIN companies c ON c.id = ml.company_id
     WHERE ml.status = "published"
       AND c.status = "active"
       AND (
            ? = ""
            OR ml.title LIKE ?
            OR ml.description LIKE ?
            OR c.business_name LIKE ?
       )
       AND (? = "" OR ml.listing_type = ?)
       AND (? = "" OR ml.province LIKE ?)
     ORDER BY ml.created_at DESC'
);

$stmt->bind_param(
    'ssssssss',
    $search,
    $searchLike,
    $searchLike,
    $searchLike,
    $typeFilter,
    $typeFilter,
    $provinceFilter,
    $provinceLike
);

$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =========================================================
   QUOTATION REQUESTS RECEIVED
   ========================================================= */

$stmt = $conn->prepare(
    'SELECT
        qr.id,
        qr.message,
        qr.status,
        qr.created_at,
        ml.title,
        bc.business_name AS buyer_name
     FROM marketplace_quote_requests qr
     INNER JOIN marketplace_listings ml ON ml.id = qr.listing_id
     INNER JOIN companies bc ON bc.id = qr.buyer_company_id
     WHERE ml.company_id = ?
     ORDER BY qr.created_at DESC
     LIMIT 100'
);

$stmt->bind_param('i', $companyId);
$stmt->execute();
$receivedQuotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =========================================================
   QUOTATION REQUESTS SENT
   ========================================================= */

$stmt = $conn->prepare(
    'SELECT
        qr.id,
        qr.message,
        qr.status,
        qr.created_at,
        ml.title,
        sc.business_name AS seller_name
     FROM marketplace_quote_requests qr
     INNER JOIN marketplace_listings ml ON ml.id = qr.listing_id
     INNER JOIN companies sc ON sc.id = ml.company_id
     WHERE qr.buyer_company_id = ?
     ORDER BY qr.created_at DESC
     LIMIT 100'
);

$stmt->bind_param('i', $companyId);
$stmt->execute();
$sentQuotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Marketplace | LogiCost SA 2.0</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .market-wrap {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 20px 44px;
        }

        .market-panel {
            background: #fff;
            border: 1px solid #e1e9e3;
            border-radius: 14px;
            padding: 22px;
            margin: 22px 0;
        }

        .market-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 18px;
        }

        .market-card {
            border: 1px solid #e0e8e1;
            border-radius: 12px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
        }

        .market-card h3 {
            margin: 0;
        }

        .market-muted {
            color: #637168;
            font-size: 14px;
        }

        .market-price {
            color: #176b45;
            font-weight: 700;
            font-size: 22px;
        }

        .market-tag {
            display: inline-block;
            background: #edf5ef;
            color: #176b45;
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 12px;
            text-transform: capitalize;
        }

        .market-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
        }

        .market-panel label {
            display: block;
            font-weight: 600;
            margin: 10px 0 6px;
        }

        .market-panel input,
        .market-panel select,
        .market-panel textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            border: 1px solid #ccd8cf;
            border-radius: 8px;
            font: inherit;
        }

        .market-panel textarea {
            min-height: 90px;
            resize: vertical;
        }

        .market-filter {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 18px;
        }

        .market-filter input,
        .market-filter select {
            min-width: 0;
            padding: 10px;
            border: 1px solid #ccd8cf;
            border-radius: 8px;
        }

        .market-notice {
            padding: 12px 16px;
            background: #edf7ef;
            border-radius: 8px;
            margin: 16px 0;
        }

        .market-table-wrap {
            overflow-x: auto;
        }

        .market-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .market-table th,
        .market-table td {
            padding: 12px;
            border-bottom: 1px solid #e5ebe6;
            text-align: left;
            vertical-align: top;
        }

        .market-table th {
            background: #f3f7f4;
        }

        .market-table textarea {
            min-width: 200px;
        }

        .market-status {
            text-transform: capitalize;
            font-weight: 600;
        }

        .market-conversation {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .market-action-form {
            margin-bottom: 12px;
            min-width: 220px;
        }

        .market-action-form button {
            margin-top: 8px;
        }

        @media (max-width: 700px) {
            .market-filter {
                grid-template-columns: 1fr;
            }

            .market-panel {
                padding: 16px;
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
        <a class="btn btn-outline-dark" href="dashboard.php">Dashboard</a>
        <a class="btn btn-outline-dark" href="inventory.php">Inventory</a>
        <a class="btn btn-outline-dark" href="live-map.php">Live Map</a>

        <span><?= market_h(current_user_name()) ?></span>

        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn btn-outline-dark" type="submit">Log out</button>
        </form>
    </div>
</header>

<main class="market-wrap">

    <h1>Marketplace &amp; Price Comparison</h1>

    <p class="market-muted">
        Discover products and services advertised by businesses on LogiCost SA.
        Prices and availability are supplied by the listing businesses.
    </p>

    <?php if ($message !== ''): ?>
        <div class="market-notice" role="status">
            <?= market_h($message) ?>
        </div>
    <?php endif; ?>

    <!-- PUBLISH AN OFFER -->
    <section class="market-panel">
        <h2>Publish an offer</h2>

        <p class="market-muted">
            Publish a real offer from your business. Leave the price blank
            if customers must request a quote.
        </p>

        <form method="post" action="marketplace.php">
            <?= csrf_field() ?>

            <input type="hidden" name="action" value="publish">

            <div class="market-form-grid">
                <div>
                    <label for="listing_type">Offer type *</label>
                    <select id="listing_type" name="listing_type" required>
                        <option value="product">Product / supplies</option>
                        <option value="service">Business service</option>
                        <option value="delivery">Delivery / transport</option>
                    </select>
                </div>

                <div>
                    <label for="title">Title *</label>
                    <input
                        id="title"
                        name="title"
                        maxlength="180"
                        required
                        placeholder="e.g. Same-day local delivery"
                    >
                </div>

                <div>
                    <label for="price">Price in rand (R)</label>
                    <input
                        id="price"
                        name="price"
                        type="number"
                        min="0"
                        max="9999999999.99"
                        step="0.01"
                        placeholder="Leave blank for quote-only"
                    >
                </div>

                <div>
                    <label for="province">Province</label>
                    <input
                        id="province"
                        name="province"
                        maxlength="80"
                        placeholder="e.g. Gauteng"
                    >
                </div>

                <div>
                    <label for="city">City / town</label>
                    <input
                        id="city"
                        name="city"
                        maxlength="100"
                        placeholder="e.g. Johannesburg"
                    >
                </div>
            </div>

            <p>
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    maxlength="10000"
                    placeholder="Describe the product or service, conditions, and what is included."
                ></textarea>
            </p>

            <button class="btn btn-primary" type="submit">
                Publish offer
            </button>
        </form>
    </section>

    <!-- BROWSE OFFERS -->
    <section class="market-panel">
        <h2>Browse offers</h2>

        <form method="get" action="marketplace.php" class="market-filter">
            <input
                name="q"
                value="<?= market_h($search) ?>"
                placeholder="Search products, services or businesses"
                aria-label="Search marketplace"
            >

            <select name="type" aria-label="Offer type">
                <option value="">All types</option>
                <option value="product" <?= $typeFilter === 'product' ? 'selected' : '' ?>>
                    Products
                </option>
                <option value="service" <?= $typeFilter === 'service' ? 'selected' : '' ?>>
                    Services
                </option>
                <option value="delivery" <?= $typeFilter === 'delivery' ? 'selected' : '' ?>>
                    Delivery
                </option>
            </select>

            <input
                name="province"
                value="<?= market_h($provinceFilter) ?>"
                placeholder="Province"
                aria-label="Filter by province"
            >

            <button class="btn btn-primary" type="submit">Search</button>
        </form>

        <?php if (!$listings): ?>
            <p>
                No published offers match your search yet.
                Try a different filter or publish the first offer.
            </p>
        <?php else: ?>
            <div class="market-grid">

                <?php foreach ($listings as $listing): ?>
                    <article class="market-card">

                        <div>
                            <span class="market-tag">
                                <?= market_h($listing['listing_type']) ?>
                            </span>
                        </div>

                        <h3><?= market_h($listing['title']) ?></h3>

                        <div class="market-muted">
                            <?= market_h($listing['business_name']) ?>

                            <?php if ($listing['city'] || $listing['province']): ?>
                                ·
                                <?= market_h(trim(
                                    ($listing['city'] ?? '') .
                                    (
                                        ($listing['city'] && $listing['province'])
                                        ? ', '
                                        : ''
                                    ) .
                                    ($listing['province'] ?? '')
                                )) ?>
                            <?php endif; ?>
                        </div>

                        <div class="market-price">
                            <?php if ($listing['price'] !== null): ?>
                                R <?= number_format((float)$listing['price'], 2) ?>
                            <?php else: ?>
                                Quote required
                            <?php endif; ?>
                        </div>

                        <p>
                            <?= nl2br(market_h(
                                $listing['description'] ?? 'No description provided.'
                            )) ?>
                        </p>

                        <div class="market-muted">
                            Published
                            <?= market_h(date(
                                'd M Y',
                                strtotime($listing['created_at'])
                            )) ?>
                        </div>

                        <?php if ((int)$listing['company_id'] === $companyId): ?>

                            <p class="market-muted">
                                <strong>Your company's listing</strong>
                            </p>

                        <?php else: ?>

                            <details>
                                <summary>Request a quotation</summary>

                                <form
                                    method="post"
                                    action="marketplace.php"
                                    style="margin-top:12px"
                                >
                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="request_quote"
                                    >

                                    <input
                                        type="hidden"
                                        name="listing_id"
                                        value="<?= (int)$listing['id'] ?>"
                                    >

                                    <label for="quote-<?= (int)$listing['id'] ?>">
                                        Message to the business *
                                    </label>

                                    <textarea
                                        id="quote-<?= (int)$listing['id'] ?>"
                                        name="message"
                                        maxlength="5000"
                                        required
                                        placeholder="Ask about quantity, delivery, availability or a better price."
                                    ></textarea>

                                    <button
                                        class="btn btn-primary"
                                        type="submit"
                                        style="margin-top:10px"
                                    >
                                        Send quotation request
                                    </button>
                                </form>
                            </details>

                        <?php endif; ?>

                    </article>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>
    </section>

    <!-- QUOTATIONS RECEIVED -->
    <section class="market-panel">
        <h2>Quotation requests received</h2>

        <p class="market-muted">
            Requests sent to listings owned by your company.
            You can propose a price, then accept or decline the request.
        </p>

        <?php if (!$receivedQuotes): ?>
            <p>No quotation requests received yet.</p>
        <?php else: ?>

            <div class="market-table-wrap">
                <table class="market-table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>From</th>
                            <th>Conversation</th>
                            <th>Status</th>
                            <th>Respond</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($receivedQuotes as $quote): ?>
                            <tr>
                                <td><?= market_h($quote['title']) ?></td>

                                <td><?= market_h($quote['buyer_name']) ?></td>

                                <td class="market-conversation"><?= market_h($quote['message']) ?></td>

                                <td class="market-status">
                                    <?= market_h($quote['status']) ?>
                                </td>

                                <td>
                                    <?php if ($quote['status'] === 'pending'): ?>

                                        <form
                                            method="post"
                                            action="marketplace.php"
                                            class="market-action-form"
                                        >
                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="quote_reply"
                                            >

                                            <input
                                                type="hidden"
                                                name="quote_id"
                                                value="<?= (int)$quote['id'] ?>"
                                            >

                                            <label for="price-<?= (int)$quote['id'] ?>">
                                                Proposed price (R)
                                            </label>

                                            <input
                                                id="price-<?= (int)$quote['id'] ?>"
                                                name="proposed_price"
                                                type="number"
                                                min="0"
                                                max="9999999999.99"
                                                step="0.01"
                                                required
                                                placeholder="e.g. 850.00"
                                            >

                                            <label for="reply-<?= (int)$quote['id'] ?>">
                                                Reply to buyer
                                            </label>

                                            <textarea
                                                id="reply-<?= (int)$quote['id'] ?>"
                                                name="reply_message"
                                                maxlength="3000"
                                                placeholder="Explain your price or terms."
                                            ></textarea>

                                            <button
                                                class="btn btn-primary"
                                                type="submit"
                                            >
                                                Send price proposal
                                            </button>
                                        </form>

                                        <form method="post" action="marketplace.php">
                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="quote_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="quote_id"
                                                value="<?= (int)$quote['id'] ?>"
                                            >

                                            <button
                                                class="btn btn-primary"
                                                name="status"
                                                value="accepted"
                                                type="submit"
                                            >
                                                Accept request
                                            </button>

                                            <button
                                                class="btn btn-outline-dark"
                                                name="status"
                                                value="declined"
                                                type="submit"
                                            >
                                                Decline request
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <span class="market-muted">Handled</span>

                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <!-- QUOTATIONS SENT -->
    <section class="market-panel">
        <h2>Quotation requests you've sent</h2>

        <p class="market-muted">
            Check the status of your requests and view seller price proposals.
        </p>

        <?php if (!$sentQuotes): ?>
            <p>You haven't requested a quotation yet.</p>
        <?php else: ?>

            <div class="market-table-wrap">
                <table class="market-table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Business</th>
                            <th>Conversation and proposals</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($sentQuotes as $quote): ?>
                            <tr>
                                <td><?= market_h($quote['title']) ?></td>

                                <td><?= market_h($quote['seller_name']) ?></td>

                                <td class="market-conversation"><?= market_h($quote['message']) ?></td>

                                <td class="market-status">
                                    <?= market_h($quote['status']) ?>
                                </td>

                                <td>
                                    <?= market_h(date(
                                        'd M Y',
                                        strtotime($quote['created_at'])
                                    )) ?>
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

