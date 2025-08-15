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
    form.addEventListener('submit', function(e){
      e.preventDefault();
      showErrors([]);
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
          alert('Employee saved');
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
