<?php
// ============================================
// save_invoice.php — Save Invoice to Database
// TechForge Solutions — Invoice Generator
// ============================================

header('Content-Type: application/json');
require_once 'db.php';

function apiError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Invalid request method.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    apiError('Invalid JSON data received.');
}

$customerName = clean($data['customerName'] ?? '');
$customerEmail = clean($data['customerEmail'] ?? '');
$customerPhone = clean($data['customerPhone'] ?? '');
$customerAddress = clean($data['customerAddress'] ?? '');
$notes = clean($data['notes'] ?? '');

if ($customerName === '') {
    apiError('Customer name is required.');
}
if (strlen($customerName) > 150) {
    apiError('Customer name is too long.');
}
if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    apiError('Enter a valid email address.');
}
if (strlen($customerEmail) > 200) {
    apiError('Customer email is too long.');
}
if ($customerPhone !== '' && !preg_match('/^[0-9+()\s-]{7,20}$/', $customerPhone)) {
    apiError('Enter a valid phone number.');
}
if (strlen($customerPhone) > 20) {
    apiError('Customer phone is too long.');
}
if (strlen($customerAddress) > 5000 || strlen($notes) > 5000) {
    apiError('Customer address or notes are too long.');
}

$itemsData = $data['items'] ?? null;
if (!is_array($itemsData) || count($itemsData) === 0) {
    apiError('At least one item is required.');
}
if (count($itemsData) > 100) {
    apiError('Too many invoice items.');
}

$taxPercent = filter_var($data['taxPercent'] ?? 18, FILTER_VALIDATE_FLOAT);
if ($taxPercent === false || !is_finite((float) $taxPercent) || $taxPercent < 0 || $taxPercent > 100) {
    apiError('Tax rate must be between 0 and 100.');
}

$status = $data['status'] ?? 'unpaid';
if (!in_array($status, ['paid', 'unpaid', 'draft', 'cancelled'], true)) {
    $status = 'unpaid';
}

$items = [];
$subtotal = 0.0;
$maxMoney = 9999999999.99;
$maxQuantity = 99999999.99;

foreach ($itemsData as $index => $item) {
    if (!is_array($item)) {
        apiError('Invalid item data at row ' . ($index + 1) . '.');
    }

    $itemName = clean($item['name'] ?? '');
    $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_FLOAT);
    $unitPrice = filter_var($item['price'] ?? null, FILTER_VALIDATE_FLOAT);

    if ($itemName === '' || strlen($itemName) > 255) {
        apiError('Item name is required and must be at most 255 characters at row ' . ($index + 1) . '.');
    }
    if ($quantity === false || !is_finite((float) $quantity) || $quantity <= 0 || $quantity > $maxQuantity) {
        apiError('Quantity is invalid at row ' . ($index + 1) . '.');
    }
    if ($unitPrice === false || !is_finite((float) $unitPrice) || $unitPrice < 0 || $unitPrice > $maxMoney) {
        apiError('Unit price is invalid at row ' . ($index + 1) . '.');
    }

    $itemTotal = round((float) $quantity * (float) $unitPrice, 2);
    if (!is_finite($itemTotal) || $itemTotal > $maxMoney) {
        apiError('Item total is too large at row ' . ($index + 1) . '.');
    }

    $subtotal += $itemTotal;
    if (!is_finite($subtotal) || $subtotal > $maxMoney) {
        apiError('Invoice subtotal is too large.');
    }

    $items[] = [
        'name' => $itemName,
        'quantity' => (float) $quantity,
        'unitPrice' => (float) $unitPrice,
        'total' => $itemTotal,
    ];
}

$subtotal = round($subtotal, 2);
$taxAmount = round($subtotal * ((float) $taxPercent / 100), 2);
$total = round($subtotal + $taxAmount, 2);

if ($taxAmount > $maxMoney || $total > $maxMoney) {
    apiError('Invoice total is too large.');
}

try {
    $conn = getDB();
    $conn->begin_transaction();

    $temporaryInvoiceNo = 'TMP-' . bin2hex(random_bytes(12));
    $accessToken = bin2hex(random_bytes(32));

    $stmt = $conn->prepare(
        "INSERT INTO invoices
         (invoice_no, access_token, customer_name, customer_email, customer_phone,
          customer_address, subtotal, tax_percent, tax_amount, total, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        'ssssssddddss',
        $temporaryInvoiceNo, $accessToken, $customerName, $customerEmail, $customerPhone,
        $customerAddress, $subtotal, $taxPercent, $taxAmount, $total, $status, $notes
    );
    $stmt->execute();

    $invoiceId = $conn->insert_id;
    $stmt->close();

    $invoiceNo = 'INV-' . str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("UPDATE invoices SET invoice_no = ? WHERE id = ?");
    $stmt->bind_param('si', $invoiceNo, $invoiceId);
    $stmt->execute();
    $stmt->close();

    $itemStmt = $conn->prepare(
        "INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price, total_price)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $itemStmt->bind_param('isddd', $invoiceId, $item['name'], $item['quantity'], $item['unitPrice'], $item['total']);
        $itemStmt->execute();
    }

    $itemStmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Invoice saved successfully!',
        'invoiceNo' => $invoiceNo,
        'invoiceId' => $invoiceId,
        'viewUrl' => 'invoice.php?token=' . urlencode($accessToken)
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
        $conn->close();
    }
    error_log('Invoice save failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to save invoice. Please try again.']);
    exit;
}

$conn->close();
?>
