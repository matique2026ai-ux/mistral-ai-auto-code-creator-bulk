<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
$db = getDB();
$stats = getGlobalStats($db);
$stacks = json_decode(AC4_STACKS, true);
$frontends = json_decode(AC4_FRONTENDS, true);
$backends = json_decode(AC4_BACKENDS, true);
$databases = json_decode(AC4_DATABASES, true);
$css_frameworks = json_decode(AC4_CSS, true);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AutoCoder V4 — Full-Stack AI Architect</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #050508;
  --surface: #0a0b10;
  --card: #0f1118;
  --card-hover: #141720;
  --border: #1c1f2e;
  --border-light: #282d42;
  --primary: #6366f1;
  --primary-dim: rgba(99,102,241,0.12);
  --primary-glow: rgba(99,102,241,0.25);
  --accent: #00e5c3;
  --accent-dim: rgba(0,229,195,0.12);
  --orange: #f59e0b;
  --pink: #ec4899;
  --text: #f1f3f9;
  --text-2: #a0a5b8;
  --text-3: #585e75;
  --success: #22c55e;
  --error: #ef4444;
  --warning: #f59e0b;
  --info: #3b82f6;
  --radius: 12px;
  --radius-sm: 8px;
  --radius-lg: 16px;
  --font: 'Inter', -apple-system, sans-serif;
  --mono: 'JetBrains Mono', monospace;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
}
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 800px 600px at 10% 10%, rgba(99,102,241,0.03), transparent),
    radial-gradient(ellipse 600px 800px at 90% 90%, rgba(0,229,195,0.03), transparent);
  pointer-events: none;
  z-index: 0;
}
.app { display: grid; grid-template-columns: 380px 1fr 420px; height: 100vh; position: relative; z-index: 1; }
@media (max-width: 1300px) { .app { grid-template-columns: 340px 1fr; } .preview { display: none; } }
@media (max-width: 900px) { .app { grid-template-columns: 1fr; } .sidebar { display: none; } }

/* ─── SIDEBAR ─── */
.sidebar {
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column; height: 100vh; overflow-y: auto;
}
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.sb-header {
  padding: 20px 24px; border-bottom: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 6px;
}
.logo { font-size: 1.4rem; font-weight: 800; letter-spacing: -1px; display: flex; align-items: center; gap: 10px; }
.logo span { background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.logo small { font-size: .65rem; font-family: var(--mono); color: var(--text-3); letter-spacing: .1em; -webkit-text-fill-color: var(--text-3); background: none; padding: 2px 8px; border: 1px solid var(--border); border-radius: 99px; }
.sb-body { padding: 20px; display: flex; flex-direction: column; gap: 16px; flex: 1; }

.card {
  background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px;
  transition: border-color .3s, box-shadow .3s;
}
.card:hover { border-color: var(--border-light); }
.card-title {
  font-size: .7rem; font-family: var(--mono); color: var(--primary);
  letter-spacing: .12em; text-transform: uppercase; margin-bottom: 14px;
  display: flex; align-items: center; gap: 8px;
}
.card-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, var(--border), transparent); }

.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: .75rem; font-weight: 500; color: var(--text-2); margin-bottom: 5px; }
select, input, textarea {
  width: 100%; background: var(--bg); border: 1px solid var(--border);
  color: var(--text); padding: 9px 12px; border-radius: var(--radius-sm);
  font-family: var(--font); font-size: .85rem; outline: none;
  transition: border-color .2s, box-shadow .2s;
}
select:focus, input:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-dim); }
textarea { resize: vertical; min-height: 60px; font-size: .82rem; }

/* ─── BUTTONS ─── */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 10px 18px; border-radius: var(--radius-sm); font-weight: 600;
  font-size: .85rem; cursor: pointer; transition: all .25s; border: none;
  font-family: var(--font);
}
.btn:disabled { opacity: .4; cursor: not-allowed; transform: none !important; }
.btn-primary {
  background: linear-gradient(135deg, var(--primary), #8b5cf6); color: #fff;
}
.btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px var(--primary-dim); }
.btn-accent { background: var(--accent); color: #000; font-weight: 700; }
.btn-accent:hover:not(:disabled) { background: #00ffda; transform: translateY(-2px); box-shadow: 0 6px 20px var(--accent-dim); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-2); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.btn-danger { background: transparent; border: 1px solid rgba(239,68,68,0.3); color: var(--error); }
.btn-danger:hover { background: rgba(239,68,68,0.1); }
.btn-sm { padding: 6px 12px; font-size: .75rem; }
.btn-lg { padding: 14px 24px; font-size: .95rem; }
.w-full { width: 100%; }

.launch-btn {
  width: 100%; padding: 14px; font-size: 1rem; font-weight: 700;
  border-radius: var(--radius-sm);
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: #000; cursor: pointer; border: none;
  transition: all .3s; box-shadow: 0 4px 20px rgba(99,102,241,0.2);
}
.launch-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(99,102,241,0.35); }
.launch-btn:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }

