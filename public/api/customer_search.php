<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/CustomerDataProvider.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$rows = [];
try {
    $pdo  = getPDO();
    $rows = CustomerDataProvider::getFiltered($pdo, $q, null, null, '10');
    $rows = array_map(static function (array $c): array {
        return [
            'id'            => (int)$c['id'],
            'first_name'    => $c['first_name'],
            'last_name'     => $c['last_name'],
            'address_line1' => $c['address_line1'] ?? '',
            'city'          => $c['city'] ?? '',
        ];
    }, $rows);
} catch (Throwable $e) {
    http_response_code(500);
}

echo json_encode($rows, JSON_UNESCAPED_SLASHES);
