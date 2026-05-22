<?php
require_once 'db.php';
session_start();
$currentUser = null;
$token = $_SESSION['user_token'] ?? null;

// Handle token from login redirect
if (isset($_GET['token'])) {
    $_SESSION['user_token'] = $_GET['token'];
    $token = $_GET['token'];
    header('Location: index.php');
    exit;
}

if ($token) {
    $db = getDB();
    $currentUser = getUserByToken($db, $token);
    if (!$currentUser) {
        unset($_SESSION['user_token']);
        $token = null;
    }
}

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
<title>NexTrade AI — Marketplace Intelligent</title>
<meta name="description" content="Plateforme de trading virtuel avec produits générés par IA, optimisation automatique et revente groupée.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
/* ========== DESIGN SYSTEM ========== */
:root {
  --bg-900: #050507;
  --bg-800: #0a0b0f;
  --bg-700: #0f1117;
  --bg-600: #141620;
  --bg-500: #1a1d2b;
  --surface: #1e2134;
  --surface-hover: #252940;
  --border: #2a2e42;
  --border-light: #363b54;

  --accent: #00e5c3;
  --accent-dim: rgba(0,229,195,0.12);
  --accent-glow: rgba(0,229,195,0.25);
  --accent-bright: #00ffda;

  --blue: #3b82f6;
  --blue-dim: rgba(59,130,246,0.12);
  --purple: #8b5cf6;
  --purple-dim: rgba(139,92,246,0.12);
  --orange: #f97316;
  --orange-dim: rgba(249,115,22,0.12);
  --pink: #ec4899;
  --pink-dim: rgba(236,72,153,0.12);

  --green: #22c55e;
  --green-dim: rgba(34,197,94,0.12);
  --red: #ef4444;
  --red-dim: rgba(239,68,68,0.12);
  --yellow: #eab308;
  --yellow-dim: rgba(234,179,8,0.12);

  --text-100: #f1f3f9;
  --text-200: #c9cdde;
  --text-300: #8b90a7;
  --text-400: #5c6180;

  --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  --mono: 'JetBrains Mono', 'Fira Code', monospace;
  --radius: 12px;
  --radius-sm: 8px;
  --radius-lg: 16px;
  --shadow: 0 8px 32px rgba(0,0,0,0.4);
  --shadow-lg: 0 16px 64px rgba(0,0,0,0.5);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  font-family: var(--font);
  background: var(--bg-900);
  color: var(--text-100);
  min-height: 100vh;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
}

/* ========== ANIMATED BACKGROUND ========== */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 800px 600px at 20% 20%, rgba(0,229,195,0.04), transparent),
    radial-gradient(ellipse 600px 800px at 80% 80%, rgba(139,92,246,0.04), transparent);
  pointer-events: none;
  z-index: 0;
}
body::after {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
  background-size: 60px 60px;
  pointer-events: none;
  z-index: 0;
}

/* ========== SCROLLBAR ========== */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--border-light); }

/* ========== UTILITIES ========== */
.relative { position: relative; z-index: 1; }
.flex { display: flex; }
.flex-col { flex-direction: column; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-8 { gap: 8px; }
.gap-12 { gap: 12px; }
.gap-16 { gap: 16px; }
.gap-20 { gap: 20px; }
.gap-24 { gap: 24px; }
.w-full { width: 100%; }
.text-center { text-align: center; }
.mono { font-family: var(--mono); }
.text-muted { color: var(--text-300); }
.text-accent { color: var(--accent); }
.text-green { color: var(--green); }
.text-red { color: var(--red); }

/* ========== GLASS CARD ========== */
.glass {
  background: rgba(30,33,52,0.6);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.card {
  background: var(--bg-700);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px;
  transition: border-color 0.3s, box-shadow 0.3s;
}
.card:hover { border-color: var(--border-light); }
.card-glow:hover {
  border-color: var(--accent);
  box-shadow: 0 0 30px var(--accent-dim);
}

.card-title {
  font-family: var(--mono);
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-title::after {
  content: '';
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, var(--border), transparent);
}

/* ========== BUTTONS ========== */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-family: var(--font);
  font-weight: 600;
  font-size: 0.85rem;
  transition: all 0.25s;
  text-decoration: none;
  white-space: nowrap;
}
.btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
.btn-primary {
  background: linear-gradient(135deg, var(--accent), #00b4d8);
  color: #000;
  font-weight: 700;
}
.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, var(--accent-bright), #00cfff);
  transform: translateY(-2px);
  box-shadow: 0 6px 24px var(--accent-dim);
}
.btn-outline {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text-200);
}
.btn-outline:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.btn-danger {
  background: transparent;
  border: 1px solid rgba(239,68,68,0.3);
  color: var(--red);
}
.btn-danger:hover:not(:disabled) { background: var(--red-dim); border-color: var(--red); }
.btn-ghost {
  background: transparent;
  border: none;
  color: var(--text-300);
  padding: 8px 12px;
}
.btn-ghost:hover { color: var(--accent); }
.btn-sm { padding: 6px 14px; font-size: 0.78rem; }
.btn-lg { padding: 14px 28px; font-size: 1rem; }
.btn-icon { padding: 8px; min-width: 36px; min-height: 36px; }

/* ========== FORM ========== */
.form-group { margin-bottom: 18px; }
.form-group label {
  display: block;
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--text-300);
  margin-bottom: 8px;
}
input, select, textarea {
  width: 100%;
  background: var(--bg-800);
  border: 1px solid var(--border);
  color: var(--text-100);
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  font-family: var(--font);
  font-size: 0.9rem;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
input:focus, select:focus, textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-dim);
}
input::placeholder { color: var(--text-400); }

/* ========== PILL BADGE ========== */
.pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 100px;
  font-family: var(--mono);
  font-size: 0.7rem;
  font-weight: 600;
}
.pill-green { background: var(--green-dim); color: var(--green); }
.pill-red { background: var(--red-dim); color: var(--red); }
.pill-blue { background: var(--blue-dim); color: var(--blue); }
.pill-yellow { background: var(--yellow-dim); color: var(--yellow); }
.pill-purple { background: var(--purple-dim); color: var(--purple); }
.pill-accent { background: var(--accent-dim); color: var(--accent); }

/* ========== ANIMATIONS ========== */
@keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: none; } }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
@keyframes glow { 0%,100% { box-shadow: 0 0 5px var(--accent-dim); } 50% { box-shadow: 0 0 20px var(--accent-glow); } }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes priceUp { from { color: var(--green); transform: scale(1.1); } to { transform: scale(1); } }
@keyframes priceDown { from { color: var(--red); transform: scale(1.1); } to { transform: scale(1); } }
@keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }

.animate-fadeUp { animation: fadeUp 0.5s ease both; }
.animate-fadeIn { animation: fadeIn 0.3s ease both; }
.animate-slideIn { animation: slideIn 0.4s ease both; }
.price-flash-up { animation: priceUp 0.6s ease; }
.price-flash-down { animation: priceDown 0.6s ease; }

