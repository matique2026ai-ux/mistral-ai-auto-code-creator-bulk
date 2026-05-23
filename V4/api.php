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
    $provider = p('provider', 'mistral');
    $providers = json_decode(AC4_PROVIDERS, true);
    $cfg = $providers[$provider] ?? $providers['mistral'];
    $url = $cfg['base_url'];
    $headers = [];
    foreach ($cfg['headers'] ?? [] as $k => $v) {
        $headers[] = str_replace('{key}', $key, $k) . ': ' . str_replace('{key}', $key, $v);
    }
    $payload = json_encode(['model' => $cfg['default_model'] ?? AC4_MODEL, 'messages' => [['role'=>'user','content'=>'OK']], 'max_tokens' => 5]);
    if ($provider === 'google') {
        $url = str_replace('{model}', $cfg['default_model'] ?? 'gemini-2.0-flash', $url) . '?key=' . $key;
        $payload = json_encode(['contents' => [['parts' => [['text' => 'OK']]]]]);
    }
    if ($provider === 'anthropic') {
        $payload = json_encode(['model' => $cfg['default_model'], 'messages' => [['role'=>'user','content'=>'OK']], 'max_tokens' => 5]);
    }
    $ch = curl_init($url);
    if ($ch === false) err('cURL init failed');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
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
    $frontend = validateStackItem(p('frontend', ''), 'frontends') ?: 'react';
    $backend  = validateStackItem(p('backend', ''), 'backends') ?: 'node_express';
    $database = validateStackItem(p('database', ''), 'databases') ?: 'sqlite';
    $css      = validateStackItem(p('css', ''), 'css') ?: 'tailwind';
    $lang     = in_array(p('lang', 'fr'), ['fr','en','ar','es','de','pt','zh','ja']) ? p('lang', 'fr') : 'fr';

    // Validate master prompt length
    if (strlen($masterPrompt) > 10000) err('Master prompt trop long (max 10000 caractères)');

    $slug     = slugify($title ?: $masterPrompt) . '-' . substr(uniqid(), -4);
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
    $limit = min((int)(p('limit') ?: 20), 100);
    $offset = max((int)(p('offset') ?: 0), 0);
    $q = trim(p('q', ''));
    $sort = p('sort', 'date_desc');
    $orderMap = [
        'date_desc' => 'id DESC',
        'date_asc' => 'id ASC',
        'score_desc' => 'qa_score DESC',
        'score_asc' => 'qa_score ASC',
        'title' => 'title ASC',
        'status' => "CASE status WHEN 'building' THEN 0 WHEN 'done' THEN 1 WHEN 'failed' THEN 2 ELSE 3 END",
    ];
    $order = $orderMap[$sort] ?? 'id DESC';
    if ($q !== '') {
        $like = '%' . $q . '%';
        $total = $db->prepare("SELECT COUNT(*) FROM projects WHERE title LIKE ? OR frontend LIKE ? OR backend LIKE ? OR status LIKE ?"); $total->execute([$like, $like, $like, $like]);
        $projects = $db->prepare("SELECT id, title, folder, project_type, frontend, backend, database, css_framework, status, qa_score, file_count, build_validated, created_at FROM projects WHERE title LIKE ? OR frontend LIKE ? OR backend LIKE ? OR status LIKE ? ORDER BY $order LIMIT ? OFFSET ?");
        $projects->execute([$like, $like, $like, $like, $limit, $offset]);
    } else {
        $total = $db->query("SELECT COUNT(*) FROM projects");
        $projects = $db->prepare("SELECT id, title, folder, project_type, frontend, backend, database, css_framework, status, qa_score, file_count, build_validated, created_at FROM projects ORDER BY $order LIMIT ? OFFSET ?");
        $projects->execute([$limit, $offset]);
    }
    respond(['projects' => $projects->fetchAll(), 'total' => (int)$total->fetchColumn(), 'limit' => $limit, 'offset' => $offset]);
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

    // Spawn background worker process (background_build.php handles enqueue + worker)
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'background_build.php';
    $phpBin = PHP_BINARY;
    $cmd = "\"$phpBin\" \"$scriptPath\" $id";
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "start /B \"\" $cmd";
        pclose(popen($cmd, 'r'));
    } else {
        exec("nohup $cmd > /dev/null 2>&1 &");
    }

    ok(['message' => 'Build démarré en arrière-plan (queue parallèle)', 'project_id' => $id]);
}

if ($action === 'rebuild_build') {
    set_time_limit(0);
    $id = (int)p('project_id'); if (!$id) err('Project ID required');
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $project = $stmt->fetch();
    if (!$project) err('Project not found');

    // Delete build directory + all data
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($project['folder']);
    if (is_dir($dir)) _rmdir_recursive($dir);
    $db->prepare("DELETE FROM build_logs WHERE project_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM generated_files WHERE project_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM jobs WHERE project_id = ?")->execute([$id]);
    updateProject($db, $id, ['status' => 'building']);

    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'background_build.php';
    $phpBin = PHP_BINARY;
    $cmd = "\"$phpBin\" \"$scriptPath\" $id";
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "start /B \"\" $cmd";
        pclose(popen($cmd, 'r'));
    } else {
        exec("nohup $cmd > /dev/null 2>&1 &");
    }

    ok(['message' => 'Re-build démarré (build effacé + redémarré)', 'project_id' => $id]);
}

