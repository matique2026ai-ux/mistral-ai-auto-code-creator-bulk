<?php
require_once 'db.php';
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

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
    if (strlen($username) < 3 || strlen($username) > 20) {
        echo json_encode(['error' => 'Le pseudo doit faire entre 3 et 20 caractères']);
        exit;
    }
    if (strlen($password) < 4) {
        echo json_encode(['error' => 'Le mot de passe doit faire au moins 4 caractères']);
        exit;
    }

    try {
        $userId = createUser($db, $username, $email, $password);
        // Auto-login après inscription
        $authResult = authenticateUser($db, $username, $password);
        if ($authResult) {
            echo json_encode([
                'success' => true,
                'user_id' => $userId,
                'token' => $authResult['token'],
                'user' => $authResult['user']
            ]);
        } else {
            echo json_encode(['success' => true, 'user_id' => $userId]);
        }
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
            'user' => [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'wallet_balance' => $result['user']['wallet_balance'],
                'total_gains' => $result['user']['total_gains'],
                'total_pertes' => $result['user']['total_pertes'],
                'total_trades' => $result['user']['total_trades'] ?? 0,
                'avatar_color' => $result['user']['avatar_color'] ?? '#00e5c3',
            ]
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

// ---- Vérification token pour actions protégées ----
$currentUser = null;
$token = $_POST['token'] ?? $_GET['token'] ?? $jsonBody['token'] ?? '';
if ($token) {
    $currentUser = getUserByToken($db, $token);
}

// ==================== PRODUITS ====================

if ($action === 'get_products') {
    $category = $_POST['category'] ?? $jsonBody['category'] ?? $_GET['category'] ?? null;
    $products = getPreGeneratedProducts($db, 50, $category);
    echo json_encode(['products' => $products]);
    exit;
}

if ($action === 'get_product_detail') {
    $productId = intval($_POST['product_id'] ?? $jsonBody['product_id'] ?? $_GET['product_id'] ?? 0);
    $product = getProductById($db, $productId);
    if (!$product) {
        echo json_encode(['error' => 'Produit non trouvé']);
        exit;
    }
    $history = getPriceHistory($db, $productId, 30);
    echo json_encode(['product' => $product, 'price_history' => $history]);
    exit;
}

if ($action === 'get_price_history') {
    $productId = intval($_POST['product_id'] ?? $jsonBody['product_id'] ?? $_GET['product_id'] ?? 0);
    $limit = intval($_POST['limit'] ?? $jsonBody['limit'] ?? $_GET['limit'] ?? 20);
    $history = getPriceHistory($db, $productId, min($limit, 50));
    echo json_encode(['history' => $history]);
    exit;
}

if ($action === 'generate_products_ai') {
    $keyData = $db->query("SELECT * FROM api_keys WHERE is_active=1 AND error_count < 3 ORDER BY last_used ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$keyData) {
        echo json_encode(['error' => 'Aucune clé API disponible']);
        exit;
    }

    $apiKey = $keyData['key_val'];

    $categories = ['tech', 'art', 'sport', 'mode', 'maison', 'jeu', 'crypto', 'musique', 'food', 'science'];

    $messages = [
        [
            'role' => 'system',
            'content' => 'Tu es un générateur de produits pour un marketplace de trading virtuel. Retourne UNIQUEMENT un JSON valide avec un tableau "products" contenant 20 produits créatifs et variés. Chaque produit DOIT avoir: name (string), description (string, 1-2 phrases), category (parmi: ' . implode(', ', $categories) . '), price (number entre 5 et 800), volatility (number entre 0.1 et 0.9), trend (parmi: stable, rising, falling). NE PAS inclure image_url.'
        ],
        [
            'role' => 'user',
            'content' => 'Génère 20 produits uniques et créatifs pour le marketplace. Sois imaginatif et diversifié dans les catégories et les prix. Produits futuristes, rares, et collector bienvenus.'
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
        CURLOPT_TIMEOUT => 45
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Marquer last_used
    $db->prepare("UPDATE api_keys SET last_used=CURRENT_TIMESTAMP WHERE id=?")->execute([$keyData['id']]);

    if ($code !== 200) {
        markKeyError($db, $keyData['id']);
        echo json_encode(['error' => 'Erreur API IA: HTTP ' . $code]);
        exit;
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    $tokens = $data['usage']['total_tokens'] ?? 0;
    recordTokenUsage($db, $keyData['id'], $tokens, 'devstral-2512');

    // Parser JSON robuste
    $content = trim($content);
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/^```\s*/i', '', $content);
    $content = preg_replace('/\s*```$/i', '', $content);

    $generated = json_decode($content, true);
    if (!$generated) {
        // Essayer d'extraire le JSON
        preg_match('/\{.*\}/s', $content, $matches);
        if ($matches) {
            $generated = json_decode($matches[0], true);
        }
    }

    if (!isset($generated['products']) || !is_array($generated['products'])) {
        echo json_encode(['error' => 'Format IA invalide', 'raw' => substr($content, 0, 200)]);
        exit;
    }

    $validCategories = array_flip($categories);
    $count = 0;
    foreach ($generated['products'] as $p) {
        $name = trim($p['name'] ?? 'Produit mystère');
        $desc = trim($p['description'] ?? 'Un produit unique.');
        $cat = strtolower(trim($p['category'] ?? 'tech'));
        if (!isset($validCategories[$cat])) $cat = 'tech';
        $price = max(5, min(800, floatval($p['price'] ?? 50)));
        $vol = max(0.1, min(0.9, floatval($p['volatility'] ?? 0.5)));
        $trend = in_array($p['trend'] ?? '', ['stable','rising','falling']) ? $p['trend'] : 'stable';

        $stmt = $db->prepare("INSERT INTO products_cache
            (product_name, description, category, price, volatility, trend, image_url, ai_generated_data, is_active)
            VALUES (?, ?, ?, ?, ?, ?, '', ?, 1)");
        $stmt->execute([$name, $desc, $cat, $price, $vol, $trend, json_encode($p)]);

        $cacheId = $db->lastInsertId();
        $stmt = $db->prepare("INSERT INTO products
            (cache_id, name, description, base_price, current_price, category, volatility, trend)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cacheId, $name, $desc, $price, $price, $cat, $vol, $trend]);

        // Init price history
        $productId = $db->lastInsertId();
        recordPriceHistory($db, $productId, $price);

        $count++;
    }

    echo json_encode(['success' => true, 'generated_count' => $count, 'tokens_used' => $tokens]);
    exit;
}

// ==================== ACHAT / VENTE ====================

if ($action === 'buy_product') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $productId = intval($_POST['product_id'] ?? $jsonBody['product_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? $jsonBody['quantity'] ?? 1));

    $result = buyProduct($db, $currentUser['id'], $productId, $quantity);
    echo json_encode($result);
    exit;
}

if ($action === 'sell_product') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $userProductId = intval($_POST['user_product_id'] ?? $jsonBody['user_product_id'] ?? 0);
    $sellPrice = floatval($_POST['sell_price'] ?? $jsonBody['sell_price'] ?? 0);

    $result = sellProduct($db, $currentUser['id'], $userProductId, $sellPrice ?: null);
    echo json_encode($result);
    exit;
}

if ($action === 'bulk_sell') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $ids = $_POST['ids'] ?? $jsonBody['ids'] ?? [];
    if (is_string($ids)) $ids = json_decode($ids, true);
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['error' => 'Aucun produit sélectionné']);
        exit;
    }

    $result = bulkSellProducts($db, $currentUser['id'], $ids);
    echo json_encode($result);
    exit;
}

// ==================== PORTEFEUILLE ====================

if ($action === 'get_portfolio') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $portfolio = getUserPortfolio($db, $currentUser['id']);
    $transactions = getUserTransactions($db, $currentUser['id'], 20);

    // Refresh user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Portfolio summary
    $totalValue = 0;
    $totalCost = 0;
    foreach ($portfolio as &$item) {
        $item['gain_loss'] = ($item['current_price'] - $item['purchase_price']) * $item['quantity'];
        $item['gain_pct'] = $item['purchase_price'] > 0
            ? round(($item['current_price'] - $item['purchase_price']) / $item['purchase_price'] * 100, 1)
            : 0;
        $totalValue += $item['current_price'] * $item['quantity'];
        $totalCost += $item['purchase_price'] * $item['quantity'];
    }

    echo json_encode([
        'user' => [
            'wallet_balance' => $user['wallet_balance'],
            'total_gains' => $user['total_gains'],
            'total_pertes' => $user['total_pertes'],
            'total_trades' => $user['total_trades'] ?? 0,
        ],
        'portfolio' => $portfolio,
        'transactions' => $transactions,
        'summary' => [
            'total_value' => round($totalValue, 2),
            'total_cost' => round($totalCost, 2),
            'unrealized_gain' => round($totalValue - $totalCost, 2),
            'items_count' => count($portfolio),
        ]
    ]);
    exit;
}

