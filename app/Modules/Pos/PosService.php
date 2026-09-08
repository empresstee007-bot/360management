<?php

declare(strict_types=1);

namespace App\Modules\Pos;

use App\Core\Database;

class PosService
{
    private static array $beverageCatalogMock = [];

    private static array $beverageCustomersMock = [];

    public static function getCatalog(string $posMode = 'beverage'): array
    {
        if (class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            $whProducts = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProducts();
            $supplierCatalogPath = dirname(__DIR__, 2) . '/Config/suppliers.php';
            $supplierCatalog = file_exists($supplierCatalogPath) ? require $supplierCatalogPath : [];
            $supplierBrandMap = [];
            foreach (is_array($supplierCatalog) ? $supplierCatalog : [] as $supplier => $brands) {
                foreach (array_keys(is_array($brands) ? $brands : []) as $brand) {
                    $supplierBrandMap[strtoupper(trim((string)$brand))] = (string)$supplier;
                }
            }
            $posCatalog = [];
            foreach ($whProducts as $idx => $p) {
                $costPrice = (float)($p['cost_price'] ?? 0.0);
                $unitPrice = 0.0;
                if (!empty($p['wholesale_price']) && (float)$p['wholesale_price'] > 0) {
                    $unitPrice = (float)$p['wholesale_price'];
                } elseif (!empty($p['selling_price']) && (float)$p['selling_price'] > 0) {
                    $unitPrice = (float)$p['selling_price'];
                } elseif ($costPrice > 0) {
                    $unitPrice = $costPrice;
                }

                $packaging = trim((string)($p['packaging'] ?? 'Crate of 24'));
                $priceType = str_contains(strtolower($packaging), 'pack') || ($p['category'] ?? '') === 'Water' ? 'Packs' : 'Crates';
                $sku = (string)($p['sku'] ?? '');
                $supplier = trim((string)($p['supplier'] ?? ''));
                if ($supplier === '') {
                    $productName = strtoupper(trim((string)($p['name'] ?? '')));
                    foreach ($supplierBrandMap as $brand => $mappedSupplier) {
                        if ($brand !== '' && str_contains($productName, $brand)) {
                            $supplier = $mappedSupplier;
                            break;
                        }
                    }
                }

                // Dynamic pricing tiers and hierarchical rebate
                $pricingTiers = class_exists('App\Modules\Pos\PricingRebateService')
                    ? PricingRebateService::getProductTiers($sku, 'all')
                    : [];
                $rebateInfo = class_exists('App\Modules\Pos\PricingRebateService')
                    ? PricingRebateService::resolveRebate($p)
                    : ['rebate_pct' => 0.0, 'level' => 'global', 'rule_name' => 'No active rebate'];
                $defaultTier = class_exists('App\Modules\Pos\PricingRebateService')
                    ? PricingRebateService::resolveTierForQty($p, 1, 'depot_sale')
                    : ['selling_price' => $unitPrice, 'tier_name' => 'Standard Price'];
                if ((float)($defaultTier['selling_price'] ?? 0) > 0) {
                    $unitPrice = (float)$defaultTier['selling_price'];
                }

                $posCatalog[] = [
                    'id' => $p['id'] ?? ($idx + 100),
                    'sku' => $sku,
                    'name' => $p['name'] ?? '',
                    'brand' => $p['brand'] ?? '',
                    'supplier' => $supplier,
                    'category' => $p['category'] ?? 'Soft Drinks',
                    'packaging' => $packaging,
                    'packaging_type' => $p['packaging_type'] ?? '',
                    'inventory_unit' => $p['inventory_unit'] ?? '',
                    'packs_per_pallet' => (int)($p['packs_per_pallet'] ?? 0),
                    'invoice_price' => (float)($p['invoice_price'] ?? $costPrice),
                    'rebate_base_price' => (float)($p['rebate_base_price'] ?? 0),
                    'minimum_selling_price' => (float)($p['minimum_selling_price'] ?? 0),
                    'cost_price' => $costPrice,
                    'wholesale_price' => (float)($p['wholesale_price'] ?? $unitPrice),
                    'selling_price' => (float)($p['selling_price'] ?? $unitPrice),
                    'price_per_unit' => $unitPrice,
                    'price_type' => $priceType,
                    'crate_deposit' => (float)($p['crate_deposit'] ?? 0.0),
                    'stock_qty' => (int)($p['stock_crates'] ?? 0),
                    'icon' => '🥤',
                    'image' => $p['image'] ?? '',
                    'pricing_tiers' => $pricingTiers,
                    'default_tier_info' => $defaultTier,
                    'rebate_info' => $rebateInfo,
                ];
            }
            return $posCatalog;
        }

        $key = 'beverage_registered_products';
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        return $_SESSION[$key];
    }

