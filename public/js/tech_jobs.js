// /public/js/tech_jobs.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function fmtStatus(s){return (s||'').replace(/_/g,' ');}  
  function truncate(str,len){str=String(str||'');return str.length>len?str.slice(0,len-1)+'\u2026':str;}
  ready(async () => {
    const list=document.getElementById('jobs-list');
    const banner=document.getElementById('date-banner');
    const techId=window.TECH_ID;
    const today=window.TODAY||new Date().toISOString().slice(0,10);
    if(banner){banner.textContent=window.TODAY_HUMAN||'';}

    const btnNote=document.getElementById('btn-add-note');
    const btnPhoto=document.getElementById('btn-add-photo');
    const btnMap=document.getElementById('btn-map-view');
    const fileInput=document.createElement('input');
    fileInput.type='file';
    fileInput.accept='image/*';
    fileInput.style.display='none';
    document.body.appendChild(fileInput);
    btnNote?.addEventListener('click',()=>alert('Add Note tapped'));
    btnPhoto?.addEventListener('click',()=>fileInput.click());
    fileInput.addEventListener('change',()=>{if(fileInput.files[0]){alert('Photo selected');fileInput.value='';}});
    btnMap?.addEventListener('click',()=>alert('Map view coming soon'));

    if(!list) return;
    try{
      const res=await fetch(`/api/jobs.php?start=${today}&end=${today}&status=in_progress,assigned`,{credentials:'same-origin'});
      const data=await res.json();
      if(!Array.isArray(data)) throw new Error('Invalid response');
      const jobs=data.filter(j => (j.assigned_employees||[]).some(e => Number(e.id)===Number(techId)));
      if(!jobs.length){
        list.innerHTML='<div class="text-center text-muted py-5">No jobs for today.</div>';
        return;
      }
      jobs.forEach(j => {
        const card=document.createElement('div');
        card.className='card mb-3 shadow-sm';
        const time=j.scheduled_time?j.scheduled_time.slice(0,5):'Unscheduled';
        const type=(j.job_skills&&j.job_skills[0]?.name)||'';
        const address=[j.customer.address_line1,j.customer.city].filter(Boolean).join(', ');
        const truncAddr=truncate(address,40);
        const statusColor={assigned:'secondary',in_progress:'info',completed:'success'}[(j.status||'').toLowerCase()]||'secondary';
        card.innerHTML=`<div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="fw-bold">${h(j.customer.first_name)} ${h(j.customer.last_name)}</div>
              <div class="text-muted small">${h(truncAddr)}</div>
              <div class="small">${h(time)} &middot; ${h(type)}</div>
            </div>
            <span class="badge bg-${statusColor}">${h(fmtStatus(j.status))}</span>
          </div>
          <div class="mt-3 d-flex gap-2">
            <a class="btn btn-outline-primary flex-fill" target="_blank" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}">Go</a>
            <a class="btn btn-primary flex-fill" href="tech_job.php?id=${j.job_id}">View Details</a>
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

