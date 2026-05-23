// ══════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════
let isBuilding = false;
let activeProjectId = null;
let activeProjectFolder = '';

// ══════════════════════════════════════════════
// API HELPERS
// ══════════════════════════════════════════════
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

// ══════════════════════════════════════════════
// TABS
// ══════════════════════════════════════════════
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelector(`.tab-btn[onclick*="${name}"]`)?.classList.add('active');
  document.getElementById('panel' + name.charAt(0).toUpperCase() + name.slice(1)).classList.add('active');
  if (name === 'keys') loadKeys();
  if (name === 'projects') { loadProjects(); loadStats(); }
  if (name === 'dashboard') { loadDashboard(); }
}

// ══════════════════════════════════════════════
// STACK OPTIONS
// ══════════════════════════════════════════════
function updateStackOptions() {
  const type = document.getElementById('projType').value;
  const opts = STACK_OPTIONS[type];
  document.getElementById('frontend').innerHTML = opts.frontends.map(k => `<option value="${k}">${FRONTEND_LABELS[k] || k}</option>`).join('');
  document.getElementById('backend').innerHTML = opts.backends.map(k => `<option value="${k}">${BACKEND_LABELS[k] || k}</option>`).join('');
  document.getElementById('database').innerHTML = opts.databases.map(k => `<option value="${k}">${DB_LABELS[k] || k}</option>`).join('');
  document.getElementById('css').innerHTML = opts.css.map(k => `<option value="${k}">${CSS_LABELS[k] || k}</option>`).join('');
}
document.addEventListener('DOMContentLoaded', updateStackOptions);

// ══════════════════════════════════════════════
// KEYS
// ══════════════════════════════════════════════
const PROVIDER_ICONS = { 'mistral': '🔮', 'openai': '🤖', 'anthropic': '🌿', 'google': '🔬' };