.skeleton {
  background: linear-gradient(90deg, var(--bg-600) 25%, var(--bg-500) 50%, var(--bg-600) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: var(--radius-sm);
}

.spinner {
  display: inline-block;
  width: 20px; height: 20px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.spinner-lg { width: 40px; height: 40px; border-width: 3px; }

/* ========== TOAST ========== */
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast {
  padding: 14px 20px;
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
  font-weight: 500;
  box-shadow: var(--shadow);
  animation: slideIn 0.3s ease;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 280px;
}
.toast-success { background: #0a2e1a; border: 1px solid rgba(34,197,94,0.3); color: var(--green); }
.toast-error { background: #2e0a0a; border: 1px solid rgba(239,68,68,0.3); color: var(--red); }
.toast-info { background: #0a1a2e; border: 1px solid rgba(59,130,246,0.3); color: var(--blue); }

/* ======================================================================= */
/* AUTH PAGE                                                               */
/* ======================================================================= */
.auth-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  position: relative;
  z-index: 1;
}
.auth-box {
  width: 100%;
  max-width: 440px;
  background: rgba(15,17,23,0.8);
  backdrop-filter: blur(40px);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 40px;
  box-shadow: var(--shadow-lg);
  animation: fadeUp 0.6s ease;
}
.auth-logo {
  text-align: center;
  margin-bottom: 32px;
}
.auth-logo h1 {
  font-size: 2rem;
  font-weight: 900;
  background: linear-gradient(135deg, var(--accent), var(--blue));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -0.5px;
}
.auth-logo p {
  font-size: 0.85rem;
  color: var(--text-300);
  margin-top: 8px;
}
.auth-tabs {
  display: flex;
  gap: 4px;
  background: var(--bg-900);
  border-radius: var(--radius-sm);
  padding: 4px;
  margin-bottom: 28px;
}
.auth-tab {
  flex: 1;
  padding: 10px;
  background: transparent;
  border: none;
  color: var(--text-400);
  font-family: var(--font);
  font-weight: 600;
  font-size: 0.85rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.auth-tab.active {
  background: var(--surface);
  color: var(--accent);
}
.auth-bonus {
  text-align: center;
  margin-top: 24px;
  padding: 16px;
  background: var(--accent-dim);
  border-radius: var(--radius-sm);
  border: 1px solid rgba(0,229,195,0.15);
}
.auth-bonus span { color: var(--accent); font-weight: 700; font-size: 1.1rem; }

/* ======================================================================= */
/* MAIN LAYOUT                                                             */
/* ======================================================================= */
.app-header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(5,5,7,0.85);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  padding: 0 24px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.header-logo {
  font-size: 1.2rem;
  font-weight: 800;
  background: linear-gradient(135deg, var(--accent), var(--blue));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.header-nav { display: flex; gap: 4px; }
.nav-btn {
  padding: 8px 16px;
  background: transparent;
  border: none;
  color: var(--text-300);
  font-family: var(--font);
  font-weight: 500;
  font-size: 0.82rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.nav-btn:hover { color: var(--text-100); background: var(--surface); }
.nav-btn.active { color: var(--accent); background: var(--accent-dim); }

.header-right { display: flex; align-items: center; gap: 16px; }
.wallet-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  background: var(--accent-dim);
  border: 1px solid rgba(0,229,195,0.2);
  border-radius: 100px;
  font-family: var(--mono);
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--accent);
}
.wallet-chip .dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; animation: pulse 2s infinite; }
.user-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: var(--text-200);
}
.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.8rem;
  color: #000;
}

/* Main Container */
.main { max-width: 1440px; margin: 0 auto; padding: 24px; position: relative; z-index: 1; }

/* ========== STATS ROW ========== */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}
.stat-card {
  background: var(--bg-700);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  transition: all 0.3s;
}
.stat-card:hover { border-color: var(--border-light); transform: translateY(-2px); }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.stat-icon {
  width: 40px; height: 40px;
  border-radius: var(--radius-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem;
}
.stat-value {
  font-size: 1.6rem;
  font-weight: 800;
  font-family: var(--mono);
  letter-spacing: -0.5px;
}
.stat-label {
  font-size: 0.72rem;
  color: var(--text-400);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-top: 4px;
}
.stat-delta { font-size: 0.75rem; font-family: var(--mono); font-weight: 600; margin-top: 6px; }

/* ========== TAB PANELS ========== */
.panel { display: none; animation: fadeUp 0.4s ease; }
.panel.active { display: block; }

/* ========== MARKET TAB ========== */
.market-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}
.filter-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-pill {
  padding: 7px 16px;
  background: var(--bg-600);
  border: 1px solid var(--border);
  color: var(--text-300);
  border-radius: 100px;
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  font-family: var(--font);
}
.filter-pill:hover { background: var(--surface); color: var(--text-100); }
.filter-pill.active { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(0,229,195,0.3); }

.search-box {
  position: relative;
  width: 240px;
}
.search-box input {
  padding-left: 36px;
  background: var(--bg-600);
  border-color: transparent;
  font-size: 0.82rem;
}
.search-box input:focus { border-color: var(--accent); }
.search-box::before {
  content: '🔍';
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.85rem;
}

/* Product Grid */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}
.product-card {
  background: var(--bg-700);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  transition: all 0.3s;
  cursor: pointer;
}
.product-card:hover {
  transform: translateY(-6px);
  border-color: var(--accent);
  box-shadow: 0 12px 40px rgba(0,229,195,0.1);
}
.product-visual {
  height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  position: relative;
  overflow: hidden;
}
.product-visual::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: linear-gradient(transparent, var(--bg-700));
}
.product-body { padding: 16px; }
.product-name { font-size: 0.95rem; font-weight: 700; margin-bottom: 6px; }
.product-desc { font-size: 0.78rem; color: var(--text-300); line-height: 1.5; margin-bottom: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.product-footer { display: flex; justify-content: space-between; align-items: center; }
.product-price {
  font-size: 1.2rem;
  font-weight: 800;
  font-family: var(--mono);
  color: var(--accent);
}
.product-buy {
  padding: 8px 18px;
  background: var(--accent);
  color: #000;
  border: none;
  border-radius: var(--radius-sm);
  font-weight: 700;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.2s;
  font-family: var(--font);
}
.product-buy:hover { background: var(--accent-bright); transform: scale(1.05); }

/* ========== PORTFOLIO TAB ========== */
.portfolio-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th {
  text-align: left;
  padding: 12px 16px;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-400);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  border-bottom: 1px solid var(--border);
  background: var(--bg-800);
}
.data-table td {
  padding: 14px 16px;
  font-size: 0.85rem;
  border-bottom: 1px solid rgba(42,46,66,0.5);
}
.data-table tr { transition: background 0.2s; }
.data-table tr:hover td { background: rgba(255,255,255,0.02); }
.data-table .empty-row td { text-align: center; color: var(--text-400); padding: 40px; }

