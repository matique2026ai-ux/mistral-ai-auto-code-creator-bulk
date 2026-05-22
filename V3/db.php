<?php
/**
 * AutoCoder V3 — Database Layer
 * Handles SQLite initialization, migrations, and helper functions.
 */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $db = new PDO('sqlite:' . AC_DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode=WAL;');
    $db->exec('PRAGMA foreign_keys=ON;');

    _migrateDB($db);
    return $db;
}

function _migrateDB(PDO $db): void {
    // ── api_keys ──────────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        label        TEXT NOT NULL,
        key_val      TEXT NOT NULL UNIQUE,
        is_active    INTEGER NOT NULL DEFAULT 1,
        error_count  INTEGER NOT NULL DEFAULT 0,
        total_tokens INTEGER NOT NULL DEFAULT 0,
        total_calls  INTEGER NOT NULL DEFAULT 0,
        last_used    DATETIME,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── model_limits ──────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS model_limits (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        model_name   TEXT NOT NULL UNIQUE,
        limit_tpm    INTEGER DEFAULT 50000,
        limit_rps    REAL    DEFAULT 1.0,
        last_tested  DATETIME,
        last_status  TEXT
    )");
    $db->exec("INSERT OR IGNORE INTO model_limits (model_name, limit_tpm, limit_rps)
               VALUES ('" . AC_MODEL . "', " . AC_TOKEN_LIMIT . ", 1.0)");

    // ── projects ──────────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        title        TEXT NOT NULL,
        folder       TEXT NOT NULL,
        site_type    TEXT DEFAULT 'landing',
        output_lang  TEXT DEFAULT 'en',
        css_framework TEXT DEFAULT 'vanilla',
        status       TEXT NOT NULL DEFAULT 'building',
        qa_score     INTEGER DEFAULT 0,
        file_count   INTEGER DEFAULT 0,
        brief        TEXT,
        arch_json    TEXT,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── token_usage ───────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS token_usage (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        api_key_id   INTEGER NOT NULL REFERENCES api_keys(id) ON DELETE CASCADE,
        project_id   INTEGER REFERENCES projects(id) ON DELETE SET NULL,
        tokens_used  INTEGER NOT NULL DEFAULT 0,
        step_name    TEXT,
        model        TEXT DEFAULT '" . AC_MODEL . "',
        used_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── build_logs ────────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS build_logs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        level      TEXT NOT NULL DEFAULT 'info',  -- info|ok|err|warn|ai|write|test
        message    TEXT NOT NULL,
        logged_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

// ─── API Key helpers ─────────────────────────────────────────────────────

/**
 * Get the best available API key (round-robin with error awareness).
 */
function getNextApiKey(PDO $db): ?array {
    $key = $db->query(
        "SELECT * FROM api_keys
         WHERE is_active = 1 AND error_count < " . AC_MAX_KEY_ERRORS . "
         ORDER BY last_used ASC NULLS FIRST
         LIMIT 1"
    )->fetch();

    if ($key) {
        $db->prepare("UPDATE api_keys SET last_used = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$key['id']]);
    }
    return $key ?: null;
}

/**
 * Mark an error on a key; auto-disable after AC_MAX_KEY_ERRORS errors.
 */
function markKeyError(PDO $db, int $keyId): void {
    $db->prepare("UPDATE api_keys SET error_count = error_count + 1 WHERE id = ?")
       ->execute([$keyId]);
    $db->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ? AND error_count >= ?")
       ->execute([$keyId, AC_MAX_KEY_ERRORS]);
}

/**
 * Record token usage for a key and project.
 */
function recordTokenUsage(PDO $db, int $keyId, int $tokens, ?int $projectId = null, string $step = ''): void {
    $db->prepare("INSERT INTO token_usage (api_key_id, project_id, tokens_used, step_name)
                  VALUES (?, ?, ?, ?)")
       ->execute([$keyId, $projectId, $tokens, $step]);

    $db->prepare("UPDATE api_keys SET
                    total_tokens = total_tokens + ?,
                    total_calls  = total_calls  + 1
                  WHERE id = ?")
       ->execute([$tokens, $keyId]);
}

// ─── Project helpers ──────────────────────────────────────────────────────

function createProject(PDO $db, string $title, string $folder, string $brief,
                       string $siteType = 'landing', string $lang = 'en',
                       string $css = 'vanilla'): int {
    // Pre-create builds folder
    $dir = AC_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($folder);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $db->prepare(
        "INSERT INTO projects (title, folder, site_type, output_lang, css_framework, brief)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$title, $folder, $siteType, $lang, $css, $brief]);

    return (int)$db->lastInsertId();
}

function updateProject(PDO $db, int $id, array $fields): void {
    $fields['updated_at'] = date('Y-m-d H:i:s');
    $sets  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
    $vals  = array_values($fields);
    $vals[] = $id;
    $db->prepare("UPDATE projects SET $sets WHERE id = ?")->execute($vals);
}

function getProject(PDO $db, int $id): ?array {
    return $db->prepare("SELECT * FROM projects WHERE id = ?")
              ->execute([$id]) ? $db->query("SELECT * FROM projects WHERE id = $id")->fetch() : null;
}

// ─── Build log helpers ────────────────────────────────────────────────────

function appendBuildLog(PDO $db, int $projectId, string $level, string $message): void {
    $db->prepare("INSERT INTO build_logs (project_id, level, message) VALUES (?, ?, ?)")
       ->execute([$projectId, $level, $message]);
}

function getBuildLogs(PDO $db, int $projectId, int $limit = 500): array {
    return $db->prepare("SELECT * FROM build_logs WHERE project_id = ? ORDER BY id DESC LIMIT ?")
              ->execute([$projectId, $limit])
        ? $db->query("SELECT * FROM build_logs WHERE project_id = $projectId ORDER BY id ASC LIMIT $limit")->fetchAll()
        : [];
}

// ─── Stats helpers ────────────────────────────────────────────────────────

function getGlobalStats(PDO $db): array {
    $keys = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active FROM api_keys")->fetch();
    $tokens = $db->query("SELECT SUM(tokens_used) as total FROM token_usage")->fetch();
    $projects = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) as done FROM projects")->fetch();
    return [
        'keys_total'    => (int)($keys['total'] ?? 0),
        'keys_active'   => (int)($keys['active'] ?? 0),
        'tokens_total'  => (int)($tokens['total'] ?? 0),
        'projects_total'=> (int)($projects['total'] ?? 0),
        'projects_done' => (int)($projects['done'] ?? 0),
    ];
}

function getTokenChartData(PDO $db, int $days = 7): array {
    $rows = $db->query(
        "SELECT date(used_at) as day, SUM(tokens_used) as total
         FROM token_usage
         WHERE used_at >= date('now', '-{$days} days')
         GROUP BY day ORDER BY day ASC"
    )->fetchAll();
    return $rows;
}
