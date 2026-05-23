<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$db = getDB();
$jobs = $db->query("SELECT id, job_name, status, retry_count, error_message, worker_id, started_at, finished_at FROM jobs WHERE project_id=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "Jobs (" . count($jobs) . ") :\n";
foreach ($jobs as $j) {
    echo "  #{$j['id']} {$j['job_name']}: {$j['status']}" . ($j['error_message'] ? " ERROR: {$j['error_message']}" : "") . "\n";
}
