<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/_csrf.php';

// Only include DB helpers when needed (AJAX list action)
if (($_GET['action'] ?? '') === 'list') {
    require_once __DIR__ . '/../config/database.php';
}

/** HTML escape */
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** @param array<string,mixed> $payload */
function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$__csrf = csrf_token();

// JSON list endpoint for AJAX reloads
if (($_GET['action'] ?? '') === 'list') {
    $pdo = getPDO();
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
            <div class="input-group">
              <input type="search" id="employeeSearch" class="form-control" placeholder="Search by name..." autocomplete="off">
              <button type="button" class="btn btn-outline-secondary" id="btnEmpSearch">Search</button>
            </div>
            <select id="employeeResults" class="form-select mt-2" size="5"></select>
            <input type="hidden" id="employee_id" name="employee_id" value="<?= $selectedEmployeeId ?: '' ?>">
          </div>
          <div class="col-auto">
            <button type="button" class="btn btn-success" id="btnAdd">Add Window</button>
            <button type="button" class="btn btn-warning ms-2" id="btnAddOverride">Add Override</button>
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

    <div class="card mt-4">
      <div class="card-body">
        <h2 class="h5 mb-3">Overrides</h2>
        <div class="table-responsive">
          <table class="table align-middle" id="overrideTable">
            <thead>
              <tr>
                <th style="width: 160px;">Date</th>
                <th style="width: 160px;">Start</th>
                <th style="width: 160px;">End</th>
                <th style="width: 160px;">Status</th>
                <th>Reason</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="overrideEmpty" class="text-muted d-none">No overrides yet.</div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="winModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="winForm">
        <div class="modal-header">
          <h5 class="modal-title" id="winTitle">Add Window</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body row g-3">
          <input type="hidden" name="id" id="win_id">
          <input type="hidden" name="csrf_token" id="csrf_token" value="<?= s($__csrf) ?>">
          <div class="col-12">
            <label class="form-label">Days of Week</label>
            <select class="form-select" id="win_days" multiple required>
              <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                <option value="<?= s($d) ?>"><?= s($d) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="mt-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnWeekdays">Copy to all weekdays</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearWeek">Clear week</button>
            </div>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="win_recurring" checked>
              <label class="form-check-label" for="win_recurring">Recurring?</label>
            </div>
          </div>
          <div class="col-6 date-range d-none">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" id="win_start_date">
          </div>
          <div class="col-6 date-range d-none">
            <label class="form-label">End Date</label>
            <input type="date" class="form-control" id="win_end_date">
          </div>
          <div class="col-6">
            <label class="form-label">Start</label>
            <input type="time" class="form-control" id="win_start" required>
          </div>
          <div class="col-6">
            <label class="form-label">End</label>
            <input type="time" class="form-control" id="win_end" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="winSubmit">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="ovModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="ovForm">
        <div class="modal-header">
          <h5 class="modal-title" id="ovTitle">Add Override</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body row g-3">
          <input type="hidden" id="ov_id">
          <div class="col-6">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" id="ov_start_date" required>
          </div>
          <div class="col-6">
            <label class="form-label">End Date</label>
            <input type="date" class="form-control" id="ov_end_date">
          </div>
          <div class="col-12">
            <label class="form-label">Days of Week</label>
            <select class="form-select" id="ov_days" multiple>
              <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                <option value="<?= s($d) ?>"><?= s($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Start</label>
            <input type="time" class="form-control" id="ov_start_time">
          </div>
          <div class="col-6">
            <label class="form-label">End</label>
            <input type="time" class="form-control" id="ov_end_time">
          </div>
          <div class="col-6">
            <label class="form-label">Status</label>
            <select class="form-select" id="ov_status" required>
              <option value="UNAVAILABLE">Unavailable</option>
              <option value="AVAILABLE">Available</option>
              <option value="PARTIAL">Partial</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Reason</label>
            <input type="text" class="form-control" id="ov_reason">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="ovSubmit">Save</button>
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

  <template id="ovActionTpl">
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
    const overrideBody = document.querySelector('#overrideTable tbody');
    const overrideEmpty = document.getElementById('overrideEmpty');
    const ovActionTpl = document.getElementById('ovActionTpl');
    const dayRows = {};
    tableBody.querySelectorAll('tr.day-row').forEach(tr => { dayRows[tr.dataset.day] = tr; });

    const employeeInput = document.getElementById('employeeSearch');
    const employeeIdField = document.getElementById('employee_id');
    const btnEmpSearch = document.getElementById('btnEmpSearch');
    const resultSelect = document.getElementById('employeeResults');
    const btnAdd = document.getElementById('btnAdd');
    const btnAddOverride = document.getElementById('btnAddOverride');

    const winModalEl = document.getElementById('winModal');
    const winModal = new bootstrap.Modal(winModalEl);
    const winForm = document.getElementById('winForm');
    const winTitle = document.getElementById('winTitle');
    const winId = document.getElementById('win_id');
    const winDays = document.getElementById('win_days');
    const winStart = document.getElementById('win_start');
    const winEnd = document.getElementById('win_end');
    const winRecurring = document.getElementById('win_recurring');
    const winStartDate = document.getElementById('win_start_date');
    const winEndDate = document.getElementById('win_end_date');
    const btnWeekdays = document.getElementById('btnWeekdays');
    const btnClearWeek = document.getElementById('btnClearWeek');

    const ovModalEl = document.getElementById('ovModal');
    const ovModal = new bootstrap.Modal(ovModalEl);
    const ovForm = document.getElementById('ovForm');
    const ovTitle = document.getElementById('ovTitle');
    const ovId = document.getElementById('ov_id');
    const ovStartDate = document.getElementById('ov_start_date');
    const ovEndDate = document.getElementById('ov_end_date');
    const ovDays = document.getElementById('ov_days');
    const ovStartTime = document.getElementById('ov_start_time');
    const ovEndTime = document.getElementById('ov_end_time');
    const ovStatus = document.getElementById('ov_status');
    const ovReason = document.getElementById('ov_reason');

    // Clear focus inside the modal before it is hidden to avoid accessibility warnings
    winModalEl.addEventListener('hide.bs.modal', () => {
      const active = document.activeElement;
      if (active instanceof HTMLElement && winModalEl.contains(active)) {
        active.blur();
      }
    });

    function toggleRecurring() {
      const hide = winRecurring.checked;
      document.querySelectorAll('.date-range').forEach(el => el.classList.toggle('d-none', hide));
    }
    winRecurring.addEventListener('change', toggleRecurring);
    toggleRecurring();

    btnWeekdays.addEventListener('click', async () => {
      if (!confirm('Copy this window to all weekdays?')) return;
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }
      const start = winStart.value;
      const end = winEnd.value;
      const srcDay = Array.from(winDays.options).find(o => o.selected)?.value;
      const weekdays = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
      let ok = true;
      for (const day of weekdays) {
        if (day === srcDay) continue;
        const payload = {
          csrf_token: CSRF,
          employee_id: eid,
          day_of_week: day,
          start_time: start,
          end_time: end
        };
        const res = await fetch('api/availability/create.php', {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data || !data.ok) { ok = false; break; }
      }
      if (ok) {
        showAlert('success', 'Copied.');
        winModal.hide();
        loadAvailability();
      } else {
        showAlert('danger', 'Copy failed');
      }
    });
    btnClearWeek.addEventListener('click', async () => {
      if (!confirm('Clear all availability windows for this employee?')) return;
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }
      const ids = Array.from(tableBody.querySelectorAll('tr[data-id]'))
        .map(r => r.dataset.id)
        .filter(Boolean);
      let ok = true;
      for (const id of ids) {
        const form = new URLSearchParams();
        form.set('csrf_token', CSRF);
        form.set('id', id);
        const res = await fetch('availability_delete.php', {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: form
        });
        const data = await res.json();
        if (!data || !data.ok) { ok = false; break; }
      }
      if (ok) {
        showAlert('success', 'Week cleared.');
        loadAvailability();
      } else {
        showAlert('danger', 'Clear failed');
      }
    });

    async function searchEmployees() {
      const q = employeeInput.value.trim();
      resultSelect.innerHTML = '';
      employeeIdField.value = '';
      if (q.length < 2) { return; }
      try {
        const res = await fetch(`api/employees/search.php?q=${encodeURIComponent(q)}`);
        if (!res.ok) throw new Error('bad response');
        const data = await res.json();
        for (const it of data) {
          const opt = document.createElement('option');
          opt.value = it.id;
          opt.textContent = `${it.name} (ID: ${it.id})`;
          resultSelect.appendChild(opt);
        }
      } catch (err) {
        // ignore errors
      }
    }

    btnEmpSearch.addEventListener('click', searchEmployees);
    resultSelect.addEventListener('change', () => {
      const id = resultSelect.value;
      if (id) {
        employeeIdField.value = id;
        loadAvailability();
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
      const diff = (d.getDay() + 6) % 7; // days since Monday
      d.setDate(d.getDate() - diff);
      return d.toISOString().slice(0,10);
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

    async function loadAvailability() {
      const eid = currentEmployeeId();

      clearRows();
      if (!eid) { emptyState.classList.remove('d-none'); return; }
      const ws = currentWeekStart();
      const url = `api/availability/index.php?employee_id=${encodeURIComponent(eid)}&week_start=${ws}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
      const data = await res.json();

      const items = Array.isArray(data.availability) ? data.availability : [];

      // Ensure items are ordered Monday→Sunday using daysOrder then by start time
      items.sort((a,b) => {
        const dayDiff = daysOrder.indexOf(a.day_of_week) - daysOrder.indexOf(b.day_of_week);
        return dayDiff !== 0 ? dayDiff : (a.start_time || '').localeCompare(b.start_time || '');
      });

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

      overrideBody.innerHTML = '';
      const overrides = Array.isArray(data.overrides) ? data.overrides : [];
      if (!overrides.length) {
        overrideEmpty.classList.remove('d-none');
      } else {
        overrideEmpty.classList.add('d-none');
        for (const ov of overrides) {
          const tr = document.createElement('tr');
          tr.dataset.id = ov.id;
          tr.innerHTML = `<td>${ov.date}</td><td>${ov.start_time || ''}</td><td>${ov.end_time || ''}</td><td>${ov.status}</td><td>${ov.reason || ''}</td><td class="actions"></td>`;
          const actions = ovActionTpl.content.firstElementChild.cloneNode(true);
          actions.querySelector('.btn-edit').addEventListener('click', () => openOvEdit(ov));
          actions.querySelector('.btn-del').addEventListener('click', () => delOverride(ov));
          tr.querySelector('.actions').appendChild(actions);
          overrideBody.appendChild(tr);
        }
      }

      for (const ov of overrides) {
        let day = ov.day_of_week;
        if (!day && ov.date) {
          const dt = new Date(ov.date + 'T00:00:00');
          day = daysOrder[(dt.getDay() + 6) % 7];
        }
        const row = dayRows[day];
        if (row) {
          const badge = row.querySelector('.day-badge');
          badge.className = 'badge bg-warning text-dark day-badge';
        }
      }
    }

    function openAdd() {
      winTitle.textContent = 'Add Window';
      winId.value = '';
      Array.from(winDays.options).forEach(o => { o.selected = o.value === 'Monday'; });
      winStart.value = '09:00';
      winEnd.value = '17:00';
      winRecurring.checked = true;
      winStartDate.value = '';
      winEndDate.value = '';
      toggleRecurring();
      winModal.show();
    }

    function openEdit(it) {
      winTitle.textContent = 'Edit Window';
      winId.value = it.id;
      Array.from(winDays.options).forEach(o => { o.selected = o.value === it.day_of_week; });
      winStart.value = it.start_time;
      winEnd.value = it.end_time;
      winRecurring.checked = true;
      winStartDate.value = '';
      winEndDate.value = '';
      toggleRecurring();
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

    function openOvAdd() {
      ovTitle.textContent = 'Add Override';
      ovId.value = '';
      ovStartDate.value = currentWeekStart();
      ovEndDate.value = '';
      Array.from(ovDays.options).forEach(o => { o.selected = false; });
      ovStartTime.value = '';
      ovEndTime.value = '';
      ovStatus.value = 'UNAVAILABLE';
      ovReason.value = '';
      ovModal.show();
    }

    function openOvEdit(ov) {
      ovTitle.textContent = 'Edit Override';
      ovId.value = ov.id;
      ovStartDate.value = ov.date;
      ovEndDate.value = ov.date;
      Array.from(ovDays.options).forEach(o => { o.selected = o.value === ov.day_of_week; });
      ovStartTime.value = ov.start_time || '';
      ovEndTime.value = ov.end_time || '';
      ovStatus.value = ov.status || 'UNAVAILABLE';
      ovReason.value = ov.reason || '';
      ovModal.show();
    }

    async function delOverride(ov) {
      if (!confirm(`Delete override on ${ov.date}?`)) return;
      const res = await fetch(`api/availability/override_delete.php?id=${ov.id}`, { method: 'DELETE' });
      const data = await res.json();
      if (data && data.ok) {
        showAlert('success', 'Deleted.');
        loadAvailability();
      } else {
        showAlert('danger', 'Delete failed');
      }
    }

    winForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }

      const id = winId.value.trim();
      const days = Array.from(winDays.options).filter(o => o.selected).map(o => o.value);
      if (!days.length) { showAlert('warning', 'Select at least one day.'); return; }

      const base = {
        csrf_token: CSRF,
        employee_id: eid,
        start_time: winStart.value,
        end_time: winEnd.value
      };
      if (!winRecurring.checked) {
        if (winStartDate.value) base.start_date = winStartDate.value;
        if (winEndDate.value) base.end_date = winEndDate.value;
      }
      if (id) base.id = id;

      let ok = true;
      const targetDays = id ? [days[0]] : days;
      for (const day of targetDays) {
        const payload = { ...base, day_of_week: day };
        const res = await fetch('api/availability/create.php', {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data || !data.ok) { ok = false; break; }
      }

      if (ok) {
        showAlert('success', 'Saved.');
        winModal.hide();
        loadAvailability();
      } else {
        showAlert('danger', 'Save failed');
      }
    });

    ovForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }

      const id = ovId.value.trim();
      const startDate = ovStartDate.value;
      const endDate = ovEndDate.value || startDate;
      const days = Array.from(ovDays.options).filter(o => o.selected).map(o => o.value);
      const status = ovStatus.value;
      const startTime = ovStartTime.value;
      const endTime = ovEndTime.value;
      const reason = ovReason.value;
      if (!startDate) { showAlert('warning', 'Start date required.'); return; }

      function* eachDate(s,e){ const d=new Date(s+'T00:00:00'); const end=new Date(e+'T00:00:00'); while(d<=end){ yield d.toISOString().slice(0,10); d.setDate(d.getDate()+1);} }
      const dayName = ds => daysOrder[(new Date(ds+'T00:00:00').getDay()+6)%7];
      let ok = true;
      if (id) {
        const payload = { id: parseInt(id,10), employee_id: eid, date: startDate, status };
        if (startTime) payload.start_time = startTime;
        if (endTime) payload.end_time = endTime;
        if (reason) payload.reason = reason;
        const res = await fetch('api/availability/override.php', {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        ok = data && data.ok;
      } else {
        for (const d of eachDate(startDate, endDate)) {
          const dn = dayName(d);
          if (days.length && !days.includes(dn)) continue;
          const payload = { employee_id: eid, date: d, status };
          if (startTime) payload.start_time = startTime;
          if (endTime) payload.end_time = endTime;
          if (reason) payload.reason = reason;
          const res = await fetch('api/availability/override.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const data = await res.json();
          if (!data || !data.ok) { ok = false; break; }
        }
      }

      if (ok) {
        showAlert('success', 'Saved.');
        ovModal.hide();
        loadAvailability();
      } else {
        showAlert('danger', 'Save failed');
      }
    });

    document.getElementById('btnAdd').addEventListener('click', openAdd);
    btnAddOverride.addEventListener('click', openOvAdd);

    const initId = currentEmployeeId();
    if (initId) {
      fetch(`api/employees/search.php?id=${initId}`)
        .then(r => r.json())
        .then(emp => {
          if (emp && emp.name) {
            employeeInput.value = emp.name;
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (ID: ${emp.id})`;
            resultSelect.appendChild(opt);
            resultSelect.value = String(emp.id);
          }
          loadAvailability();
        })
        .catch(() => loadAvailability());
    } else {
      loadAvailability();
    }
  </script>
</body>
</html>
