<?php
/**
 * AkrourCoder V4 — Base de Données
 */
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $db = new PDO('sqlite:' . AC4_DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');

    migrateDB($db);
    return $db;
}

function migrateDB(PDO $db): void {
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

    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        title         TEXT NOT NULL,
        folder        TEXT NOT NULL,
        project_type  TEXT DEFAULT 'fullstack',
        frontend      TEXT DEFAULT 'react',
        backend       TEXT DEFAULT 'node_express',
        database      TEXT DEFAULT 'sqlite',
        css_framework TEXT DEFAULT 'tailwind',
        output_lang   TEXT DEFAULT 'fr',
        status        TEXT NOT NULL DEFAULT 'building',
        qa_score      INTEGER DEFAULT 0,
        file_count    INTEGER DEFAULT 0,
        stack_choice  TEXT,
        arch_json     TEXT,
        design_json   TEXT,
        brief         TEXT,
        build_validated INTEGER DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS token_usage (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        api_key_id  INTEGER NOT NULL REFERENCES api_keys(id) ON DELETE CASCADE,
        project_id  INTEGER REFERENCES projects(id) ON DELETE SET NULL,
        tokens_used INTEGER NOT NULL DEFAULT 0,
        step_name   TEXT,
        model       TEXT DEFAULT '" . AC4_MODEL . "',
        used_at     DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS build_logs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        step       TEXT NOT NULL,
        level      TEXT NOT NULL DEFAULT 'info',
        message    TEXT NOT NULL,
        logged_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS generated_files (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        filepath   TEXT NOT NULL,
        language   TEXT,
        size       INTEGER DEFAULT 0,
        status     TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS agent_memories (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        agent      TEXT NOT NULL,
        key_point  TEXT NOT NULL,
        summary    TEXT NOT NULL,
        tags       TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS jobs (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id    INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        job_name      TEXT NOT NULL,
        status        TEXT NOT NULL DEFAULT 'pending',
        depends_on    TEXT DEFAULT '',
        worker_id     TEXT DEFAULT '',
        retry_count   INTEGER DEFAULT 0,
        max_retries   INTEGER DEFAULT 2,
        error_message TEXT DEFAULT '',
        started_at    DATETIME,
        finished_at   DATETIME,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration v4.1: add build_validated column
    try {
        $db->exec("ALTER TABLE projects ADD COLUMN build_validated INTEGER DEFAULT 0");
    } catch (\Exception $e) {
        // Column already exists
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────

function getNextApiKey(PDO $db): ?array {
    $key = $db->query(
        "SELECT * FROM api_keys WHERE is_active = 1 AND error_count < " . AC4_MAX_KEY_ERRORS . "
         ORDER BY last_used ASC NULLS FIRST LIMIT 1"
    )->fetch();
    if ($key) {
        $db->prepare("UPDATE api_keys SET last_used = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$key['id']]);
    }
    return $key ?: null;
}

function markKeyError(PDO $db, int $keyId): void {
    $db->prepare("UPDATE api_keys SET error_count = error_count + 1 WHERE id = ?")->execute([$keyId]);
    $db->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ? AND error_count >= ?")
       ->execute([$keyId, AC4_MAX_KEY_ERRORS]);
}

function recordTokens(PDO $db, int $keyId, int $tokens, ?int $projectId = null, string $step = ''): void {
    $db->prepare("INSERT INTO token_usage (api_key_id, project_id, tokens_used, step_name) VALUES (?,?,?,?)")
       ->execute([$keyId, $projectId, $tokens, $step]);
    $db->prepare("UPDATE api_keys SET total_tokens = total_tokens + ?, total_calls = total_calls + 1 WHERE id = ?")
       ->execute([$tokens, $keyId]);
}

function createProject(PDO $db, array $data): int {
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $data['slug'];
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $db->prepare(
        "INSERT INTO projects (title, folder, project_type, frontend, backend, database, css_framework, output_lang, brief)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([
        $data['title'], $data['folder'], $data['type'],
        $data['frontend'], $data['backend'], $data['database'],
        $data['css'], $data['lang'], $data['brief']
    ]);
    return (int)$db->lastInsertId();
}

function updateProject(PDO $db, int $id, array $fields): void {
    $fields['updated_at'] = date('Y-m-d H:i:s');
    $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
    $vals = array_values($fields);
    $vals[] = $id;
    $db->prepare("UPDATE projects SET $sets WHERE id = ?")->execute($vals);
}

function appendLog(PDO $db, int $projectId, string $step, string $level, string $message): void {
    $db->prepare("INSERT INTO build_logs (project_id, step, level, message) VALUES (?,?,?,?)")
       ->execute([$projectId, $step, $level, $message]);
}

function getGlobalStats(PDO $db): array {
    $keys = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active FROM api_keys")->fetch();
    $tokens = $db->query("SELECT SUM(tokens_used) as total FROM token_usage")->fetch();
    $projects = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) as done, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed, SUM(CASE WHEN status='building' THEN 1 ELSE 0 END) as building FROM projects")->fetch();
    $stacks = $db->query("SELECT project_type, COUNT(*) as cnt FROM projects GROUP BY project_type")->fetchAll();
    $validated = $db->query("SELECT COUNT(*) FROM projects WHERE build_validated = 1")->fetchColumn();
    $totalFiles = $db->query("SELECT COALESCE(SUM(file_count), 0) FROM projects")->fetchColumn();
    return [
        'keys_total'    => (int)($keys['total'] ?? 0),
        'keys_active'   => (int)($keys['active'] ?? 0),
        'tokens_total'  => (int)($tokens['total'] ?? 0),
        'projects_total'=> (int)($projects['total'] ?? 0),
        'projects_done' => (int)($projects['done'] ?? 0),
        'projects_failed' => (int)($projects['failed'] ?? 0),
        'projects_building' => (int)($projects['building'] ?? 0),
        'builds_validated' => (int)$validated,
        'total_files'   => (int)$totalFiles,
        'stacks'        => $stacks,
    ];
}

// ─── Mémoire des agents ────────────────────────────────────────────

function storeMemory(PDO $db, int $projectId, string $agent, string $keyPoint, string $summary, array $tags = []): void {
    $db->prepare("INSERT INTO agent_memories (project_id, agent, key_point, summary, tags) VALUES (?,?,?,?,?)")
       ->execute([$projectId, $agent, $keyPoint, $summary, implode(',', $tags)]);
}

function getMemories(PDO $db, string $agent, int $limit = 5): array {
    $stmt = $db->prepare(
        "SELECT m.*, p.title as project_title FROM agent_memories m
         JOIN projects p ON p.id = m.project_id
         WHERE m.agent = ? ORDER BY m.id DESC LIMIT ?"
    );
    $stmt->execute([$agent, $limit]);
    return $stmt->fetchAll();
}

function searchMemories(PDO $db, string $query, int $limit = 5): array {
    $stmt = $db->prepare(
        "SELECT m.*, p.title as project_title FROM agent_memories m
         JOIN projects p ON p.id = m.project_id
         WHERE m.key_point LIKE ? OR m.summary LIKE ? OR m.tags LIKE ?
         ORDER BY m.id DESC LIMIT ?"
    );
    $stmt->execute(["%$query%", "%$query%", "%$query%", $limit]);
    return $stmt->fetchAll();
}
