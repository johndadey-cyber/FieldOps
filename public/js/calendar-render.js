export function initCalendar(onSelect, onEventEdit) {
  const calendarEl = document.getElementById('calendar');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'timeGridWeek',
    selectable: true,
    select: onSelect,
    eventClick: info => onEventEdit(info.event)
  });
  calendar.render();
  return calendar;
}

export function renderCalendar(calendar, availability, overrides, jobs, weekStart, currentEmployeeId) {
  calendar.removeAllEvents();
  let added = 0;

  for (const it of Array.isArray(availability) ? availability : []) {
    if (!it.start || !it.end) continue;
    if (it.start >= it.end) continue;
    const color = '#198754';
    calendar.addEvent({
      id: 'win-' + it.id,
      start: it.start,
      end: it.end,
      backgroundColor: color,
      borderColor: color,
      editable: true,
      extendedProps: { type: 'window', raw: it }
    });
    added++;
  }

  for (const ov of Array.isArray(overrides) ? overrides : []) {
    if (!ov.date) continue;
    const startTime = ov.start_time || '00:00';
    const endTime = ov.end_time || ov.start_time || '00:00';
    const start = `${ov.date}T${startTime}`;
    const end = `${ov.date}T${endTime}`;
    calendar.addEvent({
      id: 'ov-' + ov.id,
      start,
      end,
      backgroundColor: '#ffc107',
      borderColor: '#ffc107',
      editable: true,
      extendedProps: { type: 'override', raw: ov }
    });
    added++;
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
    calendar.addEvent({
      id: 'job-' + job.job_id,
      title: `Job #${job.job_id}`,
      start,
      end,
      backgroundColor: '#0d6efd',
      borderColor: '#0d6efd',
      editable: false,
      extendedProps: { type: 'job', raw: job }
    });
    added++;
  }

  const calEmpty = document.getElementById('calendarEmpty');
  if (added === 0) {
    if (calEmpty) calEmpty.classList.remove('d-none');
  } else if (calEmpty) {
    calEmpty.classList.add('d-none');
  }
}
