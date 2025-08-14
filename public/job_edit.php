<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_token'];

$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if ($role !== 'dispatcher') { http_response_code(403); echo "Forbidden"; exit; }

require __DIR__ . '/../config/database.php';
$pdo = getPDO(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo "Bad id"; exit; }

$st = $pdo->prepare("
  SELECT j.id, j.customer_id, j.description, j.scheduled_date, j.scheduled_time,
         j.duration_minutes, j.status,
         c.first_name, c.last_name
  FROM jobs j
  LEFT JOIN customers c ON c.id=j.customer_id
  WHERE j.id=:id
");
$st->execute([':id'=>$id]);
$job = $st->fetch(PDO::FETCH_ASSOC);
if (!$job) { http_response_code(404); echo "Job not found"; exit; }

$statuses = ['draft','scheduled','assigned','in_progress','completed','closed','cancelled'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Job #<?= htmlspecialchars((string)$id,ENT_QUOTES) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="h4 mb-3">Edit Job #<?= htmlspecialchars((string)$id,ENT_QUOTES) ?></h1>
  <form id="jobForm" method="post" action="/job_save.php?__return=json" class="card p-3">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF,ENT_QUOTES) ?>">
    <input type="hidden" name="job_id" value="<?= htmlspecialchars((string)$id,ENT_QUOTES) ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Customer ID</label>
        <input type="number" class="form-control" name="customer_id" value="<?= (int)$job['customer_id'] ?>" required>
        <div class="form-text">Current: <?= htmlspecialchars(trim(($job['first_name']??'').' '.($job['last_name']??'')),ENT_QUOTES) ?></div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= ($job['status']===$s?'selected':'') ?>><?= str_replace('_',' ',$s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-12">
        <label class="form-label">Description</label>
        <input type="text" class="form-control" name="description" value="<?= htmlspecialchars((string)$job['description'],ENT_QUOTES) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Date (YYYY-MM-DD)</label>
        <input type="date" class="form-control" name="scheduled_date" value="<?= htmlspecialchars((string)$job['scheduled_date'],ENT_QUOTES) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Time (HH:MM:SS)</label>
        <input type="time" class="form-control" step="1" name="scheduled_time" value="<?= htmlspecialchars((string)$job['scheduled_time'],ENT_QUOTES) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Duration (minutes)</label>
        <input type="number" class="form-control" name="duration_minutes" value="<?= (int)$job['duration_minutes'] ?>" min="1" required>
      </div>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-outline-secondary" href="/jobs.php">Back</a>
    </div>
  </form>

  <div id="alert" class="alert d-none mt-3"></div>
</div>
<script>
document.getElementById('jobForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.currentTarget;
  const data = new URLSearchParams(new FormData(form));
  const r = await fetch(form.action, {method:'POST', credentials:'include',
    headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},
    body: data.toString()
  });
  const j = await r.json();
  const a = document.getElementById('alert');
  if (j.ok) {
    a.className='alert alert-success'; a.textContent='Saved.'; a.classList.remove('d-none');
    setTimeout(()=>{ window.location.href='/jobs.php'; }, 800);
  } else {
    a.className='alert alert-danger'; a.textContent=(j.error||'Error'); a.classList.remove('d-none');
  }
});
</script>
</body>
</html>
