<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminDashboardAuthTest extends TestCase
{
    public function testNonAdminIsForbidden(): void
    {
        $target = __DIR__ . '/../../public/admin/index.php';
        $tmp = tempnam(sys_get_temp_dir(), 'fo');
        $php = <<<'PHP'
<?php
session_id('t-' . bin2hex(random_bytes(3)));
session_start();
$_SESSION['role'] = 'dispatcher';
define('FIELDOPS_ALLOW_ENDPOINT_EXECUTION', true);
$GLOBALS['__FIELDOPS_TEST_CALL__'] = true;
require '%s';
PHP;
        $code = sprintf($php, addslashes($target));
        file_put_contents($tmp, $code);
        $out = shell_exec('php ' . escapeshellarg($tmp));
        unlink($tmp);
        $res = json_decode((string)$out, true);
        $this->assertFalse($res['ok'] ?? true);
        $this->assertSame(403, $res['code'] ?? 0);
    }

    public function testAdminCanAccess(): void
    {
        $target = __DIR__ . '/../../public/admin/index.php';
        $tmp = tempnam(sys_get_temp_dir(), 'fo');
        $php = <<<'PHP'
<?php
session_id('t-' . bin2hex(random_bytes(3)));
session_start();
$_SESSION['role'] = 'admin';
define('FIELDOPS_ALLOW_ENDPOINT_EXECUTION', true);
$GLOBALS['__FIELDOPS_TEST_CALL__'] = true;
require '%s';
PHP;
        $code = sprintf($php, addslashes($target));
        file_put_contents($tmp, $code);
        $out = shell_exec('php ' . escapeshellarg($tmp));
        unlink($tmp);
        $this->assertStringContainsString('Admin Tools', (string)$out);
    }
}
