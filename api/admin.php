<?php
require_once 'db.php';
require_once __DIR__ . '/../admin/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");

function require_admin(): int {
    if (empty($_SESSION['admin_user']['id'])) {
        respond(false, 'Administrator login required.', null, 401);
    }
    return (int)$_SESSION['admin_user']['id'];
}

function admin_product_data(array $data): array {
    $name = trim($data['name'] ?? '');
    $category = trim($data['category'] ?? '');
    $price = (float)($data['price'] ?? 0);
    $image = trim($data['image'] ?? '');
    $description = trim($data['description'] ?? '');
    $sales = trim($data['sales'] ?? '0');
    $rating = trim($data['rating'] ?? '');
    $review = trim($data['review'] ?? '');
    $gallery = $data['gallery'] ?? [];

    if (!$name || !$category || $price < 0 || !$image) {
        respond(false, 'Name, category, non-negative price and image are required.', null, 422);
    }
    if (!is_array($gallery)) $gallery = [$image];
    return [$name, $category, $price, $sales, $rating, $image, $description, $review, json_encode($gallery)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_input();
    $action = $data['action'] ?? '';

    if ($action === 'login') {
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $stmt = $pdo->prepare('SELECT id, name, email, password FROM admin_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password'])) {
            respond(false, 'Invalid administrator email or password.', null, 401);
        }
        session_regenerate_id(true);
        $_SESSION['admin_user'] = [
            'id' => (int)$admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email']
        ];
        respond(true, 'Administrator login successful.', $_SESSION['admin_user']);
    }

    require_admin();

    if ($action === 'logout') {
        unset($_SESSION['admin_user'], $_SESSION['admin_csrf']);
        respond(true, 'Administrator logged out.');
    }

    if ($action === 'save_product') {
        [$name, $category, $price, $sales, $rating, $image, $description, $review, $gallery] = admin_product_data($data);
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE products SET name=?, category=?, price=?, sales=?, rating=?, image=?, description=?, review=?, gallery=? WHERE id=?');
            $stmt->execute([$name, $category, $price, $sales, $rating, $image, $description, $review, $gallery, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO products (name, category, price, sales, rating, image, description, review, gallery, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$name, $category, $price, $sales, $rating, $image, $description, $review, $gallery]);
            $id = (int)$pdo->lastInsertId();
        }
        respond(true, 'Product saved.', ['id' => $id]);
    }

    if ($action === 'delete_product') {
        $id = (int)($data['id'] ?? 0);
        if ($id < 1) respond(false, 'A valid product ID is required.', null, 422);
        $stmt = $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        respond(true, 'Product hidden from the storefront.');
    }

    if ($action === 'set_product_visibility') {
        $id = (int)($data['id'] ?? 0);
        $isActive = filter_var($data['isActive'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($id < 1 || $isActive === null) {
            respond(false, 'A valid product and visibility setting are required.', null, 422);
        }
        $stmt = $pdo->prepare('UPDATE products SET is_active = ? WHERE id = ?');
        $stmt->execute([$isActive ? 1 : 0, $id]);
        respond(true, $isActive ? 'Product published to the storefront.' : 'Product hidden from the storefront.');
    }

    if ($action === 'update_order_status') {
        $id = (int)($data['id'] ?? 0);
        $status = trim($data['status'] ?? '');
        $allowed = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
        if ($id < 1 || !in_array($status, $allowed, true)) respond(false, 'Invalid order status.', null, 422);
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        respond(true, 'Order status updated.');
    }

    if ($action === 'delete_user') {
        $id = (int)($data['id'] ?? 0);
        if ($id < 1) respond(false, 'A valid user ID is required.', null, 422);
        $orders = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
        $orders->execute([$id]);
        if ((int)$orders->fetchColumn() > 0) {
            respond(false, 'This account has order history and cannot be deleted.', null, 409);
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        respond(true, 'User deleted.');
    }

    if ($action === 'save_category') {
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 50) respond(false, 'Category name is required and must be 50 characters or fewer.', null, 422);
        try {
            $pdo->beginTransaction();
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $oldName = $stmt->fetchColumn();
                if (!$oldName) respond(false, 'Category not found.', null, 404);
                $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
                $stmt->execute([$name, $id]);
                $stmt = $pdo->prepare('UPDATE products SET category = ? WHERE category = ?');
                $stmt->execute([$name, $oldName]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
                $stmt->execute([$name]);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->commit();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int)$error->errorInfo[1] === 1062) respond(false, 'That category already exists.', null, 409);
            respond(false, 'Category could not be saved.', null, 500);
        }
        respond(true, 'Category saved.', ['id' => $id]);
    }

    if ($action === 'delete_category') {
        $id = (int)($data['id'] ?? 0);
        if ($id < 1) respond(false, 'A valid category ID is required.', null, 422);
        $stmt = $pdo->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        if (!$name) respond(false, 'Category not found.', null, 404);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
        $stmt->execute([$name]);
        if ((int)$stmt->fetchColumn() > 0) respond(false, 'Move or update products in this category before deleting it.', null, 409);
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        respond(true, 'Category deleted.');
    }

    respond(false, 'Unknown administrator action.', null, 400);
}

require_admin();

$section = $_GET['section'] ?? 'summary';
if ($section === 'summary') {
    $counts = [];
    foreach (['users', 'products', 'orders'] as $table) {
        $counts[$table] = $table === 'products'
            ? (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn()
            : (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }
    respond(true, 'Summary loaded.', $counts);
}
if ($section === 'users') {
    $stmt = $pdo->query('SELECT id, full_name AS fullName, email, mobile, created_at AS createdAt FROM users ORDER BY id DESC');
    respond(true, 'Users loaded.', $stmt->fetchAll());
}
if ($section === 'products') {
    $stmt = $pdo->query('SELECT id, name, category, price, sales, rating, image, description, review, gallery, is_active AS isActive FROM products ORDER BY id DESC');
    $products = $stmt->fetchAll();
    foreach ($products as &$product) {
        $product['id'] = (int)$product['id'];
        $product['price'] = (float)$product['price'];
        $product['isActive'] = (bool)$product['isActive'];
        $product['gallery'] = $product['gallery'] ? json_decode($product['gallery'], true) : [];
    }
    respond(true, 'Products loaded.', $products);
}
if ($section === 'categories') {
    $stmt = $pdo->query('SELECT id, name, created_at AS createdAt FROM categories ORDER BY name');
    respond(true, 'Categories loaded.', $stmt->fetchAll());
}
if ($section === 'orders') {
    $stmt = $pdo->query('SELECT id, user_id AS userId, full_name AS fullName, email, phone, city, state, total, status, created_at AS createdAt FROM orders ORDER BY id DESC');
    $orders = $stmt->fetchAll();
    foreach ($orders as &$order) $order['total'] = (float)$order['total'];
    respond(true, 'Orders loaded.', $orders);
}
respond(false, 'Unknown administrator section.', null, 400);
?>
