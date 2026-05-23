<?php
/**
 * AutoCoder V4 — Pipeline Engine
 * Orchestrateur multi-agents : CTO → Architect → Designer → Backend → Frontend → QA → DevOps
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/sandbox.php';

class PipelineEngine {
    private PDO $db;
    private int $projectId;
    private string $projectFolder;
    private array $generatedFiles = [];
    private array $state = [];
    private ?AIModel $ai = null;

    public function __construct() {
        $this->db = getDB();
        $this->ai = new AIModel();
    }

    // ─── IA Multi-modèle ─────────────────────────────────────────────

    private function callAI(array $messages, int $maxTokens = 4000, bool $jsonMode = true, string $step = ''): array {
        return $this->ai->call($messages, $maxTokens, $jsonMode, $step);
    }

    // ─── Logging ──────────────────────────────────────────────────────

    private string $currentJobName = '';

    private function log(string $level, string $message): void {
        $step = debug_backtrace()[1]['function'] ?? 'engine';
        appendLog($this->db, $this->projectId, $step, $level, $message, $this->currentJobName);
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

    // ═══════════════════════════════════════════════════════════════════
    //  JOB INDIVIDUEL — exécute UNE étape du pipeline
    // ═══════════════════════════════════════════════════════════════════

    public function runJob(int $projectId, string $jobName, array $context = []): array {
        $this->projectId = $projectId;
        $this->currentJobName = $jobName;

        $project = $this->db->query("SELECT * FROM projects WHERE id = $projectId")->fetch();
        if (!$project) throw new Exception("Project #$projectId not found");

        $this->projectFolder = $project['folder'];

        $stackDecision = [
            'analysis' => ['project_type' => $project['project_type'] ?? 'fullstack'],
            'stack_decision' => [
                'frontend' => $project['frontend'] ?? 'next',
                'backend' => $project['backend'] ?? 'node_express',
                'database' => $project['database'] ?? 'sqlite',
                'css_framework' => $project['css_framework'] ?? 'tailwind',
            ],
        ];

        // If stack_choice exists in DB, use full decision
        if (!empty($project['stack_choice'])) {
            $saved = @json_decode($project['stack_choice'], true);
            if ($saved) $stackDecision = $saved;
        }

        $architecture = @json_decode($project['arch_json'], true) ?: [];

        $brief = @json_decode($project['brief'], true) ?: [];
        $brief['title'] = $project['title'];
        $brief['project_id'] = $projectId;
        $brief['folder'] = $project['folder'];
        $brief['project_type'] = $project['project_type'] ?? '';
        $brief['frontend'] = $project['frontend'] ?? '';
        $brief['backend'] = $project['backend'] ?? '';
        $brief['database'] = $project['database'] ?? '';
        $brief['css_framework'] = $project['css_framework'] ?? '';
        $brief['master_prompt'] = $brief['master_prompt'] ?? '';

        $this->log('sys', "▶ Job: $jobName (projet #$projectId)");

        try {
            return match ($jobName) {
                'cto' => $this->runJobCTO($brief, $project, $stackDecision),
                'architect' => $this->runJobArchitect($brief, $stackDecision, $project),
                'designer' => $this->runJobDesigner($brief, $stackDecision, $project),
                'backend' => $this->runJobBackend($brief, $stackDecision, $architecture, $project),
                'frontend' => $this->runJobFrontend($brief, $stackDecision, $architecture, $project),
                'qa' => $this->runJobQA($brief, $stackDecision, $project),
                'devops' => $this->runJobDevOps($brief, $stackDecision, $architecture, $project),
                default => throw new Exception("Unknown job: $jobName"),
            };
        } catch (\Exception $e) {
            $this->log('err', "Job $jobName échoué: " . $e->getMessage());
            throw $e;
        }
    }

    private function runJobCTO(array $brief, array $project, array &$stackDecision): array {
        $this->progress(5, 'CTO : Analyse du besoin...');
        $decision = $this->runCTO($brief);

        $update = [
            'project_type' => $decision['analysis']['project_type'],
            'frontend'     => $decision['stack_decision']['frontend'],
            'backend'      => $decision['stack_decision']['backend'],
            'database'     => $decision['stack_decision']['database'],
            'css_framework'=> $decision['stack_decision']['css_framework'],
            'stack_choice' => json_encode($decision),
        ];
        if (!empty($decision['analysis']['extracted_title'])) {
            $update['title'] = $decision['analysis']['extracted_title'];
        }
        updateProject($this->db, $this->projectId, $update);
        $this->log('ok', "Stack: {$decision['stack_decision']['frontend']} + {$decision['stack_decision']['backend']} + {$decision['stack_decision']['database']}");

        // Update stackDecision in DB for subsequent jobs
        $this->db->prepare("UPDATE projects SET stack_choice = ? WHERE id = ?")
            ->execute([json_encode($decision), $this->projectId]);

        return $decision;
    }

    private function runJobArchitect(array $brief, array $stackDecision, array $project): array {
        $this->progress(15, 'Architecte : Conception...');
        $architecture = $this->runArchitect($brief, $stackDecision);
        updateProject($this->db, $this->projectId, ['arch_json' => json_encode($architecture)]);
        $this->log('ok', "Architecture: {$architecture['site_name']} — " . count($architecture['frontend_pages'] ?? []) . " pages");
        return $architecture;
    }

    private function runJobDesigner(array $brief, array $stackDecision, array $project): array {
        $architecture = @json_decode($project['arch_json'], true) ?: [];
        $this->progress(28, 'Designer : Design system...');
        $design = $this->runDesigner($brief, $stackDecision, $architecture);
        $this->log('ok', 'Design system créé');
        return $design;
    }

    private function runJobBackend(array $brief, array $stackDecision, array $architecture, array $project): array {
        $design = $this->getDesignSystem($project);
        $this->progress(40, 'Backend : Génération...');
        $result = $this->runBackend($brief, $stackDecision, $architecture, $design);
        foreach (($result['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
        foreach (($result['config_files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
        $fc = count($result['files'] ?? []) + count($result['config_files'] ?? []);
        $this->log('ok', "Backend: $fc fichiers");
        return $result;
    }

    private function runJobFrontend(array $brief, array $stackDecision, array $architecture, array $project): array {
        $design = $this->getDesignSystem($project);
        $backendResult = $this->getBackendContext($architecture);
        $this->progress(58, 'Frontend : Génération...');
        $result = $this->runFrontend($brief, $stackDecision, $architecture, $design, $backendResult);
        $this->log('ok', 'Frontend généré: ' . count($result['files'] ?? []) . ' fichiers');
        return $result;
    }

    private function runJobQA(array $brief, array $stackDecision, array $project): array {
        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($project['folder']);
        $existingFiles = $this->scanFiles($buildDir);

        $this->progress(75, 'QA : Validation...');
        $result = $this->qaFixLoop($brief, $stackDecision, $existingFiles);

        $score = $result['overall_score'] ?? 0;
        $buildSuccess = $result['build_success'] ?? false;

        updateProject($this->db, $this->projectId, [
            'qa_score' => $score,
            'file_count' => count($existingFiles),
            'build_validated' => $buildSuccess ? 1 : 0,
        ]);

        $this->log('test', "Score QA: $score/100, Build: " . ($buildSuccess ? '✅' : '❌'));
        return $result;
    }

    private function runJobDevOps(array $brief, array $stackDecision, array $architecture, array $project): array {
        $this->progress(90, 'DevOps : Déploiement...');
        $result = $this->runDevOps($brief, $stackDecision, $architecture);
        foreach (($result['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
        $this->generateReadme($architecture, $stackDecision, (int)$project['qa_score']);
        $this->log('ok', 'DevOps + README prêts');
        return $result;
    }

    private function getDesignSystem(array $project): array {
        // Design system is reconstructed from context each time
        $brief = @json_decode($project['brief'], true) ?: [];
        $brief['title'] = $project['title'];
        $brief['project_id'] = $project['id'];
        $brief['folder'] = $project['folder'];

        $stackDecision = [
            'analysis' => ['project_type' => $project['project_type'] ?? 'fullstack'],
            'stack_decision' => [
                'frontend' => $project['frontend'] ?? 'next',
                'backend' => $project['backend'] ?? 'node_express',
                'database' => $project['database'] ?? 'sqlite',
                'css_framework' => $project['css_framework'] ?? 'tailwind',
            ],
        ];
        if (!empty($project['stack_choice'])) {
            $saved = @json_decode($project['stack_choice'], true);
            if ($saved) $stackDecision = $saved;
        }

        $architecture = @json_decode($project['arch_json'], true) ?: [];
        return $this->runDesigner($brief, $stackDecision, $architecture);
    }

    private function getBackendContext(array $arch): array {
        $endpoints = $arch['api_endpoints'] ?? [];
        $files = [];
        foreach ($endpoints as $ep) {
            $files[] = ['filename' => ($ep['method'] ?? 'GET') . ' ' . ($ep['path'] ?? '/'), 'content' => ''];
        }
        return ['files' => $files, 'config_files' => []];
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
            // files already written inside runFrontend

            // ── ÉTAPE 6: QA — Validation + Build + Fix Loop ────────
            $this->progress(75, 'QA Engineer : Inspection + Build + Correction (max 3 itérations)...');
            $this->log('test', 'Agent QA : Validation du code avec boucle de correction...');

            $allFiles = array_merge($backendFiles, $configFiles, $frontendResult['files'] ?? []);
            $qaResult = $this->qaFixLoop($brief, $stackDecision, $allFiles);

            $score = $qaResult['overall_score'] ?? 0;
            $buildSuccess = $qaResult['build_success'] ?? false;
            $iterations = $qaResult['iterations'] ?? 1;

            $this->log('test', "Score qualité : $score/100 (itérations : $iterations)");
            $this->log('test', "Problèmes : " . count($qaResult['issues'] ?? []) . ", Corrections : " . count($qaResult['fixes'] ?? []));
            $this->log('test', "Build : " . ($buildSuccess ? '✅ RÉUSSI' : '❌ ÉCHEC'));

            if (!empty($qaResult['build_errors'])) {
                $this->log('warn', "⚠ Erreurs de build persistantes : " . count($qaResult['build_errors']));
                foreach (array_slice($qaResult['build_errors'], 0, 10) as $be) {
                    $this->log('warn', "  " . substr($be, 0, 200));
                }
            }

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
        $this->log('sys', '🔄 Reprise du build #' . $project['id']);
        $this->log('sys', '═══════════════════════════════════════════════════');

        try {
            // Reconstruct stack decision and architecture from DB
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

            // Brief context
            $brief = @json_decode($project['brief'], true) ?: [];
            $brief['title'] = $project['title'];
            $brief['project_id'] = $project['id'];
            $brief['folder'] = $project['folder'];

            // Load existing files from disk
            $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($project['folder']);
            $existingFiles = $this->scanFiles($buildDir);

            // Regenerate design system (not stored in DB)
            $this->progress(65, 'Designer (cache)');
            $this->log('ai', 'Regénération du design system...');
            $designSystem = $this->runDesigner($brief, $stackDecision, $architecture);

            // Regenerate backend result (needed for frontend context)
            $this->progress(70, 'Backend (cache)');
            $this->log('ai', 'Reconstitution du contexte backend...');
            $backendResult = $this->runBackendBrief($architecture);

            // Check if frontend files exist; if not, regenerate them
            $hasFrontend = false;
            $feExtensions = ['.html', '.htm', '.jsx', '.tsx', '.vue', '.svelte', '.astro', '.dart', '.kt', '.swift'];
            foreach ($existingFiles as $f) {
                $ext = '.' . ($f['language'] ?? '');
                if (in_array($ext, $feExtensions)) { $hasFrontend = true; break; }
            }

            if (!$hasFrontend && $stackDecision['stack_decision']['frontend'] !== 'none') {
                $this->progress(72, 'Frontend (régénération fichiers manquants)...');
                $this->log('ai', 'Aucun fichier frontend trouvé — regénération...');
                $frontendResult = $this->runFrontend($brief, $stackDecision, $architecture, $designSystem, $backendResult);
                $existingFiles = $this->scanFiles($buildDir); // Re-scan after generation
                $this->log('ok', 'Frontend regénéré : ' . count($frontendResult['files'] ?? []) . ' fichiers');
            } else {
                $this->log('ok', 'Frontend déjà présent, reprise directe');
            }

            // ── ÉTAPE 6: QA — Validation + Build + Fix Loop ────────
            $this->progress(75, 'QA Engineer : Inspection + Build + Correction (max 3 itérations)...');
            $this->log('test', 'Agent QA : Validation du code avec boucle de correction...');

            $qaResult = $this->qaFixLoop($brief, $stackDecision, $existingFiles);

            $score = $qaResult['overall_score'] ?? 0;
            $buildSuccess = $qaResult['build_success'] ?? false;
            $iterations = $qaResult['iterations'] ?? 1;

            $this->log('test', "Score qualité : $score/100 (itérations : $iterations)");
            $this->log('test', "Problèmes : " . count($qaResult['issues'] ?? []) . ", Corrections : " . count($qaResult['fixes'] ?? []));
            $this->log('test', "Build : " . ($buildSuccess ? '✅ RÉUSSI' : '❌ ÉCHEC'));

            if (!empty($qaResult['build_errors'])) {
                $this->log('warn', "⚠ Erreurs de build persistantes : " . count($qaResult['build_errors']));
                foreach (array_slice($qaResult['build_errors'], 0, 10) as $be) {
                    $this->log('warn', "  " . substr($be, 0, 200));
                }
            }

            updateProject($this->db, $this->projectId, [
                'qa_score' => $score,
                'file_count' => count($existingFiles),
                'build_validated' => $buildSuccess ? 1 : 0,
            ]);

            // ── ÉTAPE 7: DevOps ─────────────────────────────────────
            $this->progress(90, 'DevOps : Préparation du déploiement...');
            $this->log('ai', 'Agent DevOps : Configuration infrastructure...');
            $devopsResult = $this->runDevOps($brief, $stackDecision, $architecture);

            foreach (($devopsResult['files'] ?? []) as $f) $this->writeFile($f['filename'], $f['content']);
            $this->log('ok', 'DevOps prêt : Docker + CI/CD générés');

            // Finalisation
            $this->generateReadme($architecture, $stackDecision, $score);
            updateProject($this->db, $this->projectId, ['status' => 'done']);

            $this->progress(100, '✅ Projet terminé !');
            $this->log('ok', '═══════════════════════════════════════════════════');
            $this->log('ok', "✅ REPRISE TERMINÉE — Score QA : $score/100");
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
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
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
            $fileMsg = [
                ['role' => 'system', 'content' => $prompt . "\n\nGénère un fichier HTML unique. Réponse JSON : {\"filename\":\"index.html\",\"content\":\"...\",\"language\":\"html\"}"],
                ['role' => 'user', 'content' => json_encode([
                    'brief' => $brief, 'stack' => $stack, 'architecture' => $arch,
                    'design_system' => $design, 'backend' => $backend,
                    'pages' => $arch['frontend_pages'] ?? [],
                ])],
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
        if (in_array($front, ['next','react','vue','nuxt','svelte','angular','astro','remix','react_native','html_css_js'])
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

        $sandbox = new BuildSandbox($this->projectFolder);
        $this->log('sys', '═══ Validation Build via Sandbox (' . count($commands) . ' commandes) ═══');

        $stack = $this->detectStackType($stackDecision);

        foreach ($commands as $cmd) {
            $this->log('sys', "→ {$cmd['name']}...");

            $result = $sandbox->execute([
                'cmd' => $cmd['cmd'],
                'cwd' => $cmd['cwd'],
                'stack' => $stack,
                'timeout' => $cmd['optional'] ? 60 : 120,
            ]);

            $code = $result['code'];
            $outStr = implode("\n", array_slice($result['output'], -20));

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

        $sandbox->cleanup();

        return [
            'success' => empty(array_filter($errors, fn($e) => $e['severity'] === 'error')),
            'errors' => $errors,
        ];
    }

    private function detectStackType(array $stackDecision): string {
        $back = $stackDecision['stack_decision']['backend'] ?? '';
        return match (true) {
            in_array($back, ['fastapi_python', 'django_python']) => 'python',
            $back === 'go_gin' => 'go',
            $back === 'rust_actix' => 'rust',
            in_array($back, ['kotlin']) => 'kotlin',
            $stackDecision['stack_decision']['frontend'] === 'flutter' => 'flutter',
            default => 'node',
        };
    }

    // ═══════════════════════════════════════════════════════════════════
    //  VALIDATION DES IMPORTS — vérifie que les imports locaux existent
    // ═══════════════════════════════════════════════════════════════════

    private function validateFileImports(array $allFiles): array {
        $issues = [];
        $localExports = []; // resolvedPath => [named exports, default export]

        // Collect all exports
        foreach ($allFiles as $f) {
            $content = $f['content'] ?? '';
            $filename = $f['filename'] ?? '';
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

        // Check all imports
        $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder) . DIRECTORY_SEPARATOR;
        foreach ($allFiles as $f) {
            $content = $f['content'] ?? '';
            $filename = $f['filename'] ?? '';

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
    //  BOUCLE QA-FIX — max 3 itérations avec validation de build
    // ═══════════════════════════════════════════════════════════════════

    private function qaFixLoop(array $brief, array $stackDecision, array $allFiles, int $maxIterations = 3): array {
        $currentFiles = $allFiles;
        $allBuildErrors = [];
        $finalScore = 0;
        $finalIssues = [];
        $finalFixes = [];

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $this->log('sys', "═══ QA-Fix Itération " . ($iteration + 1) . "/{$maxIterations} ═══");

            // Step 1: Run QA
            $qaResult = $this->runQA($brief, $stackDecision, $currentFiles);
            $score = $qaResult['overall_score'] ?? 0;
            $issues = $qaResult['issues'] ?? [];
            $fixes = $qaResult['fixes'] ?? [];
            $finalScore = $score;
            $finalIssues = $issues;

            $this->log('test', "Score qualité : $score/100");
            $this->log('test', "Problèmes : " . count($issues) . ", Corrections : " . count($fixes));

            // Apply fixes
            foreach ($fixes as $fix) {
                if (!empty($fix['file']) && !empty($fix['content'])) {
                    $this->writeFile($fix['file'], $fix['content']);
                    $this->log('heal', "🔧 Correction appliquée : {$fix['file']}");
                    // Update in-memory files
                    foreach ($currentFiles as &$cf) {
                        if (($cf['filename'] ?? '') === $fix['file']) {
                            $cf['content'] = $fix['content'];
                            break;
                        }
                    }
                }
            }

            // Step 2: Re-scan files from disk (in case writeFile changed things)
            $buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder);
            $currentFiles = $this->scanFiles($buildDir);

            // Step 3: Validate imports
            $importErrors = $this->validateFileImports($currentFiles);
            if (!empty($importErrors)) {
                $this->log('warn', "⚠ Problèmes d'imports détectés : " . count($importErrors));
                foreach ($importErrors as $ie) {
                    $this->log('warn', "  {$ie['file']} → {$ie['import']}: {$ie['issue']}");
                }
            }

            // Step 4: Run build validation
            $buildResult = $this->runBuildValidation($stackDecision);
            $buildErrors = $buildResult['errors'] ?? [];

            if ($buildResult['success'] && empty($importErrors)) {
                $this->log('ok', '✅ Build validé avec succès !');
                $finalFixes = $fixes;
                break;
            }

            // Collect errors for next iteration
            $errorSummary = [];
            foreach ($buildErrors as $be) {
                $errorSummary[] = "[{$be['command']}] {$be['output']}";
            }
            foreach ($importErrors as $ie) {
                $errorSummary[] = "[import] {$ie['file']}: {$ie['issue']}";
            }
            $allBuildErrors = $errorSummary;

            if ($iteration < $maxIterations - 1) {
                // Feed errors back into QA context for the next iteration
                $this->log('sys', "🔄 Réinjection des erreurs dans le QA...");
                // Create a temporary fix file that QA can see next time
                $errorFile = '_build_errors.json';
                $this->writeFile($errorFile, json_encode(['build_errors' => $allBuildErrors, 'iteration' => $iteration + 1], JSON_PRETTY_PRINT));
                // Re-scan to include the error file
                $currentFiles = $this->scanFiles($buildDir);
            }
        }

        // Clean up error file
        $errorFilePath = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($this->projectFolder) . DIRECTORY_SEPARATOR . '_build_errors.json';
        if (file_exists($errorFilePath)) unlink($errorFilePath);

        return [
            'overall_score' => $finalScore,
            'issues' => $finalIssues,
            'fixes' => $finalFixes,
            'build_errors' => $allBuildErrors,
            'iterations' => $iteration + 1,
            'build_success' => empty(array_filter($buildResult['errors'] ?? [], fn($e) => $e['severity'] === 'error')),
        ];
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
