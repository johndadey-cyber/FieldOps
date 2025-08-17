<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$eid = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$weekStart = isset($_GET['week_start']) ? (string)$_GET['week_start'] : '';

if ($eid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

$ws = new DateTimeImmutable($weekStart);
$we = $ws->modify('+6 days')->format('Y-m-d');

$stEmp = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM employees WHERE id = :id");
$stEmp->execute([':id'=>$eid]);
$empName = (string)$stEmp->fetchColumn();
if ($empName === '') {
    http_response_code(404);
    echo 'Employee not found';
    exit;
}

$dayOrderSql = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
$st = $pdo->prepare("SELECT day_of_week, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time FROM employee_availability WHERE employee_id=:eid ORDER BY {$dayOrderSql}, start_time");
$st->execute([':eid'=>$eid]);
$avail = $st->fetchAll(PDO::FETCH_ASSOC);

$st2 = $pdo->prepare("SELECT date, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time, status, reason FROM employee_availability_overrides WHERE employee_id=:eid AND date BETWEEN :ws AND :we ORDER BY date, start_time");
$st2->execute([':eid'=>$eid, ':ws'=>$ws->format('Y-m-d'), ':we'=>$we]);
$overrides = $st2->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Availability Print</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<style>body{padding:20px;}@media print{.no-print{display:none}}</style>
</head>
<body>
<div class="container">
  <h1 class="h4 mb-4">Availability for <?= htmlspecialchars($empName, ENT_QUOTES, 'UTF-8') ?></h1>
  <table class="table table-bordered">
    <thead>
      <tr><th>Day</th><th>Start</th><th>End</th><th>Status</th><th>Reason</th></tr>
    </thead>
    <tbody>
    <?php foreach ($avail as $a): ?>
      <tr>
        <td><?= htmlspecialchars((string)$a['day_of_week'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$a['start_time'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$a['end_time'], ENT_QUOTES, 'UTF-8') ?></td>
        <td></td>
        <td></td>
      </tr>
    <?php endforeach; ?>
    <?php foreach ($overrides as $ov): ?>
      <tr>
        <td><?= htmlspecialchars((string)$ov['date'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($ov['start_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($ov['end_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($ov['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($ov['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn btn-primary no-print" onclick="window.print()">Print</button>
</div>
</body>
</html>
