CREATE OR REPLACE VIEW job_employee AS
    SELECT job_id, employee_id, assigned_at
    FROM job_employee_assignment;
