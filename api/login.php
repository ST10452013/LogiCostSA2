<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

require_once __DIR__ . '/../config/database.php';

/*
 * IMPORTANT:
 * Replace this placeholder with a long, random secret stored
 * securely on your server, outside the public web directory.
 * The future API auth_helper.php must use the SAME secret.
 */
$tokenSecret = getenv('LOGICOST_API_TOKEN_SECRET');

if (!$tokenSecret || strlen($tokenSecret) < 32) {
    error_log('LogiCost API token secret is not configured.');
    respond(500, [
        'success' => false,
        'message' => 'Server authentication is not configured.'
    ]);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    respond(400, [
        'success' => false,
        'message' => 'Invalid JSON request.'
    ]);
}

$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $password === '' ||
    strlen($email) > 254
) {
    respond(422, [
        'success' => false,
        'message' => 'Enter a valid email address and password.'
    ]);
}

try {
    $stmt = $conn->prepare(
        'SELECT
            u.id,
            u.company_id,
            u.full_name,
            u.email,
            u.password_hash,
            u.role,
            u.status AS user_status,
            c.status AS company_status
         FROM users u
         INNER JOIN companies c ON c.id = u.company_id
         WHERE u.email = ?
         LIMIT 1'
    );

    $stmt->bind_param('s', $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (
        !$user ||
        !password_verify($password, $user['password_hash']) ||
        $user['user_status'] !== 'active' ||
        $user['company_status'] !== 'active'
    ) {
        respond(401, [
            'success' => false,
            'message' => 'The email or password is incorrect, or the account is inactive.'
        ]);
    }

    // Signed token: payload.signature
    // The server must verify this signature on protected API requests.
    $payload = json_encode([
        'uid' => (int) $user['id'],
        'cid' => (int) $user['company_id'],
        'iat' => time()
    ]);

    $encodedPayload = rtrim(
        strtr(base64_encode($payload), '+/', '-_'),
        '='
    );

    $signature = hash_hmac(
        'sha256',
        $encodedPayload,
        $tokenSecret
    );

    $token = $encodedPayload . '.' . $signature;

    $update = $conn->prepare(
        'UPDATE users SET last_login_at = NOW() WHERE id = ?'
    );
    $userId = (int) $user['id'];
    $update->bind_param('i', $userId);
    $update->execute();
    $update->close();

    respond(200, [
        'success' => true,
        'message' => 'Login successful.',
        'token' => $token,
        'user' => [
            'id' => (int) $user['id'],
            'fullname' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'company_id' => (int) $user['company_id']
        ]
    ]);

} catch (Throwable $e) {
    error_log('LogiCost API login error: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'A server error occurred. Please try again later.'
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
