CREATE TABLE IF NOT EXISTS job_job_type (
    job_id INT UNSIGNED NOT NULL,
    job_type_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_job_job_type_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_job_job_type_type FOREIGN KEY (job_type_id) REFERENCES job_types(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_job_job_type (job_id, job_type_id)
);
