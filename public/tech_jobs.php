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
</head>
<body class="bg-light">
<div class="container py-3">
  <h1 class="h4 mb-3">Today's Jobs</h1>
  <div id="jobs-list" class="list-group"></div>
</div>
<script>
  window.CSRF_TOKEN = "<?=htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')?>";
  window.TECH_ID = <?= $techId ?>;
  window.TODAY = "<?= $today ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/tech_jobs.js?v=<?=date('Ymd')?>"></script>
</body>
</html>
