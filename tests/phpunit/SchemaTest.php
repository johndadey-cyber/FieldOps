<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testSchemaCheckerPasses(): void
    {
        $cmd = PHP_BINARY . ' ' . __DIR__ . '/../../bin/schema_check.php --json';
        exec($cmd, $out, $code);
        $this->assertSame(0, $code, "schema_check.php failed:\n" . implode("\n", $out));
        $json = json_decode(implode("\n", $out), true);
        $this->assertIsArray($json);
        $this->assertSame('OK', $json['status'] ?? 'ISSUES', 'Schema status not OK');
        $this->assertTrue(empty($json['issues'] ?? []), 'Issues: ' . json_encode($json['issues'] ?? []));
    }
}
