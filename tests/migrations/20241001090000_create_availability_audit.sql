CREATE TABLE IF NOT EXISTS availability_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_avail_audit_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
