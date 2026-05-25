<?php
require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/../config.php';

echo "╔════════════════════════════════════════╗\n";
echo "║   Tests Config                        ║\n";
echo "╚════════════════════════════════════════╝\n\n";

$frontends = json_decode(AC4_FRONTENDS, true);
$backends = json_decode(AC4_BACKENDS, true);
$databases = json_decode(AC4_DATABASES, true);
$css = json_decode(AC4_CSS, true);
$stacks = json_decode(AC4_STACKS, true);
$providers = json_decode(AC4_PROVIDERS, true);
$agentMap = json_decode(AC4_AGENT_MODEL_MAP, true);

test('AC4_PROVIDERS — tous les providers ont les champs requis', function () use ($providers) {
    foreach ($providers as $name => $cfg) {
        assert_true(isset($cfg['base_url']), "$name: missing base_url");
        assert_true(isset($cfg['models']), "$name: missing models");
        assert_true(isset($cfg['headers']), "$name: missing headers");
        assert_true(isset($cfg['default_model']), "$name: missing default_model");
        assert_true(count($cfg['models']) > 0, "$name: empty models");
    }
    assert_true(isset($providers['mistral']));
    assert_true(isset($providers['openai']));
    assert_true(isset($providers['anthropic']));
    assert_true(isset($providers['google']));
});

test('AC4_AGENT_MODEL_MAP — chaque agent a provider, model, max_tokens', function () use ($agentMap) {
    foreach (['cto','architect','designer','backend','frontend','qa','devops'] as $agent) {
        assert_true(isset($agentMap[$agent]), "Missing agent: $agent");
        assert_true(isset($agentMap[$agent]['provider']), "$agent: missing provider");
        assert_true(isset($agentMap[$agent]['model']), "$agent: missing model");
        assert_true($agentMap[$agent]['max_tokens'] > 0);
    }
});

test('PROVIDER_FALLBACK_ORDER — contient tous les providers', function () {
    $order = array_map('trim', explode(',', AC4_PROVIDER_FALLBACK_ORDER));
    foreach (['mistral','openai','anthropic','google'] as $p) assert_true(in_array($p, $order));
});

test('AC4_FRONTENDS — toutes les stacks ont label et icon', function () use ($frontends) {
    foreach ($frontends as $name => $cfg) {
        assert_true(isset($cfg['label']), "$name: missing label");
        assert_true(isset($cfg['icon']), "$name: missing icon");
    }
    assert_true(isset($frontends['html_css_js'], $frontends['react'], $frontends['vue']));
});

test('AC4_BACKENDS — tous les backends ont label et icon', function () use ($backends) {
    foreach ($backends as $name => $cfg) {
        assert_true(isset($cfg['label']), "$name: missing label");
        assert_true(isset($cfg['icon']), "$name: missing icon");
    }
    assert_true(isset($backends['node_express'], $backends['fastapi_python'], $backends['none']));
});

test('AC4_DATABASES — valeurs non vides', function () use ($databases) {
    foreach ($databases as $name => $label) assert_true(strlen($label) > 0);
});

test('AC4_CSS — chaque CSS a label et icon', function () use ($css) {
    foreach ($css as $name => $cfg) {
        assert_true(isset($cfg['label']), "$name: missing label");
        assert_true(isset($cfg['icon']), "$name: missing icon");
    }
});

test('AC4_STACKS — chaque stack a frontends, backends, databases, css', function () use ($stacks) {
    foreach (['fullstack','mobile','api','static'] as $name) {
        assert_true(isset($stacks[$name]["frontends"], $stacks[$name]["backends"], $stacks[$name]["databases"], $stacks[$name]["css"]));
    }
    assert_true(in_array('react', $stacks['fullstack']['frontends']));
    assert_true(in_array('next', $stacks['fullstack']['frontends']));
    assert_true(in_array('node_express', $stacks['fullstack']['backends']));
    assert_true(in_array('sqlite', $stacks['fullstack']['databases']));
});

test('AC4_LANGUAGES — contient fr, en, ar', function () {
    $langs = json_decode(AC4_LANGUAGES, true);
    assert_true(isset($langs['fr'], $langs['en'], $langs['ar']));
});

test('Chemins constants — tous valides', function () {
    assert_true(is_dir(AC4_ROOT));
    assert_true(is_dir(AC4_AGENTS_DIR));
    assert_true(is_dir(AC4_BUILDS_DIR));
});

printSummary('Résumé Config');
exit($failed > 0 ? 1 : 0);
