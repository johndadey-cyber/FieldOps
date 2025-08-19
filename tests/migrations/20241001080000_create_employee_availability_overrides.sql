CREATE TABLE IF NOT EXISTS employee_availability_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    status VARCHAR(20) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'CUSTOM',
    start_time TIME NULL,
    end_time TIME NULL,
    reason VARCHAR(255) NULL,
    CONSTRAINT fk_eao_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
