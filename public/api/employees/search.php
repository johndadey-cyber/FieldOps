<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../models/EmployeeDataProvider.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q  = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));

try {
    $pdo = getPDO();

    // Fetch a single employee by ID
    if ($id > 0) {
        $sql = "SELECT e.id, CONCAT(p.first_name,' ',p.last_name) AS name FROM employees e JOIN people p ON p.id=e.person_id WHERE e.id=:id";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'item' => $row ? ['id' => (int)$row['id'], 'name' => $row['name']] : null], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (strlen($q) < 2) {
        echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $data = EmployeeDataProvider::getFiltered($pdo, null, 1, 20, null, null, $q);
    $rows = [];
    foreach ($data['rows'] as $r) {
        $rows[] = ['id' => (int)$r['employee_id'], 'name' => $r['first_name'] . ' ' . $r['last_name']];
    }
    echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => 'Database query failed'], JSON_UNESCAPED_SLASHES);
    exit;
}
