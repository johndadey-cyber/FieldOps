<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/auth_helpers.php';
require_once __DIR__ . '/../support/TestPdo.php';

final class AuthenticateHelperTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        seedSqliteSchema($pdo);
        return $pdo;
    }

    public function testAuthenticateSuccess(): void
    {
        $pdo = $this->createPdo();
        $hash = password_hash('Passw0rd1', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (username, email, password, role) VALUES ('alice','alice@example.com','$hash','dispatcher')");

        $res = authenticate($pdo, 'alice', 'Passw0rd1');
        $this->assertTrue($res['ok']);
        $this->assertIsArray($res['user']);
        $this->assertNull($res['error']);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'login_success'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testAuthenticateFailure(): void
    {
        $pdo = $this->createPdo();
        $hash = password_hash('Passw0rd1', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (username, email, password, role) VALUES ('bob','bob@example.com','$hash','dispatcher')");

        $res = authenticate($pdo, 'bob', 'wrong');
        $this->assertFalse($res['ok']);
        $this->assertNull($res['user']);
        $this->assertSame('Invalid credentials', $res['error']);
        $count = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'login_failure'")->fetchColumn();
        $this->assertSame(1, $count);
    }
}
