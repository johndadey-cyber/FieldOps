<?php
// /public/tech_jobs.php
// Daily job list for field technicians

declare(strict_types=1);

require __DIR__ . '/_cli_guard.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_csrf.php';

require_auth();
require_role('tech');

$csrf = csrf_token();
$techId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Today's Jobs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom sticky-top">
  <div class="container-fluid align-items-center">
    <button class="navbar-toggler touch-target focus-ring" type="button" id="menu-button" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <span class="navbar-brand mx-auto">FieldOps</span>
    <div class="ms-auto">
      <span class="rounded-circle bg-secondary d-block avatar-placeholder"></span>
    </div>
  </div>
  <div class="bg-light w-100 text-center small py-1" id="date-banner"></div>
</nav>
<div id="network-banner" class="alert text-center small d-none mb-0"></div>
<div class="container py-3">
  <div class="mb-3">
    <a href="/add_job.php" class="btn btn-primary w-100 touch-target focus-ring" id="btn-start-job">+ Start New Job</a>
  </div>
  <div id="jobs-list"></div>
</div>
<script>
  window.CSRF_TOKEN = "<?=htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')?>";
  window.TECH_ID = <?= $techId ?>;
  window.TODAY = "<?= $today ?>";
  window.TODAY_HUMAN = "<?= date('l, F j') ?>";
</script>
<script>
if('serviceWorker' in navigator){navigator.serviceWorker.register('/service-worker.js');}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/offline.js"></script>
<script src="/js/tech_jobs.js?v=<?=date('Ymd')?>"></script>
</body>
</html>
