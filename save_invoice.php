<?php
// ============================================
// save_invoice.php — Save Invoice to Database
// TechForge Solutions — Invoice Generator
// ============================================

header('Content-Type: application/json');
require_once 'db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get and decode JSON body
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data received.']);
    exit;
}

// ---- Validate required fields ----
if (empty($data['customerName']) || !is_string($data['customerName'])) {
    echo json_encode(['success' => false, 'message' => 'Customer name is required.']);
    exit;
}

if (empty($data['items']) || !is_array($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'At least one item is required.']);
    exit;
}

if (count($data['items']) > 100) {
    echo json_encode(['success' => false, 'message' => 'Too many invoice items.']);
    exit;
}

// ---- Sanitize and validate inputs ----
$customerName    = clean($data['customerName']);
$customerEmail   = clean($data['customerEmail']   ?? '');
$customerPhone   = clean($data['customerPhone']   ?? '');
$customerAddress = clean($data['customerAddress'] ?? '');
$taxPercent      = filter_var($data['taxPercent'] ?? 18, FILTER_VALIDATE_FLOAT);
$notes           = clean($data['notes']           ?? '');
$status          = in_array($data['status'] ?? '', ['paid', 'unpaid', 'draft', 'cancelled'], true)
                   ? $data['status'] : 'unpaid';

if ($taxPercent === false || $taxPercent < 0 || $taxPercent > 100) {
    echo json_encode(['success' => false, 'message' => 'Tax rate must be between 0 and 100.']);
    exit;
}

// ---- Calculate invoice totals from item data on the server ----
$items = [];
$subtotal = 0.0;

foreach ($data['items'] as $index => $item) {
    if (!is_array($item)) {
        echo json_encode(['success' => false, 'message' => 'Invalid item data at row ' . ($index + 1) . '.']);
        exit;
    }

    $itemName  = clean($item['name'] ?? '');
    $quantity  = filter_var($item['qty'] ?? null, FILTER_VALIDATE_FLOAT);
    $unitPrice = filter_var($item['price'] ?? null, FILTER_VALIDATE_FLOAT);

    if ($itemName === '') {
        echo json_encode(['success' => false, 'message' => 'Item name is required at row ' . ($index + 1) . '.']);
        exit;
    }

    if ($quantity === false || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0 at row ' . ($index + 1) . '.']);
        exit;
    }

    if ($unitPrice === false || $unitPrice < 0) {
        echo json_encode(['success' => false, 'message' => 'Unit price cannot be negative at row ' . ($index + 1) . '.']);
        exit;
    }

    $itemTotal = round($quantity * $unitPrice, 2);
    $subtotal += $itemTotal;

    $items[] = [
        'name' => $itemName,
        'quantity' => $quantity,
        'unitPrice' => $unitPrice,
        'total' => $itemTotal,
    ];
}

$subtotal  = round($subtotal, 2);
$taxAmount = round($subtotal * ($taxPercent / 100), 2);
$total     = round($subtotal + $taxAmount, 2);

// ---- Connect to DB ----
$conn = getDB();

// ---- Begin transaction ----
$conn->begin_transaction();

try {
    // Insert with a temporary unique invoice number. The final number is based
    // on the database-generated invoice ID, so concurrent requests cannot
    // generate the same invoice number.
    $temporaryInvoiceNo = 'TMP-' . bin2hex(random_bytes(12));

    $stmt = $conn->prepare(
        "INSERT INTO invoices
         (invoice_no, customer_name, customer_email, customer_phone,
          customer_address, subtotal, tax_percent, tax_amount, total, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        'sssssddddss',
        $temporaryInvoiceNo, $customerName, $customerEmail, $customerPhone,
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

    // Insert invoice items
    $itemStmt = $conn->prepare(
        "INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price, total_price)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $itemStmt->bind_param(
            'isddd',
            $invoiceId,
            $item['name'],
            $item['quantity'],
            $item['unitPrice'],
            $item['total']
        );
        $itemStmt->execute();
    }

    $itemStmt->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success'    => true,
        'message'    => 'Invoice saved successfully!',
        'invoiceNo'  => $invoiceNo,
        'invoiceId'  => $invoiceId,
        'viewUrl'    => 'invoice.php?id=' . $invoiceId
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Invoice save failed: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save invoice. Please try again.'
    ]);
}

$conn->close();
?>
