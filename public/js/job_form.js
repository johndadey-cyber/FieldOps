(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('jobForm');
    if(!form) return;
    var mode = form.getAttribute('data-mode') || 'add';
    var errBox = document.getElementById('form-errors');
    var skillError = document.getElementById('jobSkillError');
    var templates = window.jobChecklistTemplates || {};
    var initItems = window.initialChecklistItems || [];
    var checklistFieldset = document.getElementById('checklistFieldset');
    var checklistWrap = document.getElementById('checklistItems');
    var addBtn = document.getElementById('addChecklistItem');
    var jobTypeSelect = document.getElementById('job_type_id');

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
      if (window.FieldOpsToast) { FieldOpsToast.show(msg,'success'); }
      else { alert(msg); }
    }

    function addChecklistInput(val){
      if(!checklistFieldset || !checklistWrap) return;
      checklistFieldset.style.display='block';
      var div=document.createElement('div');
      div.className='input-group mb-2 checklist-item';
      var inp=document.createElement('input');
      inp.type='text';
      inp.name='checklist_items[]';
      inp.className='form-control checklist-input';
      inp.required=true;
      inp.maxLength=255;
      inp.value=val||'';
      var btn=document.createElement('button');
      btn.type='button';
      btn.className='btn btn-outline-danger';
      btn.textContent='Remove';
      btn.setAttribute('aria-label','Remove item');
      btn.addEventListener('click',function(){
        div.remove();
        if(checklistWrap.children.length===0){ checklistFieldset.style.display='none'; }
      });
      div.appendChild(inp);
      div.appendChild(btn);
      var fb=document.createElement('div');
      fb.className='invalid-feedback';
      fb.textContent='Description required (max 255 characters).';
      div.appendChild(fb);
      checklistWrap.appendChild(div);
    }

    function renderChecklist(items){
      if(!checklistWrap) return;
      checklistWrap.innerHTML='';
      (items||[]).forEach(function(it){ addChecklistInput(it); });
      if(checklistWrap.children.length===0){ checklistFieldset.style.display='none'; }
    }

    if(addBtn){ addBtn.addEventListener('click', function(){ addChecklistInput(''); }); }
    if(jobTypeSelect){
      jobTypeSelect.addEventListener('change', function(){
        var tid=jobTypeSelect.value;
        renderChecklist(templates[tid]||[]);
      });
    }
    if(initItems && initItems.length){ renderChecklist(initItems); }

    form.addEventListener('submit', function(e){
      e.preventDefault();
      showErrors([]);
        var skillSelect = form.querySelector('#skills');
        var selectedSkills = Array.from(skillSelect?.selectedOptions || []);
        var checklistInputs=form.querySelectorAll('.checklist-input');
        checklistInputs.forEach(function(inp){ inp.value=inp.value.trim(); });
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
