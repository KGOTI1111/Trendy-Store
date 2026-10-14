<?php
require_once 'db.php';
$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST request required.', null, 405);

$data = json_input();
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$message = trim($data['message'] ?? '');

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
    respond(false, 'Please provide a valid name, email and message.', null, 422);
}

$stmt = $pdo->prepare('INSERT INTO contact_messages (user_id, name, email, message) VALUES (?, ?, ?, ?)');
$stmt->execute([$userId, $name, $email, $message]);

respond(true, 'Your message has been submitted.');
?>
