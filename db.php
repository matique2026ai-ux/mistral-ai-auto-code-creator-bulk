<?php
define('DB_FILE', __DIR__ . '/autocoder.sqlite');

function getDB() {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pseudo TEXT,
        key_val TEXT UNIQUE,
        is_active INTEGER DEFAULT 1,
        last_used DATETIME,
        error_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS model_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_name TEXT UNIQUE,
        limit_tpm INTEGER DEFAULT 50000,
        limit_rps REAL DEFAULT 1.0,
        last_tested DATETIME,
        last_status TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        folder TEXT,
        status TEXT DEFAULT 'pending',
        brief TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS token_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        api_key_id INTEGER,
        tokens_used INTEGER,
        model TEXT,
        used_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed model
    $db->exec("INSERT OR IGNORE INTO model_limits (model_name, limit_tpm, limit_rps) VALUES ('devstral-2512', 50000, 1.0)");

    return $db;
}

// Rotate API key (round-robin with error awareness)
function getNextApiKey($db) {
    $keys = $db->query("SELECT * FROM api_keys WHERE is_active=1 AND error_count < 3 ORDER BY last_used ASC NULLS FIRST LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($keys) {
        $db->prepare("UPDATE api_keys SET last_used=CURRENT_TIMESTAMP WHERE id=?")->execute([$keys['id']]);
    }
    return $keys;
}

function markKeyError($db, $keyId) {
    $db->prepare("UPDATE api_keys SET error_count = error_count + 1 WHERE id=?")->execute([$keyId]);
    // Deactivate if too many errors
    $db->prepare("UPDATE api_keys SET is_active=0 WHERE id=? AND error_count >= 3")->execute([$keyId]);
}

function recordTokenUsage($db, $keyId, $tokens, $model) {
    $db->prepare("INSERT INTO token_usage (api_key_id, tokens_used, model) VALUES (?,?,?)")->execute([$keyId, $tokens, $model]);
}
