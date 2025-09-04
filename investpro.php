
<?php
// InvestPro - Complete Investment System
// Single File PHP Application with Auto-Setup

session_start();

// Database Configuration
define('DB_HOST', 'sql211.infinityfree.com');
define('DB_NAME', 'if0_39769389_investproph');
define('DB_USER', 'if0_39769389');
define('DB_PASS', 'Olanreynaldo271');
define('CRON_SECRET', 'SECRET_CRON_KEY_2024');

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Auto-create tables on first run
function createTables($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            balance DECIMAL(15,2) DEFAULT 0.00,
            pin VARCHAR(255),
            referral_code VARCHAR(20) UNIQUE,
            referred_by INT,
            profile_pic VARCHAR(255),
            is_admin BOOLEAN DEFAULT FALSE,
            is_banned BOOLEAN DEFAULT FALSE,
            two_fa_enabled BOOLEAN DEFAULT FALSE,
            two_fa_secret VARCHAR(32),
            signup_bonus_claimed BOOLEAN DEFAULT FALSE,
            last_roi_claim DATETIME,
            last_checkin DATETIME,
            checkin_streak INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_referral (referral_code),
            INDEX idx_referred_by (referred_by)
        )",
        
        "CREATE TABLE IF NOT EXISTS investment_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(15,2) NOT NULL,
            daily_return DECIMAL(15,2) NOT NULL,
            cycle_days INT NOT NULL,
            total_return DECIMAL(15,2) NOT NULL,
            is_vip BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS user_investments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            daily_return DECIMAL(15,2) NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            total_earned DECIMAL(15,2) DEFAULT 0.00,
            last_roi_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (plan_id) REFERENCES investment_plans(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS deposits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            payment_method VARCHAR(50),
            proof_image VARCHAR(255),
            status ENUM('pending', 'processing', 'approved', 'failed') DEFAULT 'pending',
            admin_notes TEXT,
            processed_by INT,
            processed_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            gross_amount DECIMAL(15,2) NOT NULL,
            fee_amount DECIMAL(15,2) NOT NULL,
            net_amount DECIMAL(15,2) NOT NULL,
            bank_details TEXT,
            status ENUM('pending', 'processing', 'bank_processing', 'approved', 'failed') DEFAULT 'pending',
            admin_notes TEXT,
            processed_by INT,
            processed_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('deposit', 'withdrawal', 'roi_credit', 'referral_L1', 'referral_L2', 'referral_L3', 'checkin', 'fee_withdrawal', 'signup_bonus', 'principal_return') NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            description TEXT,
            reference_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX idx_user_type (user_id, type),
            INDEX idx_created (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS gift_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            usage_limit INT DEFAULT 1,
            used_count INT DEFAULT 0,
            expires_at DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS payment_methods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type ENUM('gcash', 'paymaya', 'bank', 'crypto') NOT NULL,
            account_number VARCHAR(255),
            account_name VARCHAR(255),
            qr_code_url TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS gift_code_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (code_id) REFERENCES gift_codes(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE KEY unique_user_code (code_id, user_id)
        )",
        
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            device_info VARCHAR(255),
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempts INT DEFAULT 1,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            blocked_until DATETIME,
            INDEX idx_ip_action (ip_address, action)
        )"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    
    // Insert default investment plans with updated values
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM investment_plans");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $plans = [
            ['Starter Plan', 500, 25, 30, 750, 0],
            ['Basic Plan', 1000, 60, 30, 1800, 0],
            ['Premium Plan', 5000, 350, 30, 10500, 0],
            ['VIP Plan', 10000, 800, 30, 24000, 1],
            ['Elite Plan', 25000, 2250, 30, 67500, 1]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO investment_plans (name, price, daily_return, cycle_days, total_return, is_vip) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($plans as $plan) {
            $stmt->execute($plan);
        }
    }
    
    // Create default admin user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, fullname, is_admin, referral_code) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute(['admin', 'admin@investpro.com', $adminPassword, 'System Administrator', 'ADMIN001']);
    }
}

createTables($pdo);

// Utility Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateReferralCode() {
    return 'REF' . strtoupper(substr(md5(uniqid()), 0, 7));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function checkRateLimit($pdo, $action, $limit = 5, $window = 300) {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $pdo->prepare("SELECT attempts, blocked_until FROM rate_limits WHERE ip_address = ? AND action = ?");
    $stmt->execute([$ip, $action]);
    $record = $stmt->fetch();
    
    if ($record && $record['blocked_until'] && strtotime($record['blocked_until']) > time()) {
        return false;
    }
    
    if ($record && (time() - strtotime($record['last_attempt'])) < $window) {
        if ($record['attempts'] >= $limit) {
            $stmt = $pdo->prepare("UPDATE rate_limits SET blocked_until = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE ip_address = ? AND action = ?");
            $stmt->execute([$ip, $action]);
            return false;
        }
        
        $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1, last_attempt = NOW() WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, action, attempts) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE attempts = 1, last_attempt = NOW(), blocked_until = NULL");
        $stmt->execute([$ip, $action]);
    }
    
    return true;
}

function addTransaction($pdo, $userId, $type, $amount, $description = '', $referenceId = null) {
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, reference_id) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $amount, $description, $referenceId]);
}

function updateUserBalance($pdo, $userId, $amount, $operation = 'add') {
    $operator = $operation === 'add' ? '+' : '-';
    $stmt = $pdo->prepare("UPDATE users SET balance = balance $operator ? WHERE id = ?");
    return $stmt->execute([$amount, $userId]);
}

// Router
$action = $_GET['action'] ?? 'landing';

// Handle AJAX/API requests
if (in_array($action, ['ticker', 'tts_pref', 'theme_next', 'cron_daily_earnings', 'chatbot'])) {
    handleAPIRequest($pdo, $action);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($pdo);
}

// API Handlers
function handleAPIRequest($pdo, $action) {
    switch ($action) {
        case 'ticker':
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            
            while (true) {
                $stmt = $pdo->prepare("SELECT t.*, u.fullname FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10");
                $stmt->execute();
                $transactions = $stmt->fetchAll();
                
                echo "data: " . json_encode($transactions) . "\n\n";
                flush();
                sleep(10);
            }
            break;
            
        case 'tts_pref':
            if (isLoggedIn()) {
                $_SESSION['tts_enabled'] = !($_SESSION['tts_enabled'] ?? true);
                echo json_encode(['success' => true, 'enabled' => $_SESSION['tts_enabled']]);
            }
            break;
            
        case 'theme_next':
            $themes = ['primary', 'success', 'info', 'warning', 'danger'];
            $_SESSION['current_theme'] = $themes[array_rand($themes)];
            echo json_encode(['theme' => $_SESSION['current_theme']]);
            break;
            
        case 'cron_daily_earnings':
            if (($_GET['key'] ?? '') !== CRON_SECRET) {
                http_response_code(403);
                exit;
            }
            
            processDailyROI($pdo);
            echo json_encode(['success' => true, 'processed' => true]);
            break;
            
        case 'chatbot':
            header('Content-Type: application/json');
            $message = $_POST['message'] ?? '';
            $response = handleChatbot($message);
            echo json_encode(['response' => $response]);
            break;
    }
}

