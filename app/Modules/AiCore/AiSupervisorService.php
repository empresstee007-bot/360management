<?php

declare(strict_types=1);

namespace App\Modules\AiCore;

class AiSupervisorService
{
    public static function getSupervisorBrief(string $companyId = 'beverage'): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $companyId = 'beverage';
        $watchAlerts = self::buildWatchAlerts($companyId);
        $trainingTasks = self::buildTrainingTasks($companyId, $watchAlerts);
        $moduleChecks = self::buildModuleChecks($companyId);
        $criticalCount = count(array_filter($watchAlerts, static fn (array $alert): bool => ($alert['severity'] ?? '') === 'critical'));
        $warningCount = count(array_filter($watchAlerts, static fn (array $alert): bool => ($alert['severity'] ?? '') === 'warning'));
        $score = max(70, 100 - ($criticalCount * 12) - ($warningCount * 4));

        return [
            'mode' => 'AI Watch & Training',
            'company_id' => $companyId,
            'score' => $score,
            'status' => $criticalCount > 0 ? 'Needs Intervention' : ($warningCount > 0 ? 'Watching Closely' : 'Stable'),
            'summary' => self::buildSummary($score, $criticalCount, $warningCount),
            'watch_alerts' => $watchAlerts,
            'training_tasks' => $trainingTasks,
            'module_checks' => $moduleChecks,
            'last_checked' => date('Y-m-d H:i:s'),
        ];
    }

    public static function answerSupervisorPrompt(string $prompt, string $companyId = 'beverage'): ?string
    {
        $p = strtolower(trim($prompt));
        if (!str_contains($p, 'watch') && !str_contains($p, 'train') && !str_contains($p, 'supervisor') && !str_contains($p, 'check') && !str_contains($p, 'ensure')) {
            return null;
        }

        $brief = self::getSupervisorBrief($companyId);
        $lines = [
            "AI Watch & Training Supervisor: {$brief['score']}% - {$brief['status']}",
            "",
            $brief['summary'],
            "",
            "Top Watch Alerts:",
        ];

        foreach (array_slice($brief['watch_alerts'], 0, 4) as $alert) {
            $lines[] = "- {$alert['title']}: {$alert['message']} Action: {$alert['action']}";
        }

        $lines[] = "";
        $lines[] = "Training Focus:";
        foreach (array_slice($brief['training_tasks'], 0, 4) as $task) {
            $lines[] = "- {$task['module']}: {$task['lesson']} ({$task['why']})";
        }

        return implode("\n", $lines);
    }

    private static function buildWatchAlerts(string $companyId): array
    {
        $alerts = [];
        $sales = array_values(array_filter($_SESSION['pos_sales_history'] ?? [], static fn (array $tx): bool => (($tx['pos_mode'] ?? 'beverage') === $companyId)));
        $products = $_SESSION['beverage_products'] ?? [];
        $syncEvents = $_SESSION['erp_realtime_sync_events'] ?? [];
        $recons = $_SESSION['payment_reconciliations'] ?? [];
        $audit = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')
            ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getAuditTrail()
            : ($_SESSION['inventory_audit_trail'] ?? []);
        $transfers = array_values(array_filter($sales, static fn (array $tx): bool => str_contains(strtolower((string)($tx['payment_method'] ?? '')), 'transfer')));

        $pendingTransfers = array_values(array_filter($transfers, static fn (array $tx): bool => ($tx['transfer_status'] ?? 'Pending POS Confirmation') !== 'Approved & Posted'));
        if ($pendingTransfers !== []) {
            $amount = array_sum(array_map(static fn (array $tx): float => (float)($tx['grand_total'] ?? 0), $pendingTransfers));
            $alerts[] = [
                'severity' => 'warning',
                'module' => 'POS / Payment Recon',
                'title' => 'Transfer payments need approval',
                'message' => count($pendingTransfers) . ' transfer receipt(s) worth ₦' . number_format($amount, 2) . ' are not fully approved.',
                'action' => 'Open POS Transfer Payments and confirm/approve before cash close.',
            ];
        }

        foreach ($products as $product) {
            $stock = (int)($product['stock_crates'] ?? 0);
            $min = (int)($product['min_stock'] ?? $product['reorder_level'] ?? 0);
            if ($min > 0 && $stock <= $min) {
                $alerts[] = [
                    'severity' => $stock <= 0 ? 'critical' : 'warning',
                    'module' => 'Warehouse Inventory',
                    'title' => 'Stock below safe level',
                    'message' => ($product['name'] ?? 'Product') . ' is at ' . number_format($stock) . ' crates/packs against minimum ' . number_format($min) . '.',
                    'action' => 'Raise procurement request or confirm inbound delivery note.',
                ];
                break;
            }
        }

        $expiring = array_values(array_filter($_SESSION["fefo_register_{$companyId}"] ?? [], static fn (array $row): bool => in_array(($row['status'] ?? ''), ['Expiring Soon', 'Expired'], true)));
        if ($expiring !== []) {
            $alerts[] = [
                'severity' => 'warning',
                'module' => 'FEFO',
                'title' => 'Expiry watch active',
                'message' => count($expiring) . ' product batch(es) require FEFO review.',
                'action' => 'Prioritize older batches in POS/dispatch and review warehouse FEFO list.',
            ];
        }

        $recentSales = array_slice($sales, 0, 10);
        foreach ($recentSales as $sale) {
            $receipt = (string)($sale['receipt_no'] ?? '');
            if ($receipt === '') {
                continue;
            }
            $hasRecon = self::recordExists($recons, 'ref', $receipt);
            $hasSync = self::containsReference($syncEvents, $receipt);
            if (!$hasRecon || !$hasSync) {
                $alerts[] = [
                    'severity' => 'critical',
                    'module' => 'Real-Time Sync',
                    'title' => 'Sale sync gap detected',
                    'message' => "Receipt {$receipt} is missing " . (!$hasRecon ? 'payment reconciliation' : 'sync event') . ' evidence.',
                    'action' => 'Repost sale through the sync engine before closing accounts.',
                ];
                break;
            }
        }

        if (count($audit) < 2 && count($sales) > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'module' => 'Audit Trail',
                'title' => 'Audit evidence is light',
                'message' => 'Recent activity exists but inventory audit trail is low.',
                'action' => 'Review stock movements and confirm all POS deductions are logged.',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'severity' => 'ok',
                'module' => 'All Modules',
                'title' => 'No urgent exception detected',
                'message' => 'Sales, product master, inventory, payment recon and finance sync are currently aligned.',
                'action' => 'Continue normal operation and review training prompts during quieter periods.',
            ];
        }

        return $alerts;
    }

    private static function buildTrainingTasks(string $companyId, array $alerts): array
    {
        $tasks = [
            [
                'module' => 'POS',
                'lesson' => 'Confirm bank transfer receipts before printing cash close totals.',
                'why' => 'Prevents unpaid transfer receipts from entering revenue as approved cash.',
            ],
            [
                'module' => 'Warehouse',
                'lesson' => 'Register product image, SKU, price, stock, supplier and FEFO date together.',
                'why' => 'Keeps POS, procurement, inventory valuation and expiry controls aligned.',
            ],
            [
                'module' => 'Finance',
                'lesson' => 'Review GL postings generated from POS and delivery sync events daily.',
                'why' => 'Keeps sales, crate deposit and bank accounts balanced.',
            ],
        ];

        foreach ($alerts as $alert) {
            if (($alert['severity'] ?? '') === 'critical') {
                array_unshift($tasks, [
                    'module' => $alert['module'],
                    'lesson' => 'Resolve this exception before end-of-day close: ' . $alert['title'],
                    'why' => $alert['message'],
                ]);
            }
        }

        return $tasks;
    }

    private static function buildModuleChecks(string $companyId): array
    {
        $auditTrail = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')
            ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getAuditTrail()
            : ($_SESSION['inventory_audit_trail'] ?? []);

        return [
            ['module' => 'POS Sales', 'check' => count(array_filter($_SESSION['pos_sales_history'] ?? [], static fn (array $tx): bool => (($tx['pos_mode'] ?? 'beverage') === $companyId))) . ' receipt(s) in live history', 'status' => 'Watching'],
            ['module' => 'Product Master', 'check' => count($_SESSION["{$companyId}_product_master"] ?? $_SESSION['beverage_products'] ?? []) . ' synced SKU(s)', 'status' => 'Watching'],
            ['module' => 'Payment Recon', 'check' => count($_SESSION['payment_reconciliations'] ?? []) . ' reconciliation record(s)', 'status' => 'Watching'],
            ['module' => 'Finance Journal', 'check' => count($_SESSION["journal_entries_{$companyId}"] ?? []) . ' journal voucher(s)', 'status' => 'Watching'],
            ['module' => 'Audit Trail', 'check' => count($auditTrail) . ' audit movement(s)', 'status' => 'Watching'],
            ['module' => 'Training', 'check' => 'Contextual lessons available for active workflow', 'status' => 'Active'],
        ];
    }

    private static function buildSummary(int $score, int $criticalCount, int $warningCount): string
    {
        if ($criticalCount > 0) {
            return "AI is watching the live ERP and found {$criticalCount} critical exception(s). These should be fixed before stock, payment or finance close.";
        }
        if ($warningCount > 0) {
            return "AI is watching the live ERP and found {$warningCount} item(s) that need attention, but operations can continue with supervision.";
        }

        return "AI is watching the live ERP. Current operating discipline is healthy at {$score}% with no urgent exception.";
    }

    private static function recordExists(array $records, string $key, string $value): bool
    {
        foreach ($records as $record) {
            if ((string)($record[$key] ?? '') === $value) {
                return true;
            }
        }

        return false;
    }

    private static function containsReference(array $records, string $value): bool
    {
        foreach ($records as $record) {
            if (str_contains((string)($record['reference'] ?? ''), $value)) {
                return true;
            }
        }

        return false;
    }
}