/* ========== LEADERBOARD ========== */
.leader-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  border-bottom: 1px solid rgba(42,46,66,0.4);
  transition: background 0.2s;
}
.leader-row:hover { background: rgba(255,255,255,0.02); }
.leader-rank {
  font-size: 1.1rem;
  font-weight: 800;
  font-family: var(--mono);
  min-width: 36px;
  text-align: center;
}
.leader-rank.gold { color: #fbbf24; }
.leader-rank.silver { color: #94a3b8; }
.leader-rank.bronze { color: #d97706; }
.leader-avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.9rem; color: #000;
}
.leader-info { flex: 1; }
.leader-name { font-weight: 600; font-size: 0.9rem; }
.leader-stats { font-size: 0.75rem; color: var(--text-400); margin-top: 2px; font-family: var(--mono); }
.leader-profit { font-family: var(--mono); font-weight: 700; font-size: 0.95rem; }

/* ========== STRATEGY PANEL ========== */
.strategy-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}
.strategy-presets { display: flex; gap: 8px; margin-bottom: 20px; }
.preset-btn {
  flex: 1;
  padding: 12px;
  background: var(--bg-600);
  border: 1px solid var(--border);
  color: var(--text-300);
  border-radius: var(--radius-sm);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
  font-family: var(--font);
}
.preset-btn:hover { border-color: var(--accent); color: var(--accent); }
.preset-btn.active { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }

/* ========== CHART ========== */
.chart-container {
  position: relative;
  height: 280px;
  background: var(--bg-800);
  border-radius: var(--radius-sm);
  padding: 16px;
  border: 1px solid var(--border);
}

/* ========== MODAL ========== */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(4px);
  z-index: 500;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.modal-overlay.active { display: flex; }
.modal-box {
  background: var(--bg-700);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 32px;
  max-width: 520px;
  width: 100%;
  box-shadow: var(--shadow-lg);
  animation: fadeUp 0.3s ease;
}
.modal-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; }

/* ========== PRICE UPDATE TIMER ========== */
.update-timer {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: var(--bg-600);
  border-radius: 100px;
  font-family: var(--mono);
  font-size: 0.72rem;
  color: var(--text-400);
}
.timer-bar {
  width: 40px;
  height: 3px;
  background: var(--border);
  border-radius: 2px;
  overflow: hidden;
}
.timer-fill {
  height: 100%;
  background: var(--accent);
  border-radius: 2px;
  transition: width 1s linear;
}

/* ========== EMPTY STATE ========== */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-400);
}
.empty-state .icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }
.empty-state h3 { font-size: 1.1rem; color: var(--text-200); margin-bottom: 8px; }
.empty-state p { font-size: 0.85rem; margin-bottom: 20px; }

/* ========== RESPONSIVE ========== */
@media (max-width: 900px) {
  .app-header { padding: 0 16px; }
  .header-nav { display: none; }
  .main { padding: 16px; }
  .strategy-grid { grid-template-columns: 1fr; }
  .products-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
}
@media (max-width: 640px) {
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .products-grid { grid-template-columns: 1fr; }
  .market-toolbar { flex-direction: column; align-items: stretch; }
  .search-box { width: 100%; }
}

/* ========== MOBILE NAV ========== */
.mobile-nav {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(5,5,7,0.95);
  backdrop-filter: blur(20px);
  border-top: 1px solid var(--border);
  padding: 8px 16px;
  z-index: 100;
  justify-content: space-around;
}
.mobile-nav button {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  color: var(--text-400);
  font-size: 0.65rem;
  cursor: pointer;
  padding: 6px 12px;
  font-family: var(--font);
}
.mobile-nav button.active { color: var(--accent); }
.mobile-nav button .nav-icon { font-size: 1.2rem; }
@media (max-width: 900px) {
  .mobile-nav { display: flex; }
  .main { padding-bottom: 80px; }
}
</style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<?php if (!$currentUser): ?>
<!-- ======================== AUTH PAGE ======================== -->
<div class="auth-wrapper">
  <div class="auth-box">
    <div class="auth-logo">
      <h1>NexTrade AI</h1>
      <p>Marketplace de trading virtuel propulsé par l'IA</p>
    </div>

    <div class="auth-tabs">
      <button class="auth-tab active" id="tabLogin" onclick="showAuth('login')">Connexion</button>
      <button class="auth-tab" id="tabRegister" onclick="showAuth('register')">Inscription</button>
    </div>

    <form id="loginForm" onsubmit="handleLogin(event)">
      <div class="form-group">
        <label>Nom d'utilisateur</label>
        <input type="text" name="username" required placeholder="Votre pseudo" autocomplete="username">
      </div>
      <div class="form-group">
        <label>Mot de passe</label>
        <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-full" id="loginBtn">
        Se connecter
      </button>
    </form>

    <form id="registerForm" style="display:none" onsubmit="handleRegister(event)">
      <div class="form-group">
        <label>Nom d'utilisateur</label>
        <input type="text" name="username" required placeholder="Choisissez un pseudo" minlength="3" maxlength="20" autocomplete="username">
      </div>
      <div class="form-group">
        <label>Email (optionnel)</label>
        <input type="email" name="email" placeholder="votre@email.com" autocomplete="email">
      </div>
      <div class="form-group">
        <label>Mot de passe</label>
        <input type="password" name="password" required placeholder="4 caractères min." minlength="4" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-full" id="registerBtn">
        Créer mon compte
      </button>
    </form>

    <div class="auth-bonus">
      <div style="font-size:0.82rem;color:var(--text-200);">🎁 Bonus de bienvenue</div>
      <span>1 000 € virtuels</span>
      <div style="font-size:0.75rem;color:var(--text-400);margin-top:4px;">pour démarrer le trading</div>
    </div>
  </div>
</div>

<script>
function showAuth(type) {
  document.getElementById('tabLogin').classList.toggle('active', type === 'login');
  document.getElementById('tabRegister').classList.toggle('active', type === 'register');
  document.getElementById('loginForm').style.display = type === 'login' ? 'block' : 'none';
  document.getElementById('registerForm').style.display = type === 'register' ? 'block' : 'none';
}

async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Connexion...';
  const fd = new FormData(e.target);
  fd.append('action', 'login');
  try {
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'index.php?token=' + data.token;
    } else {
      showToast(data.error || 'Identifiants invalides', 'error');
      btn.disabled = false;
      btn.textContent = 'Se connecter';
    }
  } catch(e) {
    showToast('Erreur réseau', 'error');
    btn.disabled = false;
    btn.textContent = 'Se connecter';
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const btn = document.getElementById('registerBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Création...';
  const fd = new FormData(e.target);
  fd.append('action', 'register');
  try {
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success && data.token) {
      window.location.href = 'index.php?token=' + data.token;
    } else if (data.success) {
      showToast('Compte créé ! Connectez-vous.', 'success');
      showAuth('login');
    } else {
      showToast(data.error || 'Erreur', 'error');
    }
  } catch(e) {
    showToast('Erreur réseau', 'error');
  }
  btn.disabled = false;
  btn.textContent = 'Créer mon compte';
}
</script>

<?php else: ?>
<!-- ======================== MAIN APP ======================== -->

