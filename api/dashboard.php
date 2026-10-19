<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

require_once __DIR__ . '/../config/database.php';

/*
 * Use the SAME secret configured for api/login.php.
 * Set LOGICOST_API_TOKEN_SECRET in the PHP server environment.
 */
$secret = getenv('LOGICOST_API_TOKEN_SECRET');

if (!$secret || strlen($secret) < 32) {
    respond(500, [
        'success' => false,
        'message' => 'API authentication is not configured.'
    ]);
}

/* Read the Authorization header. */
$headers = function_exists('getallheaders')
    ? getallheaders()
    : [];

$authorization = '';

foreach ($headers as $key => $value) {
    if (strtolower($key) === 'authorization') {
        $authorization = trim($value);
        break;
    }
}

if ($authorization === '' &&
    isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authorization = trim($_SERVER['HTTP_AUTHORIZATION']);
}

if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
    respond(401, [
        'success' => false,
        'message' => 'Authentication token is required.'
    ]);
}

$token = $matches[1];
$parts = explode('.', $token);

if (count($parts) !== 2) {
    respond(401, [
        'success' => false,
        'message' => 'Invalid authentication token.'
    ]);
}

[$encodedPayload, $providedSignature] = $parts;

$expectedSignature = hash_hmac(
    'sha256',
    $encodedPayload,
    $secret
);

if (!hash_equals($expectedSignature, $providedSignature)) {
    respond(401, [
        'success' => false,
        'message' => 'Invalid authentication token.'
    ]);
}

/* Decode the signed token payload. */
$base64 = strtr($encodedPayload, '-_', '+/');
$padding = strlen($base64) % 4;

if ($padding !== 0) {
    $base64 .= str_repeat('=', 4 - $padding);
}

$payloadJson = base64_decode($base64, true);
$payload = $payloadJson !== false
    ? json_decode($payloadJson, true)
    : null;

if (
    !is_array($payload) ||
    !isset($payload['uid'], $payload['cid'], $payload['iat']) ||
    !is_numeric($payload['uid']) ||
    !is_numeric($payload['cid']) ||
    !is_numeric($payload['iat'])
) {
    respond(401, [
        'success' => false,
        'message' => 'Invalid authentication token.'
    ]);
}

$userId = (int) $payload['uid'];
$companyId = (int) $payload['cid'];
$issuedAt = (int) $payload['iat'];

/* Reject invalid timestamps and tokens older than 30 days. */
if (
    $userId < 1 ||
    $companyId < 1 ||
    $issuedAt > time() + 60 ||
    $issuedAt < time() - (30 * 24 * 60 * 60)
) {
    respond(401, [
        'success' => false,
        'message' => 'Your session has expired. Please log in again.'
    ]);
}

/*
 * Confirm that the user and company are still active
 * and that the token belongs to this company.
 */
$authStmt = $conn->prepare(
    'SELECT u.id
     FROM users u
     INNER JOIN companies c ON c.id = u.company_id
     WHERE u.id = ?
       AND u.company_id = ?
       AND u.status = "active"
       AND c.status = "active"
     LIMIT 1'
);

if (!$authStmt) {
    respond(500, [
        'success' => false,
        'message' => 'Could not verify your session.'
    ]);
}

$authStmt->bind_param('ii', $userId, $companyId);
$authStmt->execute();
$authResult = $authStmt->get_result();
$activeUser = $authResult->fetch_assoc();
$authStmt->close();

if (!$activeUser) {
    respond(401, [
        'success' => false,
        'message' => 'Your account or company is inactive.'
    ]);
}

/* Retrieve statistics scoped to this company. */
$sql = '
    SELECT
        (
            SELECT COUNT(*)
            FROM inventory_items
            WHERE company_id = ?
        ) AS inventory_count,

        (
            SELECT COUNT(*)
            FROM delivery_jobs
            WHERE company_id = ?
        ) AS delivery_count,

        (
            SELECT COUNT(*)
            FROM delivery_jobs
            WHERE company_id = ?
              AND status = "pending"
        ) AS pending_count,

        (
            SELECT COUNT(*)
            FROM inventory_items
            WHERE company_id = ?
              AND quantity <= low_stock_threshold
        ) AS low_stock,

        (
            SELECT COUNT(*)
            FROM delivery_jobs
            WHERE company_id = ?
              AND status = "in_transit"
        ) AS in_transit_count,

        (
            SELECT COUNT(*)
            FROM delivery_jobs
            WHERE company_id = ?
              AND status = "delivered"
        ) AS delivered_count
';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond(500, [
        'success' => false,
        'message' => 'Could not prepare dashboard statistics.'
    ]);
}

$stmt->bind_param(
    'iiiiii',
    $companyId,
    $companyId,
    $companyId,
    $companyId,
    $companyId,
    $companyId
);

if (!$stmt->execute()) {
    $stmt->close();

    respond(500, [
        'success' => false,
        'message' => 'Could not load dashboard statistics.'
    ]);
}

$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

if (!$stats) {
    respond(500, [
        'success' => false,
        'message' => 'Dashboard statistics were not found.'
    ]);
}

respond(200, [
    'success' => true,
    'data' => [
        'inventory_count' => (int) $stats['inventory_count'],
        'delivery_count' => (int) $stats['delivery_count'],
        'pending_count' => (int) $stats['pending_count'],
        'low_stock' => (int) $stats['low_stock'],
        'in_transit_count' => (int) $stats['in_transit_count'],
        'delivered_count' => (int) $stats['delivered_count']
    ]
]);