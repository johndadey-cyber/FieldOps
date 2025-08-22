export async function fetchAvailability(eid, weekStart) {
  if (!eid) return { availability: [], events: [], overrides: [] };
  try {
    const res = await fetch(`api/availability/index.php?employee_id=${encodeURIComponent(eid)}&week_start=${encodeURIComponent(weekStart)}`, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    const data = await res.json();
    if (!res.ok || data.ok === false) {
      console.error('fetchAvailability failed', data);
      return { availability: [], events: [], overrides: [] };
    }
    return {
      availability: Array.isArray(data.availability) ? data.availability : [],
      events: Array.isArray(data.events) ? data.events : [],
      overrides: Array.isArray(data.overrides) ? data.overrides : []
    };
  } catch (err) {
    console.error('fetchAvailability failed', err);
    return { availability: [], events: [], overrides: [] };
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
