<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function marketApiJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function marketApiInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        marketApiJson(400, [
            'success' => false,
            'message' => 'Invalid JSON request body.'
        ]);
    }

    return $data;
}

function marketApiText(
    array $input,
    string $key,
    int $maxLength,
    bool $required = false
): string {
    $value = $input[$key] ?? '';

    if (!is_string($value) && !is_numeric($value)) {
        marketApiJson(422, [
            'success' => false,
            'message' => "Invalid value for {$key}."
        ]);
    }

    $value = trim((string)$value);

    if ($required && $value === '') {
        marketApiJson(422, [
            'success' => false,
            'message' => ucfirst(str_replace('_', ' ', $key))
                . ' is required.'
        ]);
    }

    if (strlen($value) > $maxLength) {
        marketApiJson(422, [
            'success' => false,
            'message' => ucfirst(str_replace('_', ' ', $key))
                . " must be {$maxLength} characters or fewer."
        ]);
    }

    return $value;
}

function marketApiPositiveId($value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        marketApiJson(422, [
            'success' => false,
            'message' => 'A valid ID is required.'
        ]);
    }

    return $id;
}

/*
 * Authenticate using the same token format as api/login.php:
 * base64url(JSON payload).hex HMAC-SHA256 signature
 *
 * Token payload: { uid, cid, iat }
 */
function marketApiAuthenticate(mysqli $conn): array
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (
        !preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches)
    ) {
        marketApiJson(401, [
            'success' => false,
            'message' => 'Authentication token is missing.'
        ]);
    }

    $token = $matches[1];
    $parts = explode('.', $token);

    if (count($parts) !== 2) {
        marketApiJson(401, [
            'success' => false,
            'message' => 'Invalid authentication token.'
        ]);
    }

    [$encodedPayload, $providedSignature] = $parts;

    $secret = getenv('LOGICOST_API_TOKEN_SECRET');

    if ($secret === false || strlen($secret) < 32) {
        error_log('Marketplace API: token secret is missing or too short.');

        marketApiJson(500, [
            'success' => false,
            'message' => 'API authentication is not configured.'
        ]);
    }

    $expectedSignature = hash_hmac(
        'sha256',
        $encodedPayload,
        $secret
    );

    if (!hash_equals($expectedSignature, $providedSignature)) {
        marketApiJson(401, [
            'success' => false,
            'message' => 'Invalid authentication token.'
        ]);
    }

    $base64 = strtr($encodedPayload, '-_', '+/');
    $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

    $decoded = base64_decode($base64, true);
    $payload = $decoded === false
        ? null
        : json_decode($decoded, true);

    if (!is_array($payload)) {
        marketApiJson(401, [
            'success' => false,
            'message' => 'Invalid authentication token payload.'
        ]);
    }

    $userId = filter_var($payload['uid'] ?? null, FILTER_VALIDATE_INT);
    $companyId = filter_var($payload['cid'] ?? null, FILTER_VALIDATE_INT);
    $issuedAt = filter_var($payload['iat'] ?? null, FILTER_VALIDATE_INT);

    if (
        $userId === false || $userId < 1 ||
        $companyId === false || $companyId < 1 ||
        $issuedAt === false || $issuedAt < 1 ||
        $issuedAt > time() + 300 ||
        time() - $issuedAt > 30 * 24 * 60 * 60
    ) {
        marketApiJson(401, [
            'success' => false,
            'message' => 'Authentication token has expired or is invalid.'
        ]);
    }

    $stmt = $conn->prepare(
        'SELECT u.id, u.company_id
         FROM users u
         INNER JOIN companies c ON c.id = u.company_id
         WHERE u.id = ?
           AND u.company_id = ?
           AND u.status = "active"
           AND c.status = "active"
         LIMIT 1'
    );

    $stmt->bind_param('ii', $userId, $companyId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        marketApiJson(401, [
            'success' => false,
            'message' => 'Your account or company is inactive.'
        ]);
    }

    return [
        'user_id' => (int)$user['id'],
        'company_id' => (int)$user['company_id']
    ];
}

