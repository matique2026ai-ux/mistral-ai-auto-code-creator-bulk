<?php
define('DB_FILE', __DIR__ . '/marketplace.sqlite');

function getDB() {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL");
    $db->exec("PRAGMA foreign_keys=ON");

    // ==================== TABLES DE BASE ====================

    $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pseudo TEXT,
        key_val TEXT UNIQUE,
        is_active INTEGER DEFAULT 1,
        last_used DATETIME,
        error_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS model_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_name TEXT UNIQUE,
        limit_tpm INTEGER DEFAULT 50000,
        limit_rps REAL DEFAULT 1.0,
        last_tested DATETIME,
        last_status TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS token_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        api_key_id INTEGER,
        tokens_used INTEGER,
        model TEXT,
        used_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ==================== UTILISATEURS ====================

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        email TEXT,
        password_hash TEXT,
        wallet_balance REAL DEFAULT 1000.00,
        total_gains REAL DEFAULT 0,
        total_pertes REAL DEFAULT 0,
        total_trades INTEGER DEFAULT 0,
        avatar_color TEXT DEFAULT '#00e5c3',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    )");

    // ==================== PRODUITS ====================

    $db->exec("CREATE TABLE IF NOT EXISTS products_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_name TEXT,
        description TEXT,
        category TEXT,
        price REAL,
        volatility REAL DEFAULT 0.5,
        trend TEXT DEFAULT 'stable',
        image_url TEXT,
        ai_generated_data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME,
        is_active INTEGER DEFAULT 1,
        view_count INTEGER DEFAULT 0,
        purchase_count INTEGER DEFAULT 0
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cache_id INTEGER,
        name TEXT,
        description TEXT,
        base_price REAL,
        current_price REAL,
        category TEXT,
        rarity TEXT DEFAULT 'common',
        supply INTEGER DEFAULT 100,
        demand_factor REAL DEFAULT 1.0,
        volatility REAL DEFAULT 0.5,
        trend TEXT DEFAULT 'stable',
        last_price_update DATETIME,
        FOREIGN KEY (cache_id) REFERENCES products_cache(id)
    )");

    // ==================== NOUVEAU: HISTORIQUE DES PRIX ====================

    $db->exec("CREATE TABLE IF NOT EXISTS price_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER,
        price REAL,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // ==================== PORTEFEUILLE & TRANSACTIONS ====================

    $db->exec("CREATE TABLE IF NOT EXISTS user_products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        product_id INTEGER,
        purchase_price REAL,
        purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        quantity INTEGER DEFAULT 1,
        is_resale_grouped INTEGER DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        type TEXT,
        product_id INTEGER,
        amount REAL,
        quantity INTEGER,
        total_price REAL,
        gain_loss REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // ==================== GROUPES DE REVENTE ====================

    $db->exec("CREATE TABLE IF NOT EXISTS group_resales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        creator_id INTEGER,
        name TEXT,
        description TEXT,
        min_gain_percent REAL DEFAULT -10,
        max_loss_percent REAL DEFAULT -50,
        status TEXT DEFAULT 'open',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        closed_at DATETIME,
        total_value REAL DEFAULT 0,
        participants_count INTEGER DEFAULT 1,
        FOREIGN KEY (creator_id) REFERENCES users(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS group_resale_participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER,
        user_id INTEGER,
        product_ids TEXT,
        entry_value REAL,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES group_resales(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // ==================== STRATEGIES ====================

    $db->exec("CREATE TABLE IF NOT EXISTS gain_strategies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        strategy_type TEXT,
        auto_sell_gain_above REAL,
        auto_sell_loss_below REAL,
        reinvest_percent REAL DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // ==================== SESSIONS ====================

    $db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        session_token TEXT UNIQUE,
        expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // ==================== STATS MARKETPLACE ====================

    $db->exec("CREATE TABLE IF NOT EXISTS marketplace_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        stat_name TEXT UNIQUE,
        stat_value REAL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ==================== NOUVEAU: LEADERBOARD CACHE ====================

    $db->exec("CREATE TABLE IF NOT EXISTS leaderboard_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE,
        username TEXT,
        avatar_color TEXT,
        total_profit REAL DEFAULT 0,
        total_trades INTEGER DEFAULT 0,
        win_rate REAL DEFAULT 0,
        best_gain REAL DEFAULT 0,
        rank_position INTEGER DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Seed données initiales
    $db->exec("INSERT OR IGNORE INTO marketplace_stats (stat_name, stat_value) VALUES
        ('total_users', 0),
        ('total_transactions', 0),
        ('total_volume', 0),
        ('avg_product_price', 50)");

    $db->exec("INSERT OR IGNORE INTO model_limits (model_name, limit_tpm, limit_rps) VALUES ('devstral-2512', 50000, 1.0)");

    // Index pour performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_products_cache_active ON products_cache(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_products_user ON user_products(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_price_history_product ON price_history(product_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_price_history_time ON price_history(recorded_at)");

    return $db;
}

// ==================== AUTH ====================

function createUser($db, $username, $email, $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Couleur aléatoire pour l'avatar
    $colors = ['#00e5c3','#ff6b35','#a78bfa','#60a5fa','#f472b6','#34d399','#fbbf24'];
    $color = $colors[array_rand($colors)];
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, avatar_color) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hash, $color]);
    $userId = $db->lastInsertId();
    // Init leaderboard entry
    $db->prepare("INSERT OR IGNORE INTO leaderboard_cache (user_id, username, avatar_color) VALUES (?, ?, ?)")
       ->execute([$userId, $username, $color]);
    return $userId;
}

function authenticateUser($db, $username, $password) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $db->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)")
           ->execute([$user['id'], $token, $expires]);
        $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$user['id']]);
        return ['user' => $user, 'token' => $token];
    }
    return null;
}

