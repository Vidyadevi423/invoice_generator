<?php
// ============================================
// db.php — Database Connection
// TechForge Solutions — Invoice Generator
// ============================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'invoice_db');

define('COMPANY_NAME',    'TechForge Solutions');
define('COMPANY_EMAIL',   'techforge@gmail.com');
define('COMPANY_PHONE',   '+91 00000 00000');
define('COMPANY_ADDRESS', 'Theni, Tamilnadu, India');
define('COMPANY_GST',     'GSTIN: 000000000000000');
define('CURRENCY',        '₹');
define('CURRENCY_CODE',   'INR');

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

function generateInvoiceNumber($conn) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM invoices");
    $row = $result->fetch_assoc();
    return 'INV-' . str_pad($row['total'] + 1, 5, '0', STR_PAD_LEFT);
}

function clean($data) {
    if (!is_string($data)) {
        return '';
    }
    return trim($data);
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