// Chatbot Handler
function handleChatbot($message) {
    $message = strtolower(trim($message));
    
    // Greeting responses
    if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening|kumusta|kamusta)\b/', $message)) {
        $greetings = [
            "👋 Hello! Welcome to InvestPro! I'm here to help you with your investment journey. What would you like to know?",
            "🌟 Hi there! Ready to start earning with InvestPro? Ask me anything about our investment plans!",
            "💫 Hello! I'm your InvestPro assistant. How can I help you grow your money today?"
        ];
        return $greetings[array_rand($greetings)];
    }
    
    // Investment-related responses
    if (preg_match('/\b(invest|plan|investment|pano|paano|how)\b/', $message)) {
        return "🎯 **InvestPro Investment Plans:**\n\n💎 **Starter Plan** - ₱500 (₱25 daily)\n💎 **Basic Plan** - ₱1,000 (₱60 daily)\n💎 **Premium Plan** - ₱5,000 (₱350 daily)\n💎 **VIP Plan** - ₱10,000 (₱800 daily)\n💎 **Elite Plan** - ₱25,000 (₱2,250 daily)\n\nAll plans run for 30 days with guaranteed daily returns! 🚀";
    }
    
    if (preg_match('/\b(roi|return|profit|kita|kikita)\b/', $message)) {
        return "💰 **Daily ROI System:**\n\n✅ Automatic crediting every 24 hours\n✅ No manual claiming needed\n✅ Transparent transaction history\n✅ Guaranteed returns in PHP\n✅ Real-time balance updates\n\n📊 Track your earnings live on your dashboard!";
    }
    
    if (preg_match('/\b(withdraw|payout|kuha|withdrawal)\b/', $message)) {
        return "💳 **Withdrawal Process:**\n\n📋 **Requirements:**\n• Minimum: ₱100\n• 10% processing fee\n• PIN + OTP verification\n\n⏱️ **Processing Time:**\nPending → Processing → Bank Processing → Approved\n\n🏦 Supports all major banks and e-wallets!";
    }
    
    if (preg_match('/\b(deposit|recharge|load|pano mag deposit)\b/', $message)) {
        return "📱 **Deposit Methods:**\n\n💳 **Available Options:**\n• GCash\n• PayMaya  \n• Bank Transfer\n• Crypto (coming soon)\n\n📸 **Process:**\n1. Choose amount (min ₱100)\n2. Send payment\n3. Upload proof\n4. Wait for admin approval\n\n⚡ Instant activation after approval!";
    }
    
    if (preg_match('/\b(referral|team|invite|refer)\b/', $message)) {
        return "👥 **3-Level Referral System:**\n\n💰 **Commission Structure:**\n🥇 Level 1: 15% (Direct referrals)\n🥈 Level 2: 5% (Their referrals)\n🥉 Level 3: 2% (Sub-referrals)\n\n🎯 **Benefits:**\n• Lifetime commissions\n• QR code sharing\n• Real-time tracking\n• Instant payouts\n\nBuild your team and earn passive income! 🚀";
    }
    
    if (preg_match('/\b(security|safe|secure|ligtas)\b/', $message)) {
        return "🔒 **Military-Grade Security:**\n\n🛡️ **Protection Features:**\n• PIN + OTP verification\n• Session management\n• Device tracking\n• Rate limiting\n• Encrypted data\n• 2FA support\n\n✅ Your investments are 100% protected!\n🏆 Trusted by thousands of investors!";
    }
    
    if (preg_match('/\b(bonus|200|welcome|gift)\b/', $message)) {
        return "🎁 **Welcome Bonus Program:**\n\n💰 **₱200 Instant Bonus** for new members!\n\n📋 **How to unlock:**\n1. Complete registration\n2. Make first deposit\n3. Start first investment\n4. Bonus becomes withdrawable!\n\n🎯 **Daily Check-in:** Up to ₱100 daily rewards!\n🎫 **Gift Codes:** Redeem special codes for extra bonuses!";
    }
    
    if (preg_match('/\b(support|help|problem|issue|tulong)\b/', $message)) {
        return "📞 **24/7 Support Available:**\n\n💬 **Contact Options:**\n• Telegram: @InvestProPH_Support\n• Live Chat: Right here!\n• Email: support@investpro.ph\n\n⚡ **Quick Help:**\n• Account issues\n• Payment problems\n• Investment questions\n• Technical support\n\nOur team responds within minutes! 🚀";
    }
    
    if (preg_match('/\b(minimum|start|magkano|pano magsimula)\b/', $message)) {
        return "💵 **Getting Started:**\n\n🎯 **Minimum Requirements:**\n• Investment: ₱500 (Starter Plan)\n• Deposit: ₱100\n• Withdrawal: ₱100\n\n📈 **Perfect for beginners!**\nTest the platform with small amounts first.\n\n🚀 **Ready to start?** Register now and get ₱200 bonus!";
    }
    
    if (preg_match('/\b(gift code|redeem|code|promo)\b/', $message)) {
        return "🎫 **Gift Code System:**\n\n✨ **How to redeem:**\n1. Go to Profile → Redeem Gift Code\n2. Enter your code\n3. Instant credit to balance!\n\n⚠️ **Important:**\n• Codes may have usage limits\n• Check expiration dates\n• One-time use per user\n\n🎁 Follow our social media for exclusive codes!";
    }
    
    if (preg_match('/\b(admin|contact admin|may problema)\b/', $message)) {
        return "👨‍💼 **Need Admin Assistance?**\n\n📞 **Direct Contact:**\n• Telegram: @InvestProPH_Support\n• Priority support for urgent issues\n\n⚡ **Admin handles:**\n• Deposit approvals\n• Withdrawal processing\n• Account verification\n• Technical issues\n\nResponse time: 5-15 minutes! 🚀";
    }
    
    // Random encouraging responses
    $encouragements = [
        "💪 Keep investing and growing your wealth with InvestPro! Your financial freedom is just a click away!",
        "🌟 Smart choice choosing InvestPro! Thousands of investors are already earning daily. Join them now!",
        "🚀 Ready to multiply your money? InvestPro's proven system delivers consistent returns every day!",
        "💎 Your investment journey starts here! InvestPro - where your money works harder for you!",
        "🎯 Building wealth has never been easier! Start small, dream big with InvestPro!"
    ];
    
    // Default response with random encouragement
    return "👋 Hi! I'm your InvestPro Assistant! 🤖\n\n💬 **Ask me about:**\n• Investment plans & returns\n• Deposits & withdrawals  \n• Referral system\n• Security features\n• Bonuses & gift codes\n• Account support\n\n" . $encouragements[array_rand($encouragements)] . "\n\n📞 **Need human help?** Contact @InvestProPH_Support";
}

// Process Daily ROI
function processDailyROI($pdo) {
    $stmt = $pdo->prepare("
        SELECT ui.*, u.id as user_id 
        FROM user_investments ui 
        JOIN users u ON ui.user_id = u.id 
        WHERE ui.status = 'active' 
        AND ui.end_date > NOW() 
        AND (ui.last_roi_date IS NULL OR ui.last_roi_date < DATE_SUB(NOW(), INTERVAL 24 HOUR))
    ");
    $stmt->execute();
    $investments = $stmt->fetchAll();
    
    foreach ($investments as $investment) {
        // Credit daily ROI
        updateUserBalance($pdo, $investment['user_id'], $investment['daily_return']);
        addTransaction($pdo, $investment['user_id'], 'roi_credit', $investment['daily_return'], 'Daily ROI from ' . $investment['name']);
        
        // Update last ROI date
        $stmt = $pdo->prepare("UPDATE user_investments SET last_roi_date = NOW(), total_earned = total_earned + ? WHERE id = ?");
        $stmt->execute([$investment['daily_return'], $investment['id']]);
        
        // Check if investment completed (30 days)
        $daysElapsed = floor((time() - strtotime($investment['start_date'])) / 86400);
        if ($daysElapsed >= 30) {
            // Complete investment and return principal
            $stmt = $pdo->prepare("UPDATE user_investments SET status = 'completed' WHERE id = ?");
            $stmt->execute([$investment['id']]);
            
            // Return principal to user balance
            updateUserBalance($pdo, $investment['user_id'], $investment['amount']);
            addTransaction($pdo, $investment['user_id'], 'principal_return', $investment['amount'], 'Principal return from completed investment');
        }
    }
}

// Handle POST requests
function handlePostRequest($pdo) {
    $action = $_POST['action'] ?? '';
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    switch ($action) {
        case 'register':
            handleRegister($pdo);
            break;
        case 'login':
            handleLogin($pdo);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'deposit':
            handleDeposit($pdo);
            break;
        case 'withdraw':
            handleWithdraw($pdo);
            break;
        case 'invest':
            handleInvest($pdo);
            break;
        case 'checkin':
            handleCheckin($pdo);
            break;
        case 'admin_approve_deposit':
            handleAdminApproveDeposit($pdo);
            break;
        case 'admin_approve_withdrawal':
            handleAdminApproveWithdrawal($pdo);
            break;
        case 'redeem_gift_code':
            handleRedeemGiftCode($pdo);
            break;
        case 'admin_create_gift_code':
            handleAdminCreateGiftCode($pdo);
            break;
        case 'admin_create_payment_method':
            handleAdminCreatePaymentMethod($pdo);
            break;
        case 'admin_create_plan':
            handleAdminCreatePlan($pdo);
            break;
    }
}

function handleRegister($pdo) {
    if (!checkRateLimit($pdo, 'register', 3, 3600)) {
        $_SESSION['error'] = 'Too many registration attempts. Please try again later.';
        return;
    }
    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $referralCode = trim($_POST['referral_code'] ?? '');
    
    // Validation
    if (strlen($username) < 3 || strlen($password) < 6) {
        $_SESSION['error'] = 'Username must be at least 3 characters and password at least 6 characters.';
        return;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Username or email already exists.';
        return;
    }
    
    // Check referral code
    $referredBy = null;
    if ($referralCode) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$referralCode]);
        $referrer = $stmt->fetch();
        if ($referrer) {
            $referredBy = $referrer['id'];
        }
    }
    
    // Create user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userReferralCode = generateReferralCode();
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, fullname, phone, referred_by, referral_code, balance) VALUES (?, ?, ?, ?, ?, ?, ?, 200.00)");
    
    if ($stmt->execute([$username, $email, $hashedPassword, $fullname, $phone, $referredBy, $userReferralCode])) {
        $userId = $pdo->lastInsertId();
        
        // Add signup bonus transaction
        addTransaction($pdo, $userId, 'signup_bonus', 200.00, 'Welcome bonus');
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['is_admin'] = false;
        $_SESSION['success'] = 'Registration successful! Welcome bonus of ₱200 added to your account.';
        
        session_regenerate_id(true);
        header('Location: ?action=dashboard');
        exit;
    } else {
        $_SESSION['error'] = 'Registration failed. Please try again.';
    }
}

