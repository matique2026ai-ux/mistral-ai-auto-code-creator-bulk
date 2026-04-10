<?php
require_once 'db.php';
header('Content-Type: application/json');

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$jsonBody = null;
$action = '';

if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $jsonBody = json_decode($raw, true);
    $action = $jsonBody['action'] ?? '';
} else {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
}

$db = getDB();

// ==================== AUTHENTIFICATION ====================
if ($action === 'register') {
    $username = trim($_POST['username'] ?? $jsonBody['username'] ?? '');
    $email = trim($_POST['email'] ?? $jsonBody['email'] ?? '');
    $password = $_POST['password'] ?? $jsonBody['password'] ?? '';
    
    if (!$username || !$password) {
        echo json_encode(['error' => 'Username et mot de passe requis']);
        exit;
    }
    
    try {
        $userId = createUser($db, $username, $email, $password);
        echo json_encode(['success' => true, 'user_id' => $userId]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Nom d\'utilisateur déjà pris']);
    }
    exit;
}

if ($action === 'login') {
    $username = trim($_POST['username'] ?? $jsonBody['username'] ?? '');
    $password = $_POST['password'] ?? $jsonBody['password'] ?? '';
    
    $result = authenticateUser($db, $username, $password);
    if ($result) {
        echo json_encode([
            'success' => true, 
            'token' => $result['token'], 
            'user' => $result['user']
        ]);
    } else {
        echo json_encode(['error' => 'Identifiants invalides']);
    }
    exit;
}

if ($action === 'logout') {
    $token = $_POST['token'] ?? $jsonBody['token'] ?? '';
    if ($token) {
        $db->prepare("DELETE FROM user_sessions WHERE session_token = ?")->execute([$token]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Vérification token pour actions protégées
$currentUser = null;
$token = $_POST['token'] ?? $_GET['token'] ?? $jsonBody['token'] ?? '';
if ($token) {
    $currentUser = getUserByToken($db, $token);
}

// ==================== PRODUITS - CACHE IA ====================
if ($action === 'get_products') {
    $products = getPreGeneratedProducts($db, 50);
    echo json_encode(['products' => $products]);
    exit;
}

if ($action === 'generate_products_ai') {
    // Génère des produits via IA en background (appel API Mistral)
    // Cette fonction est appelée périodiquement pour remplir le cache
    
    $keyData = $db->query("SELECT * FROM api_keys WHERE is_active=1 AND error_count < 3 ORDER BY last_used ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$keyData) {
        echo json_encode(['error' => 'Aucune clé API disponible']);
        exit;
    }
    
    $apiKey = $keyData['key_val'];
    
    $messages = [
        [
            'role' => 'system',
            'content' => 'Tu es un générateur de produits pour un marketplace. Retourne UNIQUEMENT un JSON valide avec un tableau "products" contenant 20 produits variés. Chaque produit a: name, description, category (tech, art, sport, mode, maison, jeu), price (10-500), volatility (0.1-0.9), trend (stable, rising, falling), image_url (URL placeholder).'
        ],
        [
            'role' => 'user',
            'content' => 'Génère 20 produits créatifs et variés pour un marketplace de trading virtuel.'
        ]
    ];
    
    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey, 
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'devstral-2512',
            'messages' => $messages,
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object']
        ]),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 200) {
        echo json_encode(['error' => 'Erreur API IA: HTTP ' . $code]);
        exit;
    }
    
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    
    // Parser JSON
    $content = trim(str_replace(['```json', '```'], '', $content));
    $generated = json_decode($content, true);
    
    if (!isset($generated['products'])) {
        echo json_encode(['error' => 'Format IA invalide']);
        exit;
    }
    
    // Insérer dans products_cache
    $count = 0;
    foreach ($generated['products'] as $p) {
        $stmt = $db->prepare("INSERT INTO products_cache 
            (product_name, description, category, price, volatility, trend, image_url, ai_generated_data, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $p['name'] ?? 'Produit sans nom',
            $p['description'] ?? '',
            $p['category'] ?? 'general',
            $p['price'] ?? 50,
            $p['volatility'] ?? 0.5,
            $p['trend'] ?? 'stable',
            $p['image_url'] ?? 'https://via.placeholder.com/200',
            json_encode($p)
        ]);
        $count++;
        
        // Créer aussi dans products
        $cacheId = $db->lastInsertId();
        $stmt = $db->prepare("INSERT INTO products 
            (cache_id, name, description, base_price, current_price, category) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $cacheId,
            $p['name'] ?? 'Produit sans nom',
            $p['description'] ?? '',
            $p['price'] ?? 50,
            $p['price'] ?? 50,
            $p['category'] ?? 'general'
        ]);
    }
    
    echo json_encode(['success' => true, 'generated_count' => $count]);
    exit;
}

