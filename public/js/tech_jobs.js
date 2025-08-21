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
      const today=window.TODAY||new Date().toISOString().slice(0,10);
    if(banner){banner.textContent=window.TODAY_HUMAN||'';}

      const btnStart=document.getElementById('btn-start-job');

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

