<?php

declare(strict_types=1);

namespace App\Modules\Finance;

class ChartOfAccountsService
{
    /**
     * Standard Beverage Depot Chart of Accounts (COA) template.
     */
    public static function getInitialAccounts(string $companyId = 'beverage'): array
    {
        $commonAccounts = [
            // ASSETS (1000 - 1999)
            ['code' => '1110', 'name' => 'Cash & Bank Central Operating Account', 'type' => 'Asset', 'parent' => '1100'],
            ['code' => '1120', 'name' => 'Petty Cash Float', 'type' => 'Asset', 'parent' => '1100'],
            ['code' => '1150', 'name' => 'Accounts Receivable (Trade Debtors)', 'type' => 'Asset', 'parent' => '1100'],
            
            // LIABILITIES (2000 - 2999)
            ['code' => '2110', 'name' => 'Accounts Payable (Trade Creditors)', 'type' => 'Liability', 'parent' => '2100'],
            ['code' => '2220', 'name' => 'WHT Payable (Withholding Tax 5%)', 'type' => 'Liability', 'parent' => '2200'],
            ['code' => '2230', 'name' => 'PAYE & Statutory Payroll Deductions', 'type' => 'Liability', 'parent' => '2200'],
            
            // EQUITY (3000 - 3999)
            ['code' => '3100', 'name' => 'Shareholders Share Capital', 'type' => 'Equity', 'parent' => '3000'],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'Equity', 'parent' => '3000'],
            
            // EXPENSES (6000 - 6999)
            ['code' => '6110', 'name' => 'Salaries & Staff Wages Expense', 'type' => 'Expense', 'parent' => '6100'],
            ['code' => '6210', 'name' => 'Logistics & Vehicle Operations Expense', 'type' => 'Expense', 'parent' => '6200'],
            ['code' => '6310', 'name' => 'Utilities & Power Expense', 'type' => 'Expense', 'parent' => '6300'],
            ['code' => '6410', 'name' => 'Bank Charges & Transaction Fees', 'type' => 'Expense', 'parent' => '6400'],
        ];

        $divisionAccounts = [
            ['code' => '1210', 'name' => 'Beverage Finished Goods Inventory', 'type' => 'Asset', 'parent' => '1200'],
            ['code' => '1220', 'name' => 'Empty Crates & Bottles Asset', 'type' => 'Asset', 'parent' => '1200'],
            ['code' => '2310', 'name' => 'Customer Crate Deposits Liability', 'type' => 'Liability', 'parent' => '2300'],
            ['code' => '4110', 'name' => 'Beverage Wholesale Sales Revenue', 'type' => 'Revenue', 'parent' => '4100'],
            ['code' => '4120', 'name' => 'Beverage Retail POS Revenue', 'type' => 'Revenue', 'parent' => '4100'],
            ['code' => '5110', 'name' => 'Cost of Goods Sold (Beverage Finished Goods)', 'type' => 'COGS', 'parent' => '5100'],
            ['code' => '5210', 'name' => 'Beverage Spoilage & Breakage Loss Expense', 'type' => 'Expense', 'parent' => '5200'],
        ];

        return array_merge($commonAccounts, $divisionAccounts);
    }

    /**
     * Get COA list from session or default template
     */
    public static function getAccounts(string $companyId = 'beverage'): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sessionKey = "coa_accounts_{$companyId}";
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = self::getInitialAccounts($companyId);
        }

        return $_SESSION[$sessionKey];
    }

    /**
     * Add custom account to COA
     */
    public static function addAccount(string $companyId, array $accountData): array
    {
        $accounts = self::getAccounts($companyId);

        $newAccount = [
            'code' => trim((string)($accountData['code'] ?? '')),
            'name' => trim((string)($accountData['name'] ?? '')),
            'type' => trim((string)($accountData['type'] ?? 'Asset')),
            'parent' => trim((string)($accountData['parent'] ?? '1000')),
        ];

        array_push($accounts, $newAccount);
        $_SESSION["coa_accounts_{$companyId}"] = $accounts;

        return $newAccount;
    }
}