// ==================== ACHAT / VENTE ====================
if ($action === 'buy_product') {
    if (!$currentUser) {
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $productId = intval($_POST['product_id'] ?? $jsonBody['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? $jsonBody['quantity'] ?? 1);
    
    $result = buyProduct($db, $currentUser['id'], $productId, $quantity);
    echo json_encode($result);
    exit;
}

if ($action === 'sell_product') {
    if (!$currentUser) {
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $userProductId = intval($_POST['user_product_id'] ?? $jsonBody['user_product_id'] ?? 0);
    $sellPrice = floatval($_POST['sell_price'] ?? $jsonBody['sell_price'] ?? 0);
    
    $result = sellProduct($db, $currentUser['id'], $userProductId, $sellPrice ?: null);
    echo json_encode($result);
    exit;
}

// ==================== PORTEFEUILLE UTILISATEUR ====================
if ($action === 'get_portfolio') {
    if (!$currentUser) {
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $portfolio = getUserPortfolio($db, $currentUser['id']);
    $transactions = getUserTransactions($db, $currentUser['id'], 10);
    
    echo json_encode([
        'user' => $currentUser,
        'portfolio' => $portfolio,
        'transactions' => $transactions
    ]);
    exit;
}

// ==================== GROUPES DE REVENTE ====================
if ($action === 'create_group_resale') {
    if (!$currentUser) {
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $name = trim($_POST['name'] ?? $jsonBody['name'] ?? '');
    $description = trim($_POST['description'] ?? $jsonBody['description'] ?? '');
    $minGain = floatval($_POST['min_gain'] ?? $jsonBody['min_gain'] ?? -10);
    $maxLoss = floatval($_POST['max_loss'] ?? $jsonBody['max_loss'] ?? -50);
    
    $groupId = createGroupResale($db, $currentUser['id'], $name, $description, $minGain, $maxLoss);
    echo json_encode(['success' => true, 'group_id' => $groupId]);
    exit;
}

if ($action === 'join_group_resale') {
    if (!$currentUser) {
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $groupId = intval($_POST['group_id'] ?? $jsonBody['group_id'] ?? 0);
    $productIds = $_POST['product_ids'] ?? $jsonBody['product_ids'] ?? [];
    $entryValue = floatval($_POST['entry_value'] ?? $jsonBody['entry_value'] ?? 0);
    
    if (is_string($productIds)) {
        $productIds = json_decode($productIds, true);
    }
    
    $result = joinGroupResale($db, $groupId, $currentUser['id'], $productIds, $entryValue);
    echo json_encode(['success' => $result]);
    exit;
}

if ($action === 'get_group_resales') {
    $groups = $db->query("SELECT g.*, u.username as creator_name 
        FROM group_resales g 
        JOIN users u ON g.creator_id = u.id 
        WHERE g.status = 'open' 
        ORDER BY g.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['groups' => $groups]);
    exit;
}

// ==================== OPTIMISATION GAINS ====================
if ($action === 'set_strategy') {
    if (!$currentUser) {
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $strategyType = $_POST['strategy_type'] ?? $jsonBody['strategy_type'] ?? 'balanced';
    $autoSellGain = floatval($_POST['auto_sell_gain'] ?? $jsonBody['auto_sell_gain'] ?? 20);
    $autoSellLoss = floatval($_POST['auto_sell_loss'] ?? $jsonBody['auto_sell_loss'] ?? -30);
    $reinvestPercent = floatval($_POST['reinvest_percent'] ?? $jsonBody['reinvest_percent'] ?? 50);
    
    $result = optimizeGains($db, $currentUser['id'], $strategyType, $autoSellGain, $autoSellLoss, $reinvestPercent);
    echo json_encode(['success' => $result]);
    exit;
}

if ($action === 'get_strategy') {
    if (!$currentUser) {
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT * FROM gain_strategies WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$currentUser['id']]);
    $strategy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['strategy' => $strategy]);
    exit;
}

// ==================== STATS MARKETPLACE ====================
if ($action === 'get_stats') {
    $stats = getMarketplaceStats($db);
    $topProducts = $db->query("SELECT p.*, pc.purchase_count 
        FROM products p 
        LEFT JOIN products_cache pc ON p.cache_id = pc.id 
        ORDER BY pc.purchase_count DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'stats' => $stats,
        'top_products' => $topProducts
    ]);
    exit;
}

// ==================== UPDATE PRIX (background) ====================
if ($action === 'update_prices') {
    updateProductPrices($db);
    echo json_encode(['success' => true]);
    exit;
}

// ==================== COMPATIBILITE ANCIENNES ACTIONS ====================
if ($action === 'add_key') {
    $pseudo = trim($_POST['pseudo'] ?? '');
    $key    = trim($_POST['key'] ?? '');
    if (!$pseudo || !$key) { echo json_encode(['error'=>'Champs manquants']); exit; }
    $stmt = $db->prepare("INSERT OR IGNORE INTO api_keys (pseudo, key_val) VALUES (?,?)");
    $stmt->execute([$pseudo, $key]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get_key') {
    $k = $db->query("SELECT * FROM api_keys WHERE is_active=1 AND error_count < 3 ORDER BY last_used ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$k) { echo json_encode(['error' => 'Aucune cle API disponible']); exit; }
    $db->prepare("UPDATE api_keys SET last_used=CURRENT_TIMESTAMP WHERE id=?")->execute([$k['id']]);
    echo json_encode(['key' => $k['key_val'], 'id' => $k['id']]);
    exit;
}

echo json_encode(['error' => 'Action inconnue: ' . $action]);

?>