<!-- HEADER -->
<header class="app-header">
  <div class="header-logo">NexTrade AI</div>

  <nav class="header-nav">
    <button class="nav-btn active" onclick="switchTab('market')" data-tab="market">🏪 Marché</button>
    <button class="nav-btn" onclick="switchTab('portfolio')" data-tab="portfolio">💼 Portefeuille</button>
    <button class="nav-btn" onclick="switchTab('leaderboard')" data-tab="leaderboard">🏆 Classement</button>
    <button class="nav-btn" onclick="switchTab('groups')" data-tab="groups">👥 Groupes</button>
    <button class="nav-btn" onclick="switchTab('strategy')" data-tab="strategy">⚡ Stratégie</button>
    <button class="nav-btn" onclick="switchTab('keys')" data-tab="keys">🔑 API</button>
  </nav>

  <div class="header-right">
    <div class="update-timer" title="Prochaine mise à jour des prix">
      <span id="timerLabel">30s</span>
      <div class="timer-bar"><div class="timer-fill" id="timerFill" style="width:100%"></div></div>
    </div>
    <div class="wallet-chip">
      <span class="dot"></span>
      <span id="walletDisplay"><?= number_format($currentUser['wallet_balance'], 2, ',', ' ') ?> €</span>
    </div>
    <div class="user-chip">
      <div class="user-avatar" style="background:<?= htmlspecialchars($currentUser['avatar_color'] ?? '#00e5c3') ?>">
        <?= strtoupper(substr($currentUser['username'], 0, 2)) ?>
      </div>
      <span><?= htmlspecialchars($currentUser['username']) ?></span>
    </div>
    <a href="?logout" class="btn btn-ghost btn-sm" title="Déconnexion">⏻</a>
  </div>
</header>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <button class="active" onclick="switchTab('market')" data-tab="market"><span class="nav-icon">🏪</span>Marché</button>
  <button onclick="switchTab('portfolio')" data-tab="portfolio"><span class="nav-icon">💼</span>Portfolio</button>
  <button onclick="switchTab('leaderboard')" data-tab="leaderboard"><span class="nav-icon">🏆</span>Top</button>
  <button onclick="switchTab('groups')" data-tab="groups"><span class="nav-icon">👥</span>Groupes</button>
  <button onclick="switchTab('strategy')" data-tab="strategy"><span class="nav-icon">⚡</span>Stratégie</button>
</nav>

<main class="main">
  <!-- ========== STATS ========== -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-header">
        <div class="stat-icon" style="background:var(--accent-dim);">💰</div>
      </div>
      <div class="stat-value text-accent" id="statBalance"><?= number_format($currentUser['wallet_balance'], 0) ?>€</div>
      <div class="stat-label">Solde disponible</div>
    </div>
    <div class="stat-card">
      <div class="stat-header">
        <div class="stat-icon" style="background:var(--green-dim);">📈</div>
      </div>
      <div class="stat-value text-green" id="statGains">+<?= number_format($currentUser['total_gains'], 0) ?>€</div>
      <div class="stat-label">Gains totaux</div>
    </div>
    <div class="stat-card">
      <div class="stat-header">
        <div class="stat-icon" style="background:var(--red-dim);">📉</div>
      </div>
      <div class="stat-value text-red" id="statLosses"><?= number_format($currentUser['total_pertes'], 0) ?>€</div>
      <div class="stat-label">Pertes totales</div>
    </div>
    <div class="stat-card">
      <div class="stat-header">
        <div class="stat-icon" style="background:var(--purple-dim);">📦</div>
      </div>
      <div class="stat-value" style="color:var(--purple)" id="statProducts">0</div>
      <div class="stat-label">En portefeuille</div>
    </div>
    <div class="stat-card">
      <div class="stat-header">
        <div class="stat-icon" style="background:var(--blue-dim);">🔄</div>
      </div>
      <div class="stat-value" style="color:var(--blue)" id="statTrades"><?= intval($currentUser['total_trades'] ?? 0) ?></div>
      <div class="stat-label">Trades effectués</div>
    </div>
  </div>

  <!-- ========== PANEL: MARKET ========== -->
  <div class="panel active" id="panel-market">
    <div class="market-toolbar">
      <div class="filter-pills" id="filterPills">
        <button class="filter-pill active" onclick="filterProducts('all')">Tous</button>
        <button class="filter-pill" onclick="filterProducts('tech')">💻 Tech</button>
        <button class="filter-pill" onclick="filterProducts('art')">🎨 Art</button>
        <button class="filter-pill" onclick="filterProducts('sport')">⚽ Sport</button>
        <button class="filter-pill" onclick="filterProducts('mode')">👗 Mode</button>
        <button class="filter-pill" onclick="filterProducts('maison')">🏠 Maison</button>
        <button class="filter-pill" onclick="filterProducts('jeu')">🎮 Jeu</button>
        <button class="filter-pill" onclick="filterProducts('crypto')">₿ Crypto</button>
        <button class="filter-pill" onclick="filterProducts('musique')">🎵 Musique</button>
        <button class="filter-pill" onclick="filterProducts('food')">🍕 Food</button>
        <button class="filter-pill" onclick="filterProducts('science')">🔬 Science</button>
      </div>
      <div class="flex gap-8 items-center">
        <div class="search-box">
          <input type="text" id="searchInput" placeholder="Rechercher..." oninput="searchProducts()">
        </div>
        <button class="btn btn-primary" id="generateBtn" onclick="generateAIProducts()">🤖 Générer IA</button>
      </div>
    </div>
    <div class="products-grid" id="productsGrid">
      <div class="empty-state" style="grid-column:1/-1;">
        <div class="spinner-lg spinner"></div>
        <p style="margin-top:16px;">Chargement des produits...</p>
      </div>
    </div>
  </div>

  <!-- ========== PANEL: PORTFOLIO ========== -->
  <div class="panel" id="panel-portfolio">
    <div class="portfolio-summary" id="portfolioSummary"></div>

    <div class="card" style="margin-bottom:24px;">
      <div class="card-title">Mes actifs</div>
      <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button class="btn btn-danger btn-sm" onclick="bulkSellAll()">Tout vendre</button>
      </div>
      <div style="overflow-x:auto;">
        <table class="data-table">
          <thead><tr>
            <th>Produit</th><th>Cat.</th><th>Qté</th><th>Prix achat</th><th>Prix actuel</th><th>Gain/Perte</th><th>%</th><th>Trend</th><th>Action</th>
          </tr></thead>
          <tbody id="portfolioBody"><tr class="empty-row"><td colspan="9">Chargement...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Historique des transactions</div>
      <div style="overflow-x:auto;">
        <table class="data-table">
          <thead><tr><th>Type</th><th>Produit</th><th>Prix</th><th>Qté</th><th>Total</th><th>+/-</th><th>Date</th></tr></thead>
          <tbody id="transactionsBody"><tr class="empty-row"><td colspan="7">Chargement...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="card" style="margin-top:24px;">
      <div class="card-title">Performance du portefeuille</div>
      <div class="chart-container">
        <canvas id="portfolioChart"></canvas>
      </div>
    </div>
  </div>

  <!-- ========== PANEL: LEADERBOARD ========== -->
  <div class="panel" id="panel-leaderboard">
    <div class="card">
      <div class="card-title">🏆 Classement des meilleurs traders</div>
      <div id="leaderboardList">
        <div class="empty-state"><div class="spinner-lg spinner"></div><p style="margin-top:16px;">Chargement...</p></div>
      </div>
    </div>
  </div>

  <!-- ========== PANEL: GROUPS ========== -->
  <div class="panel" id="panel-groups">
    <div class="flex justify-between items-center" style="margin-bottom:24px;">
      <div>
        <h2 style="font-size:1.3rem;margin-bottom:4px;">👥 Reventes Groupées</h2>
        <p style="font-size:0.82rem;color:var(--text-400);">Mutualisez vos produits perdants pour optimiser la revente</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('groupModal')">+ Nouveau groupe</button>
    </div>
    <div id="groupsList"></div>
  </div>

  <!-- ========== PANEL: STRATEGY ========== -->
  <div class="panel" id="panel-strategy">
    <h2 style="font-size:1.3rem;margin-bottom:20px;">⚡ Stratégie d'auto-optimisation</h2>
    <div class="strategy-grid">
      <div class="card">
        <div class="card-title">Configuration</div>
        <div class="strategy-presets">
          <button class="preset-btn" onclick="applyPreset('conservative')">🛡️ Prudent</button>
          <button class="preset-btn active" onclick="applyPreset('balanced')">⚖️ Équilibré</button>
          <button class="preset-btn" onclick="applyPreset('aggressive')">🔥 Agressif</button>
        </div>
        <div class="form-group">
          <label>Type de stratégie</label>
          <select id="stratType">
            <option value="conservative">Prudente — limiter les pertes</option>
            <option value="balanced" selected>Équilibrée — risque modéré</option>
            <option value="aggressive">Agressive — gains rapides</option>
          </select>
        </div>
        <div class="form-group">
          <label>Vente auto si gain dépasse (%)</label>
          <input type="number" id="stratGain" value="20" min="1" max="200">
        </div>
        <div class="form-group">
          <label>Vente auto si perte dépasse (%)</label>
          <input type="number" id="stratLoss" value="-30" min="-99" max="0">
        </div>
        <div class="form-group">
          <label>Réinvestissement automatique (%)</label>
          <input type="number" id="stratReinvest" value="50" min="0" max="100">
        </div>
        <button class="btn btn-primary w-full" onclick="saveStrategy()">Enregistrer la stratégie</button>
      </div>
      <div class="card">
        <div class="card-title">Stratégie actuelle</div>
        <div id="strategyInfo" style="font-family:var(--mono);font-size:0.85rem;color:var(--text-300);">
          Aucune stratégie configurée
        </div>
        <div class="chart-container" style="margin-top:20px;height:200px;">
          <canvas id="strategyChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== PANEL: API KEYS ========== -->
  <div class="panel" id="panel-keys">
    <h2 style="font-size:1.3rem;margin-bottom:20px;">🔑 Gestion des clés API Mistral</h2>
    <div class="card" style="margin-bottom:24px;">
      <div class="card-title">Ajouter une clé</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label>Label / Pseudo</label>
          <input type="text" id="kPseudo" placeholder="Ex: Compte Pro">
        </div>
        <div class="form-group">
          <label>Clé API Mistral</label>
          <input type="password" id="kVal" placeholder="Colle ta clé ici">
        </div>
      </div>
      <div class="flex gap-8">
        <button class="btn btn-primary" onclick="addKey()">Enregistrer</button>
        <button class="btn btn-outline" onclick="testKey()">Tester</button>
      </div>
      <div id="keyMsg" style="margin-top:12px;font-family:var(--mono);font-size:0.8rem;"></div>
    </div>
    <div class="card">
      <div class="card-title">Clés enregistrées</div>
      <div style="overflow-x:auto;">
        <table class="data-table">
          <thead><tr><th>Label</th><th>Clé</th><th>Statut</th><th>Erreurs</th><th>Dernier usage</th><th>Actions</th></tr></thead>
          <tbody id="keysTbody"><tr class="empty-row"><td colspan="6">Chargement...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<!-- GROUP MODAL -->
