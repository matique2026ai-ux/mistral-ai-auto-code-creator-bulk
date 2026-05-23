<?php
require __DIR__ . '/V4/db.php';
$db = getDB();
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n";
$count = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
echo "Projects count: $count\n";
if ($count > 0) {
    $rows = $db->query("SELECT id, title, status FROM projects ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} else {
    echo "No projects found\n";
}
