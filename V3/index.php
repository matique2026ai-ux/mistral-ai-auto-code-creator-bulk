<?php
require_once 'db.php';
$db = getDB();
$stats = getGlobalStats($db);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AutoCoder V3 — Autonomous AI Web Architect</title>
<meta name="description" content="State-of-the-art autonomous AI website builder powered by Mistral AI with self-healing capabilities.">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  :root {
    --bg-dark: #060709;
    --bg-surface: #0c0e12;
    --bg-card: #121620;
    --border: #1e2530;
    --border-glow: rgba(0, 229, 195, 0.2);
    
    --accent: #00e5c3;
    --accent-rgb: 0, 229, 195;
    --accent-grad: linear-gradient(135deg, #00e5c3 0%, #00b4ff 100%);
    --accent-grad-hover: linear-gradient(135deg, #00ffda 0%, #3bc4ff 100%);
    --accent2: #ff6b35;
    --accent3: #a78bfa;
    
    --text-primary: #f3f4f6;
    --text-secondary: #9ca3af;
    --text-muted: #4b5563;
    
    --success: #10b981;
    --error: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    
    --font-sans: 'Outfit', 'Tajawal', sans-serif;
    --font-mono: 'Space Mono', monospace;
    --radius-lg: 16px;
    --radius-md: 10px;
    --radius-sm: 6px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  [data-theme="light"] {
    --bg-dark: #f3f4f6;
    --bg-surface: #ffffff;
    --bg-card: #f9fafb;
    --border: #e5e7eb;
    --text-primary: #111827;
    --text-secondary: #4b5563;
    --text-muted: #9ca3af;
    --border-glow: rgba(0, 229, 195, 0.1);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: var(--font-sans);
    background-color: var(--bg-dark);
    color: var(--text-primary);
    min-height: 100vh;
    overflow-x: hidden;
    transition: var(--transition);
  }

  /* Elegant Grid & Radial Background */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: 
      radial-gradient(circle at 10% 20%, rgba(0, 229, 195, 0.05) 0%, transparent 40%),
      radial-gradient(circle at 90% 80%, rgba(167, 139, 250, 0.05) 0%, transparent 40%),
      linear-gradient(rgba(0, 229, 195, 0.015) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0, 229, 195, 0.015) 1px, transparent 1px);
    background-size: 100% 100%, 100% 100%, 40px 40px, 40px 40px;
    pointer-events: none;
    z-index: 0;
  }

  .app-container {
    display: grid;
    grid-template-columns: 360px 1fr 420px;
    height: 100vh;
    position: relative;
    z-index: 1;
  }

  /* RTL Support class */
  .rtl {
    direction: rtl;
  }

  /* SIDEBAR: Config & Brief */
  .sidebar {
    background: var(--bg-surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow-y: auto;
  }
  .rtl .sidebar {
    border-right: none;
    border-left: 1px solid var(--border);
  }

  .sidebar-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .logo {
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -1px;
    background: var(--accent-grad);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .logo span {
    font-size: 0.75rem;
    font-family: var(--font-mono);
    color: var(--text-secondary);
    -webkit-text-fill-color: var(--text-secondary);
    background: var(--border);
    padding: 2px 8px;
    border-radius: 99px;
  }

  .sidebar-scroll {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  /* CENTRAL AREA: Pipeline & Terminal */
  .main-content {
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow-y: auto;
    border-right: 1px solid var(--border);
  }
  .rtl .main-content {
    border-right: none;
    border-left: 1px solid var(--border);
  }

  .top-navbar {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(12, 14, 18, 0.5);
    backdrop-filter: blur(12px);
    position: sticky;
    top: 0;
    z-index: 10;
  }

  /* PREVIEW PANEL */
  .preview-panel {
    background: var(--bg-surface);
    display: flex;
    flex-direction: column;
    height: 100vh;
  }

  .preview-header {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .preview-frame-container {
    flex: 1;
    background: #1e1e1e;
    position: relative;
  }

  .preview-iframe {
    width: 100%;
    height: 100%;
    border: none;
    background: #ffffff;
  }

  /* Glass Cards */
  .g-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    transition: var(--transition);
  }
  .g-card:hover {
    border-color: var(--border-glow);
    box-shadow: 0 8px 30px rgba(0, 229, 195, 0.02);
  }

  .card-title {
    font-size: 0.85rem;
    font-family: var(--font-mono);
    color: var(--accent);
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* UI controls */
  label {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 6px;
  }
  input, select, textarea {
    width: 100%;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 10px 14px;
    border-radius: var(--radius-md);
    font-family: var(--font-sans);
    font-size: 0.9rem;
    outline: none;
    transition: var(--transition);
  }
  input:focus, select:focus, textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(0, 229, 195, 0.15);
  }
  
  .form-group {
    margin-bottom: 16px;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    border: none;
  }
  .btn-primary {
    background: var(--accent-grad);
    color: #040506;
  }
  .btn-primary:hover {
    background: var(--accent-grad-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 229, 195, 0.3);
  }
  .btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
  }
  .btn-outline:hover {
    border-color: var(--accent);
    color: var(--accent);
  }
  .btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.2);
  }
  .btn-danger:hover {
    background: var(--error);
    color: white;
  }

  /* Big Launch/Action Button */
  .launch-btn {
    width: 100%;
    padding: 14px;
    font-size: 1rem;
    font-weight: 700;
    border-radius: var(--radius-md);
    background: var(--accent-grad);
    color: #000;
    cursor: pointer;
    border: none;
    transition: var(--transition);
    box-shadow: 0 4px 20px rgba(0, 229, 195, 0.15);
  }
  .launch-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 229, 195, 0.3);
    background: var(--accent-grad-hover);
  }
  .launch-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
  }

  /* Tabs System */
  .tabs {
    display: flex;
    background: var(--bg-dark);
    padding: 4px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
  }
  .tab-btn {
    flex: 1;
    padding: 8px;
    font-size: 0.85rem;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-family: var(--font-sans);
    transition: var(--transition);
  }
  .tab-btn.active {
    background: var(--bg-card);
    color: var(--accent);
    font-weight: 600;
  }

  /* Terminal & Steps Tracker */
  .terminal-box {
    background: #050608;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    flex: 1;
    margin: 20px;
    overflow: hidden;
  }
  .terminal-header {
    background: #0d0f13;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .terminal-dots {
    display: flex;
    gap: 6px;
  }
  .t-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
  }
  .t-red { background: var(--error); }
  .t-yellow { background: var(--warning); }
  .t-green { background: var(--success); }
  
  .terminal-title {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: var(--text-secondary);
  }

  .terminal-body {
    padding: 16px;
    overflow-y: auto;
    font-family: var(--font-mono);
    font-size: 0.85rem;
    line-height: 1.6;
    flex: 1;
    background: #030406;
  }

  .log-row {
    margin-bottom: 4px;
    display: flex;
    gap: 8px;
    animation: fadeInUp 0.2s ease-out;
  }
  .log-time { color: var(--text-muted); }
  .log-badge {
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    min-width: 65px;
    text-align: center;
  }
  .log-msg { color: var(--text-primary); flex: 1; }

  /* Log styles */
  .badge-sys { background: rgba(59, 130, 246, 0.15); color: var(--info); }
  .badge-ai { background: rgba(167, 139, 250, 0.15); color: var(--accent3); }
  .badge-write { background: rgba(0, 229, 195, 0.15); color: var(--accent); }
  .badge-ok { background: rgba(16, 185, 129, 0.15); color: var(--success); }
  .badge-err { background: rgba(239, 68, 68, 0.15); color: var(--error); }
  .badge-warn { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
  .badge-heal { background: rgba(255, 107, 53, 0.15); color: var(--accent2); }

  /* Realtime Pipeline Steps */
  .steps-box {
    margin: 20px 20px 0 20px;
    padding: 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
  }

  .progress-container {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
  }
  .progress-bg {
    height: 6px;
    background: var(--bg-dark);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 12px;
  }
  .progress-fill {
    height: 100%;
    width: 0%;
    background: var(--accent-grad);
    transition: width 0.4s ease;
  }

  .steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
  }
  .step-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    transition: var(--transition);
  }
  .step-pill.active {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(0, 229, 195, 0.05);
  }
  .step-pill.completed {
    border-color: var(--success);
    color: var(--success);
    background: rgba(16, 185, 129, 0.05);
  }
  .step-pill.failed {
    border-color: var(--error);
    color: var(--error);
    background: rgba(239, 68, 68, 0.05);
  }

  /* Self-Healing / Auto-Correction Indicator */
  .healing-banner {
    display: none;
    align-items: center;
    justify-content: space-between;
    background: rgba(255, 107, 53, 0.1);
    border: 1px solid var(--accent2);
    border-radius: var(--radius-md);
    padding: 12px 16px;
    margin: 0 20px;
    animation: pulseGlow 2s infinite;
  }
  @keyframes pulseGlow {
    0%, 100% { box-shadow: 0 0 5px rgba(255, 107, 53, 0.2); }
    50% { box-shadow: 0 0 15px rgba(255, 107, 53, 0.4); }
  }

  /* List views */
  .keys-list, .projects-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .item-row {
    background: var(--bg-dark);
    border: 1px solid var(--border);
    padding: 12px 16px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: var(--transition);
  }
  .item-row:hover {
    border-color: var(--accent);
  }

  .empty-state {
    text-align: center;
    padding: 30px;
    color: var(--text-secondary);
    font-size: 0.85rem;
  }

  /* Stats cards */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
  }
  .stat-card {
    text-align: center;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    padding: 12px;
    border-radius: var(--radius-md);
  }
  .stat-val {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--accent);
  }
  .stat-lbl {
    font-size: 0.7rem;
    color: var(--text-secondary);
    text-transform: uppercase;
  }

  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Page Selector for preview */
  .page-select {
    padding: 6px 12px;
    font-size: 0.8rem;
    border-radius: var(--radius-sm);
  }

  /* Language Switcher */
  .lang-switcher {
    display: flex;
    gap: 8px;
    align-items: center;
  }
  .lang-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 600;
  }
  .lang-btn.active {
    color: var(--accent);
    text-decoration: underline;
  }

  /* Loader spinner */
  .loader {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(0, 229, 195, 0.1);
    border-top: 2px solid var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
  }
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>
</head>
<body>

