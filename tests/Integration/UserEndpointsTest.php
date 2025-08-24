<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class UserEndpointsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            last_login DATETIME NULL
        )');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INT NULL,
            action TEXT NOT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM audit_log');
    }

    public function testAdminEndpointsRequireAdminRole(): void
    {
        $userForm = file_get_contents(__DIR__ . '/../../public/admin/user_form.php');
        $apiCreate = file_get_contents(__DIR__ . '/../../public/api/users/create.php');
        $this->assertIsString($userForm);
        $this->assertIsString($apiCreate);
        $this->assertStringContainsString('require_role(\'admin\')', $userForm);
        $this->assertStringContainsString('require_role(\'admin\')', $apiCreate);
    }

    public function testDuplicateUsernamesOrEmailsRejected(): void
    {
        $first = EndpointHarness::run(
            __DIR__ . '/../../public/api/users/create.php',
            [
                'username' => 'bob',
                'email' => 'bob@example.com',
                'password' => 'Passw0rd1',
                'role' => 'dispatcher',
            ],
            ['role' => 'admin']
        );
        $this->assertTrue($first['ok'] ?? false);

        $dup = EndpointHarness::run(
            __DIR__ . '/../../public/api/users/create.php',
            [
                'username' => 'bob',
                'email' => 'bob@example.com',
                'password' => 'Passw0rd1',
                'role' => 'dispatcher',
            ],
            ['role' => 'admin']
        );
        $this->assertFalse($dup['ok'] ?? true);
        $this->assertSame(422, $dup['code'] ?? 0);
    }

    public function testNewUserLoginAndAuditLog(): void
    {
        $create = EndpointHarness::run(
            __DIR__ . '/../../public/api/users/create.php',
            [
                'username' => 'charlie',
                'email' => 'charlie@example.com',
                'password' => 'Passw0rd1',
                'role' => 'dispatcher',
            ],
            ['role' => 'admin']
        );
        $this->assertTrue($create['ok'] ?? false);
        $uid = (int)($create['id'] ?? 0);
        $this->assertGreaterThan(0, $uid);

        $login = EndpointHarness::run(
            __DIR__ . '/../../public/api/login.php',
            [
                'username' => 'charlie',
                'password' => 'Passw0rd1',
            ],
            [],
            'POST',
            ['json' => true, 'inject_csrf' => false]
        );
        $this->assertTrue($login['ok'] ?? false);
        $this->assertSame('dispatcher', $login['role'] ?? '');

        $count = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM audit_log WHERE user_id = {$uid} AND action = 'login_success'"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }
}
