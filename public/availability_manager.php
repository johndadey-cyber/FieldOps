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

// Helper to detect numeric day column
function dow_is_int(PDO $pdo): bool {
    static $isInt = null;
    if ($isInt !== null) {
        return $isInt;
    }
    try {
        $row = $pdo->query("SHOW COLUMNS FROM employee_availability LIKE 'day_of_week'")
            ->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string)($row['Type'] ?? ''));
        $isInt = str_contains($type, 'int');
    } catch (Throwable $e) {
        $isInt = false;
    }
    return $isInt;
}

$__csrf = csrf_token();
$weekStart = isset($_GET['week_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['week_start'])
    ? (string)$_GET['week_start']
    : date('Y-m-d', strtotime('monday this week'));

// JSON list endpoint for AJAX reloads
if (($_GET['action'] ?? '') === 'list') {
    $pdo = getPDO();
    $eid = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    if ($eid <= 0) { json_out(['ok'=>true,'items'=>[]]); }

    $isInt = dow_is_int($pdo);
    $daysOrder = $isInt
        ? 'FIELD(day_of_week,1,2,3,4,5,6,0)'
        : "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
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

    $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    if ($isInt) {
        foreach ($rows as &$r) {
            $v = $r['day_of_week'] ?? '';
            if (is_numeric($v)) {
                $r['day_of_week'] = $dayNames[((int)$v)%7];
            }
        }
        unset($r);
    }
    $order = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    usort($rows, static function($a, $b) use ($order) {
        $ad = array_search($a['day_of_week'], $order, true);
        $bd = array_search($b['day_of_week'], $order, true);
        $ad = $ad === false ? 99 : $ad;
        $bd = $bd === false ? 99 : $bd;
        if ($ad === $bd) {
            return strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
        }
        return $ad <=> $bd;
    });

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
<body data-csrf="<?= s($__csrf) ?>">
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
      <div>
        <h1 class="h3 mb-0">Availability Manager</h1>
        <div id="weekDisplay" class="text-muted small"></div>
      </div>
      <a href="availability_form.php" class="btn btn-outline-secondary btn-sm">Classic Form</a>
      <a href="availability_onboard.php?employee_id=<?= $selectedEmployeeId ?: '' ?>&week_start=<?= s($weekStart) ?>" class="btn btn-outline-primary btn-sm ms-2">Setup Wizard</a>
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
          <div class="col-sm-5 col-md-4 col-lg-3">
            <label class="form-label">Week of</label>
            <input type="date" id="weekStart" class="form-control" value="<?= s($weekStart) ?>">
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
        <div id="calendarEmpty" class="text-muted text-center py-3 d-none">No calendar events</div>
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
            <label class="form-label">Day of Week</label>
            <select class="form-select" id="win_days" required>
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
          <div class="col-6">
            <label class="form-label">Effective from</label>
            <input type="date" class="form-control" id="win_start_date" required>
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
  <script type="module" src="/js/availability-manager.js"></script>
</body>
</html>
