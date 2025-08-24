<?php declare(strict_types=1);

final class JobCompletion
{
    /**
     * Save signature path for a job.
     */
    public static function save(PDO $pdo, int $jobId, string $signaturePath): bool
    {
        $sql = 'INSERT INTO job_completion (job_id, signature_path) VALUES (:jid, :sp)';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql .= ' ON CONFLICT(job_id) DO UPDATE SET signature_path=excluded.signature_path';
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE signature_path = VALUES(signature_path)';
        }
        $st = $pdo->prepare($sql);
        if ($st === false) {
            return false;
        }
        $st->execute([':jid' => $jobId, ':sp' => $signaturePath]);
        return $st->rowCount() > 0;
    }
}
