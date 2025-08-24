<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
$title = 'Reset Password';
require __DIR__ . '/../partials/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-4">
    <form id="reset-form" action="/api/reset_password.php" method="post" class="card card-body">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">
      <div class="mb-3">
        <label for="password" class="form-label">New Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>
      <div id="reset-error" class="text-danger d-none"></div>
      <div id="reset-success" class="text-success d-none">Password updated. You may now <a href="/login.php">log in</a>.</div>
    </form>
  </div>
</div>
<?php
$pageScripts = <<<HTML
<script>
(function(){
  const form=document.getElementById('reset-form');
  const err=document.getElementById('reset-error');
  const ok=document.getElementById('reset-success');
  form.addEventListener('submit',async(ev)=>{
    ev.preventDefault();
    err.classList.add('d-none');
    ok.classList.add('d-none');
    const fd=new FormData(form);
    try{
      const res=await fetch(form.action,{method:'POST',body:fd});
      const data=await res.json();
      if(res.ok && data && data.ok){
        ok.classList.remove('d-none');
      }else{
        err.textContent=data.error||'Error';
        err.classList.remove('d-none');
      }
    }catch(e){
      err.textContent='Error';
      err.classList.remove('d-none');
    }
  });
})();
</script>
HTML;
require __DIR__ . '/../partials/footer.php';
?>
