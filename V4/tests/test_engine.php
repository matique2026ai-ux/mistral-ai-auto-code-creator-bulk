<?php
require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../models.php';
require_once __DIR__ . '/../queue.php';
require_once __DIR__ . '/../engine.php';

$db = getDB();

// ─── Mock AIModel ─────────────────────────────────────────────────────

class MockAIModel extends AIModel {
    private array $queue = [];
    public array $callHistory = [];

    public function __construct() {
        // Skip parent to avoid DB dependency
    }

    public function addResponse(array $response): void {
        $this->queue[] = $response;
    }

    public function call(array $messages, int $maxTokens = 4000, bool $jsonMode = true, string $step = '', string $preferredProvider = ''): array {
        $this->callHistory[] = ['step' => $step, 'tokens' => $maxTokens];
        if (empty($this->queue)) {
            throw new \Exception("MockAIModel: no response queued (step=$step)");
        }
        $response = array_shift($this->queue);
        return ['content' => json_encode($response), 'tokens' => 100];
    }
}

echo "╔════════════════════════════════════════╗\n";
echo "║   Tests Pipeline Engine               ║\n";
echo "╚════════════════════════════════════════╝\n\n";

// ─── Helper: create project & brief ──────────────────────────────────

function makeTestProject(PDO $db): array {
    $slug = 'test-engine-' . substr(uniqid(), -6);
    $folder = 'builds/' . $slug;
    $id = createProject($db, [
        'title' => 'Engine Test', 'folder' => $folder,
        'type' => 'static', 'frontend' => 'html_css_js',
        'backend' => 'none', 'database' => 'none',
        'css' => 'vanilla', 'lang' => 'fr',
        'brief' => json_encode(['master_prompt' => 'Test page']),
        'slug' => $slug,
    ]);
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return [
        'project_id' => $id,
        'title' => 'Engine Test',
        'folder' => $folder,
        'frontend' => 'html_css_js',
        'backend' => 'none',
        'database' => 'none',
        'css_framework' => 'vanilla',
        'project_type' => 'static',
        'lang' => 'fr',
        'master_prompt' => 'Test page',
    ];
}

function cleanupTestProject(PDO $db, array $brief): void {
    $id = $brief['project_id'];
    $slug = basename($brief['folder']);
    $dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
    if (is_dir($dir)) {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f) $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
        rmdir($dir);
    }
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
}

function buildEngineResponses(): array {
    $cto = [
        'analysis' => ['project_type' => 'static', 'reasoning' => 'Test static site', 'extracted_title' => 'Engine Test'],
        'stack_decision' => ['frontend' => 'html_css_js', 'backend' => 'none', 'database' => 'none', 'css_framework' => 'vanilla'],
    ];
    $architect = [
        'site_name' => 'Engine Test',
        'site_concept' => 'A simple test page',
        'frontend_pages' => [['route' => '/', 'title' => 'Home', 'description' => 'Landing page']],
        'api_endpoints' => [],
        'database_schema' => ['tables' => []],
        'architecture_pattern' => 'single_page',
        'architecture_decisions' => ['Simple static HTML'],
    ];
    $designer = [
        'design_tokens' => ['primary_color' => '#2563eb', 'font' => 'Inter'],
        'design_rationale' => 'Clean modern design',
        'components' => [['name' => 'Layout', 'description' => 'Main wrapper']],
        'animations' => [],
        'color_palette' => ['primary' => '#2563eb', 'background' => '#ffffff'],
    ];
    $backend = [
        'files' => [['filename' => 'api.php', 'content' => "<?php\nheader('Content-Type: application/json');\necho json_encode(['status' => 'ok']);", 'language' => 'php']],
        'config_files' => [],
    ];
    $frontendFile = [
        'filename' => 'index.html',
        'content' => '<!DOCTYPE html><html><head><title>Test</title></head><body><h1>Hello</h1></body></html>',
        'language' => 'html',
    ];
    $qa = [
        'overall_score' => 100,
        'score' => 100,
        'issues' => [],
        'build_errors' => [],
        'recommendations' => [],
    ];
    $devops = [
        'docker' => false,
        'ci_cd' => false,
        'config_files' => [],
        'notes' => 'Static site — no Docker needed',
    ];
    return [$cto, $architect, $designer, $backend, $frontendFile, $qa, $devops];
}

// ═══════════════════════════════════════════════════════════════════════
//  TEST 1 — Pipeline complet (7 agents + QA) s'exécute sans erreur
// ═══════════════════════════════════════════════════════════════════════