// ==================== LEADERBOARD ====================

if ($action === 'get_leaderboard') {
    $limit = min(20, intval($_POST['limit'] ?? $jsonBody['limit'] ?? $_GET['limit'] ?? 10));
    $leaders = getLeaderboard($db, $limit);
    echo json_encode(['leaderboard' => $leaders]);
    exit;
}

// ==================== MARKET OVERVIEW ====================

if ($action === 'get_market_overview') {
    $overview = getMarketOverview($db);
    echo json_encode($overview);
    exit;
}

// ==================== GROUPES DE REVENTE ====================

if ($action === 'create_group_resale') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $name = trim($_POST['name'] ?? $jsonBody['name'] ?? '');
    $description = trim($_POST['description'] ?? $jsonBody['description'] ?? '');
    $minGain = floatval($_POST['min_gain'] ?? $jsonBody['min_gain'] ?? -10);
    $maxLoss = floatval($_POST['max_loss'] ?? $jsonBody['max_loss'] ?? -50);

    if (!$name) { echo json_encode(['error' => 'Le nom du groupe est requis']); exit; }

    $groupId = createGroupResale($db, $currentUser['id'], $name, $description, $minGain, $maxLoss);
    echo json_encode(['success' => true, 'group_id' => $groupId]);
    exit;
}

