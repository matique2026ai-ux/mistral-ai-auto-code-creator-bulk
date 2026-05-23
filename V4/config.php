<?php
/**
 * AkrourCoder V4 — Configuration Globale
 * Architecte IA Full-Stack Multi-Stack
 */
define('AC4_VERSION', '4.0.0');
define('AC4_NAME', 'AkrourCoder');

// ─── IA — Providers ────────────────────────────────────────────────────────
define('AC4_MODEL',       'devstral-2512');
define('AC4_API_URL',     'https://api.mistral.ai/v1/chat/completions');
define('AC4_MAX_TOKENS',  32000);
define('AC4_MAX_KEY_ERRORS', 3);

define('AC4_PROVIDERS', json_encode([
    'mistral' => [
        'label' => 'Mistral AI',
        'icon'  => '🔮',
        'base_url' => 'https://api.mistral.ai/v1/chat/completions',
        'models' => ['devstral-2512', 'mistral-large-2411', 'mistral-small-2509', 'codestral-2505'],
        'default_model' => 'devstral-2512',
        'headers' => ['Authorization' => 'Bearer {key}', 'Content-Type' => 'application/json'],
    ],
    'openai' => [
        'label' => 'OpenAI',
        'icon'  => '🤖',
        'base_url' => 'https://api.openai.com/v1/chat/completions',
        'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o3-mini'],
        'default_model' => 'gpt-4o-mini',
        'headers' => ['Authorization' => 'Bearer {key}', 'Content-Type' => 'application/json'],
    ],
    'anthropic' => [
        'label' => 'Anthropic Claude',
        'icon'  => '🌿',
        'base_url' => 'https://api.anthropic.com/v1/messages',
        'models' => ['claude-sonnet-4-20250514', 'claude-haiku-3-5-20241022'],
        'default_model' => 'claude-sonnet-4-20250514',
        'headers' => ['x-api-key' => '{key}', 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json'],
    ],
    'google' => [
        'label' => 'Google Gemini',
        'icon'  => '🔬',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
        'models' => ['gemini-2.0-flash', 'gemini-2.5-pro-exp-03-25'],
        'default_model' => 'gemini-2.0-flash',
        'headers' => ['Content-Type' => 'application/json'],
    ],
]));

define('AC4_AGENT_MODEL_MAP', json_encode([
    'cto'       => ['provider' => 'mistral',    'model' => 'devstral-2512',     'max_tokens' => 16000],
    'architect' => ['provider' => 'mistral',    'model' => 'devstral-2512',     'max_tokens' => 16000],
    'designer'  => ['provider' => 'mistral',    'model' => 'devstral-2512',     'max_tokens' => 16000],
    'backend'   => ['provider' => 'mistral',    'model' => 'codestral-latest',    'max_tokens' => 32000],
    'frontend'  => ['provider' => 'mistral',    'model' => 'codestral-latest',    'max_tokens' => 32000],
    'qa'        => ['provider' => 'mistral',    'model' => 'devstral-2512',     'max_tokens' => 16000],
    'devops'    => ['provider' => 'mistral',    'model' => 'devstral-2512',     'max_tokens' => 16000],
]));

define('AC4_PROVIDER_FALLBACK_ORDER', 'mistral,openai,anthropic,google');

// ─── Pipeline ─────────────────────────────────────────────────────────────
define('AC4_MAX_ITERATIONS', 5);
define('AC4_QA_TARGET', 95);
define('AC4_RPS_SLEEP', 1200);

// ─── Chemins ──────────────────────────────────────────────────────────────
define('AC4_ROOT', __DIR__);
define('AC4_BUILDS_DIR', AC4_ROOT . DIRECTORY_SEPARATOR . 'builds');
define('AC4_BUILDS_WEB', 'builds');
define('AC4_AGENTS_DIR', AC4_ROOT . DIRECTORY_SEPARATOR . 'agents');
define('AC4_DB_FILE', AC4_ROOT . '/akrourcoder_v4.sqlite');

// ─── Technologies supportées ─────────────────────────────────────────────
define('AC4_STACKS', json_encode([
    'fullstack' => [
        'label' => 'Full-Stack Web',
        'icon' => '🌐',
        'frontends' => ['react', 'next', 'vue', 'nuxt', 'svelte', 'solid', 'qwik', 'angular', 'astro', 'remix'],
        'backends'  => ['node_express', 'express_typescript', 'nestjs', 'fastapi_python', 'laravel_php', 'django_python', 'go_gin', 'rust_actix'],
        'databases' => ['sqlite', 'postgresql', 'mysql', 'mongodb'],
        'css'       => ['tailwind', 'bootstrap', 'vanilla', 'chakra', 'styled_components'],
    ],
    'mobile' => [
        'label' => 'Mobile App',
        'icon' => '📱',
        'frontends' => ['flutter', 'react_native', 'kotlin', 'swiftui'],
        'backends'  => ['node_express', 'express_typescript', 'nestjs', 'fastapi_python', 'supabase', 'firebase'],
        'databases' => ['sqlite', 'postgresql', 'supabase'],
        'css'       => ['vanilla'],
    ],
    'api' => [
        'label' => 'API / Backend Only',
        'icon' => '⚡',
        'frontends' => ['none'],
        'backends'  => ['node_express', 'express_typescript', 'nestjs', 'fastapi_python', 'laravel_php', 'django_python', 'go_gin', 'rust_actix'],
        'databases' => ['sqlite', 'postgresql', 'mysql', 'mongodb'],
        'css'       => ['none'],
    ],
    'static' => [
        'label' => 'Site Statique',
        'icon' => '📄',
        'frontends' => ['react', 'vue', 'svelte', 'solid', 'qwik', 'astro', 'html_css_js'],
        'backends'  => ['none'],
        'databases' => ['none'],
        'css'       => ['tailwind', 'bootstrap', 'vanilla'],
    ],
]));

