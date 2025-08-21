<?php
// /public/tech_job.php
// Job detail view for technicians

declare(strict_types=1);

require __DIR__ . '/_cli_guard.php';
require __DIR__ . '/_csrf.php';

$csrf   = csrf_token();
$techId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
$jobId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Job Details</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/css/app.css" rel="stylesheet">
</head>
<body class="bg-light has-sticky-bar">
<div class="container py-3">
  <div id="network-banner" class="alert text-center small d-none"></div>
  <header id="job-header" class="mb-3"></header>
  <section id="customer-info" class="mb-4"></section>
  <button class="btn btn-primary mb-3 d-none touch-target focus-ring" id="btn-start-job" aria-label="Start job">Start Job</button>
  <section id="timer-section" class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <div id="job-timer" class="fw-bold">00:00:00</div>
      <button class="btn btn-link p-0 touch-target focus-ring" id="checklist-toggle" data-bs-toggle="collapse" data-bs-target="#checklist-collapse" aria-expanded="false" aria-controls="checklist-collapse">Checklist</button>
    </div>
    <div class="collapse" id="checklist-collapse">
      <div class="progress mb-2">
        <div id="checklist-progress" class="progress-bar w-0" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
      </div>
      <ul id="checklist" class="list-unstyled mb-0"></ul>
    </div>
  </section>
  <section id="notes-section" class="mb-4">
    <h2 class="h6">Notes</h2>
    <div id="job-notes" class="small"></div>
  </section>
  <section id="photos-section" class="mb-4">
    <h2 class="h6">Photos</h2>
    <div id="job-photos" class="d-flex flex-wrap gap-2"></div>
  </section>
</div>
<nav class="sticky-bar">
  <div class="d-flex justify-content-around">
    <button class="btn btn-outline-secondary flex-fill touch-target focus-ring" id="menu-checklist" aria-label="Toggle checklist">Checklist</button>
    <button class="btn btn-outline-secondary flex-fill touch-target focus-ring" id="menu-note" aria-label="Add note">Note</button>
    <button class="btn btn-outline-secondary flex-fill touch-target focus-ring" id="menu-camera" aria-label="Add photo">Camera</button>
    <a class="btn btn-outline-secondary flex-fill touch-target focus-ring" id="menu-map" target="_blank" rel="noopener" aria-label="Open map">Map</a>
    <button class="btn btn-success flex-fill d-none touch-target focus-ring" id="btn-complete" aria-label="Mark job as complete">Complete</button>
  </div>
</nav>
<script>
  window.CSRF_TOKEN = "<?=htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')?>";
  window.TECH_ID = <?= $techId ?>;
  window.JOB_ID = <?= $jobId ?>;
</script>
<script>
if('serviceWorker' in navigator){navigator.serviceWorker.register('/service-worker.js');}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/offline.js"></script>
<script src="/js/tech_job.js?v=<?=date('Ymd')?>"></script>
</body>
</html>
