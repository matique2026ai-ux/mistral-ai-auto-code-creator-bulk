<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';

$projectId = (int)($argv[1] ?? 0);
if (!$projectId) { fwrite(STDERR, "Usage: php background_resume.php <project_id>\n"); exit(1); }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$projectId]); $project = $stmt->fetch();
if (!$project) { fwrite(STDERR, "Project #$projectId not found\n"); exit(1); }

updateProject($db, $projectId, ['status' => 'building']);

$engine = new PipelineEngine();
$result = $engine->resume($project);

if ($result['success'] ?? false) {
    fwrite(STDERR, "Resume #$projectId completed (score: {$result['qa_score']})\n");
    exit(0);
} else {
    fwrite(STDERR, "Resume #$projectId failed: " . ($result['error'] ?? 'unknown') . "\n");
    exit(1);
}
