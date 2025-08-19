import { fetchAvailability, fetchJobs } from './availability-fetch.js';
import { renderList, showAlert, hideAlert, currentGroups } from './list-render.js';
import { initCalendar, renderCalendar } from './calendar-render.js';
import { openOvAdd, openOvEdit, delOverride } from './override-handlers.js';

const DEBUG = false;
const CSRF = document.body.dataset.csrf;
const daysOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

const tableBody = document.querySelector('#availabilityTable tbody');
const employeeInput = document.getElementById('employeeSearch');
const employeeIdField = document.getElementById('employee_id');
const btnEmpSearch = document.getElementById('btnEmpSearch');
const resultSelect = document.getElementById('employeeResults');
const btnProfile = document.getElementById('btnProfile');
const btnAdd = document.getElementById('btnAdd');
const btnAddOverride = document.getElementById('btnAddOverride');
const btnQuickPTO = document.getElementById('btnQuickPTO');

const weekStartInput = document.getElementById('weekStart');
const weekDisplay = document.getElementById('weekDisplay');

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

let searchTimer;
let searchAbortController;

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

const ovForm = document.getElementById('ovForm');

const copyModalEl = document.getElementById('copyModal');
const copyModal = new bootstrap.Modal(copyModalEl);
const copyForm = document.getElementById('copyForm');
const copyDays = document.getElementById('copyDays');
let copyBlocks = [];

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
  const srcDay = winDays.value;
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
    if (DEBUG) {
      console.log('Request to api/availability/create.php', payload, document.cookie);
    }
    const res = await fetch('api/availability/create.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (DEBUG) {
      console.log('Response from api/availability/create.php', res.status, data);
    }
    if (!data || !data.ok) { ok = false; break; }
  }
  if (ok) {
    showAlert('success', 'Copied.');
    winModal.hide();
    await loadAvailability();
  } else {
    showAlert('danger', 'Copy failed');
  }
});
btnClearWeek.addEventListener('click', async () => {
  if (!confirm('Clear all availability windows for this employee?')) return;
  const eid = currentEmployeeId();
  if (!eid) { showAlert('warning', 'Select an employee first.'); return; }
  const payload = { csrf_token: CSRF, employee_id: eid };
  let ok = true;
  try {
    if (DEBUG) {
      console.log('Request to api/availability/clear_week.php', payload, document.cookie);
    }
    const res = await fetch('api/availability/clear_week.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (DEBUG) {
      console.log('Response from api/availability/clear_week.php', res.status, data);
    }
    ok = data && data.ok;
  } catch {
    ok = false;
  }
  if (ok) {
    showAlert('success', 'Week cleared.');
    await loadAvailability();
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
    openOvAdd(currentWeekStart());
    const ovStartDate = document.getElementById('ov_start_date');
    const ovEndDate = document.getElementById('ov_end_date');
    const ovDays = document.getElementById('ov_days');
    const ovStartTime = document.getElementById('ov_start_time');
    const ovEndTime = document.getElementById('ov_end_time');
    ovStartDate.value = start.toISOString().slice(0,10);
    ovEndDate.value = start.toISOString().slice(0,10);
    Array.from(ovDays.options).forEach(o => { o.selected = o.value === dayName; });
    ovStartTime.value = startTime;
    ovEndTime.value = endTime;
  } else {
    openAdd();
    winDays.value = dayName;
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

const calendar = initCalendar(handleSelect, handleEventEdit);

async function searchEmployees() {
  hideAlert();
  const q = employeeInput.value.trim();
  resultSelect.innerHTML = '';
  employeeIdField.value = '';
  if (q.length < 2) { return; }
  try {
    if (searchAbortController) { searchAbortController.abort(); }
    searchAbortController = new AbortController();
    const res = await fetch(`api/employees/search.php?search=${encodeURIComponent(q)}`, { signal: searchAbortController.signal });
    if (!res.ok) throw new Error('bad response');
    const data = await res.json();
    if (!data.ok) {
      showAlert('danger', data.error || 'Search failed');
      return;
    }
    const items = Array.isArray(data.items) ? data.items : [];
    for (const it of items) {
      const opt = document.createElement('option');
      opt.value = it.id;
      opt.textContent = `${it.name} (ID: ${it.id})`;
      resultSelect.appendChild(opt);
    }
    if (items.length === 1) {
      resultSelect.selectedIndex = 0;
      employeeIdField.value = String(items[0].id);
      updateProfileLink(items[0].id);
      await loadAvailability();
    }
  } catch (err) {
    console.error(err);
    showAlert('danger', 'Search failed');
  }
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
  const v = weekStartInput.value;
  if (v) return v;
  const d = new Date();
  const diff = (d.getDay() + 6) % 7;
  d.setDate(d.getDate() - diff);
  return d.toISOString().slice(0,10);
}

function updateWeekDisplay() {
  const ws = currentWeekStart();
  const start = new Date(ws + 'T00:00:00');
  const end = new Date(start);
  end.setDate(end.getDate() + 6);
  const opts = { month: 'short', day: 'numeric' };
  const startStr = start.toLocaleDateString(undefined, opts);
  const endStr = end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  if (weekDisplay) weekDisplay.textContent = `${startStr} – ${endStr}`;
}

function openAdd() {
  winTitle.textContent = 'Add Window';
  winId.value = '';
  winEmployee.value = `${currentEmployeeName()} (ID ${currentEmployeeId()})`;
  winDays.value = 'Monday';
  winDays.disabled = false;
  winReplaceIds.value = '';
  clearBlocks();
  addBlock('09:00', '17:00');
  winRecurring.checked = true;
  winStartDate.value = currentWeekStart();
  winEndDate.value = '';
  btnWeekdays.classList.remove('d-none');
  btnWeekdays.disabled = false;
  btnClearWeek.classList.remove('d-none');
  btnClearWeek.disabled = false;
  toggleRecurring();
  winModal.show();
}

function openEditDay(day) {
  winTitle.textContent = 'Edit Window';
  winId.value = '';
  winEmployee.value = `${currentEmployeeName()} (ID ${currentEmployeeId()})`;
  winDays.value = day;
  winDays.disabled = true;
  const arr = currentGroups[day] || [];
  winReplaceIds.value = arr.map(it => it.id).join(',');
  clearBlocks();
  if (arr.length) {
    arr.forEach(it => addBlock(it.start_time, it.end_time));
  } else {
    addBlock('09:00', '17:00');
  }
  winRecurring.checked = true;
  let sd = arr[0] && arr[0].start_date ? arr[0].start_date : currentWeekStart();
  winStartDate.value = sd;
  winEndDate.value = '';
  btnWeekdays.classList.add('d-none');
  btnWeekdays.disabled = true;
  btnClearWeek.classList.add('d-none');
  btnClearWeek.disabled = true;
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
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' },
    body: form
  });
  const data = await res.json();
  if (data && data.ok) {
    showAlert('success', 'Deleted.');
    await loadAvailability();
  } else {
    showAlert('danger', (data && (data.error || (data.errors||[]).join(', '))) || 'Delete failed');
  }
}

async function loadAvailability() {
  const eid = currentEmployeeId();
  const ws = currentWeekStart();
  const [data, jobs] = await Promise.all([
    fetchAvailability(eid, ws),
    eid ? fetchJobs(ws) : Promise.resolve([])
  ]);
  renderList(data.availability, data.overrides, {
    openEditDay,
    openCopy,
    delRow,
    openOvEdit,
    delOverride: ov => delOverride(ov, loadAvailability)
  });
  renderCalendar(calendar, data.availability, data.overrides, jobs, ws, eid);
}

winForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const eid = currentEmployeeId();
  if (!eid) { showAlert('warning', 'Select an employee first.'); return; }
  const day = winDays.value;
  if (!day) { showAlert('warning', 'Select a day.'); return; }
  const blocks = getBlocks();
  if (!blocks.length) { showAlert('warning', 'Add at least one block.'); return; }
  blocks.sort((a,b)=>a.start_time.localeCompare(b.start_time));
  for (const b of blocks) {
    if (b.start_time >= b.end_time) { showAlert('warning','Start must be before end.'); return; }
  }
  for (let i=0;i<blocks.length-1;i++) {
    if (blocks[i].end_time > blocks[i+1].start_time) { showAlert('warning','Blocks overlap or out of order.'); return; }
  }
  if (!winStartDate.value) { showAlert('warning', 'Start date required.'); return; }

  const payload = {
    csrf_token: CSRF,
    employee_id: eid,
    day_of_week: day,
    blocks,
    start_date: winStartDate.value
  };
  if (!winRecurring.checked) {
    if (winEndDate.value) payload.end_date = winEndDate.value;
  }
  if (winReplaceIds.value) {
    payload.replace_ids = winReplaceIds.value.split(',').map(v=>parseInt(v,10)).filter(Boolean);
  }

  let ok = false;
  try {
    if (DEBUG) {
      console.log('Request to api/availability/create.php', payload, document.cookie);
    }
    const res = await fetch('api/availability/create.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (DEBUG) {
      console.log('Response from api/availability/create.php', res.status, data);
    }
    ok = data && data.ok;
    if (!ok) {
      const msg = (data && data.message) ? data.message : 'Save failed';
      showAlert('danger', msg);
    }
  } catch (err) {
    const msg = (err && err.message) ? err.message : 'Save failed';
    showAlert('danger', msg);
    const logPayload = {
      csrf_token: CSRF,
      employee_id: eid,
      employee_name: currentEmployeeName(),
      message: err && err.message ? err.message : '',
      stack: err && err.stack ? err.stack : ''
    };
    if (DEBUG) {
      console.log('Request to api/availability/log_client_error.php', logPayload, document.cookie);
    }
    fetch('api/availability/log_client_error.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(logPayload)
    })
      .then(async res => {
        const data = await res.json().catch(() => ({}));
        if (DEBUG) {
          console.log('Response from api/availability/log_client_error.php', res.status, data);
        }
      })
      .catch(() => {});
  }

  if (ok) {
    showAlert('success', 'Saved.');
    winModal.hide();
    await loadAvailability();
  }
});

ovForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const eid = currentEmployeeId();
  if (!eid) { showAlert('warning', 'Select an employee first.'); return; }

  const id = document.getElementById('ov_id').value.trim();
  const startDate = document.getElementById('ov_start_date').value;
  const endDate = document.getElementById('ov_end_date').value || startDate;
  const ovDays = document.getElementById('ov_days');
  const days = Array.from(ovDays.options).filter(o => o.selected).map(o => o.value);
  const status = document.getElementById('ov_status').value;
  const type = document.getElementById('ov_type').value;
  const startTime = document.getElementById('ov_start_time').value;
  const endTime = document.getElementById('ov_end_time').value;
  const reason = document.getElementById('ov_reason').value;
  if (!startDate) { showAlert('warning', 'Start date required.'); return; }

  function* eachDate(s,e){ const d=new Date(s+'T00:00:00'); const end=new Date(e+'T00:00:00'); while(d<=end){ yield d.toISOString().slice(0,10); d.setDate(d.getDate()+1);} }
  const dayName = ds => daysOrder[(new Date(ds+'T00:00:00').getDay()+6)%7];
  let ok = true;
  if (id) {
    const payload = { csrf_token: CSRF, id: parseInt(id,10), employee_id: eid, date: startDate, status, type };
    if (startTime) payload.start_time = startTime;
    if (endTime) payload.end_time = endTime;
    if (reason) payload.reason = reason;
    if (DEBUG) {
      console.log('Request to api/availability/override.php', payload, document.cookie);
    }
    const res = await fetch('api/availability/override.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (DEBUG) {
      console.log('Response from api/availability/override.php', res.status, data);
    }
    ok = data && data.ok;
  } else {
    for (const d of eachDate(startDate, endDate)) {
      const dn = dayName(d);
      if (days.length && !days.includes(dn)) continue;
      const payload = { csrf_token: CSRF, employee_id: eid, date: d, status, type };
      if (startTime) payload.start_time = startTime;
      if (endTime) payload.end_time = endTime;
      if (reason) payload.reason = reason;
      if (DEBUG) {
        console.log('Request to api/availability/override.php', payload, document.cookie);
      }
      const res = await fetch('api/availability/override.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (DEBUG) {
        console.log('Response from api/availability/override.php', res.status, data);
      }
      if (!data || !data.ok) { ok = false; break; }
    }
  }

  if (ok) {
    showAlert('success', 'Saved.');
    const ovModal = bootstrap.Modal.getInstance(document.getElementById('ovModal'));
    ovModal?.hide();
    await loadAvailability();
  } else {
    showAlert('danger', 'Save failed');
  }
});