<div class="modal-overlay" id="groupModal">
  <div class="modal-box">
    <div class="modal-title">Créer un groupe de revente</div>
    <div class="form-group"><label>Nom du groupe</label><input type="text" id="groupName" placeholder="Ex: Liquidation Tech Q2"></div>
    <div class="form-group"><label>Description</label><input type="text" id="groupDesc" placeholder="Objectif..."></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div class="form-group"><label>Gain minimum (%)</label><input type="number" id="groupMinGain" value="-10"></div>
      <div class="form-group"><label>Perte max (%)</label><input type="number" id="groupMaxLoss" value="-50"></div>
    </div>
    <div class="flex gap-8" style="margin-top:8px;">
      <button class="btn btn-primary" onclick="createGroup()">Créer</button>
      <button class="btn btn-outline" onclick="closeModal('groupModal')">Annuler</button>
    </div>
  </div>
</div>

<script>
// ==================== STATE ====================
const TOKEN = '<?= htmlspecialchars($token) ?>';
const USER = <?= json_encode([
  'id' => $currentUser['id'],
  'username' => $currentUser['username'],
  'wallet_balance' => $currentUser['wallet_balance'],
  'avatar_color' => $currentUser['avatar_color'] ?? '#00e5c3'
]) ?>;

let allProducts = [];
let currentFilter = 'all';
let searchQuery = '';
let priceUpdateInterval = null;
let timerSeconds = 30;
let portfolioChart = null;
let strategyChart = null;

// ==================== CATEGORY EMOJI MAP ====================
const CATEGORY_EMOJI = {
  tech:'💻', art:'🎨', sport:'⚽', mode:'👗', maison:'🏠',
  jeu:'🎮', crypto:'₿', musique:'🎵', food:'🍕', science:'🔬',
  general:'📦'
};
const CATEGORY_GRADIENT = {
  tech:'linear-gradient(135deg,#0f172a,#1e3a5f)',
  art:'linear-gradient(135deg,#1a0a2e,#3b1768)',
  sport:'linear-gradient(135deg,#0a2e1a,#166534)',
  mode:'linear-gradient(135deg,#2e0a1a,#9f1239)',
  maison:'linear-gradient(135deg,#1a1a0a,#78620d)',
  jeu:'linear-gradient(135deg,#0a1a2e,#1d4ed8)',
  crypto:'linear-gradient(135deg,#1a1600,#a16207)',
  musique:'linear-gradient(135deg,#1a0a20,#7e22ce)',
  food:'linear-gradient(135deg,#2e1a0a,#c2410c)',
  science:'linear-gradient(135deg,#0a2020,#0d9488)',
  general:'linear-gradient(135deg,#1a1a1a,#404040)',
};

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', () => {
  loadProducts();
  loadPortfolio();
  startPriceTimer();
});

// ==================== TABS ====================
function switchTab(name) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-btn, .mobile-nav button').forEach(b => {
    b.classList.toggle('active', b.dataset.tab === name);
  });
  const panel = document.getElementById('panel-' + name);
  if (panel) panel.classList.add('active');

  if (name === 'portfolio') loadPortfolio();
  if (name === 'leaderboard') loadLeaderboard();
  if (name === 'groups') loadGroups();
  if (name === 'strategy') loadStrategy();
  if (name === 'keys') loadKeysData();
}

