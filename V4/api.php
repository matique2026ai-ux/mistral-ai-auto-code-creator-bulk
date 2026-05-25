<?php
/**
 * AkrourCoder V4 — API Endpoints
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/helpers.php';

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
    $slug     = slugify($masterPrompt ?: $title) . '_' . substr(md5($masterPrompt ?: $title), 0, 4);
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
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) err('Project not found');
    $stmt = $db->prepare("SELECT filepath, language, size, status FROM generated_files WHERE project_id = ? ORDER BY id ASC"); $stmt->execute([$id]); $files = $stmt->fetchAll();
    $stmt = $db->prepare("SELECT step, level, message, logged_at FROM build_logs WHERE project_id = ? ORDER BY id ASC LIMIT 200"); $stmt->execute([$id]); $logs = $stmt->fetchAll();
    respond(['project' => $row, 'files' => $files, 'logs' => $logs]);
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
    updateProject($db, $id, ['status' => 'building']);

    // Spawn background build process
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'background_build.php';
    $phpBin = PHP_BINARY;
    $cmd = "\"$phpBin\" \"$scriptPath\" $id";
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "start /B \"AC4Build$id\" $cmd";
        pclose(popen($cmd, 'r'));
    } else {
        exec("nohup $cmd > /dev/null 2>&1 &");
    }

    ok(['message' => 'Build démarré en arrière-plan', 'project_id' => $id]);
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
    $stmt = $db->prepare("SELECT step, level, message, logged_at FROM build_logs WHERE project_id = ? ORDER BY id ASC"); $stmt->execute([$id]);
    respond(['logs' => $stmt->fetchAll()]);
}

// ─── CLEANUP — supprime vieux builds et dossiers orphelins ─────────

if ($action === 'cleanup') {
    $deleted = 0;
    $freed = 0;

    // 1. Vieux builds (>30 jours)
    $old = $db->query("SELECT id, folder FROM projects WHERE created_at < date('now','-30 days') AND status = 'done'")->fetchAll();
    foreach ($old as $p) {
        $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($p['folder']);
        if (is_dir($dir)) {
            $size = 0;
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $f) if ($f->isFile()) $size += $f->getSize();
            _rmdir_recursive($dir);
            $freed += $size;
        }
        $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$p['id']]);
        $deleted++;
    }

    // 2. Dossiers orphelins (builds/sans projet correspondant)
    $folders = $db->query("SELECT folder FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    $validFolders = array_map(fn($f) => basename($f), $folders);
    $buildsDir = AC4_BUILDS_DIR;
    if (is_dir($buildsDir)) {
        foreach (new FilesystemIterator($buildsDir, FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir() && !in_array($entry->getBasename(), $validFolders, true)) {
                $size = 0;
                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entry->getPathname(), FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $f) if ($f->isFile()) $size += $f->getSize();
                _rmdir_recursive($entry->getPathname());
                $freed += $size;
                $deleted++;
            }
        }
    }

    respond(['deleted' => $deleted, 'freed_bytes' => $freed, 'freed_kb' => round($freed / 1024, 1)]);
}

// ─── Unknown ──────────────────────────────────────────────────────────

err('Unknown action: ' . htmlspecialchars($action));
