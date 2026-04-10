<?php
require_once 'db.php';

// Initialisation session
session_start();
$currentUser = null;
$token = $_SESSION['user_token'] ?? null;

if ($token) {
    $db = getDB();
    $currentUser = getUserByToken($db, $token);
}

// Déconnexion
if (isset($_GET['logout'])) {
    if ($token) {
        $db = getDB();
        $db->prepare("DELETE FROM user_sessions WHERE session_token = ?")->execute([$token]);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marketplace AI — Trading Virtuel</title>
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
  body {
    font-family: var(--sans);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
  }
  
  /* HEADER */
  header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .logo { font-size: 1.4rem; font-weight: 800; color: var(--accent); }
  .logo span { color: var(--text); font-size: 0.9rem; font-weight: 400; }
  
  .nav-links { display: flex; gap: 20px; align-items: center; }
  .nav-links a { 
    color: var(--muted); 
    text-decoration: none; 
    font-family: var(--mono); 
    font-size: 0.8rem;
    transition: color 0.2s;
  }
  .nav-links a:hover, .nav-links a.active { color: var(--accent); }
  
  .user-info { display: flex; align-items: center; gap: 12px; }
  .wallet-badge {
    background: rgba(0,229,195,0.1);
    border: 1px solid var(--accent);
    color: var(--accent);
    padding: 4px 12px;
    border-radius: 6px;
    font-family: var(--mono);
    font-size: 0.85rem;
  }
  .btn-logout {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--mono);
    font-size: 0.75rem;
  }
  .btn-logout:hover { border-color: var(--err); color: var(--err); }
  
  /* MAIN LAYOUT */
  .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
  
  /* STATS BAR */
  .stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }
  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
  }
  .stat-val { font-size: 1.8rem; font-weight: 800; color: var(--accent); font-family: var(--mono); }
  .stat-label { font-size: 0.7rem; color: var(--muted); font-family: var(--mono); margin-top: 4px; text-transform: uppercase; }
  .stat-change { font-size: 0.75rem; margin-top: 8px; }
  .stat-change.up { color: var(--ok); }
  .stat-change.down { color: var(--err); }
  
  /* TABS */
  .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px; }
  .tab-btn {
    padding: 10px 20px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: 8px;
    cursor: pointer;
    font-family: var(--mono);
    font-size: 0.8rem;
    transition: all 0.2s;
  }
  .tab-btn:hover { border-color: var(--accent); color: var(--text); }
  .tab-btn.active { background: var(--accent); color: #000; border-color: var(--accent); }
  
  .tab-content { display: none; }
  .tab-content.active { display: block; }
  
  /* PRODUCTS GRID */
  .products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
  }
  .product-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.2s, border-color 0.2s;
  }
  .product-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
  }
  .product-img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    background: var(--surface);
  }
  .product-body { padding: 16px; }
  .product-name { font-size: 1rem; font-weight: 700; margin-bottom: 6px; }
  .product-desc { font-size: 0.8rem; color: var(--muted); margin-bottom: 12px; line-height: 1.5; }
  .product-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .product-price { font-size: 1.3rem; font-weight: 800; color: var(--accent); font-family: var(--mono); }
  .product-trend { 
    font-size: 0.7rem; 
    padding: 3px 8px; 
    border-radius: 4px; 
    font-family: var(--mono);
  }
  .trend-up { background: rgba(34,197,94,0.15); color: var(--ok); }
  .trend-down { background: rgba(239,68,68,0.15); color: var(--err); }
  .trend-stable { background: rgba(107,114,128,0.15); color: var(--muted); }
  
  .btn-buy {
    width: 100%;
    padding: 12px;
    background: var(--accent);
    color: #000;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-family: var(--mono);
    cursor: pointer;
    transition: all 0.2s;
  }
  .btn-buy:hover { background: #00ffda; }
  .btn-buy:disabled { opacity: 0.5; cursor: not-allowed; }
  
  /* PORTFOLIO TABLE */
  .portfolio-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--mono);
    font-size: 0.85rem;
  }
  .portfolio-table th {
    text-align: left;
    padding: 12px;
    color: var(--muted);
    font-weight: 400;
    border-bottom: 1px solid var(--border);
    font-size: 0.72rem;
    text-transform: uppercase;
  }
  .portfolio-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
  }
  .portfolio-table tr:hover td { background: rgba(255,255,255,0.02); }
  
  .gain-pos { color: var(--ok); }
  .gain-neg { color: var(--err); }
  
  .btn-sell {
    padding: 6px 14px;
    background: transparent;
    border: 1px solid var(--accent2);
    color: var(--accent2);
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--mono);
    font-size: 0.75rem;
  }
  .btn-sell:hover { background: var(--accent2); color: #000; }
  
  /* GROUPS SECTION */
  .groups-list { display: flex; flex-direction: column; gap: 16px; }
  .group-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .group-info h4 { font-size: 1rem; margin-bottom: 6px; }
  .group-info p { font-size: 0.8rem; color: var(--muted); margin-bottom: 8px; }
  .group-stats { display: flex; gap: 16px; font-family: var(--mono); font-size: 0.75rem; color: var(--muted); }
  
  /* STRATEGY PANEL */
  .strategy-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 24px;
    max-width: 600px;
  }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-family: var(--mono); font-size: 0.75rem; color: var(--muted); margin-bottom: 8px; }
  .form-group input, .form-group select {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 10px 14px;
    border-radius: 6px;
    font-family: var(--mono);
    font-size: 0.85rem;
  }
  .form-group input:focus { border-color: var(--accent); outline: none; }
  
  .btn-primary {
    background: var(--accent);
    color: #000;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 700;
    font-family: var(--mono);
    cursor: pointer;
  }
  .btn-primary:hover { background: #00ffda; }
  
  /* AUTH FORMS */
  .auth-container {
    max-width: 400px;
    margin: 80px auto;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px;
  }
  .auth-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 24px; text-align: center; }
  .auth-toggle {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
  }
  .auth-toggle button {
    flex: 1;
    padding: 10px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--mono);
  }
  .auth-toggle button.active { background: var(--accent); color: #000; border-color: var(--accent); }
  
  /* LOADING */
  .loading { text-align: center; padding: 40px; color: var(--muted); font-family: var(--mono); }
  .spinner {
    display: inline-block;
    width: 40px;
    height: 40px;
    border: 3px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  
  /* NOTIFICATIONS */
  .toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px 24px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    z-index: 1000;
    animation: slideIn 0.3s ease;
  }
  .toast.success { border-color: var(--ok); }
  .toast.error { border-color: var(--err); }
  @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: none; opacity: 1; } }
  
  /* RESPONSIVE */
  @media (max-width: 768px) {
    header { flex-wrap: wrap; gap: 12px; }
    .nav-links { order: 3; width: 100%; justify-content: center; padding-top: 12px; }
    .products-grid { grid-template-columns: 1fr; }
    .group-card { flex-direction: column; align-items: flex-start; gap: 16px; }
  }
