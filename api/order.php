<?php
require_once 'db.php';
$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST request required.', null, 405);

$data = json_input();
$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) respond(false, 'Your order has no products.', null, 422);

$fullName = trim($data['fullName'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');
$area = trim($data['area'] ?? '');
$city = trim($data['city'] ?? '');
$state = trim($data['state'] ?? '');
$zipcode = trim($data['zipcode'] ?? '');
$country = trim($data['country'] ?? '');
$shipping = trim($data['shipping'] ?? 'standard');
$paymentMethod = trim($data['paymentMethod'] ?? 'cod');

if (!$fullName || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone || !$address || !$area || !$city || !$state || !$zipcode || !$country) {
    respond(false, 'Please complete all required delivery fields.', null, 422);
}

try {
    $pdo->beginTransaction();

    $productStmt = $pdo->prepare('SELECT id, price FROM products WHERE id = ? AND is_active = 1');
    $verifiedItems = [];
    $subtotal = 0;

    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        if (!$product) throw new Exception('One of the selected products no longer exists.');

        $price = (float)$product['price'];
        $subtotal += $price * $quantity;
        $verifiedItems[] = ['id'=>$productId, 'quantity'=>$quantity, 'price'=>$price];
    }

    $shippingCost = $shipping === 'express' ? 150 : 0;
    $tax = round($subtotal * 0.05, 2);
    $total = $subtotal + $shippingCost + $tax;

    $stmt = $pdo->prepare('
        INSERT INTO orders
        (user_id, full_name, email, phone, address, area, city, state, zipcode, country, shipping, payment_method, total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId, $fullName, $email, $phone, $address, $area, $city, $state,
        $zipcode, $country, $shipping, $paymentMethod, $total
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
    foreach ($verifiedItems as $item) {
        $itemStmt->execute([$orderId, $item['id'], $item['quantity'], $item['price']]);
    }

    $clear = $pdo->prepare('DELETE FROM cart WHERE user_id = ?');
    $clear->execute([$userId]);

    $pdo->commit();

    respond(true, 'Order placed successfully.', [
        'orderId' => $orderId,
        'subtotal' => $subtotal,
        'shipping' => $shippingCost,
        'tax' => $tax,
        'total' => $total
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(false, 'Unable to place the order.', null, 500);
}
?>
