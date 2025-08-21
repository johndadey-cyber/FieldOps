<?php
// /public/tech_job_complete.php
// Completion form for technicians

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
  <title>Complete Job</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/css/app.css" rel="stylesheet">
</head>
<body class="bg-light has-sticky-bar">
<div class="container py-3">
  <div id="network-banner" class="alert text-center small d-none"></div>
  <div class="mb-3">
    <label for="final-note" class="form-label">Summary Note</label>
    <div class="input-group">
      <textarea class="form-control" id="final-note" rows="4"></textarea>
      <button class="btn btn-outline-secondary touch-target focus-ring" id="btn-voice-note" type="button" aria-label="Voice input">🎤</button>
    </div>
    <div class="invalid-feedback"></div>
  </div>
  <div class="mb-3">
    <label class="form-label d-block">Final Photos</label>
    <small class="text-muted d-block mb-1">Tap "Add Photos" to include required images.</small>
    <div id="photo-list" class="d-flex flex-column gap-2"></div>
    <button class="btn btn-outline-primary btn-lg mt-2 touch-target focus-ring" id="btn-add-photo" type="button">Add Photos</button>
    <div class="invalid-feedback" id="photo-feedback"></div>
    <input type="file" id="photo-input" accept="image/*" multiple hidden>
  </div>
  <div class="mb-3">
    <label class="form-label d-block">Customer Signature</label>
    <small class="text-muted d-block mb-1">Customer must sign in the box below.</small>
    <canvas id="sig-canvas" width="300" height="200" class="w-100 border border-2 rounded"></canvas>
    <button class="btn btn-sm btn-outline-secondary mt-2 touch-target focus-ring" id="btn-clear-sig" type="button">Clear</button>
    <div class="invalid-feedback" id="sig-feedback"></div>
  </div>
</div>
<div class="sticky-bar">
  <button class="btn btn-success w-100 touch-target focus-ring" id="btn-submit" type="button">Submit Completion</button>
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
<script src="/js/toast.js"></script>
<script src="/js/offline.js"></script>
<script src="/js/tech_job_complete.js?v=<?=date('Ymd')?>"></script>
</body>
</html>
