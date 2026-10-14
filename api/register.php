<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST request required.', null, 405);

$data = json_input();
$name = trim($data['fullName'] ?? '');
$email = trim($data['email'] ?? '');
$mobile = trim($data['mobile'] ?? '');
$password = $data['password'] ?? '';

if (strlen($name) < 3) respond(false, 'Full name must contain at least 3 characters.', null, 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Please enter a valid email address.', null, 422);
if (!preg_match('/^\d{10}$/', $mobile)) respond(false, 'Mobile number must contain exactly 10 digits.', null, 422);
if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
    respond(false, 'Password must be at least 8 characters and contain an uppercase letter, a number and a special character.', null, 422);
}

$check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$check->execute([$email]);
if ($check->fetch()) respond(false, 'An account with this email already exists. Please use another email.', null, 409);

$hash = password_hash($password, PASSWORD_DEFAULT);
$column = $pdo->query("SHOW COLUMNS FROM users LIKE 'mobile'")->fetch();
if (!$column) {
    $pdo->exec("ALTER TABLE users ADD COLUMN mobile VARCHAR(20) NOT NULL DEFAULT '' AFTER email");
}
$stmt = $pdo->prepare('INSERT INTO users (full_name, email, mobile, password) VALUES (?, ?, ?, ?)');
$stmt->execute([$name, $email, $mobile, $hash]);

$_SESSION['user_id'] = (int)$pdo->lastInsertId();

respond(true, 'Registration successful.', [
    'id' => $_SESSION['user_id'],
    'fullName' => $name,
    'email' => $email,
    'mobile' => $mobile
]);
?>
