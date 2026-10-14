<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (empty($_SESSION['admin_user']['id'])) {
    header('Location: ./login.php');
    exit;
}