/* ─── MAIN ─── */
.main {
  display: flex; flex-direction: column; height: 100vh; overflow-y: auto;
  border-right: 1px solid var(--border);
}
.topbar {
  padding: 14px 24px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  background: rgba(10,11,16,0.8); backdrop-filter: blur(12px);
  position: sticky; top: 0; z-index: 10;
}
.topbar h2 { font-size: 1rem; font-weight: 700; }
.topbar p { font-size: .72rem; color: var(--text-2); }

.badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 99px; font-family: var(--mono);
  font-size: .7rem; font-weight: 600;
}
.badge-primary { background: var(--primary-dim); color: var(--primary); border: 1px solid rgba(99,102,241,0.2); }
.badge-accent { background: var(--accent-dim); color: var(--accent); }
.badge-ok { background: rgba(34,197,94,0.1); color: var(--success); }
.badge-err { background: rgba(239,68,68,0.1); color: var(--error); }

/* ─── PIPELINE ─── */
.pipeline-box {
  margin: 20px 20px 0; padding: 18px;
  background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg);
}
.progress-row {
  display: flex; justify-content: space-between; font-size: .8rem;
  color: var(--text-2); margin-bottom: 8px;
}
.progress-bg { height: 6px; background: var(--bg); border-radius: 99px; overflow: hidden; margin-bottom: 14px; }
.progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, var(--primary), var(--accent)); transition: width .4s ease; }

.agents-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
.agent-chip {
  display: flex; align-items: center; gap: 8px; padding: 7px 12px;
  border-radius: var(--radius-sm); font-size: .75rem;
  background: var(--bg); border: 1px solid var(--border);
  color: var(--text-3); transition: all .3s;
}
.agent-chip.active { border-color: var(--primary); color: var(--primary); background: var(--primary-dim); }
.agent-chip.done { border-color: var(--success); color: var(--success); background: rgba(34,197,94,0.08); }
.agent-chip.failed { border-color: var(--error); color: var(--error); background: rgba(239,68,68,0.08); }
.agent-num { font-family: var(--mono); font-size: .7rem; opacity: .5; }
.agent-badge {
  width: 20px; height: 20px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .6rem; font-weight: 700; margin-left: auto; flex-shrink: 0;
  background: var(--primary); color: #000;
}

