# Projet : AutoCoder V4

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

### MEDIUM
6. **`$db` hors scope dans `runWorkerLoop()`** — `worker.php:79,85` — `$db ?? getDB()` toujours `getDB()`. **Fix** : supprimer `$db ??`.
7. **`ob_flush()` sans guard SSE** — `api.php:291`. **Fix** : ajouter `if (ob_get_level())`.
8. **`enqueueProject()` redondant** — `api.php:224` puis `background_build.php:29` supprime et ré-enqueue. **Fix** : supprimer l'appel dans api.php.
9. **`test_key` toujours Mistral** — `api.php:96-110` — Ignore le provider. **Fix** : utiliser l'URL du provider.
10. **`validateStackItem` retourne `''`** — `api.php:57-65` — Écrase les defaults DB. **Fix** : retourner la valeur par défaut.
11. **`loadAgentPrompt` pas de check** — `engine.php:80` — `file_get_contents` peut retourner false. **Fix** : logger l'erreur.
12. **`$buildResult` non initialisé** — `engine.php:1153`. **Fix** : initialiser avant la boucle.

### LOW
13. **Regex sandbox inefficace** — `sandbox.php:91-98` — Tous les métacaractères shell autorisés.
14. **Nom container Docker** — `sandbox.php:49` — Collision possible. **Fix** : ajouter `bin2hex(random_bytes(4))`.
15. **`curl_init()` non vérifié** — `models.php:111`, `api.php:98`.
16. **Path traversal ZIP** — `api.php:300-330`.
17. **Redondance background_build** — `background_build.php:44-53`.
18. **`start /B` titre Windows** — `api.php:231`, `worker.php:100`.
19. **ZipArchive non fermé sur erreur** — `api.php:310-322`.

---

## Prochaines étapes possibles
1. Corriger les bugs HIGH (design 3x, SQLi, off-by-one, Windows worker, dead code)
2. Tester le pipeline end-to-end avec un vrai build
3. Ajouter un mode CLI
