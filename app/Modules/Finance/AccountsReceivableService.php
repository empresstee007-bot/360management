<?php

declare(strict_types=1);

namespace App\Modules\Finance;

class AccountsReceivableService
{
    /**
     * Get Customer Debtors & Aging Schedule
     */
    public static function getDebtors(string $companyId = 'beverage'): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sessionKey = "ar_debtors_{$companyId}";
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = self::getInitialDebtors($companyId);
        }

        return $_SESSION[$sessionKey];
    }

    /**
     * Record customer payment receipt against invoice
     */
    public static function recordPayment(string $companyId, string $invoiceNumber, float $amount): array
    {
        $debtors = self::getDebtors($companyId);
        $updatedInvoice = null;

        foreach ($debtors as &$inv) {
            if ($inv['invoice_number'] === $invoiceNumber) {
                $inv['paid_amount'] += $amount;
                $inv['balance_due'] = max(0.0, $inv['total_amount'] - $inv['paid_amount']);
                $inv['status'] = $inv['balance_due'] <= 0.0 ? 'Paid' : 'Partially Paid';
                $updatedInvoice = $inv;
                break;
            }
        }

        $_SESSION["ar_debtors_{$companyId}"] = $debtors;

        // Post Journal Entry for Cash Receipt
        if ($updatedInvoice) {
            DoubleEntryJournalService::createJournalEntry($companyId, [
                'event_type' => 'customer_payment',
                'reference' => 'RCPT-' . rand(1000, 9999),
                'narration' => "Customer Payment received from {$updatedInvoice['customer_name']} for Invoice {$invoiceNumber}",
                'lines' => [
                    ['code' => '1110', 'name' => 'Cash & Bank Central Operating Account', 'debit' => $amount, 'credit' => 0.00],
                    ['code' => '1150', 'name' => 'Accounts Receivable (Trade Debtors)', 'debit' => 0.00, 'credit' => $amount],
                ]
            ]);
        }

        return $updatedInvoice ?? [];
    }

    public static function getInitialDebtors(string $companyId = 'beverage'): array
    {
        return [];
    }
}
