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
    const btnStart=document.getElementById('btn-start-job');
    const btnNote=document.getElementById('btn-add-note');
    const btnPhoto=document.getElementById('btn-add-photo');
    const btnChecklist=document.getElementById('btn-checklist');
    const btnComplete=document.getElementById('btn-complete');
    let statusEl;
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
    fab.innerHTML=`<div class="fab-menu d-none"><button class="btn btn-light" id="fab-note">Note</button><button class="btn btn-light" id="fab-photo">Photo</button></div><button class="btn btn-primary fab-main">+</button>`;
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

    fetch(`/api/get_job_details.php?id=${jobId}`,{credentials:'same-origin'})
      .then(r=>r.json())
      .then(data=>{
        if(!data?.ok) throw new Error('Job not found');
        const j=data.job;
        details.innerHTML=`<h1 class="h5">${h(j.description||'')}</h1>
<div>${h(j.customer?.first_name||'')} ${h(j.customer?.last_name||'')}</div>
<div class="text-muted">${h(j.customer?.address_line1||'')}</div>
<div id="job-status" class="text-muted"></div>`;
        statusEl=document.getElementById('job-status');

        if(statusEl){statusEl.textContent=`Status: ${fmtStatus(j.status)}`;}
        const status=(j.status||'').toLowerCase();
        if(status==='assigned'){btnStart.classList.remove('d-none');}
        if(status==='in_progress'){btnComplete.classList.remove('d-none');}

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
          .then(res=>{
            if(!res?.ok) throw new Error(res?.error||'Failed');

            btnStart.classList.add('d-none');
            btnComplete.classList.remove('d-none');
            if(statusEl){statusEl.textContent=`Status: ${fmtStatus(res.status||'in_progress')}`;}
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

    async function showNoteModal(){
      return new Promise(resolve=>{
        const modal=document.createElement('div');
        modal.className='modal fade';
        modal.innerHTML=`<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Note</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><textarea class="form-control" id="note-text" rows="4"></textarea><button type="button" class="btn btn-sm btn-secondary mt-2" id="voice-btn">Voice</button></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="note-save">Save</button></div></div></div>`;
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
      });
    }

    async function showPhotoModal(files){
      return new Promise(resolve=>{
        const modal=document.createElement('div');
        modal.className='modal fade';
        let bodyHtml='';
        files.forEach((f,i)=>{
          const url=URL.createObjectURL(f);
          bodyHtml+=`<div class="mb-3"><img src="${url}" class="img-fluid mb-1"><select class="form-select mb-1" data-idx="${i}"><option>Before</option><option>After</option><option>Other</option></select><input type="text" class="form-control" placeholder="Annotation (optional)" data-anno="${i}"></div>`;
        });
        modal.innerHTML=`<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Upload Photos</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">${bodyHtml}</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="photo-save">Upload</button></div></div></div>`;
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
        if(details){
          const status=document.createElement('div');
          status.className='mt-2 badge bg-success';
          status.textContent='Completed';
          details.appendChild(status);
        }
        debugStep('Completion workflow finished');
      }catch(err){
        alert(err.message||'Failed');
        debugStep(`Completion failed: ${err.message||'Unknown error'}`);
        btnComplete.disabled=false;
      }
    });
  });
})();
