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
function pInt(string $key, int $default = 0): int { return (int)p($key, (string)$default); }
function pSafe(string $key, string $default = ''): string { return strip_tags(trim(p($key, $default))); }
function respond(array $data): never { echo json_encode($data); exit; }
function err(string $msg): never { respond(['error' => $msg]); }
function ok(array $extra = []): never { respond(array_merge(['success' => true], $extra)); }

// ─── Validation ──────────────────────────────────────────────────────
function validateAllowedKeys(array $data, array $allowed): array {
    $clean = [];
    foreach ($allowed as $key => $type) {
        if (!isset($data[$key])) continue;
        $v = $data[$key];
        $clean[$key] = match ($type) {
            'int' => (int)$v,
            'string' => strip_tags(trim((string)$v)),
            'bool' => (bool)$v,
            'array' => is_array($v) ? $v : [],
            default => strip_tags(trim((string)$v)),
        };
    }
    return $clean;
}

function validateProvider(string $provider): string {
    $allowed = ['mistral', 'openai', 'anthropic', 'google'];
    return in_array($provider, $allowed) ? $provider : 'mistral';
}

function validateProjectType(string $type): string {
    $allowed = ['fullstack', 'mobile', 'api', 'static'];
    return in_array($type, $allowed) ? $type : 'fullstack';
}

function validateStackItem(string $item, string $category): string {
    $stacks = json_decode(AC4_STACKS, true);
    $allItems = [];
    foreach ($stacks as $s) {
        if (isset($s[$category])) $allItems = array_merge($allItems, $s[$category]);
    }
    $allItems = array_unique($allItems);
    return in_array($item, $allItems) ? $item : '';
}

// ═══════════════════════════════════════════════════════════════════════
//  ACTIONS
// ═══════════════════════════════════════════════════════════════════════

// ─── KEYS ─────────────────────────────────────────────────────────────

if ($action === 'add_key') {
    $label = trim(p('label'));
    $key   = trim(p('key'));
    $provider = trim(p('provider', 'mistral'));
    if (!$label || !$key) err('Label and key required');
    $stmt = $db->prepare("INSERT OR IGNORE INTO api_keys (label, key_val, provider) VALUES (?, ?, ?)");
    $stmt->execute([$label, $key, $provider]);
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
    $keys = $db->query("SELECT id, label, provider, substr(key_val,1,8)||'···'||substr(key_val,-4) AS key_masked, is_active, error_count, total_tokens, total_calls, last_used FROM api_keys ORDER BY id DESC")->fetchAll();
    $stats = getGlobalStats($db);
    $providers = json_decode(AC4_PROVIDERS, true);
    respond(['keys' => $keys, 'stats' => $stats, 'stacks' => json_decode(AC4_STACKS, true), 'providers' => $providers]);
}

// ─── PROJECTS ─────────────────────────────────────────────────────────

if ($action === 'create_project') {
    $masterPrompt = pSafe('master_prompt');
    $title    = pSafe('title') ?: ($masterPrompt ? substr($masterPrompt, 0, 50) : 'Project ' . date('YmdHis'));
    $who      = pSafe('who');
    $target   = pSafe('target');
    $monetize = pSafe('monetize');
    $type     = validateProjectType(p('type', 'fullstack'));
    $frontend = validateStackItem(p('frontend', ''), 'frontends');
    $backend  = validateStackItem(p('backend', ''), 'backends');
    $database = validateStackItem(p('database', ''), 'databases');
    $css      = validateStackItem(p('css', ''), 'css');
    $lang     = in_array(p('lang', 'fr'), ['fr','en','ar','es','de','pt','zh','ja']) ? p('lang', 'fr') : 'fr';

    // Validate master prompt length
    if (strlen($masterPrompt) > 10000) err('Master prompt trop long (max 10000 caractères)');

    $slug     = 'site_' . time() . '_' . substr(md5($masterPrompt ?: $title), 0, 8);
    $folder   = AC4_BUILDS_WEB . '/' . $slug;

    $brief = $masterPrompt
        ? json_encode(['master_prompt' => $masterPrompt, 'who' => $who, 'target' => $target, 'monetize' => $monetize])
        : json_encode(compact('who', 'target', 'monetize'));

    // Prevent directory traversal in slug
    if (preg_match('/[\/\\\\]/', $slug)) err('Invalid slug');

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
    $projects = $db->prepare("SELECT id, title, folder, project_type, frontend, backend, database, css_framework, status, qa_score, file_count, build_validated, created_at FROM projects ORDER BY id DESC LIMIT ?");
    $projects->execute([$limit]);
    respond(['projects' => $projects->fetchAll()]);
}

