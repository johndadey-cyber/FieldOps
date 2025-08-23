export function initCalendar(onSelect, onEventEdit) {
  const calendar = new toastui.Calendar('#calendar', {
    defaultView: 'week',
    useCreationPopup: false,
    useDetailPopup: false
  });

  if (onSelect) {
    calendar.on('select', ev => {
      const start = ev.start.toISOString().slice(0,16);
      const end = ev.end.toISOString().slice(0,16);
      onSelect({ start, end });
    });
  }

  if (onEventEdit) {
    calendar.on('clickSchedule', ev => onEventEdit(ev.schedule));
  }

  return calendar;
}

export function renderCalendar(calendar, availability, overrides, jobs, weekStart, currentEmployeeId) {
  calendar.clear();
  const events = [];

  for (const it of Array.isArray(availability) ? availability : []) {
    let { start, end } = it;
    if (!start || !end) {
      if (!weekStart || !it.day_of_week || !it.start_time || !it.end_time) continue;
      const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
      const idx = days.indexOf(it.day_of_week);
      if (idx === -1) continue;
      const base = new Date(weekStart + 'T00:00:00');
      base.setDate(base.getDate() + idx);
      const date = base.toISOString().slice(0,10);
      start = `${date}T${it.start_time}`;
      end = `${date}T${it.end_time}`;
    }
    if (start >= end) continue;
    events.push({
      id: 'win-' + it.id,
      calendarId: 'availability',
      start,
      end,
      backgroundColor: '#198754',
      borderColor: '#198754',
      category: 'time',
      isReadOnly: false,
      raw: it
    });
  }

  for (const ov of Array.isArray(overrides) ? overrides : []) {
    if (!ov.date) continue;
    const startTime = ov.start_time || '00:00';
    const endTime = ov.end_time || ov.start_time || '00:00';
    const start = `${ov.date}T${startTime}`;
    const end = `${ov.date}T${endTime}`;
    events.push({
      id: 'ov-' + ov.id,
      calendarId: 'override',
      start,
      end,
      backgroundColor: '#ffc107',
      borderColor: '#ffc107',
      category: 'time',
      isReadOnly: false,
      raw: ov
    });
  }

  for (const job of Array.isArray(jobs) ? jobs : []) {
    const emps = Array.isArray(job.assigned_employees) ? job.assigned_employees : [];
    if (!emps.some(e => e.id === currentEmployeeId)) continue;
    if (!job.scheduled_time) continue;
    const start = `${job.scheduled_date}T${job.scheduled_time}`;
    const dur = job.duration_minutes || 60;
    const endDt = new Date(start);
    endDt.setMinutes(endDt.getMinutes() + dur);
    const end = endDt.toISOString().slice(0,16);
    events.push({
      id: 'job-' + job.job_id,
      calendarId: 'jobs',
      title: `Job #${job.job_id}`,
      start,
      end,
      backgroundColor: '#0d6efd',
      borderColor: '#0d6efd',
      category: 'time',
      isReadOnly: true,
      raw: job
    });
  }

  calendar.createEvents(events);

  const calEmpty = document.getElementById('calendarEmpty');
  if (events.length === 0) {
    if (calEmpty) calEmpty.classList.remove('d-none');
  } else if (calEmpty) {
    calEmpty.classList.add('d-none');
  }
}
