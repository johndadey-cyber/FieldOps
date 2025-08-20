// /public/js/tech_job.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function fmtStatus(s){return (s||'').replace(/_/g,' ');}
  const csrf=window.CSRF_TOKEN;
  const jobId=Number(window.JOB_ID);
  const techId=Number(window.TECH_ID);
  ready(() => {
    const details=document.getElementById('job-details');
    const notesEl=document.getElementById('job-notes');
    const photosEl=document.getElementById('job-photos');
    const statusBanner=document.getElementById('status-banner');
    const btnStart=document.getElementById('btn-start-job');
    const btnNote=document.getElementById('btn-add-note');
    const btnPhoto=document.getElementById('btn-add-photo');
    const btnChecklist=document.getElementById('btn-checklist');
    const btnComplete=document.getElementById('btn-complete');
    let scheduledStart=null;
    const fileInput=document.createElement('input');
    fileInput.type='file';
    fileInput.accept='image/*';
    fileInput.style.display='none';
    document.body.appendChild(fileInput);

    function debugStep(msg){
      console.log('[tech_job]',msg);
      let log=document.getElementById('debug-log');
      if(!log){
        log=document.createElement('div');
        log.id='debug-log';
        log.style.position='fixed';
        log.style.bottom='0';
        log.style.left='0';
        log.style.right='0';
        log.style.background='rgba(0,0,0,0.7)';
        log.style.color='#0f0';
        log.style.fontSize='12px';
        log.style.maxHeight='50vh';
        log.style.overflowY='auto';
        log.style.zIndex='9999';
        log.style.padding='4px';
        document.body.appendChild(log);
      }
      const div=document.createElement('div');
      div.textContent=msg;
      log.appendChild(div);
    }

    function updateStatus(st){
      if(!statusBanner) return;
      const map={assigned:'secondary',in_progress:'warning',completed:'success'};
      const cls=map[st]||'secondary';
      statusBanner.className=`alert alert-${cls} mb-3`;
      statusBanner.textContent=fmtStatus(st);
      statusBanner.classList.remove('d-none');
    }

    function isTooEarly(){
      return scheduledStart && new Date()<scheduledStart;
    }

    function fetchNotes(){
      fetch(`/api/job_notes_list.php?job_id=${jobId}&csrf_token=${encodeURIComponent(csrf)}`,{credentials:'same-origin'})
        .then(r=>r.json())
        .then(data=>{if(!data?.ok) throw new Error();renderNotes(data.notes||[]);})
        .catch(()=>{if(notesEl) notesEl.innerHTML='<div class="text-muted">No notes</div>';});
    }

    function renderNotes(notes){
      if(!notesEl) return;
      if(!notes.length){notesEl.innerHTML='<div class="text-muted">No notes</div>';return;}
      notesEl.innerHTML='';
      notes.forEach(n=>{
        const div=document.createElement('div');
        div.className='mb-2';
        div.innerHTML=`<div>${h(n.note)}</div><div class="text-muted small">${h(new Date(n.created_at).toLocaleString())}</div>`;
        notesEl.appendChild(div);
      });
    }

    function fetchPhotos(){
      fetch(`/api/job_photos_list.php?job_id=${jobId}&csrf_token=${encodeURIComponent(csrf)}`,{credentials:'same-origin'})
        .then(r=>r.json())
        .then(data=>{if(!data?.ok) throw new Error();renderPhotos(data.photos||[]);})
        .catch(()=>{if(photosEl) photosEl.innerHTML='<div class="text-muted">No photos</div>';});
    }

    function renderPhotos(photos){
      if(!photosEl) return;
      photosEl.innerHTML='';
      if(!photos.length){photosEl.innerHTML='<div class="text-muted">No photos</div>';return;}
      photos.forEach(p=>{
        const img=document.createElement('img');
        img.src=`/${p.path}`;
        img.className='img-thumbnail';
        img.style.maxWidth='120px';
        photosEl.appendChild(img);
      });
    }

    fetch(`/api/get_job_details.php?id=${jobId}`,{credentials:'same-origin'})
      .then(r=>r.json())
      .then(data=>{
        if(!data?.ok) throw new Error('Job not found');
        const j=data.job;
        const c=j.customer||{};
        const addr=[c.address_line1,c.city,c.state,c.postal_code].filter(Boolean).join(', ');
        const mapUrl=`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addr)}`;
        const callUrl=c.phone?`tel:${c.phone}`:'#';
        const start=j.scheduled_time?new Date(`${j.scheduled_date}T${j.scheduled_time}`):null;
        const end=start?new Date(start.getTime()+((j.duration_minutes||60)*60000)):null;
        const win=start?`${start.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})} - ${end.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}`:'';
        details.innerHTML=`<div class="mb-2 fw-bold">Job #${h(j.id)}</div>
<div>${h(c.first_name||'')} ${h(c.last_name||'')}</div>
<div>${h(c.address_line1||'')}</div>
<div class="mb-2"><a class="btn btn-sm btn-outline-primary me-2" href="${mapUrl}" target="_blank">Directions</a>${c.phone?`<a class="btn btn-sm btn-outline-primary" href="${callUrl}">Call</a>`:''}</div>
<div class="text-muted mb-2">${h(win)}</div>
<div class="fst-italic">${h(j.description||'')}</div>`;
        scheduledStart=start;
        updateStatus(j.status);
        const status=(j.status||'').toLowerCase();
        if(status==='assigned'){btnStart.classList.remove('d-none');}
        if(status==='in_progress'){btnComplete.classList.remove('d-none');}
        if(isTooEarly()){btnStart.classList.add('disabled');}else{btnStart.classList.remove('disabled');}
        fetchNotes();
        fetchPhotos();
        setInterval(()=>{if(btnStart&&btnStart.classList.contains('disabled')&&!isTooEarly())btnStart.classList.remove('disabled');},60000);
      })
      .catch(err=>{details.innerHTML=`<div class="text-danger">${h(err.message)}</div>`;});

    btnStart?.addEventListener('click',()=>{
      if(isTooEarly() && !confirm('Start before scheduled time?')){return;}
      btnStart.disabled=true;
      navigator.geolocation.getCurrentPosition(pos=>{
        const fd=new FormData();
        fd.append('job_id',jobId);
        fd.append('location_lat',pos.coords.latitude);
        fd.append('location_lng',pos.coords.longitude);
        fd.append('csrf_token',csrf);
        fetch('/api/job_start.php',{method:'POST',body:fd,credentials:'same-origin'})
          .then(r=>r.json())
          .then(res=>{
            if(!res?.ok) throw new Error(res?.error||'Failed');
            btnStart.classList.add('d-none');
            btnComplete.classList.remove('d-none');
            updateStatus(res.status||'in_progress');
          })
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
        .then(r=>r.json()).then(res=>{if(!res?.ok) throw new Error(res?.error||'Failed');fetchNotes();})
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
        .then(r=>r.json()).then(res=>{if(!res?.ok) throw new Error(res?.error||'Failed');fileInput.value='';fetchPhotos();})
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
        // hide offscreen instead of display:none so some mobile browsers
        // still allow the picker to be triggered programmatically
        input.style.position='absolute';
        input.style.left='-9999px';
        document.body.appendChild(input);

        const cleanup=()=>{input.remove();};

        // If the user selects files, convert them to base64 strings
        input.addEventListener('change',async()=>{
          const files=Array.from(input.files||[]);
          const res=[];
          for(const f of files){res.push(await fileToBase64(f));}
          cleanup();
          debugStep(`Selected ${files.length} photo(s)`);
          resolve(res);
        });

        // If the user cancels the dialog, resolve with null so callers can
        // handle the "no photos" case instead of hanging forever
        input.addEventListener('cancel',()=>{cleanup();debugStep('Photo selection cancelled');resolve(null);});
        // Fallback for browsers without "cancel" event
        input.addEventListener('blur',()=>{
          if(!input.files.length){cleanup();debugStep('Photo selection cancelled');resolve(null);}
        });

        debugStep('Opening photo picker');
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
        modal.addEventListener('hidden.bs.modal',()=>{modal.remove();debugStep('Signature dialog closed');resolve(null);});
        modal.querySelector('#sig-clear').addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);});
        modal.querySelector('#sig-save').addEventListener('click',()=>{const data=canvas.toDataURL('image/png');bsModal.hide();debugStep('Signature captured');resolve(data);});
        debugStep('Opening signature dialog');
        bsModal.show();
      });
    }

    function getLocation(){
      return new Promise((resolve,reject)=>{navigator.geolocation.getCurrentPosition(resolve,reject);});
    }

    btnComplete?.addEventListener('click',async()=>{
      debugStep('Complete button clicked');
      if(!confirm('Mark job complete?')){debugStep('User cancelled completion');return;}
      debugStep('User confirmed completion');
      const finalNote=(prompt('Enter final note:')||'').trim();
      debugStep('Final note entered');
      if(!finalNote){alert('Final note is required');debugStep('Final note missing');return;}
      debugStep('Requesting final photos');
      const photos=await pickFinalPhotos();
      if(!photos?.length){alert('At least one completion photo is required');debugStep('No photos selected');return;}
      debugStep('Requesting signature');
      const signature=await captureSignature();
      if(!signature){alert('Signature required');debugStep('Signature missing');return;}
      debugStep('Getting location');
      btnComplete.disabled=true;
      try{
        const pos=await getLocation();
        debugStep('Location received');
        const fd=new FormData();
        fd.append('job_id',jobId);
        fd.append('technician_id',techId);
        fd.append('location_lat',pos.coords.latitude);
        fd.append('location_lng',pos.coords.longitude);
        fd.append('final_note',finalNote);
        photos.forEach(p=>fd.append('final_photos[]',p));
        fd.append('signature',signature);
        fd.append('csrf_token',csrf);
        debugStep('Submitting completion to server');
        const res=await fetch('/api/job_complete.php',{method:'POST',body:fd,credentials:'same-origin'});
        const data=await res.json();
        if(!data?.ok) throw new Error(data?.error||'Failed');
        debugStep('Server responded success');
        if(window.FieldOpsToast && typeof FieldOpsToast.show==='function'){
          FieldOpsToast.show('Job completed');
        }else{
          alert('Job completed');
        }
        btnComplete.disabled=true;
        btnComplete.classList.add('d-none');
        updateStatus('completed');
        debugStep('Completion workflow finished');
      }catch(err){
        alert(err.message||'Failed');
        debugStep(`Completion failed: ${err.message||'Unknown error'}`);
        btnComplete.disabled=false;
      }
    });
  });
})();
