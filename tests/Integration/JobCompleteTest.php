<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../TestHelpers/EndpointHarness.php';
require_once __DIR__ . '/../support/TestDataFactory.php';

final class JobCompleteTest extends TestCase
{
    private PDO $pdo;
    private int $jobId;
    private int $techId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->pdo = getPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('DELETE FROM job_completion');
        $this->pdo->exec('DELETE FROM job_photos');
        $this->pdo->exec('DELETE FROM job_notes');
        $this->pdo->exec('DELETE FROM jobs');
        $this->pdo->exec('DELETE FROM employees');
        $this->pdo->exec('DELETE FROM people');
        $this->pdo->exec('DELETE FROM customers');

        $customerId   = TestDataFactory::createCustomer($this->pdo);
        $this->techId = TestDataFactory::createEmployee($this->pdo);
        $this->jobId  = TestDataFactory::createJob($this->pdo, $customerId, 'Test job', '2025-01-01', '09:00:00', 60, 'in_progress');
    }

    private function sampleImage(): string
    {
        return 'data:image/png;base64,' .
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAJRfB9kAAAAASUVORK5CYII=';
    }

    public function testCompletionSavesArtifactsAndUpdatesStatus(): void
    {
        $img = $this->sampleImage();
        $res = EndpointHarness::run(__DIR__ . '/../../public/api/job_complete.php', [
            'job_id'        => $this->jobId,
            'technician_id'=> $this->techId,
            'location_lat' => '1',
            'location_lng' => '2',
            'final_note'   => 'All done',
            'final_photos' => [$img],
            'signature'    => $img,
        ], ['role' => 'technician']);

        $this->assertTrue($res['ok'] ?? false);
        $status = $this->pdo->query('SELECT status FROM jobs WHERE id=' . $this->jobId)->fetchColumn();
        $this->assertSame('completed', $status);
        $noteCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_notes WHERE job_id=' . $this->jobId . ' AND is_final=1')->fetchColumn();
        $this->assertSame(1, $noteCount);
        $photoCount = (int)$this->pdo->query('SELECT COUNT(*) FROM job_photos WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertSame(1, $photoCount);
        $sigPath = $this->pdo->query('SELECT signature_path FROM job_completion WHERE job_id=' . $this->jobId)->fetchColumn();
        $this->assertIsString($sigPath);
        $this->assertNotSame('', $sigPath);
    }

    public function testCompletionRequiresNoteAndPhoto(): void
    {
        $img = $this->sampleImage();

        $noNote = EndpointHarness::run(__DIR__ . '/../../public/api/job_complete.php', [
            'job_id'        => $this->jobId,
            'technician_id'=> $this->techId,
            'location_lat' => '1',
            'location_lng' => '2',
            'final_note'   => '',
            'final_photos' => [$img],
            'signature'    => $img,
        ], ['role' => 'technician']);
        $this->assertFalse($noNote['ok'] ?? true);
        $this->assertSame(422, $noNote['code'] ?? 0);

        $noPhoto = EndpointHarness::run(__DIR__ . '/../../public/api/job_complete.php', [
            'job_id'        => $this->jobId,
            'technician_id'=> $this->techId,
            'location_lat' => '1',
            'location_lng' => '2',
            'final_note'   => 'done',
            'final_photos' => [],
            'signature'    => $img,
        ], ['role' => 'technician']);
        $this->assertFalse($noPhoto['ok'] ?? true);
        $this->assertSame(422, $noPhoto['code'] ?? 0);
    }
}
