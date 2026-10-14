<?php
declare(strict_types=1);
require_once __DIR__ . '/guard.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($name) < 3 || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errorMessage = 'Enter a name between 3 and 100 characters and a valid email.';
        } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errorMessage = 'Admin passwords need 12+ characters with upper/lowercase, a number, and a symbol.';
        } else {
            try {
                $statement = admin_database()->prepare('INSERT INTO admin_users (name, email, password) VALUES (:name, :email, :password)');
                $statement->execute(['name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
                $message = 'Admin account created. Share the password with the new admin through a secure channel.';
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    $errorMessage = 'An admin account with this email already exists.';
                } else {
                    error_log('Admin account creation failed: ' . $error->getMessage());
                    $errorMessage = 'Could not create the admin account.';
                }
            }
        }
    }
}

try {
    $admins = admin_database()->query('SELECT id, name, email, created_at FROM admin_users ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $error) {
    error_log('Admin directory load failed: ' . $error->getMessage());
    $admins = [];
    $errorMessage = $errorMessage ?: 'Could not load admin accounts.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trendy Store | Manage Admins</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin.css">
</head>
<body>
    <header class="admin-topbar">
        <a class="admin-brand" href="./index.php">Trendy Store<span> / Admin</span></a>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="./index.php">Overview</a>
            <a href="./manageproduct.php">Products</a>
            <a href="./manageusers.php">Users</a>
            <a href="./manageorders.php">Orders</a>
            <a href="./manageadmins.php" aria-current="page">Admins</a>
            <a href="../index.html">Storefront</a>
            <a href="./logout.php">Sign out</a>
        </nav>
    </header>
    <main class="admin-main">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Access</p>
                <h1>Manage admin accounts</h1>
                <p>Create additional administrators. This page is available only to an authenticated admin session.</p>
            </div>
        </div>
        <p class="notice"><strong>Security:</strong> Admin passwords are hashed before storage and are never displayed. Use a unique password and share it with the new admin through a secure channel.</p>
        <?php if ($errorMessage !== ''): ?><p class="login-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($message !== ''): ?><p class="inline-status" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form class="editor" method="post" action="./manageadmins.php" autocomplete="off">
            <h2>Create admin account</h2>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="adminName">Full name</label>
                    <input id="adminName" name="name" type="text" required minlength="3" maxlength="100" value="<?= htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="adminEmail">Admin email</label>
                    <input id="adminEmail" name="email" type="email" required maxlength="150" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group form-span">
                    <label for="adminPassword">Temporary password</label>
                    <input id="adminPassword" name="password" type="password" autocomplete="new-password" required minlength="12">
                </div>
            </div>
            <div class="form-actions"><button class="button" type="submit"><i class="fas fa-user-shield" aria-hidden="true"></i> Create admin</button></div>
        </form>
        <div class="section-heading"><h2>Admin accounts</h2><span><?= count($admins) ?> accounts</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?= htmlspecialchars($admin['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($admin['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$admins): ?><p class="empty-state">No admin accounts found.</p><?php endif; ?>
        </div>
    </main>
</body>
</html>
