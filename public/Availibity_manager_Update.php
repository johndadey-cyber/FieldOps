<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';

/** HTML escape */
function s(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$pdo = getPDO();
$q = trim((string)($_GET['q'] ?? ''));
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$employees = [];
$schedule = [];
$employeeName = '';

if ($q !== '' && $employeeId === 0) {
    $st = $pdo->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM employees WHERE CONCAT(first_name,' ',last_name) LIKE :q ORDER BY name LIMIT 20");
    $st->execute([':q' => "%$q%"]);
    $employees = $st->fetchAll(PDO::FETCH_ASSOC);
}

if ($employeeId > 0) {
    $nameSt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM employees WHERE id = :id");
    $nameSt->execute([':id' => $employeeId]);
    $employeeName = (string)$nameSt->fetchColumn();

    $st = $pdo->prepare(
        "SELECT j.scheduled_date, j.scheduled_time
         FROM jobs j
         JOIN job_employee_assignment a ON a.job_id = j.id
         WHERE a.employee_id = :eid
           AND j.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY j.scheduled_date, j.scheduled_time"
    );
    $st->execute([':eid' => $employeeId]);
    $schedule = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Availability Manager Update</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h1 class="h4 mb-3">Availability Manager Update</h1>
  <form method="get" class="mb-3">
    <div class="input-group">
      <input type="search" name="q" value="<?= s($q) ?>" class="form-control" placeholder="Search employees by name">
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
  </form>

  <?php if ($employeeId === 0): ?>
    <?php if ($q !== ''): ?>
      <?php if ($employees): ?>
        <ul class="list-group mb-3">
          <?php foreach ($employees as $emp): ?>
            <li class="list-group-item">
              <a href="?employee_id=<?= (int)$emp['id'] ?>"><?= s($emp['name']) ?> (ID <?= (int)$emp['id'] ?>)</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-muted">No employees found.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php else: ?>
    <h2 class="h5">Schedule for <?= s($employeeName) ?> (ID <?= $employeeId ?>)</h2>
    <?php if ($schedule): ?>
      <ul class="list-group">
        <?php foreach ($schedule as $row): ?>
          <li class="list-group-item">
            <?= s($row['scheduled_date']) ?> at <?= s(substr((string)$row['scheduled_time'],0,5)) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-muted">No scheduled jobs for the next week.</p>
    <?php endif; ?>
    <p class="mt-3"><a href="?">&#8592; Back to search</a></p>
  <?php endif; ?>
</body>
</html>
