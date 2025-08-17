<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';
require_once __DIR__ . '/../support/TestPdo.php';

#[Group('employees')]
final class EmployeesSaveControllerTest extends TestCase
{
    private PDO $pdo;
    private string $baseUrl;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Base URL for controller calls (server must be running with -t public)
        $this->baseUrl = rtrim(getenv('FIELDOPS_BASE_URL') ?: 'http://127.0.0.1:8010', '/');

        // Test DB connection
        $this->pdo = createTestPdo();

        // Clean relevant tables
        $this->pdo->exec('DELETE FROM job_employee_assignment');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');

        // Get CSRF token from test endpoint (server must run with APP_ENV=test)
        $raw = Http::get('test_csrf.php'); // relative, helper prefixes base URL
        $data = json_decode($raw, true);
        $this->token = (string)($data['token'] ?? '');
        $this->assertNotSame('', $this->token, 'CSRF token not returned — ensure server started with APP_ENV=test.');
    }

    /** Lightweight form POST returning ['status'=>int, 'body'=>string] */
    private function postForm(string $relativePath, array $fields): array
    {
        $url = $this->baseUrl . '/' . ltrim($relativePath, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_HEADER         => true,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException("cURL error: {$err}");
        }
        $body = substr($resp, $headerSize) ?: '';
        return ['status' => (int)$status, 'body' => (string)$body];
    }

    private function countRows(string $table): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");
        return (int)$stmt->fetchColumn();
    }

    public function testRejectsMissingCsrf(): void
    {
        $peopleBefore    = $this->countRows('people');
        $employeesBefore = $this->countRows('employees');

        $resp = $this->postForm('employee_save.php', [
            'first_name'        => 'NoCsrf',
            'last_name'         => 'User',
            'email'             => 'nocrsf@example.test',
            'phone'             => '(512) 555-0000',
            'address_line1'     => '123 Main',
            'city'              => 'Austin',
            'state'             => 'TX',
            'postal_code'       => '78704',
            'home_address_lat'  => '30.0000',
            'home_address_lon'  => '-97.0000',
            'employment_type'   => 'Full-Time',
            'hire_date'         => date('Y-m-d'),
            'status'            => 'Active',
            // intentionally omit csrf_token
        ]);

        // Expect non-200 OR body mentioning csrf; allow flexible server behavior
        $this->assertTrue(
            $resp['status'] >= 400 || stripos($resp['body'], 'csrf') !== false,
            "Expected CSRF rejection; got HTTP {$resp['status']} body: " . substr($resp['body'], 0, 200)
        );

        $this->assertSame($peopleBefore,    $this->countRows('people'),    'No person should be created on CSRF failure.');
        $this->assertSame($employeesBefore, $this->countRows('employees'), 'No employee should be created on CSRF failure.');
    }

    public function testRejectsMissingRequiredFields(): void
    {
        $peopleBefore    = $this->countRows('people');
        $employeesBefore = $this->countRows('employees');

        $resp = $this->postForm('employee_save.php', [
            // Missing first_name (required)
            'last_name'        => 'OnlyLast',
            'email'            => 'only.last@example.test',
            'phone'            => '(512) 555-1111',
            'address_line1'    => '456 Elm',
            'city'             => 'Austin',
            'state'            => 'TX',
            'postal_code'      => '78704',
            'home_address_lat' => '30.0000',
            'home_address_lon' => '-97.0000',
            'employment_type'  => 'Full-Time',
            'hire_date'        => date('Y-m-d'),
            'status'           => 'Active',
            'csrf_token'       => $this->token,
        ]);

        // Expect validation feedback or non-200; avoid brittle copy checks
        $this->assertTrue(
            $resp['status'] >= 400 || stripos($resp['body'], 'required') !== false || stripos($resp['body'], 'error') !== false,
            "Expected validation failure; got HTTP {$resp['status']} body: " . substr($resp['body'], 0, 200)
        );

        $this->assertSame($peopleBefore,    $this->countRows('people'),    'No person should be created when required fields are missing.');
        $this->assertSame($employeesBefore, $this->countRows('employees'), 'No employee should be created when required fields are missing.');
    }

    public function testAcceptsValidPayloadAndPersists(): void
    {
        $peopleBefore    = $this->countRows('people');
        $employeesBefore = $this->countRows('employees');

        $resp = $this->postForm('employee_save.php', [
            'first_name'       => 'Ella',
            'last_name'        => 'Engineer',
            'email'            => 'ella.engineer@example.test',
            'phone'            => '(512) 555-1234',
            'address_line1'    => '789 Pine',
            'city'             => 'Austin',
            'state'            => 'TX',
            'postal_code'      => '78704',
            'home_address_lat' => '30.0000',
            'home_address_lon' => '-97.0000',
            'employment_type'  => 'Full-Time',
            'hire_date'        => date('Y-m-d'),
            'status'           => 'Active',
            'csrf_token'       => $this->token,
        ]);

        // Accept 200 or redirect (3xx) on success; controller often redirects after save
        $this->assertTrue(
            in_array($resp['status'], [200, 302, 303], true),
            "Expected success (200/302/303), got HTTP {$resp['status']}."
        );

        $this->assertSame($peopleBefore + 1,    $this->countRows('people'),    'Person should be created.');
        $this->assertSame($employeesBefore + 1, $this->countRows('employees'), 'Employee should be created.');

        // Optional: verify person row exists
        $stmt = $this->pdo->prepare('SELECT id FROM people WHERE first_name = ? AND last_name = ?');
        $stmt->execute(['Ella', 'Engineer']);
        $personId = (int)($stmt->fetchColumn() ?: 0);
        $this->assertGreaterThan(0, $personId, 'Created person should be retrievable.');
    }
}
