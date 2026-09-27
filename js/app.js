
// ============================================
// app.js — Invoice Generator JavaScript
// TechForge Solutions — Invoice Generator
// ============================================

"use strict";

// ---- State ----
let itemCount   = 1;
let invoiceData = null; // Stores current invoice after generation

// ---- DOM Ready ----
document.addEventListener('DOMContentLoaded', () => {
    setupLivePreview();
    setupFormSubmit();
    updateCalculations();
});

// ====================================================
// LIVE PREVIEW — Updates preview as user types
// ====================================================
function setupLivePreview() {
    // Watch all form inputs for changes
    document.getElementById('invoiceForm').addEventListener('input', () => {
        updateCalculations();
    });
    document.getElementById('invoiceStatus').addEventListener('change', () => {
        updateCalculations();
    });
}

// ====================================================
// ADD NEW ITEM ROW
// ====================================================
function addItem() {
    itemCount++;
    const container = document.getElementById('itemsContainer');
    const row       = document.createElement('div');
    row.className   = 'item-row';
    row.id          = `item-${itemCount}`;

    row.innerHTML = `
        <input type="text"   class="item-name"  placeholder="Product or service name" maxlength="255">
        <input type="number" class="item-qty"   placeholder="1"    min="0.01" step="0.01" value="1">
        <input type="number" class="item-price" placeholder="0.00" min="0"    step="0.01" value="">
        <span class="item-total">₹0.00</span>
        <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove">✕</button>
    `;

    container.appendChild(row);

    // Add live listeners to new row
    row.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', updateCalculations);
    });

    // Animate in
    row.style.opacity = '0';
    row.style.transform = 'translateY(-8px)';
    setTimeout(() => {
        row.style.transition = 'all 0.2s ease';
        row.style.opacity    = '1';
        row.style.transform  = 'translateY(0)';
    }, 10);

    updateCalculations();
}

// ====================================================
// REMOVE ITEM ROW
// ====================================================
function removeItem(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length <= 1) {
        showToast('At least one item is required.', 'error');
        return;
    }
    const row = btn.closest('.item-row');
    row.style.transition = 'all 0.2s ease';
    row.style.opacity    = '0';
    row.style.transform  = 'translateX(20px)';
    setTimeout(() => { row.remove(); updateCalculations(); }, 200);
}

// ====================================================
// UPDATE CALCULATIONS & LIVE PREVIEW
// ====================================================
function updateCalculations() {
    const rows     = document.querySelectorAll('.item-row');
    let subtotal   = 0;

    // Calculate each row total
    rows.forEach(row => {
        const qty   = parseFloat(row.querySelector('.item-qty')?.value)   || 0;
        const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
        const total = qty * price;
        const span  = row.querySelector('.item-total');
        if (span) span.textContent = formatCurrency(total);
        subtotal += total;
    });

    const taxPct   = parseFloat(document.getElementById('taxPercent')?.value) || 0;
    const taxAmt   = subtotal * (taxPct / 100);
    const total    = subtotal + taxAmt;

    // Update preview
    updatePreview(subtotal, taxPct, taxAmt, total);
}

