<?php
// save.php — compatibilité avec index.php (JSON body) et api.php
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Fallback to POST
if (!$data) {
    $data = $_POST;
}

if (isset($data['path']) && isset($data['content'])) {
    $path = $data['path'];
    
    // Security: only allow writing in builds/ folder
    if (strpos($path, 'builds/') !== 0 && strpos($path, './builds/') !== 0) {
        echo json_encode(['error' => 'Chemin non autorisé: ' . $path]);
        exit;
    }
    
    $target = __DIR__ . '/' . ltrim($path, '/');
    $dir = dirname($target);
    
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $result = file_put_contents($target, $data['content']);
    
    if ($result !== false) {
        echo json_encode(['status' => 'success', 'bytes' => $result, 'path' => $path]);
    } else {
        echo json_encode(['error' => 'Échec écriture: ' . $target]);
    }
} else {
    echo json_encode(['error' => 'Données manquantes (path/content)']);
}
