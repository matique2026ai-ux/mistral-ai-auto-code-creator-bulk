# Projet : AkrourCoder V4

**Description** : Générateur de code IA full-stack autonome écrit en PHP. Pipeline de 7 agents spécialisés (CTO → Architecte → Designer → Backend || Frontend → QA → DevOps) via API Mistral AI.

**Stack** : PHP 8.3, SQLite, HTML/CSS/JS natif, API Mistral AI (multi-provider fallback)

---

## Fichiers clés
- `V4/index.php` — Interface utilisateur (sidebar + tabs)
- `V4/engine.php` — Pipeline IA (orchestration des 7 agents + job queue)
- `V4/api.php` — API REST (actions: keys, projects, build, SSE, stats, download)
- `V4/config.php` — Configuration et constantes
- `V4/db.php` — Base SQLite (schema + helpers: getGlobalStats, appendLog, etc.)
- `V4/models.php` — Routeur IA multi-providers (Mistral/OpenAI/Claude/Gemini)
- `V4/queue.php` — Job queue SQLite (enqueue, getReadyJobs, claimJob, completeJob, failJob)
- `V4/worker.php` — Worker parallèle (dispatche les jobs en enfants)
- `V4/job_runner.php` — Exécute un job individuel
- `V4/background_build.php` — Entry point build (enqueue + worker loop)
- `V4/sandbox.php` — Exécution sécurisée (Docker ou local)
- `V4/assets/app.js` — JavaScript UI
- `V4/assets/style.css` — Styles CSS
- `V4/agents/*.md` — Prompts des 7 agents

---

## Dernier commit
**V4.8** — `8cdb054` — Optimisation génération (tokens réduits 30-50%, max 3 fichiers frontend, instructions compact)

### Historique des commits récents
- `0a049ef` V4.5: Multi-modèle, pipeline parallèle, SSE, sécurité, refonte UI
- `5dae081` V4.6: Prompts agents enrichis (contexte croisé, règles strictes, français)
- `82cf793` V4.7: Dashboard monitoring (stats enrichies, bar charts)
- `8cdb054` V4.8: Optimisation génération code

---

## Bugs connus (audit complet fait, 24 trouvailles)

### ✅ HIGH — Corrigés dans `6c3ba0d`
1. ~~Designer appelé 3x~~ → Persisté en DB (`design_json`), cache dans `getDesignSystem()`
2. ~~SQL injection~~ → Toutes les requêtes remplacées par `prepare()`/`execute()`
3. ~~Off-by-one QA~~ → `min($iteration + 1, $maxIterations)`
4. ~~Windows waitForChild~~ → Supprimé (les jobs DB gèrent déjà l'état)
5. ~~localExportCache dead code~~ → Condition inutile supprimée

### ✅ MEDIUM — Corrigés dans `6c3ba0d`
6. ~~`$db` hors scope~~ → `getDB()` direct, `$db ??` supprimé
7. ~~`ob_flush()` sans guard~~ → `if (ob_get_level())` ajouté
8. ~~`enqueueProject()` redondant~~ => Supprimé de api.php (background_build.php gère tout)
9. ~~`test_key` toujours Mistral~~ → Utilise l'URL et le format du provider sélectionné
10. ~~`validateStackItem` retourne `''`~~ → Fallback `?: 'react'|'node_express'|etc.` dans create_project
11. ~~`loadAgentPrompt` pas de check~~ → `is_file`/`is_readable` + log d'erreur
12. ~~`$buildResult` non initialisé~~ → Initialisé avant la boucle

### ✅ LOW — Corrigés dans `6c3ba0d`
13. ~~Regex sandbox inefficace~~ → Regex trompeuse supprimée
14. ~~Nom container Docker~~ → `bin2hex(random_bytes(4))` ajouté
15. ~~`curl_init()` non vérifié~~ → `throw` si false
16. ~~Path traversal ZIP~~ → `realpath()` + `strpos()` vérifié avant addFile
17. ~~Redondance background_build~~ → Simplifié, suppression des updateProject redondants
18. ~~`start /B` titre Windows~~ → Titre vide explicite `""`
19. ~~ZipArchive non fermé~~ → `try/finally` autour de l'ajout des fichiers

---

## Prochaines étapes possibles
1. Corriger les bugs HIGH (design 3x, SQLi, off-by-one, Windows worker, dead code)
2. Tester le pipeline end-to-end avec un vrai build
3. Ajouter un mode CLI
