<?php
require_once 'db.php';
header('Content-Type: application/json');

// ---- DETECT ACTION from FormData OR raw JSON body ----
$action = '';
$jsonBody = null;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $jsonBody = json_decode($raw, true);
    $action = $jsonBody['action'] ?? '';
} else {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
}

$db = getDB();

// ---- ADD KEY ----
if ($action === 'add_key') {
    $pseudo = trim($_POST['pseudo'] ?? '');
    $key    = trim($_POST['key'] ?? '');
    if (!$pseudo || !$key) { echo json_encode(['error'=>'Champs manquants']); exit; }
    $stmt = $db->prepare("INSERT OR IGNORE INTO api_keys (pseudo, key_val) VALUES (?,?)");
    $stmt->execute([$pseudo, $key]);
    echo json_encode(['success' => true]);
    exit;
}

// ---- TEST KEY ----
if ($action === 'test_key') {
    $key = trim($_POST['key'] ?? '');
    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'devstral-2512',
            'messages' => [['role'=>'user','content'=>'Say OK']],
            'max_tokens' => 5
        ]),
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $res = json_decode($response, true);
    $tokens = $res['usage']['total_tokens'] ?? 0;
    $model_status = ($code === 200) ? 'OK' : 'Erreur '.$code;
    $db->prepare("INSERT OR REPLACE INTO model_limits (model_name, limit_tpm, limit_rps, last_tested, last_status) VALUES ('devstral-2512', 50000, 1.0, CURRENT_TIMESTAMP, ?)")->execute([$model_status]);
    echo json_encode(['code' => $code, 'status' => $model_status, 'tokens' => $tokens]);
    exit;
}

// ---- GET DATA ----
if ($action === 'get_data') {
    $keys = $db->query("SELECT id, pseudo, substr(key_val,1,8)||'....'||substr(key_val,-4) as key_masked, is_active, error_count, last_used FROM api_keys ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $model = $db->query("SELECT * FROM model_limits WHERE model_name='devstral-2512'")->fetch(PDO::FETCH_ASSOC);
    $token_sum = $db->query("SELECT SUM(tokens_used) as total FROM token_usage")->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['keys' => $keys, 'model' => $model, 'token_total' => $token_sum['total'] ?? 0]);
    exit;
}

// ---- GET NEXT KEY ----
if ($action === 'get_key') {
    $k = $db->query("SELECT * FROM api_keys WHERE is_active=1 AND error_count < 3 ORDER BY last_used ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$k) { echo json_encode(['error' => 'Aucune cle API disponible']); exit; }
    $db->prepare("UPDATE api_keys SET last_used=CURRENT_TIMESTAMP WHERE id=?")->execute([$k['id']]);
    echo json_encode(['key' => $k['key_val'], 'id' => $k['id']]);
    exit;
}

// ---- MARK KEY ERROR ----
if ($action === 'key_error') {
    $id = intval($_POST['id'] ?? $jsonBody['id'] ?? 0);
    if ($id) {
        $db->prepare("UPDATE api_keys SET error_count = error_count + 1 WHERE id=?")->execute([$id]);
        $db->prepare("UPDATE api_keys SET is_active=0 WHERE id=? AND error_count >= 3")->execute([$id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ---- RECORD USAGE ----
if ($action === 'record_usage') {
    $kid    = intval($_POST['key_id'] ?? $jsonBody['key_id'] ?? 0);
    $tokens = intval($_POST['tokens'] ?? $jsonBody['tokens'] ?? 0);
    if ($kid && $tokens) {
        $db->prepare("INSERT INTO token_usage (api_key_id, tokens_used, model) VALUES (?,?,?)")->execute([$kid, $tokens, 'devstral-2512']);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ---- SAVE FILE ----
if ($action === 'save_file') {
    $path    = $jsonBody['path']    ?? $_POST['path']    ?? '';
    $content = $jsonBody['content'] ?? $_POST['content'] ?? '';

    if (!$path) { echo json_encode(['error' => 'path manquant']); exit; }

    // Security: only allow writes inside builds/
    if (strpos($path, 'builds/') !== 0) {
        echo json_encode(['error' => 'Chemin non autorise: ' . $path]); exit;
    }

    $target = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $dir    = dirname($target);

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo json_encode(['error' => 'Impossible de creer le dossier: ' . $dir]); exit;
        }
    }

    $bytes = file_put_contents($target, $content);
    if ($bytes === false) {
        echo json_encode(['error' => 'Echec ecriture: ' . $target]); exit;
    }

    echo json_encode(['success' => true, 'path' => $path, 'bytes' => $bytes]);
    exit;
}

// ---- CREATE PROJECT ----
if ($action === 'create_project') {
    $title  = trim($_POST['title'] ?? ('Site '.date('YmdHis')));
    $folder = 'builds/site_' . time();
    $brief  = $_POST['brief'] ?? '';

    // Pre-create the folder immediately
    $projectDir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
    if (!is_dir($projectDir)) mkdir($projectDir, 0755, true);

    $stmt = $db->prepare("INSERT INTO projects (title, folder, brief, status) VALUES (?,?,?,'building')");
    $stmt->execute([$title, $folder, $brief]);
    echo json_encode(['id' => $db->lastInsertId(), 'folder' => $folder]);
    exit;
}

// ---- UPDATE PROJECT STATUS ----
if ($action === 'update_project') {
    $id     = intval($_POST['id'] ?? $jsonBody['id'] ?? 0);
    $status = $_POST['status'] ?? $jsonBody['status'] ?? 'done';
    $db->prepare("UPDATE projects SET status=? WHERE id=?")->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ---- LIST PROJECTS ----
if ($action === 'list_projects') {
    $projects = $db->query("SELECT * FROM projects ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['projects' => $projects]);
    exit;
}

echo json_encode(['error' => 'Action inconnue: ' . $action]);
