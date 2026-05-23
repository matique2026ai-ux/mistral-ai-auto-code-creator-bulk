<?php
/**
 * AkrourCoder V4 — Job Runner (child process)
 * Exécute un job individuel du pipeline.
 * Usage: php job_runner.php <project_id> <job_id> <job_name>
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/queue.php';

$projectId = (int)($argv[1] ?? 0);
$jobId = (int)($argv[2] ?? 0);
$jobName = $argv[3] ?? '';

if (!$projectId || !$jobId || !$jobName) {
    fwrite(STDERR, "Usage: php job_runner.php <project_id> <job_id> <job_name>\n");
    exit(1);
}

$db = getDB();
$queue = new JobQueue();

// Verify this job is still claimed by us
$job = $db->prepare("SELECT * FROM jobs WHERE id = ? AND project_id = ? AND status = 'running'");
$job->execute([$jobId, $projectId]);
$j = $job->fetch();

if (!$j) {
    fwrite(STDERR, "Job #$jobId not found or not running\n");
    exit(1);
}

$engine = new PipelineEngine();

try {
    $engine->runJob($projectId, $jobName);
    $queue->completeJob($jobId);
    fwrite(STDERR, "Job $jobName (#$jobId) done\n");
    exit(0);
} catch (\Throwable $e) {
    $queue->failJob($jobId, $e->getMessage());
    fwrite(STDERR, "Job $jobName (#$jobId) failed: " . $e->getMessage() . "\n");
    exit(1);
}
