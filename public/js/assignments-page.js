// /public/js/assignments-page.js
(() => {
  function ready(fn){document.readyState!='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  function debounce(fn,ms){let t;return (...args)=>{clearTimeout(t);t=setTimeout(()=>fn.apply(this,args),ms);};}
  ready(() => {
    const tbody=document.getElementById('assignments-tbody');
    const search=document.getElementById('filter-search');
    async function loadAssignments(){
      const params=new URLSearchParams();
      if(search&&search.value.trim()) params.set('search',search.value.trim());
      try{
        const res=await fetch('/assignments_table.php?'+params.toString(),{credentials:'same-origin'});
        const html=await res.text();
        tbody.innerHTML=html;
      }catch(err){
        tbody.innerHTML='<tr><td colspan="6" class="text-danger">Failed to load assignments</td></tr>';
      }
    }
    const trigger=debounce(loadAssignments,300);
    search&&search.addEventListener('input',trigger);
    loadAssignments();
  });
})();
