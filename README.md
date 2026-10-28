# PrintPulse ERP (پرنٹ پلس ای آر پی)
## Multi-Business Printer Shop Management, Repairing, Refilling & Double-Entry Accounting SaaS

**Version:** 2.0  
**Stack:** Core PHP (Non-MVC), MySQL 8 (PDO Prepared Statements), HTML5, CSS3, Vanilla JavaScript, AJAX  
**Deployment:** Multi-tenant shared database (`pshop_db`) on WAMP/LAMP  
**Bilingual:** Full English & Urdu (اردو) with specialized Nastaliq typography & line-height normalization  
**Default Invoice Layout:** A5 Portrait (Faithful reproduction of physical reference bill)  
**Supported Invoice Formats:** A5 (Default), A4 (Full Page), 80mm Thermal POS  

---

## 1. Distinct Software Identity

- **Name:** **PrintPulse ERP / پرنٹ پلس ای آر پی**
- **Brand Mark & Logo:** Custom SVG vector logo (`assets/images/logo.svg`) featuring a modern printer body, CMYK pulse beam, precision gear, and bilingual SaaS tagline.
- **Favicon:** Custom SVG icon (`assets/images/favicon.svg`) with cyan/amber gradient.

---

## 2. Complete Bilingual Support (English / اردو)

- **Instant Language Toggle:** Clickable anywhere on the top navigation bar (`🇵🇰 اردو` / `🇬🇧 English`).
- **Typography & No Clipping:** Incorporates Google Fonts `Noto Nastaliq Urdu` & `Noto Sans Arabic` with tuned CSS line-height (`1.85 - 2.15`) and vertical padding so Urdu letters with long ascenders and descenders (ے, ج, چ, خ) never clip or overlap.
- **Natural Business Terminology:** 
  - ڈبل انٹری کھاتہ داری
  - میزان پڑتال (Trial Balance)
  - نفع و نقصان (Profit & Loss)
  - روکڑ بہی (Cash Book)
  - پرنٹر مرمت و جاب کارڈز
  - ٹونر ریفلنگ

---

## 3. Reference Invoice Reproduction (A5 Default)

Faithfully reproduces the uploaded physical reference bill (`media_1793196481236_262c48db.png`):
- **Top Right:** `Invoice / Bill` badge.
- **Top Left:** Dynamic business logo and brand title (`FAST PRINTERS & COMPUTERS`).
- **Meta Fields:** `Invoice #`, `Date: 20-10-2026`, and `M/s [Customer Name]`.
- **Ruled Table Grid:** Columns `NO.`, `DESCRIPTION`, `QTY.`, `RATE`, `AMOUNT` with authentic notebook pad lines extending down the page.
- **Bottom-Right Totals Box:** `TOTAL`, `DEPOSIT` (Paid/Advance), `BALANCE`.
- **Bottom-Left Notice & Signature:**
  - `NOTE: RECONDITIONED OR REPAIRED GOODS ONCE SOLD ARE NOT RETURNABLE NOR EXCHANGEABLE. NO WARRANTY OR GUARANTEE FOR REPAIRED ACCESSORIES.`
  - `Received above items in order & good condition.`
  - `Received By: ____________________`
- **Bottom Footer Banner:** Black/dark bar containing Address & Contact Cell numbers.
- **Dynamic Watermark:** Business name rendered diagonally with customizable opacity (default 0.08) and rotation (-25 deg) in Business Settings.

---

## 4. Multi-Business Isolation & Tenant Switching

- **Single MySQL Database:** All records carry `business_id` foreign key.
- **Zero Cross-Tenant Leakage:** Server-side validation on every query. Business A cannot view, print, or edit Business B's data.
- **Global Business Switcher:** Top-bar dropdown instantly switches active business context without session cross-contamination.
- **Pre-Seeded Businesses:**
  1. `FAST PRINTERS & COMPUTERS` (Gujranwala - matching reference bill)
  2. `AL-MADINA PRINT & TONER SOLUTIONS` (Lahore - for isolation testing)

---

## 5. Feature Toggles (Configurable Workflows)

Under **Settings > Business Settings**, each business can enable or disable modules:
1. `repair_job_management_enabled`: When disabled, direct repair invoices remain fully available with double-entry accounting.
2. `refilling_job_management_enabled`: When disabled, direct refill billing works without job cards.
3. `inventory_enabled`: Toggle stock tracking.
4. `barcode_enabled`: Toggle POS barcode search.
5. `daily_closing_enabled`: Daily drawer cash closing.
6. `watermark_enabled`: Toggle dynamic watermark on invoices.

---

## 6. Flexible Service Billing & Autocomplete

- **Free-Text & Multiple Lines:** Enter any service description, labour charges, spare parts, or refill items.
- **Database-Backed Autocomplete:** Automatically searches existing business descriptions, tracks usage counts, and suggests rates via AJAX (`ajax/search_service_desc.php`).
- **Usage Counting:** Most frequently billed services rise to top of suggestions.

---

## 7. Genuine Double-Entry Accounting

- **Balanced Postings:** Every transaction automatically validates `Total Debits == Total Credits`.
- **Chart of Accounts:** Configured Assets (1000), Liabilities (2000), Equity (3000), Income (4000), COGS (5000), and Operating Expenses (6000).
- **Control Accounts Reconciled:** Customer receipts debit Cash/Bank and credit Accounts Receivable (never double-counting revenue). Supplier payments debit Accounts Payable and credit Cash/Bank (never double-counting purchases).
- **Financial Reports:**
  - Real-time Balanced Trial Balance
  - Profit and Loss Statement (Gross Profit, COGS, Net Profit)
  - Balance Sheet (Assets = Liabilities + Equity)
  - Cash Book (Daily running cash ledger)
  - General Ledger drilldown for any account

---

## 8. Database Backup & Restore

- **Full SQL Backup:** Generates complete schema and data SQL dump in pure PHP (`backups/pshop_backup_*.sql`).
- **Admin-Protected Restore:** Restricted to platform administrator with confirmation safeguard.
- **Business-Level Export:** One-click JSON export of tenant-specific records (`backup.php?export_business=1`).

---

## 9. Default Login Credentials

- **URL:** `http://localhost/pshop`
- **Username:** `admin`
- **Password:** `admin123`
