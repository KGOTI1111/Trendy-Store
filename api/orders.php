<?php
require_once 'db.php';
$userId = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'GET request required.', null, 405);
}

$statement = $pdo->prepare('
    SELECT
        o.id,
        o.full_name AS fullName,
        o.email,
        o.phone,
        o.address,
        o.area,
        o.city,
        o.state,
        o.zipcode,
        o.country,
        o.shipping,
        o.payment_method AS paymentMethod,
        o.total,
        o.status,
        o.created_at AS date,
        oi.quantity,
        oi.price,
        p.name AS productName,
        p.image AS productImage
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC, o.id DESC, oi.id
');
$statement->execute([$userId]);
$rows = $statement->fetchAll();
$orders = [];

foreach ($rows as $row) {
    $orderId = (int)$row['id'];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'id' => $orderId,
            'date' => $row['date'],
            'status' => $row['status'] ?: 'Pending',
            'total' => (float)$row['total'],
            'shipping' => $row['shipping'],
            'paymentMethod' => $row['paymentMethod'],
            'delivery' => [
                'name' => $row['fullName'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'address' => $row['address'],
                'area' => $row['area'],
                'city' => $row['city'],
                'state' => $row['state'],
                'zipcode' => $row['zipcode'],
                'country' => $row['country']
            ],
            'items' => [],
            'subtotal' => 0.0
        ];
    }

    if ($row['quantity'] !== null) {
        $quantity = (int)$row['quantity'];
        $price = (float)$row['price'];
        $orders[$orderId]['items'][] = [
            'name' => $row['productName'] ?: 'Product no longer available',
            'img' => $row['productImage'] ?: '',
            'quantity' => $quantity,
            'price' => $price
        ];
        $orders[$orderId]['subtotal'] += $price * $quantity;
    }
}

foreach ($orders as &$order) {
    $shippingCost = $order['shipping'] === 'express' ? 150.0 : 0.0;
    $order['shippingCost'] = $shippingCost;
    $order['tax'] = round($order['subtotal'] * 0.05, 2);
}
unset($order);

respond(true, 'Orders loaded.', array_values($orders));
?>
