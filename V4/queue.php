<?php
/**
 * AkrourCoder V4 — Job Queue (SQLite-based)
 * Gère l'enqueue, le dispatch, et le cycle de vie des jobs du pipeline.
 * Supporte le parallélisme : les jobs sans dépendances mutuelles tournent en même temps.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class JobQueue {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Crée tous les jobs d'un projet dans la queue.
     * Définit les dépendances : CTO→Architect→Designer→(Backend||Frontend)→QA→DevOps
     */
    public function enqueueProject(int $projectId): array {
        $this->db->prepare("DELETE FROM jobs WHERE project_id = ?")->execute([$projectId]);

        $jobs = [];
        $insert = $this->db->prepare(
            "INSERT INTO jobs (project_id, job_name, status, depends_on) VALUES (?,?,?,?)"
        );

        // Job 1: CTO
        $insert->execute([$projectId, 'cto', 'pending', '']);
        $ctoId = (int)$this->db->lastInsertId();
        $jobs['cto'] = $ctoId;

        // Job 2: Architect (dépend de CTO)
        $insert->execute([$projectId, 'architect', 'pending', (string)$ctoId]);
        $archId = (int)$this->db->lastInsertId();
        $jobs['architect'] = $archId;

        // Job 3: Designer (dépend de Architect)
        $insert->execute([$projectId, 'designer', 'pending', (string)$archId]);
        $designId = (int)$this->db->lastInsertId();
        $jobs['designer'] = $designId;

        // Job 4: Backend (dépend de Designer) — parallélisable avec Frontend
        $insert->execute([$projectId, 'backend', 'pending', (string)$designId]);
        $jobs['backend'] = (int)$this->db->lastInsertId();

        // Job 5: Frontend (dépend de Designer) — parallélisable avec Backend
        $insert->execute([$projectId, 'frontend', 'pending', (string)$designId]);
        $jobs['frontend'] = (int)$this->db->lastInsertId();

        // Job 6: QA (dépend de Backend ET Frontend)
        $insert->execute([$projectId, 'qa', 'pending', $jobs['backend'] . ',' . $jobs['frontend']]);
        $jobs['qa'] = (int)$this->db->lastInsertId();

        // Job 7: DevOps (dépend de QA)
        $insert->execute([$projectId, 'devops', 'pending', (string)$jobs['qa']]);
        $jobs['devops'] = (int)$this->db->lastInsertId();

        return $jobs;
    }

    /**
     * Récupère tous les jobs prêts à être exécutés :
     * - status = 'pending'
     * - toutes leurs dépendances sont 'done'
     * Limite à $max à la fois (pour contrôler le parallélisme)
     */
    public function getReadyJobs(int $projectId, int $max = 2): array {
        $allJobs = $this->db->prepare(
            "SELECT * FROM jobs WHERE project_id = ? ORDER BY id ASC"
        );
        $allJobs->execute([$projectId]);
        $jobs = $allJobs->fetchAll();

        $ready = [];
        foreach ($jobs as $job) {
            if ($job['status'] !== 'pending') continue;
            if (!$this->dependenciesMet($job, $jobs)) continue;
            $ready[] = $job;
            if (count($ready) >= $max) break;
        }
        return $ready;
    }

    public function getPendingCount(int $projectId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM jobs WHERE project_id = ? AND status = 'pending'"
        );
        $stmt->execute([$projectId]);
        return (int)$stmt->fetchColumn();
    }

    private function dependenciesMet(array $job, array $allJobs): bool {
        if (empty($job['depends_on'])) return true;
        $depIds = array_map('trim', explode(',', $job['depends_on']));
        foreach ($allJobs as $j) {
            if (in_array((string)$j['id'], $depIds) && $j['status'] !== 'done') {
                return false;
            }
        }
        return true;
    }

    public function claimJob(int $jobId, string $workerId): bool {
        $stmt = $this->db->prepare(
            "UPDATE jobs SET status = 'running', worker_id = ?, started_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$workerId, $jobId]);
        return $stmt->rowCount() > 0;
    }

    public function completeJob(int $jobId): void {
        $this->db->prepare(
            "UPDATE jobs SET status = 'done', finished_at = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$jobId]);
    }

    public function failJob(int $jobId, string $error): void {
        $job = $this->db->prepare("SELECT * FROM jobs WHERE id = ?");
        $job->execute([$jobId]);
        $j = $job->fetch();

        if (!$j) return;

        if ($j['retry_count'] < $j['max_retries']) {
            $this->db->prepare(
                "UPDATE jobs SET status = 'pending', retry_count = retry_count + 1, worker_id = NULL WHERE id = ?"
            )->execute([$jobId]);
        } else {
            $this->db->prepare(
                "UPDATE jobs SET status = 'failed', error_message = ?, finished_at = CURRENT_TIMESTAMP WHERE id = ?"
            )->execute([$error, $jobId]);
        }
    }

    public function getFailedJobs(int $projectId): array {
        $stmt = $this->db->prepare("SELECT * FROM jobs WHERE project_id = ? AND status = 'failed'");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function allDone(int $projectId): bool {
        return $this->getPendingCount($projectId) === 0
            && empty($this->getRunningJobs($projectId));
    }

    public function getRunningJobs(int $projectId): array {
        $stmt = $this->db->prepare("SELECT * FROM jobs WHERE project_id = ? AND status = 'running'");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function isProjectFinished(int $projectId): ?string {
        $failed = $this->getFailedJobs($projectId);
        if (!empty($failed)) return 'failed';

        if ($this->allDone($projectId)) return 'done';

        return null;
    }

    public function cancelProject(int $projectId): void {
        $this->db->prepare(
            "UPDATE jobs SET status = 'cancelled' WHERE project_id = ? AND status = 'pending'"
        )->execute([$projectId]);
    }
}
