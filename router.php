<?php
declare(strict_types=1);

$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$protectedRoutes = [
    '/admin' => '/admin/login.php',
    '/admin/' => '/admin/login.php',
    '/admin/index.html' => '/admin/index.php',
    '/admin/manageproduct.html' => '/admin/manageproduct.php',
    '/admin/manageusers.html' => '/admin/manageusers.php',
    '/admin/manageorders.html' => '/admin/manageorders.php'
];

if (isset($protectedRoutes[$requestedPath])) {
    header('Location: ' . $protectedRoutes[$requestedPath], true, 302);
    exit;
}

return false;