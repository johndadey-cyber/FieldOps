(function(){
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('employeeForm');
    if (!form) return;
    var mode = form.getAttribute('data-mode') || 'add';
    var phoneEl = form.querySelector('#phone');
    if (phoneEl) {
      phoneEl.addEventListener('input', function (e) {
        var digits = e.target.value.replace(/\D/g, '').slice(0, 10);
        var formatted = digits;
        if (digits.length > 6) {
          formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
        } else if (digits.length > 3) {
          formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
        } else if (digits.length > 0) {
          formatted = '(' + digits;
        }
        e.target.value = formatted;
        var valid = digits.length === 10;
        e.target.classList.toggle('is-invalid', !valid);
        e.target.setCustomValidity(valid ? '' : 'Invalid phone number');
      });
    }
    // Enhance the skills multi-select with Select2 for better UX
    if (typeof $ !== 'undefined' && $.fn.select2) {
      var skillsSelect = $('#skills');
      if (skillsSelect.length) {
        skillsSelect.select2({ width: '100%' });
      }
    }
    var errBox = document.getElementById('form-errors');
    function showErrors(list){
      if(!errBox) return;
      if(!list || !list.length){ errBox.textContent=''; return; }
      var html = '<ul>' + list.map(function(e){return '<li>'+e+'</li>';}).join('') + '</ul>';
      errBox.innerHTML = html;
    }
    function showToast(msg){
      var container=document.getElementById('toastContainer');
      if(!container||typeof bootstrap==='undefined') return;
      var el=document.createElement('div');
      el.className='toast align-items-center text-bg-success border-0';
      el.setAttribute('role','alert');
      el.setAttribute('aria-live','assertive');
      el.setAttribute('aria-atomic','true');
      el.innerHTML='<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
      el.querySelector('.toast-body').textContent=msg;
      container.appendChild(el);
      var toast=new bootstrap.Toast(el,{delay:2000});
      toast.show();
    }
    form.addEventListener('submit', function(e){
      e.preventDefault();
      showErrors([]);
      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
      }
      var submitBtn = form.querySelector('button[type="submit"]');
      var originalBtnHTML = submitBtn ? submitBtn.innerHTML : '';
      if(submitBtn){
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Saving...';
      }
      var fd = new FormData(form);
      fetch('employee_save.php?json=1', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
      }).then(function(resp){
        return resp.json();
      }).then(function(data){
        if(data && data.ok){
          try{localStorage.setItem('employeesUpdated',Date.now().toString());}catch(_){ }
          try{window.dispatchEvent(new Event('employees:updated'));}catch(_){ }
          showToast(mode === 'edit' ? 'Employee updated' : 'Employee saved');
          setTimeout(function(){window.location.href='employees.php';},800);
          return;
        }
        var errs = [];
        if (data && data.errors) {
          errs = data.errors;
        } else if (data && data.error) {
          errs = [data.error];
        } else {
          errs = ['Unknown error'];
        }
        showErrors(errs);
        if(errBox) errBox.scrollIntoView({behavior:'smooth'});
      }).catch(function(){
        showErrors(['Request failed. Please try again.']);
        if(errBox) errBox.scrollIntoView({behavior:'smooth'});
      }).finally(function(){
        if(submitBtn){
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnHTML;
        }
      });
    });
  });
})();
