(function(){
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('employeeForm');
    if (!form) return;
    var errBox = document.getElementById('form-errors');
    function showErrors(list){
      if(!errBox) return;
      if(!list || !list.length){ errBox.textContent=''; return; }
      var html = '<ul>' + list.map(function(e){return '<li>'+e+'</li>';}).join('') + '</ul>';
      errBox.innerHTML = html;
    }

    var requiredFields = form.querySelectorAll('[required]');

    function getFieldErrorEl(field){
      var parent = field.closest('label') || field.parentNode;
      var err = parent.querySelector('.field-error');
      if(!err){
        err = document.createElement('div');
        err.className = 'field-error';
        parent.appendChild(err);
      }
      return err;
    }

    function validateField(field){
      var value = field.value.trim();
      var message = '';
      if(!value){
        message = 'This field is required.';
      } else if(field.type === 'email'){
        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if(!emailRe.test(value)) message = 'Please enter a valid email.';
      } else if(field.type === 'tel' && field.pattern){
        var telRe = new RegExp(field.pattern);
        if(!telRe.test(value)) message = field.title || 'Please match the requested format.';
      }
      var err = getFieldErrorEl(field);
      if(message){
        field.classList.add('error');
        err.textContent = message;
        return false;
      }
      field.classList.remove('error');
      err.textContent = '';
      return true;
    }

    requiredFields.forEach(function(f){
      ['input','change'].forEach(function(ev){
        f.addEventListener(ev, function(){ validateField(f); });
      });
    });

    function validateForm(){
      var ok = true;
      requiredFields.forEach(function(f){ if(!validateField(f)) ok = false; });
      return ok;
    }

    form.addEventListener('submit', function(e){
      e.preventDefault();
      showErrors([]);
      if(!validateForm()){
        showErrors(['Please correct the highlighted fields.']);
        return;
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
          window.location.href = 'employees.php';
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
      }).catch(function(){
        showErrors(['Request failed. Please try again.']);
      });
    });
  });
})();
