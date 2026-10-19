<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        respond(400, [
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
    }

    return $data;
}

function bearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        respond(401, [
            'success' => false,
            'message' => 'Authentication token required.'
        ]);
    }

    return trim($matches[1]);
}

function base64UrlDecode(string $value): string|false
{
    $remainder = strlen($value) % 4;

    if ($remainder !== 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

/**
 * Verifies the signed token created by api/login.php.
 * Expected format: base64url(JSON payload).hex HMAC-SHA256 signature
 * Payload fields: uid, cid, iat.
 */
function authenticate(mysqli $conn): array
{
    $secret = getenv('LOGICOST_API_TOKEN_SECRET');

    if (!$secret || strlen($secret) < 32) {
        error_log('LOGICOST_API_TOKEN_SECRET is missing or too short.');
        respond(500, [
            'success' => false,
            'message' => 'API authentication is not configured.'
        ]);
    }

    $token = bearerToken();
    $parts = explode('.', $token);

    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        respond(401, ['success' => false, 'message' => 'Invalid token.']);
    }

    [$encodedPayload, $signature] = $parts;
    $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret);

    if (!hash_equals($expectedSignature, $signature)) {
        respond(401, ['success' => false, 'message' => 'Invalid token signature.']);
    }

    $payloadJson = base64UrlDecode($encodedPayload);
    $payload = $payloadJson !== false
        ? json_decode($payloadJson, true)
        : null;

    if (!is_array($payload)) {
        respond(401, ['success' => false, 'message' => 'Invalid token payload.']);
    }

    $userId = filter_var($payload['uid'] ?? null, FILTER_VALIDATE_INT);
    $companyId = filter_var($payload['cid'] ?? null, FILTER_VALIDATE_INT);
    $issuedAt = filter_var($payload['iat'] ?? null, FILTER_VALIDATE_INT);
    $now = time();

    if (
        !$userId || !$companyId || !$issuedAt ||
        $issuedAt > $now + 60 ||
        ($now - $issuedAt) > (30 * 24 * 60 * 60)
    ) {
        respond(401, ['success' => false, 'message' => 'Token expired or invalid.']);
    }

    $stmt = $conn->prepare(
        'SELECT u.id, u.company_id, u.full_name, u.email, u.role
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
    $stmt->close();

    if (!$user) {
        respond(401, [
            'success' => false,
            'message' => 'Your account or company is inactive.'
        ]);
    }

    return $user;
}

function requiredText(
    array $data,
    string $key,
    string $label,
    int $maxLength
): string {
    $value = trim((string)($data[$key] ?? ''));

    if ($value === '' || strlen($value) > $maxLength) {
        respond(422, [
            'success' => false,
            'message' => "$label is required and must not exceed $maxLength characters."
        ]);
    }

    return $value;
}

function optionalText(
    array $data,
    string $key,
    int $maxLength
): string {
    $value = trim((string)($data[$key] ?? ''));

    if (strlen($value) > $maxLength) {
        respond(422, [
            'success' => false,
            'message' => "The $key field is too long."
        ]);
    }

    return $value;
}

function validScheduledDate(array $data): ?string
{
    $value = trim((string)($data['scheduled_date'] ?? ''));

    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        respond(422, [
            'success' => false,
            'message' => 'Enter a valid scheduled date.'
        ]);
    }

    return $value;
}

function deliveryCost(array $data): ?float
{
    $value = trim((string)($data['agreed_cost'] ?? ''));

    if ($value === '') {
        return null;
    }

    if (
        !is_numeric($value) ||
        !is_finite((float)$value) ||
        (float)$value < 0 ||
        (float)$value > 9999999999.99
    ) {
        respond(422, [
            'success' => false,
            'message' => 'Enter a valid non-negative delivery cost.'
        ]);
    }

    return round((float)$value, 2);
}

