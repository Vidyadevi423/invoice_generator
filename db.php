<?php
// ============================================
// db.php — Database Connection
// TechForge Solutions — Invoice Generator
// ============================================

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // Change to your MySQL username
define('DB_PASS', '');             // Change to your MySQL password
define('DB_NAME', 'invoice_db');

// Company configuration — Edit these details
define('COMPANY_NAME',    'TechForge Solutions');
define('COMPANY_EMAIL',   'techforge@gmail.com');
define('COMPANY_PHONE',   '+91 00000 00000');
define('COMPANY_ADDRESS', 'Theni, Tamilnadu, India');
define('COMPANY_GST',     'GSTIN: 000000000000000');
define('CURRENCY',        '₹');
define('CURRENCY_CODE',   'INR');

// Create database connection
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }

    // Set charset
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Generate unique invoice number
function generateInvoiceNumber($conn) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM invoices");
    $row    = $result->fetch_assoc();
    $next   = $row['total'] + 1;
    return 'INV-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

// Sanitize input data
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
