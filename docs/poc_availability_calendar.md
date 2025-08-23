# Availability Calendar POC

This demo page renders availability, overrides and scheduled jobs on a calendar.

## Quick start

1. Run the PHP dev server:
   ```bash
   make serve
   # or
   php -S 127.0.0.1:8010 -t public
   ```
2. Visit [http://127.0.0.1:8010/poc_availability_calendar.php](http://127.0.0.1:8010/poc_availability_calendar.php).
3. The page uses `initCalendar` and `renderCalendar` from `js/calendar-render.js` with sample data.

### What to look for

- Green blocks represent availability windows and can be dragged or resized.
- Yellow blocks represent availability overrides (also draggable/resizable).
- Blue events show scheduled jobs and are fixed.

### Using real data

Replace the hard‑coded arrays with a fetch to the API:

```js
const resp = await fetch('/api/availability/index.php?employee_id=1&week_start=2024-04-29');
const data = await resp.json();
renderCalendar(calendar, data.availability, data.overrides, data.jobs, '2024-04-29', 1);
```