function recordHistory(
    mysqli $conn,
    int $deliveryId,
    int $companyId,
    int $userId,
    ?string $oldStatus,
    string $newStatus,
    string $note
): void {
    $stmt = $conn->prepare(
        'INSERT INTO delivery_status_history
         (delivery_id, company_id, changed_by, old_status, new_status, note)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->bind_param(
        'iiisss',
        $deliveryId,
        $companyId,
        $userId,
        $oldStatus,
        $newStatus,
        $note
    );

    $stmt->execute();
    $stmt->close();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Allow: GET, POST, PUT, OPTIONS');
        http_response_code(204);
        exit;
    }

    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT'], true)) {
        header('Allow: GET, POST, PUT, OPTIONS');
        respond(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    /** The company ID always comes from the verified token. */
    $user = authenticate($conn);
    $userId = (int)$user['id'];
    $companyId = (int)$user['company_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    /*
     * GET: list jobs for this company.
     * Optional query parameters: ?status=pending&q=JOB
     */
    if ($method === 'GET') {
        $allowedStatuses = [
            'pending', 'assigned', 'in_transit', 'delivered', 'cancelled'
        ];

        $status = trim((string)($_GET['status'] ?? ''));
        $search = trim((string)($_GET['q'] ?? ''));

        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            respond(422, ['success' => false, 'message' => 'Invalid status filter.']);
        }

        if (strlen($search) > 150) {
            respond(422, ['success' => false, 'message' => 'Search text is too long.']);
        }

        $like = '%' . $search . '%';

        $stmt = $conn->prepare(
            'SELECT id, reference_code, pickup_address, dropoff_address,
                    package_description, recipient_name, recipient_phone,
                    scheduled_date, agreed_cost, driver_name, driver_phone,
                    status, created_at, updated_at
             FROM delivery_jobs
             WHERE company_id = ?
               AND (? = "" OR status = ?)
               AND (? = "" OR reference_code LIKE ?
                    OR pickup_address LIKE ?
                    OR dropoff_address LIKE ?
                    OR package_description LIKE ?)
             ORDER BY created_at DESC'
        );

        $stmt->bind_param(
            'isssssss',
            $companyId,
            $status,
            $status,
            $search,
            $like,
            $like,
            $like,
            $like
        );

        $stmt->execute();
        $result = $stmt->get_result();
        $deliveries = [];

        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['agreed_cost'] = $row['agreed_cost'] !== null
                ? (float)$row['agreed_cost']
                : null;

            $deliveries[] = $row;
        }

        $stmt->close();

        respond(200, [
            'success' => true,
            'message' => 'Deliveries loaded.',
            'data' => $deliveries
        ]);
    }

    /*
     * POST: create a delivery.
     */
    if ($method === 'POST') {
        $data = readJson();

        $reference = strtoupper(requiredText($data, 'reference_code', 'Reference code', 30));

        if (!preg_match('/^[A-Z0-9_-]+$/', $reference)) {
            respond(422, [
                'success' => false,
                'message' => 'Use only letters, numbers, hyphens or underscores in the reference code.'
            ]);
        }

        $pickup = requiredText($data, 'pickup_address', 'Pickup address', 500);
        $dropoff = requiredText($data, 'dropoff_address', 'Drop-off address', 500);
        $package = requiredText($data, 'package_description', 'Package description', 500);
        $recipient = optionalText($data, 'recipient_name', 160);
        $recipientPhone = optionalText($data, 'recipient_phone', 30);
        $scheduled = validScheduledDate($data);
        $cost = deliveryCost($data);

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
            $companyId,
            $userId,
            $reference,
            $pickup,
            $dropoff,
            $package,
            $recipient,
            $recipientPhone,
            $scheduled,
            $cost
        );

        $stmt->execute();
        $deliveryId = (int)$conn->insert_id;
        $stmt->close();

        recordHistory(
            $conn,
            $deliveryId,
            $companyId,
            $userId,
            null,
            'pending',
            'Delivery created'
        );

        $conn->commit();

        respond(201, [
            'success' => true,
            'message' => 'Delivery created successfully.',
            'id' => $deliveryId
        ]);
    }

    /*
     * PUT: update a delivery's status and driver details.
     * Required: id, status.
     * Required when status becomes assigned: driver_name, driver_phone.
     */
    if ($method === 'PUT') {
        $data = readJson();

        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $newStatus = trim((string)($data['status'] ?? ''));

        $allowedStatuses = [
            'pending', 'assigned', 'in_transit', 'delivered', 'cancelled'
        ];

        if (!$id || !in_array($newStatus, $allowedStatuses, true)) {
            respond(422, [
                'success' => false,
                'message' => 'A valid delivery ID and status are required.'
            ]);
        }

        $driverName = optionalText($data, 'driver_name', 160);
        $driverPhone = optionalText($data, 'driver_phone', 30);
        $note = optionalText($data, 'note', 500);

        $conn->begin_transaction();

        $stmt = $conn->prepare(
            'SELECT status
             FROM delivery_jobs
             WHERE id = ? AND company_id = ?
             FOR UPDATE'
        );

        $stmt->bind_param('ii', $id, $companyId);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$job) {
            $conn->rollback();
            respond(404, [
                'success' => false,
                'message' => 'Delivery not found in your company.'
            ]);
        }

        $oldStatus = (string)$job['status'];

        $transitions = [
            'pending' => ['assigned', 'cancelled'],
            'assigned' => ['in_transit', 'cancelled'],
            'in_transit' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => []
        ];

        if (
            $newStatus !== $oldStatus &&
            !in_array($newStatus, $transitions[$oldStatus] ?? [], true)
        ) {
            $conn->rollback();
            respond(422, [
                'success' => false,
                'message' => 'That status change is not allowed. Follow the delivery status sequence.'
            ]);
        }

        if ($newStatus === 'assigned' && ($driverName === '' || $driverPhone === '')) {
            $conn->rollback();
            respond(422, [
                'success' => false,
                'message' => 'Enter the driver name and phone number before assigning the delivery.'
            ]);
        }

        /*
         * Keep existing driver details if the request leaves them blank.
         * On the initial assignment, both fields are required.
         */
        $stmt = $conn->prepare(
            'UPDATE delivery_jobs
             SET status = ?,
                 driver_name = CASE WHEN ? = "" THEN driver_name ELSE ? END,
                 driver_phone = CASE WHEN ? = "" THEN driver_phone ELSE ? END
             WHERE id = ? AND company_id = ?'
        );

        $stmt->bind_param(
            'sssssii',
            $newStatus,
            $driverName,
            $driverName,
            $driverPhone,
            $driverPhone,
            $id,
            $companyId
        );

        $stmt->execute();
        $stmt->close();

        if ($newStatus !== $oldStatus) {
            recordHistory(
                $conn,
                (int)$id,
                $companyId,
                $userId,
                $oldStatus,
                $newStatus,
                $note
            );
        }

        $conn->commit();

        respond(200, [
            'success' => true,
            'message' => 'Delivery updated successfully.'
        ]);
    }
} catch (mysqli_sql_exception $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // No active transaction.
    }

    if ((int)$e->getCode() === 1062) {
        respond(409, [
            'success' => false,
            'message' => 'That reference code is already in use.'
        ]);
    }

    error_log('Deliveries API database error: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'A database error occurred while processing the delivery.'
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // No active transaction.
    }

    error_log('Deliveries API error: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'The request could not be completed.'
    ]);
}