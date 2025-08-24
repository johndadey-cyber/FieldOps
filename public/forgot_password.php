<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

$title = 'Forgot Password';
require __DIR__ . '/../partials/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-4">
    <form id="forgot-form" action="/api/forgot_password.php" method="post" class="card card-body">
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary">Send Reset Link</button>
      </div>
      <div id="forgot-message" class="text-success d-none">If that email exists, a reset link has been sent.</div>
    </form>
  </div>
</div>
<?php
$pageScripts = <<<HTML
<script>
(function(){
  const form=document.getElementById('forgot-form');
  const msg=document.getElementById('forgot-message');
  form.addEventListener('submit',async(ev)=>{
    ev.preventDefault();
    const fd=new FormData(form);
    try{
      await fetch(form.action,{method:'POST',body:fd});
    }catch(e){
      // ignore
    }
    msg.classList.remove('d-none');
  });
})();
</script>
HTML;
require __DIR__ . '/../partials/footer.php';
?>
