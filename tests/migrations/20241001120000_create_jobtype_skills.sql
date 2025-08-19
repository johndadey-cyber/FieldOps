CREATE TABLE IF NOT EXISTS jobtype_skills (
    job_type_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_jobtype_skills_jobtype FOREIGN KEY (job_type_id) REFERENCES job_types(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_jobtype_skills_skill FOREIGN KEY (skill_id) REFERENCES skills(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uq_jobtype_skill (job_type_id, skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