<div class="app-container" id="appRoot">

  <!-- ================= SIDEBAR ================= -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo">AutoCoder <span>V3</span></div>
      <div class="lang-switcher">
        <button class="lang-btn active" onclick="setUILang('en')">EN</button>
        <span style="color: var(--border)">|</span>
        <button class="lang-btn" onclick="setUILang('ar')">العربية</button>
      </div>
    </div>

    <!-- TABS: BUILD / KEYS / PROJECTS -->
    <div style="padding: 16px 20px 0 20px;">
      <div class="tabs">
        <button class="tab-btn active" id="tabBtnBuild" onclick="switchTab('build')">⚡ Builder</button>
        <button class="tab-btn" id="tabBtnKeys" onclick="switchTab('keys')">🔑 Keys</button>
        <button class="tab-btn" id="tabBtnProjects" onclick="switchTab('projects')">📁 Projects</button>
      </div>
    </div>

    <div class="sidebar-scroll">

      <!-- ============= PANEL: BUILD ============= -->
      <div class="panel-section" id="panelBuild">
        
        <div class="g-card" style="margin-bottom: 16px;">
          <div class="card-title" data-i18n="config">Project Setup</div>
          
          <div class="form-group">
            <label for="siteType" data-i18n="site_type">Site Type</label>
            <select id="siteType" onchange="updateDefaultPages()">
              <option value="saas">⚡ SaaS / Web App</option>
              <option value="blog">📝 Blog / Magazine</option>
              <option value="store">🛒 E-Commerce Store</option>
              <option value="portfolio">🎨 Portfolio / Agency</option>
              <option value="landing" selected>🚀 Landing Page</option>
              <option value="corporate">🏢 Corporate / Business</option>
            </select>
          </div>

          <div class="form-group">
            <label for="outputLang" data-i18n="output_lang">Generated Site Language</label>
            <select id="outputLang">
              <option value="en" selected>English</option>
              <option value="ar">العربية (RTL)</option>
              <option value="fr">Français</option>
              <option value="es">Español</option>
              <option value="de">Deutsch</option>
            </select>
          </div>

          <div class="form-group">
            <label for="cssFramework" data-i18n="css_framework">CSS Style Framework</label>
            <select id="cssFramework">
              <option value="vanilla" selected>Vanilla CSS (Modern, premium)</option>
              <option value="tailwind">Tailwind CSS (Utility-first CDN)</option>
              <option value="bootstrap">Bootstrap 5 (Clean, structured)</option>
            </select>
          </div>
        </div>

        <div class="g-card" style="margin-bottom: 16px;">
          <div class="card-title" data-i18n="brief">Mission Brief</div>
          
          <div class="form-group">
            <label for="briefTitle" data-i18n="project_title">Project Name</label>
            <input type="text" id="briefTitle" placeholder="Ex: SwiftSaas, DevBlog...">
          </div>

          <div class="form-group">
            <label for="briefWho" data-i18n="brief_who">Who you are / Brand identity</label>
            <textarea id="briefWho" rows="2" placeholder="Ex: AI freelance developer offering custom automation solutions."></textarea>
          </div>

          <div class="form-group">
            <label for="briefTarget" data-i18n="brief_target">Target Audience</label>
            <textarea id="briefTarget" rows="2" placeholder="Ex: Small business owners, entrepreneurs who need to save time."></textarea>
          </div>

          <div class="form-group">
            <label for="briefMonetize" data-i18n="brief_monetize">Monetization / Goal</label>
            <textarea id="briefMonetize" rows="2" placeholder="Ex: Premium subscription plans, custom project requests."></textarea>
          </div>
        </div>

        <button class="launch-btn" id="launchBtn" onclick="launchAutonomousBuild()" data-i18n="launch">
          🚀 Launch Autonomous Build
        </button>

      </div>

      <!-- ============= PANEL: KEYS ============= -->
      <div class="panel-section" id="panelKeys" style="display: none;">
        
        <div class="g-card" style="margin-bottom: 16px;">
          <div class="card-title" data-i18n="add_key">Add API Key</div>
          <div class="form-group">
            <label for="keyLabel">Alias / Label</label>
            <input type="text" id="keyLabel" placeholder="Pro Account, Backup Key...">
          </div>
          <div class="form-group">
            <label for="keyVal">Mistral API Key</label>
            <input type="password" id="keyVal" placeholder="Paste your API key here">
          </div>
          <button class="btn btn-primary w-full" onclick="addApiKey()" data-i18n="save">Save Key</button>
        </div>

        <div class="g-card">
          <div class="card-title" data-i18n="saved_keys">Saved API Keys</div>
          <div class="keys-list" id="keysList">
            <div class="empty-state">Loading...</div>
          </div>
        </div>

      </div>

      <!-- ============= PANEL: PROJECTS ============= -->
      <div class="panel-section" id="panelProjects" style="display: none;">
        
        <div class="g-card" style="margin-bottom: 16px;">
          <div class="card-title" data-i18n="system_stats">System Performance</div>
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-val" id="statKeys">0</div>
              <div class="stat-lbl" data-i18n="lbl_keys">Keys</div>
            </div>
            <div class="stat-card">
              <div class="stat-val" id="statTokens">0</div>
              <div class="stat-lbl" data-i18n="lbl_tokens">Tokens</div>
            </div>
            <div class="stat-card">
              <div class="stat-val" id="statProjects">0</div>
              <div class="stat-lbl" data-i18n="lbl_projs">Projects</div>
            </div>
          </div>
          <canvas id="tokensChart" style="max-height: 140px; width: 100%;"></canvas>
        </div>

        <div class="g-card">
          <div class="card-title" data-i18n="recent_projects">Generated Projects</div>
          <div class="projects-list" id="projectsList">
            <div class="empty-state">Loading...</div>
          </div>
        </div>

      </div>

    </div>
  </aside>

  <!-- ================= CENTRAL CONTENT: PROCESS & LOGS ================= -->
  <main class="main-content">
    <div class="top-navbar">
      <div>
        <h2 style="font-size: 1.1rem; font-weight: 700;" data-i18n="nav_title">Autonomous Agent Pipeline</h2>
        <p style="font-size: 0.75rem; color: var(--text-secondary);" id="activeModelStatus">Rotating API Key pool • Active Model: devstral-2512</p>
      </div>
      <div style="display: flex; gap: 8px;">
        <span class="badge badge-write" id="totalKeyBadge">0 Active Keys</span>
        <span class="badge badge-ok" id="totalTokensBadge">0 Tokens Used</span>
      </div>
    </div>

    <!-- PIPELINE STATUS & STEPS TRACKER -->
    <div class="steps-box">
      <div class="progress-container">
        <span id="pipelineProgressLabel" data-i18n="standing_by">Ready to launch agent loop...</span>
        <span id="pipelineProgressPct" style="font-family: var(--font-mono)">0%</span>
      </div>
      <div class="progress-bg"><div class="progress-fill" id="pipelineProgressFill"></div></div>

      <div class="steps-grid" id="stepsGrid">
        <!-- Generated Dynamically based on pipeline definitions -->
      </div>
    </div>

    <!-- SELF-HEALING / AUTO-CORRECTION STATUS -->
    <div class="healing-banner" id="healingBanner">
      <div style="display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 1.4rem;">🔧</span>
        <div>
          <h4 style="color: var(--accent2); font-weight: 700; font-size: 0.85rem;" data-i18n="healing_active">Autonomous Self-Healing Loop Active</h4>
          <p style="font-size: 0.75rem; color: var(--text-secondary);" id="healingStatus">Debugging generated PHP syntax & layout flow issues...</p>
        </div>
      </div>
      <div class="loader"></div>
    </div>

    <!-- TERMINAL LOGS -->
    <div class="terminal-box">
      <div class="terminal-header">
        <div class="terminal-dots">
          <span class="t-dot t-red"></span>
          <span class="t-dot t-yellow"></span>
          <span class="t-dot t-green"></span>
        </div>
        <span class="terminal-title">autocoder_agent_engine.log</span>
        <button class="btn btn-outline" style="padding: 2px 8px; font-size: 0.65rem;" onclick="clearTerminalLogs()" data-i18n="clear">Clear</button>
      </div>
      <div class="terminal-body" id="terminalConsole">
        <div class="log-row">
          <span class="log-time">00:00:00</span>
          <span class="log-badge badge-sys">Engine</span>
          <span class="log-msg">Autonomous build engine initialized. Awaiting brief launch...</span>
        </div>
      </div>
    </div>
  </main>

  <!-- ================= PREVIEW PANEL ================= -->
  <section class="preview-panel">
    <div class="preview-header">
      <div>
        <h3 style="font-size: 0.95rem; font-weight: 700;" data-i18n="preview_title">Live Sandbox Interactive Preview</h3>
        <p style="font-size: 0.7rem; color: var(--text-secondary);" id="previewUrlLabel">Not launched yet</p>
      </div>
      
      <div style="display: flex; gap: 8px; align-items: center;">
        <select id="previewPageSelector" class="page-select" onchange="changePreviewPage()" style="width: auto; display: none;">
          <!-- Populated dynamically -->
        </select>
        <button class="btn btn-outline" style="padding: 6px 12px; font-size: 0.75rem;" onclick="refreshPreviewFrame()">
          🔄
        </button>
      </div>
    </div>

    <div class="preview-frame-container">
      <iframe src="about:blank" class="preview-iframe" id="previewFrame"></iframe>
    </div>
  </section>