</style>
</head>
<body>

<?php if (!$currentUser): ?>
<!-- AUTH PAGE -->
<div class="auth-container">
  <h1 class="auth-title">🎮 Marketplace AI</h1>
  <div class="auth-toggle">
    <button id="loginTab" class="active" onclick="showAuth('login')">Connexion</button>
    <button id="registerTab" onclick="showAuth('register')">Inscription</button>
  </div>
  
  <form id="loginForm" onsubmit="handleLogin(event)">
    <div class="form-group">
      <label>Nom d'utilisateur</label>
      <input type="text" name="username" required placeholder="Votre pseudo">
    </div>
    <div class="form-group">
      <label>Mot de passe</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn-primary" style="width:100%">Se connecter</button>
  </form>
  
  <form id="registerForm" style="display:none" onsubmit="handleRegister(event)">
    <div class="form-group">
      <label>Nom d'utilisateur</label>
      <input type="text" name="username" required placeholder="Choisissez un pseudo">
    </div>
    <div class="form-group">
      <label>Email (optionnel)</label>
      <input type="email" name="email" placeholder="votre@email.com">
    </div>
    <div class="form-group">
      <label>Mot de passe</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn-primary" style="width:100%">Créer un compte</button>
  </form>
  
  <p style="margin-top:20px;font-size:0.8rem;color:var(--muted);text-align:center;">
    💡 Vous recevez 1000€ virtuels pour commencer à trader
  </p>
</div>

<script>
function showAuth(type) {
  document.getElementById('loginTab').classList.toggle('active', type === 'login');
  document.getElementById('registerTab').classList.toggle('active', type === 'register');
  document.getElementById('loginForm').style.display = type === 'login' ? 'block' : 'none';
  document.getElementById('registerForm').style.display = type === 'register' ? 'block' : 'none';
}

