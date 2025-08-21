(function(){
  function initSelect2(){
    if (typeof $ !== 'undefined' && $.fn.select2) {
      var skillsSelect = $('#skills');
      if (skillsSelect.length) {
        skillsSelect.one('focus', function(){ skillsSelect.select2({ width: '100%' }); });
      }
      var jobTypeSel2 = $('#job_type_ids');
      if (jobTypeSel2.length) {
        jobTypeSel2.one('focus', function(){ jobTypeSel2.select2({ width: '100%' }); });
      }
    }
  }

  function createChecklistManager(fieldset, wrap){
    function addInput(val){
      if(!fieldset || !wrap) return;
      fieldset.style.display='block';
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
        if(wrap.children.length===0){ fieldset.style.display='none'; }
      });
      div.appendChild(inp);
      div.appendChild(btn);
      var fb=document.createElement('div');
      fb.className='invalid-feedback';
      fb.textContent='Description required (max 255 characters).';
      div.appendChild(fb);
      wrap.appendChild(div);
    }

    function render(items){
      if(!wrap) return;
      wrap.innerHTML='';
      (items||[]).forEach(function(it){ addInput(it); });
      if(wrap.children.length===0){ fieldset.style.display='none'; }
    }

    return { addInput: addInput, render: render };
  }

  function showErrors(errBox, list){
    if(!errBox) return;
    if(!list || !list.length){ errBox.textContent=''; return; }
    var html = '<ul>' + list.map(function(e){return '<li>'+e+'</li>';}).join('') + '</ul>';
    errBox.innerHTML = html;
  }

  function showToast(msg){
    if (window.FieldOpsToast) { FieldOpsToast.show(msg,'success'); }
    else { alert(msg); }
  }

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
    var jobTypeSelect = document.getElementById('job_type_ids');
    var isDev = (window.APP_ENV && window.APP_ENV !== 'prod') ||
                (location.hostname === 'localhost' || location.hostname === '127.0.0.1');

    var checklist = createChecklistManager(checklistFieldset, checklistWrap);

    function handleJobTypeChange(){
      var selected=Array.from(jobTypeSelect.selectedOptions||[]).map(function(o){return o.value;});
      var tid=selected.length?selected[0]:'';
      requestAnimationFrame(function(){ checklist.render(templates[tid]||[]); });
    }

    function handleSubmit(e){
      e.preventDefault();
      showErrors(errBox, []);
      var skillSelect = form.querySelector('#skills');
        var selectedSkills = Array.from(skillSelect?.selectedOptions || []);
        var checklistInputs=form.querySelectorAll('.checklist-input');
        checklistInputs.forEach(function(inp){ inp.value=inp.value.trim(); });
        var valid = form.checkValidity();
        if(selectedSkills.length===0){
          if(skillError){skillError.style.display='block';}
          valid=false;
        } else {
          if(skillError){skillError.style.display='none';}
        }
        if(!valid){ form.classList.add('was-validated'); return; }
      var submitBtn=form.querySelector('button[type="submit"]');
      var originalHTML = submitBtn ? submitBtn.innerHTML : '';
      if(submitBtn){ submitBtn.disabled=true; submitBtn.innerHTML='Saving...'; }
      var fd = new FormData(form);
      fetch('job_save.php?json=1',{method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, body:fd})
        .then(function(resp){
          if(resp.ok){ return resp.json(); }
          if(resp.status===400){
            return resp.json().catch(function(){ return {}; }).then(function(data){
              throw {type:'bad_request', data:data};
            });
          }
          if(resp.status>=500){
            return resp.json().catch(function(){ return {}; }).then(function(data){
              throw {type:'server_error', data:data};
            });
          }
          throw {type:'fetch_error'};
        })
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
          else if(data && data.error){
            var msg=data.error;
            if(isDev && data.detail){ msg += ' ' + data.detail; }
            errs=[msg];
          }
          else { errs=['Unknown error']; }
          showErrors(errBox, errs);
          if(errBox) errBox.scrollIntoView({behavior:'smooth'});
        })
        .catch(function(err){
          if(err && err.type==='bad_request'){
            var data=err.data||{};
            var errs=[];
            if(data.errors){
              if(Array.isArray(data.errors)){ errs=data.errors; }
              else if(typeof data.errors==='object'){ errs=Object.values(data.errors); }
            } else if(data.error){
              var msg2=data.error;
              if(isDev && data.detail){ msg2 += ' ' + data.detail; }
              errs=[msg2];
            }
            else { errs=['Request error']; }
            showErrors(errBox, errs);
          }
          else if(err && err.type==='server_error'){
            var msg3='Server error. Please try again later.';
            if(isDev && err.data && err.data.detail){ msg3 += ' ' + err.data.detail; }
            showErrors(errBox, [msg3]);
          }
          else {
            showErrors(errBox, ['Request failed. Please try again.']);
          }
          if(errBox) errBox.scrollIntoView({behavior:'smooth'});
        })
        .finally(function(){
          if(submitBtn){ submitBtn.disabled=false; submitBtn.innerHTML=originalHTML; }
        });
    }

    if(addBtn){ addBtn.addEventListener('click', function(){ checklist.addInput(''); }); }
    if(jobTypeSelect){ jobTypeSelect.addEventListener('change', handleJobTypeChange); }
    form.addEventListener('submit', handleSubmit);

    requestAnimationFrame(function(){
      initSelect2();
      if(initItems && initItems.length){ checklist.render(initItems); }
    });
  });
})();

