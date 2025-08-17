<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';


header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q  = trim((string)($_GET['q'] ?? ''));

try {
    $pdo = getPDO();
    // Optional filter by is_active if column exists
    $hasIsActive = false;
    try {
        $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='is_active' LIMIT 1");
        $hasIsActive = (bool)($chk ? $chk->fetchColumn() : false);
    } catch (Throwable $e) {
        $hasIsActive = false;
    }

    if ($id > 0) {
        $sql = "SELECT e.id, CONCAT(p.first_name,' ',p.last_name) AS name FROM employees e JOIN people p ON p.id=e.person_id WHERE e.id=:id";
        if ($hasIsActive) { $sql .= " AND e.is_active=1"; }
        $st = $pdo->prepare($sql);
        $st->execute([':id'=>$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ? ['id'=>(int)$row['id'], 'name'=>$row['name']] : new stdClass(), JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $sql = "SELECT e.id, CONCAT(p.first_name,' ',p.last_name) AS name FROM employees e JOIN people p ON p.id=e.person_id WHERE p.first_name LIKE :q OR p.last_name LIKE :q";
    if ($hasIsActive) { $sql .= " AND e.is_active=1"; }
    $sql .= " ORDER BY p.last_name, p.first_name LIMIT 20";
    $st = $pdo->prepare($sql);
    $st->execute([':q'=>'%'.$q.'%']);
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = ['id'=>(int)$r['id'], 'name'=>$r['name']];
    }
    echo json_encode($rows, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // On any DB error return an empty result set instead of a 500 error
    error_log($e->getMessage());
    http_response_code(200);
    echo json_encode($id > 0 ? new stdClass() : []);
    exit;
}
