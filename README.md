# FieldOps

FieldOps is a scheduling and job assignment application for service teams.

## Employee Schedule Badges

The employees view displays a badge describing each worker's schedule status. The badge is derived from availability and assigned jobs for the day:

- **Available** – availability exists and no jobs overlap.
- **Booked** – jobs fully consume all available time.
- **Partially Booked** – some availability remains after scheduled jobs.
- **No Hours** – no availability is defined for that day.

These badges mirror the `status` and `summary` computed in `Availability::statusForEmployeesOnDate`.

## Testing Setup

The test suite expects a MySQL service running on port `3306` with the root
password `1234!@#$`. A quick way to start one is with Docker:

```
docker run --name fieldops-mysql -e MYSQL_ROOT_PASSWORD='1234!@#$' -p 3306:3306 -d mysql:8
```

If you use a different port or password, set `DB_PORT` and `DB_PASS` in your
environment to match the running service.

## Development Database

To keep development data separate from integration tests, create a local MySQL
database and run the schema migrations against it:

```
mysql -u root -p -e 'CREATE DATABASE IF NOT EXISTS fieldops_development;'
php scripts/migrate_dev_db.php
```

The application defaults to `fieldops_development` when `APP_ENV` is `dev`.
Connection settings can be overridden in `config/local.env.php` or via
environment variables.

When running integration tests (`APP_ENV=test`), optional credentials can be
placed in `config/test.env.php`. This keeps test database settings isolated
from your local development configuration.

## CDN Scripts

When including scripts from a CDN, always add an `integrity` hash and
`crossorigin="anonymous"` attribute. These enable Subresource Integrity (SRI),
helping ensure the fetched resources have not been tampered with.
