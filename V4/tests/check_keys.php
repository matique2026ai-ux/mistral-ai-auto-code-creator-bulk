<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$db = getDB();
$keys = $db->query("SELECT id, label, provider, is_active, error_count FROM api_keys")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($keys, JSON_PRETTY_PRINT) . "\n";
