<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/TestHelpers/EndpointHarness.php';

final class AdminJobTypeSkillTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Clean relevant tables before each test
        $this->pdo->exec('DELETE FROM jobtype_skills');
        $this->pdo->exec('DELETE FROM job_types');
        $this->pdo->exec('DELETE FROM skills');
    }

    public function testAdminJobTypeCrud(): void
    {
        // CREATE
        EndpointHarness::run(__DIR__ . '/../public/admin/job_type_save.php', [
            'action' => 'create',
            'name'   => 'Install',
        ], ['role' => 'admin']);

        $id = (int)$this->pdo->query("SELECT id FROM job_types WHERE name = 'Install'")->fetchColumn();
        $this->assertGreaterThan(0, $id, 'Job type should be created');

        // UPDATE
        EndpointHarness::run(__DIR__ . '/../public/admin/job_type_save.php', [
            'action' => 'update',
            'id'     => $id,
            'name'   => 'Repair',
        ], ['role' => 'admin']);

        $name = (string)$this->pdo->query("SELECT name FROM job_types WHERE id = {$id}")->fetchColumn();
        $this->assertSame('Repair', $name, 'Job type name should update');

        // DELETE
        EndpointHarness::run(__DIR__ . '/../public/admin/job_type_save.php', [
            'action' => 'delete',
            'id'     => $id,
        ], ['role' => 'admin']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM job_types WHERE id = {$id}")->fetchColumn();
        $this->assertSame(0, $count, 'Job type should delete');
    }

    public function testNonAdminJobTypeSaveRejected(): void
    {
        $res = EndpointHarness::run(__DIR__ . '/../public/admin/job_type_save.php', [
            'action' => 'create',
            'name'   => 'Hack',
        ], ['role' => 'dispatcher']);

        $this->assertFalse($res['ok'] ?? true, 'Non-admin should be rejected');
        $this->assertSame(403, $res['code'] ?? 0);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM job_types WHERE name = 'Hack'")->fetchColumn();
        $this->assertSame(0, $count, 'No job type should be created');
    }

    public function testAdminSkillCrud(): void
    {
        // CREATE
        EndpointHarness::run(__DIR__ . '/../public/admin/skill_save.php', [
            'name' => 'Welding',
        ], ['role' => 'admin']);

        $id = (int)$this->pdo->query("SELECT id FROM skills WHERE name = 'Welding'")->fetchColumn();
        $this->assertGreaterThan(0, $id, 'Skill should be created');

        // UPDATE
        EndpointHarness::run(__DIR__ . '/../public/admin/skill_save.php', [
            'id'   => $id,
            'name' => 'Advanced Welding',
        ], ['role' => 'admin']);

        $name = (string)$this->pdo->query("SELECT name FROM skills WHERE id = {$id}")->fetchColumn();
        $this->assertSame('Advanced Welding', $name, 'Skill name should update');

        // DELETE
        EndpointHarness::run(__DIR__ . '/../public/admin/skill_save.php', [
            'id'     => $id,
            'delete' => '1',
        ], ['role' => 'admin']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM skills WHERE id = {$id}")->fetchColumn();
        $this->assertSame(0, $count, 'Skill should delete');
    }

    public function testNonAdminSkillSaveRejected(): void
    {
        $res = EndpointHarness::run(__DIR__ . '/../public/admin/skill_save.php', [
            'name' => 'HackSkill',
        ], ['role' => 'dispatcher']);

        $this->assertFalse($res['ok'] ?? true, 'Non-admin should be rejected');
        $this->assertSame(403, $res['code'] ?? 0);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM skills WHERE name = 'HackSkill'")->fetchColumn();
        $this->assertSame(0, $count, 'No skill should be created');
    }

    public function testAdminJobTypeSkillMapping(): void
    {
        // Seed job type and skill
        $this->pdo->exec("INSERT INTO job_types (name) VALUES ('MapType')");
        $jobTypeId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO skills (name) VALUES ('MapSkill')");
        $skillId = (int)$this->pdo->lastInsertId();

        // Attach
        EndpointHarness::run(__DIR__ . '/../public/admin/job_type_skill_save.php', [
            'job_type_id' => $jobTypeId,
            'skills'      => [$skillId],
        ], ['role' => 'admin']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM jobtype_skills WHERE job_type_id = {$jobTypeId} AND skill_id = {$skillId}")->fetchColumn();
        $this->assertSame(1, $count, 'Mapping should attach skill');

        // Detach by submitting no skills
        EndpointHarness::run(__DIR__ . '/../public/admin/job_type_skill_save.php', [
            'job_type_id' => $jobTypeId,
            'skills'      => [],
        ], ['role' => 'admin']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM jobtype_skills WHERE job_type_id = {$jobTypeId} AND skill_id = {$skillId}")->fetchColumn();
        $this->assertSame(0, $count, 'Mapping should remove skill');
    }

    public function testNonAdminJobTypeSkillSaveRejected(): void
    {
        // Seed job type and skill
        $this->pdo->exec("INSERT INTO job_types (name) VALUES ('TypeX')");
        $jobTypeId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO skills (name) VALUES ('SkillX')");
        $skillId = (int)$this->pdo->lastInsertId();

        $res = EndpointHarness::run(__DIR__ . '/../public/admin/job_type_skill_save.php', [
            'job_type_id' => $jobTypeId,
            'skills'      => [$skillId],
        ], ['role' => 'dispatcher']);

        $this->assertFalse($res['ok'] ?? true, 'Non-admin should be rejected');
        $this->assertSame(403, $res['code'] ?? 0);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM jobtype_skills WHERE job_type_id = {$jobTypeId}")->fetchColumn();
        $this->assertSame(0, $count, 'Mapping should not be created');
    }
}
