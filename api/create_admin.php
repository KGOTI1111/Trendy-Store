<?php

require_once 'db.php';

$name = "Store Administrator";
$email = "admin@trendystore.local";
$password = "Admin@123";

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO admin_users (name, email, password)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        name = VALUES(name),
        password = VALUES(password)
");

$stmt->execute([$name, $email, $hash]);

echo "Admin account created successfully.<br>";
echo "Email: " . htmlspecialchars($email) . "<br>";
echo "Password: " . htmlspecialchars($password);

?>