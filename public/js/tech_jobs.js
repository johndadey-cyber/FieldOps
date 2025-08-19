// /public/js/tech_jobs.js
(() => {
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  ready(async () => {
    const list=document.getElementById('jobs-list');
    if(!list) return;
    const techId=window.TECH_ID;
    const today=window.TODAY||new Date().toISOString().slice(0,10);
    try{
      const res=await fetch(`/api/jobs.php?start=${today}&end=${today}&status=scheduled,in_progress`,{credentials:'same-origin'});
      const data=await res.json();
      if(!Array.isArray(data)) throw new Error('Invalid response');
      const jobs=data.filter(j => (j.assigned_employees||[]).some(e => Number(e.id)===Number(techId)));
      if(!jobs.length){
        list.innerHTML='<div class="list-group-item text-muted">No jobs for today.</div>';
        return;
      }
      jobs.forEach(j => {
        const a=document.createElement('a');
        a.className='list-group-item list-group-item-action';
        a.href=`tech_job.php?id=${j.job_id}`;
        const time=j.scheduled_time?j.scheduled_time.slice(0,5):'Unscheduled';
        a.innerHTML=`<div class="fw-bold">${h(j.customer.first_name)} ${h(j.customer.last_name)}</div>
<small class="text-muted">${h(j.customer.address_line1||'')}</small><br>
<small>${h(time)}</small>`;
        list.appendChild(a);
      });
    }catch(err){
      console.error(err);
      list.innerHTML='<div class="list-group-item text-danger">Failed to load jobs.</div>';
    }
  });
})();