    public static function registerNewProduct(string $posMode, array $data): array
    {
        $catalog = self::getCatalog($posMode);
        $sku = trim((string)($data['sku'] ?? 'BEV-' . rand(100, 999)));
        
        $existingIdx = null;
        foreach ($catalog as $idx => $item) {
            if (strcasecmp($item['sku'] ?? '', $sku) === 0) {
                $existingIdx = $idx;
                break;
            }
        }

        $name = trim((string)($data['name'] ?? ''));
        $category = trim((string)($data['category'] ?? ''));
        $packaging = trim((string)($data['packaging'] ?? ''));
        $price = (float)($data['price_per_unit'] ?? 0.00);
        $stock = (int)($data['stock_qty'] ?? 0);
        $crateDeposit = (float)($data['crate_deposit'] ?? 0.0);
        $icon = trim((string)($data['icon'] ?? ''));
        $image = trim((string)($data['image'] ?? ''));

        if ($existingIdx !== null) {
            $catalog[$existingIdx] = array_merge($catalog[$existingIdx], [
                'name' => $name,
                'category' => $category,
                'packaging' => $packaging,
                'price_per_unit' => $price,
                'crate_deposit' => $crateDeposit,
                'stock_qty' => $stock,
                'icon' => $icon,
                'image' => $image,
            ]);
            $newProd = $catalog[$existingIdx];
        } else {
            $newId = count($catalog) + 200;
            $newProd = [
                'id' => $newId,
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'packaging' => $packaging,
                'price_per_unit' => $price,
                'price_type' => 'Pack',
                'crate_deposit' => $crateDeposit,
                'stock_qty' => $stock,
                'icon' => $icon,
                'image' => $image,
            ];
            array_unshift($catalog, $newProd);
        }

        $key = 'beverage_registered_products';
        $_SESSION[$key] = $catalog;

        return $newProd;
    }

