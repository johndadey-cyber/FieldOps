<?php declare(strict_types=1);

final class JobCompletion
{
    /**
     * Save signature path for a job.
     */
    public static function save(PDO $pdo, int $jobId, string $signaturePath): bool
    {
        $st = $pdo->prepare(
            'INSERT INTO job_completion (job_id, signature_path) VALUES (:jid, :sp)
             ON DUPLICATE KEY UPDATE signature_path = VALUES(signature_path)'
        );
        if ($st === false) {
            return false;
        }
        $st->execute([':jid' => $jobId, ':sp' => $signaturePath]);
        return $st->rowCount() > 0;
    }
}
