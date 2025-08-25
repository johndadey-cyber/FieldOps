<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

if (getenv('APP_ENV') !== 'test') {
  http_response_code(403);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'guest') {
  switch ($role) {
    case 'admin':
      header('Location: /admin/index.php');
      break;
    case 'field_tech':
      header('Location: /tech_jobs.php');
      break;
    default:
      header('Location: /jobs.php');
      break;
  }
  exit;
}

$title = 'Log In';
require __DIR__ . '/../partials/header.php';
?>
  <div class="row justify-content-center">
    <div class="col-md-4">
      <div class="alert alert-warning text-center mb-3">Integration Environment</div>
      <form id="login-form" action="/api/login.php" method="post" class="card card-body">
        <div class="mb-3">
          <label for="username" class="form-label">Username or Email</label>
          <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <?php require __DIR__ . '/../partials/csrf_input.php'; ?>
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-primary">Log In</button>
        </div>
        <div class="text-center"><a href="/forgot_password.php">Forgot password?</a></div>
        <div id="login-error" class="text-danger mt-3 d-none"></div>
      </form>
    </div>
  </div>
<?php
$pageScripts = <<<HTML
<script>
(function(){
  const form = document.getElementById('login-form');
  const err = document.getElementById('login-error');
  form.addEventListener('submit', async function(ev){
    ev.preventDefault();
    err.classList.add('d-none');
    const fd = new FormData(form);
    try {
      const res = await fetch(form.action, {method:'POST', body:fd});
      const data = await res.json();
      if(res.ok && data && data.ok){
        let dest = '/jobs.php';
        if(data.role === 'admin'){ dest = '/admin/index.php'; }
        else if(data.role === 'field_tech'){ dest = '/tech_jobs.php'; }
        window.location.href = dest;
      } else {
        err.textContent = (data && (data.message || data.error))
          ? (data.message || data.error)
          : 'Invalid credentials';
        err.classList.remove('d-none');
      }
    } catch(e) {
      err.textContent = 'An unexpected error occurred';
      err.classList.remove('d-none');
    }
  });
})();
</script>
HTML;
require __DIR__ . '/../partials/footer.php';
?>
