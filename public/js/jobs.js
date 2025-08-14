// /public/js/jobs.js
// Version: 2025-08-13.b
(() => {
  function ready(fn){ if(document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

  ready(() => {
    // Try multiple selectors so it works even if ID was missed
    const $tbody =
      document.getElementById('jobs-tbody') ||
      document.querySelector('#jobs-table tbody') ||
      document.querySelector('[data-testid="jobs-tbody"]');

    const $btn    = document.getElementById('btnRefreshJobs');     // Optional
    const $status = document.getElementById('jobsRefreshStatus');  // Optional

    if (!$tbody) {
      console.warn('[jobs.js] jobs tbody not found; add id="jobs-tbody" to your <tbody>.');
      return;
    }

    // Resolve the partial path (use relative by default; override here if your page lives elsewhere)
    // Example: const PARTIAL = '/public/jobs_table.php';
    const PARTIAL = 'jobs_table.php';

    function currentParams() {
      const p = new URLSearchParams();
      const $days   = document.getElementById('filter-days');
      const $status = document.getElementById('filter-status');
      const $search = document.getElementById('filter-search');
      if ($days && $days.value)            p.set('days', String(parseInt($days.value, 10) || 0));
      if ($status && $status.value)        p.set('status', $status.value);
      if ($search && $search.value.trim()) p.set('search', $search.value.trim());
      return p;
    }

    async function reloadJobsInternal() {
      const base = new URL(PARTIAL, window.location.href);
      const qp   = currentParams();
      if ([...qp.keys()].length) base.search = '?' + qp.toString();

      try {
        if ($btn) { $btn.disabled = true; $btn.dataset.prevLabel = $btn.textContent; $btn.textContent = 'Refreshing…'; }
        if ($status) $status.textContent = 'Refreshing…';

        const res  = await fetch(base.toString(), { credentials: 'same-origin' });
        const html = await res.text();
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        $tbody.innerHTML = html;

        if ($status) $status.textContent = 'Updated';
      } catch (err) {
        console.error('[jobs.js] reload failed:', err);
        if ($status) $status.textContent = 'Failed to refresh';
      } finally {
        if ($btn) { $btn.disabled = false; $btn.textContent = $btn.dataset.prevLabel || 'Refresh'; }
        // Clean any stray modal backdrops
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
      }
    }

    // Expose globally
    window.reloadJobs = reloadJobsInternal;

    // Wire the button
    if ($btn) $btn.addEventListener('click', (e) => { e.preventDefault(); reloadJobsInternal(); });

    // Refresh after successful assignment
    window.addEventListener('assignments:updated', reloadJobsInternal);
  });
})();