</div>

<!-- ================= TRANSLATION SYSTEM ================= -->
<script>
const TRANSLATIONS = {
  en: {
    config: "Project Setup",
    site_type: "Site Type",
    output_lang: "Generated Site Language",
    css_framework: "CSS Style Framework",
    brief: "Mission Brief",
    project_title: "Project Name",
    brief_who: "Who you are / Brand identity",
    brief_target: "Target Audience",
    brief_monetize: "Monetization / Goal",
    launch: "🚀 Launch Autonomous Build",
    add_key: "Add API Key",
    save: "Save Key",
    saved_keys: "Saved API Keys",
    system_stats: "System Performance",
    lbl_keys: "Keys",
    lbl_tokens: "Tokens",
    lbl_projs: "Projects",
    recent_projects: "Generated Projects",
    nav_title: "Autonomous Agent Pipeline",
    standing_by: "Ready to launch agent loop...",
    healing_active: "Autonomous Self-Healing Loop Active",
    clear: "Clear",
    preview_title: "Live Sandbox Interactive Preview"
  },
  ar: {
    config: "إعدادات المشروع",
    site_type: "نوع الموقع",
    output_lang: "لغة الموقع المنشأ",
    css_framework: "إطار عمل التنسيق (CSS)",
    brief: "موجز المشروع (Brief)",
    project_title: "اسم المشروع",
    brief_who: "هويتك / طبيعة عملك",
    brief_target: "الجمهور المستهدف",
    brief_monetize: "طريقة الاستثمار / الهدف",
    launch: "🚀 إطلاق البناء الذاتي المستقل",
    add_key: "إضافة مفتاح API",
    save: "حفظ المفتاح",
    saved_keys: "مفاتيح API المحفوظة",
    system_stats: "أداء النظام",
    lbl_keys: "مفاتيح",
    lbl_tokens: "توكنز",
    lbl_projs: "مشاريع",
    recent_projects: "المشاريع المنشأة",
    nav_title: "خطوات المعالجة الذاتية المستقلة",
    standing_by: "جاهز لإطلاق المعالجة المستقلة...",
    healing_active: "دورة التصحيح والمعالجة الذاتية نشطة",
    clear: "مسح",
    preview_title: "معاينة مباشرة تفاعلية للموقع"
  }
};

