<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/User.php';

final class UserModelTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            last_login TEXT NULL
        )');
        return $pdo;
    }

    public function testCreateAssignsProvidedRole(): void
    {
        $pdo = $this->createPdo();
        $res = User::create($pdo, 'john', 'john@example.com', 'Passw0rd1', 'admin');
        $this->assertTrue($res['ok']);
        $row = $pdo->query('SELECT role FROM users WHERE id = ' . $res['id'])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('admin', $row['role']);
    }

    public function testCreateFailsWithDuplicateUsername(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO users (username, email, password, role) VALUES ('alice','a@example.com','x','user')");
        $res = User::create($pdo, 'alice', 'b@example.com', 'BetterPass1');
        $this->assertFalse($res['ok']);
        $this->assertSame('Username or email already exists', $res['error']);
    }

    public function testCreateFailsWithDuplicateEmail(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO users (username, email, password, role) VALUES ('bob','bob@example.com','x','user')");
        $res = User::create($pdo, 'charlie', 'bob@example.com', 'BetterPass1');
        $this->assertFalse($res['ok']);
        $this->assertSame('Username or email already exists', $res['error']);
    }
}
