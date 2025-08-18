<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/_csrf.php';

// Only include DB helpers when needed (AJAX list action)
if (in_array($_GET['action'] ?? '', ['list','log'], true)) {
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

if (($_GET['action'] ?? '') === 'log') {
    $pdo = getPDO();
    $eid = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    if ($eid <= 0) { json_out(['ok'=>true,'items'=>[]]); }
    $st = $pdo->prepare("SELECT action, details, created_at FROM availability_audit WHERE employee_id = :eid ORDER BY created_at DESC LIMIT 20");
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
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
  <style>
    body { padding: 24px; }
    .day-badge { min-width: 90px; display: inline-block; }
    .table thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
    #calendar { max-width: 100%; }
  </style>
</head>
<body>
  <div class="container-xxl">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb mb-0">

        <li class="breadcrumb-item">
          <a href="assignments.php" class="text-decoration-none" aria-label="Back to dashboard">&larr; Back to Dashboard</a>
        </li>

        <li class="breadcrumb-item active" aria-current="page">Availability Manager</li>
      </ol>
    </nav>
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
            <a href="#" class="btn btn-outline-primary disabled" id="btnProfile" aria-label="View selected employee profile" aria-disabled="true">View Profile</a>
            <button type="button" class="btn btn-success ms-2" id="btnAdd">Add Window</button>
            <button type="button" class="btn btn-warning ms-2" id="btnAddOverride">Add Override</button>
            <button type="button" class="btn btn-outline-info ms-2" id="btnExport">Export</button>
            <button type="button" class="btn btn-outline-secondary ms-2" id="btnPrint">Print</button>

          </div>
        </form>
      </div>
    </div>
    <ul class="nav nav-tabs mb-3" id="viewTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#listView" type="button" role="tab">List</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendarView" type="button" role="tab">Calendar</button>
      </li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="listView" role="tabpanel">
        <div class="card">
          <div class="card-body">
            <div class="mb-3 d-flex">
              <select id="bulk_action" class="form-select form-select-sm w-auto me-2">
                <option value="">Bulk Actions</option>
                <option value="copy">Copy schedule to employees</option>
                <option value="reset">Reset week</option>
              </select>
              <button id="bulk_apply" class="btn btn-sm btn-secondary">Apply</button>
            </div>
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
                    <th style="width: 160px;">Type</th>
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
      <div class="tab-pane fade" id="calendarView" role="tabpanel">
        <div id="calendar" class="mt-3"></div>
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
          <input type="hidden" id="win_replace_ids">
          <div class="col-12">
            <label class="form-label">Employee</label>
            <input type="text" class="form-control" id="win_employee" readonly>
          </div>
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
          <div class="col-12" id="win_blocks"></div>
          <div class="col-12">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddBlock">Add block</button>
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
            <label class="form-label">Type</label>
            <select class="form-select" id="ov_type" required>
              <option value="PTO">PTO</option>
              <option value="SICK">Sick</option>
              <option value="CUSTOM">Custom</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Details</label>
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

  <div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Change Log</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="logContent" class="small"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="copyForm">
        <div class="modal-header">
          <h5 class="modal-title">Copy to Days</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="copyDays" class="d-flex flex-wrap gap-2"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Copy</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="bulkCopyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="bulkCopyForm">
        <div class="modal-header">
          <h5 class="modal-title">Copy Schedule</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Target Employee IDs</label>
            <input type="text" id="copyEmployees" class="form-control" placeholder="e.g. 2,3,4" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Week of</label>
            <input type="date" id="copyWeek" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Copy</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="bulkResetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="bulkResetForm">
        <div class="modal-header">
          <h5 class="modal-title">Reset Week</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Employee IDs</label>
            <input type="text" id="resetEmployees" class="form-control" placeholder="e.g. 2,3,4" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Week of</label>
            <input type="date" id="resetWeek" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reset</button>
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
      <button class="btn btn-outline-secondary btn-copy" title="Copy to week">Copy</button>
      <button class="btn btn-outline-danger btn-del">Delete</button>
    </div>
  </template>

  <template id="ovActionTpl">
    <div class="btn-group btn-group-sm">
      <button class="btn btn-outline-primary btn-edit">Edit</button>
      <button class="btn btn-outline-danger btn-del">Delete</button>
    </div>
  </template>

  <template id="blockTpl">
    <div class="row g-2 align-items-center mb-2 block">
      <div class="col">
        <input type="time" class="form-control block-start" required>
      </div>
      <div class="col">
        <input type="time" class="form-control block-end" required>
      </div>
      <div class="col-auto">
        <button type="button" class="btn btn-outline-danger btn-remove-block">&times;</button>
      </div>
    </div>
  </template>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script src="/js/toast.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
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
    const btnProfile = document.getElementById('btnProfile');
    const btnAdd = document.getElementById('btnAdd');
    const btnAddOverride = document.getElementById('btnAddOverride');

    const btnExport = document.getElementById('btnExport');
    const btnPrint = document.getElementById('btnPrint');

    const bulkAction = document.getElementById('bulk_action');
    const bulkApply = document.getElementById('bulk_apply');
    const bulkCopyModalEl = document.getElementById('bulkCopyModal');
    const bulkResetModalEl = document.getElementById('bulkResetModal');
    const bulkCopyModal = new bootstrap.Modal(bulkCopyModalEl);
    const bulkResetModal = new bootstrap.Modal(bulkResetModalEl);
    const bulkCopyForm = document.getElementById('bulkCopyForm');
    const bulkResetForm = document.getElementById('bulkResetForm');

    let alertTimer;
    let searchTimer;
    let searchAbortController;
    let currentGroups = {};


    const winModalEl = document.getElementById('winModal');
    const winModal = new bootstrap.Modal(winModalEl);
    const winForm = document.getElementById('winForm');
    const winTitle = document.getElementById('winTitle');
    const winId = document.getElementById('win_id');
    const winEmployee = document.getElementById('win_employee');
    const winDays = document.getElementById('win_days');
    const winBlocks = document.getElementById('win_blocks');
    const btnAddBlock = document.getElementById('btnAddBlock');
    const blockTpl = document.getElementById('blockTpl');
    const winReplaceIds = document.getElementById('win_replace_ids');
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
    const ovType = document.getElementById('ov_type');
    const ovReason = document.getElementById('ov_reason');

    const copyModalEl = document.getElementById('copyModal');
    const copyModal = new bootstrap.Modal(copyModalEl);
    const copyForm = document.getElementById('copyForm');
    const copyDays = document.getElementById('copyDays');
    let copyBlocks = [];

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

    function clearBlocks() { winBlocks.innerHTML = ''; }
    function addBlock(start = '', end = '') {
      const block = blockTpl.content.firstElementChild.cloneNode(true);
      const s = block.querySelector('.block-start');
      const e = block.querySelector('.block-end');
      s.value = start;
      e.value = end;
      block.querySelector('.btn-remove-block').addEventListener('click', () => block.remove());
      winBlocks.appendChild(block);
    }
    function getBlocks() {
      const blocks = [];
      winBlocks.querySelectorAll('.block').forEach(b => {
        const s = b.querySelector('.block-start').value;
        const e = b.querySelector('.block-end').value;
        if (s && e) blocks.push({ start_time: s, end_time: e });
      });
      return blocks;
    }
    btnAddBlock.addEventListener('click', () => addBlock());

    btnWeekdays.addEventListener('click', async () => {
      if (!confirm('Copy this window to all weekdays?')) return;
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }
      const blocks = getBlocks();
      if (!blocks.length) { showAlert('warning', 'Add at least one block first.'); return; }
      const srcDay = Array.from(winDays.options).find(o => o.selected)?.value;
      const weekdays = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
      let ok = true;
      for (const day of weekdays) {
        if (day === srcDay) continue;
        const payload = {
          csrf_token: CSRF,
          employee_id: eid,
          day_of_week: day,
          blocks
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

    function handleSelect(info) {
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); calendar.unselect(); return; }
      const start = info.start;
      const end = info.end;
      const dayName = daysOrder[(start.getDay() + 6) % 7];
      const startTime = start.toISOString().slice(11,16);
      const endTime = end.toISOString().slice(11,16);
      if (info.jsEvent && info.jsEvent.altKey) {
        openOvAdd();
        ovStartDate.value = start.toISOString().slice(0,10);
        ovEndDate.value = start.toISOString().slice(0,10);
        Array.from(ovDays.options).forEach(o => { o.selected = o.value === dayName; });
        ovStartTime.value = startTime;
        ovEndTime.value = endTime;
      } else {
        openAdd();
        Array.from(winDays.options).forEach(o => { o.selected = o.value === dayName; });
        clearBlocks();
        addBlock(startTime, endTime);
      }
      calendar.unselect();
    }

    function handleEventEdit(ev) {
      const type = ev.extendedProps.type;
      if (type === 'window') {
        const s = ev.start;
        const day = daysOrder[(s.getDay() + 6) % 7];
        openEditDay(day);
      } else if (type === 'override') {
        const raw = { ...ev.extendedProps.raw };
        const s = ev.start;
        const e = ev.end || s;
        raw.date = s.toISOString().slice(0,10);
        raw.start_time = s.toISOString().slice(11,16);
        raw.end_time = e.toISOString().slice(11,16);
        openOvEdit(raw);
      }
    }

    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'timeGridWeek',
      selectable: true,
      select: handleSelect,
      eventClick: info => handleEventEdit(info.event)
    });
    calendar.render();

    async function searchEmployees() {
      hideAlert();
      const q = employeeInput.value.trim();
      resultSelect.innerHTML = '';
      employeeIdField.value = '';
      if (q.length < 2) { return; }
      try {
        if (searchAbortController) { searchAbortController.abort(); }
        searchAbortController = new AbortController();
        const res = await fetch(`api/employees/search.php?q=${encodeURIComponent(q)}`, { signal: searchAbortController.signal });
        if (!res.ok) throw new Error('bad response');
        const data = await res.json();
        if (!data.ok) {
          showAlert('danger', data.error || 'Search failed');
          return;
        }
        if (!data.items || data.items.length === 0) {
          showAlert('warning', 'No employees found');
          return;
        }
        for (const it of data.items) {
          const opt = document.createElement('option');
          opt.value = it.id;
          opt.textContent = `${it.name} (ID: ${it.id})`;
          resultSelect.appendChild(opt);
        }
      } catch (err) {
        if (err.name === 'AbortError') { return; }
        console.error(err);
        resultSelect.innerHTML = '<option disabled value="">Search failed - retry.</option>';
        showAlert('danger', 'Error fetching employees. Please retry.', false);
        const hideOnInteraction = () => {
          hideAlert();
          document.removeEventListener('click', hideOnInteraction);
          document.removeEventListener('keydown', hideOnInteraction);
          document.removeEventListener('input', hideOnInteraction);
        };
        document.addEventListener('click', hideOnInteraction, { once: true });
        document.addEventListener('keydown', hideOnInteraction, { once: true });
        document.addEventListener('input', hideOnInteraction, { once: true });
      }
    }

    btnEmpSearch.addEventListener('click', searchEmployees);
    employeeInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchEmployees();
      }
    });
    employeeInput.addEventListener('input', () => {
      const q = employeeInput.value.trim();
      if (searchTimer) { clearTimeout(searchTimer); }
      if (q.length >= 2) {
        searchTimer = setTimeout(searchEmployees, 300);
      }
    });
    resultSelect.addEventListener('change', () => {
      const id = resultSelect.value;
      employeeIdField.value = id;
      updateProfileLink(id);
      loadAvailability();
    });

    function showAlert(kind, msg, autoHide = true) {
      alertBox.className = 'alert alert-' + kind;
      alertBox.textContent = msg;
      alertBox.classList.remove('d-none');
      if (alertTimer) { clearTimeout(alertTimer); }
      if (autoHide) {
        alertTimer = setTimeout(() => alertBox.classList.add('d-none'), 3000);
      }
    }

    function hideAlert() {
      if (alertTimer) { clearTimeout(alertTimer); }
      alertBox.classList.add('d-none');
    }

    function currentEmployeeId() {
      return parseInt(employeeIdField.value || '0', 10) || 0;
    }

    function currentEmployeeName() {
      const opt = resultSelect.options[resultSelect.selectedIndex];
      return opt ? opt.textContent : '';
    }

    function updateProfileLink(id) {
      if (id) {
        btnProfile.href = `edit_employee.php?id=${encodeURIComponent(id)}`;
        btnProfile.classList.remove('disabled');
        btnProfile.setAttribute('aria-disabled', 'false');
      } else {
        btnProfile.href = '#';
        btnProfile.classList.add('disabled');
        btnProfile.setAttribute('aria-disabled', 'true');
      }
    }

    function currentWeekStart() {
      const d = new Date();
      const diff = (d.getDay() + 6) % 7; // days since Monday
      d.setDate(d.getDate() - diff);
      return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
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
      actions.querySelector('.btn-edit').addEventListener('click', () => openEditDay(it.day_of_week));
      actions.querySelector('.btn-copy').addEventListener('click', () => openCopy(it.day_of_week));
      actions.querySelector('.btn-del').addEventListener('click', () => delRow(it));
      tr.querySelector('.actions').appendChild(actions);
    }

    async function loadAvailability() {
      const eid = currentEmployeeId();
      updateProfileLink(eid);

      clearRows();
      if (!eid) { emptyState.classList.remove('d-none'); return; }
      const ws = currentWeekStart();
      const url = `api/availability/index.php?employee_id=${encodeURIComponent(eid)}&week_start=${ws}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
      if (!res.ok) { showAlert('danger', 'Failed to load availability'); return; }
      const data = await res.json();
      if (data.ok === false) { showAlert('danger', data.error || 'Failed to load availability'); return; }

      const items = Array.isArray(data.availability)
        ? data.availability
        : Array.isArray(data.items)
          ? data.items
          : [];
      const wsDate = new Date(ws + 'T00:00:00');
      const weDate = new Date(wsDate);
      weDate.setDate(weDate.getDate() + 6);
      const we = weDate.toISOString().slice(0,10);

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
      currentGroups = groups;

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
          tr.innerHTML = `<td>${ov.date}</td><td>${ov.start_time || ''}</td><td>${ov.end_time || ''}</td><td>${ov.status}</td><td>${ov.type || ''}</td><td>${ov.reason || ''}</td><td class="actions"></td>`;
          const actions = ovActionTpl.content.firstElementChild.cloneNode(true);
          actions.querySelector('.btn-edit').addEventListener('click', () => openOvEdit(ov));
          actions.querySelector('.btn-del').addEventListener('click', () => delOverride(ov));
          tr.querySelector('.actions').appendChild(actions);
          overrideBody.appendChild(tr);
        }
      }
      calendar.removeAllEvents();
      for (const it of items) {
        const idx = daysOrder.indexOf(it.day_of_week);
        if (idx >= 0) {
          const d = new Date(wsDate);
          d.setDate(d.getDate() + idx);
          const start = d.toISOString().slice(0,10) + 'T' + it.start_time;
          const end = d.toISOString().slice(0,10) + 'T' + it.end_time;
          calendar.addEvent({
            id: 'win-' + it.id,
            start,
            end,
            backgroundColor: '#198754',
            borderColor: '#198754',
            editable: true,
            extendedProps: { type: 'window', raw: it }
          });
        }
      }

      for (const ov of overrides) {
        const start = `${ov.date}T${ov.start_time || '00:00'}`;
        const end = `${ov.date}T${ov.end_time || ov.start_time || '00:00'}`;
        calendar.addEvent({
          id: 'ov-' + ov.id,
          start,
          end,
          backgroundColor: '#ffc107',
          borderColor: '#ffc107',
          editable: true,
          extendedProps: { type: 'override', raw: ov }
        });
      }
      try {
        const jobRes = await fetch(`api/jobs.php?start=${ws}&end=${we}`, { headers: { 'Accept': 'application/json' }});
        const jobs = await jobRes.json();
        for (const job of Array.isArray(jobs) ? jobs : []) {
          const emps = Array.isArray(job.assigned_employees) ? job.assigned_employees : [];
          if (!emps.some(e => e.id === eid)) continue;
          if (!job.scheduled_time) continue;
          const start = `${job.scheduled_date}T${job.scheduled_time}`;
          const dur = job.duration_minutes || 60;
          const endDt = new Date(start);
          endDt.setMinutes(endDt.getMinutes() + dur);
          const end = endDt.toISOString().slice(0,16);
          calendar.addEvent({
            id: 'job-' + job.job_id,
            title: `Job #${job.job_id}`,
            start,
            end,
            backgroundColor: '#0d6efd',
            borderColor: '#0d6efd',
            editable: false,
            extendedProps: { type: 'job', raw: job }
          });
        }
      } catch (err) {
        console.error('Failed to load jobs', err);
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
      winEmployee.value = `${currentEmployeeName()} (ID ${currentEmployeeId()})`;
      Array.from(winDays.options).forEach(o => { o.selected = o.value === 'Monday'; });
      winReplaceIds.value = '';
      clearBlocks();
      addBlock('09:00', '17:00');
      winRecurring.checked = true;
      winStartDate.value = '';
      winEndDate.value = '';
      toggleRecurring();
      winModal.show();
    }

    function openEditDay(day) {
      winTitle.textContent = 'Edit Window';
      winId.value = '';
      winEmployee.value = `${currentEmployeeName()} (ID ${currentEmployeeId()})`;
      Array.from(winDays.options).forEach(o => { o.selected = o.value === day; });
      const arr = currentGroups[day] || [];
      winReplaceIds.value = arr.map(it => it.id).join(',');
      clearBlocks();
      if (arr.length) {
        arr.forEach(it => addBlock(it.start_time, it.end_time));
      } else {
        addBlock('09:00', '17:00');
      }
      winRecurring.checked = true;
      winStartDate.value = '';
      winEndDate.value = '';
      toggleRecurring();
      winModal.show();
    }

    function openCopy(day) {
      copyBlocks = (currentGroups[day] || []).map(it => ({ start_time: it.start_time, end_time: it.end_time }));
      copyDays.innerHTML = '';
      for (const d of daysOrder) {
        const id = 'copy-' + d;
        const dis = d === day ? ' disabled' : '';
        copyDays.innerHTML += `<div class="form-check me-2"><input class="form-check-input" type="checkbox" id="${id}" value="${d}"${dis}><label class="form-check-label" for="${id}">${d}</label></div>`;
      }
      copyModal.show();
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
      ovType.value = 'PTO';
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
      ovType.value = ov.type || 'PTO';
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
      const days = Array.from(winDays.options).filter(o => o.selected).map(o => o.value);
      if (!days.length) { showAlert('warning', 'Select at least one day.'); return; }
      const blocks = getBlocks();
      if (!blocks.length) { showAlert('warning', 'Add at least one block.'); return; }
      blocks.sort((a,b)=>a.start_time.localeCompare(b.start_time));
      for (const b of blocks) {
        if (b.start_time >= b.end_time) { showAlert('warning','Start must be before end.'); return; }
      }
      for (let i=0;i<blocks.length-1;i++) {
        if (blocks[i].end_time > blocks[i+1].start_time) { showAlert('warning','Blocks overlap or out of order.'); return; }
      }

      const payload = {
        csrf_token: CSRF,
        employee_id: eid,
        day_of_week: days,
        blocks
      };
      if (!winRecurring.checked) {
        if (winStartDate.value) payload.start_date = winStartDate.value;
        if (winEndDate.value) payload.end_date = winEndDate.value;
      }
      if (winReplaceIds.value) {
        payload.replace_ids = winReplaceIds.value.split(',').map(v=>parseInt(v,10)).filter(Boolean);
      }

      let ok = false;
      try {
        const res = await fetch('api/availability/create.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        ok = data && data.ok;
        if (!ok) {
          const msg = (data && data.message) ? data.message : 'Save failed';
          showAlert('danger', msg);
        }
      } catch (err) {
        const msg = (err && err.message) ? err.message : 'Save failed';
        showAlert('danger', msg);
        const logPayload = {
          employee_id: eid,
          employee_name: currentEmployeeName(),
          message: err && err.message ? err.message : '',
          stack: err && err.stack ? err.stack : ''
        };
        fetch('api/availability/log_client_error.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(logPayload)
        }).catch(() => {});
      }

      if (ok) {
        showAlert('success', 'Saved.');
        winModal.hide();
        loadAvailability();
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
      const type = ovType.value;
      const startTime = ovStartTime.value;
      const endTime = ovEndTime.value;
      const reason = ovReason.value;
      if (!startDate) { showAlert('warning', 'Start date required.'); return; }

      function* eachDate(s,e){ const d=new Date(s+'T00:00:00'); const end=new Date(e+'T00:00:00'); while(d<=end){ yield d.toISOString().slice(0,10); d.setDate(d.getDate()+1);} }
      const dayName = ds => daysOrder[(new Date(ds+'T00:00:00').getDay()+6)%7];
      let ok = true;
      if (id) {
        const payload = { id: parseInt(id,10), employee_id: eid, date: startDate, status, type };
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
          const payload = { employee_id: eid, date: d, status, type };
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

    async function showLog() {
      const eid = currentEmployeeId();
      if (!eid) { return; }
      try {
        const res = await fetch(`availability_manager.php?action=log&employee_id=${encodeURIComponent(eid)}`, { headers: { 'Accept':'application/json' }});
        const data = await res.json();
        logContent.innerHTML = '';
        const items = Array.isArray(data.items) ? data.items : [];
        if (!items.length) {
          logContent.textContent = 'No recent changes.';
        } else {
          for (const row of items) {
            const div = document.createElement('div');
            div.textContent = `[${row.created_at}] ${row.action} ${row.details || ''}`;
            logContent.appendChild(div);
          }
        }
        logModal.show();
      } catch (err) {
        showAlert('danger', 'Failed to load change log');
      }
    }

    document.getElementById('btnAdd').addEventListener('click', openAdd);
    btnAddOverride.addEventListener('click', openOvAdd);

    btnExport.addEventListener('click', () => {
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }
      const ws = encodeURIComponent(currentWeekStart());
      window.location.href = `api/availability/export.php?employee_id=${encodeURIComponent(eid)}&week_start=${ws}`;
    });
    btnPrint.addEventListener('click', () => {
      const eid = currentEmployeeId();
      if (!eid) { showAlert('warning', 'Select an employee first.'); return; }
      const ws = encodeURIComponent(currentWeekStart());
      window.open(`availability_print.php?employee_id=${encodeURIComponent(eid)}&week_start=${ws}`, '_blank');
    });

    if (bulkApply) {
      bulkApply.addEventListener('click', () => {
        const action = bulkAction.value;
        if (action === 'copy') {
          bulkCopyForm.reset();
          bulkCopyModal.show();
        } else if (action === 'reset') {
          bulkResetForm.reset();
          bulkResetModal.show();
        }
      });
    }

    bulkCopyForm.addEventListener('submit', async e => {
      e.preventDefault();
      const src = currentEmployeeId();
      const targets = document.getElementById('copyEmployees').value.split(',').map(v => parseInt(v.trim(),10)).filter(v => !isNaN(v) && v > 0);
      const week = document.getElementById('copyWeek').value;
      if (!src || targets.length === 0) { FieldOpsToast.show('Select employees', 'danger'); return; }
      const payload = { csrf_token: CSRF, source_employee_id: src, target_employee_ids: targets, week_start: week };
      try {
        const res = await fetch('api/availability/bulk_copy.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.ok) {
          FieldOpsToast.show('Schedule copied.');
          bulkCopyModal.hide();
          if (targets.includes(src)) loadAvailability();
        } else {
          FieldOpsToast.show('Copy failed', 'danger');
        }
      } catch {
        FieldOpsToast.show('Copy failed', 'danger');
      }
    });

    bulkResetForm.addEventListener('submit', async e => {
      e.preventDefault();
      const ids = document.getElementById('resetEmployees').value.split(',').map(v => parseInt(v.trim(),10)).filter(v => !isNaN(v) && v > 0);
      const week = document.getElementById('resetWeek').value;
      if (ids.length === 0) { FieldOpsToast.show('Select employees', 'danger'); return; }
      const payload = { csrf_token: CSRF, employee_ids: ids, week_start: week };
      try {
        const res = await fetch('api/availability/bulk_reset.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.ok) {
          FieldOpsToast.show('Week reset.');
          bulkResetModal.hide();
          if (ids.includes(currentEmployeeId())) loadAvailability();
        } else {
          FieldOpsToast.show('Reset failed', 'danger');
        }
      } catch {
        FieldOpsToast.show('Reset failed', 'danger');
      }
    });

    copyForm.addEventListener('submit', async e => {
      e.preventDefault();
      const eid = currentEmployeeId();
      const days = Array.from(copyDays.querySelectorAll('input:checked')).map(i => i.value);
      if (!eid || days.length === 0 || copyBlocks.length === 0) { FieldOpsToast.show('Select target days', 'danger'); return; }
      const payload = { csrf_token: CSRF, employee_id: eid, day_of_week: days, blocks: copyBlocks };
      try {
        const res = await fetch('api/availability/create.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (res.ok && data.ok) {
          copyModal.hide();
          FieldOpsToast.show('Availability copied.');
          loadAvailability();
        } else {
          FieldOpsToast.show('Copy failed', 'danger');
        }
      } catch {
        FieldOpsToast.show('Copy failed', 'danger');
      }
    });

    const initId = currentEmployeeId();
    if (initId) {
      fetch(`api/employees/search.php?id=${initId}`)
        .then(r => r.json())
        .then(resp => {
          if (resp.ok && resp.item && resp.item.name) {
            employeeInput.value = resp.item.name;
            const opt = document.createElement('option');
            opt.value = resp.item.id;
            opt.textContent = `${resp.item.name} (ID: ${resp.item.id})`;
            resultSelect.appendChild(opt);
            resultSelect.value = String(resp.item.id);
            updateProfileLink(resp.item.id);
          }
          loadAvailability();
        })
        .catch(() => { updateProfileLink(initId); loadAvailability(); });
    } else {
      loadAvailability();
    }
  </script>
</body>
</html>