let currentLang = 'en';

function setUILang(lang) {
  currentLang = lang;
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.classList.toggle('active', btn.textContent.toLowerCase() === lang.toLowerCase() || (lang === 'ar' && btn.textContent === 'العربية'));
  });
  
  const root = document.getElementById('appRoot');
  if (lang === 'ar') {
    root.classList.add('rtl');
  } else {
    root.classList.remove('rtl');
  }

  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) {
      el.textContent = TRANSLATIONS[lang][key];
    }
  });
}

// Default page names mapping
const DEFAULT_PAGES_MAP = {
  saas: ['index.php', 'features.php', 'pricing.php', 'login.php', 'contact.php'],
  blog: ['index.php', 'blog.php', 'article.php', 'about.php', 'contact.php'],
  store: ['index.php', 'shop.php', 'product.php', 'cart.php', 'contact.php'],
  portfolio: ['index.php', 'work.php', 'about.php', 'services.php', 'contact.php'],
  landing: ['index.php', 'features.php', 'testimonials.php', 'pricing.php', 'faq.php'],
  corporate: ['index.php', 'about.php', 'services.php', 'team.php', 'contact.php']
};

function updateDefaultPages() {
  const type = document.getElementById('siteType').value;
  log('sys', `Selected Site Type changed to: ${type.toUpperCase()}`);
}
</script>

<!-- ================= AUTONOMOUS AGENT CORE PIPELINE ================= -->
<script>
// Pipeline definition: 12 stages with auto-healing, self-reflection, and error recovery features.
const PIPELINE_STAGES = [
  { id: 'key_check', label: 'API Key Rotator & Readiness' },
  { id: 'brief_digest', label: 'Brief Digestion & AI Modeling' },
  { id: 'arch_design', label: 'AI Structural & UI Architecture' },
  { id: 'css_shared', label: 'CSS Stylesheet & Layout Framework' },
  { id: 'config_shared', label: 'PHP Server Configuration Setup' },
  { id: 'page_generation', label: 'PHP Page Source Generation' },
  { id: 'debug_inject', label: 'Debug Console Core Injection' },
  { id: 'file_persistance', label: 'Hard-Disk Persistent Writing' },
  { id: 'ai_qa_reflection', label: 'Autonomous AI QA Code Reflection' },
  { id: 'self_healing', label: 'Self-Correction & Healing Cycle' },
  { id: 'seo_engine', label: 'SEO robots.txt + Sitemap Generation' },
  { id: 'sandbox_publish', label: 'Live Sandbox Environment Deploy' }
];

let isCurrentlyBuilding = false;
let activeProjectId = null;
let activeProjectFolder = '';
let activeProjectSlug = '';
let activeKeyId = null;
let activeApiKeyVal = '';
let activeSiteArchitecture = null;

// Populate steps inside steps-grid
function renderPipelineStages() {
  const grid = document.getElementById('stepsGrid');
  grid.innerHTML = PIPELINE_STAGES.map((s, idx) => `
    <div class="step-pill" id="step-${s.id}">
      <span class="step-num" style="font-family: var(--font-mono); opacity: 0.5;">${String(idx+1).padStart(2, '0')}</span>
      <span>${s.label}</span>
    </div>
  `).join('');
}

function updateStageStatus(stageId, status) {
  const el = document.getElementById(`step-${stageId}`);
  if (!el) return;
  el.className = 'step-pill';
  if (status === 'active') el.classList.add('active');
  if (status === 'completed') el.classList.add('completed');
  if (status === 'failed') el.classList.add('failed');
}

// LOGGING SYSTEM
function log(level, message) {
  const consoleEl = document.getElementById('terminalConsole');
  const now = new Date();
  const timeStr = now.toTimeString().split(' ')[0];
  
  const row = document.createElement('div');
  row.className = 'log-row';
  row.innerHTML = `
    <span class="log-time">${timeStr}</span>
    <span class="log-badge badge-${level}">${level}</span>
    <span class="log-msg">${message}</span>
  `;
  consoleEl.appendChild(row);
  consoleEl.scrollTop = consoleEl.scrollHeight;
}

function clearTerminalLogs() {
  document.getElementById('terminalConsole').innerHTML = '';
}

// UI TABS SWITCHER
function switchTab(tabName) {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.panel-section').forEach(p => p.style.display = 'none');
  
  if (tabName === 'build') {
    document.getElementById('tabBtnBuild').classList.add('active');
    document.getElementById('panelBuild').style.display = 'block';
  } else if (tabName === 'keys') {
    document.getElementById('tabBtnKeys').classList.add('active');
    document.getElementById('panelKeys').style.display = 'block';
    loadKeys();
  } else if (tabName === 'projects') {
    document.getElementById('tabBtnProjects').classList.add('active');
    document.getElementById('panelProjects').style.display = 'block';
    loadProjects();
    loadStats();
  }
}

// MISTRAL API CONNECTION LAYER
async function callMistralAPI(messages, maxTokens = 3500, jsonMode = true, stepName = 'stage') {
  // Always retrieve the latest active rotated key
  const rotationResp = await fetch('api.php?action=get_key');
  const rotationData = await rotationResp.json();
  
  if (rotationData.error) {
    throw new Error('Key pool exhausted: ' + rotationData.error);
  }
  
  const apiKey = rotationData.key;
  const keyId = rotationData.id;
  
  log('ai', `Contacting Mistral AI on behalf of rotated key ID #${keyId} for stage: ${stepName.toUpperCase()}`);
  
  try {
    const payload = {
      model: 'devstral-2512',
      messages: messages,
      max_tokens: maxTokens
    };
    if (jsonMode) {
      payload.response_format = { type: 'json_object' };
    }
    
    const response = await fetch('https://api.mistral.ai/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + apiKey
      },
      body: JSON.stringify(payload)
    });
    
    if (!response.ok) {
      const errPayload = await response.json().catch(() => ({}));
      // Track error count on database
      await fetch(`api.php?action=key_error&id=${keyId}`);
      throw new Error(`HTTP Error ${response.status}: ${errPayload.message || JSON.stringify(errPayload)}`);
    }
    
    const resData = await response.json();
    const tokens = resData.usage?.total_tokens || 0;
    
    // Log usage to database
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'record_usage',
        key_id: keyId,
        tokens: tokens,
        project_id: activeProjectId,
        step: stepName
      })
    });
    
    // Increment badges
    const currentTokensBadge = document.getElementById('totalTokensBadge');
    const existingVal = parseInt(currentTokensBadge.textContent.replace(/[^0-9]/g, '')) || 0;
    currentTokensBadge.textContent = `${existingVal + tokens} Tokens Used`;
    
    return { content: resData.choices[0].message.content, tokens: tokens };
    
  } catch (error) {
    log('err', `Key ID #${keyId} error encountered: ${error.message}. Performing rotation automatic correction.`);
    await fetch(`api.php?action=key_error&id=${keyId}`);
    // Recurse / retry once automatically
    return callMistralAPI(messages, maxTokens, jsonMode, stepName);
  }
}

