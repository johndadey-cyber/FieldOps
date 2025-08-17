
# Test Suite

This directory contains unit, integration, and smoke tests for the FieldOps application.

## Database Configuration and Seeding

Several tests and helper scripts interact with a MySQL database. Before running them,
configure a database and seed it with minimal data so an active employee exists.

1. **Configure credentials** – set the connection values in `config/local.env.php`
   or export the following environment variables:

   ```php
   <?php
   return [
       'DB_HOST' => '127.0.0.1',
       'DB_PORT' => '3306',
       'DB_NAME' => 'fieldops_integration',
       'DB_USER' => 'root',
       'DB_PASS' => 'root',
   ];
   ```

2. **Create the database schema**:

   ```bash
   mysql -u root -p -e 'CREATE DATABASE fieldops_integration;'
   # Load your schema if necessary
   mysql -u root -p fieldops_integration < path/to/schema.sql
   ```

3. **Seed an active employee** – many scripts (e.g. `tests/smoke.sh`) expect at least
   one active employee. You can insert one manually with SQL:

   ```sql
   INSERT INTO people (first_name, last_name, email)
   VALUES ('Test', 'Employee', 'test@example.com');
   SET @person_id = LAST_INSERT_ID();
   INSERT INTO employees (person_id, is_active) VALUES (@person_id, 1);
   ```

4. **Verify connectivity** – run the sanity check script to ensure the database is reachable
   and populated:

   ```bash
   php tests/db_sanity_check.php
   ```

With these steps completed, scripts and tests should be able to locate an active employee
record and execute successfully.
=======
# Tests

## Availability add window script

Run the availability window script either by executing it directly if it is marked as executable, or by passing it to Bash:

```bash
./availability_add_window.sh
```

or

```bash
bash availability_add_window.sh
```


