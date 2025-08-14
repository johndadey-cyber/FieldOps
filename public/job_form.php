<?php

declare(strict_types=1);

require __DIR__ . '/_cli_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../models/JobType.php';
require_once __DIR__ . '/../models/Job.php';

$pdo       = getPDO();
$jobTypes  = JobType::all($pdo);
$statuses  = Job::allowedStatuses();
$__csrf    = csrf_token();
$today     = date('Y-m-d');

/** HTML escape */
function s(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Job</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
  <h1>Add Job</h1>
  <form method="post" action="job_save.php" id="jobForm" autocomplete="off" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">

    <!-- Section 1: Customer Information -->
    <section class="mb-4">
      <h2 class="h5">Customer Information</h2>
      <div class="mb-3 position-relative">
        <label for="customerSearch" class="form-label">Customer</label>
        <input type="text" id="customerSearch" class="form-control" placeholder="Start typing..." required>
        <input type="hidden" name="customer_id" id="customerId">
        <div id="customerResults" class="list-group position-absolute w-100"></div>
        <div class="invalid-feedback">Select a customer from the list.</div>
      </div>
    </section>

    <!-- Section 2: Job Details -->
    <section class="mb-4">
      <h2 class="h5">Job Details</h2>
      <div class="mb-3">
        <label for="description" class="form-label">Job Description</label>
        <textarea id="description" name="description" class="form-control" minlength="5" maxlength="255" required></textarea>
        <div class="invalid-feedback">Description must be between 5 and 255 characters.</div>
      </div>
      <div class="mb-3">
        <span class="form-label d-block mb-2">Job Types</span>
        <div class="row row-cols-2" id="jobTypes">
        <?php foreach ($jobTypes as $jt) : ?>
          <div class="col">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="job_types[]" value="<?= (int)$jt['id'] ?>" id="jt<?= (int)$jt['id'] ?>">
              <label class="form-check-label" for="jt<?= (int)$jt['id'] ?>"><?= s($jt['name']) ?></label>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
        <div class="invalid-feedback d-block" id="jobTypeError" style="display:none">Select at least one job type.</div>
      </div>
    </section>

    <!-- Section 3: Scheduling -->
    <section class="mb-4">
      <h2 class="h5">Scheduling</h2>
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label for="scheduled_date" class="form-label">Scheduled Date</label>
          <input type="date" id="scheduled_date" name="scheduled_date" class="form-control" min="<?= s($today) ?>">
        </div>
        <div class="col-md-4">
          <label for="scheduled_time" class="form-label">Scheduled Time</label>
          <input type="time" id="scheduled_time" name="scheduled_time" class="form-control">
        </div>
        <div class="col-md-4">
          <label for="duration_minutes" class="form-label">Duration (minutes)</label>
          <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="1" step="1" value="60">
        </div>
      </div>
    </section>



    <!-- Section 5: Actions -->
    <section class="mt-4">
      <button type="submit" class="btn btn-primary">Save Job</button>
      <a href="jobs.php" class="btn btn-secondary">Cancel</a>
    </section>
  </form>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  const searchInput = document.getElementById('customerSearch');
  const resultsDiv = document.getElementById('customerResults');
  const customerIdInput = document.getElementById('customerId');
  let results = [];
  let activeIndex = -1;

  async function fetchCustomers(q) {
    try {
      const resp = await fetch(`api/customer_search.php?q=${encodeURIComponent(q)}`);
      const type = resp.headers.get('content-type') || '';
      if (!resp.ok || !type.includes('application/json')) {
        console.error('Customer search request failed', resp.status, resp.statusText);
        return [];
      }
      return await resp.json();
    } catch (err) {
      console.error('Failed to load customer search results', err);
      return [];
    }
  }

  function renderResults() {
    resultsDiv.innerHTML = '';
    activeIndex = -1;
    results.forEach((c, idx) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'list-group-item list-group-item-action';
      item.textContent = `${c.first_name} ${c.last_name} — ${c.address_line1}, ${c.city}`;
      item.addEventListener('click', () => selectCustomer(idx));
      resultsDiv.appendChild(item);
    });
  }

  searchInput.addEventListener('input', async function () {
    const q = this.value.trim();
    customerIdInput.value = '';
    if (q.length < 2) { resultsDiv.innerHTML = ''; return; }
    results = await fetchCustomers(q);
    renderResults();
  });

  searchInput.addEventListener('keydown', function(e) {
    const items = resultsDiv.querySelectorAll('.list-group-item');
    if (items.length === 0) return;
    if (e.key === 'ArrowDown') {
      activeIndex = (activeIndex + 1) % items.length;
      updateActive(items); e.preventDefault();
    } else if (e.key === 'ArrowUp') {
      activeIndex = (activeIndex - 1 + items.length) % items.length;
      updateActive(items); e.preventDefault();
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      selectCustomer(activeIndex); e.preventDefault();
    }
  });

  function updateActive(items) {
    items.forEach((el, idx) => {
      el.classList.toggle('active', idx === activeIndex);
    });
  }

  function selectCustomer(idx) {
    const c = results[idx];
    searchInput.value = `${c.first_name} ${c.last_name} — ${c.address_line1}, ${c.city}`;
    customerIdInput.value = c.id;
    resultsDiv.innerHTML = '';
  }

  document.getElementById('jobForm').addEventListener('submit', function(e) {
    const jtChecks = document.querySelectorAll('input[name="job_types[]"]:checked');
    const jobTypeError = document.getElementById('jobTypeError');
    const timeVal = document.getElementById('scheduled_time').value;
    const dateVal = document.getElementById('scheduled_date').value;
    let valid = true;

    if (!customerIdInput.value) {
      searchInput.classList.add('is-invalid');
      valid = false;
    } else {
      searchInput.classList.remove('is-invalid');
    }

    if (jtChecks.length === 0) {
      jobTypeError.style.display = 'block';
      valid = false;
    } else {
      jobTypeError.style.display = 'none';
    }

    if (timeVal && !dateVal) {
      document.getElementById('scheduled_date').classList.add('is-invalid');
      valid = false;
    } else {
      document.getElementById('scheduled_date').classList.remove('is-invalid');
    }

    if (!valid) e.preventDefault();
  });
  </script>
</body>
</html>
