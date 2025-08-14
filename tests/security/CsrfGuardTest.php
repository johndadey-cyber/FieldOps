<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';

#[Group('security')]
final class CsrfGuardTest extends TestCase
{
    public function testTestCsrfEndpointBehavior(): void
    {
        // If server is running with APP_ENV=test, endpoint should return a token (200 + JSON).
        // Otherwise, we can't force the server env from PHPUnit, so skip gracefully.
        $appEnv = getenv('APP_ENV') ?: '';
        $raw = Http::get('test_csrf.php');
        if ($appEnv === 'test') {
            $data = json_decode($raw, true);
            $this->assertIsArray($data, 'Expected JSON from test_csrf.php');
            $this->assertArrayHasKey('token', $data);
            $this->assertNotSame('', (string)$data['token']);
        } else {
            $this->markTestSkipped('Server not running with APP_ENV=test; run server with APP_ENV=test to validate token response, or with APP_ENV=prod to manually confirm 404.');
        }
    }
}
