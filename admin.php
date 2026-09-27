<?php
// ============================================
// admin.php — Admin Panel
// TechForge Solutions — Invoice Generator
// ============================================
require_once 'db.php';
requireAdmin();

$conn = getDB();

// ---- Handle Delete Invoice ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $delId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
    if ($delId === false || $delId <= 0) {
        header('Location: admin.php?msg=error');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $delId);
    if (!$stmt->execute()) {
        error_log('Invoice deletion failed: ' . $stmt->error);
        $stmt->close();
        header('Location: admin.php?msg=error');
        exit;
    }
    $stmt->close();
    header('Location: admin.php?msg=deleted');
    exit;
}

// ---- Handle Status Update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $updId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
    $updStatus = $_POST['status'] ?? '';
    if ($updId === false || $updId <= 0 || !in_array($updStatus, ['paid','unpaid','draft','cancelled'], true)) {
        header('Location: admin.php?msg=error');
        exit;
    }

    $stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $updStatus, $updId);
    if (!$stmt->execute()) {
        error_log('Invoice status update failed: ' . $stmt->error);
        $stmt->close();
        header('Location: admin.php?msg=error');
        exit;
    }
    $stmt->close();
    header('Location: admin.php?msg=updated');
    exit;
}

// ---- Filter & Search ----
$search = clean($_GET['search'] ?? '');
$filterSt = $_GET['status'] ?? 'all';
if (!in_array($filterSt, ['all','paid','unpaid','draft','cancelled'], true)) {
    $filterSt = 'all';
}
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $where .= " AND (customer_name LIKE ? OR invoice_no LIKE ?)";
    $s = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
    $types .= 'ss';
}
if ($filterSt !== 'all') {
    $where .= " AND status = ?";
    $params[] = $filterSt;
    $types .= 's';
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM invoices $where");
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['cnt'];
$totalPages = ceil($totalRows / $perPage);
$countStmt->close();

$sql = "SELECT * FROM invoices $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(total) AS revenue,
        SUM(CASE WHEN status='paid' THEN total ELSE 0 END) AS paid_amount,
        SUM(CASE WHEN status='unpaid' THEN 1 ELSE 0 END) AS unpaid_count
    FROM invoices
")->fetch_assoc();

$conn->close();

$statusColors = [
    'paid' => ['bg' => '#dcfce7', 'color' => '#16a34a'],
    'unpaid' => ['bg' => '#fef9c3', 'color' => '#b45309'],
    'draft' => ['bg' => '#f1f5f9', 'color' => '#475569'],
    'cancelled' => ['bg' => '#fee2e2', 'color' => '#dc2626'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — <?= htmlspecialchars(COMPANY_NAME) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="admin-body">
<nav class="navbar">
    <div class="navbar-brand">
        <span class="brand-icon">⚡</span>
        <span class="brand-name"><?= htmlspecialchars(COMPANY_NAME) ?></span>
    </div>
    <div class="navbar-links">
        <a href="index.php" class="nav-link">New Invoice</a>
        <a href="admin.php" class="nav-link active">Admin Panel</a>
        <form method="POST" action="logout.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <button type="submit" class="nav-link" style="border:0;background:none;cursor:pointer;">Logout</button>
        </form>
    </div>
</nav>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Admin Panel</h1>
            <p class="admin-subtitle">Manage all invoices</p>
        </div>
        <a href="index.php" class="btn-primary">+ New Invoice</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert <?= $_GET['msg'] === 'deleted' ? 'alert-error' : 'alert-success' ?>">
        <?= $_GET['msg'] === 'deleted' ? '🗑 Invoice deleted successfully.' : '✅ Invoice status updated.' ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon">📄</div><div class="stat-info"><p class="stat-value"><?= number_format($stats['total']) ?></p><p class="stat-label">Total Invoices</p></div></div>
        <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-info"><p class="stat-value"><?= CURRENCY . number_format($stats['revenue'] ?? 0, 0) ?></p><p class="stat-label">Total Revenue</p></div></div>
        <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-info"><p class="stat-value"><?= CURRENCY . number_format($stats['paid_amount'] ?? 0, 0) ?></p><p class="stat-label">Paid Amount</p></div></div>
        <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-info"><p class="stat-value"><?= number_format($stats['unpaid_count']) ?></p><p class="stat-label">Unpaid Invoices</p></div></div>
    </div>

    <div class="admin-toolbar">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search by name or invoice no..." value="<?= htmlspecialchars($search) ?>" class="search-input">
            <select name="status" class="filter-select">
                <option value="all" <?= $filterSt === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="paid" <?= $filterSt === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="unpaid" <?= $filterSt === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                <option value="draft" <?= $filterSt === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="cancelled" <?= $filterSt === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn-primary">Search</button>
            <?php if ($search || $filterSt !== 'all'): ?><a href="admin.php" class="btn-secondary">Clear</a><?php endif; ?>
        </form>
        <p class="result-count"><?= number_format($totalRows) ?> invoice<?= $totalRows !== 1 ? 's' : '' ?> found</p>
    </div>

    <div class="admin-table-wrap">
        <?php if (empty($invoices)): ?>
            <div class="empty-table"><div class="empty-icon">📭</div><p>No invoices found. <a href="index.php">Create your first invoice →</a></p></div>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Invoice No</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
                <?php $sc = $statusColors[$inv['status']] ?? $statusColors['draft']; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></td>
                    <td><div class="customer-cell"><span class="cust-name"><?= htmlspecialchars($inv['customer_name']) ?></span><?php if ($inv['customer_email']): ?><span class="cust-email"><?= htmlspecialchars($inv['customer_email']) ?></span><?php endif; ?></div></td>
                    <td><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                    <td><strong><?= CURRENCY . number_format($inv['total'], 2) ?></strong></td>
                    <td>
                        <form method="POST" class="status-form">
                            <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <select name="status" class="status-select" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;" onchange="this.form.submit()">
                                <option value="paid" <?= $inv['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="unpaid" <?= $inv['status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                <option value="draft" <?= $inv['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="cancelled" <?= $inv['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="invoice.php?id=<?= (int) $inv['id'] ?>" class="btn-view" title="View Invoice">👁 View</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete invoice <?= htmlspecialchars($inv['invoice_no'], ENT_QUOTES, 'UTF-8') ?>? This cannot be undone.');">
                                <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
                                <input type="hidden" name="delete_invoice" value="1">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                <button type="submit" class="btn-delete" title="Delete Invoice">🗑 Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterSt) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
