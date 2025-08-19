CREATE TABLE IF NOT EXISTS job_skill (
    job_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_job_skill_job FOREIGN KEY (job_id) REFERENCES jobs(id)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_job_skill_skill FOREIGN KEY (skill_id) REFERENCES skills(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uq_job_skill (job_id, skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
