<?php
/**
 * AutoCoder V3 — API Endpoints
 * All AJAX/JSON actions are handled here.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// ── Parse action ─────────────────────────────────────────────────────────
$ct      = $_SERVER['CONTENT_TYPE'] ?? '';
$body    = [];
if (str_contains($ct, 'application/json')) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}
$action  = $body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
$db      = getDB();

// ── Helper ────────────────────────────────────────────────────────────────
function p(string $key, $default = '') {
    global $body;
    return $body[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
}
function respond(array $data): never { echo json_encode($data); exit; }
function err(string $msg):    never  { respond(['error' => $msg]); }
function ok(array $extra=[]):  never  { respond(array_merge(['success' => true], $extra)); }

// ── KEYS ─────────────────────────────────────────────────────────────────

if ($action === 'add_key') {
    $label = trim(p('label'));
    $key   = trim(p('key'));
    if (!$label || !$key) err('Label and key are required');

    $stmt = $db->prepare("INSERT OR IGNORE INTO api_keys (label, key_val) VALUES (?, ?)");
    $stmt->execute([$label, $key]);
    if (!$db->lastInsertId()) err('Key already exists');
    ok(['id' => (int)$db->lastInsertId()]);
}

if ($action === 'delete_key') {
    $id = (int)p('id');
    if (!$id) err('ID missing');
    $db->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$id]);
    ok();
}

if ($action === 'reset_key') {
    $id = (int)p('id');
    if (!$id) err('ID missing');
    $db->prepare("UPDATE api_keys SET error_count = 0, is_active = 1 WHERE id = ?")->execute([$id]);
    ok();
}

if ($action === 'toggle_key') {
    $id = (int)p('id');
    if (!$id) err('ID missing');
    $db->prepare("UPDATE api_keys SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
    ok();
}

if ($action === 'test_key') {
    $key = trim(p('key'));
    if (!$key) err('Key required');

    $ch = curl_init(AC_MISTRAL_API);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'      => AC_MODEL,
            'messages'   => [['role' => 'user', 'content' => 'Reply with just: OK']],
            'max_tokens' => 5
        ])
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $res    = json_decode($resp, true);
    $tokens = $res['usage']['total_tokens'] ?? 0;
    $status = ($code === 200) ? 'OK' : 'Error ' . $code;

    $db->prepare("INSERT OR REPLACE INTO model_limits
                  (model_name, limit_tpm, limit_rps, last_tested, last_status)
                  VALUES (?, ?, 1.0, CURRENT_TIMESTAMP, ?)")
       ->execute([AC_MODEL, AC_TOKEN_LIMIT, $status]);

    respond(['code' => $code, 'status' => $status, 'tokens' => $tokens]);
}

if ($action === 'get_key') {
    $k = getNextApiKey($db);
    if (!$k) err('No active API key available');
    respond(['id' => $k['id'], 'key' => $k['key_val']]);
}

if ($action === 'key_error') {
    $id = (int)p('id');
    if ($id) markKeyError($db, $id);
    ok();
}

if ($action === 'record_usage') {
    $keyId  = (int)p('key_id');
    $tokens = (int)p('tokens');
    $projId = (int)p('project_id') ?: null;
    $step   = trim(p('step'));
    if ($keyId && $tokens) recordTokenUsage($db, $keyId, $tokens, $projId, $step);
    ok();
}

// ── DATA ─────────────────────────────────────────────────────────────────

if ($action === 'get_data') {
    $keys = $db->query(
        "SELECT id, label,
                substr(key_val,1,8)||'···'||substr(key_val,-4) AS key_masked,
                is_active, error_count, total_tokens, total_calls, last_used
         FROM api_keys ORDER BY id DESC"
    )->fetchAll();

    $model  = $db->query("SELECT * FROM model_limits WHERE model_name='" . AC_MODEL . "'")->fetch();
    $stats  = getGlobalStats($db);
    $chart  = getTokenChartData($db, 7);

    respond([
        'keys'   => $keys,
        'model'  => $model,
        'stats'  => $stats,
        'chart'  => $chart,
    ]);
}

// ── PROJECTS ─────────────────────────────────────────────────────────────

if ($action === 'create_project') {
    $title  = trim(p('title')) ?: ('Project ' . date('YmdHis'));
    $brief  = p('brief');
    $type   = p('site_type', 'landing');
    $lang   = p('output_lang', 'en');
    $css    = p('css_framework', 'vanilla');
    $slug   = 'site_' . time() . '_' . substr(md5($title), 0, 6);
    $folder = AC_BUILDS_WEB . '/' . $slug;

    $id = createProject($db, $title, $folder, $brief, $type, $lang, $css);
    respond(['id' => $id, 'folder' => $folder, 'slug' => $slug]);
}

if ($action === 'update_project') {
    $id     = (int)p('id');
    $fields = [];
    foreach (['status','qa_score','file_count','arch_json'] as $f) {
        $v = p($f, null);
        if ($v !== null && $v !== '') $fields[$f] = $v;
    }
    if ($id && $fields) updateProject($db, $id, $fields);
    ok();
}

if ($action === 'list_projects') {
    $limit    = min((int)(p('limit') ?: 30), 100);
    $projects = $db->query(
        "SELECT id, title, folder, site_type, output_lang, css_framework,
                status, qa_score, file_count, created_at
         FROM projects ORDER BY id DESC LIMIT $limit"
    )->fetchAll();
    respond(['projects' => $projects]);
}

if ($action === 'get_project') {
    $id  = (int)p('id');
    if (!$id) err('ID missing');
    $row = $db->query("SELECT * FROM projects WHERE id = $id")->fetch();
    if (!$row) err('Project not found');
    respond(['project' => $row]);
}

if ($action === 'delete_project') {
    $id = (int)p('id');
    if (!$id) err('ID missing');

    // Delete build folder
    $row = $db->query("SELECT folder FROM projects WHERE id = $id")->fetch();
    if ($row) {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['folder']);
        if (is_dir($dir)) _rmdir_recursive($dir);
    }
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    ok();
}

// ── FILES ─────────────────────────────────────────────────────────────────

if ($action === 'save_file') {
    $path    = $body['path']    ?? $_POST['path']    ?? '';
    $content = $body['content'] ?? $_POST['content'] ?? '';

    if (!$path) err('Path is required');

    // Security: only allow writes under builds/
    $normPath = str_replace('\\', '/', $path);
    if (!str_starts_with($normPath, AC_BUILDS_WEB . '/')) {
        err('Write blocked — path must start with ' . AC_BUILDS_WEB . '/');
    }
    // Block path traversal
    if (preg_match('/(\.\.|%2e|%00|\x00|:)/', $path)) {
        err('Invalid path characters');
    }

    $target    = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normPath);
    $targetDir = dirname($target);
    $realBase  = realpath(__DIR__ . '/' . AC_BUILDS_WEB);

    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) err('Cannot create directory: ' . $targetDir);
    }

    $realDir = realpath($targetDir);
    if ($realBase && $realDir && !str_starts_with($realDir, $realBase)) {
        err('Path traversal attempt blocked');
    }

    $bytes = file_put_contents($target, $content);
    if ($bytes === false) err('Write failed: ' . $target);

    ok(['path' => $path, 'bytes' => $bytes]);
}

if ($action === 'read_file') {
    $path = $body['path'] ?? $_POST['path'] ?? '';
    if (!$path) err('Path required');

    $normPath = str_replace('\\', '/', $path);
    if (!str_starts_with($normPath, AC_BUILDS_WEB . '/')) err('Access denied');
    if (preg_match('/(\.\.|%00)/', $path)) err('Invalid path');

    $target = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normPath);
    if (!file_exists($target)) err('File not found');

    respond(['content' => file_get_contents($target), 'bytes' => filesize($target)]);
}

if ($action === 'list_files') {
    $folder = p('folder');
    if (!$folder) err('Folder required');
    $normFolder = str_replace('\\', '/', $folder);
    if (!str_starts_with($normFolder, AC_BUILDS_WEB . '/')) err('Access denied');

    $dir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normFolder);
    if (!is_dir($dir)) respond(['files' => []]);

    $files = [];
    foreach (new DirectoryIterator($dir) as $f) {
        if ($f->isDot() || $f->isDir()) continue;
        $files[] = [
            'name'  => $f->getFilename(),
            'size'  => $f->getSize(),
            'mtime' => date('Y-m-d H:i:s', $f->getMTime()),
            'ext'   => $f->getExtension(),
        ];
    }
    respond(['files' => $files]);
}

// ── LOGS ──────────────────────────────────────────────────────────────────

if ($action === 'append_log') {
    $projId  = (int)p('project_id');
    $level   = p('level', 'info');
    $message = p('message');
    if ($projId && $message) appendBuildLog($db, $projId, $level, $message);
    ok();
}

if ($action === 'get_logs') {
    $projId = (int)p('project_id');
    if (!$projId) err('project_id required');
    $logs = getBuildLogs($db, $projId);
    respond(['logs' => $logs]);
}

// ── STATS ─────────────────────────────────────────────────────────────────

if ($action === 'get_stats') {
    $stats = getGlobalStats($db);
    $chart = getTokenChartData($db, (int)(p('days') ?: 7));
    respond(['stats' => $stats, 'chart' => $chart]);
}

// ── Unknown action ────────────────────────────────────────────────────────
err('Unknown action: ' . htmlspecialchars($action));

// ── Helpers ───────────────────────────────────────────────────────────────
function _rmdir_recursive(string $dir): void {
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
    }
    rmdir($dir);
}