/* ─── TERMINAL ─── */
.terminal {
  margin: 0 20px 20px; display: flex; flex-direction: column; flex: 1;
  background: #030408; border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;
}
.term-header {
  padding: 10px 18px; background: #080a0e; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.term-dots { display: flex; gap: 6px; }
.term-dot { width: 10px; height: 10px; border-radius: 50%; }
.term-title { font-family: var(--mono); font-size: .7rem; color: var(--text-3); }
.term-body {
  padding: 14px; overflow-y: auto; font-family: var(--mono);
  font-size: .8rem; line-height: 1.7; flex: 1; min-height: 300px;
  background: #020307;
}
.term-body::-webkit-scrollbar { width: 4px; }
.term-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

.log-row { margin-bottom: 3px; display: flex; gap: 8px; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
.log-time { color: #2a2f45; min-width: 70px; }
.log-tag {
  padding: 0 6px; border-radius: 3px; font-size: .65rem; font-weight: 700;
  text-transform: uppercase; min-width: 55px; text-align: center;
}
.tag-sys { color: var(--info); background: rgba(59,130,246,0.1); }
.tag-ai { color: var(--primary); background: var(--primary-dim); }
.tag-ok { color: var(--success); background: rgba(34,197,94,0.1); }
.tag-err { color: var(--error); background: rgba(239,68,68,0.1); }
.tag-write { color: var(--accent); background: var(--accent-dim); }
.tag-test { color: var(--warning); background: rgba(245,158,11,0.1); }
.tag-heal { color: var(--pink); background: rgba(236,72,153,0.1); }
.log-msg { color: #b0b8cf; flex: 1; word-break: break-word; white-space: pre-wrap; }

/* ─── PREVIEW ─── */
.preview {
  background: var(--surface); display: flex; flex-direction: column; height: 100vh;
}
.preview-header {
  padding: 14px 20px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.preview-frame { flex: 1; background: #1a1a1a; position: relative; }
.preview-frame iframe { width: 100%; height: 100%; border: none; background: #fff; }

/* ─── TABS ─── */
.tabs { display: flex; background: var(--bg); padding: 3px; border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 16px; }
.tab-btn { flex: 1; padding: 7px; font-size: .8rem; background: transparent; border: none; color: var(--text-3); border-radius: 5px; cursor: pointer; font-family: var(--font); transition: all .2s; }
.tab-btn.active { background: var(--card); color: var(--primary); font-weight: 600; }
.panel { display: none; }
.panel.active { display: block; }

/* ─── KEYS / PROJECTS LIST ─── */
.item-list { display: flex; flex-direction: column; gap: 8px; }
.item-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px; background: var(--bg); border: 1px solid var(--border);
  border-radius: var(--radius-sm); font-size: .8rem; transition: border-color .2s;
}
.item-row:hover { border-color: var(--border-light); }
.pill { display: inline-flex; padding: 2px 8px; border-radius: 99px; font-size: .68rem; font-weight: 600; font-family: var(--mono); }
.pill-green { background: rgba(34,197,94,0.1); color: var(--success); }
.pill-red { background: rgba(239,68,68,0.1); color: var(--error); }
.pill-blue { background: rgba(59,130,246,0.1); color: var(--info); }
.empty-state { text-align: center; padding: 30px; color: var(--text-3); font-size: .82rem; }

/* ─── PROJECT DETAIL ─── */
#projectDetail { display: none; }
.project-info { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.project-info-item { padding: 8px 12px; background: var(--bg); border-radius: var(--radius-sm); }
.project-info-item .label { font-size: .65rem; color: var(--text-3); text-transform: uppercase; font-family: var(--mono); }
.project-info-item .value { font-size: .85rem; font-weight: 600; margin-top: 2px; }
.stack-tag {
  display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px;
  background: var(--primary-dim); color: var(--primary); border-radius: 99px;
  font-size: .7rem; font-weight: 600; font-family: var(--mono);
}
</style>
</head>
<body>
<div class="app">
  <!-- ═══ SIDEBAR ═══ -->
  <aside class="sidebar">
    <div class="sb-header">
      <div class="logo"><span>AutoCoder</span> <small>V4</small></div>
      <p style="font-size:.7rem;color:var(--text-3);">Architecte IA Full-Stack — 7 Agents</p>
    </div>
    <div class="sb-body">

      <!-- TABS: BUILD / KEYS / PROJECTS -->
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('build')">⚡ Build</button>
        <button class="tab-btn" onclick="switchTab('keys')">🔑 Keys</button>
        <button class="tab-btn" onclick="switchTab('projects')">📁 Projects</button>
      </div>

      <!-- BUILD PANEL -->
      <div class="panel active" id="panelBuild">
        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">🧠 Master Prompt</div>
          <div class="form-group">
            <label>Décris ton projet en détail — l'IA analyse et choisit la meilleure stack</label>
            <textarea id="masterPrompt" rows="6" placeholder="Ex: Crée un site web professionnel pour un restaurant gastronomique avec animations fluides, menu interactif, réservation en ligne, galerie photo et témoignages clients. Design premium, expérience immersive, responsive." style="font-size:.85rem;"></textarea>
          </div>
          <div style="text-align:right;">
            <button class="btn btn-sm btn-outline" onclick="toggleAdvanced()" type="button" style="font-size:.7rem;">
              ⚙️ Options avancées
            </button>
          </div>
        </div>

        <div id="advancedOptions" style="display:none;">
          <div class="card" style="margin-bottom:14px;">
            <div class="card-title">📋 Brief Détail</div>
            <div class="form-group"><label>Titre du projet</label><input type="text" id="briefTitle" placeholder="MonApp Pro..."></div>
            <div class="form-group"><label>👤 Qui êtes-vous / Marque</label><textarea id="briefWho" rows="2" placeholder="Startup SaaS, freelance développeur..."></textarea></div>
            <div class="form-group"><label>🎯 Public cible</label><textarea id="briefTarget" rows="2" placeholder="Jeunes entrepreneurs, PME..."></textarea></div>
            <div class="form-group"><label>💰 Objectif / Monétisation</label><textarea id="briefMonetize" rows="2" placeholder="Abonnements, commissions, freemium..."></textarea></div>
          </div>

          <div class="card" style="margin-bottom:14px;">
            <div class="card-title">⚙️ Stack Technique (personnalisée)</div>
            <div class="form-group">
              <label>Type de projet</label>
              <select id="projType" onchange="updateStackOptions()">
                <option value="fullstack">🌐 Full-Stack Web</option>
                <option value="mobile">📱 Mobile App</option>
                <option value="api">⚡ API / Backend Only</option>
                <option value="static">📄 Site Statique</option>
              </select>
            </div>
            <div class="form-group">
              <label>Frontend</label>
              <select id="frontend">
                <option value="next">▲ Next.js</option>
                <option value="react">⚛️ React + Vite</option>
                <option value="vue">💚 Vue 3</option>
                <option value="svelte">🧡 SvelteKit</option>
                <option value="angular">🔺 Angular</option>
                <option value="astro">🚀 Astro</option>
                <option value="flutter">🦋 Flutter</option>
                <option value="react_native">📱 React Native</option>
                <option value="html_css_js">🌍 HTML/CSS/JS</option>
              </select>
            </div>
            <div class="form-group">
              <label>Backend</label>
              <select id="backend">
                <option value="node_express">🟢 Node.js + Express</option>
                <option value="fastapi_python">🐍 Python FastAPI</option>
                <option value="laravel_php">🔥 Laravel PHP</option>
                <option value="django_python">🎸 Django Python</option>
                <option value="go_gin">🔵 Go + Gin</option>
                <option value="none">— Aucun —</option>
              </select>
            </div>
            <div class="form-group">
              <label>Base de données</label>
              <select id="database">
                <option value="sqlite">SQLite</option>
                <option value="postgresql">PostgreSQL</option>
                <option value="mysql">MySQL</option>
                <option value="mongodb">MongoDB</option>
                <option value="none">— Aucune —</option>
              </select>
            </div>
            <div class="form-group">
              <label>CSS Framework</label>
              <select id="css">
                <option value="tailwind">🌊 Tailwind CSS</option>
                <option value="bootstrap">🅱️ Bootstrap 5</option>
                <option value="vanilla">🎨 Vanilla CSS Premium</option>
                <option value="chakra">🌈 Chakra UI</option>
              </select>
            </div>
            <div class="form-group">
              <label>Langue du site</label>
              <select id="lang">
                <option value="fr">Français</option>
                <option value="en">English</option>
                <option value="ar">العربية</option>
                <option value="es">Español</option>
              </select>
            </div>
          </div>
        </div>

        <button class="launch-btn" id="launchBtn" onclick="launchBuild()">
          🚀 Lancer la construction autonome
        </button>
      </div>

      <!-- KEYS PANEL -->
      <div class="panel" id="panelKeys">
        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">🔑 Ajouter une clé API</div>
          <div class="form-group"><label>Label</label><input type="text" id="keyLabel" placeholder="Compte Pro"></div>
          <div class="form-group"><label>Clé Mistral API</label><input type="password" id="keyVal" placeholder="Paste your key"></div>
          <button class="btn btn-primary w-full" onclick="addKey()">Enregistrer</button>
          <div id="keyMsg" style="margin-top:10px;font-family:var(--mono);font-size:.75rem;"></div>
        </div>
        <div class="card">
          <div class="card-title">💾 Clés enregistrées</div>
          <div class="item-list" id="keysList"><div class="empty-state">Chargement...</div></div>
        </div>
      </div>

      <!-- PROJECTS PANEL -->
      <div class="panel" id="panelProjects">
        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">📊 Statistiques</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div style="padding:12px;background:var(--bg);border-radius:var(--radius-sm);text-align:center;">
              <div style="font-size:1.4rem;font-weight:800;color:var(--primary)" id="statKeys">0</div>
              <div style="font-size:.65rem;color:var(--text-3);">Clés</div>
            </div>
            <div style="padding:12px;background:var(--bg);border-radius:var(--radius-sm);text-align:center;">
              <div style="font-size:1.4rem;font-weight:800;color:var(--accent)" id="statTokens">0</div>
              <div style="font-size:.65rem;color:var(--text-3);">Tokens</div>
            </div>
            <div style="padding:12px;background:var(--bg);border-radius:var(--radius-sm);text-align:center;">
              <div style="font-size:1.4rem;font-weight:800;color:var(--success)" id="statProjects">0</div>
              <div style="font-size:.65rem;color:var(--text-3);">Projets</div>
            </div>
            <div style="padding:12px;background:var(--bg);border-radius:var(--radius-sm);text-align:center;">
              <div style="font-size:1.4rem;font-weight:800;color:var(--orange)" id="statDone">0</div>
              <div style="font-size:.65rem;color:var(--text-3);">Terminés</div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-title">📁 Projets</div>
          <div class="item-list" id="projectsList"><div class="empty-state">Chargement...</div></div>
        </div>
      </div>

    </div>
  </aside>

  <!-- ═══ MAIN ═══ -->
  <main class="main">
    <div class="topbar">
      <div>
        <h2>🤖 7-Agent Pipeline</h2>
        <p id="modelStatus">Modèle : <?= AC4_MODEL ?> — Pool de clés actives</p>
      </div>
      <div style="display:flex;gap:8px;">
        <span class="badge badge-primary" id="totalKeys">0 clés</span>
        <span class="badge badge-accent" id="totalTokens">0 tokens</span>
      </div>
    </div>

    <!-- PIPELINE STEPS -->
    <div class="pipeline-box" id="pipelineBox" style="display:none;">
      <div class="progress-row">
        <span id="progressLabel">En attente...</span>
        <span id="progressPct" style="font-family:var(--mono)">0%</span>
      </div>
      <div class="progress-bg"><div class="progress-fill" id="progressFill"></div></div>
      <div class="agents-grid" id="agentsGrid">
        <div class="agent-chip" id="step-cto"><span class="agent-num">01</span>🧠 CTO</div>
        <div class="agent-chip" id="step-architect"><span class="agent-num">02</span>🏗️ Architect</div>
        <div class="agent-chip" id="step-designer"><span class="agent-num">03</span>🎨 Designer</div>
        <div class="agent-chip" id="step-backend"><span class="agent-num">04</span>⚡ Backend</div>
        <div class="agent-chip" id="step-frontend"><span class="agent-num">05</span>💻 Frontend</div>
        <div class="agent-chip" id="step-qa"><span class="agent-num">06</span>🔍 QA</div>
        <div class="agent-chip" id="step-devops"><span class="agent-num">07</span>🚀 DevOps</div>
      </div>
    </div>

    <!-- DETAIL PROJET -->
    <div class="pipeline-box" id="projectDetail">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 id="detailTitle" style="font-size:.95rem;font-weight:700;"></h3>
        <button class="btn btn-sm btn-outline" onclick="closeProjectDetail()">✕</button>
      </div>
      <div class="project-info" id="detailInfo"></div>
      <div style="display:flex;gap:8px;margin-bottom:12px;">
        <button class="btn btn-sm btn-outline" id="detailOpenBtn" onclick="openProjectFolder()">📂 Ouvrir</button>
        <button class="btn btn-sm btn-outline" onclick="downloadProjectZip()">📦 ZIP</button>
        <button class="btn btn-sm btn-danger" onclick="deleteProject()">🗑️</button>
      </div>
      <div style="font-family:var(--mono);font-size:.75rem;max-height:200px;overflow-y:auto;background:var(--bg);padding:10px;border-radius:var(--radius-sm);" id="detailLogs"></div>
    </div>

    <!-- TERMINAL -->
    <div class="terminal" id="terminalBlock" style="display:none;">
      <div class="term-header">
        <div class="term-dots">
          <span class="term-dot" style="background:var(--error)"></span>
          <span class="term-dot" style="background:var(--warning)"></span>
          <span class="term-dot" style="background:var(--success)"></span>
        </div>
        <span class="term-title">autocoder_v4_pipeline.log</span>
        <button class="btn btn-sm btn-outline" onclick="clearTerminal()">Clear</button>
      </div>
      <div class="term-body" id="terminalConsole">
        <div class="log-row"><span class="log-time">-----</span><span class="log-tag tag-sys">Engine</span><span class="log-msg">AutoCoder V4 prêt. Configurez votre brief et lancez la construction.</span></div>
      </div>
    </div>
  </main>

  <!-- ═══ PREVIEW ═══ -->
  <section class="preview">
    <div class="preview-header">
      <div>
        <h3 style="font-size:.9rem;font-weight:700;">🖥️ Preview</h3>
        <p style="font-size:.68rem;color:var(--text-3);">Aperçu en direct du projet</p>
      </div>
      <select style="width:auto;padding:5px 10px;font-size:.75rem;display:none;" id="previewPageSelect" onchange="changePreviewPage()"></select>
    </div>
    <div class="preview-frame">
      <iframe src="about:blank" id="previewFrame"></iframe>
    </div>
  </section>
</div>

<script>
// ═══════════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════════
let isBuilding = false;
let activeProjectId = null;
let activeProjectFolder = '';

// ═══════════════════════════════════════════════════════════════════════
// API HELPER
// ═══════════════════════════════════════════════════════════════════════
async function api(action, data = {}) {
  const fd = new FormData();
  fd.append('action', action);
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  return fetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
}

async function apiJSON(action, data = {}) {
  return fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, ...data })
  }).then(r => r.json());
}

// ═══════════════════════════════════════════════════════════════════════
// TABS
// ═══════════════════════════════════════════════════════════════════════
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelector(`.tab-btn[onclick*="${name}"]`)?.classList.add('active');
  document.getElementById('panel' + name.charAt(0).toUpperCase() + name.slice(1)).classList.add('active');
  if (name === 'keys') loadKeys();
  if (name === 'projects') { loadProjects(); loadStats(); }
}

// ═══════════════════════════════════════════════════════════════════════
// STACK OPTIONS
// ═══════════════════════════════════════════════════════════════════════
<?php
$jsonOpts = [];
foreach ($stacks as $key => $s) {
  $jsonOpts[$key] = [
    'frontends' => $s['frontends'],
    'backends' => $s['backends'],
    'databases' => $s['databases'],
    'css' => $s['css'],
  ];
}
$frontendLabels = [];
$backendLabels = [];
$dbLabels = [];
$cssLabels = [];
foreach ($frontends as $k => $v) $frontendLabels[$k] = $v['icon'] . ' ' . $v['label'];
foreach ($backends as $k => $v) $backendLabels[$k] = $v['icon'] . ' ' . $v['label'];
foreach ($databases as $k => $v) $dbLabels[$k] = $v;
foreach ($css_frameworks as $k => $v) $cssLabels[$k] = $v['icon'] . ' ' . $v['label'];
?>
const STACK_OPTIONS = <?= json_encode($jsonOpts) ?>;
const FRONTEND_LABELS = <?= json_encode($frontendLabels) ?>;
const BACKEND_LABELS = <?= json_encode($backendLabels) ?>;
const DB_LABELS = <?= json_encode($dbLabels) ?>;
const CSS_LABELS = <?= json_encode($cssLabels) ?>;

function updateStackOptions() {
  const type = document.getElementById('projType').value;
  const opts = STACK_OPTIONS[type];

  const fe = document.getElementById('frontend');
  fe.innerHTML = opts.frontends.map(k => `<option value="${k}">${FRONTEND_LABELS[k] || k}</option>`).join('');

  const be = document.getElementById('backend');
  be.innerHTML = opts.backends.map(k => `<option value="${k}">${BACKEND_LABELS[k] || k}</option>`).join('');

  const db = document.getElementById('database');
  db.innerHTML = opts.databases.map(k => `<option value="${k}">${DB_LABELS[k] || k}</option>`).join('');

  const css = document.getElementById('css');
  css.innerHTML = opts.css.map(k => `<option value="${k}">${CSS_LABELS[k] || k}</option>`).join('');
}
updateStackOptions();

// ═══════════════════════════════════════════════════════════════════════
// KEYS
// ═══════════════════════════════════════════════════════════════════════
async function addKey() {
  const label = document.getElementById('keyLabel').value.trim();
  const key = document.getElementById('keyVal').value.trim();
  if (!label || !key) { showKeyMsg('Remplis les deux champs', 'err'); return; }
  const r = await api('add_key', { label, key });
  if (r.success) { showKeyMsg('✓ Clé enregistrée', 'ok'); document.getElementById('keyLabel').value = ''; document.getElementById('keyVal').value = ''; loadKeys(); }
  else showKeyMsg('Erreur: ' + (r.error || ''), 'err');
}
function showKeyMsg(msg, type) {
  const c = { ok: 'var(--success)', err: 'var(--error)', info: 'var(--primary)' };
  const el = document.getElementById('keyMsg');
  el.style.color = c[type] || 'var(--text)';
  el.textContent = msg;
}
async function loadKeys() {
  const data = await api('get_data');
  const keys = data.keys || [];
  const el = document.getElementById('keysList');
  if (!keys.length) { el.innerHTML = '<div class="empty-state">Aucune clé — ajoutez-en une</div>'; return; }
  el.innerHTML = keys.map(k => `
    <div class="item-row">
      <div><strong style="font-size:.85rem;">${k.label}</strong><br><span style="color:var(--text-3);font-family:var(--mono);font-size:.72rem;">${k.key_masked}</span></div>
      <div style="display:flex;align-items:center;gap:6px;">
        <span class="pill ${k.is_active == 1 ? 'pill-green' : 'pill-red'}">${k.is_active == 1 ? 'Active' : 'Inactive'}</span>
        <span style="font-family:var(--mono);font-size:.7rem;color:var(--text-3);">${k.error_count} err</span>
        <button class="btn btn-sm btn-outline" onclick="resetKey(${k.id})" title="Reset">↻</button>
        <button class="btn btn-sm btn-danger" onclick="deleteKey(${k.id})">✕</button>
      </div>
    </div>
  `).join('');
  updateBadges(data);
}
async function deleteKey(id) { if (!confirm('Supprimer cette clé ?')) return; const r = await api('delete_key', { id }); if (r.success) loadKeys(); }
async function resetKey(id) { const r = await api('reset_key', { id }); if (r.success) loadKeys(); }

// ═══════════════════════════════════════════════════════════════════════
// PROJECTS
// ═══════════════════════════════════════════════════════════════════════
async function loadProjects() {
  const data = await api('list_projects');
  const projects = data.projects || [];
  const el = document.getElementById('projectsList');
  if (!projects.length) { el.innerHTML = '<div class="empty-state">Aucun projet. Lancez un build !</div>'; return; }
  el.innerHTML = projects.map(p => `
    <div class="item-row" onclick="showProjectDetail(${p.id})" style="cursor:pointer;">
      <div>
        <strong style="font-size:.85rem;">${p.title}</strong>
        <div style="font-size:.7rem;color:var(--text-3);">
          <span class="stack-tag">${p.frontend}</span>
          <span class="stack-tag">${p.backend}</span>
          ${p.qa_score > 0 ? `<span class="pill pill-green">${p.qa_score}/100</span>` : ''}
        </div>
      </div>
      <div style="text-align:right;">
        <span class="pill ${p.status === 'done' ? 'pill-green' : p.status === 'failed' ? 'pill-red' : 'pill-blue'}">${p.status}</span>
        <div style="font-size:.65rem;color:var(--text-3);margin-top:4px;">${p.created_at || ''}</div>
      </div>
    </div>
  `).join('');
}
async function loadStats() {
  const d = await api('get_stats');
  const s = d.stats || {};
  document.getElementById('statKeys').textContent = s.keys_active || 0;
  document.getElementById('statTokens').textContent = (s.tokens_total || 0) >= 1000 ? Math.round(s.tokens_total/1000)+'k' : s.tokens_total || 0;
  document.getElementById('statProjects').textContent = s.projects_total || 0;
  document.getElementById('statDone').textContent = s.projects_done || 0;
}

async function showProjectDetail(id) {
  const d = await api('get_project', { id: '' + id });
  const p = d.project;
  if (!p) return;
  activeProjectId = p.id;
  activeProjectFolder = p.folder;

  document.getElementById('detailTitle').textContent = p.title;
  document.getElementById('detailInfo').innerHTML = `
    <div class="project-info-item"><div class="label">Type</div><div class="value">${p.project_type}</div></div>
    <div class="project-info-item"><div class="label">Stack</div><div class="value"><span class="stack-tag">${p.frontend}</span> <span class="stack-tag">${p.backend}</span></div></div>
    <div class="project-info-item"><div class="label">BDD</div><div class="value">${p.database}</div></div>
    <div class="project-info-item"><div class="label">Score QA</div><div class="value">${p.qa_score || '—'}/100</div></div>
    <div class="project-info-item"><div class="label">Fichiers</div><div class="value">${p.file_count || 0}</div></div>
    <div class="project-info-item"><div class="label">Status</div><div class="value"><span class="pill ${p.status === 'done' ? 'pill-green' : 'pill-red'}">${p.status}</span></div></div>
  `;

  const logs = d.logs || [];
  document.getElementById('detailLogs').innerHTML = logs.length
    ? logs.map(l => `<span style="color:var(--text-3)">[${l.logged_at}]</span> <span class="tag-${l.level}">${l.level}</span> ${l.message}<br>`).join('')
    : '<span style="color:var(--text-3)">Aucun log</span>';

  document.getElementById('projectDetail').style.display = 'block';
  document.getElementById('projectDetail').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function closeProjectDetail() { document.getElementById('projectDetail').style.display = 'none'; }
function openProjectFolder() {
  if (activeProjectFolder) window.open(activeProjectFolder + '/index.php', '_blank');
}
function downloadProjectZip() {
  if (!activeProjectId) return;
  window.location.href = 'api.php?action=download_zip&id=' + activeProjectId;
}
async function deleteProject() {
  if (!activeProjectId || !confirm('Supprimer ce projet définitivement ?')) return;
  const r = await api('delete_project', { id: activeProjectId });
  if (r.success) { closeProjectDetail(); loadProjects(); loadStats(); }
}

// ═══════════════════════════════════════════════════════════════════════
// BADGES
// ═══════════════════════════════════════════════════════════════════════
function updateBadges(data) {
  const kc = (data.keys || []).filter(k => k.is_active == 1).length;
  const tt = data.stats?.tokens_total || 0;
  document.getElementById('totalKeys').textContent = kc + ' clés';
  document.getElementById('totalTokens').textContent = (tt >= 1000 ? Math.round(tt/1000) + 'k' : tt) + ' tokens';
}

// ═══════════════════════════════════════════════════════════════════════
// TERMINAL
// ═══════════════════════════════════════════════════════════════════════
function terminalLog(level, msg) {
  const el = document.getElementById('terminalConsole');
  const t = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  const row = document.createElement('div');
  row.className = 'log-row';
  row.innerHTML = `<span class="log-time">${t}</span><span class="log-tag tag-${level}">${level.toUpperCase()}</span><span class="log-msg">${msg}</span>`;
  el.appendChild(row);
  el.scrollTop = el.scrollHeight;
}
function clearTerminal() { document.getElementById('terminalConsole').innerHTML = ''; }

// ═══════════════════════════════════════════════════════════════════════
// MASTER PROMPT + ADVANCED TOGGLE
// ═══════════════════════════════════════════════════════════════════════
let advancedVisible = false;
function toggleAdvanced() {
  advancedVisible = !advancedVisible;
  document.getElementById('advancedOptions').style.display = advancedVisible ? 'block' : 'none';
}

function getStackValue(id, fallback) {
  const el = document.getElementById(id);
  return el ? el.value : fallback;
}

// ═══════════════════════════════════════════════════════════════════════
// BUILD PIPELINE
// ═══════════════════════════════════════════════════════════════════════
function setStep(id, status) {
  const el = document.getElementById('step-' + id);
  if (!el) return;
  el.className = 'agent-chip';
  if (status === 'active') el.classList.add('active');
  else if (status === 'done') el.classList.add('done');
  else if (status === 'failed') el.classList.add('failed');
}

function setProgress(pct, label) {
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressPct').textContent = pct + '%';
  document.getElementById('progressLabel').textContent = label;
}

async function launchBuild() {
  if (isBuilding) return;

  const masterPrompt = document.getElementById('masterPrompt').value.trim();
  if (!masterPrompt) { alert('Remplis le Master Prompt avec la description de ton projet !'); return; }

  // Fallback to advanced fields if filled
  const advTitle = document.getElementById('briefTitle')?.value.trim() || '';
  const advWho = document.getElementById('briefWho')?.value.trim() || '';
  const advTarget = document.getElementById('briefTarget')?.value.trim() || '';
  const advMonetize = document.getElementById('briefMonetize')?.value.trim() || '';

  const useAdvanced = advancedVisible && advTitle;

  isBuilding = true;
  document.getElementById('launchBtn').disabled = true;
  document.getElementById('pipelineBox').style.display = 'block';
  document.getElementById('terminalBlock').style.display = 'flex';
  clearTerminal();
  closeProjectDetail();

  // Reset steps
  ['cto','architect','designer','backend','frontend','qa','devops'].forEach(s => setStep(s, ''));

  try {
    terminalLog('sys', '═══════════════════════════════════════════');
    terminalLog('sys', 'Création du projet...');

    const proj = await api('create_project', {
      master_prompt: masterPrompt,
      title: useAdvanced ? advTitle : '',
      who: useAdvanced ? advWho : '',
      target: useAdvanced ? advTarget : '',
      monetize: useAdvanced ? advMonetize : '',
      type: useAdvanced ? getStackValue('projType', 'fullstack') : '',
      frontend: useAdvanced ? getStackValue('frontend', '') : '',
      backend: useAdvanced ? getStackValue('backend', '') : '',
      database: useAdvanced ? getStackValue('database', '') : '',
      css: useAdvanced ? getStackValue('css', '') : '',
      lang: useAdvanced ? getStackValue('lang', 'fr') : '',
    });

    if (proj.error) throw new Error(proj.error);
    activeProjectId = proj.id;
    activeProjectFolder = proj.folder;
    terminalLog('ok', `Projet #${proj.id} créé → ${proj.folder}`);

    // Launch build via EventSource
    terminalLog('sys', '🚀 Lancement du pipeline 7 agents...');
    setProgress(2, 'Démarrage du pipeline...');

    const resp = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'run_build', project_id: proj.id })
    });

    const reader = resp.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    const stepMap = { 'cto': 'step-cto', 'architect': 'step-architect', 'designer': 'step-designer', 'backend': 'step-backend', 'frontend': 'step-frontend', 'qa': 'step-qa', 'devops': 'step-devops', 'engine': 'step-cto' };

    let buildCompleted = false;

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n');
      buffer = lines.pop() || '';

      for (const line of lines) {
        if (!line.trim()) continue;
        let msg;
        try { msg = JSON.parse(line); } catch { continue; }

        switch (msg.type) {
          case 'log':
            terminalLog(msg.level || 'info', msg.message || '');
            if (stepMap[msg.step]) setStep(msg.step, msg.level === 'ok' ? 'done' : msg.level === 'err' ? 'failed' : 'active');
            break;
          case 'progress':
            setProgress(msg.pct, msg.label);
            break;
          case 'done':
            buildCompleted = true;
            terminalLog('ok', msg.result?.success ? '✅ Projet terminé !' : '❌ Échec');
            loadProjects();
            loadStats();
            if (msg.result?.success) showProjectDetail(proj.id);
            isBuilding = false;
            document.getElementById('launchBtn').disabled = false;
            return;
        }
      }
    }

    // Stream ended without 'done' event — poll for status
    if (!buildCompleted) {
      terminalLog('warn', 'Flux interrompu, vérification du statut...');
      for (let i = 0; i < 30; i++) {
        await new Promise(r => setTimeout(r, 2000));
        try {
          const status = await api('get_project', { id: '' + proj.id });
          if (status.project?.status === 'done' || status.project?.status === 'failed') {
            terminalLog(status.project.status === 'done' ? 'ok' : 'err',
              status.project.status === 'done' ? '✅ Projet terminé !' : '❌ Échec');
            loadProjects(); loadStats();
            if (status.project.status === 'done') showProjectDetail(proj.id);
            break;
          }
        } catch {}
      }
    }
  } catch (e) {
    terminalLog('err', 'Erreur: ' + e.message);
    setStep('cto', 'failed');
  }

  isBuilding = false;
  document.getElementById('launchBtn').disabled = false;
}

// ═══════════════════════════════════════════════════════════════════════
// PREVIEW
// ═══════════════════════════════════════════════════════════════════════
function changePreviewPage() {
  const sel = document.getElementById('previewPageSelect');
  const val = sel.value;
  if (val && activeProjectFolder) {
    document.getElementById('previewFrame').src = activeProjectFolder + '/' + val;
  }
}

// ═══════════════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════════════
loadKeys();
loadProjects();
loadStats();
</script>
</body>
</html>
