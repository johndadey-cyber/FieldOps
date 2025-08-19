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
export let allGroups = {};

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
    row.querySelector('.effective').textContent = '';
    row.querySelector('.actions').innerHTML = '';
    const badge = row.querySelector('.day-badge');
    badge.className = 'badge bg-secondary day-badge';
  }
  currentGroups = {};
  allGroups = {};
}
function fillRow(tr, it, callbacks, weekStart, latest) {
  tr.dataset.id = it.id;
  tr.querySelector('.start').textContent = it.start_time;
  tr.querySelector('.end').textContent = it.end_time;
  tr.querySelector('.effective').textContent = it.start_date || '';
  const actions = actionTpl.content.firstElementChild.cloneNode(true);
  actions.querySelector('.btn-edit').addEventListener('click', () => callbacks.openEditDay(it.day_of_week, it.start_date || ''));
  actions.querySelector('.btn-copy').addEventListener('click', () => callbacks.openCopy(it.day_of_week));
  actions.querySelector('.btn-del').addEventListener('click', () => callbacks.delRow(it));
  tr.querySelector('.actions').appendChild(actions);
  const sd = it.start_date || '';
  if (sd > weekStart) {
    tr.classList.add('table-info');
  } else if (sd && sd < latest) {
    tr.classList.add('text-muted');
  }
}

export function renderList(availability, overrides, callbacks, opts = {}) {
  const weekStart = opts.weekStart || '';
  clearRows();
  if (!Array.isArray(availability) || availability.length === 0) {
    emptyState.classList.remove('d-none');
  } else {
    emptyState.classList.add('d-none');
  }

  const groupsAll = {};
  for (const d of daysOrder) groupsAll[d] = [];
  for (const it of availability) {
    if (groupsAll[it.day_of_week]) groupsAll[it.day_of_week].push(it);
  }
  allGroups = groupsAll;

  const latestStartPerDay = {};
  currentGroups = {};
  for (const d of daysOrder) {
    const arr = groupsAll[d];
    arr.sort((a,b) => {
      const sa = a.start_date || '';
      const sb = b.start_date || '';
      const cmp = sa.localeCompare(sb);
      return cmp !== 0 ? cmp : (a.start_time || '').localeCompare(b.start_time || '');
    });
    let latest = '';
    for (const it of arr) {
      const sd = it.start_date || '';
      if (!sd || sd <= weekStart) {
        if (sd > latest) latest = sd;
      }
    }
    latestStartPerDay[d] = latest;
    currentGroups[d] = arr.filter(it => {
      const sd = it.start_date || '';
      return (!sd && latest === '') || sd === latest;
    });
  }

  for (const d of daysOrder) {
    const arr = groupsAll[d];
    if (!arr.length) continue;
    const latest = latestStartPerDay[d];
    const current = currentGroups[d];
    const others = arr.filter(it => !current.includes(it)).sort((a,b) => {
      const sa = a.start_date || '';
      const sb = b.start_date || '';
      return sa.localeCompare(sb);
    });
    const first = current[0] || arr[0];
    fillRow(dayRows[d], first, callbacks, weekStart, latest);
    let prev = dayRows[d];
    const queue = [...current.slice(1), ...others];
    for (const it of queue) {
      const sub = subRowTpl.content.firstElementChild.cloneNode(true);
      fillRow(sub, it, callbacks, weekStart, latest);
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