if ($action === 'resume_build') {
    set_time_limit(0);
    $id = (int)p('project_id'); if (!$id) err('Project ID required');
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $project = $stmt->fetch();
    if (!$project) err('Project not found');
    if ($project['status'] === 'building') err('Build déjà en cours');

    updateProject($db, $id, ['status' => 'building']);

    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'background_resume.php';
    $phpBin = PHP_BINARY;
    $cmd = "\"$phpBin\" \"$scriptPath\" $id";
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "start /B \"\" $cmd";
        pclose(popen($cmd, 'r'));
    } else {
        exec("nohup $cmd > /dev/null 2>&1 &");
    }

    ok(['message' => 'Reprise du build démarrée', 'project_id' => $id]);
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

        if (ob_get_level()) ob_flush();
        flush();
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
    $realTargetDir = realpath($targetDir);
    $realBuildsDir = realpath(AC4_BUILDS_DIR);
    if (!$realTargetDir || !$realBuildsDir || strpos($realTargetDir, $realBuildsDir) !== 0) err('Invalid project folder');

    $zipFile = tempnam(sys_get_temp_dir(), 'ac4_zip_');
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) err('Failed to create ZIP');

    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $realPath = $file->getRealPath();
                if (strpos($realPath, $realTargetDir) !== 0) continue;
                $zip->addFile($realPath, substr($realPath, strlen($targetDir) + 1));
            }
        }
    } finally {
        $zip->close();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . htmlspecialchars($project['title']) . '.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    unlink($zipFile);
    exit;
}

// ─── EXPORT / IMPORT ──────────────────────────────────────────────────

if ($action === 'export_project') {
    $id = (int)p('id'); if (!$id) err('Project ID required');
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $project = $stmt->fetch();
    if (!$project) err('Project not found');

    $files = $db->prepare("SELECT filepath, language, size, status, created_at FROM generated_files WHERE project_id = ? ORDER BY id ASC"); $files->execute([$id]);
    $logs  = $db->prepare("SELECT step, level, message, job_name, logged_at FROM build_logs WHERE project_id = ? ORDER BY id ASC"); $logs->execute([$id]);

    $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($project['folder']);
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ac4_export_' . $id . '_' . uniqid();
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

    $manifest = [
        'version' => AC4_VERSION,
        'exported_at' => date('Y-m-d H:i:s'),
        'project' => [
            'title' => $project['title'],
            'project_type' => $project['project_type'],
            'frontend' => $project['frontend'],
            'backend' => $project['backend'],
            'database' => $project['database'],
            'css_framework' => $project['css_framework'],
            'output_lang' => $project['output_lang'],
            'brief' => $project['brief'],
            'arch_json' => $project['arch_json'],
            'design_json' => $project['design_json'],
            'stack_choice' => $project['stack_choice'],
            'qa_score' => $project['qa_score'],
            'build_validated' => $project['build_validated'],
            'created_at' => $project['created_at'],
        ],
        'files' => $files->fetchAll(),
        'logs' => $logs->fetchAll(),
    ];

    file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Copy build files
    if (is_dir($buildDir)) {
        $buildsOut = $tmpDir . DIRECTORY_SEPARATOR . 'build';
        if (!is_dir($buildsOut)) mkdir($buildsOut, 0755, true);
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            $relPath = substr($file->getPathname(), strlen($buildDir) + 1);
            $dest = $buildsOut . DIRECTORY_SEPARATOR . $relPath;
            $parent = dirname($dest);
            if (!is_dir($parent)) mkdir($parent, 0755, true);
            copy($file->getPathname(), $dest);
        }
    }

    $zipFile = tempnam(sys_get_temp_dir(), 'ac4_export_');
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) err('Failed to create ZIP');

    try {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            $relPath = substr($file->getPathname(), strlen($tmpDir) + 1);
            $zip->addFile($file->getPathname(), $relPath);
        }
    } finally {
        $zip->close();
    }

    // Clean temp dir
    _rmdir_recursive($tmpDir);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . htmlspecialchars($project['title']) . '_export.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    unlink($zipFile);
    exit;
}