function handleLogin($pdo) {
    if (!checkRateLimit($pdo, 'login', 5, 900)) {
        $_SESSION['error'] = 'Too many login attempts. Please try again later.';
        return;
    }
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_banned = 0");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        session_regenerate_id(true);
        
        // Log session
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], session_id(), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
        
        // Redirect based on user type
        if ($user['is_admin']) {
            header('Location: ?action=admin');
        } else {
            header('Location: ?action=dashboard');
        }
        exit;
    } else {
        $_SESSION['error'] = 'Invalid credentials or account is banned.';
    }
}

function handleLogout() {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

function handleDeposit($pdo) {
    if (!isLoggedIn()) return;
    
    $amount = floatval($_POST['amount']);
    $paymentMethod = trim($_POST['payment_method']);
    
    if ($amount < 100) {
        $_SESSION['error'] = 'Minimum deposit amount is ₱100.';
        return;
    }
    
    // Handle file upload
    $proofImage = null;
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $fileName = uniqid() . '_' . basename($_FILES['proof']['name']);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['proof']['tmp_name'], $uploadPath)) {
            $proofImage = $uploadPath;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, payment_method, proof_image) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$_SESSION['user_id'], $amount, $paymentMethod, $proofImage])) {
        $_SESSION['success'] = 'Deposit request submitted successfully. Please wait for admin approval.';
    } else {
        $_SESSION['error'] = 'Failed to submit deposit request.';
    }
}

function handleWithdraw($pdo) {
    if (!isLoggedIn()) return;
    
    $amount = floatval($_POST['amount']);
    $pin = $_POST['pin'];
    $bankDetails = trim($_POST['bank_details']);
    
    // Get user
    $user = getCurrentUser($pdo);
    if (!$user || !password_verify($pin, $user['pin'])) {
        $_SESSION['error'] = 'Invalid PIN.';
        return;
    }
    
    if ($amount < 100) {
        $_SESSION['error'] = 'Minimum withdrawal amount is ₱100.';
        return;
    }
    
    $feeAmount = $amount * 0.10; // 10% fee
    $netAmount = $amount - $feeAmount;
    
    if ($user['balance'] < $amount) {
        $_SESSION['error'] = 'Insufficient balance.';
        return;
    }
    
    // Deduct from balance
    updateUserBalance($pdo, $_SESSION['user_id'], $amount, 'subtract');
    
    // Create withdrawal record
    $stmt = $pdo->prepare("INSERT INTO withdrawals (user_id, gross_amount, fee_amount, net_amount, bank_details) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$_SESSION['user_id'], $amount, $feeAmount, $netAmount, $bankDetails])) {
        $withdrawalId = $pdo->lastInsertId();
        
        // Add transactions
        addTransaction($pdo, $_SESSION['user_id'], 'withdrawal', -$amount, 'Withdrawal request', $withdrawalId);
        addTransaction($pdo, $_SESSION['user_id'], 'fee_withdrawal', -$feeAmount, 'Withdrawal fee (10%)', $withdrawalId);
        
        $_SESSION['success'] = "Withdrawal request submitted. Fee: ₱{$feeAmount}, Net amount: ₱{$netAmount}";
    } else {
        // Refund balance on failure
        updateUserBalance($pdo, $_SESSION['user_id'], $amount);
        $_SESSION['error'] = 'Failed to submit withdrawal request.';
    }
}

