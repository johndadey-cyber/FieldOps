<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Job.php';

final class JobModelTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));

        $pdo->exec('CREATE TABLE jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT NOT NULL,
            started_at TEXT NULL,
            completed_at TEXT NULL,
            location_lat REAL NULL,
            location_lng REAL NULL,
            updated_at TEXT NULL
        )');
        $pdo->exec('CREATE TABLE job_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            is_final INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE job_photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE job_completion (
            job_id INTEGER PRIMARY KEY,
            signature_path TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE skills (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE job_skill (
            job_id INTEGER NOT NULL,
            skill_id INTEGER NOT NULL
        )');

        return $pdo;
    }

    public function testAllowedStatusesReturnsExpectedList(): void
    {
        $expected = ['draft','scheduled','assigned','in_progress','completed','closed','cancelled'];
        $this->assertSame($expected, Job::allowedStatuses());
    }

    public function testDeleteRemovesRow(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO jobs (status) VALUES ('draft')");
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
        $this->assertSame(1, Job::delete($pdo, 1));
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }

    public function testStartUpdatesOnlyWhenAssigned(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO jobs (status) VALUES ('assigned')");
        $pdo->exec("INSERT INTO jobs (status) VALUES ('scheduled')");

        $resultAssigned = Job::start($pdo, 1, 12.34, 56.78);
        $this->assertTrue($resultAssigned);
        $row = $pdo->query('SELECT status, started_at, location_lat, location_lng, updated_at FROM jobs WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('in_progress', $row['status']);
        $this->assertNotNull($row['started_at']);
        $this->assertSame(12.34, (float)$row['location_lat']);
        $this->assertSame(56.78, (float)$row['location_lng']);
        $this->assertNotNull($row['updated_at']);

        $resultScheduled = Job::start($pdo, 2, 1.0, 2.0);
        $this->assertFalse($resultScheduled);
        $row = $pdo->query('SELECT status, started_at FROM jobs WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('scheduled', $row['status']);
        $this->assertNull($row['started_at']);
    }

    public function testCompleteRequiresNotePhotoSignature(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO jobs (status, started_at) VALUES ('in_progress', '2024-01-01 10:00:00')");
        $pdo->exec("INSERT INTO jobs (status, started_at) VALUES ('in_progress', '2024-01-02 10:00:00')");

        $pdo->exec("INSERT INTO job_notes (job_id, is_final) VALUES (1, 1)");
        $pdo->exec("INSERT INTO job_photos (job_id) VALUES (1)");
        $pdo->exec("INSERT INTO job_completion (job_id, signature_path) VALUES (1, 'sig.png')");

        $this->assertTrue(Job::complete($pdo, 1, 9.9, 8.8));
        $row = $pdo->query('SELECT status, completed_at FROM jobs WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('completed', $row['status']);
        $this->assertNotNull($row['completed_at']);

        $pdo->exec("INSERT INTO job_notes (job_id, is_final) VALUES (2, 1)");
        $this->assertFalse(Job::complete($pdo, 2, 9.9, 8.8));
        $row = $pdo->query('SELECT status, completed_at FROM jobs WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('in_progress', $row['status']);
        $this->assertNull($row['completed_at']);
    }

    public function testGetSkillsForJobReturnsSorted(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO jobs (status) VALUES ('draft')");
        $pdo->exec("INSERT INTO skills (id, name) VALUES (1, 'Welding'), (2, 'Cutting'), (3, 'Assembly')");
        $pdo->exec("INSERT INTO job_skill (job_id, skill_id) VALUES (1, 1), (1, 3), (1, 2)");

        $skills = Job::getSkillsForJob($pdo, 1);
        $this->assertSame([
            ['id' => 3, 'name' => 'Assembly'],
            ['id' => 2, 'name' => 'Cutting'],
            ['id' => 1, 'name' => 'Welding'],
        ], $skills);
    }
}
