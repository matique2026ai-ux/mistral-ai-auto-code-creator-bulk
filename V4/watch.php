<?php
require_once __DIR__ . '/db.php';
$db = getDB();

$projects = $db->query("SELECT id, title, status, qa_score, file_count, created_at FROM projects ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "=== PROJETS ===\n";
foreach ($projects as $p) {
    echo "ID#{$p['id']}: {$p['title']} | Status: {$p['status']} | Files: {$p['file_count']} | QA: {$p['qa_score']}\n";
    $logs = $db->query("SELECT level, step, message, logged_at FROM build_logs WHERE project_id = {$p['id']} ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $l) {
        echo "  [{$l['logged_at']}] {$l['level']} {$l['step']}: {$l['message']}\n";
    }
}