function handleInvest($pdo) {
    if (!isLoggedIn()) return;
    
    $planId = intval($_POST['plan_id']);
    $pin = $_POST['pin'];
    
    // Get user and plan
    $user = getCurrentUser($pdo);
    $stmt = $pdo->prepare("SELECT * FROM investment_plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    
    if (!$user || !$plan || !password_verify($pin, $user['pin'])) {
        $_SESSION['error'] = 'Invalid PIN or plan not found.';
        return;
    }
    
    if ($user['balance'] < $plan['price']) {
        $_SESSION['error'] = 'Insufficient balance.';
        return;
    }
    
    // Check if user has approved deposit (for bonus withdrawal eligibility)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$_SESSION['user_id']]);
    $hasApprovedDeposit = $stmt->fetchColumn() > 0;
    
    // Deduct investment amount
    updateUserBalance($pdo, $_SESSION['user_id'], $plan['price'], 'subtract');
    
    // Create investment (30 days fixed)
    $endDate = date('Y-m-d H:i:s', strtotime("+30 days"));
    $stmt = $pdo->prepare("INSERT INTO user_investments (user_id, plan_id, amount, daily_return, start_date, end_date) VALUES (?, ?, ?, ?, NOW(), ?)");
    
    if ($stmt->execute([$_SESSION['user_id'], $planId, $plan['price'], $plan['daily_return'], $endDate])) {
        $investmentId = $pdo->lastInsertId();
        addTransaction($pdo, $_SESSION['user_id'], 'investment', -$plan['price'], "Investment in {$plan['name']}", $investmentId);
        
        // Mark signup bonus as withdrawable if this is first investment with approved deposit
        if ($hasApprovedDeposit) {
            $stmt = $pdo->prepare("UPDATE users SET signup_bonus_claimed = 1 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        $_SESSION['success'] = '🎉 Investment successfully done ✔️🙌';
    } else {
        // Refund on failure
        updateUserBalance($pdo, $_SESSION['user_id'], $plan['price']);
        $_SESSION['error'] = 'Failed to create investment.';
    }
}

function handleCheckin($pdo) {
    if (!isLoggedIn()) return;
    
    $user = getCurrentUser($pdo);
    
    // Check if already checked in today
    if ($user['last_checkin'] && date('Y-m-d', strtotime($user['last_checkin'])) === date('Y-m-d')) {
        $_SESSION['error'] = 'You have already checked in today.';
        return;
    }
    
    // Calculate reward (₱1-₱50, max ₱100/day)
    $baseReward = rand(1, 50);
    $streakBonus = min($user['checkin_streak'] * 2, 50);
    $totalReward = min($baseReward + $streakBonus, 100);
    
    // Update user
    $newStreak = (date('Y-m-d', strtotime($user['last_checkin'])) === date('Y-m-d', strtotime('-1 day'))) 
        ? $user['checkin_streak'] + 1 : 1;
    
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ?, last_checkin = NOW(), checkin_streak = ? WHERE id = ?");
    if ($stmt->execute([$totalReward, $newStreak, $_SESSION['user_id']])) {
        addTransaction($pdo, $_SESSION['user_id'], 'checkin', $totalReward, "Daily check-in reward (Streak: {$newStreak})");
        $_SESSION['success'] = "Check-in successful! Earned ₱{$totalReward} (Streak: {$newStreak})";
    }
}

// Admin functions
function handleAdminApproveDeposit($pdo) {
    if (!isAdmin()) return;
    
    $depositId = intval($_POST['deposit_id']);
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ?");
    $stmt->execute([$depositId]);
    $deposit = $stmt->fetch();
    
    if (!$deposit) return;
    
    if ($status === 'approved') {
        // Credit user balance
        updateUserBalance($pdo, $deposit['user_id'], $deposit['amount']);
        addTransaction($pdo, $deposit['user_id'], 'deposit', $deposit['amount'], 'Deposit approved', $depositId);
        
        // Process referral commissions
        processReferralCommissions($pdo, $deposit['user_id'], $deposit['amount']);
    }
    
    $stmt = $pdo->prepare("UPDATE deposits SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $notes, $_SESSION['user_id'], $depositId]);
    
    $_SESSION['success'] = "Deposit {$status} successfully.";
}

function handleAdminApproveWithdrawal($pdo) {
    if (!isAdmin()) return;
    
    $withdrawalId = intval($_POST['withdrawal_id']);
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE withdrawals SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $notes, $_SESSION['user_id'], $withdrawalId]);
    
    $_SESSION['success'] = "Withdrawal {$status} successfully.";
}

function handleRedeemGiftCode($pdo) {
    if (!isLoggedIn()) return;
    
    $code = trim($_POST['gift_code']);
    
    // Check if code exists and is valid
    $stmt = $pdo->prepare("SELECT * FROM gift_codes WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $giftCode = $stmt->fetch();
    
    if (!$giftCode) {
        $_SESSION['error'] = '❌ Invalid gift code!';
        return;
    }
    
    // Check if expired
    if ($giftCode['expires_at'] && strtotime($giftCode['expires_at']) < time()) {
        $_SESSION['error'] = '⏰ Gift code has expired!';
        return;
    }
    
    // Check usage limit
    if ($giftCode['used_count'] >= $giftCode['usage_limit']) {
        $_SESSION['error'] = '🚫 Gift code usage limit reached!';
        return;
    }
    
    // Check if user already used this code
    $stmt = $pdo->prepare("SELECT id FROM gift_code_usage WHERE code_id = ? AND user_id = ?");
    $stmt->execute([$giftCode['id'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = '⚠️ You have already used this gift code!';
        return;
    }
    
    // Redeem the code
    $pdo->beginTransaction();
    try {
        // Update user balance
        updateUserBalance($pdo, $_SESSION['user_id'], $giftCode['amount']);
        
        // Record usage
        $stmt = $pdo->prepare("INSERT INTO gift_code_usage (code_id, user_id, amount) VALUES (?, ?, ?)");
        $stmt->execute([$giftCode['id'], $_SESSION['user_id'], $giftCode['amount']]);
        
        // Update usage count
        $stmt = $pdo->prepare("UPDATE gift_codes SET used_count = used_count + 1 WHERE id = ?");
        $stmt->execute([$giftCode['id']]);
        
        // Add transaction
        addTransaction($pdo, $_SESSION['user_id'], 'gift_code', $giftCode['amount'], "Gift code redeemed: {$code}");
        
        $pdo->commit();
        $_SESSION['success'] = "🎉 Gift code redeemed successfully! ₱{$giftCode['amount']} added to your balance!";
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error'] = 'Failed to redeem gift code. Please try again.';
    }
}

function handleAdminCreateGiftCode($pdo) {
    if (!isAdmin()) return;
    
    $code = trim($_POST['code']);
    $amount = floatval($_POST['amount']);
    $usageLimit = intval($_POST['usage_limit']);
    $expiresAt = $_POST['expires_at'] ? $_POST['expires_at'] : null;
    
    $stmt = $pdo->prepare("INSERT INTO gift_codes (code, amount, usage_limit, expires_at, created_by) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$code, $amount, $usageLimit, $expiresAt, $_SESSION['user_id']])) {
        $_SESSION['success'] = "Gift code created successfully!";
    } else {
        $_SESSION['error'] = "Failed to create gift code. Code might already exist.";
    }
}

function handleAdminCreatePaymentMethod($pdo) {
    if (!isAdmin()) return;
    
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $accountNumber = trim($_POST['account_number']);
    $accountName = trim($_POST['account_name']);
    $qrCodeUrl = trim($_POST['qr_code_url']);
    
    $stmt = $pdo->prepare("INSERT INTO payment_methods (name, type, account_number, account_name, qr_code_url) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $type, $accountNumber, $accountName, $qrCodeUrl])) {
        $_SESSION['success'] = "Payment method added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add payment method.";
    }
}

function handleAdminCreatePlan($pdo) {
    if (!isAdmin()) return;
    
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $dailyReturn = floatval($_POST['daily_return']);
    $cycleDays = intval($_POST['cycle_days']);
    $isVip = isset($_POST['is_vip']) ? 1 : 0;
    
    $totalReturn = $dailyReturn * $cycleDays;
    
    $stmt = $pdo->prepare("INSERT INTO investment_plans (name, price, daily_return, cycle_days, total_return, is_vip) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $price, $dailyReturn, $cycleDays, $totalReturn, $isVip])) {
        $_SESSION['success'] = "Investment plan created successfully!";
    } else {
        $_SESSION['error'] = "Failed to create investment plan.";
    }
}

function processReferralCommissions($pdo, $userId, $amount) {
    $commissions = [15, 5, 2]; // L1: 15%, L2: 5%, L3: 2%
    
    $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    
    $level = 1;
    $referrerId = $currentUser['referred_by'] ?? null;
    
    while ($referrerId && $level <= 3) {
        $commission = ($amount * $commissions[$level - 1]) / 100;
        
        updateUserBalance($pdo, $referrerId, $commission);
        addTransaction($pdo, $referrerId, "referral_L{$level}", $commission, "L{$level} referral commission from deposit");
        
        // Get next level referrer
        $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->execute([$referrerId]);
        $nextReferrer = $stmt->fetch();
        $referrerId = $nextReferrer['referred_by'] ?? null;
        $level++;
    }
}

// Page rendering
function renderPage($pdo) {
    global $action;
    
    if (!isLoggedIn() && !in_array($action, ['login', 'register', 'landing'])) {
        $action = 'landing';
    }
    
    if (isLoggedIn() && in_array($action, ['login', 'register', 'landing'])) {
        $action = 'dashboard';
    }
    
    $currentTheme = $_SESSION['current_theme'] ?? 'primary';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>InvestPro - Smart Investment Platform</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            :root {
                --primary: #6366f1;
                --secondary: #8b5cf6;
                --success: #10b981;
                --danger: #ef4444;
                --warning: #f59e0b;
                --info: #06b6d4;
                --dark: #1f2937;
                --light: #f8fafc;
                --accent: var(--primary);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
                min-height: 100vh;
                font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
                overflow-x: hidden;
                position: relative;
            }
            
            body::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: 
                    radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                    radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                    radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
                z-index: -1;
                animation: backgroundShift 20s ease-in-out infinite;
            }
            
            @keyframes backgroundShift {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.8; }
            }
            
            .main-container {
                padding-bottom: 100px;
                position: relative;
                z-index: 1;
            }
            
            .card {
                border: none;
                border-radius: 20px;
                box-shadow: 
                    0 20px 40px rgba(0,0,0,0.1),
                    0 0 0 1px rgba(255,255,255,0.2);
                backdrop-filter: blur(20px);
                background: rgba(255,255,255,0.95);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                overflow: hidden;
                position: relative;
            }
            
            .card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8), transparent);
            }
            
            .card:hover {
                transform: translateY(-8px);
                box-shadow: 
                    0 30px 60px rgba(0,0,0,0.15),
                    0 0 0 1px rgba(255,255,255,0.3);
            }
            
            .btn-accent {
                background: linear-gradient(135deg, var(--accent), var(--secondary));
                border: none;
                border-radius: 50px;
                padding: 14px 32px;
                font-weight: 600;
                font-size: 14px;
                letter-spacing: 0.5px;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                color: white;
                text-transform: uppercase;
            }
            
            .btn-accent::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                transition: left 0.6s;
            }
            
            .btn-accent:hover {
                transform: translateY(-3px);
                box-shadow: 0 15px 35px rgba(0,0,0,0.2);
                filter: brightness(1.1);
            }
            
            .btn-accent:hover::before {
                left: 100%;
            }
            
            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(255,255,255,0.95);
                backdrop-filter: blur(20px);
                border-top: 1px solid rgba(255,255,255,0.2);
                z-index: 1000;
                padding: 8px 0;
            }
            
            .nav-item {
                flex: 1;
                text-align: center;
                padding: 12px 8px;
                color: #64748b;
                text-decoration: none;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border-radius: 16px;
                margin: 0 4px;
                position: relative;
                overflow: hidden;
            }
            
            .nav-item::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, var(--accent), var(--secondary));
                opacity: 0;
                transition: opacity 0.3s;
                border-radius: 16px;
            }
            
            .nav-item.active {
                color: white;
                transform: translateY(-2px);
            }
            
            .nav-item.active::before {
                opacity: 1;
            }
            
            .nav-item i, .nav-item small {
                position: relative;
                z-index: 1;
            }
            
            .ticker {
                background: linear-gradient(135deg, rgba(0,0,0,0.9), rgba(30,30,30,0.9));
                color: white;
                padding: 12px;
                white-space: nowrap;
                overflow: hidden;
                position: relative;
            }
            
            .ticker::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 1px;
                background: linear-gradient(90deg, var(--accent), var(--secondary), var(--accent));
            }
            
            .ticker-content {
                display: inline-block;
                animation: scroll 40s linear infinite;
                font-weight: 500;
            }
            
            @keyframes scroll {
                0% { transform: translateX(100%); }
                100% { transform: translateX(-100%); }
            }
            
            .balance-card {
                background: linear-gradient(135deg, var(--accent) 0%, var(--secondary) 50%, #f093fb 100%);
                color: white;
                border-radius: 24px;
                padding: 32px;
                margin-bottom: 24px;
                position: relative;
                overflow: hidden;
                animation: balanceGlow 4s ease-in-out infinite;
            }
            
            .balance-card::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                animation: rotate 20s linear infinite;
            }
            
            @keyframes balanceGlow {
                0%, 100% { box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3); }
                50% { box-shadow: 0 25px 50px rgba(139, 92, 246, 0.4); }
            }
            
            @keyframes rotate {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .balance-card > * {
                position: relative;
                z-index: 1;
            }
            
            .plan-card {
                border: 2px solid transparent;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }
            
            .plan-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.1), transparent);
                transition: left 0.6s;
            }
            
            .plan-card:hover {
                border-color: var(--accent);
                transform: translateY(-8px) scale(1.02);
                box-shadow: 0 25px 50px rgba(99, 102, 241, 0.2);
            }
            
            .plan-card:hover::before {
                left: 100%;
            }
            
            .plan-card.vip {
                background: linear-gradient(135deg, #ffd700 0%, #ffed4e 50%, #fff2a1 100%);
                color: #333;
                border-color: #ffd700;
                animation: vipGlow 3s ease-in-out infinite;
            }
            
            @keyframes vipGlow {
                0%, 100% { box-shadow: 0 0 30px rgba(255, 215, 0, 0.3); }
                50% { box-shadow: 0 0 40px rgba(255, 215, 0, 0.5); }
            }
            
            .progress-ring {
                width: 70px;
                height: 70px;
                position: relative;
            }
            
            .progress-ring circle {
                fill: none;
                stroke: rgba(255,255,255,0.2);
                stroke-width: 6;
            }
            
            .progress-ring .progress {
                stroke: var(--accent);
                stroke-linecap: round;
                transition: stroke-dasharray 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.5));
            }
            
            @keyframes countUp {
                from { 
                    opacity: 0; 
                    transform: translateY(30px) scale(0.8); 
                }
                to { 
                    opacity: 1; 
                    transform: translateY(0) scale(1); 
                }
            }
            
            .count-up {
                animation: countUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .floating-action {
                position: fixed;
                bottom: 110px;
                right: 24px;
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--accent), var(--secondary));
                color: white;
                border: none;
                box-shadow: 
                    0 8px 25px rgba(99, 102, 241, 0.4),
                    0 0 0 1px rgba(255,255,255,0.2);
                z-index: 999;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                animation: float 3s ease-in-out infinite;
            }
            
            .floating-action:hover {
                transform: translateY(-4px) scale(1.1);
                box-shadow: 
                    0 15px 35px rgba(99, 102, 241, 0.5),
                    0 0 0 1px rgba(255,255,255,0.3);
            }
            
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-6px); }
            }
            
            .voice-indicator {
                position: fixed;
                top: 24px;
                right: 24px;
                background: rgba(0,0,0,0.9);
                color: white;
                padding: 12px 20px;
                border-radius: 50px;
                display: none;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.1);
                animation: slideIn 0.3s ease-out;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            .stats-card {
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.2);
                backdrop-filter: blur(10px);
                transition: all 0.3s ease;
            }
            
            .stats-card:hover {
                background: rgba(255,255,255,0.15);
                transform: translateY(-2px);
            }
            
            .loading-spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255,255,255,0.3);
                border-radius: 50%;
                border-top-color: white;
                animation: spin 1s ease-in-out infinite;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            
            .notification {
                position: fixed;
                top: 24px;
                right: 24px;
                background: rgba(255,255,255,0.95);
                backdrop-filter: blur(20px);
                border-radius: 16px;
                padding: 16px 24px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                border: 1px solid rgba(255,255,255,0.2);
                z-index: 9999;
                animation: slideIn 0.3s ease-out;
            }
            
            .glassmorphism {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 20px;
            }
            
            .gradient-text {
                background: linear-gradient(135deg, var(--accent), var(--secondary));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                font-weight: 700;
            }
            
            .pulse {
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            @keyframes shimmer {
                0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
                100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            }
            
            .card:hover {
                transform: translateY(-8px) scale(1.02);
                box-shadow: 
                    0 30px 60px rgba(0,0,0,0.15),
                    0 0 0 1px rgba(255,255,255,0.3);
            }
            
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Mobile Optimizations */
            @media (max-width: 768px) {
                .balance-card {
                    padding: 24px;
                    margin: 16px;
                    border-radius: 20px;
                }
                
                .card {
                    margin: 8px;
                    border-radius: 16px;
                }
                
                .floating-action {
                    bottom: 100px;
                    right: 16px;
                    width: 56px;
                    height: 56px;
                }
            }
            
            /* Theme Transitions */
            .theme-transition {
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            }
        </style>
    </head>
    <body>
        <!-- Live Transaction Ticker -->
        <div class="ticker">
            <div class="ticker-content" id="ticker">
                <span>🎉 Welcome to InvestPro - Your Smart Investment Platform</span>
            </div>
        </div>
        
        <!-- Voice Indicator -->
        <div class="voice-indicator" id="voiceIndicator">
            <i class="bi bi-volume-up"></i> <span id="voiceText"></span>
        </div>
        
        <div class="container main-container py-4">
            <?php
            // Display messages
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
                echo $_SESSION['success'];
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                echo '</div>';
                unset($_SESSION['success']);
            }
            
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                echo $_SESSION['error'];
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                echo '</div>';
                unset($_SESSION['error']);
            }
            
            // Route to appropriate page
            switch ($action) {
                case 'landing':
                    renderLandingPage();
                    break;
                case 'login':
                    renderLoginPage();
                    break;
                case 'register':
                    renderRegisterPage();
                    break;
                case 'dashboard':
                case 'home':
                    renderDashboard($pdo);
                    break;
                case 'invest':
                    renderInvestPage($pdo);
                    break;
                case 'team':
                    renderTeamPage($pdo);
                    break;
                case 'profile':
                case 'me':
                    renderProfilePage($pdo);
                    break;
                case 'admin':
                    if (isAdmin()) {
                        renderAdminDashboard($pdo);
                    } else {
                        renderDashboard($pdo);
                    }
                    break;
                default:
                    renderDashboard($pdo);
            }
            ?>
        </div>
        
        <?php if (isLoggedIn()): ?>
        <!-- Bottom Navigation -->
        <div class="bottom-nav d-flex">
            <a href="?action=dashboard" class="nav-item <?= $action === 'dashboard' ? 'active' : '' ?>" 
               onclick="speakAction('Navigating to dashboard. View your balance, investments, and recent transactions.')">
                <i class="bi bi-house-door fs-4 d-block"></i>
                <small>Home</small>
            </a>
            <a href="?action=invest" class="nav-item <?= $action === 'invest' ? 'active' : '' ?>" 
               onclick="speakAction('Navigating to investment plans. Choose from starter, basic, premium, VIP, and elite plans with guaranteed daily returns.')">
                <i class="bi bi-graph-up-arrow fs-4 d-block"></i>
                <small>Invest</small>
            </a>
            <a href="?action=team" class="nav-item <?= $action === 'team' ? 'active' : '' ?>" 
               onclick="speakAction('Navigating to team page. Build your referral network and earn 3-level commissions: 15%, 5%, and 2%.')">
                <i class="bi bi-people fs-4 d-block"></i>
                <small>Team</small>
            </a>
            <a href="?action=me" class="nav-item <?= $action === 'me' ? 'active' : '' ?>" 
               onclick="speakAction('Navigating to profile page. Manage your account settings, security, and redeem gift codes.')">
                <i class="bi bi-person-circle fs-4 d-block"></i>
                <small>Me</small>
            </a>
        </div>
        
        <!-- Floating Action Button -->
        <button class="floating-action" onclick="toggleTTS()">
            <i class="bi bi-volume-up" id="ttsIcon"></i>
        </button>
        <?php endif; ?>
        
        <!-- Support Chat Button (Always visible) -->
        <button class="btn btn-primary rounded-circle position-fixed" style="bottom: <?= isLoggedIn() ? '160px' : '20px' ?>; right: 20px; width: 60px; height: 60px; z-index: 1000;" onclick="toggleChat()">
            <i class="bi bi-chat-dots fs-4"></i>
        </button>
        
        <!-- Chat Widget -->
        <div id="chatWidget" class="position-fixed bg-white rounded-3 shadow-lg" style="bottom: <?= isLoggedIn() ? '230px' : '90px' ?>; right: 20px; width: 350px; height: 400px; display: none; z-index: 1001;">
            <div class="bg-primary text-white p-3 rounded-top d-flex align-items-center">
                <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                    <img src="https://i.postimg.cc/h4x69VDq/images-3.png" alt="Support" style="width: 25px;">
                </div>
                <div class="flex-grow-1">
                    <strong>InvestPro Support</strong><br>
                    <small class="opacity-75">@InvestProPH_Support</small>
                </div>
                <button class="btn btn-sm text-white" onclick="toggleChat()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div id="chatMessages" class="p-3" style="height: 280px; overflow-y: auto;">
                <div class="mb-3">
                    <div class="bg-light rounded p-2 d-inline-block">
                        <small>👋 Hi! I'm InvestPro Assistant. How can I help you today?</small>
                    </div>
                </div>
            </div>
            
            <div class="p-3 border-top">
                <div class="input-group">
                    <input type="text" id="chatInput" class="form-control" placeholder="Ask about InvestPro..." onkeypress="if(event.key==='Enter') sendMessage()">
                    <button class="btn btn-primary" onclick="sendMessage()">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Theme rotation
            setInterval(() => {
                fetch('?action=theme_next', { method: 'POST' })
                    .then(r => r.json())
                    .then(data => {
                        document.documentElement.style.setProperty('--accent-color', `var(--bs-${data.theme})`);
                    });
            }, 30000);
            
            // Live ticker
            function updateTicker() {
                fetch('?action=ticker')
                    .then(r => r.json())
                    .then(data => {
                        const ticker = document.getElementById('ticker');
                        if (ticker && data.length > 0) {
                            const messages = data.map(t => 
                                `💰 ${t.fullname} ${t.type === 'deposit' ? 'deposited' : t.type === 'roi_credit' ? 'earned ROI' : t.type} ₱${Math.abs(t.amount)}`
                            );
                            ticker.innerHTML = messages.join(' • ');
                        }
                    })
                    .catch(() => {
                        // Fallback polling
                        setTimeout(updateTicker, 10000);
                    });
            }
            
            updateTicker();
            
            // TTS functionality
            let ttsEnabled = <?= json_encode($_SESSION['tts_enabled'] ?? true) ?>;
            let voiceUsed = <?= json_encode($_SESSION['voice_used'] ?? false) ?>;
            
            function toggleTTS() {
                fetch('?action=tts_pref', { method: 'POST' })
                    .then(r => r.json())
                    .then(data => {
                        ttsEnabled = data.enabled;
                        document.getElementById('ttsIcon').className = ttsEnabled ? 'bi bi-volume-up' : 'bi bi-volume-mute';
                    });
            }
            
            function speak(text) {
                if (!ttsEnabled || !('speechSynthesis' in window)) return;
                
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 0.8;
                utterance.pitch = 1;
                speechSynthesis.speak(utterance);
                
                document.getElementById('voiceIndicator').style.display = 'block';
                document.getElementById('voiceText').textContent = text;
                
                setTimeout(() => {
                    document.getElementById('voiceIndicator').style.display = 'none';
                }, 3000);
            }
            
            function speakAction(text) {
                if (ttsEnabled && 'speechSynthesis' in window) {
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.rate = 0.9;
                    utterance.pitch = 1.1;
                    utterance.volume = 0.8;
                    speechSynthesis.speak(utterance);
                    
                    // Show voice indicator
                    const indicator = document.getElementById('voiceIndicator');
                    const voiceText = document.getElementById('voiceText');
                    if (indicator && voiceText) {
                        indicator.style.display = 'block';
                        voiceText.textContent = text;
                        setTimeout(() => {
                            indicator.style.display = 'none';
                        }, 3000);
                    }
                }
            }
            
            // Voice explanations
            if (!voiceUsed) {
                setTimeout(() => {
                    speak("Welcome to InvestPro! PIN and OTP security with daily ROI in pesos - transparent and secure.");
                }, 2000);
                
                setInterval(() => {
                    const messages = [
                        "After admin-approved deposit, ROI auto-credits every 24 hours.",
                        "Live ticker and full history for complete peace of mind.",
                        "Secure PIN plus OTP for all money operations."
                    ];
                    speak(messages[Math.floor(Math.random() * messages.length)]);
                }, 43200000); // 12 hours
            }
            
            // Count-up animation
            function animateValue(element, start, end, duration) {
                const range = end - start;
                const increment = range / (duration / 16);
                let current = start;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= end) {
                        current = end;
                        clearInterval(timer);
                    }
                    element.textContent = '₱' + current.toLocaleString('en-PH', { minimumFractionDigits: 2 });
                }, 16);
            }
            
            // Initialize count-up animations
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.count-up').forEach(el => {
                    const value = parseFloat(el.dataset.value || 0);
                    animateValue(el, 0, value, 2000);
                });
            });
            
            // Progress ring animation
            function updateProgressRing(element, progress) {
                const circle = element.querySelector('.progress');
                const radius = circle.r.baseVal.value;
                const circumference = 2 * Math.PI * radius;
                const offset = circumference - (progress / 100) * circumference;
                
                circle.style.strokeDasharray = circumference;
                circle.style.strokeDashoffset = offset;
            }
            
            // Auto-refresh for real-time updates
            setInterval(() => {
                if (document.hidden) return;
                
                // Refresh current page data
                const currentAction = new URLSearchParams(window.location.search).get('action') || 'dashboard';
                if (['dashboard', 'invest', 'team', 'admin'].includes(currentAction)) {
                    // Subtle refresh without full page reload
                    fetch(window.location.href)
                        .then(r => r.text())
                        .then(html => {
                            // Update specific sections only
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            // Update balance if exists
                            const newBalance = doc.querySelector('.balance-amount');
                            const currentBalance = document.querySelector('.balance-amount');
                            if (newBalance && currentBalance && newBalance.textContent !== currentBalance.textContent) {
                                currentBalance.textContent = newBalance.textContent;
                                currentBalance.classList.add('count-up');
                            }
                        });
                }
            }, 30000);
            
            // Chat functions
            function toggleChat() {
                const widget = document.getElementById('chatWidget');
                if (widget) {
                    widget.style.display = widget.style.display === 'none' ? 'block' : 'none';
                }
            }
            
            function sendMessage() {
                const input = document.getElementById('chatInput');
                if (!input) return;
                
                const message = input.value.trim();
                if (!message) return;
                
                // Add user message
                addMessage(message, 'user');
                input.value = '';
                
                // Send to chatbot
                fetch('?action=chatbot', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'message=' + encodeURIComponent(message)
                })
                .then(r => r.json())
                .then(data => {
                    addMessage(data.response, 'bot');
                });
            }
            
            function addMessage(text, sender) {
                const messages = document.getElementById('chatMessages');
                if (!messages) return;
                
                const div = document.createElement('div');
                div.className = 'mb-3';
                
                if (sender === 'user') {
                    div.innerHTML = `<div class="bg-primary text-white rounded p-2 d-inline-block ms-auto" style="max-width: 80%;">${text}</div>`;
                    div.style.textAlign = 'right';
                } else {
                    div.innerHTML = `<div class="bg-light rounded p-2 d-inline-block" style="max-width: 80%;">${text}</div>`;
                }
                
                messages.appendChild(div);
                messages.scrollTop = messages.scrollHeight;
            }
        </script>
    <script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'979c6aff914685e6',t:'MTc1Njk3NzcwMC4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
    </html>
    <?php
}

