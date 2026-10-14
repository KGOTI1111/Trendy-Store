<?php
require_once 'db.php';
$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('
        SELECT w.product_id AS id, p.name, p.category, p.price, p.sales, p.rating,
               p.image AS img, p.description AS `desc`, p.review, p.gallery
        FROM wishlist w
        JOIN products p ON p.id = w.product_id
        WHERE w.user_id = ?
        ORDER BY w.id DESC
    ');
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['price'] = (float)$item['price'];
        $item['gallery'] = $item['gallery'] ? json_decode($item['gallery'], true) : [$item['img']];
    }
    unset($item);
    respond(true, 'Wishlist loaded.', $items);
}

$data = json_input();
$action = $data['action'] ?? '';
$productId = (int)($data['productId'] ?? 0);

if ($action === 'add') {
    $stmt = $pdo->prepare('INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)');
    $stmt->execute([$userId, $productId]);
    respond(true, 'Product added to wishlist.');
}
if ($action === 'remove') {
    $stmt = $pdo->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    respond(true, 'Product removed from wishlist.');
}

respond(false, 'Unknown wishlist action.', null, 400);
?>
