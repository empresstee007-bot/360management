<?php

declare(strict_types=1);

namespace App\Modules\BeverageWarehouse;

class AiWarehouseAssistantService
{
    /**
     * Answer natural language AI Q&A queries for beverage warehouse management
     */
    public static function askAssistant(string $prompt): array
    {
        $cleanPrompt = strtolower(trim($prompt));

        if (
            str_contains($cleanPrompt, 'opening stock')
            || str_contains($cleanPrompt, 'closing stock')
            || (str_contains($cleanPrompt, 'daily') && str_contains($cleanPrompt, 'stock'))
        ) {
            $report = BeverageWarehouseService::getDailyStockReport(date('Y-m-d'), 'All Warehouses');
            $totals = $report['totals'] ?? [];
            $closingStatus = (string)($report['closing_status'] ?? 'Live Estimate');

            return [
                'topic' => 'Daily Opening & Closing Stock',
                'answer' => sprintf(
                    "Today's stock report for %s: opening stock is %s crates/packs (%s units), closing stock is %s crates/packs (%s units), with a net movement of %+d crates/packs. Closing status: %s.",
                    (string)($report['warehouse'] ?? 'All Warehouses'),
                    number_format((int)($totals['opening_crates'] ?? 0)),
                    number_format((int)($totals['opening_units'] ?? 0)),
                    number_format((int)($totals['closing_crates'] ?? 0)),
                    number_format((int)($totals['closing_units'] ?? 0)),
                    (int)($totals['crate_change'] ?? 0),
                    $closingStatus
                ),
                'confidence' => '95%',
                'suggested_action' => $closingStatus === 'Captured' ? 'Review Daily Stock Report' : 'Capture Closing Stock',
            ];
        }

        if (str_contains($cleanPrompt, 'reorder') || str_contains($cleanPrompt, 'which drinks')) {
            return [
                'topic' => 'Reorder Recommendations',
                'answer' => "No real product sales or inventory movement has been recorded yet. Add products and record stock movement before requesting reorder recommendations.",
                'confidence' => '0%',
                'suggested_action' => 'Add Real Products',
            ];
        }

        if (str_contains($cleanPrompt, 'expire') || str_contains($cleanPrompt, 'batch')) {
            return [
                'topic' => 'FEFO Expiry Warnings',
                'answer' => "No real batch or expiry data has been entered yet.",
                'confidence' => '0%',
                'suggested_action' => 'Add Batch Details',
            ];
        }

        if (str_contains($cleanPrompt, 'driver') || str_contains($cleanPrompt, 'variance') || str_contains($cleanPrompt, 'shortage')) {
            return [
                'topic' => 'Vehicle Reconciliation Anomaly',
                'answer' => "No real driver trip, vehicle reconciliation, or shortage data has been recorded yet.",
                'confidence' => '0%',
                'suggested_action' => 'Record Real Trip Data',
            ];
        }

        if (str_contains($cleanPrompt, 'crate') || str_contains($cleanPrompt, 'unreturned') || str_contains($cleanPrompt, 'customer')) {
            return [
                'topic' => 'Customer Outstanding Crates',
                'answer' => "No real customer crate ledger data has been entered yet.",
                'confidence' => '0%',
                'suggested_action' => 'Record Customer Crate Returns',
            ];
        }

        return [
            'topic' => 'General Warehouse Health',
            'answer' => "No real warehouse activity has been recorded yet. Add products, batches, transfers, and trip records to generate warehouse health insights.",
            'confidence' => '0%',
            'suggested_action' => 'Add Warehouse Data',
        ];
    }
}
