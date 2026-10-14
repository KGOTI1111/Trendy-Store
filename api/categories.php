<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(false, 'GET request required.', null, 405);

$pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name');
respond(true, 'Categories loaded.', $stmt->fetchAll());
?>
