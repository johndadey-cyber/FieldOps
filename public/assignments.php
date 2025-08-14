<?php
// /public/assignments.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Assignments</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">
  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 m-0 me-2">Assignments</h1>
  </div>
  <div class="mb-3">
    <input type="search" id="filter-search" class="form-control form-control-sm" placeholder="Search jobs or customers">
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0" id="assignments-table">
        <thead class="table-light">
          <tr>
            <th>Job</th>
            <th>Description</th>
            <th>Customer</th>
            <th>Schedule</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody id="assignments-tbody"></tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../partials/assignments_modal.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/assignments.js"></script>
<script src="/js/assignments-page.js"></script>
</body>
</html>