async function handleLogin(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'login');
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.success) {
    // Store token in session via redirect
    window.location.href = 'index.php?token=' + data.token;
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'register');
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.success) {
    showToast('Compte créé ! Connectez-vous', 'success');
    showAuth('login');
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

function showToast(msg, type) {
  const div = document.createElement('div');
  div.className = 'toast ' + type;
  div.textContent = msg;
  document.body.appendChild(div);
  setTimeout(() => div.remove(), 3000);
}
</script>

<?php else: ?>
<!-- MAIN APP -->
<header>
  <div class="logo">Marketplace AI <span>Trading Virtuel</span></div>
  <nav class="nav-links">
    <a href="#market" class="active" onclick="switchTab('market')">🏪 Marché</a>
    <a href="#portfolio" onclick="switchTab('portfolio')">💼 Portefeuille</a>
    <a href="#groups" onclick="switchTab('groups')">👥 Revente Groupée</a>
    <a href="#strategy" onclick="switchTab('strategy')">⚡ Auto-Optimisation</a>
  </nav>
  <div class="user-info">
    <span class="wallet-badge" id="walletDisplay"><?= number_format($currentUser['wallet_balance'], 2) ?> €</span>
    <span style="font-family:var(--mono);font-size:0.8rem;"><?= htmlspecialchars($currentUser['username']) ?></span>
    <a href="?logout" class="btn-logout">Déconnexion</a>
  </div>
</header>

<div class="container">
  <!-- STATS BAR -->
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-val" id="statBalance"><?= number_format($currentUser['wallet_balance'], 0) ?>€</div>
      <div class="stat-label">Solde disponible</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" id="statGains" style="color:var(--ok)">+<?= number_format($currentUser['total_gains'], 0) ?>€</div>
      <div class="stat-label">Gains totaux</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" id="statLosses" style="color:var(--err)"><?= number_format($currentUser['total_pertes'], 0) ?>€</div>
      <div class="stat-label">Pertes totales</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" id="statProducts">0</div>
      <div class="stat-label">Produits en portefeuille</div>
    </div>
  </div>
  
  <!-- TAB: MARKET -->
  <div id="tab-market" class="tab-content active">
    <div class="tabs">
      <button class="tab-btn active" onclick="filterProducts('all')">Tous</button>
      <button class="tab-btn" onclick="filterProducts('tech')">Tech</button>
      <button class="tab-btn" onclick="filterProducts('art')">Art</button>
      <button class="tab-btn" onclick="filterProducts('sport')">Sport</button>
      <button class="tab-btn" onclick="filterProducts('mode')">Mode</button>
      <button class="tab-btn" onclick="filterProducts('maison')">Maison</button>
      <button class="tab-btn" onclick="generateAIProducts()">🤖 Générer avec IA</button>
    </div>
    <div class="products-grid" id="productsGrid">
      <div class="loading"><div class="spinner"></div><p>Chargement des produits...</p></div>
    </div>
  </div>
  
  <!-- TAB: PORTFOLIO -->
  <div id="tab-portfolio" class="tab-content">
    <h2 style="margin-bottom:20px;font-size:1.3rem;">💼 Mon Portefeuille</h2>
    <table class="portfolio-table">
      <thead>
        <tr>
          <th>Produit</th>
          <th>Catégorie</th>
          <th>Quantité</th>
          <th>Prix achat</th>
          <th>Prix actuel</th>
          <th>Gain/Perte</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="portfolioBody">
        <tr><td colspan="7" class="loading">Chargement...</td></tr>
      </tbody>
    </table>
    
    <h3 style="margin:30px 0 16px;font-size:1.1rem;">Historique des transactions</h3>
    <table class="portfolio-table">
      <thead>
        <tr>
          <th>Type</th>
          <th>Produit</th>
          <th>Prix</th>
          <th>Quantité</th>
          <th>Total</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody id="transactionsBody">
        <tr><td colspan="6" class="loading">Chargement...</td></tr>
      </tbody>
    </table>
  </div>
  
  <!-- TAB: GROUPS -->
  <div id="tab-groups" class="tab-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <h2 style="font-size:1.3rem;">👥 Reventes Groupées</h2>
      <button class="btn-primary" onclick="showCreateGroupModal()">+ Créer un groupe</button>
    </div>
    <p style="color:var(--muted);margin-bottom:20px;font-size:0.85rem;">
      Regroupez vos produits perdants ou petits gains avec d'autres utilisateurs pour optimiser la revente et réduire les pertes.
    </p>
    <div class="groups-list" id="groupsList">
      <div class="loading">Chargement des groupes...</div>
    </div>
  </div>
  
  <!-- TAB: STRATEGY -->
  <div id="tab-strategy" class="tab-content">
    <h2 style="margin-bottom:20px;font-size:1.3rem;">⚡ Stratégie d'Optimisation Auto</h2>
    <div class="strategy-panel">
      <div class="form-group">
        <label>Type de stratégie</label>
        <select id="stratType">
          <option value="balanced">Équilibrée</option>
          <option value="aggressive">Aggressive (gains rapides)</option>
          <option value="conservative">Prudente (limiter les pertes)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Vendre automatiquement si gain > (%)</label>
        <input type="number" id="stratGain" value="20" min="0" max="100">
      </div>
      <div class="form-group">
        <label>Vendre automatiquement si perte < (%)</label>
        <input type="number" id="stratLoss" value="-30" min="-100" max="0">
      </div>
      <div class="form-group">
        <label>Réinvestir automatiquement (%)</label>
        <input type="number" id="stratReinvest" value="50" min="0" max="100">
      </div>
      <button class="btn-primary" onclick="saveStrategy()">Enregistrer la stratégie</button>
      
      <div id="currentStrategy" style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border);">
        <h4 style="margin-bottom:12px;font-size:0.9rem;">Stratégie actuelle</h4>
        <div id="strategyInfo" style="font-family:var(--mono);font-size:0.8rem;color:var(--muted);">
          Aucune stratégie configurée
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CREATE GROUP MODAL -->
<div id="groupModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:32px;max-width:500px;width:90%;">
    <h3 style="margin-bottom:20px;">Créer un groupe de revente</h3>
    <div class="form-group">
      <label>Nom du groupe</label>
      <input type="text" id="groupName" placeholder="Ex: Pertes Tech Q1">
    </div>
    <div class="form-group">
      <label>Description</label>
      <input type="text" id="groupDesc" placeholder="Objectif du groupe...">
    </div>
    <div class="form-group">
      <label>Gain minimum cible (%)</label>
      <input type="number" id="groupMinGain" value="-10" min="-100" max="100">
    </div>
    <div class="form-group">
      <label>Perte maximum acceptée (%)</label>
      <input type="number" id="groupMaxLoss" value="-50" min="-100" max="0">
    </div>
    <div style="display:flex;gap:12px;margin-top:24px;">
      <button class="btn-primary" onclick="createGroup()">Créer le groupe</button>
      <button class="btn-logout" onclick="closeGroupModal()" style="flex:1">Annuler</button>
    </div>
  </div>