if ($action === 'join_group_resale') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $groupId = intval($_POST['group_id'] ?? $jsonBody['group_id'] ?? 0);
    $productIds = $_POST['product_ids'] ?? $jsonBody['product_ids'] ?? [];
    $entryValue = floatval($_POST['entry_value'] ?? $jsonBody['entry_value'] ?? 0);

    if (is_string($productIds)) $productIds = json_decode($productIds, true);

    $result = joinGroupResale($db, $groupId, $currentUser['id'], $productIds, $entryValue);
    echo json_encode(['success' => $result]);
    exit;
}

if ($action === 'get_group_resales') {
    $groups = $db->query("SELECT g.*, u.username as creator_name, u.avatar_color
        FROM group_resales g
        JOIN users u ON g.creator_id = u.id
        WHERE g.status = 'open'
        ORDER BY g.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['groups' => $groups]);
    exit;
}

// ==================== STRATEGIES ====================

if ($action === 'set_strategy') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $strategyType = $_POST['strategy_type'] ?? $jsonBody['strategy_type'] ?? 'balanced';
    $autoSellGain = floatval($_POST['auto_sell_gain'] ?? $jsonBody['auto_sell_gain'] ?? 20);
    $autoSellLoss = floatval($_POST['auto_sell_loss'] ?? $jsonBody['auto_sell_loss'] ?? -30);
    $reinvestPercent = floatval($_POST['reinvest_percent'] ?? $jsonBody['reinvest_percent'] ?? 50);

    $result = optimizeGains($db, $currentUser['id'], $strategyType, $autoSellGain, $autoSellLoss, $reinvestPercent);
    echo json_encode(['success' => $result]);
    exit;
}