    public static function getCustomerAccounts(string $posMode = 'beverage'): array
    {
        $customers = [];
        $pdo = Database::getConnection();

        if ($pdo) {
            try {
                self::ensureCustomerCommercialColumns($pdo);
                $stmt = $pdo->prepare('SELECT id, name, phone, customer_class, distributor_code, wallet_balance, credit_limit, current_debt, crates_held FROM customers WHERE company_id = :company_id ORDER BY id ASC');
                $stmt->execute(['company_id' => $posMode]);
                $dbCustomers = $stmt->fetchAll();
                if (!empty($dbCustomers)) {
                    $customers = $dbCustomers;
                }
            } catch (\Throwable $t) {
                $customers = [];
            }
        }

        foreach ($_SESSION['pos_saved_customers'][$posMode] ?? [] as $savedCustomer) {
            $exists = false;
            foreach ($customers as $customer) {
                if (strcasecmp((string)($customer['name'] ?? ''), (string)($savedCustomer['name'] ?? '')) === 0) {
                    if (empty($customer['phone']) && !empty($savedCustomer['phone'])) {
                        $customer['phone'] = $savedCustomer['phone'];
                    }
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $customers[] = $savedCustomer;
            }
        }

        return $customers;
    }

    public static function saveCustomerFromPos(string $posMode, string $customerName, string $customerPhone = '', string $customerClass = 'Retail', string $distributorCode = ''): ?array
    {
        $customerName = trim($customerName);
        $customerPhone = trim($customerPhone);
        $customerClass = trim($customerClass) !== '' ? trim($customerClass) : 'Retail';
        $distributorCode = strtoupper(trim($distributorCode));
        if ($customerName === '' || strcasecmp($customerName, 'Walk-in Customer') === 0) {
            return null;
        }

        foreach (self::getCustomerAccounts($posMode) as $customer) {
            if (strcasecmp((string)($customer['name'] ?? ''), $customerName) === 0) {
                if ($customerPhone !== '' && empty($customer['phone'])) {
                    self::updateCustomerPhone($posMode, (int)($customer['id'] ?? 0), $customerName, $customerPhone);
                    $customer['phone'] = $customerPhone;
                }
                self::updateCustomerCommercialInfo($posMode, (int)($customer['id'] ?? 0), $customerName, $customerClass, $distributorCode);
                $customer['customer_class'] = $customerClass;
                $customer['distributor_code'] = $distributorCode;
                return $customer;
            }
        }

        $customer = [
            'id' => time(),
            'name' => $customerName,
            'phone' => $customerPhone,
            'customer_class' => $customerClass,
            'distributor_code' => $distributorCode,
            'wallet_balance' => 0.0,
            'credit_limit' => 0.0,
            'current_debt' => 0.0,
            'crates_held' => 0,
        ];

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureCustomerCommercialColumns($pdo);
                $stmt = $pdo->prepare(
                    'INSERT INTO customers (company_id, name, phone, customer_class, distributor_code, wallet_balance, credit_limit, current_debt, crates_held)
                     VALUES (:company_id, :name, :phone, :customer_class, :distributor_code, 0.00, 0.00, 0.00, 0)'
                );
                $stmt->execute([
                    'company_id' => $posMode,
                    'name' => $customerName,
                    'phone' => $customerPhone,
                    'customer_class' => $customerClass,
                    'distributor_code' => $distributorCode,
                ]);
                $customer['id'] = (int)$pdo->lastInsertId();
            } catch (\Throwable $t) {
                // Keep the POS usable even if the database is temporarily unavailable.
            }
        }

        $_SESSION['pos_saved_customers'][$posMode] ??= [];
        $_SESSION['pos_saved_customers'][$posMode][$customer['id']] = $customer;

