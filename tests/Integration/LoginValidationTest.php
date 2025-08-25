<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../../helpers/csrf_helpers.php';

final class LoginValidationTest extends TestCase
{
    public function testMissingFieldsReturnsError(): void
    {
        $res = EndpointHarness::run(
            __DIR__ . '/../../public/api/login.php',
            [],
            [],
            'POST',
            ['json' => true, 'inject_csrf' => true]
        );
        $this->assertFalse($res['ok'] ?? true);
        $this->assertSame('Missing fields', $res['error'] ?? '');
        $this->assertSame(400, http_response_code());
        http_response_code(200);
    }

    public function testInvalidCsrfTokenReturns422(): void
    {
        $GLOBALS['__csrfLogFile'] = __DIR__ . '/../../logs/csrf_failures.log';
        $GLOBALS['__csrfLogMaxBytes'] = 1024 * 1024;
        $res = EndpointHarness::run(
            __DIR__ . '/../../public/api/login.php',
            ['csrf_token' => 'bad'],
            [],
            'POST',
            ['json' => true, 'inject_csrf' => false]
        );
        $this->assertFalse($res['ok'] ?? true);
        $this->assertSame('Invalid CSRF token', $res['error'] ?? '');
        $this->assertSame(422, http_response_code());
        http_response_code(200);
    }
}