test('Pipeline complet — 7 agents s\'exécutent sans erreur', function () use ($db) {
    $brief = makeTestProject($db);
    try {
        $mock = new MockAIModel();
        $responses = buildEngineResponses();
        foreach ($responses as $r) $mock->addResponse($r);

        $engine = new PipelineEngine($mock);
        $result = $engine->run($brief);

        assert_true(is_array($result), 'Result should be an array');
        assert_eq(7, count($mock->callHistory), 'Expected 7 AI calls');
        assert_eq('cto', $mock->callHistory[0]['step']);
        assert_eq('architect', $mock->callHistory[1]['step']);
        assert_eq('designer', $mock->callHistory[2]['step']);
        assert_eq('backend', $mock->callHistory[3]['step']);
        assert_eq('frontend-file', $mock->callHistory[4]['step']);
        assert_eq('qa', $mock->callHistory[5]['step']);
        assert_eq('devops', $mock->callHistory[6]['step']);

        $stmt = $db->prepare("SELECT status, qa_score, build_validated FROM projects WHERE id = ?");
        $stmt->execute([$brief['project_id']]);
        $p = $stmt->fetch();
        assert_eq('done', $p['status']);
        assert_eq(100, (int)$p['qa_score']);
        assert_eq(1, (int)$p['build_validated']);

        $filePath = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($brief['folder']) . DIRECTORY_SEPARATOR . 'index.html';
        assert_true(file_exists($filePath), 'index.html should exist');
    } finally {
        cleanupTestProject($db, $brief);
    }
});

// ═══════════════════════════════════════════════════════════════════════
//  TEST 2 — Boucle QA se déclenche quand score < 95 et corrige
// ═══════════════════════════════════════════════════════════════════════

test('Boucle QA — déclenche réparation quand score < 95', function () use ($db) {
    $brief = makeTestProject($db);
    try {
        $mock = new MockAIModel();
        $responses = buildEngineResponses();
        // First QA call returns low score + issues
        $responses[5] = [
            'overall_score' => 70,
            'score' => 70,
            'issues' => [['file' => 'index.html', 'type' => 'style', 'description' => 'Missing viewport meta tag']],
            'build_errors' => [],
            'recommendations' => ['Add viewport meta'],
        ];
        // Pipeline order: QA (70) → repair (1 call) → QA (98) → devops
        $devopsResponse = $responses[6];
        $responses[6] = [
            'fixes' => [
                ['filename' => 'index.html', 'content' => '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width"><title>Test</title></head><body><h1>Fixed</h1></body></html>'],
            ],
        ];
        $responses[7] = [
            'overall_score' => 98,
            'score' => 98,
            'issues' => [],
            'build_errors' => [],
            'recommendations' => [],
        ];
        $responses[8] = $devopsResponse;
        foreach ($responses as $r) $mock->addResponse($r);

        $engine = new PipelineEngine($mock);
        $engine->run($brief);

        // Check that QA was called 2 times (1 initial + 1 after repair)
        $qaCalls = array_values(array_filter($mock->callHistory, fn($c) => $c['step'] === 'qa'));
        assert_eq(2, count($qaCalls), 'Expected 2 QA calls (initial + repair)');

        $stmt = $db->prepare("SELECT qa_score, build_validated FROM projects WHERE id = ?");
        $stmt->execute([$brief['project_id']]);
        $p = $stmt->fetch();
        assert_eq(98, (int)$p['qa_score'], 'Final QA score should be 98');
    } finally {
        cleanupTestProject($db, $brief);
    }
});

// ═══════════════════════════════════════════════════════════════════════
//  TEST 3 — Erreur API → retry puis échec propre
// ═══════════════════════════════════════════════════════════════════════

test('Erreur API — échec propre sans crash', function () use ($db) {
    $brief = makeTestProject($db);
    try {
        $mock = new MockAIModel();
        // Only queue CTO response, then throw for second call
        $mock->addResponse([
            'analysis' => ['project_type' => 'static', 'reasoning' => 'Test'],
            'stack_decision' => ['frontend' => 'html_css_js', 'backend' => 'none', 'database' => 'none', 'css_framework' => 'vanilla'],
        ]);
        // Second call (architect) will fail because no more queued responses

        $engine = new PipelineEngine($mock);
        $result = $engine->run($brief);

        // Pipeline should return failure, not crash
        assert_true(is_array($result), 'Result should be an array');
        assert_true(isset($result['success']) && $result['success'] === false, 'Should indicate failure');
        assert_true(!empty($result['error']), 'Should contain error message');

        // Project status should NOT be 'done'
        $stmt = $db->prepare("SELECT status FROM projects WHERE id = ?");
        $stmt->execute([$brief['project_id']]);
        $p = $stmt->fetch();
        assert_true($p['status'] !== 'done', 'Project should not be marked done');
    } finally {
        cleanupTestProject($db, $brief);
    }
});

// ═══════════════════════════════════════════════════════════════════════
//  TEST 4 — Résumé des appels AI pour un pipeline simple
// ═══════════════════════════════════════════════════════════════════════

test('Pipeline — vérifie l\'ordre exact des appels AI', function () use ($db) {
    $brief = makeTestProject($db);
    try {
        $mock = new MockAIModel();
        $responses = buildEngineResponses();
        foreach ($responses as $r) $mock->addResponse($r);

        $engine = new PipelineEngine($mock);
        $engine->run($brief);

        $expectedOrder = ['cto', 'architect', 'designer', 'backend', 'frontend-file', 'qa', 'devops'];
        $actualSteps = array_map(fn($c) => $c['step'], $mock->callHistory);
        assert_eq($expectedOrder, array_slice($actualSteps, 0, 7), 'First 7 calls should match agent order');
    } finally {
        cleanupTestProject($db, $brief);
    }
});

printSummary('Résumé Pipeline Engine');
exit($failed > 0 ? 1 : 0);
