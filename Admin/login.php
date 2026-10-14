<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['admin_user']['id'])) {
    header('Location: ./index.html');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['admin_csrf'], $submittedToken)) {
        $errorMessage = 'Your sign-in session expired. Refresh the page and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $errorMessage = 'Enter your admin email and password.';
        } else {
            try {
                $statement = admin_database()->prepare(
                    'SELECT id, name, email, password FROM admin_users WHERE email = :email LIMIT 1'
                );
                $statement->execute(['email' => $email]);
                $admin = $statement->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_user'] = [
                        'id' => (int) $admin['id'],
                        'name' => $admin['name'],
                        'email' => $admin['email']
                    ];
                    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                    header('Location: ./index.html');
                    exit;
                }

                $errorMessage = 'Email or password is incorrect.';
            } catch (Throwable $error) {
                error_log('Admin sign-in failed: ' . $error->getMessage());
                $errorMessage = 'Admin database connection failed. Check XAMPP and the database settings.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trendy Store | Admin Sign In</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin.css">
    <style>
        .login-layout { min-height: 100vh; display: grid; grid-template-columns: minmax(300px, 0.9fr) minmax(380px, 1.1fr); }
        .login-aside { display: flex; flex-direction: column; justify-content: space-between; padding: clamp(28px, 6vw, 76px); background: var(--admin-ink); color: white; }
        .login-aside .admin-brand { color: white; }
        .login-aside-copy { max-width: 420px; margin: auto 0; }
        .login-aside-copy .eyebrow { color: #df9678; }
        .login-aside-copy h1 { margin: 0; font: 700 clamp(32px, 4vw, 54px) 'Playfair Display', serif; }
        .login-aside-copy p { color: #cbd6d0; font-size: 14px; line-height: 1.8; }
        .login-main { display: grid; place-items: center; padding: 32px; }
        .login-form { width: min(420px, 100%); padding: 32px; border: 1px solid var(--admin-line); background: white; box-shadow: var(--admin-shadow); }
        .login-form h2 { margin: 0 0 8px; font-size: 22px; }
        .login-form > p { margin: 0 0 24px; color: var(--admin-muted); font-size: 13px; line-height: 1.6; }
        .login-form .form-group + .form-group { margin-top: 16px; }
        .login-form .form-group input { min-height: 44px; }
        .password-entry { position: relative; }
        .password-entry input { padding-right: 76px; }
        .password-visibility { position: absolute; top: 50%; right: 10px; transform: translateY(-50%); border: 0; background: transparent; color: var(--admin-green); font-size: 11px; font-weight: 700; cursor: pointer; }
        .login-form .button { width: 100%; margin-top: 20px; }
        .login-error { margin: 0 0 14px; padding: 11px 12px; background: #fff0ee; color: var(--admin-danger); font-size: 12px; }
        .login-note { margin-top: 16px !important; font-size: 11px !important; }
        @media (max-width: 760px) { .login-layout { grid-template-columns: 1fr; } .login-aside { min-height: 240px; padding: 24px; } .login-aside-copy { margin: 38px 0 0; } .login-aside-copy h1 { font-size: 34px; } .login-main { padding: 20px; } .login-form { padding: 24px; } }
    </style>
</head>
<body>
    <div class="login-layout">
        <aside class="login-aside">
            <a class="admin-brand" href="../index.html">Trendy Store<span> / Admin</span></a>
            <div class="login-aside-copy">
                <p class="eyebrow">Store operations</p>
                <h1>Admin sign in</h1>
                <p>Use the administrator account stored in the `trendy_store` database.</p>
            </div>
            <a href="../index.html" style="color:#cbd6d0;font-size:12px;text-decoration:none;">Return to storefront</a>
        </aside>
        <main class="login-main">
            <form class="login-form" method="post" action="./login.php" autocomplete="on">
                <h2>Welcome back</h2>
                <p>Sign in with an account from the `admin_users` table.</p>
                <?php if ($errorMessage !== ''): ?>
                    <p class="login-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="email">Admin email</label>
                    <input id="email" name="email" type="email" autocomplete="username" required maxlength="150" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-entry">
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <button class="password-visibility" id="toggleAdminPassword" type="button" aria-controls="password" aria-pressed="false">Show</button>
                    </div>
                </div>
                <button class="button" type="submit"><i class="fas fa-lock" aria-hidden="true"></i> Sign in</button>
                <p class="login-note">The SQL dump stores only a password hash. If you do not know the existing password, run <code>.\admin\set-admin-password.ps1</code> from PowerShell to choose a new one.</p>
            </form>
        </main>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('toggleAdminPassword');
        passwordToggle.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            passwordToggle.textContent = showPassword ? 'Hide' : 'Show';
            passwordToggle.setAttribute('aria-pressed', String(showPassword));
        });
    </script>
</body>
</html>
