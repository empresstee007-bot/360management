<?php

declare(strict_types=1);

namespace App\Modules\Finance;

class BankReconciliationService
{
    /**
     * Get Bank Feed Statements & Auto-Matching Credit Alerts
     */
    public static function getBankFeeds(string $companyId = 'beverage'): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sessionKey = "bank_feeds_{$companyId}";
        return $_SESSION[$sessionKey] ?? [];
    }
}