function marketApiPrice($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (
        !is_numeric($value) ||
        !is_finite((float)$value) ||
        (float)$value < 0 ||
        (float)$value > 9999999999.99
    ) {
        marketApiJson(422, [
            'success' => false,
            'message' => 'Enter a valid non-negative price in rand.'
        ]);
    }

    return round((float)$value, 2);
}

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $auth = marketApiAuthenticate($conn);
    $companyId = $auth['company_id'];

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    /*
     * GET: Browse listings and retrieve quote requests.
     */
    if ($method === 'GET') {
        $search = trim((string)($_GET['q'] ?? ''));
        $type = trim((string)($_GET['type'] ?? ''));
        $province = trim((string)($_GET['province'] ?? ''));

        if (strlen($search) > 180 || strlen($province) > 80) {
            marketApiJson(422, [
                'success' => false,
                'message' => 'Search or province filter is too long.'
            ]);
        }

        if (!in_array($type, ['', 'product', 'service', 'delivery'], true)) {
            marketApiJson(422, [
                'success' => false,
                'message' => 'Invalid listing type filter.'
            ]);
        }

        $searchLike = '%' . $search . '%';
        $provinceLike = '%' . $province . '%';

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
                ml.status,
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
            $type,
            $type,
            $province,
            $provinceLike
        );

        $stmt->execute();
        $listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        /*
         * Quotes received by this company.
         */
        $stmt = $conn->prepare(
            'SELECT
                qr.id,
                qr.listing_id,
                qr.buyer_company_id,
                qr.message,
                qr.status,
                qr.created_at,
                qr.updated_at,
                ml.title,
                bc.business_name AS buyer_name
             FROM marketplace_quote_requests qr
             INNER JOIN marketplace_listings ml
                ON ml.id = qr.listing_id
             INNER JOIN companies bc
                ON bc.id = qr.buyer_company_id
             WHERE ml.company_id = ?
             ORDER BY qr.created_at DESC
             LIMIT 100'
        );

        $stmt->bind_param('i', $companyId);
        $stmt->execute();
        $receivedQuotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        /*
         * Quotes sent by this company.
         */
        $stmt = $conn->prepare(
            'SELECT
                qr.id,
                qr.listing_id,
                qr.buyer_company_id,
                qr.message,
                qr.status,
                qr.created_at,
                qr.updated_at,
                ml.title,
                sc.business_name AS seller_name
             FROM marketplace_quote_requests qr
             INNER JOIN marketplace_listings ml
                ON ml.id = qr.listing_id
             INNER JOIN companies sc
                ON sc.id = ml.company_id
             WHERE qr.buyer_company_id = ?
             ORDER BY qr.created_at DESC
             LIMIT 100'
        );

        $stmt->bind_param('i', $companyId);
        $stmt->execute();
        $sentQuotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        marketApiJson(200, [
            'success' => true,
            'data' => $listings,
            'received_quotes' => $receivedQuotes,
            'sent_quotes' => $sentQuotes
        ]);
    }

    /*
     * POST: Publish an offer or manage a quotation request.
     */
    if ($method === 'POST') {
        $input = marketApiInput();
        $action = marketApiText($input, 'action', 30, true);

        /*
         * Publish an offer.
         */
        if ($action === 'publish') {
            $type = marketApiText($input, 'listing_type', 20, true);
            $title = marketApiText($input, 'title', 180, true);
            $description = marketApiText($input, 'description', 10000);
            $province = marketApiText($input, 'province', 80);
            $city = marketApiText($input, 'city', 100);
            $price = marketApiPrice($input['price'] ?? null);

            if (!in_array($type, ['product', 'service', 'delivery'], true)) {
                marketApiJson(422, [
                    'success' => false,
                    'message' => 'Choose product, service, or delivery.'
                ]);
            }

            if ($price === null) {
                $stmt = $conn->prepare(
                    'INSERT INTO marketplace_listings
                     (company_id, listing_type, title, description,
                      province, city, price, currency, status)
                     VALUES (?, ?, ?, ?, ?, ?, NULL, "ZAR", "published")'
                );

                $stmt->bind_param(
                    'isssss',
                    $companyId,
                    $type,
                    $title,
                    $description,
                    $province,
                    $city
                );
            } else {
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
            }

            $stmt->execute();
            $listingId = (int)$conn->insert_id;

            marketApiJson(201, [
                'success' => true,
                'message' => 'Your listing is now published.',
                'id' => $listingId
            ]);
        }

        /*
         * Request a quote from another company.
         */
        if ($action === 'request_quote') {
            $listingId = marketApiPositiveId($input['listing_id'] ?? null);
            $message = marketApiText($input, 'message', 5000, true);

            $stmt = $conn->prepare(
                'SELECT ml.id
                 FROM marketplace_listings ml
                 INNER JOIN companies c ON c.id = ml.company_id
                 WHERE ml.id = ?
                   AND ml.status = "published"
                   AND c.status = "active"
                   AND ml.company_id <> ?
                 LIMIT 1'
            );

            $stmt->bind_param('ii', $listingId, $companyId);
            $stmt->execute();

            if (!$stmt->get_result()->fetch_assoc()) {
                marketApiJson(422, [
                    'success' => false,
                    'message' => 'That listing is unavailable or belongs to your company.'
                ]);
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
                $message
            );

            $stmt->execute();

            marketApiJson(201, [
                'success' => true,
                'message' => 'Your quotation request has been sent.',
                'id' => (int)$conn->insert_id
            ]);
        }

        /*
         * Seller proposes a price and appends a reply.
         */
        if ($action === 'quote_reply') {
            $quoteId = marketApiPositiveId($input['quote_id'] ?? null);
            $price = marketApiPrice($input['proposed_price'] ?? null);
            $reply = marketApiText($input, 'reply_message', 3000);

            if ($price === null) {
                marketApiJson(422, [
                    'success' => false,
                    'message' => 'A proposed price is required.'
                ]);
            }

            $proposal = "\n\n--- SELLER PRICE PROPOSAL ---\n"
                . 'Proposed price: R '
                . number_format($price, 2, '.', '')
                . "\nSeller reply: "
                . ($reply !== '' ? $reply : 'No additional message.');

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
                $proposal,
                $quoteId,
                $companyId
            );

            $stmt->execute();

            if ($stmt->affected_rows < 1) {
                marketApiJson(409, [
                    'success' => false,
                    'message' => 'Request not found or already handled.'
                ]);
            }

            marketApiJson(200, [
                'success' => true,
                'message' => 'Your price proposal has been sent.'
            ]);
        }

        /*
         * Accept, decline, or close a quote request.
         */
        if ($action === 'quote_status') {
            $quoteId = marketApiPositiveId($input['quote_id'] ?? null);
            $status = marketApiText($input, 'status', 20, true);

            if (!in_array($status, ['accepted', 'declined', 'closed'], true)) {
                marketApiJson(422, [
                    'success' => false,
                    'message' => 'Invalid quotation status.'
                ]);
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
                $status,
                $quoteId,
                $companyId
            );

            $stmt->execute();

            if ($stmt->affected_rows < 1) {
                marketApiJson(409, [
                    'success' => false,
                    'message' => 'Request not found or already handled.'
                ]);
            }

            marketApiJson(200, [
                'success' => true,
                'message' => 'Quotation request status updated.',
                'status' => $status
            ]);
        }

        marketApiJson(422, [
            'success' => false,
            'message' => 'Unknown marketplace action.'
        ]);
    }

    header('Allow: GET, POST');

    marketApiJson(405, [
        'success' => false,
        'message' => 'HTTP method not allowed.'
    ]);

} catch (mysqli_sql_exception $e) {
    error_log('Marketplace API database error: ' . $e->getMessage());

    marketApiJson(500, [
        'success' => false,
        'message' => 'The marketplace request could not be completed.'
    ]);
} catch (Throwable $e) {
    error_log('Marketplace API error: ' . $e->getMessage());

    marketApiJson(500, [
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}
