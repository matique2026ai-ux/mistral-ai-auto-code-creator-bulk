# AkrourCoder V4

**Description** : Générateur de code IA full-stack autonome. Pipeline 7 agents (CTO → Architecte → Designer → Backend || Frontend → QA → DevOps) via API multi-provider (Mistral/OpenAI/Claude/Gemini).

**Stack** : PHP 8.3, SQLite, HTML/CSS/JS natif

---

## Fichiers clés

| Fichier | Rôle |
|---------|------|
| `V4/index.php` | Interface utilisateur (sidebar + tabs + preview) |
| `V4/engine.php` | Pipeline IA — orchestration des 7 agents |
| `V4/api.php` | API REST (keys, projects, build, SSE, stats, download, export, import, cleanup) |
| `V4/config.php` | Configuration et constantes |
| `V4/db.php` | Base SQLite (schema + helpers) |
| `V4/models.php` | Routeur IA multi-providers |
| `V4/queue.php` | Job queue SQLite |
| `V4/worker.php` | Worker parallèle |
| `V4/job_runner.php` | Exécute un job individuel |
| `V4/background_build.php` | Entry point build (enqueue + worker loop) |
| `V4/background_resume.php` | Reprise d'un build interrompu |
| `V4/cli.php` | Mode CLI (build sans navigateur) |
| `V4/sandbox.php` | Exécution sécurisée (Docker ou local) |
| `V4/assets/app.js` | JavaScript UI |
| `V4/assets/style.css` | Styles CSS (dark + light theme) |
| `V4/agents/*.md` | Prompts des 7 agents |

---

## Fonctionnalités

### Pipeline IA
- 7 agents spécialisés avec contexte croisé
- Job queue parallélisée (workers simultanés)
- File d'attente SQLite avec reprise sur erreur
- Multi-provider : Mistral AI, OpenAI, Claude, Gemini (fallback automatique)
- Boucle de correction QA (max 5 itérations, score cible 95%)
- SSE (Server-Sent Events) pour logs en temps réel
- Fallback polling si SSE perdu

### Interface
- Sidebar avec 4 onglets (Build, Keys, Projects, Dashboard)
- Dashboard avec métriques, graphiques tokens, top projets
- Recherche projets avec pagination (20/page)
- Détail projet : infos stack, logs, preview iframe
- Thème dark/light avec persistance localStorage
- Terminal de logs en direct

### Projets
- CRUD complet (création via formulaire, suppression)
- Re-build (efface tout et recommence)
- Resume (reprend un build interrompu sans perdre les fichiers)
- Export ZIP (manifest.json + fichiers source)
- Import ZIP (restauration complète projet + logs + fichiers)
- Nettoyage auto : dossiers orphelins + vieux builds (>30 jours)
- Build timer (durée affichée dans le détail)
- Notifications navigateur + son à la fin du build

### Mode CLI
```bash
php cli.php --prompt="Crée un blog" [options]
# Options : --title, --type, --frontend, --backend, --database, --css, --lang, --wait
```

---

## Dernier commit

**V4.9** — `684e15d` — Theme toggle, Mode CLI, Build timer

### Historique
| Commit | Version | Description |
|--------|---------|-------------|
| `0a049ef` | V4.5 | Multi-modèle, pipeline parallèle, SSE, sécurité, refonte UI |
| `5dae081` | V4.6 | Prompts agents enrichis (contexte croisé, règles strictes, français) |
| `82cf793` | V4.7 | Dashboard monitoring (stats enrichies, bar charts) |
| `8cdb054` | V4.8 | Optimisation génération code (tokens -30%, max 3 fichiers frontend) |
| `6c3ba0d` | V4.8.1 | Correctif 24 bugs (SQLi, design 3x, off-by-one, etc.) |
| `dc66c9d` | V4.8.2 | Renommage AutoCoder → AkrourCoder |
| `7597e24` | V4.8.3 | Re-build + Resume |
| `e473ac6` | V4.8.4 | Export/Import projets + Recherche + Notifications |
| `2678ac7` | V4.8.5 | Pagination projets (20/page) |
| `b5e7137` | V4.8.6 | Nettoyage auto builds |
| `684e15d` | V4.9 | Theme toggle dark/light, Mode CLI, Build timer |

---

## Bugs (résolus)

24 bugs corrigés dans `6c3ba0d` (HIGH: 5, MEDIUM: 7, LOW: 12) — SQL injection, Designer appelé 3×, off-by-one QA, Windows worker, path traversal ZIP, etc. Voir le commit pour le détail.

---

## Prochaines étapes possibles
1. Tester le pipeline end-to-end avec un vrai build
