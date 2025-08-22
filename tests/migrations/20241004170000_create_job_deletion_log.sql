CREATE TABLE IF NOT EXISTS job_deletion_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_deletion_log_job_id (job_id),
    INDEX idx_job_deletion_log_user_id (user_id),
    CONSTRAINT fk_job_deletion_log_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_deletion_log_user FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
