<?php
declare(strict_types=1);

/**
 * DELETE /api/availability/override_delete.php?id=123
 * Remove an override by id.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_id']);
    exit;
}

$eidRow = $pdo->prepare('SELECT employee_id FROM employee_availability_overrides WHERE id = :id');
$eidRow->execute([':id'=>$id]);
$empId = (int)$eidRow->fetchColumn();

$st = $pdo->prepare('DELETE FROM employee_availability_overrides WHERE id = :id');
$st->execute([':id'=>$id]);


try {
    $uid = $_SESSION['user']['id'] ?? null;
    $det = json_encode(['id'=>$id], JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid, :uid, :act, :det)')
        ->execute([':eid'=>$empId, ':uid'=>$uid, ':act'=>'override_delete', ':det'=>$det]);
} catch (Throwable $e) {
    // ignore audit errors
}
echo json_encode(['ok'=>true,'deleted'=>$st->rowCount()]);

