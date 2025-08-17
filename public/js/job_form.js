(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('jobForm');
    if(!form) return;
    var mode = form.getAttribute('data-mode') || 'add';
    var errBox = document.getElementById('form-errors');
    var skillError = document.getElementById('jobSkillError');

    if (typeof $ !== 'undefined' && $.fn.select2) {
      var skillsSelect = $('#skills');
      if (skillsSelect.length) {
        skillsSelect.select2({ width: '100%' });
      }
    }

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
      var skillSelect = form.querySelector('#skills');
      var selectedSkills = Array.from(skillSelect?.selectedOptions || []);
      var valid = form.checkValidity();
      if(selectedSkills.length===0){ if(skillError){skillError.style.display='block';} valid=false; } else { if(skillError){skillError.style.display='none';} }
      if(!valid){ form.classList.add('was-validated'); return; }
      var submitBtn=form.querySelector('button[type="submit"]');
      var originalHTML = submitBtn ? submitBtn.innerHTML : '';
      if(submitBtn){ submitBtn.disabled=true; submitBtn.innerHTML='Saving...'; }
      var fd = new FormData(form);
      fetch('job_save.php?json=1',{method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, body:fd})
        .then(function(resp){ return resp.json(); })
        .then(function(data){
          if(data && data.ok){
            showToast(mode==='edit'?'Job updated':'Job saved');
            setTimeout(function(){ window.location.href='jobs.php'; }, 800);
            return;
          }
          var errs=[];
          if(data && data.errors){
            if(Array.isArray(data.errors)){ errs=data.errors; }
            else if(typeof data.errors==='object'){ errs=Object.values(data.errors); }
          }
          else if(data && data.error){ errs=[data.error]; }
          else { errs=['Unknown error']; }
          showErrors(errs);
          if(errBox) errBox.scrollIntoView({behavior:'smooth'});
        })
        .catch(function(){
          showErrors(['Request failed. Please try again.']);
          if(errBox) errBox.scrollIntoView({behavior:'smooth'});
        })
        .finally(function(){
          if(submitBtn){ submitBtn.disabled=false; submitBtn.innerHTML=originalHTML; }
        });
    });
  });
})();
