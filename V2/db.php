<?php
define('DB_FILE', __DIR__ . '/marketplace.sqlite');

function getDB() {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tables existantes (compatibilité)
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

    // NOUVELLES TABLES - Marketplace & Produits
    
    // Utilisateurs avec portefeuille
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        email TEXT,
        password_hash TEXT,
        wallet_balance REAL DEFAULT 1000.00,
        total_gains REAL DEFAULT 0,
        total_pertes REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    )");

    // Cache produits générés par IA (pré-générés)
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

    // Produits disponibles pour achat
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
        last_price_update DATETIME,
        FOREIGN KEY (cache_id) REFERENCES products_cache(id)
    )");

    // Portefeuille produits utilisateur
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

    // Historique transactions
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        type TEXT,
        product_id INTEGER,
        amount REAL,
        quantity INTEGER,
        total_price REAL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // Groupes de revente (optimisation pertes/petits gains)
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

    // Participation aux groupes de revente
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

    // Stratégies d'optimisation auto
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

    // Sessions utilisateurs
    $db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        session_token TEXT UNIQUE,
        expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Stats globales marketplace
    $db->exec("CREATE TABLE IF NOT EXISTS marketplace_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        stat_name TEXT UNIQUE,
        stat_value REAL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed initial
    $db->exec("INSERT OR IGNORE INTO marketplace_stats (stat_name, stat_value) VALUES 
        ('total_users', 0),
        ('total_transactions', 0),
        ('total_volume', 0),
        ('avg_product_price', 50)");

    // Index pour performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_products_cache_active ON products_cache(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_products_user ON user_products(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id)");

    return $db;
}

// Fonctions utilitaires

function createUser($db, $username, $email, $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $hash]);
    return $db->lastInsertId();
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

function getPreGeneratedProducts($db, $limit = 50) {
    $stmt = $db->prepare("SELECT * FROM products_cache WHERE is_active = 1 ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function buyProduct($db, $userId, $productId, $quantity = 1) {
    $product = getProductById($db, $productId);
    if (!$product) return ['error' => 'Produit inexistant'];
    
    $user = $db->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$userId]);
    $user = $user->fetch(PDO::FETCH_ASSOC);
    
    $totalPrice = $product['current_price'] * $quantity;
    if ($user['wallet_balance'] < $totalPrice) {
        return ['error' => 'Solde insuffisant'];
    }
    
    $db->beginTransaction();
    try {
        // Débiter utilisateur
        $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
           ->execute([$totalPrice, $userId]);
        
        // Ajouter au portefeuille
        $db->prepare("INSERT INTO user_products (user_id, product_id, purchase_price, quantity) 
                     VALUES (?, ?, ?, ?)")
           ->execute([$userId, $productId, $product['current_price'], $quantity]);
        
        // Enregistrer transaction
        $db->prepare("INSERT INTO transactions (user_id, type, product_id, amount, quantity, total_price) 
                     VALUES (?, 'buy', ?, ?, ?, ?)")
           ->execute([$userId, $productId, $product['current_price'], $quantity, $totalPrice]);
        
        // Mettre à jour stats produit
        $db->prepare("UPDATE products_cache SET purchase_count = purchase_count + ? WHERE id = ?")
           ->execute([$quantity, $product['cache_id'] ?? $productId]);
        
        $db->commit();
        return ['success' => true, 'new_balance' => $user['wallet_balance'] - $totalPrice];
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => $e->getMessage()];
    }
}

function sellProduct($db, $userId, $userProductId, $sellPrice = null) {
    $stmt = $db->prepare("SELECT up.*, p.current_price FROM user_products up 
        JOIN products p ON up.product_id = p.id 
        WHERE up.id = ? AND up.user_id = ?");
    $stmt->execute([$userProductId, $userId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) return ['error' => 'Objet non trouvé'];
    
    $finalPrice = $sellPrice ?? $item['current_price'];
    $gain = ($finalPrice - $item['purchase_price']) * $item['quantity'];
    
    $db->beginTransaction();
    try {
        // Créditer utilisateur
        $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, 
                     total_gains = CASE WHEN ? > 0 THEN total_gains + ? ELSE total_gains END,
                     total_pertes = CASE WHEN ? < 0 THEN total_pertes + ? ELSE total_pertes END
                     WHERE id = ?")
           ->execute([$finalPrice * $item['quantity'], $gain, $gain, -$gain, -$gain, $userId]);
        
        // Enregistrer transaction
        $db->prepare("INSERT INTO transactions (user_id, type, product_id, amount, quantity, total_price) 
                     VALUES (?, 'sell', ?, ?, ?, ?)")
           ->execute([$userId, $item['product_id'], $finalPrice, $item['quantity'], $finalPrice * $item['quantity']]);
        
        // Supprimer du portefeuille
        $db->prepare("DELETE FROM user_products WHERE id = ?")->execute([$userProductId]);
        
        $db->commit();
        return ['success' => true, 'gain' => $gain, 'final_price' => $finalPrice];
    } catch (Exception $e) {
        $db->rollBack();
        return ['error' => $e->getMessage()];
    }
}

function createGroupResale($db, $userId, $name, $description, $minGain = -10, $maxLoss = -50) {
    $stmt = $db->prepare("INSERT INTO group_resales (creator_id, name, description, min_gain_percent, max_loss_percent) 
                         VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $name, $description, $minGain, $maxLoss]);
    return $db->lastInsertId();
}

function joinGroupResale($db, $groupId, $userId, $productIds, $entryValue) {
    $stmt = $db->prepare("INSERT INTO group_resale_participants (group_id, user_id, product_ids, entry_value) 
                         VALUES (?, ?, ?, ?)");
    $stmt->execute([$groupId, $userId, json_encode($productIds), $entryValue]);
    
    $db->prepare("UPDATE group_resales SET participants_count = participants_count + 1, 
                 total_value = total_value + ? WHERE id = ?")
      ->execute([$entryValue, $groupId]);
    
    return true;
}

function optimizeGains($db, $userId, $strategyType, $autoSellGain = 20, $autoSellLoss = -30, $reinvestPercent = 50) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO gain_strategies 
                         (user_id, strategy_type, auto_sell_gain_above, auto_sell_loss_below, reinvest_percent, is_active) 
                         VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$userId, $strategyType, $autoSellGain, $autoSellLoss, $reinvestPercent]);
    return true;
}

function updateProductPrices($db) {
    // Simulation variation prix basée sur offre/demande
    $products = $db->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $p) {
        $variation = (rand(0, 100) - 50) / 100 * $p['volatility'];
        $newPrice = max(1, $p['current_price'] * (1 + $variation / 100));
        
        $db->prepare("UPDATE products SET current_price = ?, last_price_update = CURRENT_TIMESTAMP 
                     WHERE id = ?")->execute([$newPrice, $p['id']]);
    }
}

function getMarketplaceStats($db) {
    return $db->query("SELECT * FROM marketplace_stats")->fetchAll(PDO::FETCH_ASSOC);
}

function getUserPortfolio($db, $userId) {
    $stmt = $db->prepare("SELECT up.*, p.name, p.current_price, p.category 
        FROM user_products up 
        JOIN products p ON up.product_id = p.id 
        WHERE up.user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserTransactions($db, $userId, $limit = 20) {
    $stmt = $db->prepare("SELECT t.*, p.name as product_name 
        FROM transactions t 
        LEFT JOIN products p ON t.product_id = p.id 
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