function renderLandingPage() {
    ?>
    <div class="min-vh-100 d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card border-0 shadow-lg">
                        <div class="card-body p-5 text-center">
                            <!-- Logo -->
                            <div class="mb-4">
                                <div class="rounded-circle bg-white shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                    <img src="https://i.postimg.cc/h4x69VDq/images-3.png" alt="InvestPro" class="img-fluid" style="max-width: 80px;">
                                </div>
                            </div>
                            
                            <!-- Welcome Text -->
                            <h1 class="fw-bold text-white mb-3">Welcome to InvestPro</h1>
                            <p class="text-white-50 mb-4 fs-5">Your Smart Investment Platform</p>
                            <p class="text-white-50 mb-5">Start your investment journey with daily returns in Philippine Pesos. Secure, transparent, and profitable.</p>
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-3">
                                <a href="?action=login" class="btn btn-light btn-lg rounded-pill py-3">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    <strong>Sign In to Your Account</strong>
                                </a>
                                <a href="?action=register" class="btn btn-outline-light btn-lg rounded-pill py-3">
                                    <i class="bi bi-person-plus me-2"></i>
                                    <strong>Create New Account</strong>
                                    <small class="d-block">Get ₱200 Welcome Bonus!</small>
                                </a>
                            </div>
                            
                            <!-- Features -->
                            <div class="row mt-5 text-start">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center text-white-50">
                                        <i class="bi bi-shield-check fs-4 me-3 text-success"></i>
                                        <div>
                                            <strong class="text-white">Secure Platform</strong><br>
                                            <small>PIN + OTP Protection</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center text-white-50">
                                        <i class="bi bi-graph-up fs-4 me-3 text-primary"></i>
                                        <div>
                                            <strong class="text-white">Daily Returns</strong><br>
                                            <small>Automated ROI in Pesos</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center text-white-50">
                                        <i class="bi bi-people fs-4 me-3 text-info"></i>
                                        <div>
                                            <strong class="text-white">Referral System</strong><br>
                                            <small>3-Level Commissions</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center text-white-50">
                                        <i class="bi bi-phone fs-4 me-3 text-warning"></i>
                                        <div>
                                            <strong class="text-white">Mobile Ready</strong><br>
                                            <small>Invest Anywhere</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Support Chat Button -->
    <button class="btn btn-primary rounded-circle position-fixed" style="bottom: 20px; right: 20px; width: 60px; height: 60px; z-index: 1000;" onclick="toggleChat()">
        <i class="bi bi-chat-dots fs-4"></i>
    </button>
    
    <!-- Chat Widget -->
    <div id="chatWidget" class="position-fixed bg-white rounded-3 shadow-lg" style="bottom: 90px; right: 20px; width: 350px; height: 400px; display: none; z-index: 1001;">
        <div class="bg-primary text-white p-3 rounded-top d-flex align-items-center">
            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                <img src="https://i.postimg.cc/h4x69VDq/images-3.png" alt="Support" style="width: 25px;">
            </div>
            <div class="flex-grow-1">
                <strong>InvestPro Support</strong><br>
                <small class="opacity-75">@InvestProPH_Support</small>
            </div>
            <button class="btn btn-sm text-white" onclick="toggleChat()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div id="chatMessages" class="p-3" style="height: 280px; overflow-y: auto;">
            <div class="mb-3">
                <div class="bg-light rounded p-2 d-inline-block">
                    <small>👋 Hi! I'm InvestPro Assistant. How can I help you today?</small>
                </div>
            </div>
        </div>
        
        <div class="p-3 border-top">
            <div class="input-group">
                <input type="text" id="chatInput" class="form-control" placeholder="Ask about InvestPro..." onkeypress="if(event.key==='Enter') sendMessage()">
                <button class="btn btn-primary" onclick="sendMessage()">
                    <i class="bi bi-send"></i>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            widget.style.display = widget.style.display === 'none' ? 'block' : 'none';
        }
        
        function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;
            
            // Add user message
            addMessage(message, 'user');
            input.value = '';
            
            // Send to chatbot
            fetch('?action=chatbot', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(r => r.json())
            .then(data => {
                addMessage(data.response, 'bot');
            });
        }
        
        function addMessage(text, sender) {
            const messages = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = 'mb-3';
            
            if (sender === 'user') {
                div.innerHTML = `<div class="bg-primary text-white rounded p-2 d-inline-block ms-auto" style="max-width: 80%;">${text}</div>`;
                div.style.textAlign = 'right';
            } else {
                div.innerHTML = `<div class="bg-light rounded p-2 d-inline-block" style="max-width: 80%;">${text}</div>`;
            }
            
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }
    </script>
    <?php
}

