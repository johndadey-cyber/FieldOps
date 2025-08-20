// /public/js/tech_job.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  const csrf=window.CSRF_TOKEN;
  const jobId=Number(window.JOB_ID);
  const techId=Number(window.TECH_ID);
  ready(() => {
    const details=document.getElementById('job-details');
    const btnStart=document.getElementById('btn-start-job');
    const btnNote=document.getElementById('btn-add-note');
    const btnPhoto=document.getElementById('btn-add-photo');
    const btnChecklist=document.getElementById('btn-checklist');
    const btnComplete=document.getElementById('btn-complete');
    const fileInput=document.createElement('input');
    fileInput.type='file';
    fileInput.accept='image/*';
    fileInput.style.display='none';
    document.body.appendChild(fileInput);

    fetch(`/api/get_job_details.php?id=${jobId}`,{credentials:'same-origin'})
      .then(r=>r.json())
      .then(data=>{
        if(!data?.ok) throw new Error('Job not found');
        const j=data.job;
        details.innerHTML=`<h1 class="h5">${h(j.description||'')}</h1>
<div>${h(j.customer?.first_name||'')} ${h(j.customer?.last_name||'')}</div>
<div class="text-muted">${h(j.customer?.address_line1||'')}</div>`;
        if(j.status==='scheduled'){btnStart.classList.remove('d-none');}
      })
      .catch(err=>{details.innerHTML=`<div class="text-danger">${h(err.message)}</div>`;});

    btnStart?.addEventListener('click',()=>{
      btnStart.disabled=true;
      navigator.geolocation.getCurrentPosition(pos=>{
        const fd=new FormData();
        fd.append('job_id',jobId);
        fd.append('location_lat',pos.coords.latitude);
        fd.append('location_lng',pos.coords.longitude);
        fd.append('csrf_token',csrf);
        fetch('/api/job_start.php',{method:'POST',body:fd,credentials:'same-origin'})
          .then(r=>r.json())
          .then(res=>{if(!res?.ok) throw new Error(res?.error||'Failed');btnStart.classList.add('d-none');})
          .catch(err=>{alert(err.message||'Failed');btnStart.disabled=false;});
      },()=>{alert('Location required');btnStart.disabled=false;});
    });

    btnNote?.addEventListener('click',()=>{
      const note=prompt('Enter note:');
      if(!note) return;
      const fd=new FormData();
      fd.append('job_id',jobId);
      fd.append('technician_id',techId);
      fd.append('note',note);
      fd.append('csrf_token',csrf);
      fetch('/api/job_notes_add.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(res=>{if(!res?.ok) throw new Error(res?.error||'Failed');alert('Note added');})
        .catch(err=>alert(err.message||'Failed'));
    });

    btnPhoto?.addEventListener('click',()=>fileInput.click());
    fileInput.addEventListener('change',()=>{
      if(!fileInput.files[0]) return;
      const fd=new FormData();
      fd.append('job_id',jobId);
      fd.append('technician_id',techId);
      fd.append('photo',fileInput.files[0]);
      fd.append('csrf_token',csrf);
      fetch('/api/job_photos_upload.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(res=>{if(!res?.ok) throw new Error(res?.error||'Failed');alert('Photo uploaded');fileInput.value='';})
        .catch(err=>{alert(err.message||'Upload failed');fileInput.value='';});
    });

    btnChecklist?.addEventListener('click',async()=>{
      try{
        const res=await fetch(`/api/job_checklist.php?job_id=${jobId}`,{credentials:'same-origin'});
        const data=await res.json();
        if(!Array.isArray(data?.items)) throw new Error('No checklist');
        const checked=await showChecklistModal(data.items);
        if(!checked) return;
        const fd=new FormData();
        fd.append('job_id',jobId);
        fd.append('items',JSON.stringify(checked));
        fd.append('csrf_token',csrf);
        const res2=await fetch('/api/job_checklist_update.php',{method:'POST',body:fd,credentials:'same-origin'});
        const data2=await res2.json();
        if(!data2?.ok) throw new Error(data2?.error||'Failed');
        alert('Checklist saved');
      }catch(err){alert(err.message||'Checklist failed');}
    });

    async function showChecklistModal(items){
      return new Promise(resolve=>{
        const modal=document.createElement('div');
        modal.className='modal fade';
        modal.innerHTML=`<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Checklist</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="chk-save">Save</button></div>
</div></div>`;
        const body=modal.querySelector('.modal-body');
        items.forEach((it,i)=>{
          const id='chk'+i;
          const div=document.createElement('div');
          div.className='form-check';
          div.innerHTML=`<input class="form-check-input" type="checkbox" id="${id}" ${it.completed?'checked':''}>
<label class="form-check-label" for="${id}">${h(it.item||it.description||'')}</label>`;
          body.appendChild(div);
        });
        document.body.appendChild(modal);
        const bsModal=new bootstrap.Modal(modal);
        modal.addEventListener('hidden.bs.modal',()=>{modal.remove();resolve(null);});
        modal.querySelector('#chk-save').addEventListener('click',()=>{
          const res=[];
          body.querySelectorAll('input[type="checkbox"]').forEach((cb,idx)=>{res.push({id:items[idx].id,completed:cb.checked});});
          bsModal.hide();resolve(res);
        });
        bsModal.show();
      });
    }

    btnComplete?.addEventListener('click',()=>{
      if(!confirm('Mark job complete?')) return;
      const fd=new FormData();
      fd.append('job_id',jobId);
      fd.append('csrf_token',csrf);
      fetch('/api/job_complete.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(res=>{if(!res?.ok) throw new Error(res?.error||'Failed');alert('Job completed');})
        .catch(err=>alert(err.message||'Failed'));
    });
  });
})();