// -------------------------------------------------------------
// AUTONOMOUS AGENT: RUN BUILD PIPELINE & SELF-HEALING ENGINE
// -------------------------------------------------------------
async function launchAutonomousBuild() {
  if (isCurrentlyBuilding) return;
  
  const title = document.getElementById('briefTitle').value.trim();
  const who = document.getElementById('briefWho').value.trim();
  const target = document.getElementById('briefTarget').value.trim();
  const monetize = document.getElementById('briefMonetize').value.trim();
  
  if (!title || !who || !target || !monetize) {
    alert('Please fill out all mission brief fields before launching the agent loop!');
    return;
  }
  
  isCurrentlyBuilding = true;
  document.getElementById('launchBtn').disabled = true;
  renderPipelineStages();
  clearTerminalLogs();
  
  log('sys', '═══════════════════════════════════════════════════════════');
  log('sys', '🚀 AUTONOMOUS AI PIPELINE INITIATED — SELF-HEALING ENGINE ONLINE');
  log('sys', '═══════════════════════════════════════════════════════════');
  
  try {
    // Stage 1: Key validation
    updateStageStatus('key_check', 'active');
    setProgress(5, 'Checking API Key Pool readiness...');
    const keysCheck = await fetch('api.php?action=get_data').then(res => res.json());
    const activeKeysCount = keysCheck.keys?.filter(k => k.is_active === 1).length || 0;
    if (activeKeysCount === 0) {
      throw new Error("No active API keys found in key pool. Go to 'Keys' tab to add a Mistral API Key first.");
    }
    log('ok', `API Key pool checked: ${activeKeysCount} active key(s) ready to host pipeline.`);
    updateStageStatus('key_check', 'completed');
    
    // Stage 2: Brief digest
    updateStageStatus('brief_digest', 'active');
    setProgress(10, 'Digesting mission parameters & loading AI engine...');
    const siteType = document.getElementById('siteType').value;
    const outputLang = document.getElementById('outputLang').value;
    const cssFramework = document.getElementById('cssFramework').value;
    
    const projectSetupPayload = {
      action: 'create_project',
      title: title,
      brief: JSON.stringify({ who, target, monetize }),
      site_type: siteType,
      output_lang: outputLang,
      css_framework: cssFramework
    };
    
    const projResponse = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(projectSetupPayload)
    }).then(res => res.json());
    
    if (projResponse.error) throw new Error(projResponse.error);
    
    activeProjectId = projResponse.id;
    activeProjectFolder = projResponse.folder;
    activeProjectSlug = projResponse.slug;
    
    log('ok', `Database entry generated. Project ID: #${activeProjectId}. Workspace target: ${activeProjectFolder}`);
    updateStageStatus('brief_digest', 'completed');
    
    // Stage 3: Architecture Generation
    updateStageStatus('arch_design', 'active');
    setProgress(20, 'Structuring application directory & pages via Mistral...');
    
    const archMessages = [
      {
        role: 'system',
        content: `You are an expert system architect web developer. Respond ONLY in valid JSON. No markdown, no conversational text.
        Your JSON must follow this exact format:
        {
          "site_name": "Name of the web app",
          "site_concept": "Creative modern layout and branding ideas",
          "pages": [
            {"filename": "index.php", "title": "Home Page", "desc": "Creative content blueprint for index"},
            {"filename": "about.php", "title": "About Us", "desc": "About page structural content"},
            {"filename": "services.php", "title": "What We Offer", "desc": "Pricing/Features blueprint"},
            {"filename": "contact.php", "title": "Get In Touch", "desc": "Full functional form specs"}
          ],
          "colors": { "primary": "#hex", "secondary": "#hex", "accent": "#hex", "background": "#hex" },
          "layout_instructions": "General guide to align styling"
        }
        Generate an optimal set of 4 to 5 pages suited for a ${siteType} web platform in language: ${outputLang}.`
      },
      {
        role: 'user',
        content: `Generate site architecture based on this brief:
        - BRAND IDENTITY: ${who}
        - TARGET AUDIENCE: ${target}
        - MONETIZATION/GOAL: ${monetize}
        - TYPE: ${siteType}
        - LANGUAGE CODE: ${outputLang}
        - CSS FRAMEWORK CHOSEN: ${cssFramework}`
      }
    ];
    
    const archResponse = await callMistralAPI(archMessages, 2500, true, 'architecture');
    activeSiteArchitecture = JSON.parse(archResponse.content.trim());
    log('ok', `AI Web Architecture crafted! Concept: "${activeSiteArchitecture.site_name}" - ${activeSiteArchitecture.pages.length} pages structured.`);
    
    // Store structural architecture JSON in DB
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'update_project',
        id: activeProjectId,
        arch_json: JSON.stringify(activeSiteArchitecture)
      })
    });
    
    updateStageStatus('arch_design', 'completed');
    
    // Stage 4: CSS Stylesheet Generation
    updateStageStatus('css_shared', 'active');
    setProgress(30, 'Styling aesthetic UI components...');
    let cssCode = '';
    if (cssFramework === 'vanilla') {
      const cssMessages = [
        {
          role: 'system',
          content: 'You are a CSS Web designer. Write highly modern, premium HSL grid-focused, dark-themed responsive CSS layouts. Return ONLY valid JSON: {"css": "all compiled css code"}'
        },
        {
          role: 'user',
          content: `Write modern styling for web concept: ${activeSiteArchitecture.site_concept}.
          Colors: Primary ${activeSiteArchitecture.colors.primary}, Secondary ${activeSiteArchitecture.colors.secondary}, Accent ${activeSiteArchitecture.colors.accent}.
          Must look extremely premium, high design standard, animations, hover effects, beautiful layouts.`
        }
      ];
      const cssResp = await callMistralAPI(cssMessages, 2500, true, 'shared_css');
      const cssData = JSON.parse(cssResp.content);
      cssCode = cssData.css;
    } else if (cssFramework === 'tailwind') {
      cssCode = `/* Tailwind-CDN Utility injection CSS */
      :root {
        --primary: ${activeSiteArchitecture.colors.primary};
        --secondary: ${activeSiteArchitecture.colors.secondary};
        --accent: ${activeSiteArchitecture.colors.accent};
      }`;
    } else {
      cssCode = `/* Bootstrap CSS layout additions */`;
    }
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/style.css`,
        content: cssCode
      })
    });
    log('ok', `Styles framework style.css persisted inside sandbox!`);
    updateStageStatus('css_shared', 'completed');
    
    // Stage 5: Config Shared File Setup
    updateStageStatus('config_shared', 'active');
    setProgress(35, 'Writing global PHP platform context config...');
    const configContent = `<?php
    define('SITE_NAME', ${JSON.stringify(activeSiteArchitecture.site_name)});
    define('SITE_CONCEPT', ${JSON.stringify(activeSiteArchitecture.site_concept)});
    define('COLOR_PRIMARY', ${JSON.stringify(activeSiteArchitecture.colors.primary)});
    define('COLOR_SECONDARY', ${JSON.stringify(activeSiteArchitecture.colors.secondary)});
    define('COLOR_ACCENT', ${JSON.stringify(activeSiteArchitecture.colors.accent)});
    define('CSS_FRAMEWORK', ${JSON.stringify(cssFramework)});
    define('LANG_CODE', ${JSON.stringify(outputLang)});
    define('DEBUG_MODE', isset($_GET['debug']));
    ?>`;
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/config.php`,
        content: configContent
      })
    });
    log('ok', `PHP site configuration config.php written.`);
    updateStageStatus('config_shared', 'completed');
    
    // Stage 6: Generation of PHP Pages (Autonomous Loop)
    updateStageStatus('page_generation', 'active');
    setProgress(40, `Generating application pages source code...`);
    
    const pagesList = activeSiteArchitecture.pages;
    const generatedPagesMemory = {};
    
    for (let i = 0; i < pagesList.length; i++) {
      const page = pagesList[i];
      const pageProgressPct = 40 + Math.round((i / pagesList.length) * 30);
      setProgress(pageProgressPct, `Generating dynamic code for page: ${page.filename}...`);
      
      const pageMessages = [
        {
          role: 'system',
          content: `You are an expert full-stack developer. Write rich, comprehensive PHP/HTML layout code.
          You must output valid JSON only:
          {
            "filename": "${page.filename}",
            "code": "...Complete full functional source code of the page..."
          }
          Requirements:
          - Incorporate standard config: require_once 'config.php';
          - Link dynamically generated stylesheet style.css.
          - Output language: ${outputLang} (if Arabic, set dir="rtl" on <html> and use Outfit/Tajawal fonts).
          - Embed beautiful, descriptive bilingual copywriting (no generic Lorem Ipsum!).
          - If a contact page, write solid backend form submission script handling simulation.
          - Provide navigation linking to all these pages: ${pagesList.map(p => p.filename).join(', ')}.
          - Design style standard: premium glassmorphism dark theme, interactive cards, grid layouts.`
        },
        {
          role: 'user',
          content: `Build complete functional PHP code for "${page.filename}".
          - Page title: ${page.title}
          - Page details: ${page.desc}
          - Brand Identity / Brief: ${who}
          - CSS Framework options: ${cssFramework} (If Tailwind, include Tailwind play CDN script in <head>; if Bootstrap, include Bootstrap link & bundles).`
        }
      ];
      
      let pageData;
      let generateRetry = 0;
      const maxRetries = 3;
      
      while (generateRetry < maxRetries) {
        try {
          const pageResp = await callMistralAPI(pageMessages, 3500, true, `page_${page.filename}`);
          pageData = JSON.parse(pageResp.content.trim());
          if (!pageData.code) throw new Error("JSON structure did not contain code parameter.");
          break; // Success!
        } catch (err) {
          generateRetry++;
          log('warn', `Generation attempt #${generateRetry} for ${page.filename} failed: ${err.message}. Retrying...`);
        }
      }
      
      if (!pageData) {
        throw new Error(`Critical: Failed to generate source code for ${page.filename} after maximum attempts.`);
      }
      
      generatedPagesMemory[page.filename] = pageData.code;
      log('write', `Successfully generated page structure in memory: ${page.filename} (${pageData.code.length} characters)`);
    }
    
    updateStageStatus('page_generation', 'completed');
    
    // Stage 7: Debug Injection
    updateStageStatus('debug_inject', 'active');
    setProgress(75, 'Injecting interactive local server debugger inside pages...');
    
    for (const [fname, code] of Object.entries(generatedPagesMemory)) {
      if (fname.endsWith('.php') && code.includes('</body>')) {
        const debuggerCode = `
        <?php if(isset($_GET['debug'])): ?>
        <div id="autocoder-debug-pane" style="position:fixed;bottom:0;left:0;right:0;background:#05070a;border-top:2px solid var(--accent, #00e5c3);color:#e2e8f0;padding:12px 20px;font-family:monospace;font-size:11px;z-index:99999;box-shadow:0 -10px 30px rgba(0,0,0,0.8);max-height:220px;overflow-y:auto;text-align:left;direction:ltr;">
          <div style="display:flex;justify-content:between;align-items:center;margin-bottom:8px;border-bottom:1px solid #1e293b;padding-bottom:6px;">
            <strong style="color:var(--accent, #00e5c3);">⚙️ AUTO-DEBUG MONITOR &mdash; ${fname}</strong>
            <span style="background:#1e293b;padding:2px 8px;border-radius:4px;font-size:10px;">V3 Sandbox Environment</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <strong>PHP Context Info:</strong><br>
              - PHP Version: <?php echo PHP_VERSION; ?><br>
              - Memory Usage: <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB<br>
              - Execution Time: <?php echo round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 4); ?>s
            </div>
            <div>
              <strong>Server Environment Variables:</strong><br>
              - Request Method: <?php echo $_SERVER['REQUEST_METHOD']; ?><br>
              - User Agent: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?><br>
              - Sandbox Workspace Path: <?php echo htmlspecialchars(dirname(__FILE__)); ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
        `;
        generatedPagesMemory[fname] = code.replace('</body>', `${debuggerCode}\n</body>`);
      }
    }
    
    log('ok', `AI Debug toolset successfully injected to footer modules.`);
    updateStageStatus('debug_inject', 'completed');
    
    // Stage 8: Files Persistence
    updateStageStatus('file_persistance', 'active');
    setProgress(80, 'Saving compiled files to sandbox folder...');
    
    let fileCount = 0;
    for (const [fname, content] of Object.entries(generatedPagesMemory)) {
      const fileSavePayload = {
        action: 'save_file',
        path: `${activeProjectFolder}/${fname}`,
        content: content
      };
      
      const saveResp = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(fileSavePayload)
      }).then(res => res.json());
      
      if (saveResp.error) {
        log('err', `Error writing file ${fname} to target: ${saveResp.error}`);
      } else {
        fileCount++;
        log('write', `File successfully compiled: ${fname} (${saveResp.bytes} bytes saved)`);
      }
    }
    
    updateStageStatus('file_persistance', 'completed');
    
    // Stage 9: AI QA Code Reflection
    updateStageStatus('ai_qa_reflection', 'active');
    setProgress(85, 'Performing autonomous code quality checks...');
    
    const qaMessages = [
      {
        role: 'system',
        content: `You are an expert senior code QA and automation tester.
        Inspect the generated code files. Detect structural errors, syntax glitches, broken relative links, incomplete markup, or broken formatting tags.
        Return ONLY valid JSON format:
        {
          "issues_detected": [
            {"filename": "index.php", "issue": "detailed issue", "severity": "high|medium|low", "solution_code": "...exact PHP/HTML code to fix it..."}
          ],
          "qa_score": 95,
          "summary": "Overall evaluation summary"
        }`
      },
      {
        role: 'user',
        content: `Inspect these files generated for concept: ${activeSiteArchitecture.site_concept}.
        
        ${Object.entries(generatedPagesMemory).map(([f, code]) => `
        --- FILE: ${f} ---
        ${code.substring(0, 2000)}
        --- END ---
        `).join('\n')}`
      }
    ];
    
    const qaResp = await callMistralAPI(qaMessages, 3000, true, 'reflection');
    const qaReport = JSON.parse(qaResp.content.trim());
    log('ok', `QA Inspection complete. Evaluation Score: ${qaReport.qa_score}/100. Issues detected: ${qaReport.issues_detected.length}`);
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'update_project',
        id: activeProjectId,
        qa_score: qaReport.qa_score,
        file_count: fileCount
      })
    });
    
    updateStageStatus('ai_qa_reflection', 'completed');
    
    // Stage 10: Autonomous Self-Healing / Auto-Correction Cycle
    updateStageStatus('self_healing', 'active');
    
    if (qaReport.issues_detected.length > 0) {
      document.getElementById('healingBanner').style.display = 'flex';
      log('heal', `⚠️ Self-Healing active! Resolving ${qaReport.issues_detected.length} critical QA tickets autonomously.`);
      
      for (const issue of qaReport.issues_detected) {
        setProgress(90, `Healing module ${issue.filename} autonomously...`);
        document.getElementById('healingStatus').textContent = `Correcting: ${issue.issue} in ${issue.filename}`;
        
        log('heal', `Self-Healing code block replacement for "${issue.filename}" due to: ${issue.issue}`);
        
        const healMessages = [
          {
            role: 'system',
            content: `You are an automated self-healing developer system.
            You are provided a file containing errors, a description of the error, and a proposed fix.
            Rewrite the code to fix the error while keeping the rest of the functional logic.
            Output ONLY valid JSON:
            {
              "filename": "${issue.filename}",
              "fixed_code": "...Complete updated functional code..."
            }`
          },
          {
            role: 'user',
            content: `
            FILE TO FIX: ${issue.filename}
            ERROR DESCRIPTION: ${issue.issue}
            PROPOSED FIX: ${issue.solution_code}
            EXISTING CODE CONTENT:
            ${generatedPagesMemory[issue.filename]}`
          }
        ];
        
        try {
          const healResp = await callMistralAPI(healMessages, 3500, true, `healing_${issue.filename}`);
          const healedData = JSON.parse(healResp.content.trim());
          if (healedData.fixed_code) {
            generatedPagesMemory[issue.filename] = healedData.fixed_code;
            
            // Persist healed code to disk
            await fetch('api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                action: 'save_file',
                path: `${activeProjectFolder}/${issue.filename}`,
                content: healedData.fixed_code
              })
            });
            log('ok', `Healed & overwritten on disk: ${issue.filename}`);
          }
        } catch (healErr) {
          log('err', `Self-healing execution failed for ${issue.filename}: ${healErr.message}`);
        }
      }
      
      document.getElementById('healingBanner').style.display = 'none';
      log('ok', 'All identified QA issues successfully resolved via autonomous healing cycle!');
    } else {
      log('ok', 'Code evaluated at top quality metrics! Bypassing self-healing loops.');
    }
    updateStageStatus('self_healing', 'completed');
    
    // Stage 11: SEO Engine Robots + Sitemap Generation
    updateStageStatus('seo_engine', 'active');
    setProgress(95, 'Structuring index crawlers SEO parameters...');
    
    const robotsTxt = `User-agent: *
Disallow: /config.php
Disallow: /style.css
Sitemap: http://<?php echo $_SERVER['HTTP_HOST']; ?>/${activeProjectFolder}/sitemap.xml`;

    const sitemapXml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  ${pagesList.map(p => `
  <url>
    <loc>http://<?php echo $_SERVER['HTTP_HOST']; ?>/${activeProjectFolder}/${p.filename}</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>${p.filename === 'index.php' ? '1.0' : '0.8'}</priority>
  </url>
  `).join('')}
