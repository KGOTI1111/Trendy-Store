<?php
require_once 'db.php';
$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, full_name AS fullName, email, created_at AS createdAt FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) respond(false, 'User not found.', null, 404);
    respond(true, 'Profile loaded.', $user);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_input();
    $name = trim($data['fullName'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (strlen($name) < 3) respond(false, 'Full name must contain at least 3 characters.', null, 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Please enter a valid email address.', null, 422);

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $check->execute([$email, $userId]);
    if ($check->fetch()) respond(false, 'That email is already used by another account.', null, 409);

    if ($password !== '') {
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            respond(false, 'Password must be at least 8 characters and contain an uppercase letter, a number and a special character.', null, 422);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?');
        $stmt->execute([$name, $email, $hash, $userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
        $stmt->execute([$name, $email, $userId]);
    }

    respond(true, 'Profile updated.', ['id'=>$userId, 'fullName'=>$name, 'email'=>$email]);
}

respond(false, 'Unsupported request method.', null, 405);
?>
