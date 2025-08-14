<?php
declare(strict_types=1);
/**
 * Version: 2025-08-13.2
 * Release: Post-Rollback Safe Eligibility — UI-Only Filtering
 * File: /public/partials/assignments_modal.php
 * Purpose: Assign Employees modal shell + controls + containers for dynamic rendering.
 */
?>
<!-- Assign Employees Modal -->
<div class="modal fade" id="assignmentsModal" tabindex="-1" aria-labelledby="assignmentsModalLabel" aria-hidden="true" role="dialog">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <!-- Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="assignmentsModalLabel">
          Assign Employees <span id="assign-job-label" class="text-muted"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Body -->
      <div class="modal-body p-0">
        <!-- Container expected by assignments.js -->
        <div id="assign-modal" role="dialog" aria-modal="true" data-testid="assign-modal" class="d-flex flex-column" style="min-height:420px">

          <!-- Toolbar -->
          <div class="d-flex align-items-center gap-2 flex-wrap pb-2 px-3 pt-3 border-bottom">
            <div class="form-check me-3">
              <input class="form-check-input" type="checkbox" id="toggle-qualified" data-testid="toggle-qualified">
              <label class="form-check-label" for="toggle-qualified">Show qualified only</label>
            </div>

            <div class="d-flex align-items-center gap-2">
              <label class="form-label m-0">Filter by Skill</label>
              <select id="filter-skill" class="form-select form-select-sm" style="min-width:180px" data-testid="filter-skill">
                <option value="">All skills</option>
              </select>
            </div>

            <div class="d-flex align-items-center gap-2">
              <label class="form-label m-0">Sort by</label>
              <select id="sort-by" class="form-select form-select-sm" style="min-width:160px" data-testid="sort-by">
                <option value="distance">Distance</option>
                <option value="dayload">Day load</option>
                <option value="name">Name</option>
              </select>
            </div>

            <div class="ms-auto" style="min-width:260px">
              <input id="search" class="form-control form-control-sm" type="search"
                     placeholder="Search name, email, phone" data-testid="search">
            </div>
          </div>

          <!-- Job context -->
          <div class="small text-muted py-2 px-3" id="job-context"></div>

          <!-- Banners -->
          <div id="banner-error" class="alert alert-danger d-none mx-3 my-2" role="alert" data-testid="banner-error"></div>
          <div id="banner-empty" class="alert alert-light border d-none mx-3 my-2" role="status">
            No matching employees. Try clearing filters.
          </div>

          <!-- Candidate list (filled by JS) -->
          <div id="candidate-list" class="list-group flex-grow-1 overflow-auto mx-3 mb-3" style="max-height:55vh;"></div>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button id="btn-assign-selected" class="btn btn-primary" disabled data-testid="btn-assign-selected">
          Assign Selected (<span id="selected-count">0</span>)
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm dialog for risky assignments (force=true) -->
<div class="modal fade" id="assignConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Confirm assignment</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="assign-confirm-summary" class="small"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Review</button>
        <button id="btn-assign-anyway" type="button" class="btn btn-danger btn-sm">Assign anyway</button>
      </div>
    </div>
  </div>
</div>

<!-- Robust hook: load data when the modal is about to show -->
<script>
  (function () {
    var modalEl = document.getElementById('assignmentsModal');
    if (!modalEl) return;

    // Fire every time the modal opens, with the button that triggered it
    modalEl.addEventListener('show.bs.modal', function (event) {
      var trigger = event.relatedTarget; // the "Assign" button
      var jobId = Number(
        (trigger && trigger.dataset && trigger.dataset.jobId) ||
        (trigger && trigger.getAttribute && trigger.getAttribute('data-job-id')) || 0
      );

      // Optional: show job number in header
      var label = document.getElementById('assign-job-label');
      if (label && jobId) label.textContent = 'Job #' + jobId;

      // Kick off fetch/render (defined in assignments.js)
      if (window.openAssignModal && jobId > 0) {
        window.openAssignModal(jobId);
      } else {
        console.warn('openAssignModal is missing or jobId invalid:', { jobId: jobId });
      }
    });
  })();
</script>

<!-- These must be OUTSIDE the hook and AFTER the modal markup -->
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
  crossorigin="anonymous"></script>
<script src="/js/assignments.js?v=20250812"></script>
