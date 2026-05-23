<?php
/**
 * Helpers partagés entre API et tests
 */
function p(string $key, $default = '') { global $body; return $body[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default; }
function pInt(string $key, int $default = 0): int { return (int)p($key, (string)$default); }
function pSafe(string $key, string $default = ''): string { return strip_tags(trim(p($key, $default))); }
function respond(array $data): never { echo json_encode($data); exit; }
function err(string $msg): never { respond(['error' => $msg]); }
function ok(array $extra = []): never { respond(array_merge(['success' => true], $extra)); }

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
    if ($item === '') return '';
    $stacks = json_decode(AC4_STACKS, true);
    $allItems = [];
    foreach ($stacks as $s) {
        if (isset($s[$category])) $allItems = array_merge($allItems, $s[$category]);
    }
    $allItems = array_unique($allItems);
    return in_array($item, $allItems) ? $item : '';
}

function slugify(string $text, int $maxLen = 40): string {
    $text = strip_tags($text);
    $text = trim($text);
    $text = str_replace(
        ['é','è','ê','ë','à','â','ä','ù','û','ü','ô','ö','î','ï','ç','ñ',
         'É','È','Ê','Ë','À','Â','Ä','Ù','Û','Ü','Ô','Ö','Î','Ï','Ç','Ñ'],
        ['e','e','e','e','a','a','a','u','u','u','o','o','i','i','c','n',
         'e','e','e','e','a','a','a','u','u','u','o','o','i','i','c','n'],
        $text
    );
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    return substr($text, 0, $maxLen) ?: 'projet';
}

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
