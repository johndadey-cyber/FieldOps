<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Customer.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$customer = null;
if ($id > 0) {
    $customerModel = new Customer($pdo); // instance method usage
    $customer = $customerModel->getById($id);
}

echo json_encode(
    ['ok' => (bool)$customer, 'customer' => $customer],
    JSON_UNESCAPED_SLASHES
);
