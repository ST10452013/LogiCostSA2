<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function inventoryJson(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function inventoryInput(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        inventoryJson(400, [
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
    }

    return $data;
}

function inventoryText(
    array $data,
    string $key,
    int $maxLength,
    bool $required = false
): string {
    $value = trim((string)($data[$key] ?? ''));

    if ($required && $value === '') {
        inventoryJson(422, [
            'success' => false,
            'message' => "The {$key} field is required."
        ]);
    }

    if (strlen($value) > $maxLength) {
        inventoryJson(422, [
            'success' => false,
            'message' => "The {$key} field is too long."
        ]);
    }

    return $value;
}

function inventoryNumber(
    array $data,
    string $key,
    bool $integer = false
): int|float {
    $value = $data[$key] ?? null;

    if (
        $value === null ||
        $value === '' ||
        !is_numeric($value) ||
        !is_finite((float)$value) ||
        (float)$value < 0 ||
        ($integer && (float)$value != (int)$value)
    ) {
        inventoryJson(422, [
            'success' => false,
            'message' => "Enter a valid non-negative {$key}."
        ]);
    }

    return $integer ? (int)$value : (float)$value;
}

/*
 * Token format:
 * base64url(JSON payload).hex HMAC-SHA256 signature
 * Payload: { "uid": ..., "cid": ..., "iat": ... }
 */
function inventoryAuthenticate(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        inventoryJson(401, [
            'success' => false,
            'message' => 'Authentication required.'
        ]);
    }

    $token = trim($matches[1]);
    $parts = explode('.', $token);

    if (count($parts) !== 2) {
        inventoryJson(401, [
            'success' => false,
            'message' => 'Invalid authentication token.'
        ]);
    }

    $secret = getenv('LOGICOST_API_TOKEN_SECRET');

    if (!is_string($secret) || strlen($secret) < 32) {
        error_log('LogiCost API token secret is missing or too short.');
        inventoryJson(500, [
            'success' => false,
            'message' => 'API authentication is not configured.'
        ]);
    }

    [$encodedPayload, $providedSignature] = $parts;

    $expectedSignature = hash_hmac(
        'sha256',
        $encodedPayload,
        $secret
    );

    if (!hash_equals($expectedSignature, $providedSignature)) {
        inventoryJson(401, [
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
        inventoryJson(401, [
            'success' => false,
            'message' => 'Invalid authentication token.'
        ]);
    }

    $userId = filter_var($payload['uid'] ?? null, FILTER_VALIDATE_INT);
    $companyId = filter_var($payload['cid'] ?? null, FILTER_VALIDATE_INT);
    $issuedAt = filter_var($payload['iat'] ?? null, FILTER_VALIDATE_INT);

    if (
        !$userId || !$companyId || !$issuedAt ||
        $issuedAt > time() + 60 ||
        $issuedAt < time() - (30 * 24 * 60 * 60)
    ) {
        inventoryJson(401, [
            'success' => false,
            'message' => 'Authentication token has expired.'
        ]);
    }

    return [
        'user_id' => (int)$userId,
        'company_id' => (int)$companyId
    ];
}

function inventoryVerifyActiveUser(mysqli $conn, array $auth): void {
    $stmt = $conn->prepare(
        'SELECT u.id
         FROM users u
         INNER JOIN companies c ON c.id = u.company_id
         WHERE u.id = ?
           AND u.company_id = ?
           AND u.status = "active"
           AND c.status = "active"
         LIMIT 1'
    );

    $stmt->bind_param(
        'ii',
        $auth['user_id'],
        $auth['company_id']
    );
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        inventoryJson(401, [
            'success' => false,
            'message' => 'Your account or company is inactive.'
        ]);
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    inventoryJson(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

$auth = inventoryAuthenticate();
$companyId = $auth['company_id'];

inventoryVerifyActiveUser($conn, $auth);

try {
    if ($method === 'GET') {
        $search = trim((string)($_GET['q'] ?? ''));
        $like = '%' . $search . '%';

        $stmt = $conn->prepare(
            'SELECT id, sku, product_name, category, quantity,
                    low_stock_threshold, cost_price, selling_price,
                    created_at, updated_at
             FROM inventory_items
             WHERE company_id = ?
               AND (? = "" OR product_name LIKE ?
                    OR sku LIKE ? OR category LIKE ?)
             ORDER BY product_name ASC'
        );

        $stmt->bind_param(
            'issss',
            $companyId,
            $search,
            $like,
            $like,
            $like
        );
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['quantity'] = (int)$item['quantity'];
            $item['low_stock_threshold'] =
                (int)$item['low_stock_threshold'];
            $item['cost_price'] = (float)$item['cost_price'];
            $item['selling_price'] = (float)$item['selling_price'];
        }
        unset($item);

        $totalUnits = 0;
        $lowStockCount = 0;

        foreach ($items as $item) {
            $totalUnits += $item['quantity'];

            if ($item['quantity'] <= $item['low_stock_threshold']) {
                $lowStockCount++;
            }
        }

        inventoryJson(200, [
            'success' => true,
            'message' => 'Inventory loaded.',
            'data' => $items,
            'summary' => [
                'total_products' => count($items),
                'total_units' => $totalUnits,
                'low_stock_count' => $lowStockCount
            ]
        ]);
    }

    $data = inventoryInput();

    if ($method === 'POST') {
        $sku = inventoryText($data, 'sku', 80, true);
        $name = inventoryText($data, 'product_name', 180, true);
        $category = inventoryText($data, 'category', 100);
        $quantity = inventoryNumber($data, 'quantity', true);
        $threshold = inventoryNumber(
            $data,
            'low_stock_threshold',
            true
        );
        $cost = inventoryNumber($data, 'cost_price');
        $selling = inventoryNumber($data, 'selling_price');

        $stmt = $conn->prepare(
            'INSERT INTO inventory_items
             (company_id, sku, product_name, category, quantity,
              low_stock_threshold, cost_price, selling_price)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'isssiidd',
            $companyId,
            $sku,
            $name,
            $category,
            $quantity,
            $threshold,
            $cost,
            $selling
        );

        $stmt->execute();

        inventoryJson(201, [
            'success' => true,
            'message' => 'Product added successfully.',
            'id' => $conn->insert_id
        ]);
    }

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        inventoryJson(422, [
            'success' => false,
            'message' => 'A valid product ID is required.'
        ]);
    }

    if ($method === 'PUT') {
        $quantity = inventoryNumber($data, 'quantity', true);
        $threshold = inventoryNumber(
            $data,
            'low_stock_threshold',
            true
        );
        $cost = inventoryNumber($data, 'cost_price');
        $selling = inventoryNumber($data, 'selling_price');

        $stmt = $conn->prepare(
            'UPDATE inventory_items
             SET quantity = ?, low_stock_threshold = ?,
                 cost_price = ?, selling_price = ?
             WHERE id = ? AND company_id = ?'
        );

        $stmt->bind_param(
            'iiddii',
            $quantity,
            $threshold,
            $cost,
            $selling,
            $id,
            $companyId
        );
        $stmt->execute();

        // Check ownership/existence even when submitted values did not change.
        $check = $conn->prepare(
            'SELECT id FROM inventory_items
             WHERE id = ? AND company_id = ? LIMIT 1'
        );
        $check->bind_param('ii', $id, $companyId);
        $check->execute();

        if (!$check->get_result()->fetch_assoc()) {
            inventoryJson(404, [
                'success' => false,
                'message' => 'Product not found.'
            ]);
        }

        inventoryJson(200, [
            'success' => true,
            'message' => 'Inventory details saved.'
        ]);
    }

    if ($method === 'DELETE') {
        $stmt = $conn->prepare(
            'DELETE FROM inventory_items
             WHERE id = ? AND company_id = ?'
        );

        $stmt->bind_param('ii', $id, $companyId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            inventoryJson(404, [
                'success' => false,
                'message' => 'Product not found.'
            ]);
        }

        inventoryJson(200, [
            'success' => true,
            'message' => 'Product deleted.'
        ]);
    }
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        inventoryJson(409, [
            'success' => false,
            'message' => 'That product code already exists in your inventory.'
        ]);
    }

    error_log('Inventory API database error: ' . $e->getMessage());
    inventoryJson(500, [
        'success' => false,
        'message' => 'The inventory request could not be completed.'
    ]);
} catch (Throwable $e) {
    error_log('Inventory API error: ' . $e->getMessage());
    inventoryJson(500, [
        'success' => false,
        'message' => 'An unexpected inventory error occurred.'
    ]);
}

inventoryJson(405, [
    'success' => false,
    'message' => 'Unsupported request.'
]);