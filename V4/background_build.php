<?php
/**
 * AkrourCoder V4 — Background Build Runner
 * Usage: php background_build.php <project_id>
 * Writes progress to DB; web UI polls for updates.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';

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

// Clear old logs
$db->prepare("DELETE FROM build_logs WHERE project_id = ?")->execute([$projectId]);
$db->prepare("DELETE FROM generated_files WHERE project_id = ?")->execute([$projectId]);

$brief = @json_decode($project['brief'], true) ?: ['who'=>'', 'target'=>'', 'monetize'=>''];
$brief['title'] = $project['title'];
$brief['project_id'] = $projectId;
$brief['folder'] = $project['folder'];
$brief['project_type'] = $project['project_type'] ?? '';
$brief['frontend'] = $project['frontend'] ?? '';
$brief['backend'] = $project['backend'] ?? '';
$brief['database'] = $project['database'] ?? '';
$brief['css_framework'] = $project['css_framework'] ?? '';

$engine = new PipelineEngine();
try {
    $result = $engine->run($brief);
    $status = $result['success'] ? 'done' : 'failed';
    updateProject($db, $projectId, ['status' => $status]);
    fwrite(STDERR, "Build #$projectId terminé: $status (QA: " . ($result['qa_score'] ?? 0) . "/100)\n");
} catch (\Throwable $e) {
    updateProject($db, $projectId, ['status' => 'failed']);
    appendLog($db, $projectId, 'engine', 'err', $e->getMessage());
    fwrite(STDERR, "Build #$projectId ERREUR: " . $e->getMessage() . "\n");
}
