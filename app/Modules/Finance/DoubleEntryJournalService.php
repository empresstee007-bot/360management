<?php

declare(strict_types=1);

namespace App\Modules\Finance;

class DoubleEntryJournalService
{
    /**
     * Post a balanced double-entry journal entry ($\sum Debits = \sum Credits$)
     */
    public static function createJournalEntry(string $companyId, array $entryData): array
    {
        $companyId = 'beverage';
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $lines = $entryData['lines'] ?? [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $totalDebit += (float)($line['debit'] ?? 0.0);
            $totalCredit += (float)($line['credit'] ?? 0.0);
        }

        // Validate double-entry balance
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \InvalidArgumentException("Unbalanced Journal Entry! Total Debits (₦{$totalDebit}) must equal Total Credits (₦{$totalCredit}).");
        }

        $voucherNumber = 'JV-' . strtoupper($companyId) . '-' . date('Ymd') . '-' . rand(1000, 9999);
        $entry = [
            'voucher_number' => $voucherNumber,
            'company_id' => $companyId,
            'branch_id' => $entryData['branch_id'] ?? 'Main Depot',
            'date' => $entryData['date'] ?? date('Y-m-d H:i:s'),
            'event_type' => $entryData['event_type'] ?? 'manual',
            'reference' => $entryData['reference'] ?? 'Ref-' . rand(100, 999),
            'narration' => $entryData['narration'] ?? 'Double Entry Financial Posting',
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'posted_by' => $entryData['posted_by'] ?? 'Finance Manager',
            'lines' => $lines,
        ];

        $sessionKey = "journal_entries_{$companyId}";
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = self::getSampleJournalEntries($companyId);
        }

        array_unshift($_SESSION[$sessionKey], $entry);

        return $entry;
    }

    /**
     * Get journal entries for the beverage company.
     */
    public static function getJournalEntries(string $companyId = 'beverage'): array
    {
        $companyId = 'beverage';
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sessionKey = "journal_entries_{$companyId}";
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = self::getSampleJournalEntries($companyId);
        }

        return $_SESSION[$sessionKey];
    }

    /**
     * Start with no journal entries; real journals are created by business events.
     */
    public static function getSampleJournalEntries(string $companyId = 'beverage'): array
    {
        return [];
    }
}
