<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// dev safety: only allow localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {
  http_response_code(403); echo 'Forbidden'; exit;
}

require_once __DIR__ . '/../config/database.php';

// ensure CSRF/session basics exist
$_SESSION['role']       = $_SESSION['role']       ?? 'dispatcher';
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

$jobId = isset($_GET['jobId']) ? (int)$_GET['jobId'] : 0;
$date  = $_GET['date'] ?? date('Y-m-d', strtotime('+7 days'));
$time  = $_GET['time'] ?? '10:00:00';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Assignments Sandbox — FieldOps</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f8fafc}</style>
</head>
<body>
<div class="container py-4">
  <h1 class="h4 mb-3">Eligible Employees — Sandbox</h1>

  <form class="row g-2 mb-3" id="qform">
    <div class="col-auto">
      <label class="form-label">Job ID</label>
      <input class="form-control" type="number" name="jobId" value="<?= htmlspecialchars((string)$jobId) ?>" required>
    </div>
    <div class="col-auto">
      <label class="form-label">Date</label>
      <input class="form-control" type="date" name="date" value="<?= htmlspecialchars($date) ?>" required>
    </div>
    <div class="col-auto">
      <label class="form-label">Time</label>
      <input class="form-control" type="time" name="time" value="<?= htmlspecialchars(substr($time,0,5)) ?>" required>
    </div>
    <div class="col-auto align-self-end">
      <button class="btn btn-primary" type="submit">Fetch</button>
    </div>
  </form>

  <div id="alert" class="alert d-none" role="alert"></div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between">
        <h2 class="h6 mb-3">Results</h2>
        <small class="text-muted">Needs dispatcher session (use dev_login.php first if empty)</small>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Conflict</th>
              <th>Skill Match</th>
            </tr>
          </thead>
          <tbody id="tbody"><tr><td colspan="4" class="text-muted">No data yet.</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(async function() {
  const form = document.getElementById('qform');
  const tbody = document.getElementById('tbody');
  const alertBox = document.getElementById('alert');

  function showAlert(kind, msg) {
    alertBox.className = 'alert alert-' + kind;
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
  }
  function clearAlert(){ alertBox.classList.add('d-none'); }

  async function fetchEligible(jobId, date, time) {
    const url = new URL(location.origin + '/api/assignments/eligible.php');
    url.searchParams.set('jobId', jobId);
    url.searchParams.set('date', date);
    url.searchParams.set('time', time);
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  }

  function renderRows(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No eligible employees.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => {
      const id    = r.id ?? r.employee_id ?? '';
      const name  = [r.first_name, r.last_name].filter(Boolean).join(' ');
      const conf  = (r.conflict ? 'Yes' : 'No');
      const skill = (r.skill_match === false ? 'No' : 'Yes');
      return `<tr>
        <td>${id}</td>
        <td>${name || '—'}</td>
        <td>${conf}</td>
        <td>${skill}</td>
      </tr>`;
    }).join('');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearAlert();
    const data = new FormData(form);
    const jobId = data.get('jobId');
    const date  = data.get('date');
    const time  = data.get('time');
    try {
      const json = await fetchEligible(jobId, date, time);
      if (!json.ok) {
        showAlert('warning', (json.error || 'Forbidden') + ' — make sure you hit dev_login.php first.');
        renderRows([]);
        return;
      }
      renderRows(json.employees || json.data || []);
    } catch (err) {
      showAlert('danger', String(err));
      renderRows([]);
    }
  });
})();
</script>
</body>
</html>
