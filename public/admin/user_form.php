<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../_auth.php';
require_role('admin');

$title = 'Add User';
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/nav.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <form id="user-form" action="/api/users/create.php" method="post" class="card card-body" autocomplete="off">
      <?php require __DIR__ . '/../../partials/csrf_input.php'; ?>
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Role</label>
        <select id="role" name="role" class="form-select" required>
          <option value="">-- Select role --</option>
          <option value="admin">Admin</option>
          <option value="dispatcher">Dispatcher</option>
          <option value="tech">Tech</option>
          <option value="field_tech">Field Tech</option>
        </select>
      </div>
      <div id="form-errors" class="alert alert-danger d-none"></div>
      <div id="form-success" class="alert alert-success d-none">User created successfully.</div>
      <button type="submit" class="btn btn-primary">Create User</button>
    </form>
  </div>
</div>
<?php
$pageScripts = <<<HTML
<script>
(function(){
  const form = document.getElementById('user-form');
  const errBox = document.getElementById('form-errors');
  const okBox  = document.getElementById('form-success');
  form.addEventListener('submit', async function(ev){
    ev.preventDefault();
    errBox.classList.add('d-none');
    errBox.innerHTML = '';
    okBox.classList.add('d-none');
    const fd = new FormData(form);
    try {
      const res = await fetch(form.action, {method:'POST', body:fd});
      const data = await res.json();
      if(res.ok && data && data.ok){
        okBox.classList.remove('d-none');
        form.reset();
      } else if(data && data.errors){
        const ul = document.createElement('ul');
        for(const msg of Object.values(data.errors)){
          const li = document.createElement('li');
          li.textContent = msg;
          ul.appendChild(li);
        }
        errBox.appendChild(ul);
        errBox.classList.remove('d-none');
      } else if(data && data.error){
        errBox.textContent = data.error;
        errBox.classList.remove('d-none');
      } else {
        errBox.textContent = 'An unknown error occurred.';
        errBox.classList.remove('d-none');
      }
    } catch(e){
      errBox.textContent = 'Request failed.';
      errBox.classList.remove('d-none');
    }
  });
})();
</script>
HTML;
require_once __DIR__ . '/../../partials/footer.php';
?>
