<?php
/**
 * AutoCoder V3 — Global Configuration
 * Central place for all settings. Edit here to customize behaviour.
 */

// ─── AI Model ──────────────────────────────────────────────────────────────
define('AC_MODEL',          'devstral-2512');          // Mistral model to use
define('AC_MISTRAL_API',    'https://api.mistral.ai/v1/chat/completions');
define('AC_MAX_KEY_ERRORS', 3);                        // Auto-disable key after N errors
define('AC_TOKEN_LIMIT',    50000);                    // TPM limit display

// ─── Pipeline ──────────────────────────────────────────────────────────────
define('AC_PAGES_MIN',      3);                        // Min pages to generate
define('AC_PAGES_MAX',      6);                        // Max pages to generate
define('AC_RPS_SLEEP_MS',   1200);                     // Sleep between API calls (ms)

// ─── Paths ─────────────────────────────────────────────────────────────────
define('AC_BUILDS_DIR',     __DIR__ . DIRECTORY_SEPARATOR . 'builds');
define('AC_BUILDS_WEB',     'builds');                 // Web-accessible path prefix

// ─── Database ──────────────────────────────────────────────────────────────
define('AC_DB_FILE',        __DIR__ . '/autocoder_v3.sqlite');

// ─── Site Types ────────────────────────────────────────────────────────────
define('AC_SITE_TYPES', json_encode([
    'saas'      => ['label' => 'SaaS / Web App',     'icon' => '⚡', 'pages' => ['index','features','pricing','login','contact']],
    'blog'      => ['label' => 'Blog / Magazine',    'icon' => '📝', 'pages' => ['index','blog','article','about','contact']],
    'store'     => ['label' => 'E-Commerce Store',   'icon' => '🛒', 'pages' => ['index','shop','product','cart','contact']],
    'portfolio' => ['label' => 'Portfolio / Agency', 'icon' => '🎨', 'pages' => ['index','work','about','services','contact']],
    'landing'   => ['label' => 'Landing Page',       'icon' => '🚀', 'pages' => ['index','features','testimonials','pricing','faq']],
    'corporate' => ['label' => 'Corporate / Business','icon' => '🏢', 'pages' => ['index','about','services','team','contact']],
]));

// ─── CSS Frameworks for generated sites ────────────────────────────────────
define('AC_CSS_FRAMEWORKS', json_encode([
    'vanilla'   => ['label' => 'Vanilla CSS',   'desc' => 'Pure custom CSS, zero dependencies'],
    'bootstrap' => ['label' => 'Bootstrap 5',   'desc' => 'Most popular CSS framework'],
    'tailwind'  => ['label' => 'Tailwind CDN',  'desc' => 'Utility-first CSS via CDN'],
]));

// ─── Output Languages ──────────────────────────────────────────────────────
define('AC_LANGUAGES', json_encode([
    'en' => 'English',
    'ar' => 'العربية',
    'fr' => 'Français',
    'es' => 'Español',
    'de' => 'Deutsch',
]));

// ─── App Info ──────────────────────────────────────────────────────────────
define('AC_VERSION', '3.0.0');
define('AC_NAME',    'AutoCoder');
