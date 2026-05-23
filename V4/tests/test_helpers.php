<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

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

// ═══════════════════════════════════════════════
echo "╔════════════════════════════════════════╗\n";
echo "║   Tests Helpers                       ║\n";
echo "╚════════════════════════════════════════╝\n\n";

test('validateProjectType — valide', function () {
    assert_eq('fullstack', validateProjectType('fullstack'));
    assert_eq('mobile', validateProjectType('mobile'));
    assert_eq('api', validateProjectType('api'));
    assert_eq('static', validateProjectType('static'));
});

test('validateProjectType — invalide retourne fullstack', function () {
    assert_eq('fullstack', validateProjectType('invalid'));
    assert_eq('fullstack', validateProjectType(''));
    assert_eq('fullstack', validateProjectType('blog'));
});

test('validateStackItem — valide retourne le meme', function () {
    assert_eq('react', validateStackItem('react', 'frontends'));
    assert_eq('node_express', validateStackItem('node_express', 'backends'));
    assert_eq('sqlite', validateStackItem('sqlite', 'databases'));
    assert_eq('tailwind', validateStackItem('tailwind', 'css'));
});

test('validateStackItem — nouvelles stacks valides', function () {
    assert_eq('solid', validateStackItem('solid', 'frontends'));
    assert_eq('qwik', validateStackItem('qwik', 'frontends'));
    assert_eq('express_typescript', validateStackItem('express_typescript', 'backends'));
    assert_eq('nestjs', validateStackItem('nestjs', 'backends'));
});

test('validateStackItem — invalide retourne chaine vide', function () {
    assert_eq('', validateStackItem('nonexistent_framework', 'frontends'));
    assert_eq('', validateStackItem('', 'frontends'));
});

test('slugify — caracteres speciaux', function () {
    assert_eq('bonjour-le-monde', slugify('Bonjour le monde !'));
    assert_eq('c-est-un-test', slugify("C'est un test!"));
});

test('slugify — caracteres accentues', function () {
    assert_eq('evaluation', slugify('évaluation'));
    assert_eq('evaluation', slugify('Évaluation'));
    assert_eq('eaee', slugify('éàèê'));
    assert_eq('ouicn', slugify('ôûîçñ'));
});

test('slugify — vide retourne projet', function () {
    assert_eq('projet', slugify(''));
});

test('slugify — limite de longueur', function () {
    $long = slugify('Ceci est un très long titre de projet', 20);
    assert_eq(20, strlen($long), "Expected length 20, got '$long' (" . strlen($long) . ")");
});

// ═══════════════════════════════════════════════
echo "\n╔════════════════════════════════════════╗\n";
echo "║   Résumé                               ║\n";
echo "╚════════════════════════════════════════╝\n\n";
$total = $passed + $failed;
echo "  $passed / $total tests réussis\n";
if ($errors) {
    echo "\n  Échecs :\n";
    foreach ($errors as $e) echo "  $e\n";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
