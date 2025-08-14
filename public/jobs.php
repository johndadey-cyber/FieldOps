<?php
// /public/jobs.php
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
  <title>Jobs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 (CSS) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .jobs-toolbar { gap:.5rem; }
    .table-jobs td { vertical-align: middle; }
    .badge-status { text-transform: capitalize; }
  </style>
</head>
<body class="bg-light">

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Jobs</h1>
    <div class="jobs-toolbar d-flex">
      <!-- Hard refresh (full page) -->
      <a class="btn btn-outline-secondary" href="jobs.php">Refresh</a>
      <!-- Soft refresh (AJAX) -->
      <button id="btnRefreshJobs" class="btn btn-light btn-sm" type="button">Soft Refresh</button>
      <small id="jobsRefreshStatus" class="text-muted ms-2"></small>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover m-0 table-jobs" id="jobs-table">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Description</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Time</th>
              <th>Crew</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>

          <!-- IMPORTANT: tbody id used by reloadJobs() -->
          <tbody id="jobs-tbody" data-testid="jobs-tbody">
            <?php
              // Delegate row rendering to the partial so the soft-refresh can re-fetch it
              include __DIR__ . '/jobs_table.php';
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
// Assign modal shell + hooks
include __DIR__ . '/../partials/assignments_modal.php';
?>

<!-- Bootstrap JS (must be before assignments.js) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<!-- Assign modal controller (depends on Bootstrap). Dispatches `assignments:updated` on success -->
<script src="/js/assignments.js?v=20250812"></script>

<!-- Inline: robust soft-refresh + diagnostics -->
<script>
(function () {
  // Basic console diagnostics
  console.info('[jobs.php] Inline refresher booting…');

  // Global error logging to surface silent failures
  window.addEventListener('error', function (e) {
    console.error('[jobs.php] Window error:', e.message, e.error || '');
  });

  const $tbody  = document.getElementById('jobs-tbody');
  const $btn    = document.getElementById('btnRefreshJobs');
  const $status = document.getElementById('jobsRefreshStatus');

  if (!$tbody) {
    console.error('[jobs.php] FATAL: #jobs-tbody not found. Soft refresh disabled.');
    return;
  }

  function currentParams() {
    const p = new URLSearchParams();
    const $days   = document.getElementById('filter-days');
    const $statusF = document.getElementById('filter-status');
    const $search = document.getElementById('filter-search');
    if ($days && $days.value)              p.set('days', String(parseInt($days.value, 10) || 0));
    if ($statusF && $statusF.value)        p.set('status', $statusF.value);
    if ($search && $search.value.trim())   p.set('search', $search.value.trim());
    return p;
  }

  async function reloadJobs() {
    const base = new URL('jobs_table.php', window.location.href);
    const qp   = currentParams();
    if ([...qp.keys()].length) base.search = '?' + qp.toString();

    console.info('[jobs.php] reloadJobs -> GET', base.toString());

    try {
      if ($btn) { $btn.disabled = true; $btn.dataset.prevLabel = $btn.textContent; $btn.textContent = 'Refreshing…'; }
      if ($status) $status.textContent = 'Refreshing…';

      const res  = await fetch(base.toString(), { credentials: 'same-origin' });
      const text = await res.text();
      console.info('[jobs.php] jobs_table.php status:', res.status);

      if (!res.ok) throw new Error('HTTP ' + res.status);
      // crude check to ensure we got rows (and not a whole document/error)
      if (!/^\s*<tr[\s>]/i.test(text.replace(/\n/g,''))) {
        console.warn('[jobs.php] Response does not look like <tr> rows. Inserting anyway.');
      }

      $tbody.innerHTML = text;
      if ($status) $status.textContent = 'Updated';

      // Clear any stray modal backdrops just in case
      document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');

      console.info('[jobs.php] reloadJobs complete.');
    } catch (err) {
      console.error('[jobs.php] reloadJobs failed:', err);
      if ($status) $status.textContent = 'Failed to refresh';
    } finally {
      if ($btn) { $btn.disabled = false; $btn.textContent = $btn.dataset.prevLabel || 'Soft Refresh'; }
    }
  }

  // Expose globally for console testing
  window.reloadJobs = reloadJobs;
  console.info('[jobs.php] window.reloadJobs is ready.');

  // Wire the Soft Refresh button
  if ($btn) {
    $btn.addEventListener('click', (e) => { e.preventDefault(); reloadJobs(); });
  }

  // Refresh after a successful assignment (assignments.js should dispatch this)
  window.addEventListener('assignments:updated', function (evt) {
    console.info('[jobs.php] assignments:updated received:', evt.detail || {});
    reloadJobs();
  });

  // Optional: tooltip init for static items (dynamic rows handled inside assignments.js)
  document.addEventListener('DOMContentLoaded', function () {
    if (window.bootstrap && bootstrap.Tooltip) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach(el => new bootstrap.Tooltip(el));
    }
    console.info('[jobs.php] DOMContentLoaded.');
  });
})();
</script>

</body>
</html>
