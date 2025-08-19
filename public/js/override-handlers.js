import { showAlert } from './list-render.js';

const ovModalEl = document.getElementById('ovModal');
const ovModal = new bootstrap.Modal(ovModalEl);
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

function updateOvTimeFields() {
  const show = ovStatus.value === 'PARTIAL';
  ovStartTime.closest('.ov-time').classList.toggle('d-none', !show);
  ovEndTime.closest('.ov-time').classList.toggle('d-none', !show);
  if (!show) {
    ovStartTime.value = '';
    ovEndTime.value = '';
  }
}
ovStatus.addEventListener('change', updateOvTimeFields);

export function openOvAdd(weekStart, opts = {}) {
  ovTitle.textContent = 'Add Override';
  ovId.value = '';
  ovStartDate.value = weekStart;
  ovEndDate.value = '';
  Array.from(ovDays.options).forEach(o => { o.selected = false; });
  ovStartTime.value = '';
  ovEndTime.value = '';
  ovStatus.value = opts.status ?? 'UNAVAILABLE';
  ovType.value = opts.type ?? 'PTO';
  ovReason.value = opts.reason ?? '';
  updateOvTimeFields();
  ovModal.show();
}

export function openOvEdit(ov) {
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
  updateOvTimeFields();
  ovModal.show();
}

export async function delOverride(ov, loadAvailability) {
  if (!confirm(`Delete override on ${ov.date}?`)) return;
  const res = await fetch(`api/availability/override_delete.php?id=${ov.id}`, {
    method: 'DELETE',
    credentials: 'same-origin'
  });
  const data = await res.json();
  if (data && data.ok) {
    showAlert('success', 'Deleted.');
    await loadAvailability();
  } else {
    showAlert('danger', 'Delete failed');
  }
}
