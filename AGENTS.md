# Projet : AutoCoder V4

**Description** : Générateur de code IA full-stack autonome écrit en PHP. Il utilise l'API Mistral AI pour orchestrer une pipeline de 7 agents spécialisés (CTO, Architecte, Designer, Backend, Frontend, QA, DevOps) qui, à partir d'une description en langage naturel, génère et valide automatiquement des applications complètes prêtes à être déployées (web, mobile, API) dans des stacks variées (React, Next.js, Vue, Flutter, Python FastAPI, Go Gin, Laravel, etc.).

**Fichiers clés** :
- `V4/index.php` — Interface utilisateur
- `V4/engine.php` — Pipeline IA (orchestration des 7 agents)
- `V4/api.php` — API REST
- `V4/config.php` — Configuration et constantes
- `V4/db.php` — Base SQLite
- `V4/agents/` — Prompts des agents (markdown)
- `V4/master_prompt_v4.txt` — Instructions du projet

**Stack** : PHP 8.3, SQLite, HTML/CSS/JS natif, API Mistral AI (modèle `devstral-2512`)
