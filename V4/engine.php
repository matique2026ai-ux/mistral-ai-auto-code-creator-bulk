<?php
/**
 * AkrourCoder V4 — Pipeline Engine
 * Orchestrateur multi-agents : CTO → Architect → Designer → Backend → Frontend → QA → DevOps
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class PipelineEngine {
    private PDO $db;
    private int $projectId;
    private string $projectFolder;
    private string $currentKey = '';
    private int $currentKeyId = 0;
    private array $generatedFiles = [];
    private array $state = [];
    private array $searchCache = [];

    public function __construct() {
        ini_set('memory_limit', '512M');
        $this->db = getDB();
    }

    // ─── API Mistral ──────────────────────────────────────────────────

    private function callAI(array $messages, int $maxTokens = 4000, bool $jsonMode = true, string $step = '', int $depth = 0): array {
        $key = $this->getKey();
        $agentMap = json_decode(AC4_AGENT_MODEL_MAP, true);
        $model = ($agentMap[$step]['model'] ?? false) ?: AC4_MODEL;
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];
        if ($jsonMode) $payload['response_format'] = ['type' => 'json_object'];

        $ch = curl_init(AC4_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->currentKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $this->log('err', "HTTP $code - " . ($resp ? substr($resp, 0, 300) : 'empty') . " (depth $depth)");
            if ($depth >= 3) throw new Exception("API HTTP $code après $depth tentatives ($step)");
            if ($code === 429) {
                $wait = min(30 * ($depth + 1), 120);
                $this->log('warn', "Rate limit, attente {$wait}s...");
                sleep($wait);
                return $this->callAI($messages, $maxTokens, $jsonMode, $step, $depth + 1);
            }
            markKeyError($this->db, $this->currentKeyId);
            return $this->callAI($messages, $maxTokens, $jsonMode, $step, $depth + 1);
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
        // Store progress in DB so polling UI can read it
        appendLog($this->db, $this->projectId, 'progress', 'sys', "pct:{$pct} label:{$label}");
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

    // ─── Mémoire & Recherche pour agents ─────────────────────────

    private function enrichContext(string $agentName, array $baseContext): array {
        $memories = getMemories($this->db, $agentName, 3);
        if (!empty($memories)) {
            $this->log('sys', "🧠 $agentName consulte ses expériences passées...");
            $baseContext['past_memories'] = [];
            foreach ($memories as $m) {
                $baseContext['past_memories'][] = [
                    'project' => $m['project_title'],
                    'key_point' => $m['key_point'],
                    'summary' => $m['summary'],
                ];
            }
        }

        $searchQuery = $this->buildSearchQuery($agentName, $baseContext);
        if ($searchQuery) {
            if (isset($this->searchCache[$searchQuery])) {
                $webResults = $this->searchCache[$searchQuery];
                $this->log('sys', "🔍 $agentName utilise le cache web : \"$searchQuery\"");
            } else {
                $this->log('sys', "🔍 $agentName recherche sur le web : \"$searchQuery\"...");
                $webResults = webSearch($searchQuery, 3);
                $this->searchCache[$searchQuery] = $webResults;
            }
            if (!empty($webResults)) {
                $baseContext['web_research'] = $webResults;
            }
        }

        return $baseContext;
    }

    private function buildSearchQuery(string $agent, array $context): string {
        $title = $context['brief']['title'] ?? $context['brief']['master_prompt'] ?? $context['title'] ?? $context['master_prompt'] ?? '';
        if (is_array($title)) $title = json_encode($title);
        $title = substr(strip_tags($title), 0, 100);
        $queries = [
            'cto'       => "best tech stack for " . ($title ?: "web project") . " 2025 2026 trends",
            'architect' => "best architecture patterns for " . ($title ?: "web application"),
            'designer'  => "modern UI design trends 2025 2026 " . ($title ?: "website") . " award winning",
            'backend'   => "backend best practices " . ($title ?: "API") . " 2025",
            'frontend'  => "modern frontend animations UI trends 2025 " . ($title ?: "website"),
            'qa'        => "common code quality issues web development 2025",
            'devops'    => "Docker CI CD best practices 2025",
        ];
        return $queries[$agent] ?? '';
    }

    private function storeAgentMemory(string $agent, string $keyPoint, string $summary): void {
        storeMemory($this->db, $this->projectId, $agent, $keyPoint, $summary);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PIPELINE PRINCIPAL
    // ═══════════════════════════════════════════════════════════════════

    public function runJob(int $projectId, string $jobName): array {
        $this->projectId = $projectId;
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        if (!$project) throw new \Exception("Project #$projectId not found");
        $this->projectFolder = $project['folder'];

        $brief = @json_decode($project['brief'], true) ?: [];
        $brief['title'] = $project['title'];
        $brief['project_id'] = $project['id'];
        $brief['folder'] = $project['folder'];
        $brief['frontend'] = $project['frontend'];
        $brief['backend'] = $project['backend'];
        $brief['database'] = $project['database'];
        $brief['css_framework'] = $project['css_framework'];
        $brief['project_type'] = $project['project_type'];

        $stackDecision = @json_decode($project['stack_choice'], true) ?: [
            'analysis' => ['project_type' => $project['project_type']],
            'stack_decision' => [
                'frontend' => $project['frontend'],
                'backend' => $project['backend'],
                'database' => $project['database'],
                'css_framework' => $project['css_framework'],
            ],
        ];
        $architecture = @json_decode($project['arch_json'], true) ?: [];
        $designSystem = @json_decode($project['design_json'], true) ?: [];

        return match ($jobName) {
            'cto' => $this->runJobStepCTO($brief, $stackDecision),
            'architect' => $this->runJobStepArchitect($brief, $stackDecision),
            'designer' => $this->runJobStepDesigner($brief, $stackDecision, $architecture),
            'backend' => $this->runJobStepBackend($brief, $stackDecision, $architecture, $designSystem),
            'frontend' => $this->runJobStepFrontend($brief, $stackDecision, $architecture, $designSystem),
            'qa' => $this->runJobStepQA($brief, $stackDecision, $project, $architecture, $designSystem),
            'devops' => $this->runJobStepDevOps($brief, $stackDecision, $architecture),
            default => throw new \Exception("Unknown job: $jobName"),
        };
    }

    private function runJobStepCTO(array $brief, array &$stackDecision): array {
        $this->progress(5, 'CTO : Analyse du besoin & sélection de la stack...');
        $this->log('ai', 'Agent CTO : Analyse du projet...');
        $stackDecision = $this->runCTO($brief);
        updateProject($this->db, $this->projectId, [
            'project_type' => $stackDecision['analysis']['project_type'],
            'frontend' => $stackDecision['stack_decision']['frontend'],
            'backend' => $stackDecision['stack_decision']['backend'],
            'database' => $stackDecision['stack_decision']['database'],
            'css_framework' => $stackDecision['stack_decision']['css_framework'],
            'stack_choice' => json_encode($stackDecision),
        ]);
        $this->log('ok', "Stack choisie : {$stackDecision['stack_decision']['frontend']} + {$stackDecision['stack_decision']['backend']} + {$stackDecision['stack_decision']['database']}");
        return $stackDecision;
    }

    private function runJobStepArchitect(array $brief, array $stackDecision): array {
        $this->progress(15, 'Architecte : Conception de l\'architecture...');
        $this->log('ai', 'Agent Architecte : Design du système...');
        $architecture = $this->runArchitect($brief, $stackDecision);
        updateProject($this->db, $this->projectId, ['arch_json' => json_encode($architecture)]);
        $this->log('ok', "Architecture : {$architecture['site_name']} — " . count($architecture['frontend_pages']) . " pages, " . count($architecture['api_endpoints']) . " API");
        return $architecture;
    }

    private function runJobStepDesigner(array $brief, array $stackDecision, array $architecture): array {
        $this->progress(28, 'Designer : Création du design system premium...');
        $this->log('ai', 'Agent Designer : Design du système visuel...');
        $designSystem = $this->runDesigner($brief, $stackDecision, $architecture);
        updateProject($this->db, $this->projectId, ['design_json' => json_encode($designSystem)]);
        $this->log('ok', 'Design system créé — composants, couleurs, animations');
        return $designSystem;
    }

    private function runJobStepBackend(array $brief, array $stackDecision, array $architecture, array $designSystem): array {
        $this->progress(40, 'Développeur Backend : Génération des APIs...');
        $this->log('ai', 'Agent Backend : Génération du code serveur...');
        $backendResult = $this->runBackend($brief, $stackDecision, $architecture, $designSystem);
        foreach (($backendResult['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
        foreach (($backendResult['config_files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
        $this->log('ok', 'Backend généré : ' . (count($backendResult['files'] ?? []) + count($backendResult['config_files'] ?? [])) . " fichiers");
        return $backendResult;
    }

    private function runJobStepFrontend(array $brief, array $stackDecision, array $architecture, array $designSystem): array {
        $this->progress(58, 'Développeur Frontend : Génération des interfaces...');
        $this->log('ai', 'Agent Frontend : Génération du code UI...');
        $backendResult = ['files' => [], 'config_files' => []];
        $result = $this->runFrontend($brief, $stackDecision, $architecture, $designSystem, $backendResult);
        return $result;
    }

    private function runJobStepQA(array $brief, array $stackDecision, array $project, array $architecture = [], array $designSystem = []): array {
        $this->progress(75, 'QA Engineer : Inspection + Build + Correction...');
        $this->log('test', 'Agent QA : Validation du code avec boucle de correction...');
        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder);
        $existing = $this->scanFiles($buildDir);
        $qaResult = $this->qaFixLoop($brief, $stackDecision, $existing);
        $score = $qaResult['overall_score'] ?? 0;

        // If QA score < 95 but build compiles, re-run backend+frontend with QA issues as context
        $maxRepairIterations = 3;
        for ($repair = 0; $repair < $maxRepairIterations; $repair++) {
            if ($score >= 95) break;

            $buildErrors = $qaResult['build_errors'] ?? [];
            $issues = $qaResult['issues'] ?? [];
            if (empty($buildErrors) && empty($issues)) break;

            $this->log('sys', "🔧 Réparation #" . ($repair + 1) . ": réinjection des " . count($issues) . " issues dans les agents...");

            $brief['qa_feedback'] = ['build_errors' => $buildErrors, 'qa_issues' => $issues];

            if (!empty($architecture)) {
                $backendResult = $this->runBackend($brief, $stackDecision, $architecture, $designSystem);
                foreach (($backendResult['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
            }

            $feResult = $this->runFrontend($brief, $stackDecision, $architecture, $designSystem, ['files' => []]);
            foreach (($feResult['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);

            $existing = $this->scanFiles($buildDir);
            $qaResult = $this->qaFixLoop($brief, $stackDecision, $existing);
            $score = $qaResult['overall_score'] ?? 0;
        }

        updateProject($this->db, $this->projectId, ['qa_score' => $score, 'file_count' => count($existing), 'build_validated' => ($qaResult['build_success'] ?? false) ? 1 : 0]);
        $this->log('test', "Score qualité final : $score/100");
        return $qaResult;
    }

    private function runJobStepDevOps(array $brief, array $stackDecision, array $architecture): array {
        $this->progress(90, 'DevOps : Préparation du déploiement...');
        $this->log('ai', 'Agent DevOps : Configuration de l\'infrastructure...');
        $devopsResult = $this->runDevOps($brief, $stackDecision, $architecture);
        foreach (($devopsResult['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
        $this->log('ok', 'DevOps prêt : Docker + CI/CD générés');
        updateProject($this->db, $this->projectId, ['status' => 'done']);
        $this->progress(100, '✅ Projet terminé !');
        $this->log('ok', '═══════════════════════════════════════════════════');
        $this->log('ok', "✅ PROJET TERMINÉ — DevOps finalisé");
        $this->log('ok', "📁 Dossier : builds/" . basename($this->projectFolder));
        $this->log('ok', '═══════════════════════════════════════════════════');
        return $devopsResult;
    }

    public function run(array $brief): array {
        $this->projectId = $brief['project_id'];
        $this->projectFolder = $brief['folder'];

        $this->log('sys', '═══════════════════════════════════════════════════');
        $this->log('sys', '🤖 AkrourCoder V4 — 7 AGENTS SPÉCIALISÉS PRÊTS');
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
            // files already written inside runFrontend

            // ── ÉTAPE 6: QA — Validation + Build + Fix Loop ────────
            $this->progress(75, 'QA Engineer : Inspection + Build + Correction (max 3 itérations)...');
            $this->log('test', 'Agent QA : Validation du code avec boucle de correction...');

            $allFiles = array_merge($backendFiles, $configFiles, $frontendResult['files'] ?? []);
            $qaResult = $this->qaFixLoop($brief, $stackDecision, $allFiles);
            $score = $qaResult['overall_score'] ?? 0;
            $buildSuccess = $qaResult['build_success'] ?? false;

            for ($repair = 0; $repair < 3; $repair++) {
                if ($score >= 95) break;
                $issues = $qaResult['issues'] ?? [];
                $buildErrors = $qaResult['build_errors'] ?? [];
                if (empty($issues) && empty($buildErrors)) break;

                $this->log('sys', "🔧 Réparation #" . ($repair + 1) . " ({$score}/100, " . count($issues) . " issues)...");
                $brief['qa_feedback'] = ['build_errors' => $buildErrors, 'qa_issues' => $issues];

                $reBackend = $this->runBackend($brief, $stackDecision, $architecture, $designSystem);
                foreach (($reBackend['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
                $reFrontend = $this->runFrontend($brief, $stackDecision, $architecture, $designSystem, $reBackend);
                foreach (($reFrontend['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);

                $allFiles = array_merge($reBackend['files'] ?? [], $reFrontend['files'] ?? []);
                $qaResult = $this->qaFixLoop($brief, $stackDecision, $allFiles);
                $score = $qaResult['overall_score'] ?? 0;
                $buildSuccess = $qaResult['build_success'] ?? false;
            }

            $this->log('test', "Score qualité final : $score/100, Build : " . ($buildSuccess ? '✅' : '❌'));

            updateProject($this->db, $this->projectId, [
                'qa_score' => $score,
                'file_count' => count($allFiles),
                'build_validated' => $buildSuccess ? 1 : 0,
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

    // ═══════════════════════════════════════════════════════════════════
    //  RESUME — reprendre un build interrompu
    // ═══════════════════════════════════════════════════════════════════

    public function resume(array $project): array {
        $this->projectId = $project['id'];
        $this->projectFolder = $project['folder'];

        $this->log('sys', '═══════════════════════════════════════════════════');
        $this->log('sys', '🔄 Reprise build #' . $project['id'] . ' (API-free)');
        $this->log('sys', '═══════════════════════════════════════════════════');

        try {
            $stackDecision = [
                'analysis' => ['project_type' => $project['project_type'] ?? 'fullstack'],
                'stack_decision' => [
                    'frontend' => $project['frontend'] ?? 'next',
                    'backend' => $project['backend'] ?? 'node_express',
                    'database' => $project['database'] ?? 'postgresql',
                    'css_framework' => $project['css_framework'] ?? 'tailwind',
                ],
            ];
            $architecture = @json_decode($project['arch_json'], true) ?: [];

            $this->log('ok', 'Stack: ' . $stackDecision['stack_decision']['frontend'] . ' + ' . $stackDecision['stack_decision']['backend'] . ' + ' . $stackDecision['stack_decision']['database']);

            // Load existing files from disk
            $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($project['folder']);
            $existingFiles = $this->scanFiles($buildDir);
            $this->log('ok', 'Fichiers sur disque : ' . count($existingFiles));

            // ── RUN BUILD VALIDATION ONLY (no API calls) ──────────
            $this->progress(75, 'Validation build...');
            $this->log('test', 'Validation du code existant...');

            $buildResult = $this->runBuildValidation($stackDecision);
            $importErrors = $this->validateFileImports($existingFiles);
            $buildOk = $buildResult['success'] && empty($importErrors);
            $score = $buildOk ? 100 : 85;

            $errorSummary = [];
            foreach (($buildResult['errors'] ?? []) as $be) {
                if ($be['command'] === 'eslint') continue;
                $errorSummary[] = "[{$be['command']}] {$be['output']}";
            }
            foreach ($importErrors as $ie) {
                $errorSummary[] = "[import] {$ie['file']}: {$ie['issue']}";
            }

            if (!empty($errorSummary)) {
                $this->log('warn', "⚠ " . count($errorSummary) . " problèmes détectés :");
                foreach (array_slice($errorSummary, 0, 10) as $e) {
                    $this->log('warn', "  " . substr($e, 0, 200));
                }
            }

            $this->log('test', "Build : " . ($buildOk ? '✅ RÉUSSI' : '❌ ÉCHEC'));
            $this->log('test', "Score qualité estimé : $score/100");

            updateProject($this->db, $this->projectId, [
                'qa_score' => $score,
                'file_count' => count($existingFiles),
                'build_validated' => $buildOk ? 1 : 0,
            ]);

            updateProject($this->db, $this->projectId, ['status' => 'done']);

            $this->progress(100, $buildOk ? '✅ Projet terminé !' : '❌ Build échoué');
            $this->log('ok', '═══════════════════════════════════════════════════');
            $this->log('ok', "✅ REPRISE TERMINÉE — Statut : " . ($buildOk ? 'OK' : 'ÉCHEC'));
            $this->log('ok', "📁 Dossier : builds/" . basename($this->projectFolder));
            $this->log('ok', "📦 Fichiers : " . count($existingFiles));
            $this->log('ok', '═══════════════════════════════════════════════════');

            return ['success' => true, 'project_id' => $this->projectId, 'files' => count($existingFiles), 'qa_score' => $score];

        } catch (\Exception $e) {
            $this->log('err', '❌ Reprise interrompue : ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function scanFiles(string $dir): array {
        $files = [];
        if (!is_dir($dir)) return $files;
        $excludeDirs = ['node_modules', '.next', '.git', 'vendor', '.cache', '__pycache__', '.venv', 'target'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                function ($current, $key, $iterator) use ($excludeDirs) {
                    if ($current->isDir() && in_array($current->getBasename(), $excludeDirs, true)) {
                        return false; // Skip excluded dirs entirely
                    }
                    return true;
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relPath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relPath = str_replace('\\', '/', $relPath);
                $files[] = [
                    'filename' => $relPath,
                    'content' => file_get_contents($file->getPathname()),
                    'language' => pathinfo($relPath, PATHINFO_EXTENSION),
                    'size' => $file->getSize(),
                ];
            }
        }
        return $files;
    }

    private function runBackendBrief(array $arch): array {
        // Return minimal backend result needed for frontend context
        $files = [];
        $endpoints = $arch['api_endpoints'] ?? [];
        foreach ($endpoints as $ep) {
            $files[] = ['filename' => ($ep['method'] ?? 'GET') . ' ' . ($ep['path'] ?? '/'), 'content' => ''];
        }
        return ['files' => $files, 'config_files' => []];
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

        $userContent = $this->enrichContext('cto', $userContent);

        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode($userContent)],
        ];
        $decision = $this->callWithRetry($messages, 16000, true, 'cto');
        $ctoSummary = is_string($decision['analysis']['reasoning'] ?? null) ? $decision['analysis']['reasoning'] : (is_array($decision['analysis']['reasoning'] ?? null) ? json_encode($decision['analysis']['reasoning']) : '');
        $this->storeAgentMemory('cto', 'Stack choisie: ' . ($decision['stack_decision']['frontend'] ?? '') . '+' . ($decision['stack_decision']['backend'] ?? ''), $ctoSummary);

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
        $userContent = $this->enrichContext('architect', [
            'brief' => $brief,
            'stack' => $stack,
        ]);
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode($userContent)],
        ];
        $res = $this->callWithRetry($messages, 16000, true, 'architect');
        $this->storeAgentMemory('architect', 'Architecture: ' . ($res['architecture_pattern'] ?? '') . ', ' . count($res['frontend_pages'] ?? []) . ' pages, ' . count($res['database_schema']['tables'] ?? []) . ' tables', json_encode($res['architecture_decisions'] ?? []));
        return $res;
    }

    // ─── Agent Designer ───────────────────────────────────────────

    private function runDesigner(array $brief, array $stack, array $arch): array {
        $prompt = $this->loadAgentPrompt('designer');
        $userContent = $this->enrichContext('designer', [
            'brief' => $brief,
            'stack' => $stack,
            'architecture' => $arch,
        ]);
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode($userContent)],
        ];
        $res = $this->callWithRetry($messages, 16000, true, 'designer');
        $dKey = is_array($res['design_tokens'] ?? null) ? ($res['design_tokens']['primary_color'] ?? '') : ((is_string($res['design_tokens'] ?? null) ? substr($res['design_tokens'], 0, 60) : ''));
        $dRationale = is_string($res['design_rationale'] ?? null) ? $res['design_rationale'] : (is_array($res['design_rationale'] ?? null) ? json_encode($res['design_rationale']) : '');
        $this->storeAgentMemory('designer', 'Design: palette ' . $dKey, $dRationale);
        return $res;
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
        $userContent = $this->enrichContext('backend', [
            'brief' => $brief,
            'stack' => $stack,
            'architecture' => $arch,
            'design_system' => $design,
            'tables' => $arch['database_schema']['tables'] ?? [],
            'endpoints' => $arch['api_endpoints'] ?? [],
        ]);
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode($userContent)],
        ];
        $res = $this->callWithRetry($messages, 32000, true, 'backend');
        $this->storeAgentMemory('backend', count($res['files'] ?? []) . ' fichiers backend générés', json_encode(array_keys($res['files'] ?? [])));
        $this->fixImportExportMismatch($res['files'] ?? []);
        return $res;
    }

    private function fixImportExportMismatch(array $files): void {
        $defaultExports = [];
        foreach ($files as $f) {
            $content = $f['content'] ?? '';
            if (preg_match('/export\s+default\s+(?:class|function|const|let|var|interface|type|abstract\s+class)\s+(\w+)/', $content, $m)) {
                $defaultExports[$m[1]] = $f['filename'];
            }
        }
        if (empty($defaultExports)) return;

        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder);
        foreach ($files as $f) {
            $content = $f['content'] ?? '';
            $changed = false;
            foreach ($defaultExports as $name => $sourceFile) {
                // Detect `import { Name } from` where Name is a default export
                $pattern = '/import\s*\{\s*' . preg_quote($name, '/') . '\s*\}\s*from\s*[\'"]/';
                if (preg_match($pattern, $content)) {
                    $content = preg_replace(
                        '/import\s*\{\s*' . preg_quote($name, '/') . '\s*\}\s*from\s*([\'"])/',
                        'import ' . $name . ' from $1',
                        $content
                    );
                    $changed = true;
                    $this->log('fix', "Import corrigé: {$f['filename']} → {$name} (export default dans {$sourceFile})");
                }
            }
            if ($changed) {
                $filepath = $buildDir . DIRECTORY_SEPARATOR . $f['filename'];
                $dir = dirname($filepath);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($filepath, $content);
            }
        }
    }

    // ─── Agent Frontend (fichier par fichier) ────────────────────

    private function runFrontend(array $brief, array $stack, array $arch, array $design, array $backend): array {
        $feType = $stack['stack_decision']['frontend'] ?? 'html_css_js';

        // Step 1: Plan — liste des fichiers uniquement
        $this->log('ai', 'Frontend: Planification des fichiers...');

        // For static HTML, always generate 1 file
        if ($feType === 'html_css_js') {
            $fileList = [['filename' => 'index.html', 'description' => 'Page principale unique HTML + CSS inline + JS']];
            $this->log('ok', 'Frontend: site statique — 1 fichier (index.html)');
        } else {
            $frontendLabel = match ($feType) {
                'react', 'vite' => 'React + Vite',
                'next' => 'Next.js 14',
                'vue' => 'Vue 3',
                'flutter' => 'Flutter',
                'kotlin' => 'Android Kotlin',
                'swiftui' => 'SwiftUI',
                default => $feType,
            };
            $planMessages = [
                ['role' => 'system', 'content' => "Tu listes les fichiers nécessaires pour un projet {$frontendLabel}. MAXIMUM 5 fichiers. Réponse JSON : {\"files\":[{\"filename\":\"...\",\"description\":\"...\"}]}. AUCUN CODE. Garde le plan minimal (1 fichier = 1 composant, regroupe tout le nécessaire)."],
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
        }

        // Step 2: Génération
        $prompt = $this->loadAgentPrompt('frontend');
        $allFiles = [];
        $total = count($fileList);

        if ($feType === 'html_css_js') {
            // Static HTML: generate all content in a single API call
            $this->progress(60, 'Frontend: index.html...');
            $this->log('ai', 'Frontend: génération index.html complet...');
            $feContent = $this->enrichContext('frontend', [
                'brief' => $brief, 'stack' => $stack, 'architecture' => $arch,
                'design_system' => $design, 'backend' => $backend,
                'pages' => $arch['frontend_pages'] ?? [],
            ]);
            $fileMsg = [
                ['role' => 'system', 'content' => $prompt . "\n\nGénère un fichier HTML unique. Réponse JSON : {\"filename\":\"index.html\",\"content\":\"...\",\"language\":\"html\"}"],
                ['role' => 'user', 'content' => json_encode($feContent)],
            ];
            $res = $this->callWithRetry($fileMsg, 8000, true, 'frontend-file');
            $content = $res['content'] ?? '';
            if ($content) {
                $this->writeFile('index.html', $content);
                $allFiles[] = ['filename' => 'index.html', 'content' => $content, 'language' => 'html'];
            }
        } else {
            // File-by-file for component-based frameworks (max ~5 files from plan)
            foreach ($fileList as $i => $fileDef) {
                $this->progress(55 + intval(20 * $i / $total), "Frontend: fichier " . ($i + 1) . "/{$total}");
                $filename = $fileDef['filename'] ?? "f{$i}.tsx";
                $this->log('ai', "Frontend [{$i}/{$total}]: {$filename}...");
                $feContent = $this->enrichContext('frontend', [
                    'brief' => $brief, 'stack' => $stack, 'architecture' => $arch,
                    'design_system' => $design, 'backend' => $backend,
                    'pages' => $arch['frontend_pages'] ?? [],
                    'file_to_generate' => $filename,
                    'all_planned_files' => array_column($fileList, 'filename'),
                ]);
                $fileMsg = [
                    ['role' => 'system', 'content' => $prompt . "\n\nGénère UNIQUEMENT le fichier {$filename}. Réponse JSON : {\"filename\":\"{$filename}\",\"content\":\"...\",\"language\":\"...\"}"],
                    ['role' => 'user', 'content' => json_encode($feContent)],
                ];
                $res = $this->callWithRetry($fileMsg, 6000, true, 'frontend-file');
                $fn = $res['filename'] ?? $filename;
                $content = $res['content'] ?? '';
                if ($content) {
                    $this->writeFile($fn, $content);
                    $allFiles[] = ['filename' => $fn, 'content' => $content];
                }
                usleep(200000);
            }
        }

        if (empty($allFiles)) throw new Exception('Frontend: aucun fichier généré');
        $this->log('ok', 'Frontend généré : ' . count($allFiles) . ' fichiers');
        return ['files' => $allFiles];
    }

    // ─── Agent QA ─────────────────────────────────────────────────

    private function runQA(array $brief, array $stack, array $allFiles): array {
        $prompt = $this->loadAgentPrompt('qa');

        // Send full file content so QA can produce accurate fixes
        $filesSummary = [];
        foreach ($allFiles as $f) {
            $fn = $f['filename'] ?? 'unknown';
            if ($fn === '_build_errors.json') continue;
            $content = $f['content'] ?? '';
            if (strlen($content) > 2000) {
                $content = substr($content, 0, 2000) . "\n\n... [truncated, total " . strlen($content) . " chars]";
            }
            $filesSummary[] = [
                'filename' => $fn,
                'language' => $f['language'] ?? 'unknown',
                'size' => strlen($f['content'] ?? ''),
                'content' => $content,
            ];
        }

        $qaContent = $this->enrichContext('qa', [
            'brief' => $brief,
            'stack' => $stack,
            'files' => $filesSummary,
        ]);

        $qaJson = json_encode($qaContent, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        if ($qaJson === false) {
            $qaJson = json_encode(['error' => 'Échec encodage QA', 'files' => count($allFiles)], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $qaJson],
        ];
        $res = $this->callWithRetry($messages, 16000, true, 'qa');
        $this->storeAgentMemory('qa', 'Score: ' . ($res['score'] ?? 'N/A') . ', Problèmes: ' . count($res['issues'] ?? []), json_encode(array_slice($res['issues'] ?? [], 0, 3)));
        return $res;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  VALIDATION DE BUILD — stack-agnostique
    // ═══════════════════════════════════════════════════════════════════

    private function detectBuildCommands(array $stackDecision): array {
        $front = $stackDecision['stack_decision']['frontend'] ?? '';
        $back  = $stackDecision['stack_decision']['backend'] ?? '';
        $type  = $stackDecision['analysis']['project_type'] ?? 'fullstack';
        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder);
        $cmds = [];

        // Node.js / JS ecosystem
        if (in_array($front, ['next','react','vue','nuxt','svelte','angular','astro','remix','react_native'])
            || in_array($back, ['node_express','supabase','firebase'])) {
            $pkg = "$buildDir/package.json";
            if (file_exists($pkg)) {
                $cmds[] = ['name' => 'npm_install', 'cmd' => 'npm install --legacy-peer-deps 2>&1', 'cwd' => $buildDir, 'optional' => false];
                $cmds[] = ['name' => 'npm_build',   'cmd' => 'npm run build 2>&1',  'cwd' => $buildDir, 'optional' => false];
                // Try tsc check if typescript is present
                $cmds[] = ['name' => 'tsc_check',   'cmd' => 'npx tsc --noEmit 2>&1', 'cwd' => $buildDir, 'optional' => true];
                // Try lint
                $cmds[] = ['name' => 'eslint',      'cmd' => 'npx eslint . --max-warnings 50 2>&1', 'cwd' => $buildDir, 'optional' => true];
            }
        }

        // Python
        if (in_array($back, ['fastapi_python','django_python'])) {
            $req = "$buildDir/requirements.txt";
            if (!file_exists($req)) $req = "$buildDir/backend/requirements.txt";
            if (file_exists($req)) {
                $cmds[] = ['name' => 'pip_install', 'cmd' => 'pip install -r "' . basename(dirname($req)) . '/requirements.txt" 2>&1', 'cwd' => $buildDir, 'optional' => false];
            }
            $cmds[] = ['name' => 'py_syntax', 'cmd' => 'python -m compileall . 2>&1', 'cwd' => $buildDir, 'optional' => true];
        }

        // Go
        if ($back === 'go_gin') {
            $cmds[] = ['name' => 'go_build', 'cmd' => 'go build ./... 2>&1', 'cwd' => $buildDir, 'optional' => false];
        }

        // Rust
        if ($back === 'rust_actix') {
            $cmds[] = ['name' => 'cargo_check', 'cmd' => 'cargo check 2>&1', 'cwd' => $buildDir, 'optional' => false];
        }

        // Flutter
        if ($front === 'flutter') {
            $cmds[] = ['name' => 'flutter_analyze', 'cmd' => 'flutter analyze 2>&1', 'cwd' => $buildDir, 'optional' => false];
        }

        // Android / Kotlin
        if ($front === 'kotlin') {
            $gradle = "$buildDir/gradlew";
            $gradleBat = "$buildDir/gradlew.bat";
            $gradleCmd = is_file($gradleBat) ? 'gradlew.bat' : (is_file($gradle) ? './gradlew' : 'gradle');
            $cmds[] = ['name' => 'gradle_build', 'cmd' => "$gradleCmd assembleDebug 2>&1", 'cwd' => $buildDir, 'optional' => false];
        }

        // Swift
        if ($front === 'swiftui') {
            $cmds[] = ['name' => 'swift_build', 'cmd' => 'swift build 2>&1', 'cwd' => $buildDir, 'optional' => false];
        }

        return $cmds;
    }

    private function runBuildValidation(array $stackDecision): array {
        $errors = [];
        $commands = $this->detectBuildCommands($stackDecision);

        if (empty($commands)) {
            $this->log('ok', 'Build: aucun système détecté, validation ignorée');
            return ['success' => true, 'errors' => []];
        }

        $this->log('sys', '═══ Validation Build (' . count($commands) . ' commandes) ═══');

        foreach ($commands as $cmd) {
            $this->log('sys', "→ {$cmd['name']}...");
            $output = [];
            $code = -1;
            if (PHP_OS_FAMILY === 'Windows') {
                $fullCmd = "cd /d \"{$cmd['cwd']}\" && {$cmd['cmd']}";
            } else {
                $fullCmd = "cd \"{$cmd['cwd']}\" && {$cmd['cmd']}";
            }
            exec($fullCmd, $output, $code);
            $outStr = implode("\n", array_slice($output, -20));

            if ($code !== 0) {
                if ($cmd['optional']) {
                    $this->log('warn', "  ⚠ {$cmd['name']} (optionnel) a échoué: code $code");
                    $errors[] = ['command' => $cmd['name'], 'severity' => 'warning', 'output' => $outStr];
                } else {
                    $this->log('err', "  ✗ {$cmd['name']} a échoué (code $code)");
                    $this->log('err', "  Sortie: " . substr($outStr, 0, 500));
                    $errors[] = ['command' => $cmd['name'], 'severity' => 'error', 'output' => $outStr];
                }
            } else {
                $this->log('ok', "  ✓ {$cmd['name']} OK");
            }
        }

        return [
            'success' => empty(array_filter($errors, fn($e) => $e['severity'] === 'error')),
            'errors' => $errors,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  VALIDATION DES IMPORTS — vérifie que les imports locaux existent
    // ═══════════════════════════════════════════════════════════════════

    private function validateFileImports(array $allFiles): array {
        $issues = [];
        $localExports = []; // resolvedPath => [named exports, default export]

        // Collect all exports (skip node_modules)
        foreach ($allFiles as $f) {
            $content = $f['content'] ?? '';
            $filename = $f['filename'] ?? '';
            if (str_starts_with($filename, 'node_modules/')) continue;
            $named = [];
            $default = null;

            // export const|function|let|var|class Name
            preg_match_all('/export\s+(?:const|function|let|var|class|async\s+function)\s+(\w+)/', $content, $m);
            $named = $m[1] ?? [];

            // export default Name
            if (preg_match('/export\s+default\s+(?:function\s+|class\s+)?(\w+)/', $content, $dm)) {
                $default = $dm[1];
            }
            // export { Name1, Name2 }
            preg_match_all('/export\s+\{\s*([^}]+)\s*\}/', $content, $em);
            foreach ($em[1] ?? [] as $block) {
                foreach (explode(',', $block) as $part) {
                    $part = trim($part);
                    $part = preg_replace('/\s+as\s+\w+/', '', $part);
                    if ($part) $named[] = $part;
                }
            }

            $localExports[$filename] = ['named' => $named, 'default' => $default];
        }

        // Check all imports (skip node_modules)
        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder) . DIRECTORY_SEPARATOR;
        foreach ($allFiles as $f) {
            $content = $f['content'] ?? '';
            $filename = $f['filename'] ?? '';
            if (str_starts_with($filename, 'node_modules/')) continue;

            // Find all import ... from 'relative/path'
            preg_match_all('/import\s+(?:\{\s*([^}]+)\s*\}|(\w+)(?:\s*,\s*\{\s*([^}]+)\s*\})?)\s+from\s+[\'"](\.\.?\/[^\'"]+)[\'"]/', $content, $imports, PREG_SET_ORDER);

            foreach ($imports as $imp) {
                $importPath = $imp[4];
                $resolved = $this->resolveImportPath($filename, $importPath, $buildDir);
                if (!$resolved) {
                    $issues[] = [
                        'file' => $filename,
                        'import' => $importPath,
                        'issue' => 'Fichier introuvable',
                        'severity' => 'error',
                    ];
                    continue;
                }

                $namedImport = trim($imp[1] ?? '');
                $defaultImport = trim($imp[2] ?? '');
                $namedFromDefault = trim($imp[3] ?? '');

                $targetExports = $localExports[$resolved] ?? ['named' => [], 'default' => null];

                // Check default import
                if ($defaultImport && !$targetExports['default'] && empty($targetExports['named'])) {
                    $issues[] = [
                        'file' => $filename,
                        'import' => $importPath,
                        'issue' => "Import par défaut '{$defaultImport}' mais le fichier n'a pas d'export par défaut",
                        'severity' => 'warning',
                    ];
                }

                // Check named imports
                $names = [];
                if ($namedImport) {
                    $names = array_map('trim', explode(',', $namedImport));
                }
                if ($namedFromDefault) {
                    $names = array_merge($names, array_map('trim', explode(',', $namedFromDefault)));
                }
                foreach ($names as $name) {
                    $name = preg_replace('/\s+as\s+\w+/', '', $name); // handle "X as Y"
                    if ($name && !in_array($name, $targetExports['named']) && !$targetExports['default']) {
                        $issues[] = [
                            'file' => $filename,
                            'import' => $importPath,
                            'issue' => "'{$name}' n'est pas exporté par le fichier cible (exports: " . implode(', ', $targetExports['named']) . ')',
                            'severity' => 'error',
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    private function resolveImportPath(string $sourceFile, string $importPath, string $buildDir): ?string {
        $sourceDir = dirname($sourceFile);
        // Normalize path separators
        $importPath = str_replace('\\', '/', $importPath);
        $sourceDir = str_replace('\\', '/', $sourceDir);

        $parts = explode('/', $sourceDir);
        $importParts = explode('/', $importPath);
        foreach ($importParts as $part) {
            if ($part === '.') continue;
            if ($part === '..') {
                if (count($parts) > 0) array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        $resolved = implode('/', $parts);

        // Check common extensions
        $extensions = ['.js', '.jsx', '.ts', '.tsx', '.vue', '.svelte', '.astro', '.dart', '.kt', '.swift', '.py', '.go', '.rs', '.php'];
        $candidates = [$resolved];
        foreach ($extensions as $ext) {
            $candidates[] = $resolved . $ext;
            $candidates[] = $resolved . DIRECTORY_SEPARATOR . 'index' . $ext;
        }

        // Also check the localExports keys directly
        foreach ($candidates as $c) {
            $c = ltrim($c, '/');
            if (isset($this->localExportCache) || true) {
                // Check against known files
                foreach ($this->generatedFiles as $gf) {
                    $gf = str_replace('\\', '/', $gf);
                    if ($gf === $c) return $c;
                }
            }
        }

        // Check actual filesystem
        foreach ($candidates as $c) {
            $fullPath = $buildDir . $c;
            if (file_exists($fullPath)) return $c;
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  BOUCLE QA-FIX — NEVER STOP jusqu'à score >= 95 ou stagnation
    //  Inspiré de karpathy/autoresearch : autonome, ne demande jamais la permission
    // ═══════════════════════════════════════════════════════════════════

    private function qaFixLoop(array $brief, array $stackDecision, array $allFiles): array {
        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder);

        $this->log('sys', "═══ QA Inspection (build-only) ═══");

        // Try AI QA if API available, fall back to build-only validation
        $aiScore = null;
        $issues = [];
        try {
            $qaResult = $this->runQA($brief, $stackDecision, $allFiles);
            $aiScore = $qaResult['overall_score'] ?? null;
            $issues = $qaResult['issues'] ?? [];
            $this->log('test', "Score IA : $aiScore/100 — " . count($issues) . " problèmes");
        } catch (\Exception $e) {
            $this->log('warn', "QA IA indisponible ({$e->getMessage()}), passage en mode build-only");
        }

        // Build validation (toujours disponible)
        $buildResult = $this->runBuildValidation($stackDecision);
        $importErrors = $this->validateFileImports($allFiles);

        $errorSummary = [];
        foreach (($buildResult['errors'] ?? []) as $be) {
            if ($be['command'] === 'eslint') continue;
            $errorSummary[] = "[{$be['command']}] {$be['output']}";
        }
        foreach ($importErrors as $ie) {
            $errorSummary[] = "[import] {$ie['file']}: {$ie['issue']}";
        }

        $buildOk = $buildResult['success'] && empty($importErrors);
        $score = $aiScore ?? ($buildOk ? 100 : 85);

        $this->writeFile('_build_errors.json', json_encode([
            'build_errors' => $errorSummary,
            'issues' => $issues,
            'score' => $score,
        ], JSON_PRETTY_PRINT));

        $this->log('sys', "→ Build: " . ($buildOk ? 'OK' : 'Échec') . " | Score: $score/100");

        return [
            'overall_score' => $score,
            'issues' => $issues,
            'fixes' => [],
            'build_errors' => $errorSummary,
            'build_success' => $buildOk,
        ];
    }

    private function appendToFile(string $filename, string $content): void {
        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder);
        $path = $buildDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $content, FILE_APPEND | LOCK_EX);
    }

    // ─── Agent DevOps ─────────────────────────────────────────────

    private function runDevOps(array $brief, array $stack, array $arch): array {
        $prompt = $this->loadAgentPrompt('devops');
        $devopsContent = $this->enrichContext('devops', [
            'brief' => $brief,
            'stack' => $stack,
            'architecture' => $arch,
            'frontend' => $stack['stack_decision']['frontend'] ?? 'react',
            'backend' => $stack['stack_decision']['backend'] ?? 'node_express',
            'database' => $stack['stack_decision']['database'] ?? 'sqlite',
        ]);
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => json_encode($devopsContent)],
        ];
        $res = $this->callWithRetry($messages, 16000, true, 'devops');
        $this->storeAgentMemory('devops', 'Docker: ' . ($res['docker'] ? 'Oui' : 'Non') . ', CI: ' . ($res['ci_cd'] ? 'Oui' : 'Non'), json_encode($res));
        return $res;
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
            . "## Généré par AkrourCoder V4\n"
            . "- Modèle : " . AC4_MODEL . "\n"
            . "- Version : " . AC4_VERSION . "\n"
            . "- Date : " . date('Y-m-d H:i:s') . "\n"
            . "- Score QA : $score/100\n";

        $this->writeFile('README.md', $content);
    }
}
