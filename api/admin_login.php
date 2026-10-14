<?php
require_once 'db.php';
require_once __DIR__ . '/../admin/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['admin_user']['id'])) {
        respond(false, 'Administrator login required.', null, 401);
    }

    $stmt = $pdo->prepare('SELECT id, name, email FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['admin_user']['id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        unset($_SESSION['admin_user'], $_SESSION['admin_csrf']);
        respond(false, 'Administrator session is no longer valid.', null, 401);
    }

    respond(true, 'Administrator session is active.', $admin);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'GET or POST request required.', null, 405);
}

$data = json_input();
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid administrator email.', null, 422);
}
if ($password === '') {
    respond(false, 'Please enter the administrator password.', null, 422);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$stmt = $pdo->prepare('SELECT id, name, email, password FROM admin_users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password'])) {
    respond(false, 'Invalid administrator email or password.', null, 401);
}

session_regenerate_id(true);
$_SESSION['admin_user'] = [
    'id' => (int)$admin['id'],
    'name' => $admin['name'],
    'email' => $admin['email']
];
$_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

respond(true, 'Administrator login successful.', [
    'id' => $_SESSION['admin_user']['id'],
    'name' => $admin['name'],
    'email' => $admin['email']
]);
?>
