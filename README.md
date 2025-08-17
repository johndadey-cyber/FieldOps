# FieldOps

FieldOps is a scheduling and job assignment application for service teams.

## Employee Schedule Badges

The employees view displays a badge describing each worker's schedule status. The badge is derived from availability and assigned jobs for the day:

- **Available** – availability exists and no jobs overlap.
- **Booked** – jobs fully consume all available time.
- **Partially Booked** – some availability remains after scheduled jobs.
- **No Hours** – no availability is defined for that day.

These badges mirror the `status` and `summary` computed in `Availability::statusForEmployeesOnDate`.
