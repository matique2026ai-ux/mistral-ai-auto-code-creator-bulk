<?php
/**
 * AutoCoder V4 — Pipeline Engine
 * Orchestrateur multi-agents : CTO → Architect → Designer → Backend → Frontend → QA → DevOps
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class PipelineEngine {
    private PDO $db;
    private int $projectId;
    private string $projectFolder;
    private string $currentKey = '';
    private int $currentKeyId = 0;
    private array $generatedFiles = [];
    private array $state = [];

    public function __construct() {
        $this->db = getDB();
    }

    // ─── API Mistral ──────────────────────────────────────────────────

    private function callAI(array $messages, int $maxTokens = 4000, bool $jsonMode = true, string $step = ''): array {
        $key = $this->getKey();
        $payload = [
            'model' => AC4_MODEL,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];
        if ($jsonMode) $payload['response_format'] = ['type' => 'json_object'];

        $ch = curl_init(AC4_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->currentKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            markKeyError($this->db, $this->currentKeyId);
            $this->getKey(); // retry with next key
            $this->log('err', "HTTP $code - key rotated");
            return $this->callAI($messages, $maxTokens, $jsonMode, $step); // retry
        }

        $data = json_decode($resp, true);
        $tokens = $data['usage']['total_tokens'] ?? 0;
        recordTokens($this->db, $this->currentKeyId, $tokens, $this->projectId, $step);

        return [
            'content' => $data['choices'][0]['message']['content'],
            'tokens' => $tokens,
        ];
    }

    private function getKey(): array {
        $k = getNextApiKey($this->db);
        if (!$k) throw new Exception('Aucune clé API disponible');
        $this->currentKey = $k['key_val'];
        $this->currentKeyId = $k['id'];
        return $k;
    }

    // ─── Logging ──────────────────────────────────────────────────────

    private function log(string $level, string $message): void {
        $step = debug_backtrace()[1]['function'] ?? 'engine';
        appendLog($this->db, $this->projectId, $step, $level, $message);
        echo json_encode(['type' => 'log', 'level' => $level, 'step' => $step, 'message' => $message]) . "\n";
        if (ob_get_level()) ob_flush(); flush();
    }

    private function progress(int $pct, string $label): void {
        echo json_encode(['type' => 'progress', 'pct' => $pct, 'label' => $label]) . "\n";
        if (ob_get_level()) ob_flush(); flush();
    }

    // ─── JSON Parser robuste ──────────────────────────────────────────

    private function parseJSON(string $raw): array {
        $str = trim($raw);
        $str = preg_replace('/^```(?:json)?\s*/i', '', $str);
        $str = preg_replace('/\s*```$/i', '', $str);
        try { return json_decode($str, true, 512, JSON_THROW_ON_ERROR); } catch (\Exception) {}
        $start = strpos($str, '{');
        $end = strrpos($str, '}');
        if ($start !== false && $end !== false) {
            try { return json_decode(substr($str, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR); } catch (\Exception) {}
        }
        throw new Exception('JSON invalide: ' . substr($str, 0, 200));
    }

    // ─── File Writer ──────────────────────────────────────────────────

    private function writeFile(string $filename, string $content): void {
        $fullPath = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder) . DIRECTORY_SEPARATOR . $filename;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($fullPath, $content);

        $this->generatedFiles[] = $filename;
        $this->log('write', "✓ $filename (" . strlen($content) . " octets)");

        $this->db->prepare("INSERT INTO generated_files (project_id, filepath, language, size, status) VALUES (?,?,?,?,?)")
            ->execute([$this->projectId, $filename, pathinfo($filename, PATHINFO_EXTENSION), strlen($content), 'written']);
    }

    private function loadAgentPrompt(string $name): string {
        $path = AC4_AGENTS_DIR . DIRECTORY_SEPARATOR . $name . '.md';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PIPELINE PRINCIPAL
    // ═══════════════════════════════════════════════════════════════════

    public function run(array $brief): array {
        $this->projectId = $brief['project_id'];
        $this->projectFolder = $brief['folder'];

        $this->log('sys', '═══════════════════════════════════════════════════');
        $this->log('sys', '🤖 AutoCoder V4 — 7 AGENTS SPÉCIALISÉS PRÊTS');
        $this->log('sys', '═══════════════════════════════════════════════════');

        try {
            // ── ÉTAPE 1: CTO — Choix de la stack ──────────────────────
            $this->progress(5, 'CTO : Analyse du besoin & sélection de la stack...');
            $this->log('ai', 'Agent CTO : Analyse du projet...');
            $stackDecision = $this->runCTO($brief);

            $stackChoiceUpdate = [
                'project_type' => $stackDecision['analysis']['project_type'],
                'frontend'     => $stackDecision['stack_decision']['frontend'],
                'backend'      => $stackDecision['stack_decision']['backend'],
                'database'     => $stackDecision['stack_decision']['database'],
                'css_framework'=> $stackDecision['stack_decision']['css_framework'],
                'stack_choice' => json_encode($stackDecision),
            ];
            // Update title from CTO analysis if master prompt mode
            if (!empty($stackDecision['analysis']['extracted_title'])) {
                $stackChoiceUpdate['title'] = $stackDecision['analysis']['extracted_title'];
            }
            updateProject($this->db, $this->projectId, $stackChoiceUpdate);
            $this->log('ok', "Stack choisie : {$stackDecision['stack_decision']['frontend']} + {$stackDecision['stack_decision']['backend']} + {$stackDecision['stack_decision']['database']}");

            // ── ÉTAPE 2: Architecte — Architecture détaillée ──────────
            $this->progress(15, 'Architecte : Conception de l\'architecture...');
            $this->log('ai', 'Agent Architecte : Design du système...');
            $architecture = $this->runArchitect($brief, $stackDecision);
            updateProject($this->db, $this->projectId, ['arch_json' => json_encode($architecture)]);
            $this->log('ok', "Architecture : {$architecture['site_name']} — " . count($architecture['frontend_pages']) . " pages, " . count($architecture['api_endpoints']) . " API");

            // ── ÉTAPE 3: Designer — Design System ─────────────────────
            $this->progress(28, 'Designer : Création du design system premium...');
            $this->log('ai', 'Agent Designer : Design du système visuel...');
            $designSystem = $this->runDesigner($brief, $stackDecision, $architecture);
            $this->log('ok', 'Design system créé — composants, couleurs, animations');

            // ── ÉTAPE 4: Backend — Génération du backend ─────────────
            $this->progress(40, 'Développeur Backend : Génération des APIs...');
            $this->log('ai', 'Agent Backend : Génération du code serveur...');
            $backendResult = $this->runBackend($brief, $stackDecision, $architecture, $designSystem);

            $backendFiles = $backendResult['files'] ?? [];
            $configFiles = $backendResult['config_files'] ?? [];
            foreach ($backendFiles as $f) $this->writeFile($f['filename'], $f['content']);
            foreach ($configFiles as $f) $this->writeFile($f['filename'], $f['content']);
            $this->log('ok', 'Backend généré : ' . (count($backendFiles) + count($configFiles)) . " fichiers");

            // ── ÉTAPE 5: Frontend — Génération du frontend ───────────
            $this->progress(58, 'Développeur Frontend : Génération des interfaces...');
            $this->log('ai', 'Agent Frontend : Génération du code UI...');
            $frontendResult = $this->runFrontend($brief, $stackDecision, $architecture, $designSystem, $backendResult);

            foreach (($frontendResult['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
            $this->log('ok', 'Frontend généré : ' . count($frontendResult['files'] ?? []) . " fichiers");

            // ── ÉTAPE 6: QA — Validation du code ─────────────────────
            $this->progress(75, 'QA Engineer : Inspection qualité complète...');
            $this->log('test', 'Agent QA : Validation du code...');

            $allFiles = array_merge($backendFiles, $configFiles, $frontendResult['files'] ?? []);
            $qaResult = $this->runQA($brief, $stackDecision, $allFiles);

            $score = $qaResult['overall_score'] ?? 0;
            $this->log('test', "Score qualité : $score/100");
            $this->log('test', "Problèmes : " . count($qaResult['issues'] ?? []) . ", Corrections : " . count($qaResult['fixes'] ?? []));

            // Appliquer les corrections QA
            foreach (($qaResult['fixes'] ?? []) as $fix) {
                if (!empty($fix['file']) && !empty($fix['content'])) {
                    $this->writeFile($fix['file'], $fix['content']);
                    $this->log('heal', "🔧 Correction appliquée : {$fix['file']}");
                }
            }

            updateProject($this->db, $this->projectId, [
                'qa_score' => $score,
                'file_count' => count($allFiles),
            ]);

            // ── ÉTAPE 7: DevOps — Déploiement ────────────────────────
            $this->progress(90, 'DevOps : Préparation du déploiement...');
            $this->log('ai', 'Agent DevOps : Configuration de l\'infrastructure...');
            $devopsResult = $this->runDevOps($brief, $stackDecision, $architecture);

            foreach (($devopsResult['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
            $this->log('ok', 'DevOps prêt : Docker + CI/CD générés');

            // ── FINALISATION ─────────────────────────────────────────
            $this->generateReadme($architecture, $stackDecision, $score);
            updateProject($this->db, $this->projectId, ['status' => 'done']);

            $this->progress(100, '✅ Projet terminé !');
            $this->log('ok', '═══════════════════════════════════════════════════');
            $this->log('ok', "✅ PROJET TERMINÉ — Score QA : $score/100");
            $this->log('ok', "📁 Dossier : builds/" . basename($this->projectFolder));
            $this->log('ok', "📦 Fichiers générés : " . count($this->generatedFiles));
            $this->log('ok', '═══════════════════════════════════════════════════');

            return [
                'success' => true,
                'project_id' => $this->projectId,
                'files' => count($this->generatedFiles),
                'qa_score' => $score,
                'folder' => $this->projectFolder,
            ];

        } catch (\Exception $e) {
            $this->log('err', '❌ Pipeline interrompu : ' . $e->getMessage());
            updateProject($this->db, $this->projectId, ['status' => 'failed']);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── Agent CTO ────────────────────────────────────────────────

    private function runCTO(array $brief): array {
        $prompt = $this->loadAgentPrompt('cto');

        $hasMasterPrompt = !empty($brief['master_prompt']);
        $userContent = [];

        if ($hasMasterPrompt) {
            $this->log('ai', 'CTO analyse le Master Prompt pour choisir la stack optimale...');
            $userContent = [
                'mode' => 'master_prompt',
                'master_prompt' => $brief['master_prompt'],
                'user_explicit_stack' => [
                    'frontend' => $brief['frontend'] ?? '',
                    'backend' => $brief['backend'] ?? '',
                    'database' => $brief['database'] ?? '',
                    'css_framework' => $brief['css_framework'] ?? '',
                    'project_type' => $brief['project_type'] ?? '',
                ],
                'available_frontends' => array_keys(json_decode(AC4_FRONTENDS, true)),
                'available_backends' => array_keys(json_decode(AC4_BACKENDS, true)),
                'available_databases' => array_keys(json_decode(AC4_DATABASES, true)),
                'available_css' => array_keys(json_decode(AC4_CSS, true)),
                'available_types' => array_keys(json_decode(AC4_STACKS, true)),
            ];
            $this->log('sys', '🔍 CTO examine le prompt pour déterminer : type, frontend, backend, BDD, CSS...');
        } else {
            $userContent = [
                'mode' => 'explicit',
                'title' => $brief['title'] ?? '',
                'who' => $brief['who'] ?? '',
                'target' => $brief['target'] ?? '',
                'monetize' => $brief['monetize'] ?? '',
                'project_type' => $brief['project_type'] ?? 'fullstack',
                'frontend' => $brief['frontend'] ?? 'next',
                'backend' => $brief['backend'] ?? 'node_express',
                'database' => $brief['database'] ?? 'sqlite',
                'css_framework' => $brief['css_framework'] ?? 'tailwind',
                'available_frontends' => array_keys(json_decode(AC4_FRONTENDS, true)),
                'available_backends' => array_keys(json_decode(AC4_BACKENDS, true)),
                'available_databases' => array_keys(json_decode(AC4_DATABASES, true)),
                'available_css' => array_keys(json_decode(AC4_CSS, true)),
            ];
        }

        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode($userContent)],
        ];
        $decision = $this->callWithRetry($messages, 16000, true, 'cto');

        // If user provided explicit values in advanced mode, override AI choices
        if ($hasMasterPrompt && !empty($brief['frontend'])) {
            $decision['stack_decision']['frontend'] = $brief['frontend'];
            $decision['stack_decision']['backend'] = $brief['backend'] ?: $decision['stack_decision']['backend'];
            $decision['stack_decision']['database'] = $brief['database'] ?: $decision['stack_decision']['database'];
            $decision['stack_decision']['css_framework'] = $brief['css_framework'] ?: $decision['stack_decision']['css_framework'];
            $decision['analysis']['project_type'] = $brief['project_type'] ?: $decision['analysis']['project_type'];
            $this->log('ok', 'Stack personnalisée par l\'utilisateur appliquée');
        }

        return $decision;
    }

    // ─── Agent Architecte ──────────────────────────────────────────

    private function runArchitect(array $brief, array $stack): array {
        $prompt = $this->loadAgentPrompt('architect');
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode([
                'brief' => $brief,
                'stack' => $stack,
            ])],
        ];
        return $this->callWithRetry($messages, 16000, true, 'architect');
    }

    // ─── Agent Designer ───────────────────────────────────────────

    private function runDesigner(array $brief, array $stack, array $arch): array {
        $prompt = $this->loadAgentPrompt('designer');
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode([
                'brief' => $brief,
                'stack' => $stack,
                'architecture' => $arch,
            ])],
        ];
        return $this->callWithRetry($messages, 16000, true, 'designer');
    }

    private function callWithRetry(array $messages, int $maxTokens, bool $jsonMode, string $step, int $attempts = 2): array {
        for ($i = 0; $i < $attempts; $i++) {
            $tokens = $maxTokens + ($i * 2000);
            if ($i > 0) $this->log('warn', "🔄 Nouvelle tentative ($step) avec {$tokens} tokens...");
            try {
                $resp = $this->callAI($messages, $tokens, $jsonMode, $step);
                return $this->parseJSON($resp['content']);
            } catch (\Exception $e) {
                if ($i === $attempts - 1) throw $e;
                $this->log('warn', "Tentative $step échouée : " . $e->getMessage());
            }
        }
        throw new Exception("Échec après $attempts tentatives pour $step");
    }

    // ─── Agent Backend ────────────────────────────────────────────

    private function runBackend(array $brief, array $stack, array $arch, array $design): array {
        $prompt = $this->loadAgentPrompt('backend');
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode([
                'brief' => $brief,
                'stack' => $stack,
                'architecture' => $arch,
                'design_system' => $design,
                'tables' => $arch['database_schema']['tables'] ?? [],
                'endpoints' => $arch['api_endpoints'] ?? [],
            ])],
        ];
        return $this->callWithRetry($messages, 32000, true, 'backend');
    }

    // ─── Agent Frontend (fichier par fichier) ────────────────────

    private function runFrontend(array $brief, array $stack, array $arch, array $design, array $backend): array {
        // Step 1: Plan — liste des fichiers uniquement
        $this->log('ai', 'Frontend: Planification des fichiers...');
        $planMessages = [
            ['role' => 'system', 'content' => 'Tu listes les fichiers nécessaires pour un site Next.js. Réponse JSON : {"files":[{"filename":"...","description":"..."}]}. AUCUN CODE.'],
            ['role' => 'user', 'content' => json_encode([
                'project' => $brief['master_prompt'] ?? $brief['title'] ?? '',
                'stack' => $stack['stack_decision'] ?? [],
                'pages' => $arch['frontend_pages'] ?? [],
            ])],
        ];
        $plan = $this->callWithRetry($planMessages, 1500, true, 'frontend-plan');
        $fileList = $plan['files'] ?? [];
        if (empty($fileList)) throw new Exception('Frontend: plan vide');
        $this->log('ok', 'Frontend: ' . count($fileList) . ' fichiers à générer');

        // Step 2: Génération fichier par fichier
        $prompt = $this->loadAgentPrompt('frontend');
        $allFiles = [];
        $total = count($fileList);
        foreach ($fileList as $i => $fileDef) {
            $this->progress(55 + intval(20 * $i / $total), "Frontend: fichier " . ($i + 1) . "/{$total}");
            $filename = $fileDef['filename'] ?? "f{$i}.tsx";
            $this->log('ai', "Frontend [{$i}/{$total}]: {$filename}...");
            $fileMsg = [
                ['role' => 'system', 'content' => $prompt . "\n\nGénère UNIQUEMENT le fichier {$filename}. Réponse JSON : {\"filename\":\"{$filename}\",\"content\":\"...\",\"language\":\"...\"}"],
                ['role' => 'user', 'content' => json_encode([
                    'brief' => $brief, 'stack' => $stack, 'architecture' => $arch,
                    'design_system' => $design, 'backend' => $backend,
                    'pages' => $arch['frontend_pages'] ?? [],
                    'file_to_generate' => $filename,
                    'all_planned_files' => array_column($fileList, 'filename'),
                ])],
            ];
            $res = $this->callWithRetry($fileMsg, 4000, true, 'frontend-file');
            $fn = $res['filename'] ?? $filename;
            $content = $res['content'] ?? '';
            if ($content) $allFiles[] = ['filename' => $fn, 'content' => $content];
            usleep(200000);
        }
        if (empty($allFiles)) throw new Exception('Frontend: aucun fichier généré');
        $this->log('ok', 'Frontend généré : ' . count($allFiles) . ' fichiers');
        return ['files' => $allFiles];
    }

    // ─── Agent QA ─────────────────────────────────────────────────

    private function runQA(array $brief, array $stack, array $allFiles): array {
        $prompt = $this->loadAgentPrompt('qa');

        // Build a compact summary of all files for the QA agent
        $filesSummary = [];
        foreach ($allFiles as $f) {
            $filesSummary[] = [
                'filename' => $f['filename'] ?? 'unknown',
                'language' => $f['language'] ?? 'unknown',
                'size' => strlen($f['content'] ?? ''),
                'preview' => substr($f['content'] ?? '', 0, 500),
            ];
        }

        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode([
                'brief' => $brief,
                'stack' => $stack,
                'files' => $filesSummary,
            ])],
        ];
        return $this->callWithRetry($messages, 16000, true, 'qa');
    }

    // ─── Agent DevOps ─────────────────────────────────────────────

    private function runDevOps(array $brief, array $stack, array $arch): array {
        $prompt = $this->loadAgentPrompt('devops');
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode([
                'brief' => $brief,
                'stack' => $stack,
                'architecture' => $arch,
                'frontend' => $stack['stack_decision']['frontend'] ?? 'react',
                'backend' => $stack['stack_decision']['backend'] ?? 'node_express',
                'database' => $stack['stack_decision']['database'] ?? 'sqlite',
            ])],
        ];
        return $this->callWithRetry($messages, 16000, true, 'devops');
    }

    // ─── README ───────────────────────────────────────────────────

    private function generateReadme(array $arch, array $stack, int $score): void {
        $dec = $stack['stack_decision'] ?? [];
        $content = "# {$arch['site_name']}\n\n"
            . "## Concept\n{$arch['site_concept']}\n\n"
            . "## Stack Technique\n"
            . "- Frontend : {$dec['frontend']}\n"
            . "- Backend : {$dec['backend']}\n"
            . "- Base de données : {$dec['database']}\n"
            . "- CSS : {$dec['css_framework']}\n\n"
            . "## Pages\n"
            . implode("\n", array_map(fn($p) => "- **{$p['route']}** — {$p['title']}: {$p['description']}", $arch['frontend_pages'] ?? [])) . "\n\n"
            . "## API\n"
            . implode("\n", array_map(fn($e) => "- `{$e['method']} {$e['path']}` — {$e['description']}", $arch['api_endpoints'] ?? [])) . "\n\n"
            . "## Généré par AutoCoder V4\n"
            . "- Modèle : " . AC4_MODEL . "\n"
            . "- Version : " . AC4_VERSION . "\n"
            . "- Date : " . date('Y-m-d H:i:s') . "\n"
            . "- Score QA : $score/100\n";

        $this->writeFile('README.md', $content);
    }
}
