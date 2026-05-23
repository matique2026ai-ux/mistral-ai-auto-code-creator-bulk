<?php
/**
 * AutoCoder V4 — Configuration Globale
 * Architecte IA Full-Stack Multi-Stack
 */
define('AC4_VERSION', '4.0.0');
define('AC4_NAME', 'AutoCoder');

// ─── IA ───────────────────────────────────────────────────────────────────
define('AC4_MODEL',       'devstral-2512');
define('AC4_API_URL',     'https://api.mistral.ai/v1/chat/completions');
define('AC4_MAX_TOKENS',  8000);
define('AC4_MAX_KEY_ERRORS', 3);

// ─── Pipeline ─────────────────────────────────────────────────────────────
define('AC4_MAX_ITERATIONS', 5);
define('AC4_QA_TARGET', 95);
define('AC4_RPS_SLEEP', 1200);

// ─── Chemins ──────────────────────────────────────────────────────────────
define('AC4_ROOT', __DIR__);
define('AC4_BUILDS_DIR', AC4_ROOT . DIRECTORY_SEPARATOR . 'builds');
define('AC4_BUILDS_WEB', 'builds');
define('AC4_AGENTS_DIR', AC4_ROOT . DIRECTORY_SEPARATOR . 'agents');
define('AC4_DB_FILE', AC4_ROOT . '/autocoder_v4.sqlite');

// ─── Technologies supportées ─────────────────────────────────────────────
define('AC4_STACKS', json_encode([
    'fullstack' => [
        'label' => 'Full-Stack Web',
        'icon' => '🌐',
        'frontends' => ['react', 'next', 'vue', 'nuxt', 'svelte', 'angular', 'astro', 'remix'],
        'backends'  => ['node_express', 'fastapi_python', 'laravel_php', 'django_python', 'go_gin', 'rust_actix'],
        'databases' => ['sqlite', 'postgresql', 'mysql', 'mongodb'],
        'css'       => ['tailwind', 'bootstrap', 'vanilla', 'chakra', 'styled_components'],
    ],
    'mobile' => [
        'label' => 'Mobile App',
        'icon' => '📱',
        'frontends' => ['flutter', 'react_native', 'kotlin', 'swiftui'],
        'backends'  => ['node_express', 'fastapi_python', 'supabase', 'firebase'],
        'databases' => ['sqlite', 'postgresql', 'supabase'],
        'css'       => ['vanilla'],
    ],
    'api' => [
        'label' => 'API / Backend Only',
        'icon' => '⚡',
        'frontends' => ['none'],
        'backends'  => ['node_express', 'fastapi_python', 'laravel_php', 'django_python', 'go_gin', 'rust_actix'],
        'databases' => ['sqlite', 'postgresql', 'mysql', 'mongodb'],
        'css'       => ['none'],
    ],
    'static' => [
        'label' => 'Site Statique',
        'icon' => '📄',
        'frontends' => ['react', 'vue', 'svelte', 'astro', 'html_css_js'],
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
    'node_express'    => ['label' => 'Node.js + Express', 'icon' => '🟢', 'ext' => 'js'],
    'fastapi_python'  => ['label' => 'Python FastAPI', 'icon' => '🐍', 'ext' => 'py'],
    'laravel_php'     => ['label' => 'Laravel PHP', 'icon' => '🔥', 'ext' => 'php'],
    'django_python'   => ['label' => 'Django Python', 'icon' => '🎸', 'ext' => 'py'],
    'go_gin'          => ['label' => 'Go + Gin', 'icon' => '🔵', 'ext' => 'go'],
    'rust_actix'      => ['label' => 'Rust + Actix', 'icon' => '🦀', 'ext' => 'rs'],
    'supabase'        => ['label' => 'Supabase BaaS', 'icon' => '⚡', 'ext' => 'js'],
    'firebase'        => ['label' => 'Firebase BaaS', 'icon' => '🔥', 'ext' => 'js'],
    'none'            => ['label' => 'Aucun', 'icon' => '', 'ext' => ''],
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
