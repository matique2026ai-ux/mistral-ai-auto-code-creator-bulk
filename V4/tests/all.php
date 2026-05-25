<?php
/**
 * Master test runner — exécute toutes les suites de tests
 * Usage: php tests/all.php
 */
$testFiles = [
    'test_helpers.php' => 'Helpers',
    'test_config.php'  => 'Config',
    'test_queue.php'   => 'JobQueue',
    'test_db.php'      => 'DB Helpers',
    'test_engine.php'  => 'Pipeline Engine',
];

$totalPassed = 0;
$totalFailed = 0;
$totalTests = 0;

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║   AkrourCoder V4 — Suite de Tests Complète              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

foreach ($testFiles as $file => $label) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($path)) {
        echo "  ⚠ Fichier manquant : $file\n";
        continue;
    }

    // Capture globals before
    $prevPassed = $GLOBALS['passed'] ?? 0;
    $prevFailed = $GLOBALS['failed'] ?? 0;

    // Run in a separate process to isolate globals
    $output = [];
    $code = -1;
    exec(PHP_BINARY . ' "' . $path . '" 2>&1', $output, $code);
    $outputText = implode("\n", $output);

    // Extract test counts from output
    $passMatch = [];
    preg_match('/(\d+)\s*\/\s*(\d+)\s*tests réussis/', $outputText, $passMatch);

    if (!empty($passMatch)) {
        $passed = (int)$passMatch[1];
        $total = (int)$passMatch[2];
        $failed = $total - $passed;
        $totalPassed += $passed;
        $totalFailed += $failed;
        $totalTests += $total;

        if ($failed > 0) {
            echo "  ❌ $label : $passed/$total\n";
            // Show only error lines
            foreach ($output as $line) {
                if (str_starts_with($line, '  ❌') || str_starts_with($line, '  Échec')) {
                    echo "    $line\n";
                }
            }
        } else {
            echo "  ✅ $label : $passed/$total\n";
        }
    } else {
        echo "  ⚠ $label : pas de résultat détecté (code: $code)\n";
        echo "  " . implode("\n  ", array_slice($output, -5)) . "\n";
    }
}

echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║   Résumé Final                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";
echo "  ✅ $totalPassed / $totalTests tests réussis\n";
if ($totalFailed > 0) {
    echo "  ❌ $totalFailed échecs\n";
}
echo "\n";
exit($totalFailed > 0 ? 1 : 0);
