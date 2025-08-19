CREATE TABLE IF NOT EXISTS employee_availability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    day_of_week VARCHAR(9) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    start_date DATE NULL,
    CONSTRAINT fk_availability_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_availability_window (employee_id, day_of_week, start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
