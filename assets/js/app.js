/**
 * PrintPulse ERP - Client-Side Script
 */

document.addEventListener('DOMContentLoaded', function () {
  // Business Switcher
  const bizSelect = document.getElementById('globalBusinessSwitcher');
  if (bizSelect) {
    bizSelect.addEventListener('change', function () {
      const bid = this.value;
      fetch((window.APP_BASE_URL || '') + 'ajax/switch_business.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'business_id=' + encodeURIComponent(bid)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.location.reload();
        } else {
          alert(data.message || 'Error switching business');
        }
      });
    });
  }

  // Initialize Autocomplete for Service Descriptions
  initServiceAutocomplete();

  // Dynamic Invoice Calculation
  initInvoiceCalculations();

  // Auto-Select Input Values on Focus/Click (Especially for default 0/0.00, discount, paid, qty, rate)
  initAutoSelectInputs();

  // Navigation Dropdowns (Click & Touch support)
  initNavDropdowns();
});

// Navigation Dropdown Handler
function initNavDropdowns() {
  document.querySelectorAll('.navbar-sub .nav-item').forEach(function(item) {
    var dropdown = item.querySelector('.nav-dropdown');
    var link = item.querySelector('.nav-link');
    if (dropdown && link) {
      link.addEventListener('click', function(e) {
        // Toggle on click/tap if pointer is touch or window width <= 1024
        if (window.innerWidth <= 1024 || 'ontouchstart' in window) {
          e.preventDefault();
          var isOpen = item.classList.contains('open');
          document.querySelectorAll('.navbar-sub .nav-item.open').forEach(function(el) {
            el.classList.remove('open');
          });
          if (!isOpen) {
            item.classList.add('open');
          }
        }
      });
    }
  });

  // Close dropdown on outside click
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.navbar-sub .nav-item')) {
      document.querySelectorAll('.navbar-sub .nav-item.open').forEach(function(el) {
        el.classList.remove('open');
      });
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.navbar-sub .nav-item.open').forEach(function(el) {
        el.classList.remove('open');
      });
    }
  });
}

// Auto-Select Input Values on Focus/Click
function initAutoSelectInputs() {
  function isTargetSelectable(input) {
    if (!input || typeof input.select !== 'function') return false;
    if (input.readOnly || input.disabled) return false;
    var type = (input.type || 'text').toLowerCase();
    if (['button', 'submit', 'checkbox', 'radio', 'hidden', 'file', 'date', 'time'].indexOf(type) !== -1) return false;

    var val = (input.value !== undefined && input.value !== null) ? String(input.value).trim() : '';

    // If current value is 0, 0.00, 0.0, or equivalent zero
    if (val === '0' || val === '0.00' || val === '0.0' || (!isNaN(val) && parseFloat(val) === 0 && val !== '')) {
      return true;
    }

    // Also auto-select for numeric inputs (discount, paid, quantity, rate, tax, amount)
    if (type === 'number' || 
        input.classList.contains('item-qty') || 
        input.classList.contains('item-rate') || 
        input.classList.contains('item-amount') || 
        /discount|paid|tax|rate|qty|price|amount/i.test(input.name || '') || 
        /discount|paid|tax|rate|qty|price|amount/i.test(input.id || '')) {
      return true;
    }

    return false;
  }

  var isMouseFocus = false;

  // Listen to mousedown to detect if focus was initiated by mouse click
  document.addEventListener('mousedown', function (e) {
    var target = e.target;
    if (target && target.tagName === 'INPUT' && isTargetSelectable(target)) {
      isMouseFocus = true;
    }
  }, true);

  // Focusin event (handles keyboard Tab navigation and programmatic focus)
  document.addEventListener('focusin', function (e) {
    var target = e.target;
    if (!target || target.tagName !== 'INPUT' || !isTargetSelectable(target)) return;

    setTimeout(function () {
      if (document.activeElement === target && typeof target.select === 'function') {
        target.select();
      }
    }, 30);
  }, true);

  // Mouseup event (handles mouse click text selection without being deselected by browser default)
  document.addEventListener('mouseup', function (e) {
    var target = e.target;
    if (target && target.tagName === 'INPUT' && isTargetSelectable(target)) {
      if (isMouseFocus) {
        setTimeout(function () {
          if (document.activeElement === target && typeof target.select === 'function') {
            target.select();
          }
          isMouseFocus = false;
        }, 15);
      }
    }
  }, true);

  // Click event fallback
  document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || target.tagName !== 'INPUT') return;
    var val = (target.value !== undefined && target.value !== null) ? String(target.value).trim() : '';
    if (val === '0' || val === '0.00' || val === '0.0' || (!isNaN(val) && parseFloat(val) === 0 && val !== '')) {
      if (typeof target.select === 'function') {
        target.select();
      }
    }
  }, true);
}

