<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
require_once __DIR__ . '/_csrf.php';

/** HTML escape */
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$weekStart  = date('Y-m-d', strtotime('monday this week'));
$__csrf     = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Availability Onboarding</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
</head>
<body class="p-4" data-emp="<?= s((string)$employeeId) ?>" data-csrf="<?= s($__csrf) ?>">
  <div class="container" style="max-width:500px;">
    <h1 class="h3 mb-3">Availability Onboarding</h1>
    <div class="mb-3">
      <label class="form-label">Week Starting</label>
      <input type="date" id="weekStart" class="form-control" value="<?= s($weekStart) ?>">
    </div>
    <div id="step" class="card">
      <div class="card-body">
        <h2 id="dayTitle" class="h5 mb-3"></h2>
        <div class="row g-2 mb-3">
          <div class="col">
            <label class="form-label">Start Time</label>
            <input type="time" id="startTime" class="form-control" value="09:00">
          </div>
          <div class="col">
            <label class="form-label">End Time</label>
            <input type="time" id="endTime" class="form-control" value="17:00">
          </div>
        </div>
        <button id="nextBtn" class="btn btn-primary w-100">Next</button>
      </div>
    </div>
  </div>
  <script>
  (function(){
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    let idx = 0;
    const empId = document.body.dataset.emp || '';
    const csrf  = document.body.dataset.csrf || '';
    const dayTitle = document.getElementById('dayTitle');
    const startInput = document.getElementById('startTime');
    const endInput = document.getElementById('endTime');
    const nextBtn = document.getElementById('nextBtn');

    function showDay(){
      dayTitle.textContent = days[idx];
      nextBtn.textContent = idx === days.length - 1 ? 'Finish' : 'Next';
    }

    function saveAndNext(){
      if(!empId){ alert('Employee ID required'); return; }
      const fd = new FormData();
      fd.append('csrf_token', csrf);
      fd.append('employee_id', empId);
      fd.append('day_of_week', days[idx]);
      fd.append('start_time', startInput.value);
      fd.append('end_time', endInput.value);
      fd.append('initial_setup', '1');
      fetch('availability_save.php?json=1', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      }).then(r=>r.json()).then(data=>{
        if(!data || !data.ok){ alert('Save failed'); return; }
        idx++;
        if(idx < days.length){
          showDay();
        } else {
          window.location.href = 'availability_manager.php?employee_id=' + encodeURIComponent(empId);
        }
      }).catch(()=>alert('Request failed'));
    }

    nextBtn.addEventListener('click', function(e){ e.preventDefault(); saveAndNext(); });
    showDay();
  })();
  </script>
</body>
</html>
