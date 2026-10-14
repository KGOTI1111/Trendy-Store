<?php
require_once 'db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    respond(false, 'Not logged in.', null, 401);
}

    $stmt = $pdo->prepare('SELECT id, full_name AS fullName, email, mobile, created_at AS createdAt FROM users WHERE id = ?');
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    respond(false, 'User account not found.', null, 401);
}

respond(true, 'Logged in.', $user);
?>
