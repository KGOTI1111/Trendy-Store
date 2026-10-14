<?php
header('Content-Type: application/json; charset=utf-8');

$host = '127.0.0.1';
$port = '3306'; // Change to your XAMPP MySQL port if you use a different port.
$db   = 'trendy_store';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Check XAMPP MySQL, database name, username, password and port.'
    ]);
    exit;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(bool $success, string $message = '', $data = null, int $status = 200): void {
    http_response_code($status);
    $out = ['success' => $success, 'message' => $message];
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out);
    exit;
}

function require_login(): int {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $userId = $_SESSION['user_id'] ?? $_SESSION['store_user']['id'] ?? null;
    if (empty($userId)) {
        respond(false, 'Please login first.', null, 401);
    }
    return (int)$userId;
}
?>