function renderLoginPage() {
    ?>
    <div class="min-vh-100 d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card glassmorphism border-0 shadow-lg">
                        <div class="card-body p-5">
                            <div class="text-center mb-5">
                                <div class="position-relative d-inline-block mb-4">
                                    <div class="rounded-circle bg-white shadow-lg mx-auto d-flex align-items-center justify-content-center pulse" style="width: 120px; height: 120px;">
                                        <img src="https://i.postimg.cc/h4x69VDq/images-3.png" alt="InvestPro" class="img-fluid" style="max-width: 80px;">
                                    </div>
                                    <div class="position-absolute top-0 start-0 w-100 h-100 rounded-circle" style="background: linear-gradient(135deg, var(--accent), var(--secondary)); opacity: 0.1; animation: rotate 10s linear infinite;"></div>
                                </div>
                                <h2 class="fw-bold gradient-text mb-2">Welcome Back!</h2>
                                <p class="text-white-50 fs-5">Sign in to continue your investment journey</p>
                            </div>
                            
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="login">
                                
                                <div class="mb-4">
                                    <label class="form-label text-white fw-semibold">
                                        <i class="bi bi-person-circle me-2"></i>Username or Email
                                    </label>
                                    <div class="position-relative">
                                        <input type="text" name="username" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                               style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                               placeholder="Enter your username or email" required>
                                        <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                            <i class="bi bi-person text-white-50"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label text-white fw-semibold">
                                        <i class="bi bi-shield-lock me-2"></i>Password
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" name="password" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                               style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                               placeholder="Enter your password" required>
                                        <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                            <i class="bi bi-eye text-white-50" style="cursor: pointer;" onclick="togglePassword(this)"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-accent btn-lg py-3 fw-bold">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>
                                        SIGN IN TO DASHBOARD
                                    </button>
                                </div>
                                
                                <div class="text-center">
                                    <p class="text-white-50 mb-3">Don't have an account?</p>
                                    <a href="?action=register" class="btn btn-outline-light btn-lg px-4 fw-semibold">
                                        <i class="bi bi-person-plus me-2"></i>Create New Account
                                    </a>
                                </div>
                            </form>
                            
                            <!-- Admin Quick Login (Hidden) -->
                            <div class="text-center mt-4">
                                <small class="text-white-50">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Secure login with advanced encryption
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Features Preview -->
                    <div class="row mt-4 text-center">
                        <div class="col-4">
                            <div class="glassmorphism p-3 rounded-3">
                                <i class="bi bi-shield-check text-success fs-4 mb-2 d-block"></i>
                                <small class="text-white">Secure</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="glassmorphism p-3 rounded-3">
                                <i class="bi bi-graph-up text-primary fs-4 mb-2 d-block"></i>
                                <small class="text-white">Profitable</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="glassmorphism p-3 rounded-3">
                                <i class="bi bi-clock text-warning fs-4 mb-2 d-block"></i>
                                <small class="text-white">24/7</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(icon) {
            const input = icon.closest('.position-relative').querySelector('input');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash text-white-50';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye text-white-50';
            }
        }
        
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
    <?php
}

