<?php
/**
 * AkrourCoder V4 — Worker Process
 * Consomme les jobs de la queue SQLite et les exécute en parallèle.
 * Usage: php worker.php <project_id> [--daemon]
 *
 * Stratégie de parallélisme :
 * - Jobs sans dépendances mutuelles = exécutés simultanément
 * - Backend + Frontend tournent en parallèle (dépendent tous deux de Designer)
 * - Jusqu'à 2 workers simultanés par projet
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/queue.php';

// Only run if executed directly (not when included)
if (basename($argv[0] ?? '') === 'worker.php') {
    $isDaemon = in_array('--daemon', $argv ?? []);
    $projectId = isset($argv[1]) && ctype_digit($argv[1]) ? (int)$argv[1] : 0;

    if ($isDaemon) {
        runDaemon($projectId);
    } elseif ($projectId) {
        $queue = new JobQueue();
        runWorkerLoop($projectId, $queue, 'worker_once', 2);
    } else {
        fwrite(STDERR, "Usage: php worker.php [project_id] [--daemon]\n"); exit(1);
    }
}

function runDaemon(int $projectId = 0): void {
    $workerId = 'daemon_' . uniqid();
    fwrite(STDERR, "[daemon:$workerId] Starting" . ($projectId ? " for project #$projectId" : " (auto-scan)") . "\n");

    if (PHP_OS_FAMILY !== 'Windows') {
        pcntl_signal(SIGINT, fn() => die("\n[daemon] Shutdown by SIGINT\n"));
        pcntl_signal(SIGTERM, fn() => die("\n[daemon] Shutdown by SIGTERM\n"));
    }

    $db = getDB();
    $queue = new JobQueue();
    $loopCount = 0;

    while (true) {
        if (PHP_OS_FAMILY !== 'Windows') pcntl_signal_dispatch();

        $targetId = $projectId ?: getNextPendingProject($db);

        if ($targetId) {
            fwrite(STDERR, "[daemon] Processing project #$targetId...\n");
            try {
                runWorkerLoop($targetId, $queue, $workerId, 2);
                fwrite(STDERR, "[daemon] Project #$targetId done\n");
            } catch (\Throwable $e) {
                fwrite(STDERR, "[daemon] Project #$targetId failed: {$e->getMessage()}\n");
            }
            if ($projectId) break; // Single-project daemon exits after completion
        } else {
            if (++$loopCount % 30 === 0) {
                fwrite(STDERR, "[daemon] Waiting for projects... ({$loopCount}s)\n");
            }
        }
        sleep(1);
    }
}

function getNextPendingProject(PDO $db): ?int {
    $stmt = $db->query(
        "SELECT id FROM projects WHERE status = 'building'
         ORDER BY created_at ASC LIMIT 1"
    );
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function runWorkerLoop(int $projectId, JobQueue $queue, string $workerId, int $maxParallel): bool {
    $engine = new PipelineEngine();

    // Dispatch ready jobs in parallel
    while (true) {
        $readyJobs = $queue->getReadyJobs($projectId, $maxParallel);
        if (empty($readyJobs)) break;

        $children = [];
        foreach ($readyJobs as $job) {
            if (!$queue->claimJob((int)$job['id'], $workerId)) continue;

            $jobName = $job['job_name'];
            fwrite(STDERR, "[worker] Dispatching: $jobName (job #{$job['id']})\n");

            // For parallel execution, fork a child process
            $pid = launchWorkerChild($projectId, (int)$job['id'], $jobName);
            if ($pid !== null) {
                $children[(int)$job['id']] = ['pid' => $pid, 'name' => $jobName];
            } else {
                // Fallback: run synchronously
                executeJobSync($engine, $projectId, $job, $queue);
            }
        }

        // Wait for all children to finish
        foreach ($children as $jobId => $child) {
            waitForChild($child['pid']);
            fwrite(STDERR, "[worker] Child done: {$child['name']} (job #$jobId)\n");
        }
    }

    // Check overall project status
    $status = $queue->isProjectFinished($projectId);

    $db = getDB();
    if ($status === 'done') {
        updateProject($db, $projectId, ['status' => 'done']);
        fwrite(STDERR, "[worker] Project #$projectId completed successfully\n");
        return true;
    } elseif ($status === 'failed') {
        $failed = $queue->getFailedJobs($projectId);
        $errors = array_map(fn($j) => "{$j['job_name']}: {$j['error_message']}", $failed);
        updateProject($db, $projectId, ['status' => 'failed']);
        appendLog($db, $projectId, 'worker', 'err', 'Jobs failed: ' . implode('; ', $errors));
        fwrite(STDERR, "[worker] Project #$projectId failed: " . implode('; ', $errors) . "\n");
        return true;
    }

    return false; // Still pending jobs
}

function launchWorkerChild(int $projectId, int $jobId, string $jobName): ?int {
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'job_runner.php';
    $phpBin = PHP_BINARY;
    $cmd = "\"$phpBin\" \"$scriptPath\" $projectId $jobId $jobName";

    if (PHP_OS_FAMILY === 'Windows') {
        // Run synchronously: return null so caller's else-branch executes the job
        return null;
    } else {
        exec("nohup $cmd > /dev/null 2>&1 & echo $!", $out);
        return (int)($out[0] ?? 0);
    }
}

function waitForChild(int $pid): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        // On Unix, wait for the specific child
        pcntl_waitpid($pid, $status);
    }
    // On Windows, child process updates DB state independently via completeJob/failJob.
    // No OS-level wait needed — the job queue handles ordering.
}

function executeJobSync(PipelineEngine $engine, int $projectId, array $job, JobQueue $queue): void {
    $jobName = $job['job_name'];
    $jobId = (int)$job['id'];
    try {
        // Load context from DB
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$projectId]); $project = $stmt->fetch();

        // Reconstruct stack and architecture from DB
        $stackDecision = [
            'analysis' => ['project_type' => $project['project_type'] ?? 'fullstack'],
            'stack_decision' => [
                'frontend' => $project['frontend'] ?? 'next',
                'backend' => $project['backend'] ?? 'node_express',
                'database' => $project['database'] ?? 'sqlite',
                'css_framework' => $project['css_framework'] ?? 'tailwind',
            ],
        ];
        if (!empty($project['stack_choice'])) {
            $saved = @json_decode($project['stack_choice'], true);
            if ($saved) $stackDecision = $saved;
        }

        $engine->runJob($projectId, $jobName);
        $queue->completeJob($jobId);
        fwrite(STDERR, "[worker] $jobName done\n");
    } catch (\Throwable $e) {
        $queue->failJob($jobId, $e->getMessage());
        fwrite(STDERR, "[worker] $jobName failed: " . $e->getMessage() . "\n");
    }
}
