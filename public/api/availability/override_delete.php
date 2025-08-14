<?php
declare(strict_types=1);

/**
 * DELETE /api/availability/override_delete.php?id=123
 * Remove an override by id.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_id']);
    exit;
}

$st = $pdo->prepare('DELETE FROM employee_availability_overrides WHERE id = :id');
$st->execute([':id'=>$id]);

echo json_encode(['ok'=>true,'deleted'=>$st->rowCount()]);