// Autocomplete Engine
function initServiceAutocomplete() {
  const inputs = document.querySelectorAll('.service-autocomplete-input:not([data-ac-bound])');

  inputs.forEach(input => {
    input.setAttribute('data-ac-bound', '1');
    let dropdown = input.parentElement.querySelector('.autocomplete-dropdown');
    if (!dropdown) {
      dropdown = document.createElement('div');
      dropdown.className = 'autocomplete-dropdown';
      input.parentElement.appendChild(dropdown);
    }

    let debounceTimer;

    function doSearch() {
      const query = input.value.trim();
      clearTimeout(debounceTimer);

      if (query.length < 1) {
        dropdown.style.display = 'none';
        return;
      }

      debounceTimer = setTimeout(() => {
        fetch((window.APP_BASE_URL || '') + 'ajax/search_service_desc.php?q=' + encodeURIComponent(query))
          .then(res => res.json())
          .then(items => {
            dropdown.innerHTML = '';
            if (items.length > 0) {
              items.forEach(item => {
                const opt = document.createElement('div');
                opt.className = 'autocomplete-item';
                const typeBadge = item.line_type ? `<span class="badge badge-secondary" style="font-size:10px; padding:2px 6px; text-transform:uppercase;">${escapeHtml(item.line_type)}</span>` : '';
                opt.innerHTML = `
                  <div style="display:flex; align-items:center; gap:8px;">
                    <strong>${escapeHtml(item.description)}</strong>
                    ${typeBadge}
                  </div>
                  <span style="font-weight:700; color:var(--primary); font-size:12.5px; white-space:nowrap; margin-left:12px;">Rs ${parseFloat(item.default_rate).toLocaleString()}</span>
                `;
                opt.addEventListener('mousedown', function (e) {
                  e.preventDefault();
                  input.value = item.description;

                  // Find sibling rate input and populate
                  const row = input.closest('tr');
                  if (row) {
                    const rateInput = row.querySelector('.item-rate');
                    if (rateInput && (!rateInput.value || rateInput.value == '0.00')) {
                      rateInput.value = item.default_rate;
                    }
                    const typeInput = row.querySelector('.item-line-type');
                    if (typeInput && item.line_type) {
                      typeInput.value = item.line_type;
                    }
                    calcRowTotal(row);
                  }

                  dropdown.style.display = 'none';
                });
                dropdown.appendChild(opt);
              });
              dropdown.style.display = 'block';
            } else {
              dropdown.style.display = 'none';
            }
          });
      }, 200);
    }

    input.addEventListener('input', doSearch);
    input.addEventListener('focus', function () {
      if (this.value.trim().length >= 1) {
        doSearch();
      }
    });

    input.addEventListener('blur', function () {
      setTimeout(() => { dropdown.style.display = 'none'; }, 220);
    });
  });
}

// Calculate individual row total
function calcRowTotal(row) {
  const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
  const rate = parseFloat(row.querySelector('.item-rate')?.value) || 0;
  const total = qty * rate;
  const totalInput = row.querySelector('.item-amount');
  if (totalInput) {
    totalInput.value = total.toFixed(2);
  }
  recalcInvoiceTotals();
}

// Recalculate Grand Totals
function recalcInvoiceTotals() {
  const amountInputs = document.querySelectorAll('.item-amount');
  let subtotal = 0;
  amountInputs.forEach(inp => {
    subtotal += parseFloat(inp.value) || 0;
  });

  const subtotalEl = document.getElementById('invSubtotal');
  if (subtotalEl) subtotalEl.value = subtotal.toFixed(2);

  const discount = parseFloat(document.getElementById('invDiscount')?.value) || 0;
  const tax = parseFloat(document.getElementById('invTax')?.value) || 0;

  const total = Math.max(0, subtotal - discount + tax);
  const totalEl = document.getElementById('invTotal');
  if (totalEl) totalEl.value = total.toFixed(2);

  const paid = parseFloat(document.getElementById('invPaid')?.value) || 0;
  const balance = Math.max(0, total - paid);
  const balanceEl = document.getElementById('invBalance');
  if (balanceEl) balanceEl.value = balance.toFixed(2);
}

function initInvoiceCalculations() {
  document.addEventListener('input', function (e) {
    if (e.target.matches('.item-qty, .item-rate')) {
      const row = e.target.closest('tr');
      if (row) calcRowTotal(row);
    } else if (e.target.matches('#invDiscount, #invTax, #invPaid')) {
      recalcInvoiceTotals();
    }
  });

  // Add Item Row
  const btnAdd = document.getElementById('btnAddRow');
  if (btnAdd) {
    btnAdd.addEventListener('click', function () {
      const tbody = document.getElementById('invoiceItemsTbody');
      if (!tbody) return;
      const rowCount = tbody.querySelectorAll('tr').length + 1;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="text-center row-num">${rowCount}</td>
        <td>
          <div class="autocomplete-wrapper">
            <input type="text" name="items[${rowCount}][description]" class="form-control service-autocomplete-input" placeholder="Service / Part / Toner" required autocomplete="off">
          </div>
        </td>
        <td>
          <select name="items[${rowCount}][line_type]" class="form-control item-line-type">
            <option value="service">Service</option>
            <option value="refill">Refill</option>
            <option value="part">Part</option>
            <option value="labour">Labour</option>
          </select>
        </td>
        <td><input type="number" step="0.01" name="items[${rowCount}][quantity]" class="form-control item-qty text-center" value="1"></td>
        <td><input type="number" step="0.01" name="items[${rowCount}][unit_price]" class="form-control item-rate text-right" value="0.00"></td>
        <td><input type="number" step="0.01" name="items[${rowCount}][total_amount]" class="form-control item-amount text-right" value="0.00" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-del-row">&times;</button></td>
      `;
      tbody.appendChild(tr);
      initServiceAutocomplete();
    });
  }

  // Delete Item Row
  document.addEventListener('click', function (e) {
    if (e.target.matches('.btn-del-row')) {
      const row = e.target.closest('tr');
      const tbody = row.parentElement;
      if (tbody.querySelectorAll('tr').length > 1) {
        row.remove();
        // renumber rows
        tbody.querySelectorAll('tr').forEach((r, idx) => {
          const numCell = r.querySelector('.row-num');
          if (numCell) numCell.textContent = idx + 1;
        });
        recalcInvoiceTotals();
      }
    }
  });
}

function escapeHtml(text) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return (text || '').replace(/[&<>"']/g, m => map[m]);
}
