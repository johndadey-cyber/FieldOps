// /public/js/jobs.js
(() => {
  function ready(fn){document.readyState!='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function h(s){const d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  ready(() => {
    const $tbody=document.getElementById('jobs-tbody');
    const $start=document.getElementById('filter-start');
    const $end=document.getElementById('filter-end');
    const $status=document.getElementById('filter-status');
    const $search=document.getElementById('filter-search');
    const $showPast=document.getElementById('filter-show-past');

    function selectedValues(sel){return Array.from(sel?.selectedOptions||[]).map(o=>o.value);}

    function syncShowPast(){
      if(!$showPast||!$status) return;
      const sts=selectedValues($status);
      $showPast.checked=sts.length===1&&sts[0]==='completed';
    }

    async function loadJobs(){
      const params=new URLSearchParams();
      if($start?.value) params.set('start',$start.value);
      if($end?.value) params.set('end',$end.value);
      const sts=selectedValues($status); if(sts.length) params.set('status',sts.join(','));
      if($search?.value.trim()) params.set('search',$search.value.trim());
      if($showPast?.checked) params.set('show_past','1');
      try{
        const res=await fetch('/api/jobs.php?'+params.toString(),{credentials:'same-origin'});
        const data=await res.json();
        if(!res.ok) throw new Error(data?.error||`Request failed: ${res.status}`);
        if(!Array.isArray(data)) throw new Error('Invalid response');
        renderRows(data);
      }catch(err){
        console.error('loadJobs failed',err);
        $tbody.innerHTML=`<tr><td colspan="7" class="text-danger">${h(err.message||'Failed to load jobs')}</td></tr>`;
      }
    }

    function renderRows(rows){
      const d=new Date();
      const todayStr=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      $tbody.innerHTML='';
      if(!rows.length){
        $tbody.innerHTML='<tr><td colspan="7" class="text-center text-muted py-3">No jobs match your filters</td></tr>';
        return;
      }
      rows.forEach(job=>{
        const tr=document.createElement('tr');
        // Date
        const dateCell=document.createElement('td');
        let dLabel='';
        if(job.scheduled_date){
          const [y,m,d] = job.scheduled_date.split('-');
          dLabel = h(`${m}/${d}/${y}`);
          if(job.scheduled_date===todayStr){dLabel+=' <span class="badge bg-primary-subtle text-primary border">Today</span>';}
        }
        dateCell.innerHTML=dLabel; tr.appendChild(dateCell);
        // Time
        const timeCell=document.createElement('td');
        if(job.scheduled_time){const dt=new Date('1970-01-01T'+job.scheduled_time);timeCell.textContent=dt.toLocaleTimeString(undefined,{hour:'numeric',minute:'2-digit'}).toLowerCase();}
        else{timeCell.innerHTML='<span class="badge bg-danger-subtle text-danger border">Unscheduled</span>';} tr.appendChild(timeCell);
        // Customer
        const custCell=document.createElement('td');
        const name=h(job.customer.first_name)+' '+h(job.customer.last_name);
        const addr=h(job.customer.address_line1||'');
        custCell.innerHTML=`<a href="customer_form.php?id=${job.customer.id}" target="_blank">${name}</a><br><small class="text-muted">${addr}</small>`;
        tr.appendChild(custCell);
        // Job skills
        const jsCell=document.createElement('td');
        const skills = Array.isArray(job.skills) && job.skills.length
            ? job.skills
            : Array.isArray(job.job_skills) ? job.job_skills : [];
        if(skills.length){
          jsCell.innerHTML=skills
            .map(s=>`<span class="badge bg-secondary-subtle text-secondary border me-1">${h(s.name)}</span>`)
            .join('');
        } else {
          jsCell.textContent='—';
        }
        tr.appendChild(jsCell);
        // Employees
        const empCell=document.createElement('td');
        if(job.assigned_employees?.length){
          const names=job.assigned_employees.map(e=>`<a href="employee_form.php?id=${e.id}" target="_blank">${h(e.first_name)} ${h(e.last_name)}</a>`);
          let html=names.slice(0,2).join(', ');
          if(names.length>2) html+=` <span class="text-muted">+${names.length-2} more</span>`;
          empCell.innerHTML=html;
        } else {
          empCell.innerHTML='<span class="badge bg-secondary-subtle text-secondary border">Unassigned</span>';
        }
        tr.appendChild(empCell);
        // Status
        const statusCell=document.createElement('td');
        const sc={
          'draft':'bg-light text-dark border',
          'scheduled':'bg-secondary-subtle text-secondary border',
          'assigned':'bg-primary-subtle text-primary border',
          'in_progress':'bg-warning-subtle text-warning border',
          'completed':'bg-success-subtle text-success border',
          'closed':'bg-secondary-subtle text-secondary border',
          'cancelled':'bg-secondary-subtle text-secondary border'
        };
        const label=job.status.replace(/_/g,' ');
        statusCell.innerHTML=`<span class="badge ${sc[job.status]||'bg-light text-dark border'} badge-status">${h(label)}</span>`;
        tr.appendChild(statusCell);
        // Actions
        const actCell=document.createElement('td');
        actCell.classList.add('text-nowrap');
        actCell.innerHTML=`<button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#assignmentsModal" data-job-id="${job.job_id}">Assign</button>
<a class="btn btn-sm btn-outline-secondary me-1" href="edit_job.php?id=${job.job_id}" target="_blank">Edit</a>
<a class="btn btn-sm btn-outline-danger" href="job_delete.php?id=${job.job_id}" onclick="return confirm('Delete this job? This cannot be undone.');">Delete</a>`;
        tr.appendChild(actCell);
        $tbody.appendChild(tr);
      });
    }

    function debounce(fn,ms){let t;return (...args)=>{clearTimeout(t);t=setTimeout(()=>fn.apply(this,args),ms);};}
    const trigger=debounce(loadJobs,300);
    [$start,$end,$showPast].forEach(el=>{el&&el.addEventListener('change',trigger);});
    $status&&$status.addEventListener('change',()=>{syncShowPast();trigger();});
    $search&&$search.addEventListener('input',trigger);
    window.addEventListener('assignments:updated', loadJobs);
    syncShowPast();
    loadJobs();
  });
})();
