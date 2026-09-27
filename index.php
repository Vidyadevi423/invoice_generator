<?php
// ============================================
// index.php — Invoice Creation Page
// TechForge Solutions — Invoice Generator
// ============================================
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Generator — TechForge Solutions</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- jsPDF for PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>

<!-- ====== TOP NAVBAR ====== -->
<nav class="navbar">
    <div class="navbar-brand">
        <span class="brand-icon">⚡</span>
        <span class="brand-name"><?= COMPANY_NAME ?></span>
    </div>
    <div class="navbar-links">
        <a href="index.php" class="nav-link active">New Invoice</a>
        <a href="admin.php" class="nav-link">Admin Panel</a>
    </div>
</nav>

<!-- ====== MAIN LAYOUT ====== -->
<div class="app-container">

    <!-- LEFT: FORM PANEL -->
    <div class="form-panel">
        <div class="panel-header">
            <h1 class="panel-title">Create Invoice</h1>
            <p class="panel-subtitle">Fill in the details below</p>
        </div>

        <form id="invoiceForm" novalidate>

            <!-- CUSTOMER SECTION -->
            <div class="form-section">
                <h3 class="section-title">
                    <span class="section-num">01</span> Customer Details
                </h3>

                <div class="form-group">
                    <label for="customerName">Customer Name <span class="required">*</span></label>
                    <input type="text" id="customerName" name="customerName"
                           placeholder="e.g. Rajesh Kumar" maxlength="150">
                    <span class="error-msg" id="err-customerName"></span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customerEmail">Email Address</label>
                        <input type="email" id="customerEmail" name="customerEmail"
                               placeholder="email@example.com">
                        <span class="error-msg" id="err-customerEmail"></span>
                    </div>
                    <div class="form-group">
                        <label for="customerPhone">Phone Number</label>
                        <input type="text" id="customerPhone" name="customerPhone"
                               placeholder="+91 98765 43210" maxlength="20">
                        <span class="error-msg" id="err-customerPhone"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="customerAddress">Address</label>
                    <textarea id="customerAddress" name="customerAddress"
                              rows="2" placeholder="Street, City, State, PIN"></textarea>
                </div>
            </div>

            <!-- ITEMS SECTION -->
            <div class="form-section">
                <h3 class="section-title">
                    <span class="section-num">02</span> Invoice Items
                </h3>

                <!-- Items Table Header -->
                <div class="items-header">
                    <span class="col-name">Item / Service</span>
                    <span class="col-qty">Qty</span>
                    <span class="col-price">Unit Price</span>
                    <span class="col-total">Total</span>
                    <span class="col-action"></span>
                </div>

                <!-- Items will be added here -->
                <div id="itemsContainer">
                    <!-- Default first row -->
                    <div class="item-row" id="item-1">
                        <input type="text" class="item-name" placeholder="Product or service name" maxlength="255">
                        <input type="number" class="item-qty" placeholder="1" min="0.01" step="0.01" value="1">
                        <input type="number" class="item-price" placeholder="0.00" min="0" step="0.01" value="">
                        <span class="item-total">₹0.00</span>
                        <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove">✕</button>
                    </div>
                </div>

                <button type="button" class="btn-add-item" id="btnAddItem" onclick="addItem()">
                    + Add Another Item
                </button>
            </div>

            <!-- TAX & NOTES SECTION -->
            <div class="form-section">
                <h3 class="section-title">
                    <span class="section-num">03</span> Tax & Notes
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="taxPercent">GST / Tax Rate (%)</label>
                        <input type="number" id="taxPercent" name="taxPercent"
                               value="18" min="0" max="100" step="0.5"
                               placeholder="18">
                        <span class="error-msg" id="err-taxPercent"></span>
                    </div>
                    <div class="form-group">
                        <label for="invoiceStatus">Payment Status</label>
                        <select id="invoiceStatus" name="invoiceStatus">
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                            <option value="draft">Draft</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes / Payment Terms</label>
                    <textarea id="notes" name="notes" rows="2"
                              placeholder="e.g. Payment due within 30 days. Thank you for your business!"></textarea>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="clearForm()">
                    Clear Form
                </button>
                <button type="submit" class="btn-primary" id="btnGenerate">
                    Generate Invoice →
                </button>
            </div>

        </form>
    </div><!-- /form-panel -->

    <!-- RIGHT: PREVIEW PANEL -->
    <div class="preview-panel">
        <div class="preview-header">
            <h2 class="preview-title">Live Preview</h2>
            <div class="preview-actions" id="previewActions" style="display:none;">
                <button class="btn-download" onclick="downloadPDF()">
                    ⬇ Download PDF
                </button>
                <button class="btn-print" onclick="window.print()">
                    🖨 Print
                </button>
                <button class="btn-save" onclick="saveInvoice()">
                    💾 Save to DB
                </button>
            </div>
        </div>

        <!-- Invoice Preview Area -->
        <div class="invoice-preview" id="invoicePreview">

            <!-- EMPTY STATE -->
            <div class="empty-state" id="emptyState">
                <div class="empty-icon">📄</div>
                <p>Fill the form to see<br>your invoice preview</p>
            </div>

            <!-- INVOICE DOCUMENT (hidden until generated) -->
            <div class="invoice-doc" id="invoiceDoc" style="display:none;">

                <!-- Invoice Header -->
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
                            <tr>
                                <td>Invoice No:</td>
                                <td><strong id="previewInvNo">—</strong></td>
                            </tr>
                            <tr>
                                <td>Date:</td>
                                <td><strong id="previewDate">—</strong></td>
                            </tr>
                            <tr>
                                <td>Status:</td>
                                <td><span class="status-badge" id="previewStatus">—</span></td>
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
                        <p class="bill-name" id="previewCustName">Customer Name</p>
                        <p class="bill-detail" id="previewCustEmail"></p>
                        <p class="bill-detail" id="previewCustPhone"></p>
                        <p class="bill-detail" id="previewCustAddr"></p>
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
                    <tbody id="previewItemsBody">
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="inv-totals">
                    <div class="inv-notes" id="previewNotesBox">
                        <p class="notes-label">Notes</p>
                        <p class="notes-text" id="previewNotes"></p>
                    </div>
                    <div class="inv-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="previewSubtotal">₹0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>GST (<span id="previewTaxPct">18</span>%)</span>
                            <span id="previewTaxAmt">₹0.00</span>
                        </div>
                        <div class="summary-row total-row">
                            <span>TOTAL</span>
                            <span id="previewTotal">₹0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="inv-footer">
                    <p>Thank you for your business! 🙏</p>
                    <p class="inv-footer-brand">Generated by <?= COMPANY_NAME ?> Invoice System</p>
                </div>

            </div><!-- /invoice-doc -->
        </div><!-- /invoice-preview -->

        <!-- Save Success Message -->
        <div class="save-toast" id="saveToast"></div>

    </div><!-- /preview-panel -->

</div><!-- /app-container -->

<script src="js/app.js"></script>
</body>
</html>
