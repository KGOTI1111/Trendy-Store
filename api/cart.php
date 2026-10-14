<?php
require_once 'db.php';
$userId = require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('
        SELECT c.product_id AS id, c.quantity,
               p.name, p.category, p.price, p.sales, p.rating,
               p.image AS img, p.description AS `desc`, p.review, p.gallery
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.user_id = ?
        ORDER BY c.id DESC
    ');
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['quantity'] = (int)$item['quantity'];
        $item['price'] = (float)$item['price'];
        $item['gallery'] = $item['gallery'] ? json_decode($item['gallery'], true) : [$item['img']];
    }
    unset($item);
    respond(true, 'Cart loaded.', $items);
}

$data = json_input();
$action = $data['action'] ?? '';

if ($action === 'add') {
    $productId = (int)($data['productId'] ?? 0);
    $quantity = max(1, (int)($data['quantity'] ?? 1));
    $check = $pdo->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1');
    $check->execute([$productId]);
    if (!$check->fetch()) respond(false, 'Product not found.', null, 404);

    $stmt = $pdo->prepare('
        INSERT INTO cart (user_id, product_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ');
    $stmt->execute([$userId, $productId, $quantity]);
    respond(true, 'Product added to cart.');
}

if ($action === 'remove') {
    $productId = (int)($data['productId'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    respond(true, 'Product removed from cart.');
}

if ($action === 'clear') {
    $stmt = $pdo->prepare('DELETE FROM cart WHERE user_id = ?');
    $stmt->execute([$userId]);
    respond(true, 'Cart cleared.');
}

if ($action === 'setQuantity') {
    $productId = (int)($data['productId'] ?? 0);
    $quantity = max(1, (int)($data['quantity'] ?? 1));
    $stmt = $pdo->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$quantity, $userId, $productId]);
    respond(true, 'Cart updated.');
}

respond(false, 'Unknown cart action.', null, 400);
?>