if ($action === 'get_strategy') {
    if (!$currentUser) { echo json_encode(['error' => 'Non authentifié']); exit; }

    $stmt = $db->prepare("SELECT * FROM gain_strategies WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$currentUser['id']]);
    $strategy = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['strategy' => $strategy]);
    exit;
}

// ==================== MISE A JOUR DES PRIX ====================

if ($action === 'update_prices') {
    updateProductPrices($db);
    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
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

// ==================== GESTION CLES API ====================

if ($action === 'add_key') {
    $pseudo = trim($_POST['pseudo'] ?? $jsonBody['pseudo'] ?? '');
    $key    = trim($_POST['key'] ?? $jsonBody['key'] ?? '');
    if (!$pseudo || !$key) { echo json_encode(['error'=>'Champs manquants']); exit; }
    $stmt = $db->prepare("INSERT OR IGNORE INTO api_keys (pseudo, key_val) VALUES (?,?)");
    $stmt->execute([$pseudo, $key]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'test_key') {
    $key = trim($_POST['key'] ?? $jsonBody['key'] ?? '');
    if (!$key) { echo json_encode(['error' => 'Clé manquante']); exit; }

    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'devstral-2512',
            'messages' => [['role'=>'user','content'=>'Say OK']],
            'max_tokens' => 5
        ]),
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $res = json_decode($response, true);
    $tokens = $res['usage']['total_tokens'] ?? 0;
    $model_status = ($code === 200) ? 'OK' : 'Erreur '.$code;

    echo json_encode(['code' => $code, 'status' => $model_status, 'tokens' => $tokens]);
    exit;
}

if ($action === 'get_key') {
    $k = $db->query("SELECT * FROM api_keys WHERE is_active=1 AND error_count < 3 ORDER BY last_used ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$k) { echo json_encode(['error' => 'Aucune cle API disponible']); exit; }
    $db->prepare("UPDATE api_keys SET last_used=CURRENT_TIMESTAMP WHERE id=?")->execute([$k['id']]);
    echo json_encode(['key' => $k['key_val'], 'id' => $k['id']]);
    exit;
}

if ($action === 'get_data') {
    $keys = $db->query("SELECT id, pseudo, substr(key_val,1,8)||'....'||substr(key_val,-4) as key_masked, is_active, error_count, last_used FROM api_keys ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $model = $db->query("SELECT * FROM model_limits WHERE model_name='devstral-2512'")->fetch(PDO::FETCH_ASSOC);
    $token_sum = $db->query("SELECT SUM(tokens_used) as total FROM token_usage")->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['keys' => $keys, 'model' => $model, 'token_total' => $token_sum['total'] ?? 0]);
    exit;
}

if ($action === 'delete_key') {
    $id = intval($_POST['id'] ?? $jsonBody['id'] ?? 0);
    if ($id) {
        $db->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'ID manquant']);
    }
    exit;
}

if ($action === 'reset_key_errors') {
    $id = intval($_POST['id'] ?? $jsonBody['id'] ?? 0);
    if ($id) {
        $db->prepare("UPDATE api_keys SET error_count = 0, is_active = 1 WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'ID manquant']);
    }
    exit;
}

if ($action === 'key_error') {
    $id = intval($_POST['id'] ?? $jsonBody['id'] ?? 0);
    if ($id) {
        markKeyError($db, $id);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'record_usage') {
    $kid    = intval($_POST['key_id'] ?? $jsonBody['key_id'] ?? 0);
    $tokens = intval($_POST['tokens'] ?? $jsonBody['tokens'] ?? 0);
    if ($kid && $tokens) {
        recordTokenUsage($db, $kid, $tokens, 'devstral-2512');
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Action inconnue: ' . $action]);
