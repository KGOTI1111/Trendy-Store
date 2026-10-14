<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respond(false, 'GET request required.', null, 405);

$categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name');
$categories = $categoryStmt->fetchAll();

$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = 'SELECT id, name, category, price, sales, rating, image AS img, description AS `desc`, review, gallery FROM products';
$where = ['is_active = 1'];
$params = [];

if ($category && strtolower($category) !== 'all') {
    $where[] = 'category = ?';
    $params[] = $category;
}
if ($search !== '') {
    $where[] = '(name LIKE ? OR category LIKE ? OR description LIKE ? OR review LIKE ?)';
    $q = '%' . $search . '%';
    array_push($params, $q, $q, $q, $q);
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

foreach ($products as &$p) {
    $p['id'] = (int)$p['id'];
    $p['price'] = (float)$p['price'];
    $p['gallery'] = $p['gallery'] ? json_decode($p['gallery'], true) : [$p['img']];
}
unset($p);

respond(true, 'Products loaded.', $products);
?>