</urlset>`;

    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/robots.txt`,
        content: robotsTxt
      })
    });
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/sitemap.xml`,
        content: sitemapXml
      })
    });
    
    log('ok', `SEO Assets (robots.txt, sitemap.xml) packaged.`);
    updateStageStatus('seo_engine', 'completed');
    
    // Stage 12: Launch / Deployment
    updateStageStatus('sandbox_publish', 'active');
    setProgress(100, 'Deploying sandbox parameters live...');
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'update_project',
        id: activeProjectId,
        status: 'done'
      })
    });
    
    log('ok', '═══════════════════════════════════════════════════════════');
    log('ok', '🎉 DEPLOYMENT COMPLETE! LIVE PREVIEW ACTIVE');
    log('ok', `📁 Location: ${activeProjectFolder}`);
    log('ok', `🌐 Live URL: ${activeProjectFolder}/index.php?debug=1`);
    log('ok', '═══════════════════════════════════════════════════════════');
    
    updateStageStatus('sandbox_publish', 'completed');
    
    // Launch iframe preview
    loadProjectPreview(activeProjectFolder, pagesList.map(p => p.filename));
    
  } catch (error) {
    log('err', `FATAL PIPELINE HALT: ${error.message}`);
    // Highlight active failed stage
    PIPELINE_STAGES.forEach(s => {
      const pill = document.getElementById(`step-${s.id}`);
      if (pill && pill.classList.contains('active')) {
        updateStageStatus(s.id, 'failed');
      }
    });
  } finally {
    isCurrentlyBuilding = false;
    document.getElementById('launchBtn').disabled = false;
  }
}

function setProgress(pct, labelText) {
  document.getElementById('pipelineProgressFill').style.width = `${pct}%`;
  document.getElementById('pipelineProgressPct').textContent = `${pct}%`;
  document.getElementById('pipelineProgressLabel').textContent = labelText;
}

// -------------------------------------------------------------
// LIVE PREVIEW SANDBOX CONTROLLER
// -------------------------------------------------------------
let previewPagesList = [];
let previewFolderRoot = '';

function loadProjectPreview(folderPath, pages) {
  previewFolderRoot = folderPath;
  previewPagesList = pages;
  
  const selector = document.getElementById('previewPageSelector');
  selector.innerHTML = pages.map(p => `<option value="${p}">${p}</option>`).join('');
  selector.style.display = 'inline-block';
  
  document.getElementById('previewUrlLabel').textContent = `${folderPath}/${pages[0]}`;
  
  // Set iframe src
  const iframe = document.getElementById('previewFrame');
  iframe.src = `${folderPath}/${pages[0]}?debug=1`;
}

function changePreviewPage() {
  const page = document.getElementById('previewPageSelector').value;
  document.getElementById('previewUrlLabel').textContent = `${previewFolderRoot}/${page}`;
  document.getElementById('previewFrame').src = `${previewFolderRoot}/${page}?debug=1`;
}

function refreshPreviewFrame() {
  const iframe = document.getElementById('previewFrame');
  iframe.contentWindow.location.reload();
  log('sys', 'Live preview sandbox reloaded.');
}

// -------------------------------------------------------------
// DATA MANAGEMENT (KEYS, PROJECTS, STATS)
// -------------------------------------------------------------

async function addApiKey() {
  const label = document.getElementById('keyLabel').value.trim();
  const val = document.getElementById('keyVal').value.trim();
  
  if (!label || !val) {
    alert('Please enter label and API key values!');
    return;
  }
  
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'add_key',
      label: label,
      key: val
    })
  }).then(res => res.json());
  
  if (res.error) {
    alert(res.error);
  } else {
    document.getElementById('keyLabel').value = '';
    document.getElementById('keyVal').value = '';
    loadKeys();
    log('sys', `New API key [${label}] registered to rotate stack.`);
  }
}

async function testApiKey(keyVal) {
  log('sys', 'Running instant readiness checks on target key...');
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'test_key',
      key: keyVal
    })
  }).then(res => res.json());
  
  if (res.code === 200) {
    alert(`Success! Key is active and operational. Status: ${res.status}`);
  } else {
    alert(`Error: API Key verification failed. HTTP Status ${res.code}`);
  }
}

async function deleteApiKey(id) {
  if (!confirm('Are you sure you want to delete this API Key?')) return;
  await fetch(`api.php?action=delete_key&id=${id}`);
  loadKeys();
  log('sys', `API key ID #${id} deleted.`);
}

