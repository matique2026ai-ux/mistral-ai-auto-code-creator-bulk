<?php
/**
 * AutoCoder V4 — API Endpoints
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache');

// ─── Parse request ─────────────────────────────────────────────────────
$ct   = $_SERVER['CONTENT_TYPE'] ?? '';
$body = [];
if (str_contains($ct, 'application/json')) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}
$action = $body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

// ─── Helpers ──────────────────────────────────────────────────────────
function p(string $key, $default = '') { global $body; return $body[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default; }
function respond(array $data): never { echo json_encode($data); exit; }
function err(string $msg): never { respond(['error' => $msg]); }
function ok(array $extra = []): never { respond(array_merge(['success' => true], $extra)); }

// ═══════════════════════════════════════════════════════════════════════
//  ACTIONS
// ═══════════════════════════════════════════════════════════════════════

// ─── KEYS ─────────────────────────────────────────────────────────────

if ($action === 'add_key') {
    $label = trim(p('label'));
    $key   = trim(p('key'));
    if (!$label || !$key) err('Label and key required');
    $stmt = $db->prepare("INSERT OR IGNORE INTO api_keys (label, key_val) VALUES (?, ?)");
    $stmt->execute([$label, $key]);
    if (!$db->lastInsertId()) err('Key already exists');
    ok(['id' => (int)$db->lastInsertId()]);
}

if ($action === 'delete_key') {
    $id = (int)p('id'); if (!$id) err('ID missing');
    $db->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$id]);
    ok();
}

if ($action === 'reset_key') {
    $id = (int)p('id'); if (!$id) err('ID missing');
    $db->prepare("UPDATE api_keys SET error_count = 0, is_active = 1 WHERE id = ?")->execute([$id]);
    ok();
}

if ($action === 'test_key') {
    $key = trim(p('key')); if (!$key) err('Key required');
    $ch = curl_init(AC4_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['model' => AC4_MODEL, 'messages' => [['role'=>'user','content'=>'OK']], 'max_tokens' => 5])
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    respond(['code' => $code, 'status' => $code === 200 ? 'OK' : 'Error ' . $code]);
}

if ($action === 'get_key') {
    $k = getNextApiKey($db);
    if (!$k) err('No active API key available');
    respond(['id' => $k['id'], 'key' => $k['key_val']]);
}

if ($action === 'key_error') {
    $id = (int)p('id'); if ($id) markKeyError($db, $id);
    ok();
}

// ─── DATA ─────────────────────────────────────────────────────────────

if ($action === 'get_data') {
    $keys = $db->query("SELECT id, label, substr(key_val,1,8)||'···'||substr(key_val,-4) AS key_masked, is_active, error_count, total_tokens, total_calls, last_used FROM api_keys ORDER BY id DESC")->fetchAll();
    $stats = getGlobalStats($db);
    respond(['keys' => $keys, 'stats' => $stats, 'stacks' => json_decode(AC4_STACKS, true)]);
}

// ─── PROJECTS ─────────────────────────────────────────────────────────

if ($action === 'create_project') {
    $masterPrompt = trim(p('master_prompt'));
    $title    = trim(p('title')) ?: ($masterPrompt ? substr($masterPrompt, 0, 50) : 'Project ' . date('YmdHis'));
    $who      = trim(p('who'));
    $target   = trim(p('target'));
    $monetize = trim(p('monetize'));
    $type     = p('type', 'fullstack');
    $frontend = p('frontend', '');
    $backend  = p('backend', '');
    $database = p('database', '');
    $css      = p('css', '');
    $lang     = p('lang', 'fr');
    $slug     = 'site_' . time() . '_' . substr(md5($masterPrompt ?: $title), 0, 8);
    $folder   = AC4_BUILDS_WEB . '/' . $slug;

    $brief = $masterPrompt
        ? json_encode(['master_prompt' => $masterPrompt, 'who' => $who, 'target' => $target, 'monetize' => $monetize])
        : json_encode(compact('who', 'target', 'monetize'));

    $id = createProject($db, [
        'title' => $title, 'folder' => $folder, 'type' => $type,
        'frontend' => $frontend, 'backend' => $backend,
        'database' => $database, 'css' => $css, 'lang' => $lang,
        'brief' => $brief,
        'slug' => $slug,
    ]);
    respond(['id' => $id, 'folder' => $folder, 'slug' => $slug]);
}

if ($action === 'update_project') {
    $id = (int)p('id');
    $fields = [];
    foreach (['status','qa_score','file_count','arch_json','stack_choice'] as $f) {
        $v = p($f, null);
        if ($v !== null && $v !== '') $fields[$f] = $v;
    }
    if ($id && $fields) updateProject($db, $id, $fields);
    ok();
}

if ($action === 'list_projects') {
    $limit = min((int)(p('limit') ?: 30), 100);
    $projects = $db->query("SELECT id, title, folder, project_type, frontend, backend, database, css_framework, status, qa_score, file_count, build_validated, created_at FROM projects ORDER BY id DESC LIMIT $limit")->fetchAll();
    respond(['projects' => $projects]);
}

if ($action === 'get_project') {
    $id = (int)p('id'); if (!$id) err('ID missing');
    $row = $db->query("SELECT * FROM projects WHERE id = $id")->fetch();
    if (!$row) err('Project not found');
    $files = $db->query("SELECT filepath, language, size, status FROM generated_files WHERE project_id = $id ORDER BY id ASC")->fetchAll();
    $logs  = $db->query("SELECT step, level, message, logged_at FROM build_logs WHERE project_id = $id ORDER BY id ASC LIMIT 200")->fetchAll();
    respond(['project' => $row, 'files' => $files, 'logs' => $logs]);
}

if ($action === 'delete_project') {
    $id = (int)p('id'); if (!$id) err('ID missing');
    $row = $db->query("SELECT folder FROM projects WHERE id = $id")->fetch();
    if ($row) {
        $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($row['folder']);
        if (is_dir($dir)) _rmdir_recursive($dir);
    }
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    ok();
}

// ─── BUILD ────────────────────────────────────────────────────────────

if ($action === 'run_build') {
    set_time_limit(0);
    $id = (int)p('project_id'); if (!$id) err('Project ID required');
    $project = $db->query("SELECT * FROM projects WHERE id = $id")->fetch();
    if (!$project) err('Project not found');

    // Clear old logs
    $db->prepare("DELETE FROM build_logs WHERE project_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM generated_files WHERE project_id = ?")->execute([$id]);

    // Configure SSE streaming
    header('Content-Type: text/event-stream');
    header('X-Accel-Buffering: no');
    ob_implicit_flush(true);
    ob_end_flush();

    $brief = @json_decode($project['brief'], true) ?: ['who'=>'', 'target'=>'', 'monetize'=>''];
    $brief['title'] = $project['title'];
    $brief['project_id'] = $id;
    $brief['folder'] = $project['folder'];
    $brief['project_type'] = $project['project_type'] ?? '';
    $brief['frontend'] = $project['frontend'] ?? '';
    $brief['backend'] = $project['backend'] ?? '';
    $brief['database'] = $project['database'] ?? '';
    $brief['css_framework'] = $project['css_framework'] ?? '';

    $engine = new PipelineEngine();
    $result = $engine->run($brief);

    echo json_encode(['type' => 'done', 'result' => $result]) . "\n";
    exit;
}

// ─── FILES (ZIP download) ─────────────────────────────────────────────

if ($action === 'download_zip') {
    $id = (int)p('id'); if (!$id) err('Project ID required');
    $project = $db->query("SELECT * FROM projects WHERE id = $id")->fetch();
    if (!$project) err('Project not found');

    $folderName = basename($project['folder']);
    $targetDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $folderName;
    if (!is_dir($targetDir)) err('Project folder not found');

    $zipFile = tempnam(sys_get_temp_dir(), 'ac4_zip_');
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) err('Failed to create ZIP');

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $zip->addFile($file->getRealPath(), substr($file->getRealPath(), strlen($targetDir) + 1));
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . htmlspecialchars($project['title']) . '.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    unlink($zipFile);
    exit;
}

// ─── STATS ────────────────────────────────────────────────────────────

if ($action === 'get_stats') {
    $stats = getGlobalStats($db);
    respond(['stats' => $stats]);
}

// ─── GET_LOGS (for polling) ──────────────────────────────────────────

if ($action === 'get_logs') {
    $id = (int)p('project_id'); if (!$id) err('Project ID required');
    $logs = $db->query("SELECT step, level, message, logged_at FROM build_logs WHERE project_id = $id ORDER BY id ASC")->fetchAll();
    respond(['logs' => $logs]);
}

// ─── Unknown ──────────────────────────────────────────────────────────

err('Unknown action: ' . htmlspecialchars($action));

// ─── Helpers ──────────────────────────────────────────────────────────

function _rmdir_recursive(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
    }
    rmdir($dir);
}
