<?php
// ============================================
// invoice.php — View Single Invoice
// TechForge Solutions — Invoice Generator
// ============================================
require_once 'db.php';

// Get invoice ID from URL
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch invoice from DB
$conn    = getDB();
$stmt    = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;">Invoice not found.</h2>');
}

// Fetch invoice items
$stmt2 = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
$stmt2->bind_param('i', $id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
$conn->close();

// Status badge color
$statusColors = [
    'paid'      => '#22c55e',
    'unpaid'    => '#f59e0b',
    'draft'     => '#94a3b8',
    'cancelled' => '#ef4444',
];
$statusColor = $statusColors[$invoice['status']] ?? '#94a3b8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_no']) ?> — <?= COMPANY_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #f8f9fb; }
        .page-actions {
            display: flex; gap: 12px; justify-content: center;
            padding: 24px 0 0; flex-wrap: wrap;
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<nav class="navbar">
    <div class="navbar-brand">
        <span class="brand-icon">⚡</span>
        <span class="brand-name"><?= COMPANY_NAME ?></span>
    </div>
    <div class="navbar-links">
        <a href="index.php" class="nav-link">New Invoice</a>
        <a href="admin.php" class="nav-link">Admin Panel</a>
    </div>
</nav>

<!-- ACTION BUTTONS -->
<div class="page-actions">
    <button class="btn-download" onclick="downloadPDF()">⬇ Download PDF</button>
    <button class="btn-print"    onclick="window.print()">🖨 Print</button>
    <a href="admin.php" class="btn-secondary" style="text-decoration:none;">← Back to Admin</a>
</div>

<!-- INVOICE DOCUMENT -->
<div style="max-width:860px; margin:20px auto; padding:0 16px 60px;" id="invoicePrintArea">
    <div class="invoice-doc" id="invoiceDoc" style="display:block;">

        <!-- Header -->
        <div class="inv-header">
            <div class="inv-company">
                <div class="inv-company-logo">⚡</div>
                <div>
                    <h2 class="inv-company-name"><?= COMPANY_NAME ?></h2>
                    <p class="inv-company-detail"><?= COMPANY_ADDRESS ?></p>
                    <p class="inv-company-detail"><?= COMPANY_PHONE ?> | <?= COMPANY_EMAIL ?></p>
                    <p class="inv-company-detail"><?= COMPANY_GST ?></p>
                </div>
            </div>
            <div class="inv-meta">
                <h1 class="inv-title">INVOICE</h1>
                <table class="inv-meta-table">
                    <tr><td>Invoice No:</td><td><strong><?= htmlspecialchars($invoice['invoice_no']) ?></strong></td></tr>
                    <tr><td>Date:</td><td><strong><?= date('d M Y', strtotime($invoice['created_at'])) ?></strong></td></tr>
                    <tr>
                        <td>Status:</td>
                        <td>
                            <span class="status-badge" style="background:<?= $statusColor ?>20; color:<?= $statusColor ?>; border-color:<?= $statusColor ?>40;">
                                <?= strtoupper($invoice['status']) ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Bill To -->
        <div class="inv-bill">
            <div class="inv-bill-from">
                <p class="bill-label">FROM</p>
                <p class="bill-name"><?= COMPANY_NAME ?></p>
                <p class="bill-detail"><?= COMPANY_ADDRESS ?></p>
            </div>
            <div class="inv-bill-to">
                <p class="bill-label">BILL TO</p>
                <p class="bill-name"><?= htmlspecialchars($invoice['customer_name']) ?></p>
                <?php if ($invoice['customer_email']): ?>
                    <p class="bill-detail"><?= htmlspecialchars($invoice['customer_email']) ?></p>
                <?php endif; ?>
                <?php if ($invoice['customer_phone']): ?>
                    <p class="bill-detail"><?= htmlspecialchars($invoice['customer_phone']) ?></p>
                <?php endif; ?>
                <?php if ($invoice['customer_address']): ?>
                    <p class="bill-detail"><?= nl2br(htmlspecialchars($invoice['customer_address'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="inv-items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item / Service</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= number_format($item['quantity'], 2) ?></td>
                    <td><?= CURRENCY . number_format($item['unit_price'], 2) ?></td>
                    <td><?= CURRENCY . number_format($item['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="inv-totals">
            <div class="inv-notes">
                <?php if ($invoice['notes']): ?>
                    <p class="notes-label">Notes</p>
                    <p class="notes-text"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="inv-summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?= CURRENCY . number_format($invoice['subtotal'], 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>GST (<?= $invoice['tax_percent'] ?>%)</span>
                    <span><?= CURRENCY . number_format($invoice['tax_amount'], 2) ?></span>
                </div>
                <div class="summary-row total-row">
                    <span>TOTAL</span>
                    <span><?= CURRENCY . number_format($invoice['total'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="inv-footer">
            <p>Thank you for your business! 🙏</p>
            <p class="inv-footer-brand">Generated by <?= COMPANY_NAME ?> Invoice System</p>
        </div>

    </div><!-- /invoice-doc -->
</div>

<script>
function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const element   = document.getElementById('invoiceDoc');
    html2canvas(element, { scale: 2, useCORS: true }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf     = new jsPDF('p', 'mm', 'a4');
        const pdfW    = pdf.internal.pageSize.getWidth();
        const pdfH    = (canvas.height * pdfW) / canvas.width;
        pdf.addImage(imgData, 'PNG', 0, 0, pdfW, pdfH);
        pdf.save('<?= $invoice['invoice_no'] ?>.pdf');
    });
}
</script>
</body>
</html>
