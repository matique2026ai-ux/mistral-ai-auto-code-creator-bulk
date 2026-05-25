<?php
require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../queue.php';

$db = getDB();

echo "╔════════════════════════════════════════╗\n";
echo "║   Tests DB Helpers                    ║\n";
echo "╚════════════════════════════════════════╝\n\n";

test('createProject — crée un projet et retourne ID', function () use ($db) {
    $slug = 'test-db-' . substr(uniqid(), -6);
    $id = createProject($db, [
        'title' => 'DB Test', 'folder' => 'builds/' . $slug,
        'type' => 'static', 'frontend' => 'html_css_js',
        'backend' => 'none', 'database' => 'none',
        'css' => 'vanilla', 'lang' => 'fr',
        'brief' => json_encode(['master_prompt' => 'Test']),
        'slug' => $slug,
    ]);
    assert_true($id > 0);
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $p = $stmt->fetch();
    assert_eq('DB Test', $p['title']);
    assert_eq('static', $p['project_type']);
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
    if (is_dir($dir)) _rmdir_recursive($dir);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
});

test('updateProject — met à jour les champs', function () use ($db) {
    $slug = 'test-upd-' . substr(uniqid(), -6);
    $id = createProject($db, [
        'title' => 'Update Test', 'folder' => 'builds/' . $slug,
        'type' => 'fullstack', 'frontend' => 'react',
        'backend' => 'node_express', 'database' => 'sqlite',
        'css' => 'tailwind', 'lang' => 'fr',
        'brief' => '{}', 'slug' => $slug,
    ]);
    updateProject($db, $id, ['status' => 'done', 'qa_score' => 95, 'file_count' => 42, 'build_validated' => 1]);
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]); $p = $stmt->fetch();
    assert_eq('done', $p['status']);
    assert_eq(95, (int)$p['qa_score']);
    assert_eq(42, (int)$p['file_count']);
    assert_eq(1, (int)$p['build_validated']);
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
    if (is_dir($dir)) _rmdir_recursive($dir);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
});

test('appendLog — écrit un log en base', function () use ($db) {
    $slug = 'test-log-' . substr(uniqid(), -6);
    $id = createProject($db, [
        'title' => 'Log Test', 'folder' => 'builds/' . $slug,
        'type' => 'static', 'frontend' => 'html_css_js',
        'backend' => 'none', 'database' => 'none',
        'css' => 'vanilla', 'lang' => 'fr',
        'brief' => '{}', 'slug' => $slug,
    ]);
    appendLog($db, $id, 'test_step', 'ok', 'Test message');
    $logs = $db->prepare("SELECT * FROM build_logs WHERE project_id = ?");
    $logs->execute([$id]);
    $log = $logs->fetch();
    assert_true($log !== false);
    assert_eq('test_step', $log['step']);
    assert_eq('ok', $log['level']);
    assert_eq('Test message', $log['message']);
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
    if (is_dir($dir)) _rmdir_recursive($dir);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
});

test('getGlobalStats — retourne les stats enrichies', function () use ($db) {
    $stats = getGlobalStats($db);
    foreach (['keys_total','keys_active','tokens_total','projects_total','projects_done','projects_failed','projects_building','builds_validated','total_files','stacks'] as $k) {
        assert_true(isset($stats[$k]), "Missing stat: $k");
    }
});

test('getNextApiKey — retourne clé active', function () use ($db) {
    $key = getNextApiKey($db);
    if ($key) {
        assert_true($key['is_active'] == 1);
        assert_true($key['error_count'] < AC4_MAX_KEY_ERRORS);
    }
});

test('markKeyError — incrémente error_count', function () use ($db) {
    $keyVal = 'test-key-' . uniqid();
    $db->prepare("INSERT INTO api_keys (label, key_val) VALUES (?, ?)")->execute(['Test Key', $keyVal]);
    $keyId = (int)$db->lastInsertId();
    markKeyError($db, $keyId);
    $stmt = $db->prepare("SELECT error_count FROM api_keys WHERE id = ?"); $stmt->execute([$keyId]);
    assert_eq(1, (int)$stmt->fetchColumn());
    $db->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$keyId]);
});

test('storeMemory et getMemories', function () use ($db) {
    $slug = 'test-mem-' . substr(uniqid(), -6);
    $id = createProject($db, [
        'title' => 'Memory Test', 'folder' => 'builds/' . $slug,
        'type' => 'static', 'frontend' => 'html_css_js',
        'backend' => 'none', 'database' => 'none',
        'css' => 'vanilla', 'lang' => 'fr',
        'brief' => '{}', 'slug' => $slug,
    ]);
    storeMemory($db, $id, 'test_agent', 'Key point', 'Summary details', ['tag1']);
    $memories = getMemories($db, 'test_agent', 5);
    assert_true(count($memories) > 0);
    assert_eq('Key point', $memories[0]['key_point']);
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
    if (is_dir($dir)) _rmdir_recursive($dir);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
});

test('searchMemories — returns array', function () use ($db) {
    assert_true(is_array(searchMemories($db, 'Key point', 5)));
});

printSummary('Résumé DB Helpers');
exit($failed > 0 ? 1 : 0);
