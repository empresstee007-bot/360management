<?php

declare(strict_types=1);

namespace App\Modules\Finance;

class AccountsPayableService
{
    /**
     * Get Supplier Creditors & Brewery Payables List
     */
    public static function getCreditors(string $companyId = 'beverage'): array
    {
        $companyId = 'beverage';
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sessionKey = "ap_creditors_{$companyId}";
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = self::getInitialCreditors($companyId);
        }

        return $_SESSION[$sessionKey];
    }

    /**
     * Record Supplier Payment Voucher
     */
    public static function recordSupplierPayment(string $companyId, string $billNumber, float $amount): array
    {
        $companyId = 'beverage';
        $creditors = self::getCreditors($companyId);
        $updatedBill = null;

        foreach ($creditors as &$bill) {
            if ($bill['bill_number'] === $billNumber) {
                $bill['paid_amount'] += $amount;
                $bill['balance_due'] = max(0.0, $bill['total_amount'] - $bill['paid_amount']);
                $bill['status'] = $bill['balance_due'] <= 0.0 ? 'Paid' : 'Partially Paid';
                $updatedBill = $bill;
                break;
            }
        }

        $_SESSION["ap_creditors_{$companyId}"] = $creditors;

        // Post Journal Entry for Supplier Payment
        if ($updatedBill) {
            DoubleEntryJournalService::createJournalEntry($companyId, [
                'event_type' => 'supplier_payment',
                'reference' => 'PV-' . rand(1000, 9999),
                'narration' => "Supplier Payment Voucher issued to {$updatedBill['supplier_name']} for Bill {$billNumber}",
                'lines' => [
                    ['code' => '2110', 'name' => 'Accounts Payable (Trade Creditors)', 'debit' => $amount, 'credit' => 0.00],
                    ['code' => '1110', 'name' => 'Cash & Bank Central Operating Account', 'debit' => 0.00, 'credit' => $amount],
                ]
            ]);
        }

        return $updatedBill ?? [];
    }

    public static function getInitialCreditors(string $companyId = 'beverage'): array
    {
        return [];
    }
}
