<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';

final class LoginValidationTest extends TestCase
{
    public function testMissingFieldsReturnsError(): void
    {
        $res = EndpointHarness::run(
            __DIR__ . '/../../public/api/login.php',
            [],
            [],
            'POST',
            ['json' => true, 'inject_csrf' => false]
        );
        $this->assertFalse($res['ok'] ?? true);
        $this->assertSame('Missing fields', $res['error'] ?? '');
        $this->assertSame(400, http_response_code());
        http_response_code(200);
    }
}
