<?php
declare(strict_types=1);
require_once __DIR__ . '/db-connection.php';

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function valid_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['message' => 'Method not allowed.']);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['message' => 'Invalid request.']);
}

$action = (string) ($input['action'] ?? '');
try {
    $database = store_database();

    if ($action === 'logout') {
        unset($_SESSION['store_user'], $_SESSION['user_id']);
        session_regenerate_id(true);
        respond(200, ['message' => 'Signed out.']);
    }

    if ($action === 'register') {
        $fullName = trim((string) ($input['fullName'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100) {
            respond(422, ['message' => 'Name must be between 3 and 100 characters.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            respond(422, ['message' => 'Enter a valid email address.']);
        }
        if (!valid_password($password)) {
            respond(422, ['message' => 'Password needs 8+ characters, an uppercase letter, a number, and a special character.']);
        }
        $statement = $database->prepare('INSERT INTO users (full_name, email, mobile, password) VALUES (:full_name, :email, :mobile, :password)');
        try {
            $statement->execute([
                'full_name' => $fullName,
                'email' => $email,
                'mobile' => '',
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                respond(409, ['message' => 'An account with this email already exists. Sign in instead.']);
            }
            throw $error;
        }
        $user = ['id' => (int) $database->lastInsertId(), 'fullName' => $fullName, 'email' => $email, 'mobile' => '', 'hasPassword' => true];
        $_SESSION['store_user'] = $user;
        respond(201, ['user' => $user, 'message' => 'Account created.']);
    }

    if ($action === 'login') {
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            respond(422, ['message' => 'Enter your email and password.']);
        }

        $adminStatement = $database->prepare('SELECT id, name, email, password FROM admin_users WHERE email = :email LIMIT 1');
        $adminStatement->execute(['email' => $email]);
        $adminRecord = $adminStatement->fetch();
        if ($adminRecord && password_verify($password, $adminRecord['password'])) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_name('TRENDY_ADMIN');
            session_start();
            session_regenerate_id(true);
            unset($_SESSION['store_user']);
            $_SESSION['admin_user'] = [
                'id' => (int) $adminRecord['id'],
                'name' => $adminRecord['name'],
                'email' => $adminRecord['email']
            ];
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            respond(200, [
                'role' => 'admin',
                'redirectUrl' => './admin/index.php',
                'message' => 'Admin sign in successful.'
            ]);
        }

        $statement = $database->prepare('SELECT id, full_name, email, mobile, password FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $record = $statement->fetch();
        if (!$record || !password_verify($password, $record['password'])) {
            respond(401, ['message' => 'Email or password is incorrect.']);
        }
        session_regenerate_id(true);
        $user = [
            'id' => (int) $record['id'],
            'fullName' => $record['full_name'],
            'email' => $record['email'],
            'mobile' => $record['mobile'],
            'hasPassword' => true
        ];
        $_SESSION['store_user'] = $user;
        respond(200, [
            'role' => 'user',
            'redirectUrl' => './index.html',
            'user' => $user,
            'message' => 'Signed in.'
        ]);
    }

    if ($action === 'update-profile') {
        $currentUser = $_SESSION['store_user'] ?? null;
        if (!$currentUser || empty($currentUser['id'])) {
            respond(401, ['message' => 'Sign in to update your profile.']);
        }
        $fullName = trim((string) ($input['fullName'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            respond(422, ['message' => 'Enter a valid name and email address.']);
        }
        if ($password !== '' && !valid_password($password)) {
            respond(422, ['message' => 'Password needs 8+ characters, an uppercase letter, a number, and a special character.']);
        }
        $parameters = ['full_name' => $fullName, 'email' => $email, 'id' => (int) $currentUser['id']];
        $sql = 'UPDATE users SET full_name = :full_name, email = :email WHERE id = :id';
        if ($password !== '') {
            $sql = 'UPDATE users SET full_name = :full_name, email = :email, password = :password WHERE id = :id';
            $parameters['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        try {
            $database->prepare($sql)->execute($parameters);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                respond(409, ['message' => 'An account with this email already exists.']);
            }
            throw $error;
        }
        $user = ['id' => (int) $currentUser['id'], 'fullName' => $fullName, 'email' => $email, 'mobile' => $currentUser['mobile'] ?? '', 'hasPassword' => true];
        $_SESSION['store_user'] = $user;
        respond(200, ['user' => $user, 'message' => 'Profile updated.']);
    }

    respond(400, ['message' => 'Unknown account action.']);
} catch (Throwable $error) {
    error_log('User authentication request failed: ' . $error->getMessage());
    respond(500, ['message' => 'The account service is unavailable. Check the database connection.']);
}