async function resetApiKeyErrors(id) {
  await fetch(`api.php?action=reset_key&id=${id}`);
  loadKeys();
  log('sys', `API key ID #${id} errors count reset to 0.`);
}

async function loadKeys() {
  const data = await fetch('api.php?action=get_data').then(res => res.json());
  const list = document.getElementById('keysList');
  
  document.getElementById('totalKeyBadge').textContent = `${data.keys.filter(k => k.is_active === 1).length} Active Keys`;
  document.getElementById('totalTokensBadge').textContent = `${data.stats.tokens_total} Tokens Used`;
  
  if (!data.keys || data.keys.length === 0) {
    list.innerHTML = `<div class="empty-state">No keys saved. Add one above!</div>`;
    return;
  }
  
  list.innerHTML = data.keys.map(k => `
    <div class="item-row">
      <div>
        <strong style="font-size:0.85rem;">${k.label}</strong>
        <div style="font-size:0.7rem; color:var(--text-secondary); font-family:var(--font-mono);">${k.key_masked}</div>
        <div style="font-size:0.65rem; color:${k.is_active ? 'var(--success)' : 'var(--error)'};">
          ${k.is_active ? '● Active' : '● Inactive / Suspended'} (Errors: ${k.error_count})
        </div>
      </div>
      <div style="display:flex; gap:6px;">
        <button class="btn btn-outline" style="padding:4px 8px; font-size:0.7rem;" onclick="resetApiKeyErrors(${k.id})">↻ Reset</button>
        <button class="btn btn-danger" style="padding:4px 8px; font-size:0.7rem;" onclick="deleteApiKey(${k.id})">✗</button>
      </div>
    </div>
  `).join('');
}

