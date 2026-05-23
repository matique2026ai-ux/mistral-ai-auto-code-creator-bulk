<?php
require __DIR__ . '/V4/config.php';
require __DIR__ . '/V4/db.php';
require __DIR__ . '/V4/engine.php';

$db = getDB();
$slug = 'site_' . time() . '_' . substr(uniqid(), -8);
$folder = 'builds/' . $slug;
$brief = [
    'master_prompt' => 'Crée une application web de gestion de tâches (Todo List). Stack: React (Vite) frontend, Node.js + Express backend, SQLite base de données. Fonctionnalités : ajouter, lister, supprimer des tâches, marquer comme complétées. UI moderne avec Tailwind CSS.',
    'title' => 'Todo List App',
    'project_id' => 0,
    'folder' => $folder,
    'who' => '',
    'target' => '',
    'monetize' => '',
    'project_type' => 'fullstack',
    'frontend' => 'react',
    'backend' => 'node_express',
    'database' => 'sqlite',
    'css_framework' => 'tailwind',
    'lang' => 'fr',
];

$id = createProject($db, [
    'title' => $brief['title'],
    'slug' => $slug,
    'folder' => $folder,
    'type' => 'fullstack',
    'frontend' => 'react',
    'backend' => 'node_express',
    'database' => 'sqlite',
    'css' => 'tailwind',
    'lang' => 'fr',
    'brief' => json_encode($brief),
]);

$brief['project_id'] = $id;

echo "=== BUILD #$id: Todo List App (React + Node + SQLite) ===\n";
echo "Heure: " . date('H:i:s') . "\n";
echo "Dossier: $folder\n\n";

updateProject($db, $id, ['status' => 'building']);
$db->prepare("DELETE FROM build_logs WHERE project_id = ?")->execute([$id]);
$db->prepare("DELETE FROM generated_files WHERE project_id = ?")->execute([$id]);

$engine = new PipelineEngine();
try {
    $result = $engine->run($brief);
    $status = $result['success'] ? 'done' : 'failed';
    updateProject($db, $id, ['status' => $status]);
    echo "\n=== RÉSULTAT ===\n";
    echo "Status: $status\n";
    echo "Score QA: " . ($result['qa_score'] ?? 'N/A') . "/100\n";
    echo "Fichiers: " . ($result['files'] ?? 0) . "\n";
    echo "Build validé: " . ($result['build_validated'] ?? '0') . "\n";
    echo "Erreurs: " . ($result['errors'] ?? 'aucune') . "\n";
} catch (\Throwable $e) {
    updateProject($db, $id, ['status' => 'failed']);
    echo "\n=== ERREUR ===\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
