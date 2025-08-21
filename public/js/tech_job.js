// /public/js/tech_job.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function fmtStatus(s){return (s||'').replace(/_/g,' ');}
  const dateTimeFmt=new Intl.DateTimeFormat([], {dateStyle:'medium',timeStyle:'short'});
  const csrf=window.CSRF_TOKEN;
  const jobId=Number(window.JOB_ID);
  const techId=Number(window.TECH_ID);
  ready(() => {
    const headerEl=document.getElementById('job-header');
    const customerEl=document.getElementById('customer-info');
    const timerEl=document.getElementById('job-timer');
    const checklistEl=document.getElementById('checklist');
    const progressEl=document.getElementById('checklist-progress');
    const notesEl=document.getElementById('job-notes');
    const photosEl=document.getElementById('job-photos');
    const btnStart=document.getElementById('btn-start-job');
    const menuChecklist=document.getElementById('menu-checklist');
    const menuNote=document.getElementById('menu-note');
    const menuCamera=document.getElementById('menu-camera');
    const menuMap=document.getElementById('menu-map');
    const btnComplete=document.getElementById('btn-complete');
    const checklistCollapse=document.getElementById('checklist-collapse');
    let scheduledStart=null;
    let notesCache=[];
    let checklistCache=[];
    let timerInterval=null;
    let jobStatus='';
    let mapUrl='#';
    const fileInput=document.createElement('input');
    fileInput.type='file';
    fileInput.accept='image/*';
    fileInput.multiple=true;
    fileInput.style.display='none';
    document.body.appendChild(fileInput);

    function queueOffline(item){if(window.offlineQueue){window.offlineQueue.add(item);}}

    function updateStatus(st){
      jobStatus=st;
      const badge=document.getElementById('status-badge');
      if(!badge) return;
      const map={assigned:'secondary',in_progress:'warning',completed:'success'};
      const cls=map[st]||'secondary';
      badge.className=`badge bg-${cls}`;
      badge.textContent=fmtStatus(st);
    }

      const EARLY_MS=5*60*1000;
      function isTooEarly(){return scheduledStart && Date.now()<scheduledStart.getTime()-EARLY_MS;}

    function startTimer(start){
      if(timerInterval) clearInterval(timerInterval);
      const startTime=new Date(start);
      function tick(){
        const diff=Math.max(0, Math.floor((Date.now()-startTime.getTime())/1000));
        const hms=[Math.floor(diff/3600),Math.floor(diff%3600/60),diff%60].map(v=>String(v).padStart(2,'0'));
        timerEl.textContent=hms.join(':');
      }
      tick();
      timerInterval=setInterval(tick,1000);
    }

    function fetchNotes(){
      fetch(`/api/job_notes_list.php?job_id=${jobId}&csrf_token=${encodeURIComponent(csrf)}`,{credentials:'same-origin'})
        .then(r=>r.json())
        .then(data=>{if(!data?.ok) throw new Error();notesCache=data.notes||[];renderNotes(false);})
        .catch(()=>{if(notesEl) notesEl.innerHTML='<div class="text-muted">No notes</div>';});
    }

    function renderNotes(showAll){
      if(!notesEl) return;
      if(!notesCache.length){notesEl.innerHTML='<div class="text-muted">No notes</div>';return;}
      notesEl.innerHTML='';
      const list=showAll?notesCache:notesCache.slice(0,2);
      list.forEach(n=>{
        const div=document.createElement('div');
        div.className='mb-2';
        div.innerHTML=`<div>${h(n.note)}</div><div class="text-muted small">${h(dateTimeFmt.format(new Date(n.created_at)))}</div>`;
        notesEl.appendChild(div);
      });
      if(!showAll && notesCache.length>2){
        const link=document.createElement('a');
        link.href='#';
        link.textContent='See all';
        link.addEventListener('click',e=>{e.preventDefault();renderNotes(true);});
        notesEl.appendChild(link);
      }
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
        const wrapper=document.createElement('div');
        wrapper.className='text-center';
        const img=document.createElement('img');
        img.src=`/${p.path}`;
        img.className='img-thumbnail mb-1';
        img.style.maxWidth='120px';
        const cap=document.createElement('div');
        cap.className='small text-muted';
        cap.textContent=p.label||'';
        wrapper.appendChild(img);
        wrapper.appendChild(cap);
        photosEl.appendChild(wrapper);
      });
    }

    function appendPreview(file,tag){
      if(!photosEl) return;
      const url=URL.createObjectURL(file);
      const wrapper=document.createElement('div');
      wrapper.className='text-center';
      const img=document.createElement('img');
      img.src=url;
      img.className='img-thumbnail mb-1';
      img.style.maxWidth='120px';
      const cap=document.createElement('div');
      cap.className='small text-muted';
      cap.textContent=tag||'';
      wrapper.appendChild(img);
      wrapper.appendChild(cap);
      photosEl.appendChild(wrapper);
    }

    function fetchChecklist(){
      fetch(`/api/job_checklist.php?job_id=${jobId}&csrf_token=${encodeURIComponent(csrf)}`,{credentials:'same-origin'})
        .then(r=>r.json())
        .then(data=>{if(!Array.isArray(data?.items)) throw new Error();checklistCache=data.items;renderChecklist();})
        .catch(()=>{checklistEl.innerHTML='<div class="text-muted">No checklist</div>';});
    }

    function renderChecklist(){
      checklistEl.innerHTML='';
      checklistCache.forEach(it=>{
        const li=document.createElement('li');
        li.className='form-check mb-2';
        li.innerHTML=`<input class="form-check-input" type="checkbox" data-id="${it.id}" ${it.completed?'checked':''}> <label class="form-check-label">${h(it.description||'')}</label>`;
        checklistEl.appendChild(li);
      });
      updateProgress();
    }

    function updateProgress(){
      const total=checklistCache.length;
      const done=checklistCache.filter(it=>it.completed).length;
      const pct=total?Math.round((done/total)*100):0;
      progressEl.style.width=pct+'%';
      progressEl.setAttribute('aria-valuenow', String(pct));
      if(jobStatus==='in_progress' && total>0 && done===total){
        btnComplete?.classList.remove('d-none');
        if(btnComplete) btnComplete.disabled=false;
      }else{
        btnComplete?.classList.add('d-none');
        if(btnComplete) btnComplete.disabled=true;
      }
    }

    checklistEl.addEventListener('change',async e=>{
      const cb=e.target;
      if(!(cb instanceof HTMLInputElement)) return;
      const id=Number(cb.dataset.id);
      const completed=cb.checked;
      const item=checklistCache.find(i=>i.id===id);
      if(item){item.completed=completed;}
      updateProgress();
      const fd=new FormData();
      fd.append('job_id',jobId);
      fd.append('items',JSON.stringify([{id,completed}]));
      fd.append('csrf_token',csrf);
      const send=()=>fetch('/api/job_checklist_update.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
      if(navigator.onLine){
        try{const res=await send();if(!res?.ok) throw new Error();}
        catch(e){queueOffline({type:'checklist',job_id:jobId,items:[{id,completed}],csrf_token:csrf});}
      }else{queueOffline({type:'checklist',job_id:jobId,items:[{id,completed}],csrf_token:csrf});}
    });

    fetch(`/api/get_job_details.php?id=${jobId}`,{credentials:'same-origin'})
      .then(r=>r.json())
      .then(data=>{
        if(!data?.ok) throw new Error('Job not found');
        const j=data.job;
        const c=j.customer||{};
        const addr=[c.address_line1,c.city,c.state,c.postal_code].filter(Boolean).join(', ');
        mapUrl=`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addr)}`;
        if(menuMap){
          menuMap.href=mapUrl;
          menuMap.addEventListener('click',e=>{e.preventDefault();window.open(mapUrl,'_blank');});
        }
        const callUrl=c.phone?`tel:${c.phone}`:'#';
        headerEl.innerHTML=`<div class="d-flex justify-content-between align-items-center"><div><div class="h5 mb-0">Job #${h(j.id)}</div><div class="text-muted">${h(j.job_type||'')}</div></div><span id="status-badge" class="badge"></span></div>`;
        customerEl.innerHTML=`<div class="fw-bold">${h(c.first_name||'')} ${h(c.last_name||'')}</div><div>${h(c.address_line1||'')}</div><div>${h(c.city||'')}, ${h(c.state||'')} ${h(c.postal_code||'')}</div><div class="mt-2"><button class="btn btn-sm btn-outline-secondary me-2" id="copy-address" aria-label="Copy address">Copy</button><a class="btn btn-sm btn-outline-primary me-2" href="${mapUrl}" target="_blank" rel="noopener">Directions</a>${c.phone?`<a class="btn btn-sm btn-outline-primary" href="${callUrl}" aria-label="Call customer">Call</a>`:''}</div>`;
        document.getElementById('copy-address')?.addEventListener('click',()=>{navigator.clipboard?.writeText(addr);});
        scheduledStart=j.scheduled_time?new Date(`${j.scheduled_date}T${j.scheduled_time}`):null;
        updateStatus(j.status);
        if(j.status==='assigned'){
          btnStart.classList.remove('d-none');
          if(isTooEarly()){btnStart.classList.add('disabled');btnStart.disabled=true;}
        }
        if(j.status==='in_progress'){startTimer(j.started_at||new Date());}
        fetchNotes();
        fetchPhotos();
        fetchChecklist();
        setInterval(()=>{
          if(btnStart&&(btnStart.disabled||btnStart.classList.contains('disabled'))&&!isTooEarly()){
            btnStart.disabled=false;btnStart.classList.remove('disabled');
          }
        },60000);
      })
      .catch(err=>{customerEl.innerHTML=`<div class="text-danger">${h(err.message)}</div>`;});

    btnStart?.addEventListener('click',()=>{
      if(isTooEarly()){alert('You may start the job up to 5 minutes before the scheduled time.');return;}
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
            updateStatus(res.status||'in_progress');
            startTimer(new Date());
            updateProgress();
          })
          .catch(err=>{alert(err.message||'Failed');btnStart.disabled=false;});
      },()=>{alert('Location required');btnStart.disabled=false;});
    });

    menuChecklist?.addEventListener('click',()=>{
      bootstrap.Collapse.getOrCreateInstance(checklistCollapse).toggle();
    });

    menuNote?.addEventListener('click',async()=>{
      const note=await showNoteModal();
      if(!note) return;
      const fd=new FormData();
      fd.append('job_id',jobId);
      fd.append('technician_id',techId);
      fd.append('note',note);
      fd.append('csrf_token',csrf);
      const addLocal=id=>{notesCache.unshift({id:id,job_id:jobId,technician_id:techId,note,created_at:new Date().toISOString(),is_final:false});renderNotes(false);};
      const send=()=>fetch('/api/job_notes_add.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
      if(navigator.onLine){
        try{const res=await send();if(!res?.ok) throw new Error(res?.error||'Failed');addLocal(res.id);alert('Note added');}
        catch(e){queueOffline({type:'note',job_id:jobId,technician_id:techId,note,csrf_token:csrf});addLocal(Date.now());alert('Note saved offline');}
      }else{queueOffline({type:'note',job_id:jobId,technician_id:techId,note,csrf_token:csrf});addLocal(Date.now());alert('Note saved offline');}
    });

    menuCamera?.addEventListener('click',()=>fileInput.click());
    fileInput.addEventListener('change',async()=>{
      const files=Array.from(fileInput.files||[]);
      if(!files.length) return;
      const info=await showPhotoModal(files);
      fileInput.value='';
      if(!info) return;
      const send=async(list)=>{const fd=new FormData();fd.append('job_id',jobId);fd.append('technician_id',techId);fd.append('csrf_token',csrf);list.forEach(it=>{fd.append('photos[]',it.file);fd.append('tags[]',it.tag);fd.append('annotations[]',it.annotation||'');});const r=await fetch('/api/job_photos_upload.php',{method:'POST',body:fd,credentials:'same-origin'});return r.json();};
      if(navigator.onLine){
        try{const res=await send(info);if(!res?.ok) throw new Error(res?.error||'Failed');alert('Photo uploaded');fetchPhotos();}
        catch(e){for(const it of info){const b64=await fileToBase64(it.file);queueOffline({type:'photo',job_id:jobId,technician_id:techId,photo:b64,tag:it.tag,annotation:it.annotation||'',csrf_token:csrf});appendPreview(it.file,it.tag);}alert('Photo saved offline');}
      }else{for(const it of info){const b64=await fileToBase64(it.file);queueOffline({type:'photo',job_id:jobId,technician_id:techId,photo:b64,tag:it.tag,annotation:it.annotation||'',csrf_token:csrf});appendPreview(it.file,it.tag);}alert('Photo saved offline');}
    });

    btnComplete?.addEventListener('click',()=>{window.location.href=`/tech_job_complete.php?id=${jobId}`;});

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
          const overlay=document.createElement('div');
          overlay.className='position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 text-white';
          overlay.textContent='Listening…';
          modal.querySelector('.modal-content').appendChild(overlay);
          recog=new SR();
          recog.lang='en-US';
          recog.onresult=e=>{const t=Array.from(e.results).map(r=>r[0].transcript).join(' ');textarea.value+=(textarea.value?' ':'')+t;};
          const end=()=>overlay.remove();
          recog.onend=end;
          recog.onerror=end;
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
  });
})();

