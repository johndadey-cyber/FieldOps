// /public/js/tech_job_complete.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function fileToBase64(file){
    return new Promise((resolve,reject)=>{
      const reader=new FileReader();
      reader.onload=()=>resolve(reader.result);
      reader.onerror=()=>reject(new Error('Read failed'));
      reader.readAsDataURL(file);
    });
  }
  ready(()=>{
    const csrf=window.CSRF_TOKEN;
    const jobId=Number(window.JOB_ID);
    const techId=Number(window.TECH_ID);
    const noteEl=document.getElementById('final-note');
    const btnVoice=document.getElementById('btn-voice-note');
    const noteFeedback=noteEl.parentElement.nextElementSibling;
    const photoInput=document.getElementById('photo-input');
    const photoList=document.getElementById('photo-list');
    const btnAddPhoto=document.getElementById('btn-add-photo');
    const photoFeedback=document.getElementById('photo-feedback');
    const sigCanvas=document.getElementById('sig-canvas');
    const btnClearSig=document.getElementById('btn-clear-sig');
    const btnSubmit=document.getElementById('btn-submit');
    const sigFeedback=document.getElementById('sig-feedback');
    const ctx=sigCanvas.getContext('2d');
    ctx.strokeStyle='#000';
    ctx.lineWidth=2;
    let drawing=false;
    let signed=false;
    function getPos(e){const r=sigCanvas.getBoundingClientRect();const p=e.touches?e.touches[0]:e;return {x:p.clientX-r.left,y:p.clientY-r.top};}
    function start(e){drawing=true;signed=true;const p=getPos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);e.preventDefault();}
    function draw(e){if(!drawing)return;const p=getPos(e);ctx.lineTo(p.x,p.y);ctx.stroke();e.preventDefault();}
    function end(){drawing=false;}
    sigCanvas.addEventListener('mousedown',start);
    sigCanvas.addEventListener('mousemove',draw);
    sigCanvas.addEventListener('mouseup',end);
    sigCanvas.addEventListener('mouseleave',end);
    sigCanvas.addEventListener('touchstart',start);
    sigCanvas.addEventListener('touchmove',draw);
    sigCanvas.addEventListener('touchend',end);
    btnClearSig.addEventListener('click',()=>{ctx.clearRect(0,0,sigCanvas.width,sigCanvas.height);signed=false;});
    btnAddPhoto.addEventListener('click',()=>photoInput.click());
    photoInput.addEventListener('change',()=>{
      const files=Array.from(photoInput.files||[]);
      files.forEach(f=>{
        const url=URL.createObjectURL(f);
        const div=document.createElement('div');
        div.className='photo-preview';
        div.innerHTML=`<img src="${url}" class="img-thumbnail"><select class="form-select form-select-sm mt-1"><option>Before</option><option selected>After</option><option>Other</option></select>`;
        div.file=f;
        photoList.appendChild(div);
      });
      photoInput.value='';
      if(files.length>0){btnAddPhoto.classList.remove('is-invalid');photoFeedback.textContent='';photoFeedback.classList.remove('d-block');}
    });
    noteEl.addEventListener('input',()=>{if(noteEl.value.trim()){noteEl.classList.remove('is-invalid');noteFeedback.textContent='';noteFeedback.classList.remove('d-block');}});
    sigCanvas.addEventListener('mousedown',()=>{sigCanvas.classList.remove('is-invalid');sigFeedback.textContent='';sigFeedback.classList.remove('d-block');});
    sigCanvas.addEventListener('touchstart',()=>{sigCanvas.classList.remove('is-invalid');sigFeedback.textContent='';sigFeedback.classList.remove('d-block');});
    if(window.SpeechRecognition||window.webkitSpeechRecognition){
      const Rec=window.SpeechRecognition||window.webkitSpeechRecognition;
      const rec=new Rec();
      rec.lang='en-US';
      rec.interimResults=false;
      btnVoice.addEventListener('click',()=>{try{rec.start();}catch(_){}});
      rec.addEventListener('result',e=>{
        const txt=e.results[0][0].transcript;
        noteEl.value=(noteEl.value?noteEl.value+' ':'')+txt;
        noteEl.dispatchEvent(new Event('input'));
        noteEl.focus();
      });
    }else{
      btnVoice.classList.add('d-none');
    }
    function getLocation(){return new Promise((resolve,reject)=>{navigator.geolocation.getCurrentPosition(resolve,reject);});}
    btnSubmit.addEventListener('click',async()=>{
      btnSubmit.disabled=true;
      try{
        noteEl.classList.remove('is-invalid');noteFeedback.textContent='';noteFeedback.classList.remove('d-block');
        btnAddPhoto.classList.remove('is-invalid');photoFeedback.textContent='';photoFeedback.classList.remove('d-block');
        sigCanvas.classList.remove('is-invalid');sigFeedback.textContent='';sigFeedback.classList.remove('d-block');
        const note=(noteEl.value||'').trim();
        const previews=Array.from(photoList.querySelectorAll('.photo-preview'));
        let hasError=false;
        if(!note){noteEl.classList.add('is-invalid');noteFeedback.textContent='Summary note required';noteFeedback.classList.add('d-block');hasError=true;}
        if(previews.length===0){btnAddPhoto.classList.add('is-invalid');photoFeedback.textContent='At least one photo required';photoFeedback.classList.add('d-block');hasError=true;}
        if(!signed){sigCanvas.classList.add('is-invalid');sigFeedback.textContent='Signature required';sigFeedback.classList.add('d-block');hasError=true;}
        if(hasError){btnSubmit.disabled=false;return;}
        const photos=[];const tags=[];
        for(const div of previews){
          const b64=await fileToBase64(div.file);
          photos.push(b64);
          const tag=div.querySelector('select').value;
          tags.push(tag);
        }
        const sigData=sigCanvas.toDataURL('image/png');
        const pos=await getLocation();
        if(!navigator.onLine){
          await window.offlineQueue.add({type:'completion',job_id:jobId,technician_id:techId,final_note:note,photos,tags,signature:sigData,location:{lat:pos.coords.latitude,lng:pos.coords.longitude},csrf_token:csrf});
          FieldOpsToast.show('Submission saved offline','info');
          setTimeout(()=>{window.location.href='/tech_jobs.php';},1000);
          return;
        }
        const fd=new FormData();
        fd.append('job_id',jobId);
        fd.append('technician_id',techId);
        fd.append('final_note',note);
        photos.forEach(p=>fd.append('final_photos[]',p));
        tags.forEach(t=>fd.append('final_tags[]',t));
        fd.append('signature',sigData);
        fd.append('location_lat',pos.coords.latitude);
        fd.append('location_lng',pos.coords.longitude);
        fd.append('csrf_token',csrf);
        const res=await fetch('/api/job_complete.php',{method:'POST',body:fd,credentials:'same-origin'});
        const data=await res.json();
        if(!data?.ok) throw new Error(data?.error||'Failed');
        FieldOpsToast.show('Job completion submitted','success');
        setTimeout(()=>{window.location.href='/tech_jobs.php';},1000);
      }catch(err){
        FieldOpsToast.show(err.message||'Failed','danger');
        btnSubmit.disabled=false;
      }
    });
  });
})();
