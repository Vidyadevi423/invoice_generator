<?php
// ============================================
// invoice.php — View Single Invoice
// TechForge Solutions — Invoice Generator
// ============================================
require_once 'db.php';

$token = trim($_GET['token'] ?? '');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$isAdmin = !empty($_SESSION['admin_user_id']);

$conn = getDB();

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE access_token = ?");
    $stmt->bind_param('s', $token);
} elseif ($isAdmin && $id !== false && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $id);
} else {
    $conn->close();
    http_response_code(404);
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;">Invoice not found.</h2>');
}

if (!$stmt) {
    error_log('Invoice lookup query preparation failed: ' . $conn->error);
    $conn->close();
    http_response_code(500);
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;">Unable to load invoice.</h2>');
}
if (!$stmt->execute()) {
    error_log('Invoice lookup query failed: ' . $stmt->error);
    $stmt->close();
    $conn->close();
    http_response_code(500);
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;">Unable to load invoice.</h2>');
}
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    $conn->close();
    http_response_code(404);
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;">Invoice not found.</h2>');
}

$stmt2 = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
if (!$stmt2) {
    error_log('Invoice items query preparation failed: ' . $conn->error);
    $conn->close();
    http_response_code(500);
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;">Unable to load invoice.</h2>');
}
$stmt2->bind_param('i', $invoice['id']);
if (!$stmt2->execute()) {
    error_log('Invoice items query failed: ' . $stmt2->error);
    $stmt2->close();
    $conn->close();
    http_response_code(500);
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;">Unable to load invoice.</h2>');
}
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
$conn->close();

