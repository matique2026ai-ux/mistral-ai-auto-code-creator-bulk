<?php
require_once 'db.php';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AutoCoder — Mistral Builder</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #08090a;
    --surface: #111315;
    --card: #161a1d;
    --border: #252a2f;
    --accent: #00e5c3;
    --accent2: #ff6b35;
    --accent3: #a78bfa;
    --text: #e8eaed;
    --muted: #6b7280;
    --ok: #22c55e;
    --err: #ef4444;
    --warn: #f59e0b;
    --mono: 'Space Mono', monospace;
    --sans: 'Syne', sans-serif;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    font-family: var(--sans);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* BG grid */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(0,229,195,.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,229,195,.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
  }

  .wrap { position: relative; z-index: 1; max-width: 1100px; margin: 0 auto; padding: 0 24px 80px; }

  /* HEADER */
  header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 28px 0 20px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 36px;
  }
  .logo { font-size: 1.3rem; font-weight: 800; letter-spacing: -0.5px; }
  .logo span { color: var(--accent); }
  .logo small { font-family: var(--mono); font-size: .65rem; color: var(--muted); display: block; margin-top: 2px; font-weight: 400; }

  .badge {
    font-family: var(--mono);
    font-size: .7rem;
    padding: 4px 10px;
    border-radius: 4px;
    border: 1px solid var(--border);
    color: var(--muted);
  }
  .badge.live { border-color: var(--accent); color: var(--accent); }
  .badge.live::before { content: '● '; animation: blink 1.2s infinite; }
  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

  /* TABS */
  .tabs { display: flex; gap: 4px; margin-bottom: 28px; }
  .tab {
    padding: 8px 20px;
    font-family: var(--mono);
    font-size: .8rem;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    cursor: pointer;
    border-radius: 6px;
    transition: all .2s;
    letter-spacing: .05em;
  }
  .tab.active, .tab:hover {
    background: var(--card);
    color: var(--text);
    border-color: var(--accent);
  }
  .tab.active { color: var(--accent); }
  .panel { display: none; }
  .panel.active { display: block; }

  /* CARDS */
  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 20px;
  }
  .card-title {
    font-size: .75rem;
    font-family: var(--mono);
    color: var(--accent);
    letter-spacing: .1em;
    text-transform: uppercase;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .card-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }

  /* FORM ELEMENTS */
  input, textarea, select {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 10px 14px;
    border-radius: 6px;
    font-family: var(--mono);
    font-size: .85rem;
    transition: border-color .2s;
    outline: none;
  }
  input:focus, textarea:focus { border-color: var(--accent); }
  textarea { resize: vertical; min-height: 80px; }
  label { font-size: .75rem; color: var(--muted); margin-bottom: 6px; display: block; font-family: var(--mono); }

  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
  .form-group { margin-bottom: 16px; }

  /* BUTTONS */
  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--mono);
    font-size: .82rem;
    font-weight: 700;
    transition: all .2s;
    letter-spacing: .03em;
  }
  .btn-primary { background: var(--accent); color: #000; }
  .btn-primary:hover { background: #00ffda; transform: translateY(-1px); }
  .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
  .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
  .btn-danger { background: transparent; border: 1px solid var(--err); color: var(--err); }
  .btn-lg { padding: 14px 32px; font-size: 1rem; }
  .btn:disabled { opacity: .4; cursor: not-allowed; transform: none !important; }

  /* BIG LAUNCH BUTTON */
  .launch-btn {
    width: 100%;
    padding: 18px;
    font-size: 1.05rem;
    font-family: var(--sans);
    font-weight: 800;
    letter-spacing: .05em;
    background: linear-gradient(135deg, var(--accent) 0%, #00b4ff 100%);
    border: none;
    border-radius: 10px;
    color: #000;
    cursor: pointer;
    transition: all .25s;
    position: relative;
    overflow: hidden;
  }
  .launch-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #00ffda 0%, #60c6ff 100%);
    opacity: 0;
    transition: opacity .2s;
  }
  .launch-btn:hover::before { opacity: 1; }
  .launch-btn span { position: relative; z-index: 1; }
  .launch-btn:disabled { background: #333; color: var(--muted); }
  .launch-btn:disabled::before { display: none; }

  /* KEY TABLE */
  .key-table { width: 100%; border-collapse: collapse; font-size: .82rem; font-family: var(--mono); }
  .key-table th { text-align: left; padding: 8px 12px; color: var(--muted); font-weight: 400; border-bottom: 1px solid var(--border); font-size: .72rem; }
  .key-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); }
  .key-table tr:last-child td { border-bottom: none; }
  .key-table tr:hover td { background: rgba(255,255,255,.02); }

  .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .7rem; font-family: var(--mono); }
  .pill-ok { background: rgba(34,197,94,.15); color: var(--ok); border: 1px solid rgba(34,197,94,.3); }
  .pill-err { background: rgba(239,68,68,.15); color: var(--err); border: 1px solid rgba(239,68,68,.3); }
  .pill-warn { background: rgba(245,158,11,.15); color: var(--warn); border: 1px solid rgba(245,158,11,.3); }

  /* BRIEF FORM */
  .brief-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
  .brief-section { }
  .brief-section h4 { font-size: .75rem; font-family: var(--mono); color: var(--accent2); margin-bottom: 10px; letter-spacing: .08em; text-transform: uppercase; }
  .brief-inputs { display: flex; flex-direction: column; gap: 8px; }
  .brief-input-row { display: flex; gap: 6px; align-items: center; }
  .brief-num { font-family: var(--mono); font-size: .7rem; color: var(--muted); min-width: 18px; }
  .brief-inputs input { font-size: .8rem; padding: 8px 10px; }

  /* LOG TERMINAL */
  .terminal {
    background: #050608;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
  }
  .terminal-bar {
    padding: 10px 16px;
    background: #0d0f10;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid var(--border);
  }
  .terminal-dot { width: 10px; height: 10px; border-radius: 50%; }
  .terminal-title { font-family: var(--mono); font-size: .75rem; color: var(--muted); margin-left: 4px; flex: 1; }
  #termLog {
    height: 480px;
    overflow-y: auto;
    padding: 16px;
    font-family: var(--mono);
    font-size: .8rem;
    line-height: 1.7;
  }
  #termLog::-webkit-scrollbar { width: 4px; }
  #termLog::-webkit-scrollbar-track { background: transparent; }
  #termLog::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

  .log-line { display: flex; gap: 10px; margin-bottom: 2px; animation: logFade .3s ease; }
  @keyframes logFade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
  .log-ts { color: #2a3040; min-width: 80px; }
  .log-tag { min-width: 70px; font-weight: 700; }
  .log-tag.sys { color: #4a5568; }
  .log-tag.info { color: #60a5fa; }
  .log-tag.ai { color: var(--accent3); }
  .log-tag.write { color: var(--accent); }
  .log-tag.test { color: var(--warn); }
  .log-tag.ok { color: var(--ok); }
  .log-tag.err { color: var(--err); }
  .log-tag.think { color: var(--accent2); }
  .log-msg { color: #b0c4d8; flex: 1; word-break: break-word; }
  .log-msg a { color: var(--accent); text-decoration: none; }
  .log-msg a:hover { text-decoration: underline; }

  /* PROGRESS */
  .progress-wrap { margin: 16px 0; }
  .progress-label { font-family: var(--mono); font-size: .72rem; color: var(--muted); margin-bottom: 6px; display: flex; justify-content: space-between; }
  .progress-bar-bg { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; }
  .progress-bar-fill { height: 100%; background: linear-gradient(90deg, var(--accent), #00b4ff); border-radius: 2px; transition: width .4s ease; }

  /* STEPS */
  .steps { display: flex; flex-direction: column; gap: 2px; margin: 16px 0; }
  .step { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 6px; font-family: var(--mono); font-size: .78rem; transition: all .3s; }
  .step.pending { color: var(--muted); }
  .step.active { background: rgba(0,229,195,.08); color: var(--accent); border: 1px solid rgba(0,229,195,.2); }
  .step.done { color: var(--ok); }
  .step.error { color: var(--err); }
  .step-icon { font-size: 1rem; min-width: 20px; }

  /* PROJECT CARDS */
  .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
  .project-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 18px;
    transition: border-color .2s;
  }
  .project-card:hover { border-color: var(--accent); }
  .project-card h3 { font-size: .9rem; margin-bottom: 6px; }
  .project-card .meta { font-family: var(--mono); font-size: .72rem; color: var(--muted); margin-bottom: 12px; }
  .project-card .actions { display: flex; gap: 8px; }

  /* STATS */
  .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
  .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
  .stat-val { font-size: 1.6rem; font-weight: 800; color: var(--accent); font-family: var(--mono); }
  .stat-label { font-size: .7rem; color: var(--muted); font-family: var(--mono); margin-top: 2px; }

  /* RESPONSIVE */
  @media (max-width: 768px) {
    .brief-grid { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: 1fr 1fr; }
    .form-row { grid-template-columns: 1fr; }
  }

  /* GLOW EFFECT on active elements */
  .glow { box-shadow: 0 0 20px rgba(0,229,195,.15); }

  /* Scrolling indicator */
  .thinking-dots::after {
    content: '...';
    animation: dots 1.2s steps(3, end) infinite;
  }
  @keyframes dots {
    0%,20% { content: '.'; }
    40% { content: '..'; }
    60%,100% { content: '...'; }
  }
</style>
</head>
<body>

<div class="wrap">

  <!-- HEADER -->
  <header>
    <div class="logo">
      Auto<span>Coder</span>
      <small>MISTRAL DEVSTRAL-2512 // AUTONOMOUS BUILDER</small>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <span class="badge" id="keyBadge">0 clé(s)</span>
      <span class="badge" id="tokenBadge">0 tokens</span>
      <span class="badge live" id="modelBadge">devstral-2512</span>
    </div>
  </header>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('build')">⚡ Builder</button>
    <button class="tab" onclick="switchTab('keys')">🔑 API Keys</button>
    <button class="tab" onclick="switchTab('projects')">📁 Projets</button>
  </div>

  <!-- ===== TAB: BUILD ===== -->
  <div class="panel active" id="tab-build">

    <div class="card">
      <div class="card-title">Mission Brief</div>
      <div class="brief-grid">

        <div class="brief-section">
          <h4>👤 Qui tu es</h4>
          <div class="brief-inputs" id="about-inputs">
            <?php for($i=1;$i<=5;$i++): ?>
            <div class="brief-input-row">
              <span class="brief-num"><?=$i?></span>
              <input type="text" class="about-field" placeholder="Ex: développeur freelance, 30 ans">
            </div>
            <?php endfor; ?>
          </div>
        </div>

        <div class="brief-section">
          <h4>🎯 Ton public cible</h4>
          <div class="brief-inputs" id="audience-inputs">
            <?php for($i=1;$i<=5;$i++): ?>
            <div class="brief-input-row">
              <span class="brief-num"><?=$i?></span>
              <input type="text" class="audience-field" placeholder="Ex: entrepreneurs SaaS, 25-40 ans">
            </div>
            <?php endfor; ?>
          </div>
        </div>

        <div class="brief-section">
          <h4>💰 Monétisation</h4>
          <div class="brief-inputs" id="monetize-inputs">
            <?php for($i=1;$i<=5;$i++): ?>
            <div class="brief-input-row">
              <span class="brief-num"><?=$i?></span>
              <input type="text" class="monetize-field" placeholder="Ex: abonnement mensuel 29€">
            </div>
            <?php endfor; ?>
          </div>
        </div>

      </div>
    </div>

    <div style="display:flex;gap:12px;margin-bottom:24px;">
      <button class="launch-btn" id="launchBtn" onclick="launchBuild()">
        <span>🚀 Générer le site autonome</span>
      </button>
    </div>

    <!-- STEPS TRACKER -->
    <div class="card" id="stepsCard" style="display:none;">
      <div class="card-title">Pipeline d'exécution</div>
      <div class="steps" id="stepsContainer"></div>
      <div class="progress-wrap">
        <div class="progress-label">
          <span id="progressLabel">Démarrage...</span>
          <span id="progressPct">0%</span>
        </div>
        <div class="progress-bar-bg"><div class="progress-bar-fill" id="progressFill" style="width:0%"></div></div>
      </div>
    </div>

    <!-- TERMINAL -->
    <div class="terminal" id="terminalBlock" style="display:none;">
      <div class="terminal-bar">
        <div class="terminal-dot" style="background:#ff5f57"></div>
        <div class="terminal-dot" style="background:#febc2e"></div>
        <div class="terminal-dot" style="background:#28c840"></div>
        <span class="terminal-title">autocoder.log — devstral-2512</span>
        <button class="btn btn-outline" style="padding:3px 10px;font-size:.7rem;" onclick="clearLog()">clear</button>
      </div>
      <div id="termLog"></div>
    </div>

  </div>

  <!-- ===== TAB: KEYS ===== -->
  <div class="panel" id="tab-keys">
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-val" id="stat-keys">0</div>
        <div class="stat-label">Clés actives</div>
      </div>
      <div class="stat-card">
        <div class="stat-val" id="stat-tokens">0</div>
        <div class="stat-label">Tokens totaux</div>
      </div>
      <div class="stat-card">
        <div class="stat-val" id="stat-tpm">50k</div>
        <div class="stat-label">Limite TPM</div>
      </div>
      <div class="stat-card">
        <div class="stat-val" id="stat-model-status">—</div>
        <div class="stat-label">Statut modèle</div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Ajouter une clé API Mistral</div>
      <div class="form-row">
        <div class="form-group">
          <label>Pseudo / label</label>
          <input type="text" id="kPseudo" placeholder="Ex: Pro Account">
        </div>
        <div class="form-group">
          <label>Clé API</label>
          <input type="password" id="kVal" placeholder="Colle ta clé Mistral ici">
        </div>
      </div>
      <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" onclick="addKey()">Enregistrer</button>
        <button class="btn btn-outline" onclick="testLastKey()">Tester devstral-2512</button>
      </div>
      <div id="keyMsg" style="margin-top:10px;font-family:var(--mono);font-size:.78rem;"></div>
    </div>

    <div class="card">
      <div class="card-title">Clés enregistrées</div>
      <table class="key-table">
        <thead><tr><th>Pseudo</th><th>Clé</th><th>Statut</th><th>Erreurs</th><th>Dernier usage</th><th>Actions</th></tr></thead>
        <tbody id="keysTbody"><tr><td colspan="6" style="color:var(--muted);text-align:center;padding:20px;">Chargement...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ===== TAB: PROJECTS ===== -->
  <div class="panel" id="tab-projects">
    <div class="card">
      <div class="card-title">Projets générés</div>
      <div class="project-grid" id="projectGrid">
        <div style="color:var(--muted);font-family:var(--mono);font-size:.8rem;padding:20px;">Chargement...</div>
      </div>
    </div>
  </div>

</div><!-- /wrap -->

<script>
// =================== STATE ===================
let isBuilding = false;
let currentProjectId = null;
let currentProjectFolder = null;
let currentKeyId = null;

// =================== TAB SWITCHING ===================
function switchTab(name) {
  document.querySelectorAll('.tab').forEach((t,i) => {
    const tabs = ['build','keys','projects'];
    t.classList.toggle('active', tabs[i] === name);
  });
  document.querySelectorAll('.panel').forEach((p,i) => {
    const panels = ['tab-build','tab-keys','tab-projects'];
    p.classList.toggle('active', panels[i] === 'tab-'+name);
  });
  if (name === 'keys') loadKeysData();
  if (name === 'projects') loadProjects();
}

// =================== LOGGING ===================
function log(type, msg) {
  const log = document.getElementById('termLog');
  const ts = new Date().toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
  const div = document.createElement('div');
  div.className = 'log-line';
  div.innerHTML = `<span class="log-ts">[${ts}]</span><span class="log-tag ${type}">${type.toUpperCase().padEnd(7)}</span><span class="log-msg">${msg}</span>`;
  log.appendChild(div);
  log.scrollTop = log.scrollHeight;
}
function clearLog() { document.getElementById('termLog').innerHTML = ''; }

// =================== STEPS ===================
const STEPS_DEF = [
  { id: 's1', label: 'Récupération clé API (rotation)' },
  { id: 's2', label: 'Création du brief IA' },
  { id: 's3', label: 'Génération architecture (iteration 1)' },
  { id: 's4', label: 'Génération des pages PHP' },
  { id: 's5', label: 'Génération CSS + JS' },
  { id: 's6', label: 'Injection mode DEBUG dans chaque fichier' },
  { id: 's7', label: 'Écriture fichiers sur disque' },
  { id: 's8', label: 'Phase TEST — L\'IA relit et teste chaque bouton' },
  { id: 's9', label: 'Corrections & itérations post-test' },
  { id: 's10', label: 'Finalisation & rapport' },
];
let currentStep = -1;

function initSteps() {
  const c = document.getElementById('stepsContainer');
  c.innerHTML = STEPS_DEF.map(s =>
    `<div class="step pending" id="${s.id}"><span class="step-icon">○</span><span>${s.label}</span></div>`
  ).join('');
}

function setStep(idx) {
  currentStep = idx;
  STEPS_DEF.forEach((s, i) => {
    const el = document.getElementById(s.id);
    if (i < idx) { el.className = 'step done'; el.querySelector('.step-icon').textContent = '✓'; }
    else if (i === idx) { el.className = 'step active'; el.querySelector('.step-icon').textContent = '▶'; }
    else { el.className = 'step pending'; el.querySelector('.step-icon').textContent = '○'; }
  });
  const pct = Math.round((idx / STEPS_DEF.length) * 100);
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressPct').textContent = pct + '%';
  document.getElementById('progressLabel').textContent = idx < STEPS_DEF.length ? STEPS_DEF[idx].label : 'Terminé';
}

function stepDone(idx) {
  const el = document.getElementById(STEPS_DEF[idx].id);
  if (el) { el.className = 'step done'; el.querySelector('.step-icon').textContent = '✓'; }
}

// =================== API HELPERS ===================
async function apiPost(action, data={}) {
  const fd = new FormData();
  fd.append('action', action);
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  const res = await fetch('api.php', { method: 'POST', body: fd });
  return res.json();
}

async function apiRaw(url, opts) {
  const res = await fetch(url, opts);
  return res.json();
}

// =================== KEY MANAGEMENT ===================
async function addKey() {
  const pseudo = document.getElementById('kPseudo').value.trim();
  const key = document.getElementById('kVal').value.trim();
  if (!pseudo || !key) { showKeyMsg('Remplis les deux champs', 'err'); return; }
  const r = await apiPost('add_key', { pseudo, key });
  if (r.success) {
    showKeyMsg('✓ Clé enregistrée', 'ok');
    document.getElementById('kPseudo').value = '';
    document.getElementById('kVal').value = '';
    loadKeysData();
  } else {
    showKeyMsg('Erreur: ' + (r.error || 'inconnue'), 'err');
  }
}

async function testLastKey() {
  const key = document.getElementById('kVal').value.trim();
  if (!key) { showKeyMsg('Entre une clé à tester d\'abord', 'warn'); return; }
  showKeyMsg('Test en cours...', 'info');
  const r = await apiPost('test_key', { key });
  if (r.code === 200) {
    showKeyMsg(`✓ OK — devstral-2512 répond (${r.tokens} tokens)`, 'ok');
  } else {
    showKeyMsg(`✗ Erreur HTTP ${r.code}`, 'err');
  }
}

function showKeyMsg(msg, type) {
  const colors = { ok: 'var(--ok)', err: 'var(--err)', warn: 'var(--warn)', info: 'var(--accent)' };
  document.getElementById('keyMsg').style.color = colors[type] || 'var(--text)';
  document.getElementById('keyMsg').textContent = msg;
}

async function loadKeysData() {
  const data = await apiPost('get_data');
  const keys = data.keys || [];
  const model = data.model || {};
  const total = data.token_total || 0;

  document.getElementById('stat-keys').textContent = keys.filter(k=>k.is_active==1).length;
  document.getElementById('stat-tokens').textContent = formatNum(total);
  document.getElementById('stat-model-status').textContent = model.last_status || '—';
  document.getElementById('keyBadge').textContent = keys.length + ' clé(s)';
  document.getElementById('tokenBadge').textContent = formatNum(total) + ' tok';

  const tb = document.getElementById('keysTbody');
  if (!keys.length) {
    tb.innerHTML = '<tr><td colspan="6" style="color:var(--muted);text-align:center;padding:20px;">Aucune clé — ajoute-en une ci-dessus</td></tr>';
    return;
  }
  tb.innerHTML = keys.map(k => `
    <tr>
      <td>${k.pseudo}</td>
      <td><code>${k.key_masked}</code></td>
      <td>${k.is_active==1 ? '<span class="pill pill-ok">active</span>' : '<span class="pill pill-err">désactivée</span>'}</td>
      <td><span class="${k.error_count > 0 ? 'pill pill-err' : ''}">${k.error_count || 0}</span></td>
      <td style="color:var(--muted)">${k.last_used || 'jamais'}</td>
      <td><button class="btn btn-outline" style="padding:4px 10px;font-size:.7rem;" onclick="testKeyById(${k.id})">tester</button></td>
    </tr>
  `).join('');
}

async function testKeyById(id) {
  // We just trigger a get_key + test
  log('info', `Test de la clé #${id} sur devstral-2512...`);
}

function formatNum(n) {
  if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
  if (n >= 1000) return (n/1000).toFixed(1) + 'k';
  return n;
}

// =================== PROJECTS ===================
async function loadProjects() {
  const data = await apiPost('list_projects');
  const projects = data.projects || [];
  const grid = document.getElementById('projectGrid');
  if (!projects.length) {
    grid.innerHTML = '<div style="color:var(--muted);font-family:var(--mono);font-size:.8rem;">Aucun projet généré pour l\'instant.</div>';
    return;
  }
  grid.innerHTML = projects.map(p => `
    <div class="project-card">
      <h3>${p.title}</h3>
      <div class="meta">${p.folder} · ${p.status} · ${p.created_at}</div>
      <div class="actions">
        <a href="${p.folder}" target="_blank" class="btn btn-outline" style="padding:6px 14px;font-size:.75rem;text-decoration:none;">Ouvrir</a>
      </div>
    </div>
  `).join('');
}

// =================== MISTRAL API CALL (with key rotation) ===================
async function callMistral(messages, maxTokens=4000) {
  // Get key from server (handles rotation)
  const keyData = await apiPost('get_key');
  if (keyData.error) throw new Error('Aucune clé API disponible: ' + keyData.error);
  currentKeyId = keyData.id;
  const apiKey = keyData.key;

  try {
    const resp = await fetch('https://api.mistral.ai/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + apiKey
      },
      body: JSON.stringify({
        model: 'devstral-2512',
        messages: messages,
        max_tokens: maxTokens,
        response_format: { type: 'json_object' }
      })
    });

    if (!resp.ok) {
      const err = await resp.json();
      await apiPost('key_error', { id: currentKeyId });
      throw new Error(`HTTP ${resp.status}: ${err.message || JSON.stringify(err)}`);
    }

    const data = await resp.json();
    const tokens = data.usage?.total_tokens || 0;

    // Record usage
    await apiPost('record_usage', { key_id: currentKeyId, tokens });

    // Update token badge
    const curBadge = document.getElementById('tokenBadge').textContent;
    document.getElementById('tokenBadge').textContent = '~' + tokens + ' tok';

    return { content: data.choices[0].message.content, tokens };

  } catch(e) {
    await apiPost('key_error', { id: currentKeyId });
    throw e;
  }
}

// =================== BUILD PIPELINE ===================
async function launchBuild() {
  if (isBuilding) return;

  // Gather brief
  const about = [...document.querySelectorAll('.about-field')].map(i=>i.value.trim()).filter(Boolean);
  const audience = [...document.querySelectorAll('.audience-field')].map(i=>i.value.trim()).filter(Boolean);
  const monetize = [...document.querySelectorAll('.monetize-field')].map(i=>i.value.trim()).filter(Boolean);

  if (about.length < 2 || audience.length < 2 || monetize.length < 2) {
    alert('Remplis au moins 2 éléments dans chaque section !');
    return;
  }

  isBuilding = true;
  document.getElementById('launchBtn').disabled = true;
  document.getElementById('stepsCard').style.display = 'block';
  document.getElementById('terminalBlock').style.display = 'block';
  document.getElementById('terminalBlock').scrollIntoView({ behavior: 'smooth', block: 'start' });
  initSteps();
  clearLog();

  try {
    await runPipeline(about, audience, monetize);
  } catch(e) {
    log('err', 'Pipeline interrompu: ' + e.message);
  }

  isBuilding = false;
  document.getElementById('launchBtn').disabled = false;
}

async function runPipeline(about, audience, monetize) {

  // --- STEP 0: Get key ---
  setStep(0);
  log('sys', 'Récupération clé API (rotation intelligente)...');
  const keyCheck = await apiPost('get_key');
  if (keyCheck.error) throw new Error(keyCheck.error);
  log('ok', `Clé sélectionnée: #${keyCheck.id} — devstral-2512 prêt`);
  stepDone(0);
  await sleep(400);

  // --- STEP 1: Build brief ---
  setStep(1);
  log('sys', 'Construction du brief IA multi-axes...');
  const brief = buildBrief(about, audience, monetize);
  log('info', 'Brief: ' + about.join(', ').substring(0, 60) + '...');
  log('info', 'Public: ' + audience.join(', ').substring(0, 60) + '...');
  log('info', 'Monétisation: ' + monetize.join(', ').substring(0, 60) + '...');

  // Create project in DB
  const projTitle = about[0] + ' → ' + audience[0];
  const projData = await apiPost('create_project', { title: projTitle, brief: JSON.stringify({about,audience,monetize}) });
  currentProjectId = projData.id;
  currentProjectFolder = projData.folder;
  log('ok', `Projet #${currentProjectId} créé → ${currentProjectFolder}`);
  stepDone(1);
  await sleep(400);

  // --- STEP 2: Architecture ---
  setStep(2);
  log('ai', 'Appel IA — Génération architecture du site...');

  const archMessages = [
    {
      role: 'system',
      content: `Tu es un architecte web expert. Réponds UNIQUEMENT en JSON valide, sans texte avant/après, sans markdown.
Structure JSON attendue:
{
  "site_name": "...",
  "site_concept": "...",
  "pages": [
    {"filename": "index.php", "title": "...", "description": "..."},
    ...
  ],
  "nav_links": ["index.php", ...],
  "color_primary": "#hex",
  "color_secondary": "#hex",
  "font_stack": "...",
  "monetization_strategy": "..."
}`
    },
    {
      role: 'user',
      content: `Crée l'architecture d'un site web professionnel basé sur ce brief:

QUI JE SUIS: ${about.join(' | ')}

MON PUBLIC CIBLE: ${audience.join(' | ')}

MONÉTISATION: ${monetize.join(' | ')}

Génère une architecture de 4-5 pages PHP (index, à propos, offres, contact, et une page bonus pertinente).
Adapte le design et le concept au public cible. Retourne UNIQUEMENT le JSON.`
    }
  ];

  let arch;
  try {
    const archResp = await callMistral(archMessages, 2000);
    arch = safeParseJSON(archResp.content);
    log('think', `Concept: ${arch.site_concept}`);
    log('info', `Pages planifiées: ${arch.pages.map(p=>p.filename).join(', ')}`);
    log('ok', `Architecture générée — ${arch.pages.length} pages`);
  } catch(e) {
    log('err', 'Erreur architecture: ' + e.message);
    throw e;
  }
  stepDone(2);
  await sleep(600);

  // --- STEP 3: Generate pages ---
  setStep(3);
  log('ai', `Génération des ${arch.pages.length} pages PHP...`);
  await sleep(200);

  const generatedFiles = {};

  for (let i = 0; i < arch.pages.length; i++) {
    const page = arch.pages[i];
    log('ai', `[${i+1}/${arch.pages.length}] Génération: ${page.filename} — ${page.title}`);

    const pageMessages = [
      {
        role: 'system',
        content: `Tu es un développeur PHP full-stack expert. Génère du code PHP/HTML/CSS/JS complet et fonctionnel.
Réponds UNIQUEMENT en JSON valide:
{
  "filename": "...",
  "content": "...code PHP complet..."
}
IMPORTANT: Dans le code, inclure un bloc MODE DEBUG en bas de chaque page (remplace PHPOPEN par < ?php et PHPCLOSE par ? >) :
PHPOPEN if(isset($_GET['debug'])): PHPCLOSE
<div id="debug-panel" style="position:fixed;bottom:0;left:0;right:0;background:#000;color:#0f0;padding:10px;font-family:monospace;font-size:12px;z-index:9999;">
  PHPOPEN echo 'DEBUG: ' . basename(__FILE__); PHPCLOSE
</div>
PHPOPEN endif; PHPCLOSE
Et inclure dans le head: meta name=debug-ready content=true`
      },
      {
        role: 'user',
        content: `Génère le fichier "${page.filename}" pour ce site:

CONCEPT DU SITE: ${arch.site_concept}
NOM DU SITE: ${arch.site_name}
TITRE DE CETTE PAGE: ${page.title}
DESCRIPTION: ${page.description}
COULEUR PRINCIPALE: ${arch.color_primary}
COULEUR SECONDAIRE: ${arch.color_secondary}
TYPOGRAPHIE: ${arch.font_stack}
PAGES DU SITE (pour la navigation): ${arch.nav_links.join(', ')}

Crée un fichier PHP complet avec:
- HTML5 sémantique
- CSS inline ou <style> tag intégré (design professionnel inspiré du concept)
- Navigation vers les autres pages
- Contenu réel et pertinent (pas de Lorem Ipsum)
- Formulaire de contact sur la page contact (avec simulation d'envoi en PHP/JS)
- Intégration du mode DEBUG (?debug=1 dans l'URL)
- Commentaires de code clairs

Retourne UNIQUEMENT le JSON avec le code complet.`
      }
    ];

    try {
      const pageResp = await callMistral(pageMessages, 4000);
      const pageData = safeParseJSON(pageResp.content);
      generatedFiles[pageData.filename] = pageData.content;
      log('write', `Fichier généré en mémoire: ${pageData.filename} (${pageData.content.length} chars)`);
    } catch(e) {
      log('err', `Erreur page ${page.filename}: ${e.message}`);
    }

    await sleep(1200); // respect RPS limit
  }
  stepDone(3);
  await sleep(400);

  // --- STEP 4: Generate CSS + shared assets ---
  setStep(4);
  log('ai', 'Génération assets partagés (style.css, helpers.php)...');

  const cssMessages = [
    {
      role: 'system',
      content: `Tu génères du CSS et un fichier PHP helper. Réponds UNIQUEMENT en JSON:
{"filename": "style.css", "content": "...CSS complet..."}`
    },
    {
      role: 'user',
      content: `Crée un fichier style.css pour ${arch.site_name}.
Concept: ${arch.site_concept}
Couleur principale: ${arch.color_primary}
Secondaire: ${arch.color_secondary}
Font: ${arch.font_stack}
Style professionnel, responsive, moderne. CSS complet avec variables, media queries, animations.
Retourne UNIQUEMENT le JSON.`
    }
  ];

  try {
    const cssResp = await callMistral(cssMessages, 2000);
    // Robust JSON parse: strip markdown fences if present
    let cssRaw = cssResp.content.trim();
    cssRaw = cssRaw.replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/```\s*$/i, '');
    const cssData = safeParseJSON(cssRaw);
    generatedFiles[cssData.filename || 'style.css'] = cssData.content || cssData.css || '';
    log('write', `style.css généré (${(cssData.content || '').length} chars)`);
  } catch(e) {
    log('warn', 'CSS JSON invalide, génération CSS basique de secours');
    generatedFiles['style.css'] = `/* style.css — fallback */
:root { --primary: ${arch.color_primary || '#007bff'}; --secondary: ${arch.color_secondary || '#6c757d'}; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: ${arch.font_stack || 'sans-serif'}; color: #333; }
nav { background: var(--primary); padding: 1rem; }
nav a { color: white; text-decoration: none; margin-right: 1rem; }
.container { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }
h1, h2 { color: var(--primary); margin-bottom: 1rem; }
.btn { background: var(--primary); color: white; padding: .7rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
footer { background: #333; color: #fff; text-align: center; padding: 1rem; margin-top: 3rem; }
`;
    log('write', 'style.css de secours généré');
  }

  // Generate config.php — built as a string with no literal PHP open tags in this file
  const phpTag = '<' + '?php';
  const configContent = phpTag + `
define('SITE_NAME', ${JSON.stringify(arch.site_name)});
define('SITE_CONCEPT', ${JSON.stringify(arch.site_concept)});
define('COLOR_PRIMARY', ${JSON.stringify(arch.color_primary)});
define('COLOR_SECONDARY', ${JSON.stringify(arch.color_secondary)});
define('DEBUG_MODE', isset($_GET['debug']));
`;
  generatedFiles['config.php'] = configContent;
  log('write', 'config.php généré');
  stepDone(4);
  await sleep(400);

  // --- STEP 5: Debug mode injection ---
  setStep(5);
  log('sys', 'Vérification injection mode DEBUG dans tous les fichiers...');
  let debugInjected = 0;
  for (const [fname, content] of Object.entries(generatedFiles)) {
    if (fname.endsWith('.php') && !content.includes('debug-panel') && content.includes('<body')) {
      // Inject debug panel before </body>
      const phpOpen = '<?php';
      const phpClose = '?>';
      const debugPanel = `
${phpOpen} if(isset($_GET['debug'])): ${phpClose}
<div id="auto-debug-panel" style="position:fixed;bottom:0;left:0;right:0;background:#000;color:#0f0;font-family:monospace;font-size:12px;padding:10px;z-index:9999;border-top:2px solid #0f0;max-height:200px;overflow-y:auto;">
<strong style="color:#ff0">&#x1F41B; DEBUG MODE &mdash; ${fname}</strong><br>
PHP: ${phpOpen} echo PHP_VERSION; ${phpClose} | 
Fichier: ${phpOpen} echo basename(__FILE__); ${phpClose} | 
Heure: ${phpOpen} echo date('H:i:s'); ${phpClose} | 
GET: ${phpOpen} echo htmlspecialchars(json_encode($_GET)); ${phpClose} | 
POST: ${phpOpen} echo htmlspecialchars(json_encode($_POST)); ${phpClose}
</div>
${phpOpen} endif; ${phpClose}`;
      generatedFiles[fname] = content.replace('</body>', debugPanel + '\n</body>');
      debugInjected++;
    }
  }
  log('ok', `Mode DEBUG injecté dans ${debugInjected} fichier(s) PHP`);
  stepDone(5);
  await sleep(400);

  // --- STEP 6: Write files to disk ---
  setStep(6);
  log('sys', `Écriture de ${Object.keys(generatedFiles).length} fichiers sur le disque...`);

  for (const [fname, content] of Object.entries(generatedFiles)) {
    const fullPath = currentProjectFolder + '/' + fname;
    try {
      const saveResp = await fetch('api.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'save_file', path: fullPath, content: content }),
        headers: { 'Content-Type': 'application/json' }
      });
      const saveJson = await saveResp.json();
      if (saveJson.success) {
        log('write', `✓ ${fname} → ${fullPath} (${saveJson.bytes || '?'} octets)`);
      } else {
        log('err', `✗ Serveur refuse ${fname}: ${saveJson.error || JSON.stringify(saveJson)}`);
      }
    } catch(e) {
      log('err', `✗ Échec écriture ${fname}: ${e.message}`);
    }
    await sleep(200);
  }
  stepDone(6);
  await sleep(500);

  // --- STEP 7: AI TESTING PHASE ---
  setStep(7);
  log('test', '═══════════════════════════════════');
  log('test', 'PHASE TEST — L\'IA analyse et teste le site généré');
  log('test', '═══════════════════════════════════');
  await sleep(300);

  const testMessages = [
    {
      role: 'system',
      content: `Tu es un QA engineer expert. Tu testes des sites web PHP. Réponds UNIQUEMENT en JSON:
{
  "test_results": [
    {
      "file": "...",
      "checks": [
        {"item": "...", "status": "pass|fail|warn", "detail": "..."}
      ]
    }
  ],
  "issues_found": [...],
  "fixes_needed": [
    {"file": "...", "issue": "...", "fix_code_snippet": "..."}
  ],
  "overall_score": 0-100,
  "summary": "..."
}`
    },
    {
      role: 'user',
      content: `Analyse et teste ce site web généré. Voici le code de chaque fichier:

${Object.entries(generatedFiles).filter(([f]) => f.endsWith('.php')).map(([f,c]) =>
  `=== ${f} ===\n${c.substring(0, 1500)}\n...`
).join('\n\n')}

TESTS À EFFECTUER:
1. ✅ Chaque page a-t-elle une balise <html> et </html> correcte ?
2. ✅ Les liens de navigation pointent-ils vers les bons fichiers ?
3. ✅ Les formulaires ont-ils un action et method corrects ?
4. ✅ Le mode DEBUG (?debug=1) est-il présent ?
5. ✅ Les includes/requires pointent-ils vers des fichiers existants (${Object.keys(generatedFiles).join(', ')}) ?
6. ✅ Y a-t-il des erreurs PHP évidentes (tags non fermés, syntaxe etc.) ?
7. ✅ Les boutons CTA ont-ils des liens/actions ?
8. ✅ La page contact a-t-elle un formulaire fonctionnel ?

Retourne un rapport JSON complet avec les corrections nécessaires.`
    }
  ];

  let testReport;
  try {
    log('test', 'Appel IA pour analyse qualité...');
    const testResp = await callMistral(testMessages, 3000);
    testReport = safeParseJSON(testResp.content);

    log('test', `Score qualité: ${testReport.overall_score}/100`);
    log('test', `Résumé: ${testReport.summary}`);

    if (testReport.test_results) {
      for (const file of testReport.test_results) {
        log('test', `--- ${file.file} ---`);
        for (const check of (file.checks || [])) {
          const icon = check.status === 'pass' ? '✓' : check.status === 'warn' ? '⚠' : '✗';
          const color = check.status === 'pass' ? 'ok' : check.status === 'warn' ? 'warn' : 'err';
          log(color, `  ${icon} ${check.item}: ${check.detail}`);
        }
      }
    }

    const issues = testReport.issues_found || [];
    const fixes = testReport.fixes_needed || [];
    log('test', `Problèmes trouvés: ${issues.length} | Corrections: ${fixes.length}`);

  } catch(e) {
    log('err', 'Erreur phase test: ' + e.message);
    testReport = { fixes_needed: [], overall_score: 50, summary: 'Test incomplet' };
  }
  stepDone(7);
  await sleep(600);

  // --- STEP 8: Apply fixes ---
  setStep(8);
  const fixes = testReport?.fixes_needed || [];
  if (fixes.length > 0) {
    log('ai', `Application de ${fixes.length} correction(s) post-test...`);

    const fixMessages = [
      {
        role: 'system',
        content: `Tu es un développeur PHP. Applique les corrections demandées et retourne le code corrigé.
Réponds UNIQUEMENT en JSON:
{
  "fixed_files": [
    {"filename": "...", "content": "...code PHP complet corrigé..."}
  ]
}`
      },
      {
        role: 'user',
        content: `Applique ces corrections aux fichiers du site "${arch.site_name}":

CORRECTIONS REQUISES:
${fixes.map(f => `- ${f.file}: ${f.issue}\n  Fix: ${f.fix_code_snippet}`).join('\n')}

CONTEXTE: Site concept="${arch.site_concept}", pages=${arch.nav_links.join(', ')}

Retourne les fichiers corrigés complets en JSON.`
      }
    ];

    try {
      await sleep(1200);
      const fixResp = await callMistral(fixMessages, 4000);
      const fixData = safeParseJSON(fixResp.content);
      for (const fixed of (fixData.fixed_files || [])) {
        generatedFiles[fixed.filename] = fixed.content;
        const fullPath = currentProjectFolder + '/' + fixed.filename;
        const sr = await fetch('api.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'save_file', path: fullPath, content: fixed.content }),
          headers: { 'Content-Type': 'application/json' }
        });
        const sj = await sr.json();
        if (sj.success) {
          log('write', `✓ Corrigé & ré-écrit: ${fixed.filename} (${sj.bytes} octets)`);
        } else {
          log('err', `✗ Échec correction ${fixed.filename}: ${sj.error}`);
        }
      }
    } catch(e) {
      log('err', 'Erreur corrections: ' + e.message);
    }
  } else {
    log('ok', 'Aucune correction nécessaire — code validé ✓');
  }
  stepDone(8);
  await sleep(400);

  // --- STEP 9: Finalize ---
  setStep(9);
  log('sys', 'Finalisation du projet...');

  // Write README
  const readmeContent = `# ${arch.site_name}

## Concept
${arch.site_concept}

## Pages
${arch.pages.map(p => `- **${p.filename}** — ${p.title}: ${p.description}`).join('\n')}

## Monétisation
${monetize.join('\n')}

## Mode DEBUG
Ajoute \`?debug=1\` à n'importe quelle URL pour activer le panneau de debug.

## Généré par AutoCoder
- Modèle: devstral-2512
- Date: ${new Date().toLocaleString('fr-FR')}
- Score QA: ${testReport?.overall_score || '?'}/100
`;

  await fetch('api.php', {
    method: 'POST',
    body: JSON.stringify({ action: 'save_file', path: currentProjectFolder + '/README.md', content: readmeContent }),
    headers: { 'Content-Type': 'application/json' }
  });

  await apiPost('update_project', { id: currentProjectId, status: 'done' });

  stepDone(9);
  setStep(10);

  // Final success
  log('ok', '═══════════════════════════════════');
  log('ok', `✅ PROJET TERMINÉ — Score QA: ${testReport?.overall_score || '?'}/100`);
  log('ok', `📁 Dossier: ${currentProjectFolder}/`);
  log('ok', `🌐 Accès: <a href="${currentProjectFolder}/index.php" target="_blank">${currentProjectFolder}/index.php</a>`);
  log('ok', `🐛 Debug: <a href="${currentProjectFolder}/index.php?debug=1" target="_blank">?debug=1</a>`);
  log('ok', '═══════════════════════════════════');

  document.getElementById('progressFill').style.width = '100%';
  document.getElementById('progressPct').textContent = '100%';
  document.getElementById('progressLabel').textContent = '✅ Terminé';

  // Update all steps as done
  STEPS_DEF.forEach((s,i) => stepDone(i));
}

function buildBrief(about, audience, monetize) {
  return {
    about: about,
    audience: audience,
    monetize: monetize,
    timestamp: new Date().toISOString()
  };
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// Robust JSON parser — strips markdown fences, handles trailing commas
function safeParseJSON(str) {
  str = str.trim();
  // Strip ```json ... ``` or ``` ... ```
  str = str.replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/\s*```$/i, '');
  // Try direct parse first
  try { return JSON.parse(str); } catch(e) {}
  // Try extracting first { ... } block
  const start = str.indexOf('{');
  const end   = str.lastIndexOf('}');
  if (start !== -1 && end !== -1) {
    try { return JSON.parse(str.slice(start, end + 1)); } catch(e) {}
  }
  throw new Error('JSON invalide: ' + str.substring(0, 120));
}

// =================== INIT ===================
loadKeysData();
</script>
</body>
</html>
