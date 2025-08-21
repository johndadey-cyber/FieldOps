<?php
// /public/tech_jobs.php
// Daily job list for field technicians

declare(strict_types=1);

require __DIR__ . '/_cli_guard.php';
require __DIR__ . '/_csrf.php';

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
  <style>
    .btn,.navbar-toggler{min-width:44px;min-height:44px}
    button:focus-visible,a:focus-visible{outline:2px solid #0d6efd;outline-offset:2px}
    .fab{z-index:1030}
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white border-bottom sticky-top">
  <div class="container-fluid align-items-center">
    <button class="navbar-toggler" type="button" id="menu-button" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <span class="navbar-brand mx-auto">FieldOps</span>
    <div class="ms-auto">
      <span class="rounded-circle bg-secondary d-block" style="width:40px;height:40px;"></span>
    </div>
  </div>
  <div class="bg-light w-100 text-center small py-1" id="date-banner"></div>
</nav>
<div id="network-banner" class="alert text-center small d-none mb-0"></div>
<div class="container py-3">
  <div class="mb-3">
    <a href="/add_job.php" class="btn btn-primary w-100" id="btn-start-job">+ Start New Job</a>
  </div>
  <div id="jobs-list"></div>
</div>
<div class="fab position-fixed bottom-0 end-0 mb-4 me-4">
  <div class="btn-group-vertical align-items-end">
    <div class="collapse mb-2" id="fab-actions">
      <button class="btn btn-light rounded-circle mb-2 shadow d-flex align-items-center justify-content-center" id="btn-add-note" aria-label="Add note" style="width:44px;height:44px;">📝</button>
      <button class="btn btn-light rounded-circle mb-2 shadow d-flex align-items-center justify-content-center" id="btn-add-photo" aria-label="Add photo" style="width:44px;height:44px;">📷</button>
      <button class="btn btn-light rounded-circle shadow d-flex align-items-center justify-content-center" id="btn-map-view" aria-label="Map view" style="width:44px;height:44px;">🗺️</button>
    </div>
    <button class="btn btn-primary rounded-circle shadow d-flex align-items-center justify-content-center" data-bs-toggle="collapse" data-bs-target="#fab-actions" aria-expanded="false" aria-label="Toggle actions" style="width:56px;height:56px;">+</button>
  </div>
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
