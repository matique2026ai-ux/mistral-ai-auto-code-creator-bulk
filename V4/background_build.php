<?php
/**
 * AkrourCoder V4 — Background Build Runner
 * Utilise le JobQueue pour exécuter le pipeline de manière parallélisée.
 * Usage: php background_build.php <project_id>
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/queue.php';

$projectId = (int)($argv[1] ?? 0);
if (!$projectId) { fwrite(STDERR, "Usage: php background_build.php <project_id>\n"); exit(1); }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$projectId]); $project = $stmt->fetch();
if (!$project) { fwrite(STDERR, "Project #$projectId not found\n"); exit(1); }

// Create build directory
$buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($project['folder']);
if (!is_dir($buildDir)) mkdir($buildDir, 0755, true);

// Mark as building
updateProject($db, $projectId, ['status' => 'building']);

// Clear old logs and files
$db->prepare("DELETE FROM build_logs WHERE project_id = ?")->execute([$projectId]);
$db->prepare("DELETE FROM generated_files WHERE project_id = ?")->execute([$projectId]);
$db->prepare("DELETE FROM jobs WHERE project_id = ?")->execute([$projectId]);

// Enqueue all pipeline jobs
$queue = new JobQueue();
$jobs = $queue->enqueueProject($projectId);

fwrite(STDERR, "Project #$projectId: " . count($jobs) . " jobs enqueued\n");

// Run the worker (single run mode — processes all jobs then exits)
require_once __DIR__ . '/worker.php';
runWorkerLoop($projectId, $queue, 'bg_' . uniqid(), 2);

// Final status check (worker already sets status, just report)
$failed = $queue->getFailedJobs($projectId);
fwrite(STDERR, empty($failed) ? "Build #$projectId completed\n" : "Build #$projectId FAILED\n");
exit(empty($failed) ? 0 : 1);
