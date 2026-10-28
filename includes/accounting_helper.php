<?php
/**
 * Genuine Double-Entry Accounting Engine
 */

require_once __DIR__ . '/../config/init.php';

class AccountingEngine {

    /**
     * Post a balanced journal entry
     * @param int $business_id
     * @param string $entry_number
     * @param string $entry_date (Y-m-d)
     * @param string $ref_type ('sale', 'purchase', 'repair', 'refill', 'receipt', 'payment', 'expense', 'voucher', 'opening')
     * @param int|null $ref_id
     * @param string $description
     * @param array $lines [ ['account_id' => X, 'debit' => 0.00, 'credit' => 0.00, 'narration' => '...'], ... ]
     * @param int|null $user_id
     * @return int Entry ID
     * @throws Exception if debits != credits or invalid account
     */
    public static function postJournalEntry($business_id, $entry_number, $entry_date, $ref_type, $ref_id, $description, array $lines, $user_id = null) {
        $pdo = getDB();

        if (empty($lines)) {
            throw new Exception("Journal entry must have at least one debit and one credit line.");
        }

        $total_debit = 0.0;
        $total_credit = 0.0;

        foreach ($lines as $line) {
            $total_debit += (float)($line['debit'] ?? 0.0);
            $total_credit += (float)($line['credit'] ?? 0.0);
        }

        $total_debit = round($total_debit, 2);
        $total_credit = round($total_credit, 2);

        if (abs($total_debit - $total_credit) > 0.01) {
            throw new Exception("Double-entry violation: Total debits ({$total_debit}) must equal total credits ({$total_credit})!");
        }

        $wasInTransaction = $pdo->inTransaction();
        if (!$wasInTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO journal_entries 
                (business_id, entry_number, entry_date, reference_type, reference_id, description, total_debit, total_credit, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $business_id,
                $entry_number,
                $entry_date,
                $ref_type,
                $ref_id,
                $description,
                $total_debit,
                $total_credit,
                $user_id
            ]);
            $entry_id = $pdo->lastInsertId();

            $lineStmt = $pdo->prepare("INSERT INTO journal_entry_lines 
                (entry_id, account_id, debit, credit, narration) 
                VALUES (?, ?, ?, ?, ?)");

            $balUpdateStmt = $pdo->prepare("UPDATE chart_of_accounts 
                SET current_balance = current_balance + ? 
                WHERE id = ? AND business_id = ?");

            foreach ($lines as $line) {
                $acc_id = (int)$line['account_id'];
                $deb = round((float)($line['debit'] ?? 0.0), 2);
                $crd = round((float)($line['credit'] ?? 0.0), 2);
                $narration = $line['narration'] ?? $description;

                // Validate account belongs to this business
                $accCheck = $pdo->prepare("SELECT account_type FROM chart_of_accounts WHERE id = ? AND business_id = ?");
                $accCheck->execute([$acc_id, $business_id]);
                $accType = $accCheck->fetchColumn();
                if (!$accType) {
                    throw new Exception("Account ID #{$acc_id} does not belong to authorized business #{$business_id}!");
                }

                $lineStmt->execute([$entry_id, $acc_id, $deb, $crd, $narration]);

                // Update Account balance based on normal balance type:
                // Assets & Expenses increase with Debit (+deb -crd)
                // Liabilities, Equity, Income increase with Credit (+crd -deb)
                $netChange = 0.0;
                if (in_array($accType, ['asset', 'expense', 'cogs'])) {
                    $netChange = $deb - $crd;
                } else {
                    $netChange = $crd - $deb;
                }

                $balUpdateStmt->execute([$netChange, $acc_id, $business_id]);
            }

            if (!$wasInTransaction) {
                $pdo->commit();
            }

            return $entry_id;

        } catch (Exception $e) {
            if (!$wasInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete a journal entry and safely reverse its impact on chart_of_accounts balance
     */
    public static function deleteJournalEntry($business_id, $entry_id) {
        $pdo = getDB();
        $lineStmt = $pdo->prepare("
            SELECT jel.*, coa.account_type 
            FROM journal_entry_lines jel
            JOIN chart_of_accounts coa ON coa.id = jel.account_id
            WHERE jel.entry_id = ? AND coa.business_id = ?
        ");
        $lineStmt->execute([$entry_id, $business_id]);
        $lines = $lineStmt->fetchAll();

        $balUpdateStmt = $pdo->prepare("UPDATE chart_of_accounts 
            SET current_balance = current_balance - ? 
            WHERE id = ? AND business_id = ?");

        foreach ($lines as $line) {
            $deb = (float)$line['debit'];
            $crd = (float)$line['credit'];
            $accType = $line['account_type'];
            $accId = (int)$line['account_id'];

            if (in_array($accType, ['asset', 'expense', 'cogs'])) {
                $netChange = $deb - $crd;
            } else {
                $netChange = $crd - $deb;
            }

            $balUpdateStmt->execute([$netChange, $accId, $business_id]);
        }

        $pdo->prepare("DELETE FROM journal_entry_lines WHERE entry_id = ?")->execute([$entry_id]);
        $pdo->prepare("DELETE FROM journal_entries WHERE id = ? AND business_id = ?")->execute([$entry_id, $business_id]);
    }

    /**
     * Reverse and delete all journal entries for a given reference
     */
    public static function reverseJournalEntryByReference($business_id, $ref_type, $ref_id) {
        $pdo = getDB();
        $refTypes = is_array($ref_type) ? $ref_type : [$ref_type];
        $placeholders = implode(',', array_fill(0, count($refTypes), '?'));
        $params = array_merge([$business_id], $refTypes, [$ref_id]);

        $stmt = $pdo->prepare("SELECT id FROM journal_entries WHERE business_id = ? AND reference_type IN ($placeholders) AND reference_id = ?");
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($entries as $entryId) {
            self::deleteJournalEntry($business_id, $entryId);
        }
    }

    /**
     * Get Chart of Account ID by code
     */
    public static function getAccountIdByCode($business_id, $code) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE business_id = ? AND account_code = ?");
        $stmt->execute([$business_id, $code]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new Exception("System Account Code '{$code}' not found for business ID {$business_id}.");
        }
        return (int)$id;
    }

    /**
     * Get Trial Balance for the business
     */
    public static function getTrialBalance($business_id, $as_of_date = null) {
        $pdo = getDB();
        $dateCondition = $as_of_date ? "AND je.entry_date <= :as_of_date" : "";

        $sql = "
            SELECT 
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.account_name_ur,
                coa.account_type,
                COALESCE(SUM(jel.debit), 0.00) AS total_debit,
                COALESCE(SUM(jel.credit), 0.00) AS total_credit
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON jel.account_id = coa.id
            LEFT JOIN journal_entries je ON je.id = jel.entry_id {$dateCondition}
            WHERE coa.business_id = :bid AND coa.status = 'active'
            GROUP BY coa.id
            ORDER BY coa.account_code ASC
        ";

        $stmt = $pdo->prepare($sql);
        $params = [':bid' => $business_id];
        if ($as_of_date) {
            $params[':as_of_date'] = $as_of_date;
        }
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();

        $trialBalance = [];
        $grand_debit = 0.0;
        $grand_credit = 0.0;

        foreach ($accounts as $acc) {
            $deb = (float)$acc['total_debit'];
            $crd = (float)$acc['total_credit'];
            $type = $acc['account_type'];

            $net_debit = 0.0;
            $net_credit = 0.0;

            if (in_array($type, ['asset', 'expense', 'cogs'])) {
                $net = $deb - $crd;
                if ($net >= 0) {
                    $net_debit = $net;
                } else {
                    $net_credit = abs($net);
                }
            } else {
                $net = $crd - $deb;
                if ($net >= 0) {
                    $net_credit = $net;
                } else {
                    $net_debit = abs($net);
                }
            }

            if ($net_debit > 0 || $net_credit > 0) {
                $grand_debit += $net_debit;
                $grand_credit += $net_credit;
                $acc['closing_debit'] = $net_debit;
                $acc['closing_credit'] = $net_credit;
                $trialBalance[] = $acc;
            }
        }

        return [
            'accounts' => $trialBalance,
            'total_debit' => round($grand_debit, 2),
            'total_credit' => round($grand_credit, 2),
            'is_balanced' => (abs($grand_debit - $grand_credit) < 0.01)
        ];
    }

    /**
     * Calculate Profit and Loss Statement
     */
    public static function getProfitAndLoss($business_id, $start_date = null, $end_date = null) {
        $pdo = getDB();
        $dateCond = "";
        $params = [':bid' => $business_id];

        if ($start_date && $end_date) {
            $dateCond = "AND je.entry_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $start_date;
            $params[':end_date'] = $end_date;
        }

        $sql = "
            SELECT 
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.account_name_ur,
                coa.account_type,
                COALESCE(SUM(jel.debit), 0.00) AS total_debit,
                COALESCE(SUM(jel.credit), 0.00) AS total_credit
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON jel.account_id = coa.id
            LEFT JOIN journal_entries je ON je.id = jel.entry_id {$dateCond}
            WHERE coa.business_id = :bid AND coa.account_type IN ('income', 'cogs', 'expense')
            GROUP BY coa.id
            ORDER BY coa.account_code ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $incomes = [];
        $cogs = [];
        $expenses = [];

        $total_income = 0.0;
        $total_cogs = 0.0;
        $total_expenses = 0.0;

        foreach ($rows as $r) {
            $deb = (float)$r['total_debit'];
            $crd = (float)$r['total_credit'];

            if ($r['account_type'] === 'income') {
                $amount = $crd - $deb;
                if ($amount != 0) {
                    $r['amount'] = $amount;
                    $incomes[] = $r;
                    $total_income += $amount;
                }
            } elseif ($r['account_type'] === 'cogs') {
                $amount = $deb - $crd;
                if ($amount != 0) {
                    $r['amount'] = $amount;
                    $cogs[] = $r;
                    $total_cogs += $amount;
                }
            } elseif ($r['account_type'] === 'expense') {
                $amount = $deb - $crd;
                if ($amount != 0) {
                    $r['amount'] = $amount;
                    $expenses[] = $r;
                    $total_expenses += $amount;
                }
            }
        }

        $gross_profit = $total_income - $total_cogs;
        $net_profit = $gross_profit - $total_expenses;

        return [
            'incomes' => $incomes,
            'total_income' => round($total_income, 2),
            'cogs' => $cogs,
            'total_cogs' => round($total_cogs, 2),
            'gross_profit' => round($gross_profit, 2),
            'expenses' => $expenses,
            'total_expenses' => round($total_expenses, 2),
            'net_profit' => round($net_profit, 2)
        ];
    }

    /**
     * Calculate Balance Sheet
     */
    public static function getBalanceSheet($business_id, $as_of_date = null) {
        $tb = self::getTrialBalance($business_id, $as_of_date);
        $pnl = self::getProfitAndLoss($business_id, null, $as_of_date);

        $assets = [];
        $liabilities = [];
        $equity = [];

        $total_assets = 0.0;
        $total_liabilities = 0.0;
        $total_equity = 0.0;

        foreach ($tb['accounts'] as $acc) {
            if ($acc['account_type'] === 'asset') {
                $val = $acc['closing_debit'] - $acc['closing_credit'];
                $acc['balance'] = $val;
                $assets[] = $acc;
                $total_assets += $val;
            } elseif ($acc['account_type'] === 'liability') {
                $val = $acc['closing_credit'] - $acc['closing_debit'];
                $acc['balance'] = $val;
                $liabilities[] = $acc;
                $total_liabilities += $val;
            } elseif ($acc['account_type'] === 'equity') {
                $val = $acc['closing_credit'] - $acc['closing_debit'];
                $acc['balance'] = $val;
                $equity[] = $acc;
                $total_equity += $val;
            }
        }

        // Add current period net profit to equity
        $current_profit = $pnl['net_profit'];
        $total_equity += $current_profit;

        return [
            'assets' => $assets,
            'total_assets' => round($total_assets, 2),
            'liabilities' => $liabilities,
            'total_liabilities' => round($total_liabilities, 2),
            'equity' => $equity,
            'current_period_profit' => round($current_profit, 2),
            'total_equity' => round($total_equity, 2),
            'is_balanced' => (abs($total_assets - ($total_liabilities + $total_equity)) < 0.01)
        ];
    }

    /**
     * Get General Ledger details for an account
     */
    public static function getAccountLedger($business_id, $account_id, $start_date = null, $end_date = null) {
        $pdo = getDB();

        // Get account info
        $accStmt = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE id = ? AND business_id = ?");
        $accStmt->execute([$account_id, $business_id]);
        $account = $accStmt->fetch();

        if (!$account) {
            throw new Exception("Account not found.");
        }

        $dateCond = "";
        $params = [':acc_id' => $account_id, ':bid' => $business_id];

        if ($start_date && $end_date) {
            $dateCond = "AND je.entry_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $start_date;
            $params[':end_date'] = $end_date;
        }

        $sql = "
            SELECT 
                je.id AS entry_id,
                je.entry_number,
                je.entry_date,
                je.reference_type,
                je.reference_id,
                je.description,
                jel.debit,
                jel.credit,
                jel.narration
            FROM journal_entry_lines jel
            JOIN journal_entries je ON je.id = jel.entry_id
            WHERE jel.account_id = :acc_id AND je.business_id = :bid {$dateCond}
            ORDER BY je.entry_date ASC, je.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll();

        $running_balance = 0.0;
        $isAssetOrExpense = in_array($account['account_type'], ['asset', 'expense', 'cogs']);

        foreach ($entries as &$e) {
            $deb = (float)$e['debit'];
            $crd = (float)$e['credit'];

            if ($isAssetOrExpense) {
                $running_balance += ($deb - $crd);
            } else {
                $running_balance += ($crd - $deb);
            }
            $e['running_balance'] = $running_balance;
        }

        return [
            'account' => $account,
            'entries' => $entries,
            'closing_balance' => round($running_balance, 2)
        ];
    }
}
