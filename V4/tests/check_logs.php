<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$db = getDB();
$logs = $db->query("SELECT level, message, logged_at FROM build_logs WHERE project_id=1 ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
foreach (array_reverse($logs) as $r) {
    echo "[" . $r['logged_at'] . "] " . strtoupper($r['level']) . ": " . $r['message'] . "\n";
}
$proj = $db->query("SELECT id, title, status, qa_score, file_count, created_at, updated_at FROM projects WHERE id=1")->fetch(PDO::FETCH_ASSOC);
if ($proj) {
    echo "\n--- Projet ---\n";
    echo "Status: " . $proj['status'] . "\n";
    echo "Score QA: " . ($proj['qa_score'] ?? 'N/A') . "\n";
    echo "Fichiers: " . ($proj['file_count'] ?? 0) . "\n";
}
