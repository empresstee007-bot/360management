<?php

declare(strict_types=1);

namespace App\Modules\BeverageWarehouse;

class VehicleReconciliationService
{
    /**
     * Calculate vehicle trip stock reconciliation and detect driver variances
     * Formula: Opening + Loaded - Sold - Damaged - Returned = Expected Closing Stock
     */
    public static function reconcileTrip(
        int $openingCrates,
        int $loadedCrates,
        int $soldCrates,
        int $damagedCrates,
        int $returnedCrates,
        int $driverPhysicalCount
    ): array {
        $expectedClosing = $openingCrates + $loadedCrates - $soldCrates - $damagedCrates - $returnedCrates;
        $variance = $driverPhysicalCount - $expectedClosing;

        $status = 'Balanced';
        $requiresApproval = false;

        if ($variance < 0) {
            $status = 'Shortage Flagged';
            $requiresApproval = true;
        } elseif ($variance > 0) {
            $status = 'Excess Flagged';
            $requiresApproval = true;
        }

        return [
            'opening_crates' => $openingCrates,
            'loaded_crates' => $loadedCrates,
            'sold_crates' => $soldCrates,
            'damaged_crates' => $damagedCrates,
            'returned_crates' => $returnedCrates,
            'expected_closing_crates' => $expectedClosing,
            'driver_physical_crates' => $driverPhysicalCount,
            'variance_crates' => $variance,
            'status' => $status,
            'requires_manager_approval' => $requiresApproval,
            'variance_formatted' => $variance === 0 ? '0 Crates (Exact Balance)' : ($variance > 0 ? "+{$variance} Crates Excess" : "{$variance} Crates Shortage"),
        ];
    }
}
