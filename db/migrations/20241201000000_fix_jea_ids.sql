-- Assign unique IDs and enforce primary key on job_employee_assignment
SET @i := 0;
UPDATE job_employee_assignment
SET id = (@i:=@i+1)
ORDER BY job_id, employee_id;

ALTER TABLE job_employee_assignment
    MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY;
