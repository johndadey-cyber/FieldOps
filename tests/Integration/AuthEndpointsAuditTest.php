<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/TestPdo.php';
require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../../models/User.php';

final class AuthEndpointsAuditTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM users');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testAuditLogsForAuthEndpoints(): void
    {
        $username = 'auuser';
        $email    = 'au@example.com';
        $pass     = 'Secret123';
        $res = User::create($this->pdo, $username, $email, $pass);
        $uid = (int)($res['id'] ?? 0);

        // Successful login
        EndpointHarness::run(__DIR__ . '/../../public/api/login.php', [
            'username' => $username,
            'password' => $pass,
        ], [], 'POST', ['json' => true, 'inject_csrf' => false]);

        // Failed login
        EndpointHarness::run(__DIR__ . '/../../public/api/login.php', [
            'username' => $username,
            'password' => 'badpass',
        ], [], 'POST', ['json' => true, 'inject_csrf' => false]);

        // Logout
        EndpointHarness::run(__DIR__ . '/../../public/api/logout.php', [], [
            'user_id' => $uid,
            'user' => ['id' => $uid],
        ], 'POST', ['inject_csrf' => false]);

        // Password reset
        EndpointHarness::run(__DIR__ . '/../../public/api/change_password.php', [
            'password' => 'NewPass123',
        ], [
            'user_id' => $uid,
        ], 'POST', ['json' => true, 'inject_csrf' => false]);

        $rows = $this->pdo->query('SELECT action FROM audit_log ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('login_success', $rows);
        $this->assertContains('login_failure', $rows);
        $this->assertContains('logout', $rows);
        $this->assertContains('password_reset', $rows);
    }
}