$statusColors = [
    'paid' => '#22c55e',
    'unpaid' => '#f59e0b',
    'draft' => '#94a3b8',
    'cancelled' => '#ef4444',
];
$statusColor = $statusColors[$invoice['status']] ?? '#94a3b8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_no']) ?> — <?= htmlspecialchars(COMPANY_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #f8f9fb; }
        .page-actions { display:flex; gap:12px; justify-content:center; padding:24px 0 0; flex-wrap:wrap; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">
        <span class="brand-icon">⚡</span>
        <span class="brand-name"><?= htmlspecialchars(COMPANY_NAME) ?></span>
    </div>
    <div class="navbar-links">
        <a href="index.php" class="nav-link">New Invoice</a>
        <?php if ($isAdmin): ?>
            <a href="admin.php" class="nav-link">Admin Panel</a>
        <?php else: ?>
            <a href="login.php" class="nav-link">Admin Login</a>
        <?php endif; ?>
    </div>
</nav>

<div class="page-actions">
    <button class="btn-download" onclick="downloadPDF()">⬇ Download PDF</button>
    <button class="btn-print" onclick="window.print()">🖨 Print</button>
    <?php if ($isAdmin): ?><a href="admin.php" class="btn-secondary" style="text-decoration:none;">← Back to Admin</a><?php endif; ?>
</div>

<div style="max-width:860px; margin:20px auto; padding:0 16px 60px;" id="invoicePrintArea">
    <div class="invoice-doc" id="invoiceDoc" style="display:block;">
        <div class="inv-header">
            <div class="inv-company">
                <div class="inv-company-logo">⚡</div>
                <div>
                    <h2 class="inv-company-name"><?= htmlspecialchars(COMPANY_NAME) ?></h2>
                    <p class="inv-company-detail"><?= htmlspecialchars(COMPANY_ADDRESS) ?></p>
                    <p class="inv-company-detail"><?= htmlspecialchars(COMPANY_PHONE) ?> | <?= htmlspecialchars(COMPANY_EMAIL) ?></p>
                    <p class="inv-company-detail"><?= htmlspecialchars(COMPANY_GST) ?></p>
                </div>
            </div>
            <div class="inv-meta">
                <h1 class="inv-title">INVOICE</h1>
                <table class="inv-meta-table">
                    <tr><td>Invoice No:</td><td><strong><?= htmlspecialchars($invoice['invoice_no']) ?></strong></td></tr>
                    <tr><td>Date:</td><td><strong><?= date('d M Y', strtotime($invoice['created_at'])) ?></strong></td></tr>
                    <tr><td>Status:</td><td><span class="status-badge" style="background:<?= $statusColor ?>20;color:<?= $statusColor ?>;border-color:<?= $statusColor ?>40;"><?= strtoupper($invoice['status']) ?></span></td></tr>
                </table>
            </div>
        </div>

        <div class="inv-bill">
            <div class="inv-bill-from">
                <p class="bill-label">FROM</p>
                <p class="bill-name"><?= htmlspecialchars(COMPANY_NAME) ?></p>
                <p class="bill-detail"><?= htmlspecialchars(COMPANY_ADDRESS) ?></p>
            </div>
            <div class="inv-bill-to">
                <p class="bill-label">BILL TO</p>
                <p class="bill-name"><?= htmlspecialchars($invoice['customer_name']) ?></p>
                <?php if ($invoice['customer_email']): ?><p class="bill-detail"><?= htmlspecialchars($invoice['customer_email']) ?></p><?php endif; ?>
                <?php if ($invoice['customer_phone']): ?><p class="bill-detail"><?= htmlspecialchars($invoice['customer_phone']) ?></p><?php endif; ?>
                <?php if ($invoice['customer_address']): ?><p class="bill-detail"><?= nl2br(htmlspecialchars($invoice['customer_address'])) ?></p><?php endif; ?>
            </div>
        </div>

        <table class="inv-items-table">
            <thead><tr><th>#</th><th>Item / Service</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
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

        <div class="inv-totals">
            <div class="inv-notes">
                <?php if ($invoice['notes']): ?>
                    <p class="notes-label">Notes</p>
                    <p class="notes-text"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="inv-summary">
                <div class="summary-row"><span>Subtotal</span><span><?= CURRENCY . number_format($invoice['subtotal'], 2) ?></span></div>
                <div class="summary-row"><span>GST (<?= number_format($invoice['tax_percent'], 2) ?>%)</span><span><?= CURRENCY . number_format($invoice['tax_amount'], 2) ?></span></div>
                <div class="summary-row total-row"><span>TOTAL</span><span><?= CURRENCY . number_format($invoice['total'], 2) ?></span></div>
            </div>
        </div>

        <div class="inv-footer">
            <p>Thank you for your business! 🙏</p>
            <p class="inv-footer-brand">Generated by <?= htmlspecialchars(COMPANY_NAME) ?> Invoice System</p>
        </div>
    </div>
</div>

<script>
function downloadPDF() {
    if (!window.jspdf || !window.html2canvas) {
        alert('PDF libraries are not available. Check your internet connection and try again.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const element = document.getElementById('invoiceDoc');

    const renderScale = Math.min(2, Math.max(1, 1800 / Math.max(element.scrollWidth, element.scrollHeight)));
    html2canvas(element, { scale: renderScale, useCORS: true }).then(canvas => {
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pdfW = pdf.internal.pageSize.getWidth();
        const pdfPageH = pdf.internal.pageSize.getHeight();
        const pageCanvasHeight = Math.floor(canvas.width * pdfPageH / pdfW);
        let sourceY = 0;

        // Slice the rendered canvas into A4-sized images for reliable pagination.
        while (sourceY < canvas.height) {
            const sliceHeight = Math.min(pageCanvasHeight, canvas.height - sourceY);
            const pageCanvas = document.createElement('canvas');
            pageCanvas.width = canvas.width;
            pageCanvas.height = sliceHeight;

            const context = pageCanvas.getContext('2d');
            context.drawImage(
                canvas,
                0, sourceY, canvas.width, sliceHeight,
                0, 0, canvas.width, sliceHeight
            );

            const imgData = pageCanvas.toDataURL('image/png');
            const imgHeight = (sliceHeight * pdfW) / canvas.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pdfW, imgHeight);

            sourceY += sliceHeight;
            if (sourceY < canvas.height) pdf.addPage();
        }
        pdf.save('<?= htmlspecialchars($invoice['invoice_no'], ENT_QUOTES, 'UTF-8') ?>.pdf');
    }).catch(() => alert('PDF generation failed. Please try again.'));
}
</script>
</body>
</html>
