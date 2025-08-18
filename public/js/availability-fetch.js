export async function fetchAvailability(eid, weekStart) {
  if (!eid) return { availability: [], overrides: [] };
  try {
    const res = await fetch(`availability_manager.php?action=list&employee_id=${encodeURIComponent(eid)}&week_start=${encodeURIComponent(weekStart)}`, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    const data = await res.json();
    return {
      availability: Array.isArray(data.availability) ? data.availability : [],
      overrides: Array.isArray(data.overrides) ? data.overrides : []
    };
  } catch (err) {
    console.error('fetchAvailability failed', err);
    return { availability: [], overrides: [] };
  }
}

export async function fetchJobs(weekStart) {
  const wsDate = new Date(weekStart + 'T00:00:00');
  const weDate = new Date(wsDate);
  weDate.setDate(weDate.getDate() + 6);
  const we = weDate.toISOString().slice(0,10);
  try {
    const res = await fetch(`api/jobs.php?start=${weekStart}&end=${we}`, { headers: { 'Accept': 'application/json' }});
    const jobs = await res.json();
    return Array.isArray(jobs) ? jobs : [];
  } catch (err) {
    console.error('fetchJobs failed', err);
    return [];
  }
}
