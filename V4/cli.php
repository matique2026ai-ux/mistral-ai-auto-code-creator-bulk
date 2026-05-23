<?php
/**
 * AkrourCoder V4 — Mode CLI
 * Usage:
 *   php cli.php --prompt="Crée un site e-commerce" [options]
 *
 * Options:
 *   --prompt       Master prompt (obligatoire)
 *   --title        Titre du projet (optionnel, défaut: extrait du prompt)
 *   --type         fullstack|mobile|api|static (défaut: fullstack)
 *   --frontend     react|next|vue|svelte|... (défaut: auto)
 *   --backend      node_express|fastapi_python|laravel_php|... (défaut: auto)
 *   --database     sqlite|postgresql|mysql|mongodb (défaut: sqlite)
 *   --css          tailwind|bootstrap|vanilla (défaut: tailwind)
 *   --lang         fr|en|ar|es|de|pt|zh|ja (défaut: fr)
 *   --wait         Attendre la fin du build (défaut: true)
 *   --help         Affiche cette aide
 *
 * Exemples:
 *   php cli.php --prompt="Blog personnel avec Next.js"
 *   php cli.php --prompt="API REST" --type=api --backend=fastapi_python --wait=1
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ─── Parse arguments ──────────────────────────────────────────────────
$longopts = [
    'prompt:', 'title:', 'type:', 'frontend:', 'backend:',
    'database:', 'css:', 'lang:', 'wait::', 'help',
];
$args = getopt('', $longopts);

if (isset($args['help']) || empty($args['prompt'])) {
    $usage = file_get_contents(__FILE__);
    preg_match('/\/\*\*([\s\S]*?)\*\//', $usage, $m);
    echo trim($m[1] ?? "Usage: php cli.php --prompt=\"...\"") . "\n";
    exit(isset($args['help']) ? 0 : 1);
}

$masterPrompt = $args['prompt'];
$title = $args['title'] ?? substr($masterPrompt, 0, 50);
$type = $args['type'] ?? 'fullstack';
$frontend = $args['frontend'] ?? '';
$backend = $args['backend'] ?? '';
$database = $args['database'] ?? 'sqlite';
$css = $args['css'] ?? 'tailwind';
$lang = $args['lang'] ?? 'fr';
$wait = $args['wait'] ?? '1';

// ─── Validate types ───────────────────────────────────────────────────
$validTypes = ['fullstack', 'mobile', 'api', 'static'];
if (!in_array($type, $validTypes)) {
    echo "❌ Type invalide : $type. Choisir parmi : " . implode(', ', $validTypes) . "\n"; exit(1);
}

$db = getDB();

// ─── Create project ───────────────────────────────────────────────────
$slug = slugify($title) . '-' . substr(uniqid(), -4);
$folder = AC4_BUILDS_WEB . '/' . $slug;

$brief = json_encode(['master_prompt' => $masterPrompt, 'who' => '', 'target' => '', 'monetize' => '']);

$id = createProject($db, [
    'title' => $title, 'folder' => $folder,
    'type' => $type,
    'frontend' => $frontend, 'backend' => $backend,
    'database' => $database, 'css' => $css, 'lang' => $lang,
    'brief' => $brief, 'slug' => $slug,
]);

echo "📦 Projet créé #$id : $title\n";
echo "   Type : $type | Lang : $lang\n";
echo "   Stack : " . ($frontend ?: 'auto') . " + " . ($backend ?: 'auto') . " + $database\n";
echo "   Dossier : $folder\n";

// ─── Launch build ────────────────────────────────────────────────────
updateProject($db, $id, ['status' => 'building']);

require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/queue.php';

$queue = new JobQueue();
$jobs = $queue->enqueueProject($id);
echo "🚀 Build démarré — " . count($jobs) . " jobs en file\n";

require_once __DIR__ . '/worker.php';
$workerId = 'cli_' . uniqid();
runWorkerLoop($id, $queue, $workerId, 1);

// ─── Check result ─────────────────────────────────────────────────────
$failed = $queue->getFailedJobs($id);
$stmt = $db->prepare("SELECT status, qa_score FROM projects WHERE id = ?"); $stmt->execute([$id]);
$proj = $stmt->fetch();

if (empty($failed) && $proj && $proj['status'] === 'done') {
    echo "\n✅ Build #$id terminé ! Score QA : " . ($proj['qa_score'] ?? 'N/A') . "/100\n";
    echo "📁 builds/$slug/index.html\n";
    exit(0);
} else {
    echo "\n❌ Build #$id échoué\n";
    $errJobs = $queue->getFailedJobs($id);
    foreach ($errJobs as $ej) {
        echo "   - {$ej['job_name']}: {$ej['error_message']}\n";
    }
    exit(1);
}
