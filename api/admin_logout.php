<?php
require_once 'db.php';
require_once __DIR__ . '/../admin/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'POST request required.', null, 405);
}

unset($_SESSION['admin_user'], $_SESSION['admin_csrf']);
session_regenerate_id(true);
respond(true, 'Administrator logged out.');
?>