if ($action === 'import_project') {
    if (empty($_FILES['file'])) err('No file uploaded');
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) err('Upload error: ' . $file['error']);

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ac4_import_' . uniqid();
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) { _rmdir_recursive($tmpDir); err('Invalid ZIP file'); }
    $zip->extractTo($tmpDir);
    $zip->close();

    $manifestPath = $tmpDir . DIRECTORY_SEPARATOR . 'manifest.json';
    if (!file_exists($manifestPath)) { _rmdir_recursive($tmpDir); err('manifest.json not found in ZIP'); }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (!$manifest || empty($manifest['project'])) { _rmdir_recursive($tmpDir); err('Invalid manifest.json'); }

    $p = $manifest['project'];

    $slug = slugify($p['title']) . '-' . substr(uniqid(), -4);
    $folder = AC4_BUILDS_WEB . '/' . $slug;
    $newId = createProject($db, [
        'title' => $p['title'], 'folder' => $folder,
        'type' => $p['project_type'] ?? 'fullstack',
        'frontend' => $p['frontend'] ?? 'react',
        'backend' => $p['backend'] ?? 'node_express',
        'database' => $p['database'] ?? 'sqlite',
        'css' => $p['css_framework'] ?? 'tailwind',
        'lang' => $p['output_lang'] ?? 'fr',
        'brief' => $p['brief'] ?? '{}',
    ]);

    // Restore additional fields
    $extraFields = [];
    if (!empty($p['arch_json'])) $extraFields['arch_json'] = $p['arch_json'];
    if (!empty($p['design_json'])) $extraFields['design_json'] = $p['design_json'];
    if (!empty($p['stack_choice'])) $extraFields['stack_choice'] = $p['stack_choice'];
    if (isset($p['qa_score'])) $extraFields['qa_score'] = (int)$p['qa_score'];
    if (isset($p['build_validated'])) $extraFields['build_validated'] = (int)$p['build_validated'];
    if (!empty($p['created_at'])) $extraFields['created_at'] = $p['created_at'];
    if ($extraFields) updateProject($db, $newId, $extraFields);

    // Restore build files
    $importBuildDir = $tmpDir . DIRECTORY_SEPARATOR . 'build';
    $targetBuildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
    if (!is_dir($targetBuildDir)) mkdir($targetBuildDir, 0755, true);
    if (is_dir($importBuildDir)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($importBuildDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $f) {
            $relPath = substr($f->getPathname(), strlen($importBuildDir) + 1);
            $dest = $targetBuildDir . DIRECTORY_SEPARATOR . $relPath;
            $parent = dirname($dest);
            if (!is_dir($parent)) mkdir($parent, 0755, true);
            copy($f->getPathname(), $dest);
        }
    }

    // Restore generated_files
    if (!empty($manifest['files'])) {
        $ins = $db->prepare("INSERT INTO generated_files (project_id, filepath, language, size, status, created_at) VALUES (?,?,?,?,?,?)");
        foreach ($manifest['files'] as $gf) {
            $ins->execute([$newId, $gf['filepath'], $gf['language'] ?? null, (int)($gf['size'] ?? 0), $gf['status'] ?? 'done', $gf['created_at'] ?? date('Y-m-d H:i:s')]);
        }
    }

    // Restore logs
    if (!empty($manifest['logs'])) {
        $ins = $db->prepare("INSERT INTO build_logs (project_id, step, level, message, job_name, logged_at) VALUES (?,?,?,?,?,?)");
        foreach ($manifest['logs'] as $log) {
            $ins->execute([$newId, $log['step'], $log['level'], $log['message'], $log['job_name'] ?? null, $log['logged_at'] ?? date('Y-m-d H:i:s')]);
        }
    }

    // Update file count
    updateProject($db, $newId, ['file_count' => count($manifest['files'] ?? [])]);

    _rmdir_recursive($tmpDir);

    respond(['id' => $newId, 'title' => $p['title'], 'folder' => $folder, 'slug' => $slug]);
}

// ─── CLEANUP ──────────────────────────────────────────────────────────

if ($action === 'cleanup_builds') {
    $days = max(1, (int)(p('days') ?: 30));
    $deleted = ['orphans' => 0, 'old' => 0, 'errors' => 0];
    $buildsDir = AC4_BUILDS_DIR;
    if (!is_dir($buildsDir)) respond($deleted);

    // Get all valid project slugs
    $slugs = $db->query("SELECT folder FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    $validSlugs = [];
    foreach ($slugs as $f) $validSlugs[] = basename($f);

    $items = new DirectoryIterator($buildsDir);
    foreach ($items as $item) {
        if (!$item->isDir() || $item->isDot()) continue;
        $slug = $item->getFilename();

        // Orphaned: directory with no matching project
        if (!in_array($slug, $validSlugs, true)) {
            _rmdir_recursive($item->getPathname());
            $deleted['orphans']++;
            continue;
        }

        // Old completed builds
        $stmt = $db->prepare("SELECT status, updated_at FROM projects WHERE folder LIKE ?");
        $search = '%' . $slug . '%';
        $stmt->execute([$search]);
        $proj = $stmt->fetch();
        if ($proj && in_array($proj['status'], ['done', 'failed'])) {
            $updated = strtotime($proj['updated_at'] ?? '2000-01-01');
            if ($updated > 0 && (time() - $updated) > $days * 86400) {
                _rmdir_recursive($item->getPathname());
                $deleted['old']++;
            }
        }
    }

    $deleted['total'] = $deleted['orphans'] + $deleted['old'];
    respond($deleted);
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
