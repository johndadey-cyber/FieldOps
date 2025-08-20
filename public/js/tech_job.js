// /public/js/tech_job.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function fmtStatus(s){return (s||'').replace(/_/g,' ');}
  const timeFmt=new Intl.DateTimeFormat([], {hour:'numeric',minute:'numeric'});
  const dateTimeFmt=new Intl.DateTimeFormat([], {dateStyle:'medium',timeStyle:'short'});
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
    fileInput.multiple=true;
    fileInput.style.display='none';
    document.body.appendChild(fileInput);

    const offlineKey='techJobQueue';
    function queueOffline(item){
      const list=JSON.parse(localStorage.getItem(offlineKey)||'[]');
      list.push(item);
      localStorage.setItem(offlineKey,JSON.stringify(list));
    }
    async function processQueue(){
      if(!navigator.onLine) return;
      const list=JSON.parse(localStorage.getItem(offlineKey)||'[]');
      const remaining=[];
      for(const item of list){
        try{
          if(item.type==='note'){
            const fd=new FormData();
            fd.append('job_id',item.job_id);
            fd.append('technician_id',item.technician_id);
            fd.append('note',item.note);
            fd.append('csrf_token',csrf);
            const r=await fetch('/api/job_notes_add.php',{method:'POST',body:fd,credentials:'same-origin'});
            const j=await r.json();
            if(!j?.ok) throw new Error();
          }else if(item.type==='photo'){
            const fd=new FormData();
            fd.append('job_id',item.job_id);
            fd.append('technician_id',item.technician_id);
            fd.append('csrf_token',csrf);
            fd.append('tags[]',item.tag);
            fd.append('annotations[]',item.annotation||'');
            fd.append('photos[]',dataURLtoBlob(item.photo),'photo.png');
            const r=await fetch('/api/job_photos_upload.php',{method:'POST',body:fd,credentials:'same-origin'});
            const j=await r.json();
            if(!j?.ok) throw new Error();
          }else if(item.type==='checklist'){
            const fd=new FormData();
            fd.append('job_id',item.job_id);
            fd.append('items',JSON.stringify(item.items));
            fd.append('csrf_token',csrf);
            const r=await fetch('/api/job_checklist_update.php',{method:'POST',body:fd,credentials:'same-origin'});
            const j=await r.json();
            if(!j?.ok) throw new Error();
          }
        }catch(e){
          remaining.push(item);
        }
      }
      localStorage.setItem(offlineKey,JSON.stringify(remaining));
    }
    window.addEventListener('online',processQueue);
    processQueue();

    const fab=document.createElement('div');
    fab.className='fab';
    fab.innerHTML=`<div class="fab-menu d-none"><button class="btn btn-light" id="fab-note" aria-label="Add note">Note</button><button class="btn btn-light" id="fab-photo" aria-label="Add photo">Photo</button></div><button class="btn btn-primary fab-main" aria-label="Toggle quick actions">+</button>`;
    document.body.appendChild(fab);
    const fabMenu=fab.querySelector('.fab-menu');
    fab.querySelector('.fab-main').addEventListener('click',()=>fabMenu.classList.toggle('d-none'));
    fab.querySelector('#fab-note').addEventListener('click',()=>{fabMenu.classList.add('d-none');btnNote?.click();});
    fab.querySelector('#fab-photo').addEventListener('click',()=>{fabMenu.classList.add('d-none');btnPhoto?.click();});

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
        div.innerHTML=`<div>${h(n.note)}</div><div class="text-muted small">${h(dateTimeFmt.format(new Date(n.created_at)))}</div>`;
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
        const win=start?`${timeFmt.format(start)} - ${timeFmt.format(end)}`:'';
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

    btnNote?.addEventListener('click',async()=>{
      const note=await showNoteModal();
      if(!note) return;
      const fd=new FormData();
      fd.append('job_id',jobId);
      fd.append('technician_id',techId);
      fd.append('note',note);
      fd.append('csrf_token',csrf);

      const send=()=>fetch('/api/job_notes_add.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
      if(navigator.onLine){
        try{const res=await send();if(!res?.ok) throw new Error(res?.error||'Failed');alert('Note added');}
        catch(e){queueOffline({type:'note',job_id:jobId,technician_id:techId,note});alert('Note saved offline');}
      }else{queueOffline({type:'note',job_id:jobId,technician_id:techId,note});alert('Note saved offline');}
    });

    btnPhoto?.addEventListener('click',()=>fileInput.click());
    fileInput.addEventListener('change',async()=>{
      const files=Array.from(fileInput.files||[]);
      if(!files.length) return;
      const info=await showPhotoModal(files);
      fileInput.value='';
      if(!info) return;
      const send=async(list)=>{
        const fd=new FormData();
        fd.append('job_id',jobId);
        fd.append('technician_id',techId);
        fd.append('csrf_token',csrf);
        list.forEach(it=>{fd.append('photos[]',it.file);fd.append('tags[]',it.tag);fd.append('annotations[]',it.annotation||'');});
        const r=await fetch('/api/job_photos_upload.php',{method:'POST',body:fd,credentials:'same-origin'});
        return r.json();
      };
      if(navigator.onLine){
        try{const res=await send(info);if(!res?.ok) throw new Error(res?.error||'Failed');alert('Photo uploaded');}
        catch(e){for(const it of info){const b64=await fileToBase64(it.file);queueOffline({type:'photo',job_id:jobId,technician_id:techId,photo:b64,tag:it.tag,annotation:it.annotation||''});}alert('Photo saved offline');}
      }else{for(const it of info){const b64=await fileToBase64(it.file);queueOffline({type:'photo',job_id:jobId,technician_id:techId,photo:b64,tag:it.tag,annotation:it.annotation||''});}alert('Photo saved offline');}
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
        const send=()=>fetch('/api/job_checklist_update.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
        if(navigator.onLine){
          try{const data2=await send();if(!data2?.ok) throw new Error(data2?.error||'Failed');alert('Checklist saved');}
          catch(e){queueOffline({type:'checklist',job_id:jobId,items:checked});alert('Checklist saved offline');}
        }else{queueOffline({type:'checklist',job_id:jobId,items:checked});alert('Checklist saved offline');}
      }catch(err){alert(err.message||'Checklist failed');}
    });

    async function showChecklistModal(items){
      return new Promise(resolve=>{
        const modal=document.createElement('div');
        const titleId='chkModalTitle';
        modal.className='modal fade';
        modal.setAttribute('tabindex','-1');
        modal.setAttribute('role','dialog');
        modal.setAttribute('aria-modal','true');
        modal.setAttribute('aria-labelledby',titleId);
        modal.innerHTML=`<div class="modal-dialog" role="document"><div class="modal-content">
<div class="modal-header"><h5 id="${titleId}" class="modal-title">Checklist</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body" role="list"></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button><button type="button" class="btn btn-primary" id="chk-save" aria-label="Save checklist">Save</button></div>
</div></div>`;
        const body=modal.querySelector('.modal-body');
        items.forEach((it,i)=>{
          const id='chk'+i;
          const div=document.createElement('div');
          div.className='form-check mb-2';
          div.setAttribute('role','listitem');
          div.innerHTML=`<input class="form-check-input" type="checkbox" id="${id}" ${it.completed?'checked':''}>
<label class="form-check-label" for="${id}">${h(it.item||it.description||'')}</label>`;
          const cb=div.querySelector('input');
          cb.setAttribute('aria-label',it.item||it.description||'');
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
        modal.addEventListener('shown.bs.modal',()=>{modal.querySelector('input,textarea,select,button')?.focus();},{once:true});
      });
    }

    async function showNoteModal(){
      return new Promise(resolve=>{
        const modal=document.createElement('div');
        const titleId='noteModalTitle';
        modal.className='modal fade';
        modal.setAttribute('tabindex','-1');
        modal.setAttribute('role','dialog');
        modal.setAttribute('aria-modal','true');
        modal.setAttribute('aria-labelledby',titleId);
        modal.innerHTML=`<div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h5 id="${titleId}" class="modal-title">Add Note</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><textarea class="form-control" id="note-text" rows="4"></textarea><button type="button" class="btn btn-sm btn-secondary mt-2" id="voice-btn" aria-label="Voice input">Voice</button></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Cancel">Cancel</button><button type="button" class="btn btn-primary" id="note-save" aria-label="Save note">Save</button></div></div></div>`;
        document.body.appendChild(modal);
        const bsModal=new bootstrap.Modal(modal);
        const textarea=modal.querySelector('#note-text');
        let recog;
        modal.addEventListener('hidden.bs.modal',()=>{modal.remove();if(recog)recog.stop();resolve(null);});
        modal.querySelector('#note-save').addEventListener('click',()=>{const val=textarea.value.trim();bsModal.hide();resolve(val);});
        modal.querySelector('#voice-btn').addEventListener('click',()=>{
          const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
          if(!SR){alert('Speech recognition not supported');return;}
          recog=new SR();
          recog.lang='en-US';
          recog.onresult=e=>{const t=Array.from(e.results).map(r=>r[0].transcript).join(' ');textarea.value+=(textarea.value?' ':'')+t;};
          recog.start();
        });
        bsModal.show();
        modal.addEventListener('shown.bs.modal',()=>{textarea.focus();},{once:true});
      });
    }

    async function showPhotoModal(files){
      return new Promise(resolve=>{
        const modal=document.createElement('div');
        modal.className='modal fade';
        modal.setAttribute('tabindex','-1');
        modal.setAttribute('role','dialog');
        modal.setAttribute('aria-modal','true');
        const titleId='photoModalTitle';
        modal.setAttribute('aria-labelledby',titleId);
        let bodyHtml='';
        files.forEach((f,i)=>{
          const url=URL.createObjectURL(f);
          bodyHtml+=`<div class="mb-3"><img src="${url}" class="img-fluid mb-1"><select class="form-select mb-1" data-idx="${i}"><option>Before</option><option>After</option><option>Other</option></select><input type="text" class="form-control" placeholder="Annotation (optional)" data-anno="${i}"></div>`;
        });
        modal.innerHTML=`<div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h5 id="${titleId}" class="modal-title">Upload Photos</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">${bodyHtml}</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Cancel">Cancel</button><button type="button" class="btn btn-primary" id="photo-save" aria-label="Upload photos">Upload</button></div></div></div>`;
        document.body.appendChild(modal);
        const bsModal=new bootstrap.Modal(modal);
        modal.addEventListener('hidden.bs.modal',()=>{modal.remove();resolve(null);});
        modal.querySelector('#photo-save').addEventListener('click',()=>{
          const list=[];
          files.forEach((f,i)=>{
            const tag=modal.querySelector(`select[data-idx="${i}"]`).value;
            const annotation=modal.querySelector(`input[data-anno="${i}"]`).value;
            list.push({file:f,tag,annotation});
          });
          bsModal.hide();
          resolve(list);
        });
        bsModal.show();
        modal.addEventListener('shown.bs.modal',()=>{modal.querySelector('select,input')?.focus();},{once:true});
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

    function dataURLtoBlob(dataUrl){
      const arr=dataUrl.split(',');
      const mime=(arr[0].match(/:(.*?);/)||[])[1]||'application/octet-stream';
      const bstr=atob(arr[1]);
      let n=bstr.length;const u8=new Uint8Array(n);
      while(n--){u8[n]=bstr.charCodeAt(n);}return new Blob([u8],{type:mime});
    }

    btnComplete?.addEventListener('click',()=>{
      window.location.href=`/tech_job_complete.php?id=${jobId}`;
    });
  });
})();
