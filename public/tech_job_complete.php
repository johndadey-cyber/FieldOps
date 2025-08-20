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
  <style>
    body{padding-bottom:6rem}
    .action-bar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #dee2e6;padding:.75rem;z-index:1000}
    .action-bar .btn{padding:1rem;font-size:1.25rem}
    #sig-canvas{border:1px solid #ced4da;border-radius:.25rem}
    .photo-preview img{max-width:120px}
  </style>
</head>
<body class="bg-light">
<div class="container py-3">
  <div class="mb-3">
    <label for="final-note" class="form-label">Summary Note</label>
    <textarea class="form-control" id="final-note" rows="4"></textarea>
  </div>
  <div class="mb-3">
    <label class="form-label d-block">Final Photos</label>
    <div id="photo-list" class="d-flex flex-column gap-2"></div>
    <button class="btn btn-outline-secondary mt-2" id="btn-add-photo" type="button">Add Photos</button>
    <input type="file" id="photo-input" accept="image/*" multiple hidden>
  </div>
  <div class="mb-3">
    <label class="form-label d-block">Customer Signature</label>
    <canvas id="sig-canvas" width="300" height="150" class="w-100"></canvas>
    <button class="btn btn-sm btn-outline-secondary mt-2" id="btn-clear-sig" type="button">Clear</button>
  </div>
</div>
<div class="action-bar">
  <button class="btn btn-success w-100" id="btn-submit" type="button">Submit Completion</button>
</div>
<script>
  window.CSRF_TOKEN = "<?=htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')?>";
  window.TECH_ID = <?= $techId ?>;
  window.JOB_ID = <?= $jobId ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/tech_job_complete.js?v=<?=date('Ymd')?>"></script>
</body>
</html>
