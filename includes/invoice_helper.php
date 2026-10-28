<?php
/**
 * Invoice Management Helper
 * Handles Invoice Retrieval, Deletion with Financial & Stock Rollback, and Atomic Edits.
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/accounting_helper.php';
require_once __DIR__ . '/inventory_helper.php';

class InvoiceHelper {

    /**
     * Get Invoice and its Line Items with Tenant Verification
     */
    public static function getInvoiceWithItems($invoice_id, $business_id) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT i.*, c.name AS cust_company, c.address AS cust_address 
                               FROM invoices i 
                               LEFT JOIN customers c ON c.id = i.customer_id 
                               WHERE i.id = ? AND i.business_id = ?");
        $stmt->execute([(int)$invoice_id, (int)$business_id]);
        $inv = $stmt->fetch();

        if (!$inv) {
            throw new Exception("Invoice #{$invoice_id} not found or you are not authorized to access it.");
        }

        $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY line_number ASC, id ASC");
        $itemStmt->execute([(int)$invoice_id]);
        $items = $itemStmt->fetchAll();

        return [
            'invoice' => $inv,
            'items' => $items
        ];
    }

    /**
     * Unwind all financial, stock, and status impacts of an invoice
     */
    public static function unwindInvoice($invoice_id, $business_id) {
        $pdo = getDB();
        $data = self::getInvoiceWithItems($invoice_id, $business_id);
        $inv = $data['invoice'];
        $items = $data['items'];

        // 1. Reverse Customer Balance if credit/balance was billed
        if (!empty($inv['customer_id']) && (float)$inv['balance_amount'] > 0) {
            $pdo->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ? AND business_id = ?")
                ->execute([(float)$inv['balance_amount'], (int)$inv['customer_id'], (int)$business_id]);
        }

        // 2. Reverse Physical Product Stock Deductions
        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $pid = (int)$item['product_id'];
                $qty = (float)$item['quantity'];

                // Verify if physical tracked product
                $pStmt = $pdo->prepare("SELECT product_type FROM products WHERE id = ? AND business_id = ?");
                $pStmt->execute([$pid, (int)$business_id]);
                $ptype = $pStmt->fetchColumn();

                if ($ptype && $ptype !== 'service') {
                    $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ? AND business_id = ?")
                        ->execute([$qty, $pid, (int)$business_id]);
                }
            }
        }

        // Delete recorded stock movements for this invoice
        $pdo->prepare("DELETE FROM stock_movements WHERE business_id = ? AND reference_id = ? AND movement_type = 'sale'")
            ->execute([(int)$business_id, (int)$invoice_id]);

        // 3. Reverse and Delete Double-Entry Journal Entries
        AccountingEngine::reverseJournalEntryByReference((int)$business_id, ['sale', 'pos', 'repair', 'refill'], (int)$invoice_id);

        // 4. Restore Related Job Status if invoice was created from a Job Card
        if (!empty($inv['reference_job_id'])) {
            $jobId = (int)$inv['reference_job_id'];
            if ($inv['invoice_type'] === 'repair') {
                $pdo->prepare("UPDATE repair_jobs SET status = 'ready', delivery_date = NULL WHERE id = ? AND business_id = ?")
                    ->execute([$jobId, (int)$business_id]);
            } elseif ($inv['invoice_type'] === 'refill') {
                $pdo->prepare("UPDATE refill_jobs SET status = 'ready' WHERE id = ? AND business_id = ?")
                    ->execute([$jobId, (int)$business_id]);
            }
        }

        return $data;
    }

    /**
     * Delete an invoice permanently with complete transactional integrity
     */
    public static function deleteInvoice($invoice_id, $business_id, $user_id, $reason = '') {
        $pdo = getDB();
        $wasInTransaction = $pdo->inTransaction();
        if (!$wasInTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Unwind all ledger, inventory, customer, and job impacts
            $data = self::unwindInvoice($invoice_id, $business_id);
            $inv = $data['invoice'];

            // Delete line items
            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([(int)$invoice_id]);

            // Delete invoice
            $pdo->prepare("DELETE FROM invoices WHERE id = ? AND business_id = ?")->execute([(int)$invoice_id, (int)$business_id]);

            // Log in Audit Trail
            log_audit(
                'DELETE_INVOICE',
                'invoice',
                (int)$invoice_id,
                [
                    'invoice_number' => $inv['invoice_number'],
                    'invoice_type' => $inv['invoice_type'],
                    'customer_name' => $inv['customer_name'],
                    'total_amount' => (float)$inv['total_amount'],
                    'reason' => $reason
                ]
            );

            if (!$wasInTransaction) {
                $pdo->commit();
            }

            return true;
        } catch (Exception $e) {
            if (!$wasInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atomically Save Updated Invoice
     */
    public static function saveInvoiceEdit($invoice_id, $business_id, $user_id, array $data) {
        global $current_settings;
        $pdo = getDB();

        $invoice_id = (int)$invoice_id;
        $business_id = (int)$business_id;

        $wasInTransaction = $pdo->inTransaction();
        if (!$wasInTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // 1. Unwind previous financial and stock impact
            $old = self::unwindInvoice($invoice_id, $business_id);
            $oldInv = $old['invoice'];

            // Delete previous line items
            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoice_id]);

            // 2. Resolve Customer ID
            $customer_name = trim($data['customer_name'] ?? '');
            $customer_phone = trim($data['customer_phone'] ?? '');
            if (empty($customer_name)) {
                throw new Exception("Customer name is required.");
            }

            $custStmt = $pdo->prepare("SELECT id FROM customers WHERE business_id = ? AND name = ? LIMIT 1");
            $custStmt->execute([$business_id, $customer_name]);
            $customerId = $custStmt->fetchColumn();
            if (!$customerId) {
                $insCust = $pdo->prepare("INSERT INTO customers (business_id, name, phone) VALUES (?, ?, ?)");
                $insCust->execute([$business_id, $customer_name, $customer_phone]);
                $customerId = $pdo->lastInsertId();
            } elseif (!empty($customer_phone)) {
                $pdo->prepare("UPDATE customers SET phone = ? WHERE id = ? AND business_id = ?")
                    ->execute([$customer_phone, $customerId, $business_id]);
            }

            // 3. Process Line Items
            $rawItems = $data['items'] ?? [];
            if (empty($rawItems)) {
                throw new Exception("At least one line item is required.");
            }

            $subtotal = 0.0;
            $validItems = [];
            $cogs_total = 0.0;
            $serviceRevenue = 0.0;
            $partsRevenue = 0.0;
            $refillRevenue = 0.0;

            foreach ($rawItems as $it) {
                $desc = trim($it['description'] ?? '');
                $qty = (float)($it['quantity'] ?? 1);
                $rate = (float)($it['unit_price'] ?? 0);
                $pid = !empty($it['product_id']) ? (int)$it['product_id'] : null;
                $lineType = $it['line_type'] ?? 'product';
                $lineTotal = round($qty * $rate, 2);

                if (!empty($desc) && $lineTotal >= 0) {
                    $subtotal += $lineTotal;
                    $validItems[] = [
                        'product_id' => $pid,
                        'line_type' => $lineType,
                        'description' => $desc,
                        'quantity' => $qty,
                        'unit_price' => $rate,
                        'total_amount' => $lineTotal
                    ];
                }
            }

            if (empty($validItems)) {
                throw new Exception("Invoice must contain at least one valid item.");
            }

            $discount = max(0, (float)($data['discount'] ?? 0));
            $total_amount = max(0, round($subtotal - $discount, 2));
            $paid_amount = max(0, (float)($data['paid_amount'] ?? 0));
            $balance_amount = max(0, round($total_amount - $paid_amount, 2));
            $payment_method = $data['payment_method'] ?? 'cash';
            $invoice_date = $data['invoice_date'] ?? date('Y-m-d');
            $notes = trim($data['notes'] ?? '');

            // 4. Update Invoices table row
            $updInv = $pdo->prepare("UPDATE invoices SET 
                customer_id = ?, 
                customer_name = ?, 
                customer_phone = ?, 
                invoice_date = ?, 
                subtotal = ?, 
                discount = ?, 
                total_amount = ?, 
                paid_amount = ?, 
                balance_amount = ?, 
                payment_method = ?, 
                notes = ? 
                WHERE id = ? AND business_id = ?");
            $updInv->execute([
                $customerId,
                $customer_name,
                $customer_phone,
                $invoice_date,
                $subtotal,
                $discount,
                $total_amount,
                $paid_amount,
                $balance_amount,
                $payment_method,
                $notes,
                $invoice_id,
                $business_id
            ]);

            // 5. Insert New Line Items
            $insItem = $pdo->prepare("INSERT INTO invoice_items 
                (invoice_id, line_number, line_type, product_id, description, quantity, unit_price, total_amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $descCheckStmt = $pdo->prepare("SELECT id FROM service_descriptions WHERE business_id = ? AND description = ?");
            $descInsStmt = $pdo->prepare("INSERT INTO service_descriptions (business_id, description, default_rate, line_type, usage_count) VALUES (?, ?, ?, ?, 1)");
            $descUpdStmt = $pdo->prepare("UPDATE service_descriptions SET usage_count = usage_count + 1, last_used_at = NOW() WHERE id = ?");

            $lno = 1;
            foreach ($validItems as $v) {
                $insItem->execute([
                    $invoice_id,
                    $lno++,
                    $v['line_type'],
                    $v['product_id'],
                    $v['description'],
                    $v['quantity'],
                    $v['unit_price'],
                    $v['total_amount']
                ]);

                // Categorize revenue based on line type / invoice type
                if ($oldInv['invoice_type'] === 'repair') {
                    if (in_array($v['line_type'], ['part', 'product'])) {
                        $partsRevenue += $v['total_amount'];
                    } else {
                        $serviceRevenue += $v['total_amount'];
                    }
                } elseif ($oldInv['invoice_type'] === 'refill') {
                    if (in_array($v['line_type'], ['part', 'product'])) {
                        $partsRevenue += $v['total_amount'];
                    } else {
                        $refillRevenue += $v['total_amount'];
                    }
                }

                // If physical product, deduct stock and calculate COGS
                if ($v['product_id']) {
                    $pQuery = $pdo->prepare("SELECT purchase_price, product_type FROM products WHERE id = ? AND business_id = ?");
                    $pQuery->execute([$v['product_id'], $business_id]);
                    $prodRow = $pQuery->fetch();

                    if ($prodRow) {
                        $cost = (float)$prodRow['purchase_price'];
                        if ($prodRow['product_type'] !== 'service') {
                            $cogs_total += ($v['quantity'] * $cost);
                            InventoryHelper::recordMovement($business_id, $v['product_id'], 'sale', $v['quantity'], $cost, $invoice_id, "Sale {$oldInv['invoice_number']}");
                        }
                    }
                }

                // Save or update autocomplete description catalog for service/repair/refill
                if (in_array($oldInv['invoice_type'], ['repair', 'refill']) || in_array($v['line_type'], ['service', 'labour', 'refill'])) {
                    $descCheckStmt->execute([$business_id, $v['description']]);
                    $existingDescId = $descCheckStmt->fetchColumn();
                    if ($existingDescId) {
                        $descUpdStmt->execute([$existingDescId]);
                    } else {
                        $descInsStmt->execute([$business_id, $v['description'], $v['unit_price'], $v['line_type']]);
                    }
                }
            }

            // 6. Update Customer Balance
            if ($balance_amount > 0) {
                $pdo->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ? AND business_id = ?")
                    ->execute([$balance_amount, $customerId, $business_id]);
            }

            // If linked to job, mark delivered again
            if (!empty($oldInv['reference_job_id'])) {
                $jobId = (int)$oldInv['reference_job_id'];
                if ($oldInv['invoice_type'] === 'repair') {
                    $pdo->prepare("UPDATE repair_jobs SET status = 'delivered', delivery_date = ?, final_cost = ? WHERE id = ? AND business_id = ?")
                        ->execute([$invoice_date, $total_amount, $jobId, $business_id]);
                } elseif ($oldInv['invoice_type'] === 'refill') {
                    $pdo->prepare("UPDATE refill_jobs SET status = 'delivered' WHERE id = ? AND business_id = ?")
                        ->execute([$jobId, $business_id]);
                }
            }

            // 7. Double-Entry Accounting Journal Posting
            $cashAccId = AccountingEngine::getAccountIdByCode($business_id, '1010');
            $arAccId = AccountingEngine::getAccountIdByCode($business_id, '1030');
            $salesRevAccId = AccountingEngine::getAccountIdByCode($business_id, '4010');
            $invAccId = AccountingEngine::getAccountIdByCode($business_id, '1040');
            $cogsAccId = AccountingEngine::getAccountIdByCode($business_id, '5010');

            $journalLines = [];
            if ($paid_amount > 0) {
                $journalLines[] = [
                    'account_id' => $cashAccId,
                    'debit' => $paid_amount,
                    'credit' => 0.00,
                    'narration' => "Deposit for Invoice {$oldInv['invoice_number']}"
                ];
            }
            if ($balance_amount > 0) {
                $journalLines[] = [
                    'account_id' => $arAccId,
                    'debit' => $balance_amount,
                    'credit' => 0.00,
                    'narration' => "Receivable for Invoice {$oldInv['invoice_number']}"
                ];
            }

            if ($oldInv['invoice_type'] === 'repair') {
                $repairRevAccId = AccountingEngine::getAccountIdByCode($business_id, '4020');
                if ($serviceRevenue > 0) {
                    $journalLines[] = [
                        'account_id' => $repairRevAccId,
                        'debit' => 0.00,
                        'credit' => $serviceRevenue,
                        'narration' => "Repair Service Revenue {$oldInv['invoice_number']}"
                    ];
                }
                if ($partsRevenue > 0) {
                    $journalLines[] = [
                        'account_id' => $salesRevAccId,
                        'debit' => 0.00,
                        'credit' => $partsRevenue,
                        'narration' => "Repair Parts Revenue {$oldInv['invoice_number']}"
                    ];
                }
            } elseif ($oldInv['invoice_type'] === 'refill') {
                $refillRevAccId = AccountingEngine::getAccountIdByCode($business_id, '4030');
                if ($refillRevenue > 0) {
                    $journalLines[] = [
                        'account_id' => $refillRevAccId,
                        'debit' => 0.00,
                        'credit' => $refillRevenue,
                        'narration' => "Refill Revenue {$oldInv['invoice_number']}"
                    ];
                }
                if ($partsRevenue > 0) {
                    $journalLines[] = [
                        'account_id' => $salesRevAccId,
                        'debit' => 0.00,
                        'credit' => $partsRevenue,
                        'narration' => "Refill Parts Revenue {$oldInv['invoice_number']}"
                    ];
                }
            } else {
                // Sale or POS
                $journalLines[] = [
                    'account_id' => $salesRevAccId,
                    'debit' => 0.00,
                    'credit' => $total_amount,
                    'narration' => "Sales Revenue {$oldInv['invoice_number']}"
                ];

                if ($cogs_total > 0 && !empty($current_settings['inventory_enabled'])) {
                    $journalLines[] = [
                        'account_id' => $cogsAccId,
                        'debit' => $cogs_total,
                        'credit' => 0.00,
                        'narration' => "COGS for {$oldInv['invoice_number']}"
                    ];
                    $journalLines[] = [
                        'account_id' => $invAccId,
                        'debit' => 0.00,
                        'credit' => $cogs_total,
                        'narration' => "Inventory reduced for {$oldInv['invoice_number']}"
                    ];
                }
            }

            $refType = ($oldInv['invoice_type'] === 'pos') ? 'sale' : $oldInv['invoice_type'];
            AccountingEngine::postJournalEntry(
                $business_id,
                "JV-{$oldInv['invoice_number']}",
                $invoice_date,
                $refType,
                $invoice_id,
                "Updated Invoice {$oldInv['invoice_number']} to {$customer_name}",
                $journalLines,
                $user_id
            );

            // 8. Audit Trail
            log_audit(
                'UPDATE_INVOICE',
                'invoice',
                $invoice_id,
                [
                    'invoice_number' => $oldInv['invoice_number'],
                    'old_total' => (float)$oldInv['total_amount'],
                    'new_total' => (float)$total_amount,
                    'customer_name' => $customer_name
                ]
            );

            if (!$wasInTransaction) {
                $pdo->commit();
            }

            return $invoice_id;

        } catch (Exception $e) {
            if (!$wasInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
