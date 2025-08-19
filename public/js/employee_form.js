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
      if (window.FieldOpsToast) { FieldOpsToast.show(msg,'success'); }
      else { alert(msg); }
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
          setTimeout(function(){
            var dest = 'employees.php';
            if (mode === 'add' && data && data.id) { dest = 'availability_onboard.php?employee_id=' + encodeURIComponent(data.id); }
            window.location.href = dest;
          },800);
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