define('AC4_FRONTENDS', json_encode([
    'react'           => ['label' => 'React + Vite', 'icon' => '⚛️', 'ext' => 'jsx', 'dir' => 'src/'],
    'next'            => ['label' => 'Next.js 14', 'icon' => '▲', 'ext' => 'tsx', 'dir' => 'app/'],
    'vue'             => ['label' => 'Vue 3 + Vite', 'icon' => '💚', 'ext' => 'vue', 'dir' => 'src/'],
    'nuxt'            => ['label' => 'Nuxt 3', 'icon' => '🍃', 'ext' => 'vue', 'dir' => 'pages/'],
    'svelte'          => ['label' => 'SvelteKit', 'icon' => '🧡', 'ext' => 'svelte', 'dir' => 'src/'],
    'solid'           => ['label' => 'SolidJS', 'icon' => '🔵', 'ext' => 'jsx', 'dir' => 'src/'],
    'qwik'            => ['label' => 'Qwik', 'icon' => '⚡', 'ext' => 'tsx', 'dir' => 'src/'],
    'angular'         => ['label' => 'Angular 17', 'icon' => '🔺', 'ext' => 'ts', 'dir' => 'src/'],
    'astro'           => ['label' => 'Astro', 'icon' => '🚀', 'ext' => 'astro', 'dir' => 'src/'],
    'remix'           => ['label' => 'Remix', 'icon' => '💿', 'ext' => 'tsx', 'dir' => 'app/'],
    'flutter'         => ['label' => 'Flutter', 'icon' => '🦋', 'ext' => 'dart', 'dir' => 'lib/'],
    'react_native'    => ['label' => 'React Native', 'icon' => '📱', 'ext' => 'tsx', 'dir' => 'src/'],
    'kotlin'          => ['label' => 'Android Kotlin', 'icon' => '🤖', 'ext' => 'kt', 'dir' => 'app/src/main/java/'],
    'swiftui'         => ['label' => 'SwiftUI', 'icon' => '🍎', 'ext' => 'swift', 'dir' => ''],
    'html_css_js'     => ['label' => 'HTML/CSS/JS Pur', 'icon' => '🌍', 'ext' => 'html', 'dir' => ''],
    'none'            => ['label' => 'Aucun', 'icon' => '', 'ext' => '', 'dir' => ''],
]));

define('AC4_BACKENDS', json_encode([
    'node_express'        => ['label' => 'Node.js + Express', 'icon' => '🟢', 'ext' => 'js'],
    'express_typescript'  => ['label' => 'Express + TypeScript', 'icon' => '🔷', 'ext' => 'ts'],
    'nestjs'              => ['label' => 'NestJS', 'icon' => '🦁', 'ext' => 'ts'],
    'fastapi_python'      => ['label' => 'Python FastAPI', 'icon' => '🐍', 'ext' => 'py'],
    'laravel_php'         => ['label' => 'Laravel PHP', 'icon' => '🔥', 'ext' => 'php'],
    'django_python'       => ['label' => 'Django Python', 'icon' => '🎸', 'ext' => 'py'],
    'go_gin'              => ['label' => 'Go + Gin', 'icon' => '🔵', 'ext' => 'go'],
    'rust_actix'          => ['label' => 'Rust + Actix', 'icon' => '🦀', 'ext' => 'rs'],
    'supabase'            => ['label' => 'Supabase BaaS', 'icon' => '⚡', 'ext' => 'js'],
    'firebase'            => ['label' => 'Firebase BaaS', 'icon' => '🔥', 'ext' => 'js'],
    'none'                => ['label' => 'Aucun', 'icon' => '', 'ext' => ''],
]));

define('AC4_DATABASES', json_encode([
    'sqlite'    => 'SQLite',
    'postgresql'=> 'PostgreSQL',
    'mysql'     => 'MySQL',
    'mongodb'   => 'MongoDB',
    'supabase'  => 'Supabase',
    'none'      => 'Aucune',
]));

define('AC4_CSS', json_encode([
    'tailwind'          => ['label' => 'Tailwind CSS', 'icon' => '🌊'],
    'bootstrap'         => ['label' => 'Bootstrap 5', 'icon' => '🅱️'],
    'vanilla'           => ['label' => 'Vanilla CSS (Premium)', 'icon' => '🎨'],
    'chakra'            => ['label' => 'Chakra UI', 'icon' => '🌈'],
    'styled_components' => ['label' => 'Styled Components', 'icon' => '💅'],
    'none'              => ['label' => 'Aucun', 'icon' => ''],
]));

define('AC4_LANGUAGES', json_encode([
    'fr' => 'Français',
    'en' => 'English',
    'ar' => 'العربية',
    'es' => 'Español',
    'de' => 'Deutsch',
    'pt' => 'Português',
    'zh' => '中文',
    'ja' => '日本語',
]));
