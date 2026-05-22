<?php
/**
 * AutoCoder V3 — Secure Project ZIP Downloader
 * Compiles all project files into a ZIP archive and triggers browser download.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Security check and validations
$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) {
    http_response_code(400);
    die("Error: Project ID is required.");
}

$db  = getDB();
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    die("Error: Project not found.");
}

$folderName = basename($project['folder']); // e.g. site_171638210_abc123
$targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'builds' . DIRECTORY_SEPARATOR . $folderName;

if (!is_dir($targetDir)) {
    http_response_code(404);
    die("Error: Project source folder does not exist or has been deleted.");
}

// Secure traversal check: ensure final realpath starts with the builds directory
$realBase = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'builds');
$realTarget = realpath($targetDir);

if (!$realBase || !$realTarget || !str_starts_with($realTarget, $realBase)) {
    http_response_code(403);
    die("Error: Access denied (security boundary violation).");
}

// Create a temporary ZIP file name
$tempZipFile = tempnam(sys_get_temp_dir(), 'autocoder_zip_');
if (!$tempZipFile) {
    http_response_code(500);
    die("Error: Unable to create temporary archive file on server.");
}

$zip = new ZipArchive();
if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die("Error: Failed to open ZipArchive.");
}

// Loop recursively through the folder and add all files
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($realTarget, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    // Only add files (directories are handled implicitly)
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        // Calculate relative path inside the ZIP
        $relativePath = substr($filePath, strlen($realTarget) + 1);
        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

// Stream zip to browser
if (file_exists($tempZipFile) && filesize($tempZipFile) > 0) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . htmlspecialchars($project['title'] ?: 'autocoder_project') . '.zip"');
    header('Content-Length: ' . filesize($tempZipFile));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Clear output buffer to avoid corruption
    ob_clean();
    flush();
    
    readfile($tempZipFile);
    unlink($tempZipFile); // delete temporary file
    exit;
} else {
    http_response_code(500);
    die("Error: Failed to compile ZIP archive.");
}
