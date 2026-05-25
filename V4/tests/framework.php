<?php
/**
 * Test Framework — defines test() and assertion functions only, NO executable code
 */
$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, callable $fn): void {
    global $passed, $failed, $errors;
    try {
        $fn();
        echo "  ✅ $name\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ❌ $name\n";
        $errors[] = "  $name — {$e->getMessage()}";
        $failed++;
    }
}

function assert_eq($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $diff = $msg ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        throw new RuntimeException($diff);
    }
}

function assert_true($actual, string $msg = ''): void {
    if ($actual !== true) throw new RuntimeException($msg ?: "Expected true, got " . var_export($actual, true));
}

function printSummary(string $title): void {
    global $passed, $failed, $errors;
    echo "\n╔════════════════════════════════════════╗\n";
    echo "║   $title\n";
    echo "╚════════════════════════════════════════╝\n\n";
    $total = $passed + $failed;
    echo "  $passed / $total tests réussis\n";
    if ($errors) {
        echo "\n  Échecs :\n";
        foreach ($errors as $e) echo "  $e\n";
    }
    echo "\n";
}
