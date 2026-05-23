<?php
/**
 * Helpers partagés entre API et tests
 */
if (!function_exists('p')) { function p(string $key, $default = '') { global $body; return $body[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default; } }
if (!function_exists('pInt')) { function pInt(string $key, int $default = 0): int { return (int)p($key, (string)$default); } }
if (!function_exists('pSafe')) { function pSafe(string $key, string $default = ''): string { return strip_tags(trim(p($key, $default))); } }
if (!function_exists('respond')) { function respond(array $data): never { echo json_encode($data); exit; } }
if (!function_exists('err')) { function err(string $msg): never { respond(['error' => $msg]); } }
if (!function_exists('ok')) { function ok(array $extra = []): never { respond(array_merge(['success' => true], $extra)); } }

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

if (!function_exists('_rmdir_recursive')) { function _rmdir_recursive(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
    }
    rmdir($dir);
} }

function webSearch(string $query, int $maxResults = 5): array {
    $results = [];
    $ch = curl_init('https://api.duckduckgo.com/?q=' . urlencode($query) . '&format=json&no_html=1&skip_disambig=1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($resp, true);
        if (!empty($data['AbstractText'])) {
            $results[] = ['title' => $data['Heading'] ?? '', 'snippet' => $data['AbstractText'], 'source' => $data['AbstractSource'] ?? 'DuckDuckGo'];
        }
        if (!empty($data['RelatedTopics'])) {
            foreach (array_slice($data['RelatedTopics'], 0, $maxResults) as $topic) {
                if (isset($topic['Text'])) {
                    $results[] = ['title' => $topic['FirstURL'] ?? '', 'snippet' => $topic['Text'], 'source' => 'DuckDuckGo'];
                }
            }
        }
    }

    return $results;
}