// ==================== API ====================
async function api(action, data = {}) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('token', TOKEN);
  for (const [k, v] of Object.entries(data)) fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v);
  const res = await fetch('api.php', { method: 'POST', body: fd });
  return res.json();
}

async function apiJSON(action, data = {}) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, token: TOKEN, ...data })
  });
  return res.json();
}

// ==================== PRODUCTS ====================
async function loadProducts() {
  const data = await api('get_products');
  allProducts = (data.products || []).map(p => ({
    ...p,
    price: parseFloat(p.current_price || p.price),
    id: p.product_id || p.id,
  }));
  renderProducts();
}

function renderProducts() {
  let filtered = allProducts;
  if (currentFilter !== 'all') filtered = filtered.filter(p => p.category === currentFilter);
  if (searchQuery) {
    const q = searchQuery.toLowerCase();
    filtered = filtered.filter(p => (p.product_name || '').toLowerCase().includes(q) || (p.description || '').toLowerCase().includes(q));
  }

  const grid = document.getElementById('productsGrid');
  if (!filtered.length) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">
      <div class="icon">📦</div>
      <h3>Aucun produit trouvé</h3>
      <p>Cliquez sur "Générer IA" pour créer des produits</p>
      <button class="btn btn-primary" onclick="generateAIProducts()">🤖 Générer avec l'IA</button>
    </div>`;
    return;
  }

  grid.innerHTML = filtered.map((p, i) => {
    const emoji = CATEGORY_EMOJI[p.category] || '📦';
    const bg = CATEGORY_GRADIENT[p.category] || CATEGORY_GRADIENT.general;
    const trend = p.prod_trend || p.trend || 'stable';
    const trendPill = trend === 'rising'
      ? '<span class="pill pill-green">▲ Hausse</span>'
      : trend === 'falling'
      ? '<span class="pill pill-red">▼ Baisse</span>'
      : '<span class="pill pill-blue">● Stable</span>';

    return `<div class="product-card animate-fadeUp" style="animation-delay:${i*40}ms">
      <div class="product-visual" style="background:${bg}">${emoji}</div>
      <div class="product-body">
        <div class="flex justify-between items-center" style="margin-bottom:6px;">
          <div class="product-name">${esc(p.product_name)}</div>
          ${trendPill}
        </div>
        <div class="product-desc">${esc(p.description || '')}</div>
        <div class="product-footer">
          <div class="product-price" id="price-${p.id}">${p.price.toFixed(2)}€</div>
          <button class="product-buy" onclick="event.stopPropagation();buyProduct(${p.id},${p.price})">Acheter</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function filterProducts(cat) {
  currentFilter = cat;
  document.querySelectorAll('.filter-pill').forEach(b => {
    const label = b.textContent.toLowerCase();
    b.classList.toggle('active', cat === 'all' ? label.includes('tous') : label.includes(cat));
  });
  renderProducts();
}

function searchProducts() {
  searchQuery = document.getElementById('searchInput').value.trim();
  renderProducts();
}

async function generateAIProducts() {
  const btn = document.getElementById('generateBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Génération...';
  try {
    const data = await api('generate_products_ai');
    if (data.success) {
      showToast(`${data.generated_count} produits générés ! (${data.tokens_used || '?'} tokens)`, 'success');
      await loadProducts();
    } else {
      showToast(data.error || 'Erreur génération', 'error');
    }
  } catch(e) {
    showToast('Erreur réseau', 'error');
  }
  btn.disabled = false;
  btn.innerHTML = '🤖 Générer IA';
}

async function buyProduct(productId, price) {
  if (!confirm(`Acheter pour ${price.toFixed(2)}€ ?`)) return;
  const data = await api('buy_product', { product_id: productId, quantity: 1 });
  if (data.success) {
    showToast('Achat réussi !', 'success');
    updateWallet(data.new_balance);
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

// ==================== PORTFOLIO ====================
async function loadPortfolio() {
  const data = await api('get_portfolio');
  if (!data.user) return;

  updateWallet(data.user.wallet_balance);
  document.getElementById('statGains').textContent = '+' + Math.floor(data.user.total_gains) + '€';
  document.getElementById('statLosses').textContent = '-' + Math.floor(data.user.total_pertes) + '€';
  document.getElementById('statProducts').textContent = data.summary?.items_count || data.portfolio.length;
  document.getElementById('statTrades').textContent = data.user.total_trades || 0;

  // Summary cards
  const summary = data.summary || {};
  document.getElementById('portfolioSummary').innerHTML = `
    <div class="stat-card"><div class="stat-value text-accent" style="font-size:1.3rem;">${(summary.total_value||0).toFixed(0)}€</div><div class="stat-label">Valeur du portfolio</div></div>
    <div class="stat-card"><div class="stat-value" style="font-size:1.3rem;color:${(summary.unrealized_gain||0)>=0?'var(--green)':'var(--red)'}">${(summary.unrealized_gain||0)>=0?'+':''}${(summary.unrealized_gain||0).toFixed(0)}€</div><div class="stat-label">Plus-value latente</div></div>
    <div class="stat-card"><div class="stat-value" style="font-size:1.3rem;color:var(--blue)">${summary.items_count||0}</div><div class="stat-label">Actifs détenus</div></div>
  `;

  // Portfolio table
  const tbody = document.getElementById('portfolioBody');
  if (!data.portfolio.length) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="9">Aucun actif — achetez des produits sur le Marché</td></tr>';
  } else {
    tbody.innerHTML = data.portfolio.map(item => {
      const gain = item.gain_loss || ((item.current_price - item.purchase_price) * item.quantity);
      const pct = item.gain_pct || (item.purchase_price > 0 ? ((item.current_price - item.purchase_price) / item.purchase_price * 100) : 0);
      const cls = gain >= 0 ? 'text-green' : 'text-red';
      const sign = gain >= 0 ? '+' : '';
      const trend = item.trend || 'stable';
      const trendPill = trend === 'rising' ? '<span class="pill pill-green">▲</span>' : trend === 'falling' ? '<span class="pill pill-red">▼</span>' : '<span class="pill pill-blue">●</span>';
      return `<tr>
        <td style="font-weight:600">${esc(item.name)}</td>
        <td><span class="pill pill-accent">${item.category}</span></td>
        <td>${item.quantity}</td>
        <td class="mono">${parseFloat(item.purchase_price).toFixed(2)}€</td>
        <td class="mono">${parseFloat(item.current_price).toFixed(2)}€</td>
        <td class="mono ${cls}" style="font-weight:700">${sign}${gain.toFixed(2)}€</td>
        <td class="mono ${cls}">${sign}${pct.toFixed(1)}%</td>
        <td>${trendPill}</td>
        <td><button class="btn btn-outline btn-sm" onclick="sellProduct(${item.id})">Vendre</button></td>
      </tr>`;
    }).join('');
  }

  // Transactions
  const transBody = document.getElementById('transactionsBody');
  if (!data.transactions || !data.transactions.length) {
    transBody.innerHTML = '<tr class="empty-row"><td colspan="7">Aucune transaction</td></tr>';
  } else {
    transBody.innerHTML = data.transactions.map(t => {
      const isBuy = t.type === 'buy';
      const gl = parseFloat(t.gain_loss || 0);
      return `<tr>
        <td><span class="pill ${isBuy ? 'pill-green' : 'pill-red'}">${isBuy ? '🟢 ACHAT' : '🔴 VENTE'}</span></td>
        <td>${esc(t.product_name || '-')}</td>
        <td class="mono">${parseFloat(t.amount).toFixed(2)}€</td>
        <td>${t.quantity}</td>
        <td class="mono">${parseFloat(t.total_price).toFixed(2)}€</td>
        <td class="mono ${gl>=0?'text-green':'text-red'}">${gl>=0?'+':''}${gl.toFixed(2)}€</td>
        <td class="text-muted" style="font-size:0.78rem;">${new Date(t.created_at).toLocaleDateString('fr-FR')}</td>
      </tr>`;
    }).join('');
  }

  // Portfolio chart
  renderPortfolioChart(data.transactions || []);
}

async function sellProduct(userProductId) {
  if (!confirm('Vendre au prix actuel du marché ?')) return;
  const data = await api('sell_product', { user_product_id: userProductId });
  if (data.success) {
    const sign = data.gain >= 0 ? '+' : '';
    showToast(`Vendu ! ${sign}${data.gain.toFixed(2)}€`, data.gain >= 0 ? 'success' : 'error');
    updateWallet(data.new_balance);
    loadPortfolio();
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

async function bulkSellAll() {
  if (!confirm('⚠️ Vendre TOUS vos actifs au prix actuel ?')) return;
  const portData = await api('get_portfolio');
  if (!portData.portfolio || !portData.portfolio.length) { showToast('Aucun actif à vendre', 'info'); return; }
  const ids = portData.portfolio.map(p => p.id);
  const data = await apiJSON('bulk_sell', { ids });
  if (data.success) {
    showToast(`${data.sold} actifs vendus. Total: ${data.total_gain>=0?'+':''}${data.total_gain.toFixed(2)}€`, data.total_gain >= 0 ? 'success' : 'error');
    if (data.new_balance !== undefined) updateWallet(data.new_balance);
    loadPortfolio();
  } else {
    showToast(data.error || 'Erreur', 'error');
  }
}

// ==================== LEADERBOARD ====================
async function loadLeaderboard() {
  const data = await api('get_leaderboard', { limit: 15 });
  const list = document.getElementById('leaderboardList');
  const leaders = data.leaderboard || [];

  if (!leaders.length) {
    list.innerHTML = '<div class="empty-state"><div class="icon">🏆</div><h3>Aucun trader classé</h3><p>Commencez à trader pour apparaître !</p></div>';
    return;
  }

  list.innerHTML = leaders.map((l, i) => {
    const rankClass = i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : '';
    const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `#${i+1}`;
    const profitColor = l.total_profit >= 0 ? 'text-green' : 'text-red';
    const profitSign = l.total_profit >= 0 ? '+' : '';
    return `<div class="leader-row">
      <div class="leader-rank ${rankClass}">${medal}</div>
      <div class="leader-avatar" style="background:${l.avatar_color || '#666'}">${(l.username||'?').substring(0,2).toUpperCase()}</div>
      <div class="leader-info">
        <div class="leader-name">${esc(l.username)}</div>
        <div class="leader-stats">${l.total_trades} trades · ${l.win_rate}% winrate</div>
      </div>
      <div class="leader-profit ${profitColor}">${profitSign}${parseFloat(l.total_profit).toFixed(0)}€</div>
    </div>`;
  }).join('');
}

// ==================== GROUPS ====================
async function loadGroups() {
  const data = await api('get_group_resales');
  const list = document.getElementById('groupsList');
  const groups = data.groups || [];

  if (!groups.length) {
    list.innerHTML = '<div class="empty-state"><div class="icon">👥</div><h3>Aucun groupe ouvert</h3><p>Créez le premier !</p></div>';
    return;
  }

  list.innerHTML = groups.map(g => `
    <div class="card card-glow" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <div>
        <div style="font-weight:700;margin-bottom:4px;">${esc(g.name)}</div>
        <div style="font-size:0.82rem;color:var(--text-300);margin-bottom:8px;">${esc(g.description || '')}</div>
        <div class="flex gap-16" style="font-family:var(--mono);font-size:0.75rem;color:var(--text-400);">
          <span>👥 ${g.participants_count} participants</span>
          <span>💰 ${parseFloat(g.total_value).toFixed(0)}€</span>
          <span>📊 Perte max: ${g.max_loss_percent}%</span>
          <span>Par: ${esc(g.creator_name)}</span>
        </div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="joinGroup(${g.id})">Rejoindre</button>
    </div>
  `).join('');
}

async function createGroup() {
  const name = document.getElementById('groupName').value.trim();
  if (!name) { showToast('Nom requis', 'error'); return; }
  const data = await api('create_group_resale', {
    name,
    description: document.getElementById('groupDesc').value,
    min_gain: document.getElementById('groupMinGain').value,
    max_loss: document.getElementById('groupMaxLoss').value,
  });
  if (data.success) { showToast('Groupe créé !', 'success'); closeModal('groupModal'); loadGroups(); }
  else showToast(data.error || 'Erreur', 'error');
}

async function joinGroup(id) {
  const data = await api('join_group_resale', { group_id: id, product_ids: '[]', entry_value: 100 });
  if (data.success) { showToast('Groupe rejoint !', 'success'); loadGroups(); }
  else showToast(data.error || 'Erreur', 'error');
}

// ==================== STRATEGY ====================
async function loadStrategy() {
  const data = await api('get_strategy');
  const info = document.getElementById('strategyInfo');
  if (data.strategy) {
    const s = data.strategy;
    info.innerHTML = `
      <div style="display:grid;gap:12px;">
        <div class="flex justify-between"><span>Type:</span><span class="pill pill-accent">${s.strategy_type}</span></div>
        <div class="flex justify-between"><span>Vente auto gain:</span><strong class="text-green">>${s.auto_sell_gain_above}%</strong></div>
        <div class="flex justify-between"><span>Vente auto perte:</span><strong class="text-red"><${s.auto_sell_loss_below}%</strong></div>
        <div class="flex justify-between"><span>Réinvestissement:</span><strong class="text-accent">${s.reinvest_percent}%</strong></div>
      </div>
    `;
    document.getElementById('stratType').value = s.strategy_type;
    document.getElementById('stratGain').value = s.auto_sell_gain_above;
    document.getElementById('stratLoss').value = s.auto_sell_loss_below;
    document.getElementById('stratReinvest').value = s.reinvest_percent;

    renderStrategyChart(s);
  }
}

function applyPreset(type) {
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
  event.target.classList.add('active');
  const presets = {
    conservative: { type: 'conservative', gain: 10, loss: -15, reinvest: 20 },
    balanced:     { type: 'balanced',     gain: 20, loss: -30, reinvest: 50 },
    aggressive:   { type: 'aggressive',   gain: 40, loss: -50, reinvest: 80 },
  };
  const p = presets[type];
  document.getElementById('stratType').value = p.type;
  document.getElementById('stratGain').value = p.gain;
  document.getElementById('stratLoss').value = p.loss;
  document.getElementById('stratReinvest').value = p.reinvest;
}

async function saveStrategy() {
  const data = await api('set_strategy', {
    strategy_type: document.getElementById('stratType').value,
    auto_sell_gain: document.getElementById('stratGain').value,
    auto_sell_loss: document.getElementById('stratLoss').value,
    reinvest_percent: document.getElementById('stratReinvest').value,
  });
  if (data.success) { showToast('Stratégie enregistrée !', 'success'); loadStrategy(); }
  else showToast(data.error || 'Erreur', 'error');
}

// ==================== API KEYS ====================
async function loadKeysData() {
  const data = await api('get_data');
  const keys = data.keys || [];
  document.getElementById('keysTbody').innerHTML = !keys.length
    ? '<tr class="empty-row"><td colspan="6">Aucune clé — ajoutez-en une ci-dessus</td></tr>'
    : keys.map(k => `<tr>
        <td style="font-weight:600">${esc(k.pseudo)}</td>
        <td class="mono" style="font-size:0.78rem;">${k.key_masked}</td>
        <td>${k.is_active==1 ? '<span class="pill pill-green">Active</span>' : '<span class="pill pill-red">Désactivée</span>'}</td>
        <td>${k.error_count > 0 ? '<span class="pill pill-red">'+k.error_count+'</span>' : '<span class="text-muted">0</span>'}</td>
        <td class="text-muted" style="font-size:0.78rem;">${k.last_used || 'jamais'}</td>
        <td class="flex gap-8">
          <button class="btn btn-outline btn-sm" onclick="resetKeyErrors(${k.id})">Reset</button>
          <button class="btn btn-danger btn-sm" onclick="deleteKey(${k.id})">Suppr.</button>
        </td>
      </tr>`).join('');
}

async function addKey() {
  const pseudo = document.getElementById('kPseudo').value.trim();
  const key = document.getElementById('kVal').value.trim();
  if (!pseudo || !key) { showKeyMsg('Remplis les deux champs', 'var(--red)'); return; }
  const r = await api('add_key', { pseudo, key });
  if (r.success) {
    showKeyMsg('✓ Clé enregistrée', 'var(--green)');
    document.getElementById('kPseudo').value = '';
    document.getElementById('kVal').value = '';
    loadKeysData();
  } else showKeyMsg('Erreur: '+(r.error||'inconnue'), 'var(--red)');
}

async function testKey() {
  const key = document.getElementById('kVal').value.trim();
  if (!key) { showKeyMsg('Saisis une clé d\'abord', 'var(--yellow)'); return; }
  showKeyMsg('Test en cours...', 'var(--accent)');
  const r = await api('test_key', { key });
  if (r.code === 200) showKeyMsg(`✓ devstral-2512 OK (${r.tokens} tokens)`, 'var(--green)');
  else showKeyMsg(`✗ Erreur HTTP ${r.code}`, 'var(--red)');
}

async function deleteKey(id) {
  if (!confirm('Supprimer cette clé ?')) return;
  await apiJSON('delete_key', { id });
  showToast('Clé supprimée', 'success');
  loadKeysData();
}

async function resetKeyErrors(id) {
  await apiJSON('reset_key_errors', { id });
  showToast('Erreurs réinitialisées', 'success');
  loadKeysData();
}

function showKeyMsg(msg, color) {
  const el = document.getElementById('keyMsg');
  el.textContent = msg;
  el.style.color = color;
}

// ==================== CHARTS ====================
function renderPortfolioChart(transactions) {
  const ctx = document.getElementById('portfolioChart');
  if (!ctx) return;
  if (portfolioChart) portfolioChart.destroy();

  // Build cumulative P&L from transactions
  const sorted = [...transactions].reverse();
  let cumulative = 0;
  const labels = [];
  const data = [];
  sorted.forEach(t => {
    if (t.type === 'sell') cumulative += parseFloat(t.gain_loss || 0);
    labels.push(new Date(t.created_at).toLocaleDateString('fr-FR', {day:'2-digit',month:'short'}));
    data.push(cumulative);
  });

  if (!data.length) { labels.push('Aujourd\'hui'); data.push(0); }

  portfolioChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'P&L cumulé',
        data,
        borderColor: cumulative >= 0 ? '#22c55e' : '#ef4444',
        backgroundColor: cumulative >= 0 ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6,
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#5c6180', font: { size: 10 } } },
        y: { grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#5c6180', font: { size: 10 }, callback: v => v + '€' } },
      },
    },
  });
}

function renderStrategyChart(strategy) {
  const ctx = document.getElementById('strategyChart');
  if (!ctx) return;
  if (strategyChart) strategyChart.destroy();

  strategyChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Réinvestir', 'Conserver'],
      datasets: [{
        data: [strategy.reinvest_percent, 100 - strategy.reinvest_percent],
        backgroundColor: ['rgba(0,229,195,0.7)', 'rgba(42,46,66,0.5)'],
        borderColor: ['#00e5c3', '#2a2e42'],
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: { position: 'bottom', labels: { color: '#8b90a7', font: { size: 11 } } },
      },
    },
  });
}

