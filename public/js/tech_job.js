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
        if(j.status==='assigned'){btnStart.classList.remove('d-none');}
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
        const res=await fetch(`/api/job_checklist.php?job_id=${jobId}&csrf_token=${encodeURIComponent(csrf)}`,{credentials:'same-origin'});
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

    function fileToBase64(file){
      return new Promise((resolve,reject)=>{
        const reader=new FileReader();
        reader.onload=()=>resolve(reader.result);
        reader.onerror=()=>reject(new Error('Read failed'));
        reader.readAsDataURL(file);
      });
    }

    async function pickFinalPhotos(){
      return new Promise(resolve=>{
        const input=document.createElement('input');
        input.type='file';
        input.accept='image/*';
        input.multiple=true;
        input.style.display='none';
        document.body.appendChild(input);
        input.addEventListener('change',async()=>{
          const files=Array.from(input.files||[]);
          const res=[];
          for(const f of files){res.push(await fileToBase64(f));}
          input.remove();
          resolve(res);
        });
        input.click();
      });
    }

    async function captureSignature(){
      return new Promise(resolve=>{
        const modal=document.createElement('div');
        modal.className='modal fade';
        modal.innerHTML=`<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Signature</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><canvas width="300" height="150" class="border w-100"></canvas></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning" id="sig-clear">Clear</button><button type="button" class="btn btn-primary" id="sig-save">Save</button></div>
</div></div>`;
        document.body.appendChild(modal);
        const canvas=modal.querySelector('canvas');
        const ctx=canvas.getContext('2d');
        ctx.strokeStyle='#000';
        ctx.lineWidth=2;
        let drawing=false;
        function getPos(e){const rect=canvas.getBoundingClientRect();const p=e.touches?e.touches[0]:e;return {x:p.clientX-rect.left,y:p.clientY-rect.top};}
        function start(e){drawing=true;const p=getPos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);e.preventDefault();}
        function draw(e){if(!drawing) return;const p=getPos(e);ctx.lineTo(p.x,p.y);ctx.stroke();e.preventDefault();}
        function end(){drawing=false;}
        canvas.addEventListener('mousedown',start);
        canvas.addEventListener('mousemove',draw);
        canvas.addEventListener('mouseup',end);
        canvas.addEventListener('mouseleave',end);
        canvas.addEventListener('touchstart',start);
        canvas.addEventListener('touchmove',draw);
        canvas.addEventListener('touchend',end);
        const bsModal=new bootstrap.Modal(modal);
        modal.addEventListener('hidden.bs.modal',()=>{modal.remove();resolve(null);});
        modal.querySelector('#sig-clear').addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);});
        modal.querySelector('#sig-save').addEventListener('click',()=>{const data=canvas.toDataURL('image/png');bsModal.hide();resolve(data);});
        bsModal.show();
      });
    }

    function getLocation(){
      return new Promise((resolve,reject)=>{navigator.geolocation.getCurrentPosition(resolve,reject);});
    }

    btnComplete?.addEventListener('click',async()=>{
      if(!confirm('Mark job complete?')) return;
      const finalNote=(prompt('Enter final note:')||'').trim();
      if(!finalNote){alert('Final note is required');return;}
      const photos=await pickFinalPhotos();
      if(!photos?.length){alert('At least one completion photo is required');return;}
      const signature=await captureSignature();
      if(!signature){alert('Signature required');return;}
      btnComplete.disabled=true;
      try{
        const pos=await getLocation();
        const fd=new FormData();
        fd.append('job_id',jobId);
        fd.append('technician_id',techId);
        fd.append('location_lat',pos.coords.latitude);
        fd.append('location_lng',pos.coords.longitude);
        fd.append('final_note',finalNote);
        photos.forEach(p=>fd.append('final_photos[]',p));
        fd.append('signature',signature);
        fd.append('csrf_token',csrf);
        const res=await fetch('/api/job_complete.php',{method:'POST',body:fd,credentials:'same-origin'});
        const data=await res.json();
        if(!data?.ok) throw new Error(data?.error||'Failed');
        if(window.FieldOpsToast && typeof FieldOpsToast.show==='function'){
          FieldOpsToast.show('Job completed');
        }else{
          alert('Job completed');
        }
        btnComplete.disabled=true;
        btnComplete.classList.add('d-none');
        if(details){
          const status=document.createElement('div');
          status.className='mt-2 badge bg-success';
          status.textContent='Completed';
          details.appendChild(status);
        }
      }catch(err){
        alert(err.message||'Failed');
        btnComplete.disabled=false;
      }
    });
  });
})();
