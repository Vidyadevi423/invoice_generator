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

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data received.']);
    exit;
}

// ---- Validate required fields ----
if (empty($data['customerName'])) {
    echo json_encode(['success' => false, 'message' => 'Customer name is required.']);
    exit;
}
if (empty($data['items']) || !is_array($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'At least one item is required.']);
    exit;
}

// ---- Sanitize inputs ----
$customerName    = clean($data['customerName']);
$customerEmail   = clean($data['customerEmail']   ?? '');
$customerPhone   = clean($data['customerPhone']   ?? '');
$customerAddress = clean($data['customerAddress'] ?? '');
$taxPercent      = floatval($data['taxPercent']   ?? 18);
$notes           = clean($data['notes']           ?? '');
$status          = in_array($data['status'] ?? '', ['paid','unpaid','draft','cancelled'])
                   ? $data['status'] : 'unpaid';
$subtotal        = floatval($data['subtotal']     ?? 0);
$taxAmount       = floatval($data['taxAmount']    ?? 0);
$total           = floatval($data['total']        ?? 0);

// ---- Connect to DB ----
$conn = getDB();

// ---- Generate invoice number ----
$invoiceNo = generateInvoiceNumber($conn);

// ---- Begin transaction ----
$conn->begin_transaction();

try {
    // Insert invoice header
    $stmt = $conn->prepare(
        "INSERT INTO invoices
         (invoice_no, customer_name, customer_email, customer_phone,
          customer_address, subtotal, tax_percent, tax_amount, total, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        'sssssddddss',
        $invoiceNo, $customerName, $customerEmail, $customerPhone,
        $customerAddress, $subtotal, $taxPercent, $taxAmount, $total, $status, $notes
    );
    $stmt->execute();

    $invoiceId = $conn->insert_id;
    $stmt->close();

    // Insert invoice items
    $itemStmt = $conn->prepare(
        "INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price, total_price)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($data['items'] as $item) {
        $itemName   = clean($item['name']  ?? 'Item');
        $quantity   = floatval($item['qty']   ?? 1);
        $unitPrice  = floatval($item['price'] ?? 0);
        $itemTotal  = $quantity * $unitPrice;

        $itemStmt->bind_param('isddd', $invoiceId, $itemName, $quantity, $unitPrice, $itemTotal);
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

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save invoice: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
