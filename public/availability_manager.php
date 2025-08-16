<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';

/** HTML escape */
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** @param array<string,mixed> $payload */
function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo    = getPDO();
$__csrf = csrf_token();

// JSON list endpoint for AJAX reloads
if (($_GET['action'] ?? '') === 'list') {
    $eid = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    if ($eid <= 0) { json_out(['ok'=>true,'items'=>[]]); }

    $daysOrder = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
    $st = $pdo->prepare("
        SELECT id,
               day_of_week,
               DATE_FORMAT(start_time, '%H:%i') AS start_time,
               DATE_FORMAT(end_time,   '%H:%i') AS end_time
        FROM employee_availability
        WHERE employee_id = :eid
        ORDER BY {$daysOrder}, start_time
    ");
    $st->execute([':eid'=>$eid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    json_out(['ok'=>true,'items'=>$rows]);
}

// Selected employee id from query string (if any)
$selectedEmployeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Availability Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <style>
    body { padding: 24px; }
    .day-badge { min-width: 90px; display: inline-block; }
    .table thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
  </style>
</head>
<body>
  <div class="container-xxl">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 mb-0">Availability Manager</h1>
      <a href="availability_form.php" class="btn btn-outline-secondary btn-sm">Classic Form</a>
    </div>

    <div id="alertBox" class="alert d-none" role="alert"></div>

    <div class="card mb-4">
      <div class="card-body">
        <form id="employeePicker" class="row g-2 align-items-end">
          <div class="col-sm-7 col-md-6 col-lg-5">
            <label class="form-label">Employee</label>
            <input type="search" id="employeeSearch" class="form-control" placeholder="Type to search..." list="employeeList" autocomplete="off">
            <datalist id="employeeList"></datalist>
            <input type="hidden" id="employee_id" name="employee_id" value="<?= $selectedEmployeeId ?: '' ?>">
          </div>
          <div class="col-auto">
            <button type="button" class="btn btn.success btn-success" id="btnAdd">Add Window</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle" id="availabilityTable">
            <thead>
              <tr>
                <th style="width: 160px;">Day</th>
                <th style="width: 160px;">Start</th>
                <th style="width: 160px;">End</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                <tr class="day-row" data-day="<?= s($d) ?>">
                  <td><span class="badge bg-light text-dark day-badge"><?= s($d) ?></span></td>
                  <td class="start"></td>
                  <td class="end"></td>
                  <td class="actions"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="emptyState" class="text-muted d-none">No availability windows yet.</div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="winModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="winForm">
        <div class="modal-header">
          <h5 class="modal-title" id="winTitle">Add Window</h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body row g-3">
          <input type="hidden" name="id" id="win_id">
          <input type="hidden" name="csrf_token" id="csrf_token" value="<?= s($__csrf) ?>">
          <div class="col-12">
            <label class="form-label">Day of Week</label>
            <select class="form-select" name="day_of_week" id="win_day" required>
              <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                <option value="<?= s($d) ?>"><?= s($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Start</label>
            <input type="time" class="form-control" name="start_time" id="win_start" required>
          </div>
          <div class="col-6">
            <label class="form-label">End</label>
            <input type="time" class="form-control" name="end_time" id="win_end" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="winSubmit">Save</button>
        </div>
      </form>
    </div>
  </div>

  <template id="subRowTpl">
    <tr class="sub-row" data-id="">
      <td></td>
      <td class="start"></td>
      <td class="end"></td>
      <td class="actions"></td>
    </tr>
  </template>

  <template id="actionTpl">
    <div class="btn-group btn-group-sm">
      <button class="btn btn-outline-primary btn-edit">Edit</button>
      <button class="btn btn-outline-danger btn-del">Delete</button>
    </div>
  </template>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    const CSRF = <?= json_encode($__csrf) ?>;
    const daysOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const tableBody = document.querySelector('#availabilityTable tbody');
    const emptyState = document.getElementById('emptyState');
    const alertBox = document.getElementById('alertBox');
    const subRowTpl = document.getElementById('subRowTpl');
    const actionTpl = document.getElementById('actionTpl');
    const dayRows = {};
    tableBody.querySelectorAll('tr.day-row').forEach(tr => { dayRows[tr.dataset.day] = tr; });

    const employeeInput = document.getElementById('employeeSearch');
    const employeeIdField = document.getElementById('employee_id');
    const suggestionList = document.getElementById('employeeList');
    const btnAdd = document.getElementById('btnAdd');

    const winModalEl = document.getElementById('winModal');
    const winModal = new bootstrap.Modal(winModalEl);
    const winForm = document.getElementById('winForm');
    const winTitle = document.getElementById('winTitle');
    const winId = document.getElementById('win_id');
    const winDay = document.getElementById('win_day');
    const winStart = document.getElementById('win_start');
    const winEnd = document.getElementById('win_end');

    employeeInput.addEventListener('input', async () => {
      const q = employeeInput.value.trim();
      if (q.length < 2) { suggestionList.innerHTML = ''; return; }
      const res = await fetch(`api/employees/search.php?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      suggestionList.innerHTML = '';
      for (const it of data) {
        const opt = document.createElement('option');
        opt.value = `${it.name} (ID: ${it.id})`;
        suggestionList.appendChild(opt);
      }
    });

    employeeInput.addEventListener('change', () => {
      const m = /\(ID:\s*(\d+)\)$/.exec(employeeInput.value);
      if (m) {
        employeeIdField.value = m[1];
        loadAvailability();
      } else {
        employeeIdField.value = '';
      }
    });

    function showAlert(kind, msg) {
      alertBox.className = 'alert alert-' + kind;
      alertBox.textContent = msg;
      alertBox.classList.remove('d-none');
      setTimeout(() => alertBox.classList.add('d-none'), 3000);
    }

    function currentEmployeeId() {
      return parseInt(employeeIdField.value || '0', 10) || 0;
    }

    function currentWeekStart() {
      const d = new Date();
      const day = d.getDay(); // 0 Sun - 6 Sat
      const diff = (day === 0 ? -6 : 1) - day;
      d.setDate(d.getDate() + diff);
      return d.toISOString().slice(0, 10);
    }

    function clearRows() {
      tableBody.querySelectorAll('.sub-row').forEach(r => r.remove());
      for (const d of daysOrder) {
        const row = dayRows[d];
        row.dataset.id = '';
        row.querySelector('.start').textContent = '';
        row.querySelector('.end').textContent = '';
        row.querySelector('.actions').innerHTML = '';
        const badge = row.querySelector('.day-badge');
        badge.className = 'badge bg-light text-dark day-badge';
      }
    }

    function fillRow(tr, it) {
      tr.dataset.id = it.id;
      tr.querySelector('.start').textContent = it.start_time;
      tr.querySelector('.end').textContent = it.end_time;
      const actions = actionTpl.content.firstElementChild.cloneNode(true);
      actions.querySelector('.btn-edit').addEventListener('click', () => openEdit(it));
      actions.querySelector('.btn-del').addEventListener('click', () => delRow(it));
      tr.querySelector('.actions').appendChild(actions);
    }

    function flagOverrides(overrides) {
      const ovDays = new Set();
      for (const o of overrides) {
        const dt = new Date(o.date + 'T00:00:00');
        const idx = dt.getDay();
        const dayName = daysOrder[(idx + 6) % 7];
        ovDays.add(dayName);
      }
      for (const d of daysOrder) {
        const badge = dayRows[d].querySelector('.day-badge');
        if (ovDays.has(d)) {
          badge.className = 'badge bg-warning text-dark day-badge';
        }
      }
    }

    async function loadAvailability() {
      const eid = currentEmployeeId();
      clearRows();
      if (!eid) { emptyState.classList.remove('d-none'); return; }
      const ws = currentWeekStart();
      const url = `api/availability/index.php?employee_id=${encodeURIComponent(eid)}&week_start=${ws}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
      const data = await res.json();

      const items = (data && data.availability) ? data.availability : [];
      const overrides = (data && data.overrides) ? data.overrides : [];

      if (!items.length) {
        emptyState.classList.remove('d-none');
      } else {
        emptyState.classList.add('d-none');
      }

      const groups = {};
      for (const d of daysOrder) groups[d] = [];
      for (const it of items) {
        if (groups[it.day_of_week]) groups[it.day_of_week].push(it);
      }

      for (const d of daysOrder) {
        const arr = groups[d];
        arr.sort((a,b) => (a.start_time || '').localeCompare(b.start_time || ''));
        if (!arr.length) continue;
        fillRow(dayRows[d], arr[0]);
        let prev = dayRows[d];
        for (const it of arr.slice(1)) {
          const sub = subRowTpl.content.firstElementChild.cloneNode(true);
          fillRow(sub, it);
          tableBody.insertBefore(sub, prev.nextSibling);
          prev = sub;
        }
      }

      if (overrides.length) flagOverrides(overrides);
    }

    function openAdd() {
      winTitle.textContent = 'Add Window';
      winId.value = '';
      winDay.value = 'Monday';
      winStart.value = '09:00';
      winEnd.value = '17:00';
      winModal.show();
    }

    function openEdit(it) {
      winTitle.textContent = 'Edit Window';
      winId.value = it.id;
      winDay.value = it.day_of_week;
      winStart.value = it.start_time;
      winEnd.value = it.end_time;
      winModal.show();
    }

    async function delRow(it) {
      if (!confirm(`Delete ${it.day_of_week} ${it.start_time}–${it.end_time}?`)) return;
      const form = new URLSearchParams();
      form.set('csrf_token', CSRF);
      form.set('id', String(it.id));
      const res = await fetch('availability_delete.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: form
      });
      const data = await res.json();
      if (data && data.ok) {
        showAlert('success', 'Deleted.');
        loadAvailability();
      } else {
        showAlert('danger', (data && (data.error || (data.errors||[]).join(', '))) || 'Delete failed');
      }
    }

    winForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }

      const id = winId.value.trim();
      const form = new URLSearchParams();
      form.set('csrf_token', CSRF);
      form.set('employee_id', String(eid));
      form.set('day_of_week', winDay.value);
      form.set('start_time', winStart.value);
      form.set('end_time', winEnd.value);

      const endpoint = id ? 'availability_update.php' : 'availability_save.php';
      if (id) form.set('id', id);

      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: form
      });
      const data = await res.json();
      if (data && data.ok) {
        showAlert('success', 'Saved.');
        winModal.hide();
        loadAvailability();
      } else {
        const msg = (data && (data.error || (data.errors||[]).join(', '))) || 'Save failed';
        showAlert('danger', msg);
      }
    });

    document.getElementById('btnAdd').addEventListener('click', openAdd);

    const initId = currentEmployeeId();
    if (initId) {
      fetch(`api/employees/search.php?id=${initId}`)
        .then(r => r.json())
        .then(emp => {
          if (emp && emp.name) employeeInput.value = `${emp.name} (ID: ${emp.id})`;
          loadAvailability();
        })
        .catch(() => loadAvailability());
    } else {
      loadAvailability();
    }
  </script>
</body>
</html>
