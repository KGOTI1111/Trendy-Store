<?php
require_once 'db.php';
$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST request required.', null, 405);

$data = json_input();
$email = trim($data['email'] ?? '');
$message = trim($data['message'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
    respond(false, 'Please provide a valid email and inquiry.', null, 422);
}

$stmt = $pdo->prepare('INSERT INTO inquiries (user_id, email, message) VALUES (?, ?, ?)');
$stmt->execute([$userId, $email, $message]);

respond(true, 'Your inquiry has been submitted.');
?>