async function addKey() {
  const label = document.getElementById('keyLabel').value.trim();
  const key = document.getElementById('keyVal').value.trim();
  const provider = document.getElementById('keyProvider').value;
  if (!label || !key) { showKeyMsg('Remplis tous les champs', 'err'); return; }
  const r = await api('add_key', { label, key, provider });
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
      <div><strong style="font-size:.85rem;">${PROVIDER_ICONS[k.provider] || '🔑'} ${k.label}</strong><br>
        <span style="color:var(--text-3);font-family:var(--mono);font-size:.72rem;">${k.key_masked}</span>
        <span style="font-size:.65rem;color:var(--accent);font-family:var(--mono);">${k.provider}</span>
      </div>
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

// ══════════════════════════════════════════════
// PROJECTS
// ══════════════════════════════════════════════
async function loadProjects() {
  const data = await api('list_projects');
  const projects = data.projects || [];
  const el = document.getElementById('projectsList');
  if (!projects.length) { el.innerHTML = '<div class="empty-state">Aucun projet. Lancez un build !</div>'; return; }
  el.innerHTML = projects.map(p => `
    <div class="item-row" style="cursor:pointer;">
      <div onclick="showProjectDetail(${p.id})" style="flex:1;min-width:0;">
        <strong style="font-size:.85rem;">${p.title}</strong>
        <div style="font-size:.7rem;color:var(--text-3);">
          <span class="stack-tag">${p.frontend}</span>
          <span class="stack-tag">${p.backend}</span>
          ${p.build_validated ? '<span class="pill pill-green">✅ build</span>' : ''}
          ${p.qa_score > 0 ? `<span class="pill pill-green">${p.qa_score}/100</span>` : ''}
        </div>
      </div>
      <div style="text-align:right;display:flex;align-items:center;gap:4px;">
        <span class="pill ${p.status === 'done' ? 'pill-green' : p.status === 'failed' ? 'pill-red' : 'pill-blue'}">${p.status}</span>
        <button class="btn btn-sm btn-outline" onclick="event.stopPropagation();quickDownload(${p.id})" title="📦">📦</button>
        <button class="btn btn-sm btn-danger" onclick="event.stopPropagation();quickDelete(${p.id})" title="🗑️">🗑️</button>
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

// ══════════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════════
async function loadDashboard() {
  const d = await api('get_stats');
  const s = d.stats || {};
  document.getElementById('dashKeys').textContent = (s.keys_active ?? 0) + '/' + (s.keys_total ?? 0);
  document.getElementById('dashTokens').textContent = s.tokens_total >= 1000 ? Math.round(s.tokens_total/1000)+'k' : s.tokens_total || 0;
  document.getElementById('dashProjects').textContent = s.projects_total || 0;
  document.getElementById('dashDone').textContent = s.projects_done || 0;
  document.getElementById('dashFailed').textContent = s.projects_failed || 0;
  document.getElementById('dashAvgScore').textContent = s.avg_score ? s.avg_score + '/100' : '-';
  renderTokenDayChart(s.tokens_by_day || []);
  renderTokenStepChart(s.tokens_by_step || []);
  renderTopProjects(s.top_projects || []);
  renderRecentProjects(s.recent_projects || []);
}

function renderTokenDayChart(data) {
  const el = document.getElementById('tokenDayChart');
  if (!data.length) { el.innerHTML = '<div class="empty-state">Aucune donnée de tokens</div>'; return; }
  const maxVal = Math.max(...data.map(d => parseInt(d.tokens)));
  el.innerHTML = '<div class="bar-chart">' + data.map(d => {
    const pct = maxVal > 0 ? (parseInt(d.tokens) / maxVal * 100) : 0;
    const label = d.day.slice(5);
    const val = parseInt(d.tokens) >= 1000 ? Math.round(parseInt(d.tokens)/1000)+'k' : d.tokens;
    return `<div class="bar-col"><div class="bar" style="height:${pct}%"><span class="bar-val">${val}</span></div><div class="bar-label">${label}</div></div>`;
  }).join('') + '</div>';
}

function renderTokenStepChart(data) {
  const el = document.getElementById('tokenStepChart');
  if (!data.length) { el.innerHTML = '<div class="empty-state">Aucune donnée</div>'; return; }
  const maxVal = Math.max(...data.map(d => parseInt(d.tokens)));
  el.innerHTML = '<div class="bar-chart bar-chart-h">' + data.map(d => {
    const pct = maxVal > 0 ? (parseInt(d.tokens) / maxVal * 100) : 0;
    const val = parseInt(d.tokens) >= 1000 ? Math.round(parseInt(d.tokens)/1000)+'k' : d.tokens;
    const step = d.step || 'unknown';
    const icon = {cto:'🧠',architect:'🏗️',designer:'🎨',backend:'⚡',frontend:'💻',qa:'🔍',devops:'🚀'}[step] || '🤖';
    return `<div class="bar-row"><div class="bar-row-label">${icon} ${step}</div><div class="bar-row-track"><div class="bar-row-fill" style="width:${pct}%"></div></div><div class="bar-row-val">${val}</div></div>`;
  }).join('') + '</div>';
}

function renderTopProjects(projects) {
  const el = document.getElementById('dashTopProjects');
  if (!projects.length) { el.innerHTML = '<div class="empty-state">Aucun projet noté</div>'; return; }
  el.innerHTML = projects.map((p, i) => `
    <div class="item-row" onclick="showProjectDetail(${p.id})" style="cursor:pointer;">
      <div><strong>#${i+1}</strong> ${p.title}</div>
      <div><span class="pill pill-green">${p.qa_score}/100</span> <span style="font-size:.7rem;color:var(--text-3)">${p.file_count} fichiers</span></div>
    </div>
  `).join('');
}

function renderRecentProjects(projects) {
  const el = document.getElementById('dashRecentProjects');
  if (!projects.length) { el.innerHTML = '<div class="empty-state">Aucun projet</div>'; return; }
  el.innerHTML = projects.map(p => `
    <div class="item-row" onclick="showProjectDetail(${p.id})" style="cursor:pointer;">
      <div><strong>${p.title}</strong><br><span style="font-size:.7rem;color:var(--text-3);">${p.frontend} + ${p.backend}</span></div>
      <div style="display:flex;align-items:center;gap:6px;">
        <span class="pill ${p.status === 'done' ? 'pill-green' : p.status === 'failed' ? 'pill-red' : 'pill-blue'}">${p.status}</span>
        ${p.qa_score > 0 ? `<span class="pill pill-green">${p.qa_score}/100</span>` : ''}
      </div>
    </div>
  `).join('');
}

async function showProjectDetail(id) {
  const d = await api('get_project', { id: '' + id });
  const p = d.project;
  if (!p) return;
  activeProjectId = p.id;
  activeProjectFolder = p.folder;
  document.getElementById('detailTitle').textContent = p.title;
  const buildBadge = p.build_validated == 1 ? '<span class="pill pill-green">✅ Build OK</span>' : '<span class="pill pill-red">❌ Build</span>';
  document.getElementById('detailInfo').innerHTML = `
    <div class="project-info-item"><div class="label">Type</div><div class="value">${p.project_type}</div></div>
    <div class="project-info-item"><div class="label">Stack</div><div class="value"><span class="stack-tag">${p.frontend}</span> <span class="stack-tag">${p.backend}</span></div></div>
    <div class="project-info-item"><div class="label">BDD</div><div class="value">${p.database}</div></div>
    <div class="project-info-item"><div class="label">Score QA</div><div class="value">${p.qa_score || '—'}/100</div></div>
    <div class="project-info-item"><div class="label">Build</div><div class="value">${buildBadge}</div></div>
    <div class="project-info-item"><div class="label">Fichiers</div><div class="value">${p.file_count || 0}</div></div>
    <div class="project-info-item"><div class="label">Status</div><div class="value"><span class="pill ${p.status === 'done' ? 'pill-green' : p.status === 'failed' ? 'pill-red' : 'pill-blue'}">${p.status}</span></div></div>
  `;
  const logs = d.logs || [];
  const errorLogs = logs.filter(l => l.level === 'err' || l.level === 'warn');
  document.getElementById('detailLogs').innerHTML = (logs.length
    ? (errorLogs.length ? `<div style="margin-bottom:8px;padding:8px;background:rgba(239,68,68,0.08);border-radius:var(--radius-sm);border:1px solid rgba(239,68,68,0.2);"><span style="color:var(--error);font-weight:700;">⚠ ${errorLogs.length} erreur(s)</span></div>` : '')
    + logs.map(l => `<span style="color:var(--text-3)">[${l.logged_at}]</span> <span class="tag-${l.level}">${l.level}</span> ${l.message}<br>`).join('')
    : '<span style="color:var(--text-3)">Aucun log</span>');
  document.getElementById('projectDetail').style.display = 'block';
  document.getElementById('projectDetail').scrollIntoView({ behavior: 'smooth', block: 'start' });
  updatePreview(p);
}

function updatePreview(p) {
  const folder = p?.folder || activeProjectFolder;
  if (!folder) return;
  document.getElementById('previewFrame').src = folder + '/index.html';
}

function closeProjectDetail() { document.getElementById('projectDetail').style.display = 'none'; }
function openProjectFolder() { if (activeProjectFolder) window.open(activeProjectFolder + '/index.html', '_blank'); }
function downloadProjectZip() { if (activeProjectId) window.location.href = 'api.php?action=download_zip&id=' + activeProjectId; }
async function deleteProject() { if (!activeProjectId || !confirm('Supprimer ce projet définitivement ?')) return; const r = await api('delete_project', { id: activeProjectId }); if (r.success) { closeProjectDetail(); loadProjects(); loadStats(); } }
async function quickDelete(id) { if (!confirm('Supprimer ce projet définitivement ?')) return; const r = await api('delete_project', { id }); if (r.success) { loadProjects(); loadStats(); } }
function quickDownload(id) { window.location.href = 'api.php?action=download_zip&id=' + id; }

// ══════════════════════════════════════════════
// BADGES
// ══════════════════════════════════════════════
function updateBadges(data) {
  const kc = (data.keys || []).filter(k => k.is_active == 1).length;
  const tt = data.stats?.tokens_total || 0;
  document.getElementById('totalKeys').textContent = kc + ' clés';
  document.getElementById('totalTokens').textContent = (tt >= 1000 ? Math.round(tt/1000) + 'k' : tt) + ' tokens';
}

// ══════════════════════════════════════════════
// TERMINAL
// ══════════════════════════════════════════════
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

// ══════════════════════════════════════════════
// MASTER PROMPT + ADVANCED TOGGLE
// ══════════════════════════════════════════════
let advancedVisible = false;
function toggleAdvanced() {
  advancedVisible = !advancedVisible;
  document.getElementById('advancedOptions').style.display = advancedVisible ? 'block' : 'none';
}
function getStackValue(id, fallback) {
  const el = document.getElementById(id);
  return el ? el.value : fallback;
}

// ══════════════════════════════════════════════
// BUILD PIPELINE
// ══════════════════════════════════════════════
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

    terminalLog('sys', '🚀 Lancement du pipeline 7 agents en arrière-plan...');
    setProgress(2, 'Démarrage du pipeline...');

    const launchResp = await apiJSON('run_build', { project_id: proj.id });
    if (launchResp.error) { terminalLog('err', 'Erreur: ' + launchResp.error); isBuilding = false; document.getElementById('launchBtn').disabled = false; return; }
    terminalLog('ok', 'Build #' + proj.id + ' lancé en arrière-plan');

    const stepMap = { 'cto': 'step-cto', 'architect': 'step-architect', 'designer': 'step-designer', 'backend': 'step-backend', 'frontend': 'step-frontend', 'qa': 'step-qa', 'devops': 'step-devops', 'engine': 'step-cto' };

    terminalLog('sys', '📡 Connexion SSE pour logs en temps réel...');
    const evtSource = new EventSource('api.php?action=sse_stream&project_id=' + proj.id);

    evtSource.addEventListener('log', function(e) {
      const log = JSON.parse(e.data);
      terminalLog(log.level || 'info', log.message || '');
      if (stepMap[log.step]) setStep(log.step, log.level === 'ok' ? 'done' : log.level === 'err' ? 'failed' : 'active');
      const pctMatch = log.message?.match(/pct:(\d+)/);
      if (pctMatch) setProgress(parseInt(pctMatch[1]), log.label || log.message || '');
    });

    evtSource.addEventListener('status', function(e) {
      const st = JSON.parse(e.data);
      if (st.status === 'building') setProgress(Math.min(95, 3), 'Construction en cours...');
    });

    function onBuildEnd(status) {
      const isOk = status === 'done';
      terminalLog(isOk ? 'ok' : 'err', isOk ? '✅ Projet terminé !' : '❌ Échec');
      setProgress(100, isOk ? '✅ Terminé' : '❌ Échoué');
      evtSource.close();
      loadProjects(); loadStats();
      if (isOk) showProjectDetail(proj.id);
      isBuilding = false;
      document.getElementById('launchBtn').disabled = false;
    }

    evtSource.addEventListener('done', function(e) {
      const st = JSON.parse(e.data);
      onBuildEnd(st.status);
    });

    evtSource.onerror = function() {
      terminalLog('warn', '⚠ Connexion SSE perdue, fallback polling...');
      evtSource.close();
      pollFallback(proj.id, function(status) { onBuildEnd(status); });
    };
  } catch (e) {
    terminalLog('err', 'Erreur: ' + e.message);
    setStep('cto', 'failed');
    isBuilding = false;
    document.getElementById('launchBtn').disabled = false;
  }
}

// ─── SSE Fallback: polling ────────────────
async function pollFallback(projectId, callback) {
  const stepMap = { 'cto': 'step-cto', 'architect': 'step-architect', 'designer': 'step-designer', 'backend': 'step-backend', 'frontend': 'step-frontend', 'qa': 'step-qa', 'devops': 'step-devops', 'engine': 'step-cto' };
  let lastLogId = 0;
  for (let i = 0; i < 180; i++) {
    await new Promise(r => setTimeout(r, 2000));
    try {
      const status = await api('get_project', { id: '' + projectId });
      const p = status.project;
      const logData = await api('get_logs', { project_id: '' + projectId });
      const logs = logData.logs || [];
      for (const log of logs.slice(lastLogId)) {
        terminalLog(log.level || 'info', log.message || '');
        if (stepMap[log.step]) setStep(log.step, log.level === 'ok' ? 'done' : log.level === 'err' ? 'failed' : 'active');
        const pctMatch = log.message?.match(/pct:(\d+)/);
        if (pctMatch) setProgress(parseInt(pctMatch[1]), log.label || log.message || '');
      }
      lastLogId = logs.length;
      if (p?.status === 'done' || p?.status === 'failed') { if (callback) callback(p.status); return; }
      if (p?.status === 'building') setProgress(Math.min(95, 3 + Math.floor(i/2)), 'Construction en cours...');
    } catch (e) { terminalLog('warn', 'Fallback poll: ' + e.message); }
  }
  terminalLog('warn', '⚠ Le build prend plus de 6 min — vérifie le statut manuellement');
  if (callback) callback('timeout');
}

// ══════════════════════════════════════════════
// PREVIEW
// ══════════════════════════════════════════════
function changePreviewPage() {
  const sel = document.getElementById('previewPageSelect');
  const val = sel.value;
  if (val && activeProjectFolder) document.getElementById('previewFrame').src = activeProjectFolder + '/' + val;
}

// ══════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
  loadKeys();
  loadProjects();
  loadStats();
});
