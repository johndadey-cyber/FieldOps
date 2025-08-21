// /public/js/tech_jobs.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function fmtStatus(s){return (s||'').replace(/_/g,' ');}
  function truncate(str,len){str=String(str||'');return str.length>len?str.slice(0,len-1)+'\u2026':str;}
  const timeFmt=new Intl.DateTimeFormat([], {hour:'numeric',minute:'numeric'});
  function formatTime(str){try{const [h,m]=String(str).split(':');const d=new Date();d.setHours(Number(h),Number(m));return timeFmt.format(d);}catch(e){return str;}}
  const skillIcons={plumbing:'🛠️',electrical:'⚡',hvac:'❄️',cleaning:'🧹',default:'🛠️'};
  ready(async () => {
    const list=document.getElementById('jobs-list');
    const banner=document.getElementById('date-banner');
    const techId=window.TECH_ID;
    const csrf=window.CSRF_TOKEN;
    const today=window.TODAY||new Date().toISOString().slice(0,10);
    if(banner){banner.textContent=window.TODAY_HUMAN||'';}

    const btnNote=document.getElementById('btn-add-note');
    const btnPhoto=document.getElementById('btn-add-photo');
    const btnMap=document.getElementById('btn-map-view');
    const btnStart=document.getElementById('btn-start-job');
    const fileInput=document.createElement('input');
    fileInput.type='file';
    fileInput.accept='image/*';
    fileInput.style.display='none';
    document.body.appendChild(fileInput);
    let jobsCache=[];let firstJobId=null;

    btnNote?.addEventListener('click',async()=>{
      if(!firstJobId)return;const note=prompt('Enter note');
      if(!note)return;const fd=new FormData();fd.append('job_id',firstJobId);
      fd.append('technician_id',techId);fd.append('note',note);
      fd.append('csrf_token',csrf);
      fetch('/api/job_notes_add.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(res=>{if(!res?.ok)alert(res?.error||'Failed to save note');});
    });

    btnPhoto?.addEventListener('click',()=>fileInput.click());
    fileInput.addEventListener('change',()=>{
      if(!firstJobId||!fileInput.files.length){fileInput.value='';return;}
      const fd=new FormData();fd.append('job_id',firstJobId);
      fd.append('technician_id',techId);fd.append('csrf_token',csrf);
      Array.from(fileInput.files).forEach(f=>fd.append('photos[]',f));
      fetch('/api/job_photos_upload.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(res=>{if(!res?.ok)alert(res?.error||'Upload failed');});
      fileInput.value='';
    });

    btnMap?.addEventListener('click',()=>{
      if(!jobsCache.length)return;
      const addrs=jobsCache.map(j=>[j.customer.address_line1,j.customer.city].filter(Boolean).join(' '));
      const dest=addrs[addrs.length-1];
      const waypts=addrs.slice(0,-1).join('|');
      const url=`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(dest)}${waypts?`&waypoints=${encodeURIComponent(waypts)}`:''}`;
      window.open(url,'_blank');
    });

    btnStart?.addEventListener('click',e=>{e.preventDefault();
      const base=btnStart.getAttribute('href')||'/add_job.php';
      window.location.href=`${base}?redirect=${encodeURIComponent(location.pathname)}`;
    });
    const startParam=new URLSearchParams(location.search).get('start');
    if(startParam==='1'&&btnStart){btnStart.click();}

    if(!list) return;
    try{
      const res=await fetch(`/api/jobs.php?start=${today}&end=${today}&status=in_progress,assigned`,{credentials:'same-origin'});
      const data=await res.json();
      if(!Array.isArray(data)) throw new Error('Invalid response');
      const jobs=data.filter(j => (j.assigned_employees||[]).some(e => Number(e.id)===Number(techId)));
      jobsCache=jobs;firstJobId=jobs[0]?.job_id||null;
      if(!jobs.length){
        list.innerHTML='<div class="text-center text-muted py-5">No jobs for today.</div>';
        return;
      }
      jobs.forEach(j => {
        const card=document.createElement('div');
        card.className='card mb-3 shadow-sm';
        const time=j.scheduled_time?formatTime(j.scheduled_time):'Unscheduled';
        const type=(j.job_skills&&j.job_skills[0]?.name)||'';
        const icon=skillIcons[type.toLowerCase()]||skillIcons.default;
        const address=[j.customer.address_line1,j.customer.city].filter(Boolean).join(', ');
        const truncAddr=truncate(address,40);
        const statusColor={assigned:'bg-secondary',in_progress:'bg-warning text-dark',completed:'bg-success'}[(j.status||'').toLowerCase()]||'bg-secondary';
        card.innerHTML=`<div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="fw-bold">${h(j.customer.first_name)} ${h(j.customer.last_name)}</div>
              <div class="text-muted small">${h(truncAddr)}</div>
              <div class="small"><span class="me-1">${icon}</span>${h(time)} &middot; ${h(type)}</div>
            </div>
            <span class="badge ${statusColor}">${h(fmtStatus(j.status))}</span>
          </div>
          <div class="mt-3 d-flex gap-3">
            <a class="btn btn-outline-primary flex-fill" target="_blank" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}" aria-label="Open directions">GO</a>
            <a class="btn btn-primary flex-fill" href="tech_job.php?id=${j.job_id}" aria-label="View details for job ${h(j.job_id)}">Details &gt;</a>
          </div>
        </div>`;
        list.appendChild(card);
      });
    }catch(err){
      console.error(err);
      list.innerHTML='<div class="text-center text-danger py-5">Failed to load jobs.</div>';
    }
  });
})();