</div>

<script>
const USER_TOKEN = '<?= $token ?>';
const CURRENT_USER = <?= json_encode($currentUser) ?>;

let allProducts = [];
let currentFilter = 'all';

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', () => {
  loadProducts();
  loadPortfolio();
  loadGroups();
  loadStrategy();
  
  // Update prices every 30s
  setInterval(updatePrices, 30000);
});

// ==================== TAB SWITCHING ====================
function switchTab(name) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.nav-links a').forEach(el => el.classList.remove('active'));
  
  document.getElementById('tab-' + name).classList.add('active');
  event.target.classList.add('active');
  
  if (name === 'portfolio') loadPortfolio();
  if (name === 'groups') loadGroups();
  if (name === 'strategy') loadStrategy();
}

// ==================== PRODUCTS ====================
async function loadProducts() {
  const fd = new FormData();
  fd.append('action', 'get_products');
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  allProducts = data.products || [];
  renderProducts(allProducts);
}

function renderProducts(products) {
  const grid = document.getElementById('productsGrid');
  
  if (!products.length) {
    grid.innerHTML = '<div class="loading">Aucun produit. Cliquez sur "Générer avec IA" pour créer des produits.</div>';
    return;
  }
  
  grid.innerHTML = products.map(p => `
    <div class="product-card" data-category="${p.category}">
      <img src="${p.image_url || 'https://via.placeholder.com/200'}" class="product-img" alt="${p.product_name}">
      <div class="product-body">
        <div class="product-name">${escapeHtml(p.product_name)}</div>
        <div class="product-desc">${escapeHtml(p.description.substring(0, 80))}...</div>
        <div class="product-meta">
          <span class="product-price">${p.price.toFixed(2)}€</span>
          <span class="product-trend trend-${p.trend}">${p.trend}</span>
        </div>
        <button class="btn-buy" onclick="buyProduct(${p.id}, ${p.price})">Acheter</button>
      </div>
    </div>
  `).join('');
}

