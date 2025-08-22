const daysOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function canonicalDay(val) {
  if (typeof val === 'number' || /^\d+$/.test(String(val))) {
    const n = parseInt(val, 10);
    return dayNames[((n % 7) + 7) % 7];
  }
  const key = String(val || '').toLowerCase();
  const map = {
    sun: 'Sunday', sunday: 'Sunday',
    mon: 'Monday', monday: 'Monday',
    tue: 'Tuesday', tues: 'Tuesday', tuesday: 'Tuesday',
    wed: 'Wednesday', wednesday: 'Wednesday',
    thu: 'Thursday', thur: 'Thursday', thurs: 'Thursday', thursday: 'Thursday',
    fri: 'Friday', friday: 'Friday',
    sat: 'Saturday', saturday: 'Saturday'
  };
  return map[key] || val;
}

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
  const wsDate = new Date(weekStart + 'T00:00:00');
  let added = 0;

  const byDay = {};
  for (const it of Array.isArray(availability) ? availability : []) {
    const day = canonicalDay(it.day_of_week);
    it.day_of_week = day;
    if (!byDay[day]) byDay[day] = [];
    byDay[day].push(it);
  }

  for (const day of daysOrder) {
    const arr = byDay[day] || [];
    const idx = daysOrder.indexOf(day);
    const d = new Date(wsDate);
    d.setDate(d.getDate() + idx);
    const dayStr = d.toISOString().slice(0,10);
    const applicable = arr.filter(it => !it.start_date || it.start_date <= dayStr);
    let latest = '';
    for (const it of applicable) {
      const sd = it.start_date || '';
      if (sd > latest) latest = sd;
    }
    const final = applicable.filter(it => (it.start_date || '') === latest);
    for (const it of final) {
      if (!it.start_time || !it.end_time) continue;
      const start = `${dayStr}T${it.start_time}`;
      const end = `${dayStr}T${it.end_time}`;
      if (start >= end) continue;
      const future = it.start_date && it.start_date > weekStart;
      const color = future ? '#0dcaf0' : '#198754';
      calendar.addEvent({
        id: 'win-' + it.id,
        start,
        end,
        backgroundColor: color,
        borderColor: color,
        editable: true,
        extendedProps: { type: 'window', raw: it }
      });
      added++;
    }
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
