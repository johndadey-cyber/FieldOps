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
  <style>
    body{padding-bottom:6rem}
    .action-bar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #dee2e6;padding:.75rem;z-index:1000}
    .action-bar .btn{padding:1rem;font-size:1.25rem}
    #btn-start-job.disabled{opacity:.65;pointer-events:auto}
  </style>
</head>
<body class="bg-light">
<div class="container py-3">
  <div id="network-banner" class="alert text-center small d-none"></div>
  <div id="status-banner" class="alert alert-secondary mb-3 d-none"></div>
  <button class="btn btn-primary mb-3 d-none" id="btn-start-job">Start Job</button>
  <div id="job-details" class="mb-4"></div>
  <div id="notes-section" class="mb-4">
    <h2 class="h6">Notes</h2>
    <div id="job-notes" class="small"></div>
  </div>
  <div id="photos-section" class="mb-4">
    <h2 class="h6">Photos</h2>
    <div id="job-photos" class="d-flex flex-wrap gap-2"></div>
  </div>
</div>
<div class="action-bar">
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary flex-fill btn-lg" id="btn-add-note">Add Note</button>
    <button class="btn btn-outline-secondary flex-fill btn-lg" id="btn-add-photo">Add Photo</button>
    <button class="btn btn-outline-secondary flex-fill btn-lg" id="btn-checklist">Checklist</button>
    <button class="btn btn-success flex-fill btn-lg d-none" id="btn-complete">Mark as Complete</button>
  </div>
</div>
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
