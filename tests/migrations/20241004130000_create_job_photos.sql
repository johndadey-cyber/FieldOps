CREATE TABLE IF NOT EXISTS job_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    technician_id INT NOT NULL,
    path VARCHAR(255) NOT NULL,
    label VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_photos_job_id (job_id),
    CONSTRAINT fk_job_photos_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_photos_technician FOREIGN KEY (technician_id) REFERENCES employees(id) ON DELETE RESTRICT
);