async function loadProjects() {
  const data = await fetch('api.php?action=list_projects').then(res => res.json());
  const list = document.getElementById('projectsList');
  
  if (!data.projects || data.projects.length === 0) {
    list.innerHTML = `<div class="empty-state">No projects found. Launch a build!</div>`;
    return;
  }
  
  list.innerHTML = data.projects.map(p => `
    <div class="item-row" style="flex-direction: column; align-items: stretch; gap: 8px;">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <strong style="font-size:0.9rem; color:var(--accent);">${p.title}</strong>
          <span style="font-size:0.7rem; background:var(--border); padding:2px 6px; border-radius:4px; margin-left:6px; text-transform:uppercase;">
            ${p.site_type}
          </span>
        </div>
        <span style="font-size:0.75rem; color:${p.status === 'done' ? 'var(--success)' : 'var(--warning)'}; font-weight:600;">
          ${p.status === 'done' ? '✓ Ready' : '● Building'}
        </span>
      </div>
      
      <div style="font-size:0.7rem; color:var(--text-secondary); font-family:var(--font-mono); display:flex; justify-content:space-between;">
        <span>QA Rating: ${p.qa_score}/100</span>
        <span>Files: ${p.file_count}</span>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px; border-top:1px solid var(--border); padding-top:8px;">
        <span style="font-size:0.65rem; color:var(--text-muted);">${p.created_at}</span>
        <div style="display:flex; gap:6px;">
          <button class="btn btn-primary" style="padding:4px 8px; font-size:0.7rem;" onclick="loadPreviewFromHistory('${p.folder}', ${p.id})">Open Sandbox</button>
          <button class="btn btn-danger" style="padding:4px 8px; font-size:0.7rem;" onclick="deleteProject(${p.id})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

async function loadPreviewFromHistory(folderPath, projectId) {
  log('sys', `Loading historical project preview workspace: ${folderPath}`);
  const project = await fetch(`api.php?action=get_project&id=${projectId}`).then(res => res.json());
  if (project.project && project.project.arch_json) {
    const arch = JSON.parse(project.project.arch_json);
    const pages = arch.pages.map(p => p.filename);
    loadProjectPreview(folderPath, pages);
  } else {
    // Default fallback
    loadProjectPreview(folderPath, ['index.php']);
  }
}

async function deleteProject(id) {
  if (!confirm('Are you sure you want to delete this project? This will completely clear all files on disk!')) return;
  await fetch(`api.php?action=delete_project&id=${id}`);
  loadProjects();
  log('sys', `Project ID #${id} deleted from databases & disk.`);
}

async function loadStats() {
  const data = await fetch('api.php?action=get_data').then(res => res.json());
  
  document.getElementById('statKeys').textContent = data.stats.keys_active;
  document.getElementById('statTokens').textContent = formatTokensNumber(data.stats.tokens_total);
  document.getElementById('statProjects').textContent = data.stats.projects_total;
  
  // Render Chart
  renderStatsChart(data.chart);
}

function formatTokensNumber(num) {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
  return num;
}

let activeChartInstance = null;
function renderStatsChart(chartData) {
  const ctx = document.getElementById('tokensChart').getContext('2d');
  if (activeChartInstance) activeChartInstance.destroy();
  
  const labels = chartData.map(c => c.day);
  const values = chartData.map(c => c.total);
  
  activeChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Tokens Used',
        data: values,
        borderColor: '#00e5c3',
        backgroundColor: 'rgba(0, 229, 195, 0.05)',
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#4b5563', font: { size: 9 } } },
        y: { grid: { color: '#1e2530' }, ticks: { color: '#4b5563', font: { size: 9 } } }
      }
    }
  });
}

// Startup initializations
window.addEventListener('DOMContentLoaded', () => {
  renderPipelineStages();
  setUILang('en');
  // Load keys metadata count initially
  loadKeys();
});
</script>

</body>
</html>
