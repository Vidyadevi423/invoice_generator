<?php
// ============================================
// db.php — Database Connection
// TechForge Solutions — Invoice Generator
// ============================================

$appEnv = getenv('APP_ENV') ?: 'development';

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'invoice_db');

if ($appEnv === 'production' && (getenv('DB_HOST') === false || getenv('DB_USER') === false || getenv('DB_PASS') === false || getenv('DB_NAME') === false)) {
    throw new RuntimeException('Production database configuration is incomplete.');
}

define('COMPANY_NAME', getenv('COMPANY_NAME') ?: 'TechForge Solutions');
define('COMPANY_EMAIL', getenv('COMPANY_EMAIL') ?: 'techforge@gmail.com');
define('COMPANY_PHONE', getenv('COMPANY_PHONE') ?: '+91 00000 00000');
define('COMPANY_ADDRESS', getenv('COMPANY_ADDRESS') ?: 'Theni, Tamilnadu, India');
define('COMPANY_GST', getenv('COMPANY_GST') ?: 'GSTIN: 000000000000000');
define('CURRENCY', getenv('CURRENCY') ?: '₹');
define('CURRENCY_CODE', getenv('CURRENCY_CODE') ?: 'INR');

define('INVOICE_RATE_LIMIT', max(1, (int) (getenv('INVOICE_RATE_LIMIT') ?: 30)));
define('INVOICE_RATE_WINDOW_MINUTES', max(1, (int) (getenv('INVOICE_RATE_WINDOW_MINUTES') ?: 15)));
define('LOGIN_RATE_LIMIT', max(1, (int) (getenv('LOGIN_RATE_LIMIT') ?: 5)));
define('LOGIN_RATE_WINDOW_MINUTES', max(1, (int) (getenv('LOGIN_RATE_WINDOW_MINUTES') ?: 15)));
define('LOGIN_BLOCK_MINUTES', max(1, (int) (getenv('LOGIN_BLOCK_MINUTES') ?: 15)));

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log('Database connection failed: ' . $conn->connect_error);
        throw new RuntimeException('Database connection failed.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function clean($data) {
    return is_string($data) ? trim($data) : '';
}

function requireAdmin() {
    if (empty($_SESSION['admin_user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
?>