function getUserByToken($db, $token) {
    $stmt = $db->prepare("SELECT u.* FROM users u
        JOIN user_sessions s ON u.id = s.user_id
        WHERE s.session_token = ? AND s.expires_at > datetime('now')");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==================== PRODUITS ====================

function getPreGeneratedProducts($db, $limit = 50, $category = null) {
    if ($category && $category !== 'all') {
        $stmt = $db->prepare("SELECT pc.*, p.id as product_id, p.current_price, p.volatility as prod_volatility, p.trend as prod_trend
            FROM products_cache pc
            JOIN products p ON pc.id = p.cache_id
            WHERE pc.is_active = 1 AND pc.category = ?
            ORDER BY pc.created_at DESC LIMIT ?");
        $stmt->execute([$category, $limit]);
    } else {
        $stmt = $db->prepare("SELECT pc.*, p.id as product_id, p.current_price, p.volatility as prod_volatility, p.trend as prod_trend
            FROM products_cache pc
            JOIN products p ON pc.id = p.cache_id
            WHERE pc.is_active = 1
            ORDER BY pc.created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==================== HISTORIQUE DES PRIX ====================

function recordPriceHistory($db, $productId, $price) {
    $stmt = $db->prepare("INSERT INTO price_history (product_id, price) VALUES (?, ?)");
    $stmt->execute([$productId, $price]);
}

function getPriceHistory($db, $productId, $limit = 20) {
    $stmt = $db->prepare("SELECT price, recorded_at FROM price_history
        WHERE product_id = ?
        ORDER BY recorded_at DESC LIMIT ?");
    $stmt->execute([$productId, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_reverse($rows); // Chronologique
}

// ==================== ACHAT / VENTE ====================

function buyProduct($db, $userId, $productId, $quantity = 1) {
    $product = getProductById($db, $productId);
    if (!$product) return ['error' => 'Produit inexistant'];

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalPrice = $product['current_price'] * $quantity;
    if ($user['wallet_balance'] < $totalPrice) {
        return ['error' => 'Solde insuffisant'];
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ?, total_trades = total_trades + 1 WHERE id = ?")
           ->execute([$totalPrice, $userId]);

        $db->prepare("INSERT INTO user_products (user_id, product_id, purchase_price, quantity) VALUES (?, ?, ?, ?)")
           ->execute([$userId, $productId, $product['current_price'], $quantity]);

        $db->prepare("INSERT INTO transactions (user_id, type, product_id, amount, quantity, total_price, gain_loss) VALUES (?, 'buy', ?, ?, ?, ?, 0)")
           ->execute([$userId, $productId, $product['current_price'], $quantity, $totalPrice]);

        $db->prepare("UPDATE products_cache SET purchase_count = purchase_count + ? WHERE id = ?")
           ->execute([$quantity, $product['cache_id'] ?? $productId]);

        $db->commit();

        // Refresh user
        $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $newBalance = $stmt->fetch(PDO::FETCH_ASSOC)['wallet_balance'];

        return ['success' => true, 'new_balance' => $newBalance];
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => $e->getMessage()];
    }
}

function sellProduct($db, $userId, $userProductId, $sellPrice = null) {
    $stmt = $db->prepare("SELECT up.*, p.current_price, p.name FROM user_products up
        JOIN products p ON up.product_id = p.id
        WHERE up.id = ? AND up.user_id = ?");
    $stmt->execute([$userProductId, $userId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) return ['error' => 'Objet non trouvé'];

    $finalPrice = $sellPrice ?? $item['current_price'];
    $gain = ($finalPrice - $item['purchase_price']) * $item['quantity'];
    $totalReceived = $finalPrice * $item['quantity'];

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE users SET
            wallet_balance = wallet_balance + ?,
            total_gains = CASE WHEN ? > 0 THEN total_gains + ? ELSE total_gains END,
            total_pertes = CASE WHEN ? < 0 THEN total_pertes + ABS(?) ELSE total_pertes END,
            total_trades = total_trades + 1
            WHERE id = ?")
           ->execute([$totalReceived, $gain, $gain, $gain, $gain, $userId]);

        $db->prepare("INSERT INTO transactions (user_id, type, product_id, amount, quantity, total_price, gain_loss) VALUES (?, 'sell', ?, ?, ?, ?, ?)")
           ->execute([$userId, $item['product_id'], $finalPrice, $item['quantity'], $totalReceived, $gain]);

        $db->prepare("DELETE FROM user_products WHERE id = ?")->execute([$userProductId]);

        $db->commit();

        // Update leaderboard
        updateLeaderboardEntry($db, $userId);

        $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $newBalance = $stmt->fetch(PDO::FETCH_ASSOC)['wallet_balance'];

        return ['success' => true, 'gain' => $gain, 'final_price' => $finalPrice, 'new_balance' => $newBalance];
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => $e->getMessage()];
    }
}

// Vente en masse (bulk sell)
function bulkSellProducts($db, $userId, $userProductIds) {
    $results = ['success' => true, 'total_gain' => 0, 'sold' => 0, 'errors' => []];

    foreach ($userProductIds as $upId) {
        $result = sellProduct($db, $userId, intval($upId));
        if (isset($result['success'])) {
            $results['total_gain'] += $result['gain'];
            $results['sold']++;
            if (isset($result['new_balance'])) {
                $results['new_balance'] = $result['new_balance'];
            }
        } else {
            $results['errors'][] = $result['error'] ?? 'Erreur inconnue';
        }
    }

    return $results;
}

// ==================== LEADERBOARD ====================

function updateLeaderboardEntry($db, $userId) {
    $stmt = $db->prepare("SELECT u.username, u.avatar_color, u.total_gains, u.total_pertes, u.total_trades,
        (SELECT MAX(gain_loss) FROM transactions WHERE user_id = u.id AND type = 'sell') as best_gain
        FROM users u WHERE u.id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) return;

    $totalTrades = max(1, $data['total_trades']);
    $winTrades = $db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND type = 'sell' AND gain_loss > 0");
    $winTrades->execute([$userId]);
    $wins = $winTrades->fetchColumn();
    $winRate = round(($wins / $totalTrades) * 100, 1);
    $totalProfit = $data['total_gains'] - $data['total_pertes'];

    $db->prepare("INSERT OR REPLACE INTO leaderboard_cache
        (user_id, username, avatar_color, total_profit, total_trades, win_rate, best_gain, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)")
       ->execute([$userId, $data['username'], $data['avatar_color'],
                  $totalProfit, $data['total_trades'], $winRate,
                  $data['best_gain'] ?? 0]);
}

function getLeaderboard($db, $limit = 10) {
    $stmt = $db->prepare("SELECT * FROM leaderboard_cache ORDER BY total_profit DESC LIMIT ?");
    $stmt->execute([$limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Attribuer les rangs
    foreach ($rows as $i => &$row) {
        $row['rank_position'] = $i + 1;
    }
    return $rows;
}

// ==================== MISE À JOUR DES PRIX ====================

function updateProductPrices($db) {
    $products = $db->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $p) {
        // Enregistrer le prix actuel AVANT mise à jour
        recordPriceHistory($db, $p['id'], $p['current_price']);

        // Simulation basée sur volatilité et tendance
        $volatility = $p['volatility'] ?? 0.5;
        $trend = $p['trend'] ?? 'stable';

        $baseDrift = 0;
        if ($trend === 'rising') $baseDrift = 0.005;
        elseif ($trend === 'falling') $baseDrift = -0.005;

        $randomChange = (mt_rand(-1000, 1000) / 1000) * $volatility * 0.05;
        $totalChange = $baseDrift + $randomChange;

        $newPrice = max(1, round($p['current_price'] * (1 + $totalChange), 2));

        // Déterminer la nouvelle tendance
        $diff = $newPrice - $p['current_price'];
        $newTrend = 'stable';
        if ($diff > $p['current_price'] * 0.01) $newTrend = 'rising';
        elseif ($diff < -$p['current_price'] * 0.01) $newTrend = 'falling';

        $db->prepare("UPDATE products SET current_price = ?, trend = ?, last_price_update = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$newPrice, $newTrend, $p['id']]);

        // Mise à jour cache
        $db->prepare("UPDATE products_cache SET trend = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$newTrend, $p['cache_id'] ?? $p['id']]);
    }

    // Nettoyer l'historique ancien (garder 50 points par produit max)
    $db->exec("DELETE FROM price_history WHERE id NOT IN (
        SELECT id FROM price_history ph2
        WHERE ph2.product_id = price_history.product_id
        ORDER BY recorded_at DESC LIMIT 50
    )");
}

// ==================== OVERVIEW MARCHÉ ====================

function getMarketOverview($db) {
    $topRising = $db->query("SELECT p.name, p.current_price, p.trend, p.category,
        ROUND((p.current_price - p.base_price) / p.base_price * 100, 1) as change_pct
        FROM products p WHERE p.trend = 'rising'
        ORDER BY change_pct DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

    $topFalling = $db->query("SELECT p.name, p.current_price, p.trend, p.category,
        ROUND((p.current_price - p.base_price) / p.base_price * 100, 1) as change_pct
        FROM products p WHERE p.trend = 'falling'
        ORDER BY change_pct ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

    $mostTraded = $db->query("SELECT p.name, pc.purchase_count, p.current_price, p.category
        FROM products p
        JOIN products_cache pc ON p.cache_id = pc.id
        ORDER BY pc.purchase_count DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

    $totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalVolume   = $db->query("SELECT COALESCE(SUM(total_price),0) FROM transactions")->fetchColumn();
    $avgPrice      = $db->query("SELECT COALESCE(AVG(current_price),0) FROM products")->fetchColumn();

    return [
        'top_rising'   => $topRising,
        'top_falling'  => $topFalling,
        'most_traded'  => $mostTraded,
        'total_products' => $totalProducts,
        'total_users'    => $totalUsers,
        'total_volume'   => round($totalVolume, 2),
        'avg_price'      => round($avgPrice, 2),
    ];
}

// ==================== PORTFOLIO & TRANSACTIONS ====================

function getMarketplaceStats($db) {
    return $db->query("SELECT * FROM marketplace_stats")->fetchAll(PDO::FETCH_ASSOC);
}

function getUserPortfolio($db, $userId) {
    $stmt = $db->prepare("SELECT up.*, p.name, p.current_price, p.category, p.trend
        FROM user_products up
        JOIN products p ON up.product_id = p.id
        WHERE up.user_id = ?
        ORDER BY up.purchase_date DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserTransactions($db, $userId, $limit = 30) {
    $stmt = $db->prepare("SELECT t.*, p.name as product_name
        FROM transactions t
        LEFT JOIN products p ON t.product_id = p.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== GROUPES DE REVENTE ====================

function createGroupResale($db, $userId, $name, $description, $minGain = -10, $maxLoss = -50) {
    $stmt = $db->prepare("INSERT INTO group_resales (creator_id, name, description, min_gain_percent, max_loss_percent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $name, $description, $minGain, $maxLoss]);
    return $db->lastInsertId();
}

function joinGroupResale($db, $groupId, $userId, $productIds, $entryValue) {
    $stmt = $db->prepare("INSERT INTO group_resale_participants (group_id, user_id, product_ids, entry_value) VALUES (?, ?, ?, ?)");
    $stmt->execute([$groupId, $userId, json_encode($productIds), $entryValue]);
    $db->prepare("UPDATE group_resales SET participants_count = participants_count + 1, total_value = total_value + ? WHERE id = ?")
      ->execute([$entryValue, $groupId]);
    return true;
}

// ==================== STRATEGIES ====================

function optimizeGains($db, $userId, $strategyType, $autoSellGain = 20, $autoSellLoss = -30, $reinvestPercent = 50) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO gain_strategies
        (user_id, strategy_type, auto_sell_gain_above, auto_sell_loss_below, reinvest_percent, is_active)
        VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$userId, $strategyType, $autoSellGain, $autoSellLoss, $reinvestPercent]);
    return true;
}

// ==================== CLÉS API ====================

function getNextApiKey($db) {
    $key = $db->query("SELECT * FROM api_keys WHERE is_active=1 AND error_count < 3 ORDER BY last_used ASC NULLS FIRST LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($key) {
        $db->prepare("UPDATE api_keys SET last_used=CURRENT_TIMESTAMP WHERE id=?")->execute([$key['id']]);
    }
    return $key;
}

function markKeyError($db, $keyId) {
    $db->prepare("UPDATE api_keys SET error_count = error_count + 1 WHERE id=?")->execute([$keyId]);
    $db->prepare("UPDATE api_keys SET is_active=0 WHERE id=? AND error_count >= 3")->execute([$keyId]);
}

function recordTokenUsage($db, $keyId, $tokens, $model) {
    $db->prepare("INSERT INTO token_usage (api_key_id, tokens_used, model) VALUES (?,?,?)")->execute([$keyId, $tokens, $model]);
}

?>
