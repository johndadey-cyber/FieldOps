<?php

// /public/jobs.php

declare(strict_types=1);

require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_token'];

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Job.php';

$pdo = getPDO();
$statuses = Job::allowedStatuses();
$today = date('Y-m-d');
$weekLater = date('Y-m-d', strtotime('+7 days'));

$title = 'Jobs';
$headExtra = <<<HTML
  <style>
    .jobs-toolbar{gap:.5rem;flex-wrap:wrap}
    .table-jobs th{white-space:nowrap}
    .table-jobs td{vertical-align:middle}
    .badge-status{text-transform:capitalize}
    .sticky-header thead th{position:sticky;top:0;z-index:1}
  </style>
HTML;
require __DIR__ . '/../partials/header.php';
?>

  <div class="d-flex align-items-center mb-3">
    <div class="ms-auto">
      <a href="add_job.php" class="btn btn-primary btn-sm">+ Add Job</a>
    </div>
  </div>
  <div class="jobs-toolbar d-flex mb-3">
    <div>
      <label for="filter-start" class="form-label small mb-0">Start</label>
      <input type="date" id="filter-start" class="form-control form-control-sm" value="<?=$today?>">
    </div>
    <div>
      <label for="filter-end" class="form-label small mb-0">End</label>
      <input type="date" id="filter-end" class="form-control form-control-sm" value="<?=$weekLater?>">
    </div>
    <div>
      <label for="filter-status" class="form-label small mb-0">Status</label>
      <select id="filter-status" class="form-select form-select-sm" multiple>
        <?php foreach ($statuses as $s) : ?>
            <?php $label = ucwords(str_replace('_', ' ', $s)); ?>
            <option value="<?=htmlspecialchars($s)?>"
                <?=in_array($s, ['scheduled', 'in_progress'], true) ? 'selected' : ''?>>
                <?=htmlspecialchars($label)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="filter-search" class="form-label small mb-0">Customer</label>
      <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Search customer">
    </div>
    <div class="form-check form-switch align-self-end ms-2">
      <input class="form-check-input" type="checkbox" id="filter-show-past">
      <label class="form-check-label small" for="filter-show-past">Show Past</label>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0 table-jobs sticky-header" id="jobs-table">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Customer</th>
            <th>Job Skills</th>
            <th>Assigned Employees</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="jobs-tbody"></tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../partials/assignments_modal.php'; ?>
<?php
$pageScripts = <<<HTML
<script src="/js/assignments.js?v=20250812"></script>
<script src="/js/jobs.js?v=20250812"></script>
HTML;
require __DIR__ . '/../partials/footer.php';
?>