document.getElementById('btnAdd').addEventListener('click', openAdd);
btnAddOverride.addEventListener('click', () => openOvAdd(currentWeekStart()));
btnQuickPTO?.addEventListener('click', () => openOvAdd(currentWeekStart(), { status: 'UNAVAILABLE', type: 'PTO', reason: 'Vacation' }));

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
    if (DEBUG) {
      console.log('Request to api/availability/bulk_copy.php', payload, document.cookie);
    }
    const res = await fetch('api/availability/bulk_copy.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (DEBUG) {
      console.log('Response from api/availability/bulk_copy.php', res.status, data);
    }
    if (res.ok && data.ok) {
      FieldOpsToast.show('Schedule copied.');
      bulkCopyModal.hide();
      if (targets.includes(src)) {
        await loadAvailability();
      }
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
    if (DEBUG) {
      console.log('Request to api/availability/bulk_reset.php', payload, document.cookie);
    }
    const res = await fetch('api/availability/bulk_reset.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (DEBUG) {
      console.log('Response from api/availability/bulk_reset.php', res.status, data);
    }
    if (res.ok && data.ok) {
      FieldOpsToast.show('Week reset.');
      bulkResetModal.hide();
      if (ids.includes(currentEmployeeId())) {
        await loadAvailability();
      }
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
    if (DEBUG) {
      console.log('Request to api/availability/create.php', payload, document.cookie);
    }
    const res = await fetch('api/availability/create.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (DEBUG) {
      console.log('Response from api/availability/create.php', res.status, data);
    }
    if (res.ok && data.ok) {
      copyModal.hide();
      FieldOpsToast.show('Availability copied.');
      await loadAvailability();
    } else {
      FieldOpsToast.show('Copy failed', 'danger');
    }
  } catch {
    FieldOpsToast.show('Copy failed', 'danger');
  }
});

weekStartInput.value = currentWeekStart();
updateWeekDisplay();

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

btnEmpSearch.addEventListener('click', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(searchEmployees, 150);
});
employeeInput.addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(searchEmployees, 300);
});
resultSelect.addEventListener('change', () => {
  const val = resultSelect.value;
  employeeIdField.value = val;
  updateProfileLink(val);
  loadAvailability();
});
weekStartInput.addEventListener('change', () => {
  updateWeekDisplay();
  loadAvailability();
});
