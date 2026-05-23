<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$db = getDB();
$db->prepare('INSERT INTO api_keys (label, key_val) VALUES (?,?)')->execute(['Mistral', 'EFYY92k42Pl8W8sHf4x5kNfcadJttm9p']);
echo "Key added\n";
