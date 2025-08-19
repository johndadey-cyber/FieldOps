CREATE TABLE IF NOT EXISTS employee_skills (
    employee_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    proficiency VARCHAR(20) NULL,
    CONSTRAINT fk_es_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_es_skill FOREIGN KEY (skill_id) REFERENCES skills(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uq_employee_skill (employee_id, skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
