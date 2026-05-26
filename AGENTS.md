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
php tests/all.php   # 40 tests, 5 suites
```

| Suite | Tests | Description |
|-------|-------|-------------|
| `tests/test_engine.php` | 4 | Pipeline IA : runCTO, runArchitect, runDesigner, runQA (mock) |
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

**V4.17c** — `HEAD` — Nettoyage repo + refactoring prompts premium

### Historique des commits
| Hash | Sujet |
|------|-------|
| `5bb0a0d` | Nettoyage projet : suppression V2, V3, fichiers orphelins racine, .gitignore |
| `cbea222` | V4.17 suite — refactoring moteur + prompts agents CTO/QA/DevOps + optimisation BDD |
| `4b2dd90` | V4.17 Prompts premium : Designer unique, upload image, compliance forcée |
| `1b236a9` | V4.16 Correction prompts + engine pour builds blog réels validés |

### Nettoyage effectué
- **V2/ et V3/** supprimés (versions obsolètes suivies dans git)
- **Fichiers orphelins racine** supprimés : `api.php`, `index.php`, `build_test.php`, `check_db.php`, `save.php`
- **V4/V4/**, **V4/templates/**, **V4/data.db** nettoyés
- **V4/builds/** vidé et supprimé (recréé automatiquement par `writeFile()`)
- **.gitignore** enrichi : couvre `*.sqlite`, `V2/`, `V3/`, fichiers orphelins

### Améliorations prompts (V4.17)
| Prompt | Changement clé |
|--------|----------------|
| `designer.md` | Palette UNIQUE obligatoire (interdiction des 3 exemples), 4 @keyframes, Google Fonts, image upload dans design system |
| `frontend.md` | Compliance designer FORCÉE (couleurs, animations, polies), `<ImageUpload>` drag & drop, variables CSS du designer |
| `backend.md` | `POST /api/upload` multer 5MB, `image_url` sur Article, suppression fichier, dossier `uploads/` statique |

### Améliorations engine (V4.16-17)
- `runRepair()` : 1 appel API au lieu de ~15 (réparation ciblée au lieu de regénération complète)
- `runFrontend()` : scaffolding config_files, force main.tsx + App.tsx
- `fixImportExportMismatch()` : gère named + default exports
- `detectBuildCommands()` : cherche package.json dans backend/ + frontend/
- `maxRepairIterations` : 3 → 2

### Build blog-pro-final-32f1 validé
- ✅ 20 fichiers source (10 backend + 10 frontend)
- ✅ Backend compile (TypeScript → dist/, 0 erreurs)
- ✅ Frontend compile (Vite, 43 modules, 10.68s)
- ✅ Serveur démarre sur port 3000
- ✅ Login API fonctionnel (admin@blog.com / admin123)
- ❌ **Design non-premium** : palette générique, pas d'upload image, UX basique (cause : prompts trop permissifs, corrigé dans V4.17)

---

## État actuel du repo

```
/ (racine)
├── AGENTS.md          ← ce fichier
├── opencode.json      ← config opencode
├── .gitignore         ← enrichi
└── V4/
    ├── index.php      ← UI
    ├── engine.php     ← pipeline 7 agents
    ├── api.php        ← REST API
    ├── config.php     ← constantes
    ├── db.php         ← SQLite
    ├── models.php     ← routeur IA
    ├── queue.php      ← job queue
    ├── worker.php     ← worker parallèle
    ├── job_runner.php ← exécution jobs
    ├── background_build.php
    ├── background_resume.php
    ├── cli.php        ← mode CLI
    ├── sandbox.php    ← exécution sécurisée
    ├── helpers.php    ← utilitaires
    ├── agents/*.md    ← prompts des 7 agents
    ├── assets/        ← JS + CSS
    ├── tests/         ← 40 tests, 5 suites
    └── builds/        ← (créé automatiquement)
```

---

## Session context (pour prochain agent)

### Problème principal
Les agents IA (Designer, Frontend) ne produisent pas de design premium malgré la recherche web. Les palettes sont recyclées, l'UX est basique, pas d'upload image.

### Cause identifiée
Les prompts `designer.md` et `frontend.md` étaient trop permissifs. Le designer avait 3 palettes d'exemple qu'il copiait toujours. Le frontend n'était pas forcé d'utiliser les specs du designer.

### Correction appliquée (V4.17 prompts)
- Designer : palette UNIQUE obligatoire, specs détaillées (composants, animations, upload)
- Frontend : compliance STRICTE au designer (couleurs, animations, polies)
- Backend : endpoint upload + champ image_url

### Ce qui reste à faire
1. **Tester un nouveau build** avec les prompts V4.17 pour vérifier que le rendu est premium
2. **Vérifier que la recherche web** est utilisée par les agents pour innover
3. **Si le rendu reste basique**, renforcer encore les prompts ou ajouter de la mémoire inter-build pour forcer l'originalité
4. **Ne PAS modifier manuellement les builds** — les corrections doivent passer par les prompts

### Rappel technique
- `php tests/all.php` → 40/40 tests
- `AC4_BUILDS_DIR` = `V4/builds/` (créé auto par `writeFile()`)
- `run()` = pipeline synchrone, `runJob()` = pipeline asynchrone
- Les deux utilisent `runRepair()` maintenant
- Multi-provider : Mistral (devstral-2512), OpenAI, Claude, Gemini
- Windows : `node_modules/` ralentit `Get-ChildItem -Recurse`, utiliser `-Exclude`
