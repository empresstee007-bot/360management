<?php

declare(strict_types=1);

namespace App\Modules\BeverageWarehouse;

use App\Core\Database;
use App\Modules\Pos\PricingRebateService;

class ProcurementReceivingService
{
    /**
     * Process receiving of a Purchase Order, generate GRN, and release stock upon inspection
     */
    public static function processReceiving(array $data): array
    {
        $poNumber = $data['po_number'] ?? 'PO-BEV-' . rand(1000, 9999);
        $sku = strtoupper(trim((string)($data['sku'] ?? '')));
        $supplier = trim((string)($data['supplier'] ?? ''));
        $warehouse = BeverageWarehouseService::normalizeWarehouse($data['warehouse'] ?? null);
        $ordered = (int)($data['ordered_qty'] ?? 0);
        $delivered = (int)($data['delivered_qty'] ?? 0);
        $rejected = (int)($data['rejected_qty'] ?? 0);
        $damaged = (int)($data['damaged_qty'] ?? 0);
        $promoFree = (int)($data['promo_free_qty'] ?? 0);
        $invoiceCost = max(0.0, (float)($data['invoice_cost'] ?? $data['invoice_price'] ?? 0));
        $rebateBasePrice = max(0.0, (float)($data['rebate_base_price'] ?? 0));
        $rebatePct = max(0.0, (float)($data['expected_rebate_pct'] ?? $data['rebate_pct'] ?? 0));
        $rebateAdjustment = max(0.0, (float)($data['rebate_adjustment_factor'] ?? 100));
        $promotionCode = strtoupper(trim((string)($data['promotion_code'] ?? '')));
        $promotionBenefit = max(0.0, (float)($data['promotion_benefit_per_unit'] ?? 0));

        $accepted = max(0, $delivered - $rejected - $damaged) + $promoFree;
        $grnNumber = 'GRN-' . date('Ymd') . '-' . rand(100, 999);

        $shortage = max(0, $ordered - $delivered);
        $excess = max(0, $delivered - $ordered);
        $expectedRebateAmount = $accepted * (($rebateBasePrice > 0 ? $rebateBasePrice : $invoiceCost) * ($rebatePct / 100.0) * ($rebateAdjustment / 100.0));
        $effectiveLandingCost = max(0.0, $invoiceCost - ($accepted > 0 ? ($expectedRebateAmount / $accepted) : 0.0) - $promotionBenefit);

        $stockBefore = null;
        $stockAfter = null;
        if ($sku !== '' && $accepted > 0) {
            $warehouseStock = BeverageWarehouseService::getProductWarehouseStock($sku);
            $stockBefore = (int)($warehouseStock[$warehouse] ?? 0);
            $stockAfter = BeverageWarehouseService::setProductWarehouseStock($sku, $warehouse, $stockBefore + $accepted);
            self::syncProductCommercialCost($sku, $stockAfter, $invoiceCost, $rebateBasePrice);
            BeverageWarehouseService::logMovement([
                'user' => \current_user()['name'] ?? 'Warehouse Admin',
                'action' => 'GRN Stock Received',
                'item' => $sku,
                'sku' => $sku,
                'quantity' => '+' . number_format($accepted) . ' Crates/Packs',
                'previous_balance' => number_format($stockBefore) . ' Crates/Packs',
                'new_balance' => number_format($stockAfter) . ' Crates/Packs',
                'location' => $warehouse,
                'reference' => $grnNumber,
                'timestamp' => date('d M Y, h:i A'),
            ]);
        }

        $result = [
            'grn_number' => $grnNumber,
            'po_number' => $poNumber,
            'supplier' => $supplier,
            'sku' => $sku,
            'warehouse' => $warehouse,
            'ordered_qty' => $ordered,
            'delivered_qty' => $delivered,
            'accepted_qty' => $accepted,
            'rejected_qty' => $rejected,
            'damaged_qty' => $damaged,
            'shortage_qty' => $shortage,
            'excess_qty' => $excess,
            'free_promo_qty' => $promoFree,
            'invoice_cost' => $invoiceCost,
            'rebate_base_price' => $rebateBasePrice,
            'expected_rebate_pct' => $rebatePct,
            'rebate_adjustment_factor' => $rebateAdjustment,
            'expected_rebate_amount' => $expectedRebateAmount,
            'promotion_code' => $promotionCode,
            'promotion_benefit_per_unit' => $promotionBenefit,
            'effective_landing_cost' => $effectiveLandingCost,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'inspection_status' => ($rejected > 0 || $damaged > 0) ? 'Passed with Variance' : 'Passed & Stock Available',
            'stock_released' => true,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        self::persistGrn($result);
        if (class_exists(PricingRebateService::class) && $expectedRebateAmount > 0) {
            PricingRebateService::recordRebateSettlement([
                'supplier' => $supplier,
                'sku' => $sku !== '' ? $sku : 'ALL',
                'period_start' => date('Y-m-01'),
                'period_end' => date('Y-m-t'),
                'expected_amount' => $expectedRebateAmount,
                'confirmed_amount' => 0,
                'received_amount' => 0,
                'status' => 'Expected from GRN',
                'reference' => $grnNumber,
                'notes' => 'Auto-created expected rebate from goods received note.',
            ], \current_user()['name'] ?? 'Warehouse Admin');
        }

        return $result;
    }

    public static function getGrnRecords(int $limit = 100): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTable($pdo);
                $limit = max(1, min(500, $limit));
                $stmt = $pdo->query('SELECT grn_json FROM beverage_grn_records WHERE company_id = "beverage" ORDER BY id DESC LIMIT ' . $limit);
                $rows = $stmt ? $stmt->fetchAll() : [];
                if ($rows) {
                    $_SESSION['beverage_grn_records'] = array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['grn_json'], true) ?: null, $rows)));
                    return $_SESSION['beverage_grn_records'];
                }
            } catch (\Throwable $t) {
            }
        }

        return array_slice($_SESSION['beverage_grn_records'] ?? [], 0, $limit);
    }

    private static function persistGrn(array $grn): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['beverage_grn_records'] = $_SESSION['beverage_grn_records'] ?? [];
        array_unshift($_SESSION['beverage_grn_records'], $grn);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensureTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO beverage_grn_records
                    (grn_number, company_id, po_number, supplier, sku, warehouse, accepted_qty, invoice_cost, rebate_base_price, expected_rebate_pct, expected_rebate_amount, effective_landing_cost, grn_json, created_at)
                 VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE po_number=VALUES(po_number), supplier=VALUES(supplier), sku=VALUES(sku), warehouse=VALUES(warehouse), accepted_qty=VALUES(accepted_qty), invoice_cost=VALUES(invoice_cost), rebate_base_price=VALUES(rebate_base_price), expected_rebate_pct=VALUES(expected_rebate_pct), expected_rebate_amount=VALUES(expected_rebate_amount), effective_landing_cost=VALUES(effective_landing_cost), grn_json=VALUES(grn_json)'
            );
            $stmt->execute([
                $grn['grn_number'],
                $grn['po_number'],
                $grn['supplier'] ?: null,
                $grn['sku'] ?: null,
                $grn['warehouse'],
                (int)$grn['accepted_qty'],
                (float)$grn['invoice_cost'],
                (float)$grn['rebate_base_price'],
                (float)$grn['expected_rebate_pct'],
                (float)$grn['expected_rebate_amount'],
                (float)$grn['effective_landing_cost'],
                json_encode($grn, JSON_UNESCAPED_SLASHES),
                $grn['timestamp'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function syncProductCommercialCost(string $sku, int $stockAfter, float $invoiceCost, float $rebateBasePrice): void
    {
        if ($sku === '' || $invoiceCost <= 0) {
            return;
        }

        foreach (BeverageWarehouseService::getProducts() as $product) {
            if (strcasecmp((string)($product['sku'] ?? ''), $sku) !== 0) {
                continue;
            }
            $product['cost_price'] = $invoiceCost;
            $product['invoice_price'] = $invoiceCost;
            if ($rebateBasePrice > 0) {
                $product['rebate_base_price'] = $rebateBasePrice;
            }
            $product['stock_crates'] = $stockAfter;
            $product['stock_bottles'] = $stockAfter * max(1, (int)($product['units_per_crate'] ?? $product['units_per_pack'] ?? 1));
            BeverageWarehouseService::saveProduct($product);
            break;
        }
    }

    private static function ensureTable(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS beverage_grn_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grn_number VARCHAR(80) NOT NULL UNIQUE,
            company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
            po_number VARCHAR(100) DEFAULT NULL,
            supplier VARCHAR(150) DEFAULT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            warehouse VARCHAR(120) DEFAULT NULL,
            accepted_qty INT NOT NULL DEFAULT 0,
            invoice_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            rebate_base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            expected_rebate_pct DECIMAL(7,3) NOT NULL DEFAULT 0.000,
            expected_rebate_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            effective_landing_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            grn_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_beverage_grn_lookup (company_id, sku, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