if ($action === 'get_project') {
    $id = (int)p('id'); if (!$id) err('ID missing');
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) err('Project not found');
    $files = $db->prepare("SELECT filepath, language, size, status FROM generated_files WHERE project_id = ? ORDER BY id ASC"); $files->execute([$id]);
    $logs  = $db->prepare("SELECT step, level, message, logged_at FROM build_logs WHERE project_id = ? ORDER BY id ASC LIMIT 200"); $logs->execute([$id]);
    respond(['project' => $row, 'files' => $files->fetchAll(), 'logs' => $logs->fetchAll()]);
}

if ($action === 'delete_project') {
    $id = (int)p('id'); if (!$id) err('ID missing');
    $stmt = $db->prepare("SELECT folder FROM projects WHERE id = ?"); $stmt->execute([$id]); $row = $stmt->fetch();
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
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $project = $stmt->fetch();
    if (!$project) err('Project not found');


    // Clear old logs
    $db->prepare("DELETE FROM build_logs WHERE project_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM generated_files WHERE project_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM jobs WHERE project_id = ?")->execute([$id]);
    updateProject($db, $id, ['status' => 'building']);

    // Enqueue all pipeline jobs via the queue system
    require_once __DIR__ . '/queue.php';
    $queue = new JobQueue();
    $queue->enqueueProject($id);

    // Spawn background worker process
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'background_build.php';
    $phpBin = PHP_BINARY;
    $cmd = "\"$phpBin\" \"$scriptPath\" $id";
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "start /B \"AC4Build$id\" $cmd";
        pclose(popen($cmd, 'r'));
    } else {
        exec("nohup $cmd > /dev/null 2>&1 &");
    }

    ok(['message' => 'Build démarré en arrière-plan (queue parallèle)', 'project_id' => $id]);
}

// ─── SSE (Server-Sent Events) — logs en temps réel ──────────────────

if ($action === 'sse_stream') {
    set_time_limit(0);
    $id = (int)p('project_id'); if (!$id) err('Project ID required');

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');

    $lastId = 0;
    $maxPolls = 180; // 6 minutes

    for ($i = 0; $i < $maxPolls; $i++) {
        if (connection_aborted()) break;

        $logs = $db->prepare(
            "SELECT id, step, level, message, job_name, logged_at FROM build_logs WHERE project_id = ? AND id > ? ORDER BY id ASC"
        );
        $logs->execute([$id, $lastId]);
        $rows = $logs->fetchAll();

        foreach ($rows as $row) {
            $lastId = (int)$row['id'];
            $data = json_encode([
                'id' => $row['id'],
                'step' => $row['step'],
                'level' => $row['level'],
                'message' => $row['message'],
                'job' => $row['job_name'],
                'time' => $row['logged_at'],
            ]);
            echo "id: {$row['id']}\nevent: log\ndata: $data\n\n";
        }

        // Check project status
        $pStmt = $db->prepare("SELECT status, qa_score FROM projects WHERE id = ?"); $pStmt->execute([$id]); $project = $pStmt->fetch();
        if ($project) {
            $statusData = json_encode([
                'status' => $project['status'],
                'qa_score' => (int)($project['qa_score'] ?? 0),
            ]);
            echo "event: status\ndata: $statusData\n\n";
        }

        if ($project && in_array($project['status'], ['done', 'failed'])) {
            echo "event: done\ndata: {\"status\":\"{$project['status']}\"}\n\n";
            break;
        }

        ob_flush(); flush();
        sleep(1);
    }

    exit;
}

// ─── FILES (ZIP download) ─────────────────────────────────────────────

if ($action === 'download_zip') {
    $id = (int)p('id'); if (!$id) err('Project ID required');
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $project = $stmt->fetch();
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
    $lStmt = $db->prepare("SELECT step, level, message, logged_at FROM build_logs WHERE project_id = ? ORDER BY id ASC"); $lStmt->execute([$id]); $logs = $lStmt->fetchAll();
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