// ==================== PRICE UPDATE TIMER ====================
function startPriceTimer() {
  timerSeconds = 30;
  updateTimerDisplay();
  if (priceUpdateInterval) clearInterval(priceUpdateInterval);
  priceUpdateInterval = setInterval(() => {
    timerSeconds--;
    updateTimerDisplay();
    if (timerSeconds <= 0) {
      updatePrices();
      timerSeconds = 30;
    }
  }, 1000);
}

function updateTimerDisplay() {
  const label = document.getElementById('timerLabel');
  const fill = document.getElementById('timerFill');
  if (label) label.textContent = timerSeconds + 's';
  if (fill) fill.style.width = (timerSeconds / 30 * 100) + '%';
}

async function updatePrices() {
  await api('update_prices');
  await loadProducts();
  // Refresh portfolio if it's visible
  if (document.getElementById('panel-portfolio').classList.contains('active')) {
    loadPortfolio();
  }
}

// ==================== UTILS ====================
function updateWallet(balance) {
  const val = parseFloat(balance);
  document.getElementById('walletDisplay').textContent = val.toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' €';
  document.getElementById('statBalance').textContent = Math.floor(val) + '€';
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function showToast(msg, type = 'info') {
  const container = document.getElementById('toastContainer');
  const div = document.createElement('div');
  div.className = 'toast toast-' + type;
  const icons = { success: '✓', error: '✗', info: 'ℹ' };
  div.innerHTML = `<span style="font-size:1.1rem;">${icons[type]||'ℹ'}</span> ${esc(msg)}`;
  container.appendChild(div);
  setTimeout(() => { div.style.opacity = '0'; div.style.transform = 'translateX(40px)'; setTimeout(() => div.remove(), 300); }, 3500);
}

function esc(text) {
  if (!text) return '';
  const d = document.createElement('div');
  d.textContent = text;
  return d.innerHTML;
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});
</script>

<?php endif; ?>
</body>
</html>
