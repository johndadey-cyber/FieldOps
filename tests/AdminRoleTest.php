<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/TestHelpers/EndpointHarness.php';

final class AdminRoleTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('DELETE FROM roles');
    }

    public function testAdminRoleCrud(): void
    {
        // CREATE
        EndpointHarness::run(__DIR__ . '/../public/admin/role_save.php', [
            'name' => 'Manager',
        ], ['role' => 'admin']);

        $id = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'Manager'")->fetchColumn();
        $this->assertGreaterThan(0, $id, 'Role should be created');

        // UPDATE
        EndpointHarness::run(__DIR__ . '/../public/admin/role_save.php', [
            'id' => $id,
            'name' => 'Supervisor',
        ], ['role' => 'admin']);

        $name = (string)$this->pdo->query("SELECT name FROM roles WHERE id = {$id}")->fetchColumn();
        $this->assertSame('Supervisor', $name, 'Role name should update');

        // DELETE
        EndpointHarness::run(__DIR__ . '/../public/admin/role_save.php', [
            'id' => $id,
            'delete' => '1',
        ], ['role' => 'admin']);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM roles WHERE id = {$id}")->fetchColumn();
        $this->assertSame(0, $count, 'Role should delete');
    }

    public function testNonAdminRoleSaveRejected(): void
    {
        $res = EndpointHarness::run(__DIR__ . '/../public/admin/role_save.php', [
            'name' => 'Hacker',
        ], ['role' => 'dispatcher']);

        $this->assertFalse($res['ok'] ?? true, 'Non-admin should be rejected');
        $this->assertSame(403, $res['code'] ?? 0);

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM roles WHERE name = 'Hacker'")->fetchColumn();
        $this->assertSame(0, $count, 'No role should be created');
    }
}
