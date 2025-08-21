<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/JobTypeSkill.php';

final class JobTypeSkillTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE job_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE skills (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE jobtype_skills (job_type_id INTEGER NOT NULL, skill_id INTEGER NOT NULL)');
        return $pdo;
    }

    private function seed(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO job_types (name) VALUES ('TypeA')");
        $pdo->exec("INSERT INTO skills (name) VALUES ('Alpha'), ('Beta'), ('Gamma')");
    }

    public function testAllForJobTypeReturnsSkillDetails(): void
    {
        $pdo = $this->createPdo();
        $this->seed($pdo);
        JobTypeSkill::create($pdo, 1, 1);
        JobTypeSkill::create($pdo, 1, 3);
        $skills = JobTypeSkill::allForJobType($pdo, 1);
        $this->assertSame([
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 3, 'name' => 'Gamma'],
        ], $skills);
    }

    public function testCreateInsertsRow(): void
    {
        $pdo = $this->createPdo();
        $this->seed($pdo);
        $this->assertTrue(JobTypeSkill::create($pdo, 1, 1));
        $count = (int)$pdo->query('SELECT COUNT(*) FROM jobtype_skills')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testUpdateChangesSkill(): void
    {
        $pdo = $this->createPdo();
        $this->seed($pdo);
        JobTypeSkill::create($pdo, 1, 1);
        $this->assertTrue(JobTypeSkill::update($pdo, 1, 1, 2));
        $old = (int)$pdo->query('SELECT COUNT(*) FROM jobtype_skills WHERE job_type_id = 1 AND skill_id = 1')->fetchColumn();
        $new = (int)$pdo->query('SELECT COUNT(*) FROM jobtype_skills WHERE job_type_id = 1 AND skill_id = 2')->fetchColumn();
        $this->assertSame(0, $old);
        $this->assertSame(1, $new);
    }

    public function testDeleteRemovesRow(): void
    {
        $pdo = $this->createPdo();
        $this->seed($pdo);
        JobTypeSkill::create($pdo, 1, 1);
        $this->assertTrue(JobTypeSkill::delete($pdo, 1, 1));
        $count = (int)$pdo->query('SELECT COUNT(*) FROM jobtype_skills')->fetchColumn();
        $this->assertSame(0, $count);
    }
}

