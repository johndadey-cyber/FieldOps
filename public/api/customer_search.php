<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$rows = [];
try {
    $pdo = getPDO();
    $needle = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q) . '%';
    $sql = "SELECT id, first_name, last_name, address_line1, city FROM customers WHERE first_name LIKE :q OR last_name LIKE :q OR address_line1 LIKE :q OR city LIKE :q ORDER BY last_name, first_name LIMIT 10";
    $st = $pdo->prepare($sql);
    $st->execute([':q' => $needle]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    http_response_code(500);
}

echo json_encode($rows, JSON_UNESCAPED_SLASHES);
