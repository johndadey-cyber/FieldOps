CREATE TABLE IF NOT EXISTS job_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    technician_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    is_final TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_notes_job_id (job_id),
    CONSTRAINT fk_job_notes_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_notes_technician FOREIGN KEY (technician_id) REFERENCES employees(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