function renderRegisterPage() {
    ?>
    <div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7">
                    <div class="card glassmorphism border-0 shadow-lg">
                        <div class="card-body p-5">
                            <div class="text-center mb-5">
                                <div class="position-relative d-inline-block mb-4">
                                    <div class="rounded-circle bg-white shadow-lg mx-auto d-flex align-items-center justify-content-center pulse" style="width: 120px; height: 120px;">
                                        <img src="https://i.postimg.cc/h4x69VDq/images-3.png" alt="InvestPro" class="img-fluid" style="max-width: 80px;">
                                    </div>
                                    <div class="position-absolute top-0 start-0 w-100 h-100 rounded-circle" style="background: linear-gradient(135deg, var(--success), var(--info)); opacity: 0.1; animation: rotate 15s linear infinite;"></div>
                                </div>
                                <h2 class="fw-bold gradient-text mb-2">Join InvestPro Today!</h2>
                                <p class="text-white-50 fs-5">Start your investment journey with <span class="text-warning fw-bold">₱200 Welcome Bonus!</span></p>
                            </div>
                            
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="register">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label text-white fw-semibold">
                                            <i class="bi bi-person me-2"></i>Username
                                        </label>
                                        <div class="position-relative">
                                            <input type="text" name="username" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                                   style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                                   placeholder="Choose username" required minlength="3">
                                            <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                                <i class="bi bi-person-check text-white-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label text-white fw-semibold">
                                            <i class="bi bi-envelope me-2"></i>Email Address
                                        </label>
                                        <div class="position-relative">
                                            <input type="email" name="email" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                                   style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                                   placeholder="your@email.com" required>
                                            <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                                <i class="bi bi-envelope-check text-white-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label text-white fw-semibold">
                                            <i class="bi bi-person-badge me-2"></i>Full Name
                                        </label>
                                        <div class="position-relative">
                                            <input type="text" name="fullname" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                                   style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                                   placeholder="Your full name" required>
                                            <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                                <i class="bi bi-person-lines-fill text-white-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label text-white fw-semibold">
                                            <i class="bi bi-phone me-2"></i>Phone Number
                                        </label>
                                        <div class="position-relative">
                                            <input type="tel" name="phone" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                                   style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                                   placeholder="+63 9XX XXX XXXX">
                                            <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                                <i class="bi bi-phone-vibrate text-white-50"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label text-white fw-semibold">
                                        <i class="bi bi-shield-lock me-2"></i>Password
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" name="password" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                               style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                               placeholder="Create strong password" required minlength="6">
                                        <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                            <i class="bi bi-eye text-white-50" style="cursor: pointer;" onclick="togglePassword(this)"></i>
                                        </div>
                                    </div>
                                    <small class="text-white-50">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Minimum 6 characters for security
                                    </small>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label text-white fw-semibold">
                                        <i class="bi bi-gift me-2"></i>Referral Code (Optional)
                                    </label>
                                    <div class="position-relative">
                                        <input type="text" name="referral_code" class="form-control form-control-lg glassmorphism border-0 text-white" 
                                               style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);" 
                                               placeholder="Enter referral code for bonus">
                                        <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                            <i class="bi bi-gift-fill text-warning"></i>
                                        </div>
                                    </div>
                                    <small class="text-warning">
                                        <i class="bi bi-star-fill me-1"></i>
                                        Get extra bonuses with referral code!
                                    </small>
                                </div>
                                
                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-accent btn-lg py-3 fw-bold">
                                        <i class="bi bi-rocket-takeoff me-2"></i>
                                        CREATE ACCOUNT & GET ₱200 BONUS
                                    </button>
                                </div>
                                
                                <div class="text-center">
                                    <p class="text-white-50 mb-3">Already have an account?</p>
                                    <a href="?action=login" class="btn btn-outline-light btn-lg px-4 fw-semibold">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Instead
                                    </a>
                                </div>
                            </form>
                            
                            <!-- Security Notice -->
                            <div class="text-center mt-4">
                                <small class="text-white-50">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Your data is protected with bank-level security
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Benefits Preview -->
                    <div class="row mt-4 text-center">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="glassmorphism p-3 rounded-3">
                                <i class="bi bi-currency-dollar text-success fs-4 mb-2 d-block"></i>
                                <small class="text-white fw-semibold">₱200 Bonus</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="glassmorphism p-3 rounded-3">
                                <i class="bi bi-graph-up-arrow text-primary fs-4 mb-2 d-block"></i>
                                <small class="text-white fw-semibold">Daily ROI</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="glassmorphism p-3 rounded-3">
                                <i class="bi bi-people text-info fs-4 mb-2 d-block"></i>
                                <small class="text-white fw-semibold">Referrals</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="glassmorphism p-3 rounded-3">
                                <i class="bi bi-shield-check text-warning fs-4 mb-2 d-block"></i>
                                <small class="text-white fw-semibold">Secure</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(icon) {
            const input = icon.closest('.position-relative').querySelector('input');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash text-white-50';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye text-white-50';
            }
        }
        
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
    <?php
}

