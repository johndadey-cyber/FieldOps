const daysOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const tableBody = document.querySelector('#availabilityTable tbody');
const emptyState = document.getElementById('emptyState');
const subRowTpl = document.getElementById('subRowTpl');
const actionTpl = document.getElementById('actionTpl');
const overrideBody = document.querySelector('#overrideTable tbody');
const overrideEmpty = document.getElementById('overrideEmpty');
const ovActionTpl = document.getElementById('ovActionTpl');
const dayRows = {};
tableBody.querySelectorAll('tr.day-row').forEach(tr => { dayRows[tr.dataset.day] = tr; });

export let currentGroups = {};

let alertTimer;
const alertBox = document.getElementById('alertBox');

export function showAlert(kind, msg, autoHide = true) {
  alertBox.className = `alert alert-${kind}`;
  alertBox.textContent = msg;
  alertBox.classList.remove('d-none');
  if (autoHide) {
    if (alertTimer) clearTimeout(alertTimer);
    alertTimer = setTimeout(() => alertBox.classList.add('d-none'), 3000);
  }
}

export function hideAlert() {
  if (alertTimer) clearTimeout(alertTimer);
  alertBox.classList.add('d-none');
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
    badge.className = 'badge bg-secondary day-badge';
  }
  currentGroups = {};
}

function fillRow(tr, it, callbacks) {
  tr.dataset.id = it.id;
  tr.querySelector('.start').textContent = it.start_time;
  tr.querySelector('.end').textContent = it.end_time;
  const actions = actionTpl.content.firstElementChild.cloneNode(true);
  actions.querySelector('.btn-edit').addEventListener('click', () => callbacks.openEditDay(it.day_of_week));
  actions.querySelector('.btn-copy').addEventListener('click', () => callbacks.openCopy(it.day_of_week));
  actions.querySelector('.btn-del').addEventListener('click', () => callbacks.delRow(it));
  tr.querySelector('.actions').appendChild(actions);
}

export function renderList(availability, overrides, callbacks) {
  clearRows();
  if (!Array.isArray(availability) || availability.length === 0) {
    emptyState.classList.remove('d-none');
  } else {
    emptyState.classList.add('d-none');
  }

  const groups = {};
  for (const d of daysOrder) groups[d] = [];
  for (const it of availability) {
    if (groups[it.day_of_week]) groups[it.day_of_week].push(it);
  }
  currentGroups = groups;

  for (const d of daysOrder) {
    const arr = groups[d];
    arr.sort((a,b) => (a.start_time || '').localeCompare(b.start_time || ''));
    if (!arr.length) continue;
    fillRow(dayRows[d], arr[0], callbacks);
    let prev = dayRows[d];
    for (const it of arr.slice(1)) {
      const sub = subRowTpl.content.firstElementChild.cloneNode(true);
      fillRow(sub, it, callbacks);
      tableBody.insertBefore(sub, prev.nextSibling);
      prev = sub;
    }
  }

  overrideBody.innerHTML = '';
  if (!Array.isArray(overrides) || overrides.length === 0) {
    overrideEmpty.classList.remove('d-none');
  } else {
    overrideEmpty.classList.add('d-none');
    for (const ov of overrides) {
      const tr = document.createElement('tr');
      tr.dataset.id = ov.id;
      tr.innerHTML = `<td>${ov.date}</td><td>${ov.start_time || ''}</td><td>${ov.end_time || ''}</td><td>${ov.status}</td><td>${ov.type || ''}</td><td>${ov.reason || ''}</td><td class="actions"></td>`;
      const actions = ovActionTpl.content.firstElementChild.cloneNode(true);
      actions.querySelector('.btn-edit').addEventListener('click', () => callbacks.openOvEdit(ov));
      actions.querySelector('.btn-del').addEventListener('click', () => callbacks.delOverride(ov));
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
