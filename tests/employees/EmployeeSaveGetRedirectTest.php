<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/Http.php';

#[Group('employees')]
final class EmployeeSaveGetRedirectTest extends TestCase
{
    public function testGetRedirectsToForm(): void
    {
        $url = Http::baseUrl() . '/employee_save.php';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
        ]);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(302, $status, "GET should redirect to form, got {$status}.");
        $this->assertMatchesRegularExpression('/Location: .*employee_form\\.php/i', (string)$resp);
    }
}
