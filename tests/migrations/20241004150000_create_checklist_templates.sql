CREATE TABLE IF NOT EXISTS checklist_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    position INT UNSIGNED NULL,
    CONSTRAINT fk_checklist_templates_job_type FOREIGN KEY (job_type_id) REFERENCES job_types(id)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO job_types (id, name) VALUES
    (1, 'Basic Installation'),
    (2, 'Routine Maintenance')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO checklist_templates (job_type_id, description, position) VALUES
    (1, 'Review work order', 1),
    (1, 'Confirm materials on site', 2),
    (1, 'Perform installation', 3),
    (1, 'Test and verify operation', 4),
    (2, 'Inspect equipment condition', 1),
    (2, 'Perform routine maintenance', 2),
    (2, 'Update service log', 3);