function filterProducts(category) {
  currentFilter = category;
  
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.textContent.toLowerCase().includes(category === 'all' ? 'tous' : category));
  });
  
  if (category === 'all') {
    renderProducts(allProducts);
  } else {
    const filtered = allProducts.filter(p => p.category === category);
    renderProducts(filtered);
  }
}

async function generateAIProducts() {
  const btn = event.target;
  btn.disabled = true;
  btn.textContent = 'Génération en cours...';
  
  const fd = new FormData();
  fd.append('action', 'generate_products_ai');
  fd.append('token', USER_TOKEN);
  
  try {
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.success) {
      showToast(`${data.generated_count} produits générés !`, 'success');
      loadProducts();
    } else {
      showToast(data.error || 'Erreur génération', 'error');
    }
  } catch(e) {
    showToast('Erreur réseau', 'error');
  }
  
  btn.disabled = false;
  btn.innerHTML = '🤖 Générer avec IA';
}

async function buyProduct(productId, price) {
  if (!confirm(`Confirmer l'achat pour ${price.toFixed(2)}€ ?`)) return;
  
  const fd = new FormData();
  fd.append('action', 'buy_product');
  fd.append('product_id', productId);
  fd.append('quantity', 1);
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.success) {
    showToast('Achat réussi !', 'success');
    updateWallet(data.new_balance);
    loadPortfolio();
  } else {
    showToast(data.error || 'Erreur achat', 'error');
  }
}

// ==================== PORTFOLIO ====================
async function loadPortfolio() {
  const fd = new FormData();
  fd.append('action', 'get_portfolio');
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.user) {
    updateWallet(data.user.wallet_balance);
    document.getElementById('statGains').textContent = '+' + formatNum(data.user.total_gains) + '€';
    document.getElementById('statLosses').textContent = formatNum(data.user.total_pertes) + '€';
    document.getElementById('statProducts').textContent = data.portfolio.length;
  }
  
  const tbody = document.getElementById('portfolioBody');
  if (!data.portfolio.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="loading">Aucun produit en portefeuille</td></tr>';
  } else {
    tbody.innerHTML = data.portfolio.map(item => {
      const gain = (item.current_price - item.purchase_price) * item.quantity;
      const gainClass = gain >= 0 ? 'gain-pos' : 'gain-neg';
      const gainSign = gain >= 0 ? '+' : '';
      
      return `
        <tr>
          <td>${escapeHtml(item.name)}</td>
          <td>${item.category}</td>
          <td>${item.quantity}</td>
          <td>${item.purchase_price.toFixed(2)}€</td>
          <td>${item.current_price.toFixed(2)}€</td>
          <td class="${gainClass}">${gainSign}${gain.toFixed(2)}€</td>
          <td><button class="btn-sell" onclick="sellProduct(${item.id})">Vendre</button></td>
        </tr>
      `;
    }).join('');
  }
  
  const transBody = document.getElementById('transactionsBody');
  if (!data.transactions.length) {
    transBody.innerHTML = '<tr><td colspan="6" class="loading">Aucune transaction</td></tr>';
  } else {
    transBody.innerHTML = data.transactions.map(t => `
      <tr>
        <td style="color:${t.type === 'buy' ? 'var(--accent)' : 'var(--accent2)'}">${t.type === 'buy' ? '🟢 ACHAT' : '🔴 VENTE'}</td>
        <td>${escapeHtml(t.product_name || '-')}</td>
        <td>${t.amount.toFixed(2)}€</td>
        <td>${t.quantity}</td>
        <td>${t.total_price.toFixed(2)}€</td>
        <td style="color:var(--muted)">${new Date(t.created_at).toLocaleDateString('fr-FR')}</td>
      </tr>
    `).join('');
  }
}

async function sellProduct(userProductId) {
  if (!confirm('Confirmer la vente au prix actuel ?')) return;
  
  const fd = new FormData();
  fd.append('action', 'sell_product');
  fd.append('user_product_id', userProductId);
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.success) {
    const gainMsg = data.gain >= 0 ? `+${data.gain.toFixed(2)}€` : `${data.gain.toFixed(2)}€`;
    showToast(`Vendu ! Gain/Perte: ${gainMsg}`, data.gain >= 0 ? 'success' : 'error');
    loadPortfolio();
  } else {
    showToast(data.error || 'Erreur vente', 'error');
  }
}

