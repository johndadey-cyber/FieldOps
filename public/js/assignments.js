// public/js/assignments.js
// Version: 2025-08-13f
// Release: Post-Rollback Safe Eligibility — UI-Only Filtering
// Fixes:
// - Modal close/backdrop/focus hang after submit
// - Jobs table refresh signal
// - Local time rendering (client fallback with org TZ)
// - Search fallback, tooltips, skills truncation, multi-select count

(() => {
  const API_BASE = '/api/assignments';
  const ALLOW_OVERRIDE = true; // set false to hard-disable risky selections

  // If the API doesn't provide start_local/end_local, render using this org timezone:
  const ORG_TZ = 'America/Chicago'; // change if your business operates in a different TZ

  // Elements
  const $list      = document.getElementById('candidate-list');
  const $bannerErr = document.getElementById('banner-error');
  const $bannerEmp = document.getElementById('banner-empty');
  const $btnAssign = document.getElementById('btn-assign-selected');
  const $selCount  = document.getElementById('selected-count');
  const $jobCtx    = document.getElementById('job-context');

  const $chkQualified = document.getElementById('toggle-qualified');
  const $selSkill     = document.getElementById('filter-skill');
  const $selSort      = document.getElementById('sort-by');
  const $txtSearch    = document.getElementById('search');

  // State
  let JOB_ID = null;
  let payload = null;          // last eligible payload
  let selected = new Set();    // employeeIds
  let isPosting = false;
  let searchMode = 'name';     // 'name' or 'name_email_phone' when fields exist

  // ---------- Modal lifecycle helpers ----------
  function closeModalsAndCleanup() {
    try { document.activeElement && document.activeElement.blur(); } catch (_){}

    const $confirm = document.getElementById('assignConfirmModal');
    const $assign  = document.getElementById('assignmentsModal');

    try { bootstrap?.Modal.getOrCreateInstance($confirm)?.hide(); } catch(_){}
    try { bootstrap?.Modal.getOrCreateInstance($assign )?.hide(); } catch(_){}

    // Clear any lingering artifacts from stacked modals
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');

    // Defensive: ensure aria-hidden isn't stuck
    if ($assign) $assign.removeAttribute('aria-hidden');
  }

  // Bind cleanup on hide (defensive)
  ['assignConfirmModal','assignmentsModal'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('hidden.bs.modal', () => {
      document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
    });
  });

  // ---------- Public entrypoint (called by the modal show hook) ----------
  window.openAssignModal = function openAssignModal(jobId) {
    JOB_ID = Number(jobId);
    selected.clear();
    updateSelectedCount();
    $chkQualified.checked = false;
    $selSkill.value = '';
    $selSort.value = 'distance';
    $txtSearch.value = '';
    fetchEligible();
  };

  function eligibleURL() {
    const u = new URL(location.origin + `${API_BASE}/eligible.php`);
    u.searchParams.set('jobId', String(JOB_ID));
    u.searchParams.set('sort', ($selSort.value || 'distance'));
    return u.toString();
  }

  async function fetchEligible() {
    showError('');
    showEmpty(false);
    $list.innerHTML = '<div class="p-3 text-muted">Loading…</div>';

    try {
      const res = await fetch(eligibleURL(), { credentials: 'same-origin' });
      const json = await res.json();
      if (!res.ok || !json || json.ok === false) {
        throw new Error(json?.error || `HTTP ${res.status}`);
      }
      payload = json;

      // Determine search capability & placeholder
      const hasAnyContact = (payload.employees || []).some(e =>
        e?.meta?.email || e?.meta?.phone
      );
      searchMode = hasAnyContact ? 'name_email_phone' : 'name';
      if ($txtSearch) {
        $txtSearch.placeholder = hasAnyContact ? 'Search name, email, phone' : 'Search name';
      }

      // Header job context
      setJobContext(payload.job);

      // Build filter options
      buildSkillFilter(payload);

      // Render
      renderList();

      // Enable tooltips after first render
      initTooltips();
    } catch (e) {
      showError(`Couldn't load candidates. Please retry. (${e.message})`);
      $list.innerHTML = '';
    }
  }

  function setJobContext(job = {}) {
    // Prefer API-supplied label or local fields; otherwise, render UTC in org time zone
    const label =
      job.windowLabel ||
      formatRange(job.start_local, job.end_local, job.timezone || ORG_TZ, job.scheduledDate) ||
      formatRange(job.start,       job.end,       ORG_TZ,              job.scheduledDate) ||
      (job.scheduledDate || '');

    const desc = (job.description || '').trim();
    $jobCtx.textContent = desc ? `${desc} • ${label}` : label;
  }

  function formatRange(startISO, endISO, tz, fallbackDate) {
    if (!startISO || !endISO) return null;
    try {
      const opts = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz || undefined };
      const fmt = new Intl.DateTimeFormat(undefined, opts);
      const s = new Date(startISO);
      const e = new Date(endISO);
      const dateStr = fallbackDate ? `${fallbackDate}, ` : '';
      return `${dateStr}${fmt.format(s)}—${fmt.format(e)}`;
    } catch { return null; }
  }

  function buildSkillFilter(data) {
    const skillNames = new Map(); // id -> name
    (data.job?.requiredJobTypeIds || []).forEach((id, i) => {
      const name = (data.job?.requiredJobTypeNames || [])[i] || `Skill ${id}`;
      if (id != null) skillNames.set(id, name);
    });
    (data.employees || []).forEach(emp => {
      (emp.skills || []).forEach(s => {
        if (s?.id) skillNames.set(s.id, s.name || `Skill ${s.id}`);
      });
    });

    const prev = $selSkill.value;
    $selSkill.innerHTML = '<option value="">All skills</option>';
    [...skillNames.entries()]
      .sort((a,b) => String(a[1]).localeCompare(String(b[1])))
      .forEach(([id, name]) => {
        const opt = document.createElement('option');
        opt.value = String(id);
        opt.textContent = name;
        $selSkill.appendChild(opt);
      });
    if ([...$selSkill.options].some(o => o.value === prev)) $selSkill.value = prev;
  }

  function renderList() {
    if (!payload) return;

    const params = {
      showQualifiedOnly: $chkQualified.checked,
      skillId: $selSkill.value ? Number($selSkill.value) : null,
      sort: $selSort.value || 'distance',
      search: ($txtSearch.value || '').trim().toLowerCase(),
    };

    let items = [...(payload.employees || [])];

    // Search
    if (params.search) {
      items = items.filter(e => {
        const name = `${e.first_name || ''} ${e.last_name || ''}`.toLowerCase();
        if (name.includes(params.search)) return true;
        if (searchMode === 'name_email_phone') {
          const email = (e.meta?.email || '').toLowerCase();
          const phone = (e.meta?.phone || '').toLowerCase();
          if (email.includes(params.search) || phone.includes(params.search)) return true;
        }
        return false;
      });
    }

    // Filter by skill
    if (params.skillId) {
      items = items.filter(e => (e.skills || []).some(s => s.id === params.skillId));
    }

    // Show qualified only
    if (params.showQualifiedOnly) {
      items = items.filter(e => e.qualified === true);
    }

    // Sorting (distance → dayLoad → name)
    const norm = s => (s || '').toLowerCase().trim();
    items.sort((a,b) => {
      const nameA = norm(`${a.last_name} ${a.first_name}`);
      const nameB = norm(`${b.last_name} ${b.first_name}`);
      const distA = a.distanceKm, distB = b.distanceKm;
      const loadA = Number(a.dayLoad || 0), loadB = Number(b.dayLoad || 0);

      if (params.sort === 'name') return nameA.localeCompare(nameB);
      if (params.sort === 'dayload') {
        if (loadA !== loadB) return loadA - loadB;
        if (distA == null && distB != null) return 1;
        if (distB == null && distA != null) return -1;
        if (distA != null && distB != null && distA !== distB) return distA - distB;
        return nameA.localeCompare(nameB);
      }
      // distance default
      if (distA == null && distB != null) return 1;
      if (distB == null && distA != null) return -1;
      if (distA != null && distB != null && distA !== distB) return distA - distB;
      if (loadA !== loadB) return loadA - loadB;
      return nameA.localeCompare(nameB);
    });

    // Render
    $list.innerHTML = '';
    if (!items.length) {
      showEmpty(true);
      return;
    }
    showEmpty(false);

    const frag = document.createDocumentFragment();
    items.forEach(e => frag.appendChild(renderRow(e)));
    $list.appendChild(frag);

    // Reapply selection
    selected.forEach(id => {
      const cb = document.getElementById(`row-${id}-checkbox`);
      if (cb) cb.checked = true;
    });

    // Init tooltips on this batch
    initTooltips();
  }

  function renderRow(e) {
    const li = document.createElement('div');
    li.className = 'list-group-item';
    li.setAttribute('data-testid', `row-${e.id}`);
    li.id = `row-${e.id}`;

    const fullName = `${e.first_name || ''} ${e.last_name || ''}`.trim();
    const pillClass = e.qualified ? 'badge text-bg-success' : 'badge text-bg-secondary';
    const pillText  = e.qualified ? 'qualified' : 'not qualified';

    // availability
    const av = e.availability || {status:'none', window:{start:null, end:null}};
    const avIcon = av.status === 'full' ? '✅' : (av.status === 'partial' ? '⚠️' : '❌');
    const avLabel = `${avIcon} ${av.window?.start || '—'}–${av.window?.end || '—'} (${av.status || 'none'})`;

    // conflicts
    const conflicts = Array.isArray(e.conflicts) ? e.conflicts : [];
    const conflictLine = conflicts.length
      ? `<div class="small text-danger">⚠ Conflict: ${conflicts.map(c=>`Job #${c.jobId} (${c.start}–${c.end})`).join(', ')}</div>`
      : '';

    // skills + missing (truncate + tooltip)
    const skills = (e.skills || []).map(s => s.name).filter(Boolean);
    const missing = (e.missingSkills || []).map(s => s.name).filter(Boolean);
    const trunc = truncateList(skills, 6);
    const skillsHtml = trunc.extra > 0
      ? `<span title="${escapeHtml(skills.join(', '))}" data-bs-toggle="tooltip">${escapeHtml(trunc.label)}</span>`
      : escapeHtml(trunc.label || '—');

    const missingLine = !e.qualified && missing.length
      ? `<div class="small text-danger">Missing: ${escapeHtml(missing.join(', '))}</div>` : '';

    // distance/load with tooltip
    const dl = `
      <span class="text-muted"> • </span>
      <span class="text-muted" title="Jobs on this day" data-bs-toggle="tooltip" data-bs-placement="top">
        load: ${Number(e.dayLoad || 0)}
      </span>`;

    const km = e.distanceKm != null ? `${e.distanceKm} km` : '—';
    const rightMeta = `<span class="text-muted">${km}</span>${dl}`;

    // checkbox rules
    const hasRisk = (!e.qualified) || av.status !== 'full' || conflicts.length > 0;
    const disabled = (!ALLOW_OVERRIDE && hasRisk);
    const titleWhy = disabled
      ? (!e.qualified ? 'Not qualified' : (av.status !== 'full' ? 'Unavailable' : 'Conflict'))
      : '';

    li.innerHTML = `
      <div class="d-flex align-items-start gap-2">
        <div class="pt-1">
          <input type="checkbox" class="form-check-input" id="row-${e.id}-checkbox"
                 data-testid="row-${e.id}-checkbox" ${disabled ? 'disabled' : ''} ${titleWhy ? `title="${titleWhy}"` : ''}>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <strong>${escapeHtml(fullName)}</strong>
              <span class="${pillClass}" data-testid="row-${e.id}-pill">${pillText}</span>
            </div>
            <div class="small">${rightMeta}</div>
          </div>
          <div class="small">Skills: ${skillsHtml}</div>
          ${missingLine}
          <div class="small">Availability: ${avLabel}</div>
          ${conflictLine}
        </div>
      </div>
    `;

    const cb = li.querySelector('input[type="checkbox"]');
    cb.addEventListener('change', () => {
      if (cb.checked) selected.add(e.id); else selected.delete(e.id);
      updateSelectedCount();
    });

    return li;
  }

  function updateSelectedCount() {
    const n = selected.size;
    if ($selCount) $selCount.textContent = String(n);
    if ($btnAssign) {
      $btnAssign.disabled = isPosting || n === 0;
      $btnAssign.textContent = `Assign Selected (${n})`;
    }
  }

  function showError(msg) {
    if (!$bannerErr) return;
    if (!msg) {
      $bannerErr.classList.add('d-none'); $bannerErr.textContent = '';
    } else {
      $bannerErr.classList.remove('d-none'); $bannerErr.textContent = msg;
    }
  }
  function showEmpty(show) {
    if ($bannerEmp) $bannerEmp.classList.toggle('d-none', !show);
  }

  function truncateList(arr, visible = 6) {
    if (!arr || !arr.length) return { label: '—', extra: 0 };
    if (arr.length <= visible) return { label: arr.join(', '), extra: 0 };
    const head = arr.slice(0, visible).join(', ');
    return { label: `${head} … +${arr.length - visible} more`, extra: arr.length - visible };
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function initTooltips() {
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      try { new bootstrap.Tooltip(el); } catch(_) {}
    });
  }

  // ---------- Submit with risk confirm (force flow) ----------
  async function submit(force = false) {
    if (selected.size === 0 || isPosting) return;
    isPosting = true; updateSelectedCount();

    const btnLabelPrev = $btnAssign ? $btnAssign.textContent : '';
    if ($btnAssign) $btnAssign.textContent = 'Assigning…';

    try {
      const res = await fetch(`/api/assignments/assign.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ jobId: JOB_ID, employeeIds: [...selected], force })
      });
      const json = await res.json();

      if (res.status === 409 && !force) {
        // Summarize issues and show confirm modal
        const counts = { time_conflict:0, partial_availability:0, unavailable_for_job_window:0, missing_required_skills:0, inactive_employee:0, wrong_role:0 };
        (json.details || []).forEach(d => (d.issues || []).forEach(i => { if (counts[i] !== undefined) counts[i]++; }));
        const lines = [];
        if (counts.time_conflict) lines.push(`• ${counts.time_conflict} with time conflicts`);
        if (counts.partial_availability) lines.push(`• ${counts.partial_availability} with partial availability`);
        if (counts.unavailable_for_job_window) lines.push(`• ${counts.unavailable_for_job_window} unavailable`);
        if (counts.missing_required_skills) lines.push(`• ${counts.missing_required_skills} missing required skills`);
        if (counts.wrong_role) lines.push(`• ${counts.wrong_role} wrong role`);
        if (counts.inactive_employee) lines.push(`• ${counts.inactive_employee} inactive`);

        const $sum = document.getElementById('assign-confirm-summary');
        if ($sum) {
          $sum.innerHTML = lines.length
            ? `<div class="mb-2">Some selections have issues:</div><div>${lines.join('<br>')}</div>`
            : `Some selections may have issues. Proceed anyway?`;
        }
        bootstrap?.Modal.getOrCreateInstance(document.getElementById('assignConfirmModal'))?.show();
        return; // stop; wait for "assign anyway"
      }

      if (!res.ok || json.ok === false) throw new Error(json?.error || `HTTP ${res.status}`);

      // Success → close modal(s) & notify page to refresh jobs table
      closeModalsAndCleanup();
      window.dispatchEvent(new CustomEvent('assignments:updated', { detail: { jobId: JOB_ID } }));

      // Reset UI
      selected.clear();
      updateSelectedCount();
      showError('');
    } catch (e) {
      showError(`Assignment failed. Please retry. (${e.message})`);
    } finally {
      isPosting = false;
      if ($btnAssign) $btnAssign.textContent = btnLabelPrev || `Assign Selected (${selected.size})`;
      updateSelectedCount();
    }
  }

  // Events
  [$chkQualified, $selSkill, $selSort].forEach(el => el.addEventListener('change', renderList));
  $txtSearch.addEventListener('input', debounce(renderList, 150));
  $btnAssign.addEventListener('click', () => submit(false));
  document.getElementById('btn-assign-anyway')?.addEventListener('click', () => {
    try { bootstrap?.Modal.getOrCreateInstance(document.getElementById('assignConfirmModal'))?.hide(); } catch(_){}
    submit(true);
  });

  function debounce(fn, ms) {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }
})();