// ====================================================
// UPDATE PREVIEW PANEL
// ====================================================
function updatePreview(subtotal, taxPct, taxAmt, total) {
    const custName = document.getElementById('customerName')?.value.trim() || '';
    const hasData  = custName || getItemsData().some(i => i.name);

    if (!hasData) {
        // Show empty state
        document.getElementById('emptyState').style.display = 'flex';
        document.getElementById('invoiceDoc').style.display  = 'none';
        document.getElementById('previewActions').style.display = 'none';
        return;
    }

    // Show invoice doc
    document.getElementById('emptyState').style.display    = 'none';
    document.getElementById('invoiceDoc').style.display     = 'block';
    document.getElementById('previewActions').style.display = 'flex';

    // Fill customer info
    document.getElementById('previewCustName').textContent  = custName || '—';
    document.getElementById('previewCustEmail').textContent = document.getElementById('customerEmail')?.value || '';
    document.getElementById('previewCustPhone').textContent = document.getElementById('customerPhone')?.value || '';
    document.getElementById('previewCustAddr').textContent  = document.getElementById('customerAddress')?.value || '';

    // Invoice meta
    document.getElementById('previewDate').textContent = formatDate(new Date());

    // Status badge
    const status     = document.getElementById('invoiceStatus')?.value || 'unpaid';
    const statusEl   = document.getElementById('previewStatus');
    const statusMap  = { paid: '#22c55e', unpaid: '#f59e0b', draft: '#94a3b8', cancelled: '#ef4444' };
    const color      = statusMap[status] || '#94a3b8';
    statusEl.textContent = status.toUpperCase();
    statusEl.style.background   = color + '20';
    statusEl.style.color        = color;
    statusEl.style.borderColor  = color + '40';

    // Invoice number (will be final after save)
    const invNoEl = document.getElementById('previewInvNo');
    if (!invNoEl.dataset.final) invNoEl.textContent = 'PREVIEW';

    // Fill items table
    const tbody  = document.getElementById('previewItemsBody');
    tbody.innerHTML = '';
    const items  = getItemsData();

    items.forEach((item, idx) => {
        if (!item.name && !item.price) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td>${escapeHtml(item.name || '—')}</td>
            <td>${item.qty}</td>
            <td>${formatCurrency(item.price)}</td>
            <td>${formatCurrency(item.qty * item.price)}</td>
        `;
        tbody.appendChild(tr);
    });

    // Totals
    document.getElementById('previewSubtotal').textContent = formatCurrency(subtotal);
    document.getElementById('previewTaxPct').textContent   = taxPct;
    document.getElementById('previewTaxAmt').textContent   = formatCurrency(taxAmt);
    document.getElementById('previewTotal').textContent    = formatCurrency(total);

    // Notes
    const notes    = document.getElementById('notes')?.value || '';
    const notesBox = document.getElementById('previewNotesBox');
    document.getElementById('previewNotes').textContent = notes;
    notesBox.style.display = notes ? 'block' : 'none';
}

// ====================================================
// FORM SUBMIT — Validate + Generate Invoice
// ====================================================
function setupFormSubmit() {
    document.getElementById('invoiceForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (validateForm()) {
            generateInvoice();
        }
    });
}

// ====================================================
// FORM VALIDATION
// ====================================================
function validateForm() {
    let valid = true;
    clearErrors();

    // Customer name
    const name = document.getElementById('customerName').value.trim();
    if (!name) {
        showError('err-customerName', 'Customer name is required.');
        valid = false;
    } else if (name.length < 2) {
        showError('err-customerName', 'Name must be at least 2 characters.');
        valid = false;
    }

    // Email (optional but validate format)
    const email = document.getElementById('customerEmail').value.trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showError('err-customerEmail', 'Enter a valid email address.');
        valid = false;
    }

    // Phone (optional but validate format)
    const phone = document.getElementById('customerPhone').value.trim();
    if (phone && !/^[\d\s\+\-\(\)]{7,20}$/.test(phone)) {
        showError('err-customerPhone', 'Enter a valid phone number.');
        valid = false;
    }

    // Tax rate
    const tax = parseFloat(document.getElementById('taxPercent').value);
    if (isNaN(tax) || tax < 0 || tax > 100) {
        showError('err-taxPercent', 'Tax must be between 0 and 100.');
        valid = false;
    }

    // Items — at least one with name and price
    const items    = getItemsData();
    let hasItem    = false;
    let itemErrors = false;

    items.forEach((item, idx) => {
        const row = document.querySelectorAll('.item-row')[idx];
        if (!row) return;
        const nameInput  = row.querySelector('.item-name');
        const priceInput = row.querySelector('.item-price');
        const qtyInput   = row.querySelector('.item-qty');

        if (item.name || item.price > 0) {
            hasItem = true;
            if (!item.name) {
                nameInput.classList.add('input-error');
                itemErrors = true;
            }
            if (item.price <= 0) {
                priceInput.classList.add('input-error');
                itemErrors = true;
            }
            if (item.qty <= 0) {
                qtyInput.classList.add('input-error');
                itemErrors = true;
            }
        }
    });

    if (!hasItem) {
        showToast('Please add at least one item with name and price.', 'error');
        valid = false;
    } else if (itemErrors) {
        showToast('Please fill all item fields correctly.', 'error');
        valid = false;
    }

    return valid;
}

// ====================================================
// GENERATE INVOICE — Show final invoice
// ====================================================
function generateInvoice() {
    const invNoEl      = document.getElementById('previewInvNo');
    invNoEl.textContent = 'GENERATING...';
    invNoEl.dataset.final = '';

    // Small delay for UX feel
    setTimeout(() => {
        const timestamp    = Date.now();
        const tempNo       = 'PREV-' + timestamp.toString().slice(-6);
        invNoEl.textContent      = tempNo;
        invNoEl.dataset.final    = '1';

        showToast('Invoice preview ready! Click "Save to DB" to save.', 'success');

        // Scroll to preview on mobile
        if (window.innerWidth < 1024) {
            document.querySelector('.preview-panel').scrollIntoView({ behavior: 'smooth' });
        }
    }, 400);
}

// ====================================================
// SAVE INVOICE TO DATABASE
// ====================================================
function saveInvoice() {
    if (!validateForm()) return;

    const btn = document.querySelector('.btn-save');
    btn.textContent  = '⏳ Saving...';
    btn.disabled     = true;

    const items = getItemsData().filter(i => i.name && i.price > 0);
    const sub   = items.reduce((s, i) => s + i.qty * i.price, 0);
    const taxP  = parseFloat(document.getElementById('taxPercent').value) || 0;
    const taxA  = sub * (taxP / 100);

    const payload = {
        customerName:    document.getElementById('customerName').value.trim(),
        customerEmail:   document.getElementById('customerEmail').value.trim(),
        customerPhone:   document.getElementById('customerPhone').value.trim(),
        customerAddress: document.getElementById('customerAddress').value.trim(),
        taxPercent:      taxP,
        notes:           document.getElementById('notes').value.trim(),
        status:          document.getElementById('invoiceStatus').value,
        subtotal:        sub,
        taxAmount:       taxA,
        total:           sub + taxA,
        items:           items,
    };

    fetch('save_invoice.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update invoice number in preview
            const invNoEl = document.getElementById('previewInvNo');
            invNoEl.textContent = data.invoiceNo;

            showToast('✅ Invoice ' + data.invoiceNo + ' saved! Redirecting...', 'success');

            setTimeout(() => {
                window.location.href = data.viewUrl;
            }, 1800);
        } else {
            showToast('❌ Error: ' + data.message, 'error');
            btn.textContent = '💾 Save to DB';
            btn.disabled    = false;
        }
    })
    .catch(err => {
        showToast('❌ Network error. Check your connection.', 'error');
        btn.textContent = '💾 Save to DB';
        btn.disabled    = false;
    });
}

// ====================================================
// DOWNLOAD PDF
// ====================================================
function downloadPDF() {
    const { jsPDF }  = window.jspdf;
    const element    = document.getElementById('invoiceDoc');
    const custName   = document.getElementById('customerName')?.value.trim() || 'Invoice';
    const invNo      = document.getElementById('previewInvNo')?.textContent || 'Invoice';

    showToast('⏳ Generating PDF, please wait...', 'info');

    html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    .then(canvas => {
        const pdf     = new jsPDF('p', 'mm', 'a4');
        const pdfW    = pdf.internal.pageSize.getWidth();
        const pdfPageH = pdf.internal.pageSize.getHeight();
        const pageCanvasHeight = Math.floor(canvas.width * pdfPageH / pdfW);
        let sourceY = 0;

        // Slice the rendered canvas into A4-sized images so each PDF page
        // contains only its own portion of the invoice.
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

        const filename = `${invNo}-${custName.replace(/\s+/g, '-')}.pdf`;
        pdf.save(filename);
        showToast('✅ PDF downloaded!', 'success');
    })
    .catch(() => showToast('❌ PDF generation failed. Try again.', 'error'));
}

// ====================================================
// CLEAR FORM
// ====================================================
function clearForm() {
    if (!confirm('Clear all form data? This cannot be undone.')) return;

    document.getElementById('invoiceForm').reset();

    // Reset items to single row
    const container = document.getElementById('itemsContainer');
    container.innerHTML = `
        <div class="item-row" id="item-1">
            <input type="text"   class="item-name"  placeholder="Product or service name" maxlength="255">
            <input type="number" class="item-qty"   placeholder="1"    min="0.01" step="0.01" value="1">
            <input type="number" class="item-price" placeholder="0.00" min="0"    step="0.01" value="">
            <span class="item-total">₹0.00</span>
            <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove">✕</button>
        </div>
    `;
    itemCount = 1;

    // Hide preview
    document.getElementById('emptyState').style.display    = 'flex';
    document.getElementById('invoiceDoc').style.display     = 'none';
    document.getElementById('previewActions').style.display = 'none';

    clearErrors();
    updateCalculations();
}

// ====================================================
// HELPER FUNCTIONS
// ====================================================

// Get all items data from rows
function getItemsData() {
    const rows = document.querySelectorAll('.item-row');
    return Array.from(rows).map(row => ({
        name:  row.querySelector('.item-name')?.value.trim() || '',
        qty:   parseFloat(row.querySelector('.item-qty')?.value)   || 0,
        price: parseFloat(row.querySelector('.item-price')?.value) || 0,
    }));
}

// Format number as currency
function formatCurrency(amount) {
    return '₹' + parseFloat(amount || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Format date as "15 Jan 2025"
function formatDate(date) {
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Show inline error message
function showError(id, message) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = message;
        el.style.display = 'block';
        el.closest('.form-group')?.querySelector('input, textarea')?.classList.add('input-error');
    }
}

// Clear all error messages
function clearErrors() {
    document.querySelectorAll('.error-msg').forEach(el => {
        el.textContent  = '';
        el.style.display = 'none';
    });
    document.querySelectorAll('.input-error').forEach(el => {
        el.classList.remove('input-error');
    });
}

// Show toast notification
function showToast(message, type = 'info') {
    let toast = document.getElementById('saveToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'saveToast';
        document.body.appendChild(toast);
    }

    toast.textContent  = message;
    toast.className    = `save-toast toast-${type} toast-show`;

    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.remove('toast-show');
    }, 3500);
}