function renderDashboard($pdo) {
    $user = getCurrentUser($pdo);
    
    // Get user stats
    $stmt = $pdo->prepare("SELECT 
        (SELECT SUM(amount) FROM deposits WHERE user_id = ? AND status = 'approved') as total_deposits,
        (SELECT SUM(net_amount) FROM withdrawals WHERE user_id = ? AND status = 'approved') as total_withdrawals,
        (SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'roi_credit') as total_roi,
        (SELECT COUNT(*) FROM user_investments WHERE user_id = ? AND status = 'active') as active_investments
    ");
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
    $stats = $stmt->fetch();
    
    // Get recent transactions
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $transactions = $stmt->fetchAll();
    
    // Get active investments
    $stmt = $pdo->prepare("
        SELECT ui.*, ip.name as plan_name 
        FROM user_investments ui 
        JOIN investment_plans ip ON ui.plan_id = ip.id 
        WHERE ui.user_id = ? AND ui.status = 'active'
        ORDER BY ui.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $investments = $stmt->fetchAll();
    ?>
    
    <!-- Welcome Header -->
    <div class="text-center mb-4">
        <div class="position-relative d-inline-block">
            <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center pulse" 
                 style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 1.5rem; font-weight: bold;">
                <?= strtoupper(substr($user['fullname'], 0, 2)) ?>
            </div>
            <div class="position-absolute top-0 start-0 w-100 h-100 rounded-circle" 
                 style="background: linear-gradient(135deg, var(--accent), var(--secondary)); opacity: 0.2; animation: rotate 8s linear infinite;"></div>
        </div>
        <h4 class="mt-3 fw-bold gradient-text">Welcome back, <?= explode(' ', $user['fullname'])[0] ?>! 👋</h4>
        <p class="text-white-50">Ready to grow your wealth today?</p>
    </div>
    
    <!-- Super Beautiful Balance Card -->
    <div class="position-relative mb-4">
        <div class="balance-card text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); position: relative; overflow: hidden;">
            <!-- Animated Background Elements -->
            <div class="position-absolute" style="top: -50px; right: -50px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
            <div class="position-absolute" style="bottom: -30px; left: -30px; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 4s ease-in-out infinite reverse;"></div>
            
            <div class="position-relative" style="z-index: 2;">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-wallet2 me-2 fs-4"></i>
                    <h5 class="mb-0">Available Balance</h5>
                </div>
                <h1 class="fw-bold mb-4 balance-amount count-up" data-value="<?= $user['balance'] ?>" 
                    style="font-size: 3rem; text-shadow: 0 2px 10px rgba(0,0,0,0.3);">₱0.00</h1>
                
                <div class="row text-center g-2">
                    <div class="col-4">
                        <button class="btn btn-light btn-lg w-100 glassmorphism border-0" 
                                data-bs-toggle="modal" data-bs-target="#depositModal"
                                onclick="speakAction('Opening deposit modal. Add funds to your InvestPro account securely.')"
                                style="backdrop-filter: blur(10px); background: rgba(255,255,255,0.2); color: white; transition: all 0.3s ease;">
                            <i class="bi bi-plus-circle fs-3 d-block mb-2"></i>
                            <strong>Deposit</strong>
                        </button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-light btn-lg w-100 glassmorphism border-0" 
                                data-bs-toggle="modal" data-bs-target="#withdrawModal"
                                onclick="speakAction('Opening withdrawal modal. Cash out your earnings with 10% processing fee.')"
                                style="backdrop-filter: blur(10px); background: rgba(255,255,255,0.2); color: white; transition: all 0.3s ease;">
                            <i class="bi bi-arrow-up-circle fs-3 d-block mb-2"></i>
                            <strong>Withdraw</strong>
                        </button>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-light btn-lg w-100 glassmorphism border-0" 
                                onclick="dailyCheckin(); speakAction('Processing daily check-in. Earn up to 100 pesos daily bonus with streak multiplier!')"
                                style="backdrop-filter: blur(10px); background: rgba(255,255,255,0.2); color: white; transition: all 0.3s ease;">
                            <i class="bi bi-calendar-check fs-3 d-block mb-2"></i>
                            <strong>Check-in</strong>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Super Colorful Animated Stats Cards -->
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 position-relative overflow-hidden" 
                 onclick="speakAction('Total deposits: <?= number_format($stats['total_deposits'] ?? 0, 2) ?> pesos. This shows all your approved deposits.')"
                 style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"50\" cy=\"50\" r=\"1\" fill=\"%23ffffff\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>'); animation: shimmer 3s ease-in-out infinite;"></div>
                <div class="card-body text-center text-white position-relative" style="z-index: 2;">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" 
                             style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                            <i class="bi bi-arrow-down-circle fs-2"></i>
                        </div>
                    
