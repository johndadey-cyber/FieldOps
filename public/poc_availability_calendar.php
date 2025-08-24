<?php declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Availability Calendar POC</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div id="calendar"></div>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script type="module">
    import { initCalendar, renderCalendar } from './js/calendar-render.js';

    const availability = [
      { id: 1, start: '2024-05-01T09:00', end: '2024-05-01T12:00' },
      { id: 2, start: '2024-05-02T13:00', end: '2024-05-02T17:00' }
    ];

    const overrides = [
      { id: 1, date: '2024-05-03', start_time: '14:00', end_time: '16:00' }
    ];

    const jobs = [
      {
        job_id: 101,
        scheduled_date: '2024-05-04',
        scheduled_time: '10:00',
        duration_minutes: 90,
        assigned_employees: [{ id: 1 }]
      }
    ];

    const calendar = initCalendar(() => {}, () => {});
    renderCalendar(calendar, availability, overrides, jobs, '2024-04-29', 1);
  </script>
</body>
</html>
