<?php
declare(strict_types=1);
require_once __DIR__ . '/guard.php';

if (empty($_SESSION['admin_csrf'])) {
	$_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$database = admin_database();
$message = '';
$errorMessage = '';
$selectedUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!hash_equals($_SESSION['admin_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
		$errorMessage = 'Your session expired. Refresh the page and try again.';
	} else {
		$action = (string) ($_POST['action'] ?? '');
		$userId = (int) ($_POST['user_id'] ?? 0);
		$name = trim((string) ($_POST['full_name'] ?? ''));
		$email = trim((string) ($_POST['email'] ?? ''));
		$mobile = trim((string) ($_POST['mobile'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');

		try {
			if ($action === 'create' || $action === 'update') {
				if (strlen($name) < 3 || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || strlen($mobile) > 20) {
					throw new InvalidArgumentException('Enter a valid name, email, and phone number.');
				}
				if ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password))) {
					throw new InvalidArgumentException('Passwords need 8+ characters, an uppercase letter, a number, and a symbol.');
				}

				if ($action === 'create') {
					if ($password === '') throw new InvalidArgumentException('A password is required when creating an account.');
					$statement = $database->prepare('INSERT INTO users (full_name, email, mobile, password) VALUES (:full_name, :email, :mobile, :password)');
					$statement->execute(['full_name' => $name, 'email' => $email, 'mobile' => $mobile, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
					$message = 'User account created.';
				} else {
					if ($userId < 1) throw new InvalidArgumentException('Choose a valid user record.');
					if ($password === '') {
						$statement = $database->prepare('UPDATE users SET full_name = :full_name, email = :email, mobile = :mobile WHERE id = :id');
						$statement->execute(['full_name' => $name, 'email' => $email, 'mobile' => $mobile, 'id' => $userId]);
					} else {
						$statement = $database->prepare('UPDATE users SET full_name = :full_name, email = :email, mobile = :mobile, password = :password WHERE id = :id');
						$statement->execute(['full_name' => $name, 'email' => $email, 'mobile' => $mobile, 'password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
					}
					$message = 'User account updated.';
				}
			} elseif ($action === 'delete') {
				if ($userId < 1) throw new InvalidArgumentException('Choose a valid user record.');
				$orders = $database->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
				$orders->execute(['user_id' => $userId]);
				if ((int) $orders->fetchColumn() > 0) {
					throw new InvalidArgumentException('This account has order history and cannot be deleted.');
				}
				$database->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
				$message = 'User account deleted.';
			}
		} catch (InvalidArgumentException $error) {
			$errorMessage = $error->getMessage();
		} catch (PDOException $error) {
			if ($error->getCode() === '23000') {
				$errorMessage = 'An account with this email already exists.';
			} else {
				error_log('User management request failed: ' . $error->getMessage());
				$errorMessage = 'Could not update the user account.';
			}
		}
	}
}

if (isset($_GET['edit'])) {
	$statement = $database->prepare('SELECT id, full_name, email, mobile FROM users WHERE id = :id');
	$statement->execute(['id' => (int) $_GET['edit']]);
	$selectedUser = $statement->fetch() ?: null;
}

$users = $database->query('SELECT id, full_name, email, mobile, created_at FROM users ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Trendy Store | Manage Users</title>
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
			<a href="./manageusers.php" aria-current="page">Users</a>
			<a href="./manageorders.php">Orders</a>
			<a href="./manageadmins.php">Admins</a>
			<a href="../index.html">Storefront</a>
			<a href="./logout.php">Sign out</a>
		</nav>
	</header>
	<main class="admin-main">
		<div class="page-heading">
			<div><p class="eyebrow">Accounts</p><h1>Manage users</h1><p>Manage customer accounts stored in MySQL. Passwords are hashed and never displayed.</p></div>
		</div>
		<p class="notice"><strong>Account deletion:</strong> Users with SQL order history cannot be deleted, preserving their order records.</p>
		<?php if ($errorMessage !== ''): ?><p class="login-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
		<?php if ($message !== ''): ?><p class="inline-status" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
		<?php if ($selectedUser): ?>
			<form class="editor" method="post" action="./manageusers.php?edit=<?= (int) $selectedUser['id'] ?>" autocomplete="off">
				<h2>Edit user account</h2>
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
				<input type="hidden" name="action" value="update">
				<input type="hidden" name="user_id" value="<?php (int) $selectedUser['id'] ?>">
				<div class="form-grid">
					<div class="form-group"><label for="fullName">Full name</label><input id="fullName" name="full_name" required minlength="3" maxlength="100" value="<?= htmlspecialchars($selectedUser['full_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
					<div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150" value="<?= htmlspecialchars($selectedUser['email'], ENT_QUOTES, 'UTF-8') ?>"></div>
					<div class="form-group"><label for="mobile">Phone</label><input id="mobile" name="mobile" maxlength="20" value="<?= htmlspecialchars($selectedUser['mobile'], ENT_QUOTES, 'UTF-8') ?>"></div>
					<div class="form-group"><label for="password">New password (optional)</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8"></div>
				</div>
				<div class="form-actions"><button class="button" type="submit">Save changes</button><a class="button button-secondary" href="./manageusers.php">Cancel</a></div>
			</form>
		<?php else: ?>
			<form class="editor" method="post" action="./manageusers.php" autocomplete="off">
				<h2>Create user account</h2>
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
				<input type="hidden" name="action" value="create">
				<div class="form-grid">
					<div class="form-group"><label for="fullName">Full name</label><input id="fullName" name="full_name" required minlength="3" maxlength="100"></div>
					<div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150"></div>
					<div class="form-group"><label for="mobile">Phone</label><input id="mobile" name="mobile" maxlength="20"></div>
					<div class="form-group"><label for="password">Temporary password</label><input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"></div>
				</div>
				<div class="form-actions"><button class="button" type="submit">Create user</button></div>
			</form>
		<?php endif; ?>
		<div class="section-heading"><h2>Customer accounts</h2><span><?= count($users) ?> users</span></div>
		<div class="table-wrap">
			<table><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
			<?php foreach ($users as $user): ?>
				<tr>
					<td><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars($user['mobile'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
					<td><div class="row-actions"><a class="button button-secondary" href="./manageusers.php?edit=<?= (int) $user['id'] ?>">Edit</a><form method="post" action="./manageusers.php" onsubmit="return confirm('Delete this user account? Accounts with order history are protected.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><button class="button button-danger" type="submit">Delete</button></form></div></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php if (!$users): ?><p class="empty-state">No customer accounts found.</p><?php endif; ?>
		</div>
	</main>
</body>
</html>
