<?php

declare(strict_types=1);

namespace App\Modules\BeverageWarehouse;

use App\Core\Database;

class TruckLoadService
{
    public static function getSettings(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            $stored = self::loadSettingsFromDatabase($pdo);
            if ($stored) {
                $_SESSION['truck_load_settings'] = $stored;
            }
        }

        $settings = $_SESSION['truck_load_settings'] ?? [
            'default_pallet_capacity' => 12,
            'allow_partial_pallets' => false,
        ];

        return [
            'default_pallet_capacity' => max(1, (int)($settings['default_pallet_capacity'] ?? 12)),
            'allow_partial_pallets' => !empty($settings['allow_partial_pallets']),
        ];
    }

    public static function saveSettings(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $old = self::getSettings();
        $new = [
            'default_pallet_capacity' => max(1, (int)($data['default_pallet_capacity'] ?? 12)),
            'allow_partial_pallets' => !empty($data['allow_partial_pallets']),
        ];
        $_SESSION['truck_load_settings'] = $new;
        self::persistSettings($new);
        self::logAudit('truck_load_settings', 'global', 'Update Truck Load Settings', $old, $new, $user ?? (\current_user()['name'] ?? 'Warehouse Admin'));

        return $new;
    }

    public static function inferPacksPerPallet(array $product): int
    {
        if (!empty($product['packs_per_pallet'])) {
            return max(1, (int)$product['packs_per_pallet']);
        }

        $name = strtoupper((string)($product['name'] ?? ''));
        $packaging = strtoupper((string)($product['packaging'] ?? ''));
        $size = strtoupper((string)($product['size'] ?? ''));
        $haystack = "{$name} {$packaging} {$size}";

        if (str_contains($haystack, 'RGB 30CL') || str_contains($haystack, 'RBG 30CL')) {
            return 48;
        }
        if (str_contains($haystack, 'RGB 50CL') || str_contains($haystack, 'RBG 50CL')) {
            return 40;
        }
        if (str_contains($haystack, '30CL') && str_contains($haystack, 'PET')) {
            return 224;
        }

        return 154;
    }

    public static function calculateLoad(array $lines): array
    {
        $settings = self::getSettings();
        $products = BeverageWarehouseService::getProducts();
        $bySku = [];
        foreach ($products as $product) {
            $sku = strtoupper(trim((string)($product['sku'] ?? '')));
            if ($sku !== '') {
                $bySku[$sku] = $product;
            }
        }

        $rows = [];
        $totalPallets = 0.0;
        $totalPacks = 0;
        $totalCrates = 0;
        $totalValue = 0.0;

        foreach ($lines as $line) {
            $sku = strtoupper(trim((string)($line['sku'] ?? '')));
            if ($sku === '' || !isset($bySku[$sku])) {
                continue;
            }

            $product = $bySku[$sku];
            $pallets = max(0.0, (float)($line['pallets'] ?? 0));
            if (empty($settings['allow_partial_pallets']) && abs($pallets - round($pallets)) > 0.0001) {
                $rows[] = [
                    'sku' => $sku,
                    'name' => (string)($product['name'] ?? $sku),
                    'source_warehouse' => BeverageWarehouseService::normalizeWarehouse($line['source_warehouse'] ?? null),
                    'pallets' => $pallets,
                    'packs_per_pallet' => self::inferPacksPerPallet($product),
                    'actual_quantity' => 0,
                    'inventory_unit' => 'Invalid',
                    'line_value' => 0.0,
                    'error' => 'Partial pallets are disabled in truck settings.',
                ];
                continue;
            }

            $perPallet = max(1, (int)($line['packs_per_pallet'] ?? self::inferPacksPerPallet($product)));
            $actualQty = (int)round($pallets * $perPallet);
            $packaging = strtoupper((string)($product['packaging'] ?? ''));
            $isCrate = str_contains($packaging, 'CRATE') || str_contains((string)($product['name'] ?? ''), 'RGB') || str_contains((string)($product['name'] ?? ''), 'RBG');
            $unitCost = (float)($product['cost_price'] ?? $product['wholesale_price'] ?? 0);
            $lineValue = $actualQty * $unitCost;

            $totalPallets += $pallets;
            if ($isCrate) {
                $totalCrates += $actualQty;
            } else {
                $totalPacks += $actualQty;
            }
            $totalValue += $lineValue;

            $rows[] = [
                'sku' => $sku,
                'name' => (string)($product['name'] ?? $sku),
                'source_warehouse' => BeverageWarehouseService::normalizeWarehouse($line['source_warehouse'] ?? null),
                'pallets' => $pallets,
                'packs_per_pallet' => $perPallet,
                'actual_quantity' => $actualQty,
                'inventory_unit' => $isCrate ? 'Crates' : 'Packs',
                'line_value' => $lineValue,
            ];
        }

        return [
            'settings' => $settings,
            'rows' => $rows,
            'totals' => [
                'pallets' => $totalPallets,
                'packs' => $totalPacks,
                'crates' => $totalCrates,
                'value' => $totalValue,
                'over_capacity' => $totalPallets > $settings['default_pallet_capacity'],
                'has_errors' => count(array_filter($rows, static fn(array $row): bool => !empty($row['error']))) > 0,
            ],
        ];
    }

    public static function createLoad(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $lines = self::normalizePostedLines($data);
        $calculation = self::calculateLoad($lines);
        if (($calculation['totals']['over_capacity'] ?? false) === true) {
            return ['success' => false, 'message' => 'Truck load exceeds configured pallet capacity.', 'load' => $calculation];
        }
        if (($calculation['totals']['has_errors'] ?? false) === true) {
            return ['success' => false, 'message' => 'Truck load contains invalid pallet quantities. Enable partial pallets in settings or use whole pallets.', 'load' => $calculation];
        }

        $load = [
            'id' => 'LOAD-' . date('Ymd-His') . '-' . rand(10, 99),
            'customer' => trim((string)($data['customer'] ?? '')),
            'sales_channel' => trim((string)($data['sales_channel'] ?? 'diversion_sale')),
            'status' => 'Planned',
            'created_by' => $user ?? (\current_user()['name'] ?? 'Warehouse Admin'),
            'created_at' => date('Y-m-d H:i:s'),
            'calculation' => $calculation,
        ];

        $_SESSION['truck_loads'] = $_SESSION['truck_loads'] ?? [];
        array_unshift($_SESSION['truck_loads'], $load);
        self::persistLoad($load);
        self::logAudit('truck_load', $load['id'], 'Create Truck Load', [], $load, $load['created_by']);

        return ['success' => true, 'message' => 'Truck load plan created. Inventory will be deducted only when finalized through stock movement.', 'load' => $load];
    }

    public static function finalizeLoad(string $loadId, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $loadId = trim($loadId);
        if ($loadId === '') {
            return ['success' => false, 'message' => 'Load ID is required.'];
        }

        $loads = self::getLoads();
        $loadIndex = null;
        $load = null;
        foreach ($loads as $idx => $candidate) {
            if ((string)($candidate['id'] ?? '') === $loadId) {
                $loadIndex = $idx;
                $load = $candidate;
                break;
            }
        }

        if (!$load) {
            return ['success' => false, 'message' => 'Truck load plan was not found.'];
        }

        if (($load['status'] ?? '') === 'Finalized') {
            return ['success' => false, 'message' => 'This truck load has already been finalized.'];
        }

        $rows = $load['calculation']['rows'] ?? [];
        if (!$rows) {
            return ['success' => false, 'message' => 'This truck load has no valid SKU rows to finalize.'];
        }

        $stockErrors = [];
        foreach ($rows as $row) {
            if (!empty($row['error'])) {
                $stockErrors[] = (string)($row['name'] ?? $row['sku'] ?? 'SKU') . ': ' . (string)$row['error'];
                continue;
            }
            $sku = (string)($row['sku'] ?? '');
            $warehouse = BeverageWarehouseService::normalizeWarehouse($row['source_warehouse'] ?? null);
            $available = BeverageWarehouseService::getProductWarehouseStock($sku)[$warehouse] ?? 0;
            $required = (int)($row['actual_quantity'] ?? 0);
            if ($required <= 0) {
                $stockErrors[] = "{$sku}: quantity must be greater than zero.";
                continue;
            }
            if ($available < $required) {
                $stockErrors[] = "{$sku}: {$required} required from {$warehouse}, only {$available} available.";
            }
        }

        if ($stockErrors) {
            return [
                'success' => false,
                'message' => 'Truck load cannot be finalized because stock is insufficient: ' . implode(' ', $stockErrors),
                'load' => $load,
            ];
        }

        $products = BeverageWarehouseService::getProducts();
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string)($row['sku'] ?? '')));
            $warehouse = BeverageWarehouseService::normalizeWarehouse($row['source_warehouse'] ?? null);
            $required = (int)($row['actual_quantity'] ?? 0);
            $currentWarehouseStock = BeverageWarehouseService::getProductWarehouseStock($sku)[$warehouse] ?? 0;
            $newTotalStock = BeverageWarehouseService::setProductWarehouseStock($sku, $warehouse, $currentWarehouseStock - $required);

            foreach ($products as $idx => $product) {
                if (strcasecmp((string)($product['sku'] ?? ''), $sku) !== 0) {
                    continue;
                }
                $unitsPerCrate = max(0, (int)($product['units_per_crate'] ?? $product['units_per_pack'] ?? 0));
                $products[$idx]['stock_crates'] = $newTotalStock;
                $products[$idx]['stock_bottles'] = $newTotalStock * $unitsPerCrate;
                if (class_exists('App\Core\UnifiedDataEngine')) {
                    \App\Core\UnifiedDataEngine::saveProduct($products[$idx]);
                }
                break;
            }

            BeverageWarehouseService::logMovement([
                'user' => $user ?? (\current_user()['name'] ?? 'Warehouse Admin'),
                'action' => 'Truck Load Finalized',
                'item' => (string)($row['name'] ?? $sku),
                'quantity' => '-' . number_format($required) . ' ' . (string)($row['inventory_unit'] ?? 'Packs/Crates'),
                'previous_balance' => number_format($currentWarehouseStock) . ' ' . (string)($row['inventory_unit'] ?? 'Packs/Crates'),
                'new_balance' => number_format(max(0, $currentWarehouseStock - $required)) . ' ' . (string)($row['inventory_unit'] ?? 'Packs/Crates'),
                'location' => $warehouse,
                'timestamp' => date('d M Y, h:i A'),
            ]);
        }

        $_SESSION['beverage_products'] = $products;
        $oldLoad = $load;
        $load['status'] = 'Finalized';
        $load['finalized_by'] = $user ?? (\current_user()['name'] ?? 'Warehouse Admin');
        $load['finalized_at'] = date('Y-m-d H:i:s');
        if ($loadIndex !== null) {
            $loads[$loadIndex] = $load;
        }
        $_SESSION['truck_loads'] = $loads;
        self::updatePersistedLoad($load);
        self::logAudit('truck_load', $load['id'], 'Finalize Truck Load', $oldLoad, $load, $load['finalized_by']);

        return ['success' => true, 'message' => 'Truck load finalized and stock deducted once.', 'load' => $load];
    }

    public static function getLoads(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->query('SELECT load_json FROM truck_loads WHERE company_id = "beverage" ORDER BY id DESC LIMIT 100');
                $rows = $stmt ? $stmt->fetchAll() : [];
                if ($rows) {
                    $_SESSION['truck_loads'] = array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['load_json'], true) ?: null, $rows)));
                }
            } catch (\Throwable $t) {
            }
        }

        return $_SESSION['truck_loads'] ?? [];
    }

    private static function normalizePostedLines(array $data): array
    {
        $skus = $data['load_sku'] ?? [];
        $pallets = $data['load_pallets'] ?? [];
        $perPallet = $data['load_packs_per_pallet'] ?? [];
        $warehouses = $data['load_source_warehouse'] ?? [];
        $lines = [];

        foreach ((array)$skus as $index => $sku) {
            $lines[] = [
                'sku' => $sku,
                'pallets' => (float)($pallets[$index] ?? 0),
                'packs_per_pallet' => (int)($perPallet[$index] ?? 0),
                'source_warehouse' => $warehouses[$index] ?? null,
            ];
        }

        return $lines;
    }

    private static function persistLoad(array $load): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO truck_loads (load_id, company_id, sales_channel, customer_name, load_json, created_by, created_at) VALUES (?, "beverage", ?, ?, ?, ?, ?)');
            $stmt->execute([
                $load['id'],
                $load['sales_channel'],
                $load['customer'],
                json_encode($load, JSON_UNESCAPED_SLASHES),
                $load['created_by'],
                $load['created_at'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function updatePersistedLoad(array $load): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('UPDATE truck_loads SET load_json = ?, sales_channel = ?, customer_name = ? WHERE load_id = ? AND company_id = "beverage"');
            $stmt->execute([
                json_encode($load, JSON_UNESCAPED_SLASHES),
                $load['sales_channel'] ?? 'diversion_sale',
                $load['customer'] ?? '',
                $load['id'] ?? '',
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function loadSettingsFromDatabase(\PDO $pdo): ?array
    {
        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('SELECT setting_value FROM truck_load_settings WHERE company_id = "beverage" AND setting_key = "default" LIMIT 1');
            $stmt->execute();
            $json = $stmt->fetchColumn();
            if (!$json) {
                return null;
            }
            $settings = json_decode((string)$json, true);
            if (!is_array($settings)) {
                return null;
            }

            return [
                'default_pallet_capacity' => max(1, (int)($settings['default_pallet_capacity'] ?? 12)),
                'allow_partial_pallets' => !empty($settings['allow_partial_pallets']),
            ];
        } catch (\Throwable $t) {
            return null;
        }
    }

    private static function persistSettings(array $settings): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO truck_load_settings (company_id, setting_key, setting_value, updated_at) VALUES ("beverage", "default", ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)');
            $stmt->execute([json_encode($settings, JSON_UNESCAPED_SLASHES)]);
        } catch (\Throwable $t) {
        }
    }

    private static function ensureTables(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS truck_load_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
            setting_key VARCHAR(80) NOT NULL DEFAULT "default",
            setting_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_truck_load_setting (company_id, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $pdo->exec('CREATE TABLE IF NOT EXISTS truck_loads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            load_id VARCHAR(80) NOT NULL UNIQUE,
            company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
            sales_channel VARCHAR(30) NOT NULL DEFAULT "diversion_sale",
            customer_name VARCHAR(160) DEFAULT NULL,
            load_json LONGTEXT NOT NULL,
            created_by VARCHAR(120) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_truck_loads_company_channel (company_id, sales_channel, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private static function logAudit(string $entityType, string $entityId, string $action, array $old, array $new, string $user): void
    {
        if (class_exists('App\Modules\Pos\PricingRebateService')) {
            \App\Modules\Pos\PricingRebateService::logAudit($entityType, $entityId, $action, $old, $new, $user);
        }
    }
}
