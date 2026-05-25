# AkrourCoder V4

**Description** : Générateur de code IA full-stack autonome. Pipeline 7 agents (CTO → Architecte → Designer → Backend || Frontend → QA → DevOps) via API multi-provider (Mistral/OpenAI/Claude/Gemini).

**Stack** : PHP 8.3, SQLite, HTML/CSS/JS natif

---

## Fichiers clés

| Fichier | Rôle |
|---------|------|
| `V4/index.php` | Interface utilisateur (sidebar + tabs + preview + dashboard) |
| `V4/engine.php` | Pipeline IA — orchestration des 7 agents + QA loop |
| `V4/api.php` | API REST (keys, projects, build, stats, download, cleanup) |
| `V4/config.php` | Configuration et constantes multi-provider |
| `V4/db.php` | Base SQLite (schema + helpers + stats enrichies) |
| `V4/models.php` | Routeur IA multi-providers (Mistral/OpenAI/Claude/Gemini) |
| `V4/queue.php` | Job queue SQLite avec dépendances |
| `V4/worker.php` | Worker parallèle (max 2 simultanés) |
| `V4/job_runner.php` | Exécute un job individuel du pipeline |
| `V4/background_build.php` | Entry point build arrière-plan |
| `V4/background_resume.php` | Reprise d'un build interrompu |
| `V4/cli.php` | Mode CLI (build sans navigateur) |
| `V4/sandbox.php` | Exécution sécurisée (Docker ou local) |
| `V4/helpers.php` | Fonctions partagées (slugify, webSearch, validation) |
| `V4/assets/app.js` | JavaScript UI |
| `V4/assets/style.css` | Styles CSS (dark + light theme) |
| `V4/agents/*.md` | Prompts des 7 agents |
| `V4/tests/framework.php` | Framework de test unitaire |
| `V4/tests/all.php` | Runner master (php tests/all.php) |

---

## Fonctionnalités

### Pipeline IA
- 7 agents spécialisés avec contexte croisé
- Job queue parallélisée (Backend + Frontend simultanés)
- File d'attente SQLite avec reprise sur erreur (retry x2 puis failed)
- Multi-provider : Mistral AI, OpenAI, Claude, Gemini (fallback automatique)
- Boucle de correction QA **avec injection réelle des issues** dans les agents backend/frontend
- Cache web search intra-build : 1 appel DuckDuckGo, 4 lectures cache
- Fallback polling si SSE perdu
- **Mémoire persistante** : chaque agent stocke ses décisions (`agent_memories`), consultables entre projets
- **Recherche web** : agents consultent DuckDuckGo avant chaque décision (stack, architecture, tendances UI)
- **Autocorrection imports** : détection et correction automatique des mismatch `import { X }` / `export default X`

### Interface
- Sidebar avec 4 onglets (Build, Keys, Projects, Dashboard)
- Dashboard avec métriques enrichies : builds validés/échoués/en cours, fichiers générés, clés, tokens
- Recherche projets avec pagination (20/page)
- Détail projet : infos stack, logs, preview iframe
- Bouton 🧹 Cleanup vieux builds (>30j) + orphelins
- Thème dark/light avec persistance localStorage
- Terminal de logs en direct

### Projets
- CRUD complet (création via formulaire, suppression)
- Re-build (efface tout et recommence)
- Resume (reprend un build interrompu sans perdre les fichiers)
- Export ZIP (manifest.json + fichiers source)
- Import ZIP (restauration complète projet + logs + fichiers)
- Build timer (durée affichée dans le détail)
- Notifications navigateur + son à la fin du build
- **file_count fiable** : exclut node_modules, .next, vendor, .git, .cache, __pycache__, .venv, target

### Sécurité
- Toutes les requêtes SQL utilisent des requêtes préparées (plus d'injection)
- `scanFiles()` filtre les dossiers système exclus
- Sandbox Docker avec fallback local sécurisé

### Mode CLI
```bash
php cli.php --prompt="Crée un blog" [options]
# Options : --title, --type, --frontend, --backend, --database, --css, --lang, --wait
```

---

## Tests

```bash
php tests/all.php   # 36 tests, 4 suites
```

| Suite | Tests | Description |
|-------|-------|-------------|
| `tests/test_helpers.php` | 9 | validateProjectType, validateStackItem, slugify |
| `tests/test_config.php` | 10 | Providers, agents, stacks, frontends, backends, langues, chemins |
| `tests/test_queue.php` | 9 | enqueue, claim, complete, fail/retry, cancel, allDone |
| `tests/test_db.php` | 8 | createProject, updateProject, appendLog, stats, keys, memories, search |

Framework : `tests/framework.php` — fonctions `test()`, `assert_eq()`, `assert_true()`

---

## Pipeline de données

```
CTO (stack) → Architect (BDD/API/pages) → Designer (UI/UX)
                                                 ↓
                                    ┌───────────────────────┐
                                    ↓                       ↓
                               Backend (API)          Frontend (UI)
                                    ↓                       ↓
                                    └───────────────────────┘
                                                 ↓
                                            QA (build + code quality)
                                                 ↓
                                           DevOps (Docker/CI)
```

Le QA loop injecte les issues détectées comme `$brief['qa_feedback']` dans les agents Backend et Frontend pour correction.

---

## Dernier commit

**V4.13** — `HEAD` — Tests engine with mock, setAI injection, 40 tests

### Nouveautés
- **Tests mockés pour le pipeline engine** (`tests/test_engine.php`) : 4 tests qui valident le pipeline complet sans clé API
- **`PipelineEngine::setAI()`** ajouté pour injection de dépendance — permet de remplacer `AIModel` par un mock dans les tests
- **Constructeur `PipelineEngine(?AIModel $ai = null)`** accepte un AIModel optionnel
- **MockAIModel** : classe qui étend AIModel et retourne des réponses prédéfinies par étape
- **Master runner** : `tests/all.php` inclut désormais la suite Pipeline Engine

### Tests ajoutés (4)
1. **Pipeline complet — 7 agents** : CTO → Architect → Designer → Backend → Frontend → QA → DevOps, vérifie l'exécution sans erreur, le status 'done', qa_score=100, et la génération des fichiers
2. **Boucle QA — réparation** : QA renvoie score 70 (< 95), vérifie que le pipeline déclenche la réparation (backend+frontend re-générés), limite à 2 appels QA
3. **Erreur API — échec propre** : simulateur de panne API (CTO OK, architect manquant), vérifie que le pipeline retourne `success: false` sans crash
4. **Ordre des appels AI** : vérifie que les 7 premiers appels suivent l'ordre exact des agents

### Fichiers impactés (V4.13)
| Fichier | Changements |
|---------|-------------|
| `engine.php` | Constructeur accepte `?AIModel`, setAI() ajouté |
| `tests/test_engine.php` | Nouveau : MockAIModel + 4 tests pipeline |
| `tests/all.php` | Suite `Pipeline Engine` ajoutée |
| `AGENTS.md` | Changelog V4.13 mis à jour |

---

## Prochaines étapes possibles
1. **Tester le pipeline end-to-end avec un vrai build** (nécessite clé API Mistral fonctionnelle)
2. Ajouter un test unitaire pour la boucle QA avec mock
3. Implémenter le mode démon pour le worker (`worker.php --daemon`)
4. Améliorer la gestion d'erreur API avec fallback provider automatique
5. Ajouter un endpoint SSE temps réel pour remplacer le polling
