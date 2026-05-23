<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$db = getDB();
$logs = $db->query("SELECT id, step, level, message, logged_at FROM build_logs WHERE project_id=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "Logs (" . count($logs) . ") :\n";
foreach ($logs as $l) {
    echo "  [{$l['logged_at']}] {$l['step']} {$l['level']}: {$l['message']}\n";
}
$proj = $db->query("SELECT id, title, status, qa_score, file_count, created_at, updated_at FROM projects WHERE id=1")->fetch(PDO::FETCH_ASSOC);
echo "\nProjet: " . json_encode($proj, JSON_PRETTY_PRINT) . "\n";
