<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../partials/assignments_toast_hook.php'; ?>


$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$st = $pdo->prepare("
    SELECT a.id, a.job_id, a.employee_id, a.assigned_at,
           p.first_name, p.last_name
    FROM job_employee_assignment a
    JOIN employees e ON e.id = a.employee_id
    LEFT JOIN people p ON p.id = e.person_id
    ORDER BY a.assigned_at DESC
");
if ($st === false) {
    $rows = [];
} else {
    $st->execute();
    /** @var list<array<string,mixed>> $rows */
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