// ==================== GROUPS ====================
async function loadGroups() {
  const fd = new FormData();
  fd.append('action', 'get_group_resales');
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  const list = document.getElementById('groupsList');
  if (!data.groups.length) {
    list.innerHTML = '<div class="loading">Aucun groupe ouvert</div>';
  } else {
    list.innerHTML = data.groups.map(g => `
      <div class="group-card">
        <div class="group-info">
          <h4>${escapeHtml(g.name)}</h4>
          <p>${escapeHtml(g.description)}</p>
          <div class="group-stats">
            <span>👥 ${g.participants_count} participants</span>
            <span>💰 ${g.total_value.toFixed(0)}€</span>
            <span>📊 Perte max: ${g.max_loss_percent}%</span>
          </div>
        </div>
        <button class="btn-primary" onclick="joinGroup(${g.id})">Rejoindre</button>
      </div>
    `).join('');
  }
}

function showCreateGroupModal() {
  document.getElementById('groupModal').style.display = 'flex';
}

function closeGroupModal() {
  document.getElementById('groupModal').style.display = 'none';
}

async function createGroup() {
  const name = document.getElementById('groupName').value;
  const desc = document.getElementById('groupDesc').value;
  const minGain = document.getElementById('groupMinGain').value;
  const maxLoss = document.getElementById('groupMaxLoss').value;
  
  const fd = new FormData();
  fd.append('action', 'create_group_resale');
  fd.append('name', name);
  fd.append('description', desc);
  fd.append('min_gain', minGain);
  fd.append('max_loss', maxLoss);
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.success) {
    showToast('Groupe créé !', 'success');
    closeGroupModal();
    loadGroups();
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

async function joinGroup(groupId) {
  // Simplifié: rejoint avec produits perdants du portfolio
  const fd = new FormData();
  fd.append('action', 'join_group_resale');
  fd.append('group_id', groupId);
  fd.append('product_ids', '[]');
  fd.append('entry_value', '100');
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.success) {
    showToast('Groupe rejoint !', 'success');
    loadGroups();
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

// ==================== STRATEGY ====================
async function loadStrategy() {
  const fd = new FormData();
  fd.append('action', 'get_strategy');
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  const info = document.getElementById('strategyInfo');
  if (data.strategy) {
    info.innerHTML = `
      <div>Type: <strong>${data.strategy.strategy_type}</strong></div>
      <div>Vente auto si gain > ${data.strategy.auto_sell_gain_above}%</div>
      <div>Vente auto si perte < ${data.strategy.auto_sell_loss_below}%</div>
      <div>Réinvestissement: ${data.strategy.reinvest_percent}%</div>
    `;
    
    document.getElementById('stratType').value = data.strategy.strategy_type;
    document.getElementById('stratGain').value = data.strategy.auto_sell_gain_above;
    document.getElementById('stratLoss').value = data.strategy.auto_sell_loss_below;
    document.getElementById('stratReinvest').value = data.strategy.reinvest_percent;
  } else {
    info.textContent = 'Aucune stratégie configurée';
  }
}

async function saveStrategy() {
  const fd = new FormData();
  fd.append('action', 'set_strategy');
  fd.append('strategy_type', document.getElementById('stratType').value);
  fd.append('auto_sell_gain', document.getElementById('stratGain').value);
  fd.append('auto_sell_loss', document.getElementById('stratLoss').value);
  fd.append('reinvest_percent', document.getElementById('stratReinvest').value);
  fd.append('token', USER_TOKEN);
  
  const res = await fetch('api.php', { method: 'POST', body: fd });
  const data = await res.json();
  
  if (data.success) {
    showToast('Stratégie enregistrée !', 'success');
    loadStrategy();
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

// ==================== UTILS ====================
function updateWallet(newBalance) {
  document.getElementById('walletDisplay').textContent = newBalance.toFixed(2) + ' €';
  document.getElementById('statBalance').textContent = Math.floor(newBalance) + '€';
}

async function updatePrices() {
  const fd = new FormData();
  fd.append('action', 'update_prices');
  fd.append('token', USER_TOKEN);
  
  await fetch('api.php', { method: 'POST', body: fd });
  loadProducts();
  loadPortfolio();
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatNum(n) {
  return n.toFixed(0);
}

function showToast(msg, type) {
  const div = document.createElement('div');
  div.className = 'toast ' + type;
  div.textContent = msg;
  document.body.appendChild(div);
  setTimeout(() => div.remove(), 3000);
}
</script>
</div>
<?php endif; ?>

</body>
</html>
