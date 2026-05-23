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
<title>AkrourCoder V4 — Full-Stack AI Architect</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <!-- ═══ SIDEBAR ═══ -->
  <aside class="sidebar">
    <div class="sb-header">
      <div class="logo"><span>AkrourCoder</span> <small>V4</small></div>
      <p style="font-size:.7rem;color:var(--text-3);">Architecte IA Full-Stack — 7 Agents</p>
    </div>
    <div class="sb-body">
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('build')">⚡ Build</button>
        <button class="tab-btn" onclick="switchTab('keys')">🔑 Keys</button>
        <button class="tab-btn" onclick="switchTab('projects')">📁 Projects</button>
        <button class="tab-btn" onclick="switchTab('dashboard')">📊 Dashboard</button>
      </div>

      <!-- BUILD PANEL -->
      <div class="panel active" id="panelBuild">
        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">🧠 Master Prompt</div>
          <div class="form-group">
            <label>Décris ton projet en détail — l'IA analyse et choisit la meilleure stack</label>
            <textarea id="masterPrompt" rows="6" placeholder="Ex: Crée un site web professionnel pour un restaurant gastronomique avec animations fluides, menu interactif, réservation en ligne, galerie photo et témoignages clients. Design premium, expérience immersive, responsive."></textarea>
          </div>
          <div style="text-align:right;">
            <button class="btn btn-sm btn-outline" onclick="toggleAdvanced()" type="button" style="font-size:.7rem;">⚙️ Options avancées</button>
          </div>
        </div>

        <div id="advancedOptions" style="display:none;">
          <div class="card" style="margin-bottom:14px;">
            <div class="card-title">📋 Brief Détail</div>
            <div class="form-group"><label>Titre du projet</label><input type="text" id="briefTitle" placeholder="MonApp Pro..."></div>
            <div class="form-group"><label>👤 Qui êtes-vous / Marque</label><textarea id="briefWho" rows="2" placeholder="Startup SaaS, freelance..."></textarea></div>
            <div class="form-group"><label>🎯 Public cible</label><textarea id="briefTarget" rows="2" placeholder="Jeunes entrepreneurs, PME..."></textarea></div>
            <div class="form-group"><label>💰 Objectif / Monétisation</label><textarea id="briefMonetize" rows="2" placeholder="Abonnements, commissions..."></textarea></div>
          </div>

          <div class="card" style="margin-bottom:14px;">
            <div class="card-title">⚙️ Stack Technique</div>
            <div class="form-group">
              <label>Type de projet</label>
              <select id="projType" onchange="updateStackOptions()">
                <option value="fullstack">🌐 Full-Stack Web</option>
                <option value="mobile">📱 Mobile App</option>
                <option value="api">⚡ API / Backend Only</option>
                <option value="static">📄 Site Statique</option>
              </select>
            </div>
            <div class="form-group"><label>Frontend</label><select id="frontend"></select></div>
            <div class="form-group"><label>Backend</label><select id="backend"></select></div>
            <div class="form-group"><label>Base de données</label><select id="database"></select></div>
            <div class="form-group"><label>CSS Framework</label><select id="css"></select></div>
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

        <button class="launch-btn" id="launchBtn" onclick="launchBuild()">🚀 Lancer la construction autonome</button>
      </div>

      <!-- KEYS PANEL -->
      <div class="panel" id="panelKeys">
        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">🔑 Ajouter une clé API</div>
          <div class="form-group"><label>Label</label><input type="text" id="keyLabel" placeholder="Compte Pro"></div>
          <div class="form-group">
            <label>Provider</label>
            <select id="keyProvider">
              <option value="mistral">🔮 Mistral AI</option>
              <option value="openai">🤖 OpenAI</option>
              <option value="anthropic">🌿 Anthropic Claude</option>
              <option value="google">🔬 Google Gemini</option>
            </select>
          </div>
          <div class="form-group"><label>Clé API</label><input type="password" id="keyVal" placeholder="sk-... or paste your key"></div>
          <button class="btn btn-primary w-full" onclick="addKey()">Enregistrer</button>
          <div id="keyMsg" style="margin-top:10px;font-family:var(--mono);font-size:.75rem;"></div>
        </div>
        <div class="card">
          <div class="card-title">💾 Clés enregistrées</div>
          <div class="item-list" id="keysList"><div class="empty-state">Chargement...</div></div>
        </div>
      </div>

      <!-- DASHBOARD PANEL -->
      <div class="panel" id="panelDashboard">
        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">📊 Vue d'ensemble</div>
          <div class="stat-grid" id="dashMetrics">
            <div class="stat-card"><div class="stat-value" id="dashKeys">-</div><div class="stat-label">Clés actives</div></div>
            <div class="stat-card"><div class="stat-value" id="dashTokens">-</div><div class="stat-label">Tokens totaux</div></div>
            <div class="stat-card"><div class="stat-value" id="dashProjects">-</div><div class="stat-label">Projets</div></div>
            <div class="stat-card"><div class="stat-value" id="dashDone">-</div><div class="stat-label">Terminés</div></div>
            <div class="stat-card"><div class="stat-value" id="dashFailed">-</div><div class="stat-label">Échoués</div></div>
            <div class="stat-card"><div class="stat-value" id="dashAvgScore">-</div><div class="stat-label">Score Ø</div></div>
          </div>
        </div>

        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">📈 Tokens par jour (30j)</div>
          <div class="chart-container" id="tokenDayChart"><div class="empty-state">Aucune donnée</div></div>
        </div>

        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">🤖 Tokens par agent</div>
          <div class="chart-container" id="tokenStepChart"><div class="empty-state">Aucune donnée</div></div>
        </div>

        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">🏆 Top projets</div>
          <div class="item-list" id="dashTopProjects"><div class="empty-state">Aucun projet noté</div></div>
        </div>

        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">📁 Projets récents</div>
          <div class="item-list" id="dashRecentProjects"><div class="empty-state">Aucun projet</div></div>
        </div>
      </div>

      <!-- PROJECTS PANEL -->
      <div class="panel" id="panelProjects">
        <div class="card" style="margin-bottom:14px;">
          <div class="card-title">📊 Statistiques</div>
          <div class="stat-grid">
            <div class="stat-card"><div class="stat-value" style="color:var(--primary)" id="statKeys">0</div><div class="stat-label">Clés</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--accent)" id="statTokens">0</div><div class="stat-label">Tokens</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--success)" id="statProjects">0</div><div class="stat-label">Projets</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--orange)" id="statDone">0</div><div class="stat-label">Terminés</div></div>
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
        <p id="modelStatus">Modèle : <?= AC4_MODEL ?> — Multi-provider</p>
      </div>
      <div style="display:flex;gap:8px;">
        <span class="badge badge-primary" id="totalKeys">0 clés</span>
        <span class="badge badge-accent" id="totalTokens">0 tokens</span>
      </div>
    </div>

    <div class="pipeline-box" id="pipelineBox" style="display:none;">
      <div class="progress-row"><span id="progressLabel">En attente...</span><span id="progressPct" style="font-family:var(--mono)">0%</span></div>
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

    <div class="pipeline-box" id="projectDetail">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 id="detailTitle" style="font-size:.95rem;font-weight:700;"></h3>
        <button class="btn btn-sm btn-outline" onclick="closeProjectDetail()">✕</button>
      </div>
      <div class="project-info" id="detailInfo"></div>
      <div style="display:flex;gap:8px;margin-bottom:12px;">
        <button class="btn btn-sm btn-outline" id="detailOpenBtn" onclick="openProjectFolder()">📂 Ouvrir</button>
        <button class="btn btn-sm btn-outline" onclick="downloadProjectZip()">📦 ZIP</button>
        <button class="btn btn-sm btn-primary" id="detailRebuildBtn" onclick="rebuildProject()" style="display:none;">🔄 Re-build</button>
        <button class="btn btn-sm btn-primary" id="detailResumeBtn" onclick="resumeProject()" style="display:none;">▶ Resume</button>
        <button class="btn btn-sm btn-danger" onclick="deleteProject()">🗑️</button>
      </div>
      <div style="font-family:var(--mono);font-size:.75rem;max-height:200px;overflow-y:auto;background:var(--bg);padding:10px;border-radius:var(--radius-sm);" id="detailLogs"></div>
    </div>

    <div class="terminal" id="terminalBlock" style="display:none;">
      <div class="term-header">
        <div class="term-dots">
          <span class="term-dot" style="background:var(--error)"></span>
          <span class="term-dot" style="background:var(--warning)"></span>
          <span class="term-dot" style="background:var(--success)"></span>
        </div>
        <span class="term-title">akrourcoder_v4_pipeline.log</span>
        <button class="btn btn-sm btn-outline" onclick="clearTerminal()">Clear</button>
      </div>
      <div class="term-body" id="terminalConsole">
        <div class="log-row"><span class="log-time">-----</span><span class="log-tag tag-sys">Engine</span><span class="log-msg">AkrourCoder V4 prêt. Configurez votre brief et lancez la construction.</span></div>
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

<!-- PHP→JS constants -->
<script>
const STACK_OPTIONS = <?= json_encode(array_map(fn($s) => ['frontends' => $s['frontends'], 'backends' => $s['backends'], 'databases' => $s['databases'], 'css' => $s['css']], $stacks)) ?>;
const FRONTEND_LABELS = <?= json_encode(array_combine(array_keys($frontends), array_map(fn($v) => $v['icon'] . ' ' . $v['label'], $frontends))) ?>;
const BACKEND_LABELS = <?= json_encode(array_combine(array_keys($backends), array_map(fn($v) => $v['icon'] . ' ' . $v['label'], $backends))) ?>;
const DB_LABELS = <?= json_encode($databases) ?>;
const CSS_LABELS = <?= json_encode(array_combine(array_keys($css_frameworks), array_map(fn($v) => $v['icon'] . ' ' . $v['label'], $css_frameworks))) ?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>
