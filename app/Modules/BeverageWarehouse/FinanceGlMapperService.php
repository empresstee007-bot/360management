<?php

declare(strict_types=1);

namespace App\Modules\BeverageWarehouse;

class FinanceGlMapperService
{
    /**
     * Map warehouse events to double-entry General Ledger postings
     */
    public static function generateJournalEntry(string $eventType, float $amount, string $reference): array
    {
        $entries = match ($eventType) {
            'purchase_receiving' => [
                ['account' => '1200 - Beverage Inventory Asset', 'debit' => $amount, 'credit' => 0.00],
                ['account' => '2100 - Accounts Payable (Suppliers)', 'debit' => 0.00, 'credit' => $amount],
            ],
            'sales_dispatch' => [
                ['account' => '5100 - Cost of Goods Sold (COGS)', 'debit' => $amount, 'credit' => 0.00],
                ['account' => '1200 - Beverage Inventory Asset', 'debit' => 0.00, 'credit' => $amount],
            ],
            'breakage_loss' => [
                ['account' => '5400 - Inventory Breakage & Spoilage Loss', 'debit' => $amount, 'credit' => 0.00],
                ['account' => '1200 - Beverage Inventory Asset', 'debit' => 0.00, 'credit' => $amount],
            ],
            'crate_deposit_issued' => [
                ['account' => '1150 - Customer Crate Deposits Held', 'debit' => $amount, 'credit' => 0.00],
                ['account' => '2300 - Crate Deposit Liability', 'debit' => 0.00, 'credit' => $amount],
            ],
            default => [
                ['account' => '1200 - General Inventory Adjustment', 'debit' => $amount, 'credit' => 0.00],
                ['account' => '9900 - Clearing Account', 'debit' => 0.00, 'credit' => $amount],
            ]
        };

        return [
            'entry_number' => 'JV-' . date('Ymd') . '-' . rand(100, 999),
            'event_type' => $eventType,
            'reference' => $reference,
            'date' => date('Y-m-d H:i:s'),
            'total_debit' => $amount,
            'total_credit' => $amount,
            'lines' => $entries,
        ];
    }
}