        return $customer;
    }

    private static function ensureCustomerPhoneColumn(\PDO $pdo): void
    {
        self::ensureCustomerCommercialColumns($pdo);
    }

    private static function ensureCustomerCommercialColumns(\PDO $pdo): void
    {
        try {
            $pdo->exec('ALTER TABLE customers ADD COLUMN phone VARCHAR(40) DEFAULT NULL AFTER name');
        } catch (\Throwable $t) {
            // Column already exists, or the fallback database is unavailable.
        }
        foreach ([
            'customer_class VARCHAR(80) NOT NULL DEFAULT "Retail"',
            'distributor_code VARCHAR(80) DEFAULT NULL',
        ] as $columnSql) {
            try {
                $pdo->exec("ALTER TABLE customers ADD COLUMN {$columnSql}");
            } catch (\Throwable $t) {
            }
        }
    }

    private static function updateCustomerPhone(string $posMode, int $customerId, string $customerName, string $customerPhone): void
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureCustomerPhoneColumn($pdo);
                $stmt = $pdo->prepare(
                    'UPDATE customers SET phone = :phone WHERE company_id = :company_id AND (id = :id OR name = :name)'
                );
                $stmt->execute([
                    'phone' => $customerPhone,
                    'company_id' => $posMode,
                    'id' => $customerId,
                    'name' => $customerName,
                ]);
            } catch (\Throwable $t) {
                // Keep checkout moving; phone can be captured again later.
            }
        }

        foreach ($_SESSION['pos_saved_customers'][$posMode] ?? [] as $idx => $customer) {
            if ((int)($customer['id'] ?? 0) === $customerId || strcasecmp((string)($customer['name'] ?? ''), $customerName) === 0) {
                $_SESSION['pos_saved_customers'][$posMode][$idx]['phone'] = $customerPhone;
            }
        }
    }

    private static function updateCustomerCommercialInfo(string $posMode, int $customerId, string $customerName, string $customerClass, string $distributorCode): void
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureCustomerCommercialColumns($pdo);
                $stmt = $pdo->prepare(
                    'UPDATE customers SET customer_class = :customer_class, distributor_code = :distributor_code WHERE company_id = :company_id AND (id = :id OR name = :name)'
                );
                $stmt->execute([
                    'customer_class' => $customerClass,
                    'distributor_code' => $distributorCode !== '' ? $distributorCode : null,
                    'company_id' => $posMode,
                    'id' => $customerId,
                    'name' => $customerName,
                ]);
            } catch (\Throwable $t) {
            }
        }

        foreach ($_SESSION['pos_saved_customers'][$posMode] ?? [] as $idx => $customer) {
            if ((int)($customer['id'] ?? 0) === $customerId || strcasecmp((string)($customer['name'] ?? ''), $customerName) === 0) {
                $_SESSION['pos_saved_customers'][$posMode][$idx]['customer_class'] = $customerClass;
                $_SESSION['pos_saved_customers'][$posMode][$idx]['distributor_code'] = $distributorCode;
            }
        }
    }

    public static function processSale(array $payload): array
    {
        $mode = 'beverage';
        $customerName = trim((string)($payload['customer_name'] ?? 'Walk-in Customer'));
        $customerPhone = trim((string)($payload['customer_phone'] ?? ''));
        $customerClass = trim((string)($payload['customer_class'] ?? 'Retail'));
        $distributorCode = strtoupper(trim((string)($payload['distributor_code'] ?? '')));
        $savedCustomer = null;
        if (($payload['save_customer'] ?? '') === '1') {
            $savedCustomer = self::saveCustomerFromPos($mode, $customerName, $customerPhone, $customerClass, $distributorCode);
        }
        $paymentMethod = trim((string)($payload['payment_method'] ?? 'Cash'));
        $salesChannel = class_exists('App\Modules\Pos\PricingRebateService')
            ? PricingRebateService::normalizeSalesChannel((string)($payload['sales_channel'] ?? 'depot_sale'))
            : 'depot_sale';
        $items = json_decode($payload['cart_items'] ?? '[]', true) ?: [];
        $discount = (float)($payload['discount'] ?? 0.0);

        $receiptNo = 'POS-BEV-' . date('Ymd-His') . '-' . rand(10, 99);

        $loggedUser = current_user() ?? [];
        $cashierName = $loggedUser['name'] ?? 'Beverage Cashier';
        $cashierEmployee = null;
        if (class_exists('App\Modules\HrPayroll\HrPayrollService')) {
            foreach (\App\Modules\HrPayroll\HrPayrollService::getEmployees() as $employee) {
                if (strcasecmp((string)($employee['email'] ?? ''), (string)($loggedUser['email'] ?? '')) === 0) {
                    $cashierEmployee = $employee;
                    break;
                }
            }
        }
        $branchName = 'Jacroxx Warehouse (Main)';

        // Generate permanent, immutable line-item snapshots
        $snapshottedItems = [];
        $totalCostPrice = 0.0;
        $totalRebateAmount = 0.0;
        $totalEffectiveCost = 0.0;
        $totalProfitBeforeRebate = 0.0;
        $totalTrueProfitAfterRebate = 0.0;
        $totalDirectCost = 0.0;
        $totalOperationalNetMargin = 0.0;
        $subtotal = 0.0;
        $crateDeposit = 0.0;

        foreach ($items as $rawItem) {
            $qty = max(1, (int)($rawItem['qty'] ?? 1));

            $snapshot = class_exists('App\Modules\Pos\PricingRebateService')
                ? PricingRebateService::createLineItemSnapshot($rawItem, $qty, $cashierName, $branchName, null, [
                    'sales_channel' => $salesChannel,
                    'customer_class' => $customerClass,
                    'location' => $branchName,
                    'promotion_code' => (string)($payload['promotion_code'] ?? ''),
                    'override_reason' => (string)($payload['override_reason'] ?? ''),
                ])
                : $rawItem;

            $snapshottedItems[] = $snapshot;
            $subtotal += (float)($snapshot['total'] ?? 0);
            $crateDeposit += ((float)($snapshot['crate_deposit'] ?? 0) * $qty);
            $totalCostPrice += ((float)($snapshot['cost_price'] ?? 0) * $qty);
            $totalRebateAmount += (float)($snapshot['total_rebate_amount'] ?? 0);
            $totalEffectiveCost += (float)($snapshot['total_effective_cost'] ?? 0);
            $totalProfitBeforeRebate += (float)($snapshot['total_profit_before_rebate'] ?? 0);
            $totalTrueProfitAfterRebate += (float)($snapshot['total_true_profit'] ?? 0);
            $totalDirectCost += (float)($snapshot['total_direct_cost'] ?? 0);
            $totalOperationalNetMargin += (float)($snapshot['total_operational_net_margin'] ?? 0);
        }

        $belowThresholdItems = array_values(array_filter($snapshottedItems, static fn(array $item): bool => !empty($item['below_minimum_threshold'])));
        if ($belowThresholdItems) {
            $user = current_user();
            $canOverride = class_exists('App\Modules\Pos\PricingRebateService')
                ? PricingRebateService::canManagePricing($user)
                : false;
            $overrideReason = trim((string)($payload['override_reason'] ?? ''));

            if (!$canOverride || $overrideReason === '') {
                return [
                    'error' => true,
                    'message' => 'Sale blocked: one or more items are below the configured minimum selling price. A manager/admin override reason is required.',
                    'blocked_items' => array_map(static fn(array $item): string => (string)($item['sku'] ?? $item['name'] ?? 'Item'), $belowThresholdItems),
                ];
            }

            foreach ($belowThresholdItems as $item) {
                PricingRebateService::logAudit(
                    'price_override',
                    (string)($item['sku'] ?? ''),
                    'Approve Below Minimum Sale',
                    ['minimum_selling_price' => $item['minimum_selling_price'] ?? 0],
                    ['actual_selling_price' => $item['actual_selling_price'] ?? 0, 'reason' => $overrideReason, 'sales_channel' => $salesChannel],
                    $user['name'] ?? $cashierName
                );
            }
        }

        $posSettings = $_SESSION['beverage_pos_settings'] ?? [];
        if (($posSettings['crate_deposit_enabled'] ?? '1') !== '1') {
            $crateDeposit = 0.0;
        }
        $grandTotal = max(0.0, $subtotal - $discount + $crateDeposit);

        $transaction = [
            'receipt_no' => $receiptNo,
            'pos_mode' => $mode,
            'mode_title' => 'EMPRESS TEE BEVERAGE DEPOT POS',
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_class' => $customerClass,
            'distributor_code' => $distributorCode,
            'payment_method' => $paymentMethod,
            'sales_channel' => $salesChannel,
            'items' => $snapshottedItems,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'crate_deposit' => $crateDeposit,
            'grand_total' => $grandTotal,
            'total_cost_price' => $totalCostPrice,
            'total_rebate_amount' => $totalRebateAmount,
            'total_effective_cost' => $totalEffectiveCost,
            'total_profit_before_rebate' => $totalProfitBeforeRebate,
            'total_true_profit_after_rebate' => $totalTrueProfitAfterRebate,
            'total_direct_cost' => $totalDirectCost,
            'total_operational_net_margin' => $totalOperationalNetMargin,
            'cashier_name' => $cashierName,
            'employee_code' => $cashierEmployee['employee_code'] ?? null,
            'employee_role' => $cashierEmployee['employee_role'] ?? ($loggedUser['role'] ?? null),
            'pos_terminal' => $posSettings['terminal_name'] ?? 'Main POS',
            'pos_login_time' => $_SESSION['pos_login_time'] ?? ($_SESSION['pos_login_time'] = date('Y-m-d H:i:s')),
            'branch' => $branchName,
            'timestamp' => date('d M Y, h:i A'),
            'qr_code_data' => "REC:{$receiptNo}|TOT:{$grandTotal}|PAY:{$paymentMethod}",
            'customer_saved' => $savedCustomer !== null,
        ];

        if (str_contains(strtolower($paymentMethod), 'transfer')) {
            $transaction['transfer_status'] = 'Pending POS Confirmation';
            $transaction['transfer_confirmed_by'] = null;
            $transaction['transfer_confirmed_at'] = null;
            $transaction['transfer_approved_by'] = null;
            $transaction['transfer_approved_at'] = null;
        }

        // Sync with Central Real-Time Cross-Module Data Engine
        if (class_exists('App\Core\UnifiedDataEngine')) {
            $transaction = \App\Core\UnifiedDataEngine::recordSale($transaction);
        } else {
            $history = $_SESSION['pos_sales_history'] ?? [];
            array_unshift($history, $transaction);
            $_SESSION['pos_sales_history'] = $history;
        }

        $_SESSION['last_pos_receipt'] = $transaction;

        return $transaction;
    }

    public static function updateTransferPaymentStatus(string $receiptNo, string $action): ?array
    {
        $history = $_SESSION['pos_sales_history'] ?? [];
        foreach ($history as $idx => $tx) {
            if (($tx['receipt_no'] ?? '') !== $receiptNo) {
                continue;
            }

            $method = strtolower((string)($tx['payment_method'] ?? ''));
            if (!str_contains($method, 'transfer')) {
                return null;
            }

            if ($action === 'confirm_paid') {
                $history[$idx]['transfer_status'] = 'Paid - Awaiting Approval';
                $history[$idx]['transfer_confirmed_by'] = current_user()['name'] ?? 'POS Cashier';
                $history[$idx]['transfer_confirmed_at'] = date('d M Y, h:i A');
            } elseif ($action === 'approve_paid') {
                $history[$idx]['transfer_status'] = 'Approved & Posted';
                $history[$idx]['transfer_confirmed_by'] = $history[$idx]['transfer_confirmed_by'] ?? (current_user()['name'] ?? 'POS Cashier');
                $history[$idx]['transfer_confirmed_at'] = $history[$idx]['transfer_confirmed_at'] ?? date('d M Y, h:i A');
                $history[$idx]['transfer_approved_by'] = current_user()['name'] ?? 'Beverage Manager';
                $history[$idx]['transfer_approved_at'] = date('d M Y, h:i A');
            }

            $_SESSION['pos_sales_history'] = $history;
            if (class_exists('App\Core\UnifiedDataEngine')) {
                \App\Core\UnifiedDataEngine::syncTransferApproval($history[$idx]);
            }
            return $history[$idx];
        }

        return null;
    }

    public static function getDailyShiftSummary(string $posMode = 'beverage'): array
    {
        $history = $_SESSION['pos_sales_history'] ?? [];
        $totalSales = 0.0;
        $totalCash = 0.0;
        $totalTransfer = 0.0;
        $totalPos = 0.0;
        $totalDebt = 0.0;
        $totalWallet = 0.0;
        $totalCrateDeposit = 0.0;
        $count = 0;

        foreach ($history as $tx) {
            if (($tx['pos_mode'] ?? 'beverage') === $posMode) {
                $tot = (float)($tx['grand_total'] ?? 0);
                $method = strtolower((string)($tx['payment_method'] ?? 'cash'));
                $totalSales += $tot;
                $totalCrateDeposit += (float)($tx['crate_deposit'] ?? 0);
                $count++;

                if (str_contains($method, 'cash')) {
                    $totalCash += $tot;
                } elseif (str_contains($method, 'transfer')) {
                    $totalTransfer += $tot;
                } elseif (str_contains($method, 'card') || str_contains($method, 'pos')) {
                    $totalPos += $tot;
                } elseif (str_contains($method, 'debt') || str_contains($method, 'credit')) {
                    $totalDebt += $tot;
                } elseif (str_contains($method, 'wallet')) {
                    $totalWallet += $tot;
                } else {
                    $totalCash += $tot;
                }
            }
        }

        $openingFloat = 0.00;
        $expectedCash = $openingFloat + $totalCash;

        return [
            'total_sales' => $totalSales,
            'total_cash' => $totalCash,
            'total_transfer' => $totalTransfer,
            'total_pos' => $totalPos,
            'total_debt' => $totalDebt,
            'total_wallet' => $totalWallet,
            'total_crate_deposit' => $totalCrateDeposit,
            'total_transactions' => $count,
            'opening_float' => $openingFloat,
            'expected_closing_cash' => $expectedCash,
        ];
    }
}
