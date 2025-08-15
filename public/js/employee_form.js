(function(){
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('employeeForm');
    if (!form) return;
    var phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput && window.Inputmask) {
      Inputmask("(999) 999-9999").mask(phoneInput);
    }
    var errBox = document.getElementById('form-errors');
    function showErrors(list){
      if(!errBox) return;
      if(!list || !list.length){ errBox.textContent=''; return; }
      var html = '<ul>' + list.map(function(e){return '<li>'+e+'</li>';}).join('') + '</ul>';
      errBox.innerHTML = html;
    }
    form.addEventListener('submit', function(e){
      e.preventDefault();
      showErrors([]);
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
