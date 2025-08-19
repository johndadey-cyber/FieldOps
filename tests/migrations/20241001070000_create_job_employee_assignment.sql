CREATE TABLE IF NOT EXISTS job_employee_assignment (
    job_id INT NOT NULL,
    employee_id INT NOT NULL,
    PRIMARY KEY (job_id, employee_id),
    CONSTRAINT fk_jea_job FOREIGN KEY (job_id) REFERENCES jobs(id)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_jea_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
