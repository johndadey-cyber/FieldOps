<?php
// /public/assign.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// DB only needed if you plan to validate job existence here; safe to keep:
require_once __DIR__ . '/../config/database.php';
$pdo = getPDO();

$jobId = (int)($_GET['job_id'] ?? 0);
if ($jobId <= 0) { http_response_code(400); echo 'Missing job_id'; exit; }

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assign Employees</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: rgba(0,0,0,0.05); }
    .modal-card { max-width: 900px; margin: 40px auto; }
    .status-dot { width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:8px; }
    .st-available { background:#28a745; }
    .st-partial { background:#ffc107; }
    .st-unavailable { background:#dc3545; }
    .is-click { cursor:pointer; }
    .scroll-box { max-height: 50vh; overflow:auto; }
  </style>
</head>
<body>
<div class="container">
  <div class="card modal-card shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="m-0">Assign Employees to Job #<span id="hdrJobId"><?= $jobId ?></span></h5>
      <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">✕</a>
    </div>
    <div class="card-body">
      <!-- Job info -->
      <div class="row g-2 mb-3">
        <div class="col-md-6"><strong>Customer:</strong> <span id="jobCustomer">—</span></div>
        <div class="col-md-6"><strong>Description:</strong> <span id="jobDesc">—</span></div>
        <div class="col-md-6"><strong>Scheduled:</strong> <span id="jobSched">—</span></div>
        <div class="col-md-6"><strong>Required Skills:</strong> <span id="jobSkills">—</span></div>
      </div>

      <!-- Filters -->
      <div class="row g-2 align-items-end mb-3">
        <div class="col-md-3 form-check">
          <input class="form-check-input" type="checkbox" id="availableOnly" checked>
          <label class="form-check-label" for="availableOnly">Show Available Only</label>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="skillFilter">Filter by Skill</label>
          <input class="form-control" id="skillFilter" placeholder="e.g., Window Washing">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="sortBy">Sort By</label>
          <select id="sortBy" class="form-select">
            <option value="distance">Distance</option>
            <option value="load">Day load</option>
            <option value="name">Name</option>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button id="applyFilters" class="btn btn-primary">Apply</button>
        </div>
      </div>

      <!-- Candidates -->
      <div class="scroll-box border rounded">
        <table class="table table-hover align-middle mb-0">
          <tbody id="candBody">
          <tr><td class="text-center text-muted py-3">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
      <button class="btn btn-outline-secondary" onclick="window.history.back()">Cancel</button>
      <div class="d-flex gap-2">
        <button id="clearAll" class="btn btn-outline-secondary">Clear All</button>
        <button id="assignBtn" class="btn btn-primary">Assign Selected →</button>
      </div>
    </div>
  </div>
</div>

<script>
'use strict';

const jobId = <?= (int)$jobId ?>;
const csrf  = "<?= e($csrf) ?>";

function fmtHuman(dateStr, timeStr) {
  try {
    const d = new Date(`${dateStr}T${(timeStr||'00:00:00')}`);
    return d.toLocaleString([], {month:'short', day:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'});
  } catch (e) { return `${dateStr} @ ${timeStr}`; }
}

function badge(status, availWindow, conflict) {
  const base = {'AVAILABLE':'st-available','PARTIAL':'st-partial','UNAVAILABLE':'st-unavailable'}[String(status).toUpperCase()] || 'st-unavailable';
  const label = String(status || 'unavailable').toLowerCase();
  const extra = availWindow ? ` ${availWindow}` : '';
  const warn = conflict ? ' ⚠️' : '';
  return `<span class="status-dot ${base}"></span>${label}${extra}${warn}`;
}

function rowHTML(emp, prechecked) {
  const name = [emp.first_name, emp.last_name].filter(Boolean).join(' ') || `#${emp.id}`;
  const skills = Array.isArray(emp.skills) && emp.skills.length
    ? `<div class="text-muted small">Skills: ${emp.skills.map(s=>s.name||s).join(', ')}</div>`
    : '';
  const dist = (typeof emp.distanceKm === 'number')
    ? `<span class="ms-2 badge text-bg-light border">${(emp.distanceKm * 0.621371).toFixed(1)} mi</span>`
    : '';
  const checked = prechecked ? 'checked' : '';
  const win = emp.availability && emp.availability.window ? emp.availability.window : '';
  const conflict = (emp.conflicts && emp.conflicts.length) ? true : false;

  return `
    <tr data-id="${emp.id}" class="is-click">
      <td style="width:40px"><input type="checkbox" class="form-check-input sel" ${checked}></td>
      <td>
        <div><strong>${name}</strong>${dist}</div>
        ${skills}
      </td>
      <td style="width:280px" class="text-end">${badge(emp.availability?.status || 'unavailable', win, conflict)}</td>
    </tr>
  `;
}

function loadCandidates() {
  const availableOnly = document.getElementById('availableOnly').checked ? 1 : 0;
  const skill = encodeURIComponent(document.getElementById('skillFilter').value || '');
  const sort  = encodeURIComponent(document.getElementById('sortBy').value || 'distance');

  const body = document.getElementById('candBody');
  body.innerHTML = `<tr><td class="text-center text-muted py-3">Loading…</td></tr>`;

  // Pull eligible + current in parallel
  Promise.all([
    fetch(`/api/assignments/eligible.php?job_id=${jobId}&qualified_only=${availableOnly}&sort=${sort}&skill=${skill}`).then(r=>r.json()),
    fetch(`/api/assignments/current.php?job_id=${jobId}`).then(r=>r.json())
  ]).then(([elig, curr])=>{
    if (!elig.ok) {
      body.innerHTML = `<tr><td class="text-danger text-center py-3">${elig.error||'Error loading eligible employees'}</td></tr>`;
      return;
    }
    const job = elig.job || {};
    document.getElementById('jobCustomer').textContent = (job.customer && job.customer.name) ? job.customer.name : '—';
    document.getElementById('jobDesc').textContent     = job.description || '—';
    document.getElementById('jobSched').textContent    = (job.scheduledDate ? fmtHuman(job.scheduledDate, job.scheduledTime) : '—');
    document.getElementById('jobSkills').textContent   = Array.isArray(job.requiredSkills) && job.requiredSkills.length
      ? job.requiredSkills.map(s=>s.name).join(', ')
      : '—';

    const assignedSet = new Set((curr && curr.employees ? curr.employees : []).map(Number));
    const list = Array.isArray(elig.employees) ? elig.employees.slice() : [];

    if (!list.length) {
      body.innerHTML = `<tr><td class="text-center text-muted py-3">No matching employees found.</td></tr>`;
      return;
    }

    body.innerHTML = list.map(emp => rowHTML(emp, assignedSet.has(Number(emp.id)))).join('');

    // Row click toggles checkbox
    Array.prototype.forEach.call(body.querySelectorAll('tr'), function(tr){
      tr.addEventListener('click', function(e){
        if (e.target && e.target.classList && e.target.classList.contains('sel')) return;
        const cb = tr.querySelector('input.sel');
        if (cb) cb.checked = !cb.checked;
      });
    });
  }).catch(err=>{
    console.error(err);
    body.innerHTML = `<tr><td class="text-danger text-center py-3">Failed to load employees.</td></tr>`;
  });
}

document.getElementById('applyFilters').addEventListener('click', loadCandidates);
document.getElementById('clearAll').addEventListener('click', function(){
  document.querySelectorAll('#candBody input.sel').forEach(function(cb){ cb.checked = false; });
});

document.getElementById('assignBtn').addEventListener('click', function(){
  const ids = [];
  document.querySelectorAll('#candBody input.sel').forEach(function(cb){
    if (cb.checked) {
      const tr = cb.closest('tr');
      if (tr) {
        const id = parseInt(tr.getAttribute('data-id'), 10);
        if (!isNaN(id)) ids.push(id);
      }
    }
  });

  if (ids.length === 0 && !confirm('No employees selected. Proceed to clear assignments for this job?')) return;

  // Replace-all contract (matches your new assignment_process.php)
  const form = new URLSearchParams();
  form.append('csrf_token', csrf);
  form.append('job_id', String(jobId));
  ids.forEach(id => form.append('employee_ids[]', String(id)));

  fetch('/assignment_process.php?action=assign', {
    method: 'POST',
    headers: { 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded' },
    body: form.toString()
  })
  .then(r => r.json())
  .then(data => {
    if (!data || !data.ok) {
      alert(data && data.error ? data.error : 'Save failed.');
      return;
    }
    // Success: navigate back or refresh Jobs
    window.location.href = '/jobs.php';
  })
  .catch(err => {
    console.error(err);
    alert('Save failed.');
  });
});

// Initial load
loadCandidates();
</script>
</body>
</html>
