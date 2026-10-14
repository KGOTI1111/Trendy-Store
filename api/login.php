<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST request required.', null, 405);

$data = json_input();
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    respond(false, 'Please enter your email and password.', null, 422);
}

$adminStatement = $pdo->prepare('SELECT id, name, email, password FROM admin_users WHERE email = ? LIMIT 1');
$adminStatement->execute([$email]);
$admin = $adminStatement->fetch();

if ($admin && password_verify($password, $admin['password'])) {
    session_regenerate_id(true);
    unset($_SESSION['user_id']);
    $_SESSION['admin_id'] = (int)$admin['id'];

    respond(true, 'Administrator login successful.', [
        'id' => (int)$admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => 'admin'
    ]);
}

$stmt = $pdo->prepare('SELECT id, full_name, email, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    respond(false, 'Invalid email or password.', null, 401);
}

session_regenerate_id(true);
unset($_SESSION['admin_id']);
$_SESSION['user_id'] = (int)$user['id'];

respond(true, 'Login successful.', [
    'id' => $_SESSION['user_id'],
    'fullName' => $user['full_name'],
    'email' => $user['email'],
    'mobile' => $user['mobile'] ?? '',
    'role' => 'user'
]);
?>