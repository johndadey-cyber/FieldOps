<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../_auth.php';
require_role('admin');
require_once __DIR__ . '/../../config/database.php';

$pdo = getPDO();
$st = $pdo->query(
    "SELECT jdl.job_id, jdl.user_id, jdl.reason, jdl.deleted_at, p.first_name, p.last_name
     FROM job_deletion_log jdl
     LEFT JOIN employees e ON e.id = jdl.user_id
     LEFT JOIN people p ON p.id = e.person_id
     ORDER BY jdl.deleted_at DESC"
);
$rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Job Deletion Log</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">
  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 m-0">Job Deletion Log</h1>
    <a href="index.php" class="btn btn-sm btn-secondary ms-auto">Back</a>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0">
        <thead class="table-light">
          <tr><th>Job ID</th><th>User</th><th>Reason</th><th>Deleted At</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $name = trim(((string)($r['first_name'] ?? '')) . ' ' . ((string)($r['last_name'] ?? '')));
            $userDisplay = $name !== '' ? $name : ((isset($r['user_id']) && $r['user_id'] !== null) ? (string)$r['user_id'] : '-');
          ?>
          <tr>
            <td><?= (int)$r['job_id'] ?></td>
            <td><?= s($userDisplay) ?></td>
            <td><?= s($r['reason'] ?? '') ?></td>
            <td><?= s($r['deleted_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
