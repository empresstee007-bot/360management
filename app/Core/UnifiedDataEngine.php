<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Class UnifiedDataEngine
 * Real-Time Centralized Data Engine for 360Management ERP
 * Connects Warehouse Inventory, POS Sales, Logistics POD, Payment Recon, Procurement GRN, Customer Credit & GL.
 */
class UnifiedDataEngine
{
    /**
    /**
     * Initialize Centralized ERP Storage & Seed MySQL Database via PDO
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Sync session cache from Database
        $_SESSION['beverage_products'] = self::getProducts();
        if (class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            $_SESSION['beverage_product_master_version'] = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProductMasterVersion();
        }
        $_SESSION['beverage_customers_ledger'] = self::getCustomers();
        $_SESSION['pos_sales_history'] = self::getSalesHistory();
        $_SESSION['payment_reconciliations'] = self::getPaymentReconciliations();
        $_SESSION['gl_journal_entries'] = self::getGlJournalEntries();
    }

    public static function getProducts(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureProductCommercialColumns($pdo);
                $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    $products = array_map(function($row) {
                        $row['id'] = (int)$row['id'];
                        $row['wholesale_price'] = (float)$row['wholesale_price'];
                        $row['selling_price'] = (float)$row['selling_price'];
                        $row['cost_price'] = (float)$row['cost_price'];
                        $row['crate_deposit'] = (float)$row['crate_deposit'];
                        $row['stock_crates'] = (int)$row['stock_crates'];
                        $row['stock_bottles'] = (int)$row['stock_bottles'];
                        $row['units_per_crate'] = (int)$row['units_per_crate'];
                        return $row;
                    }, $rows);
                    $applyMasterUpdate = self::shouldApplyProductMasterUpdate();
                    $products = self::mergeStarterProducts($products, $applyMasterUpdate);
                    if ($applyMasterUpdate) {
                        self::syncProductsToDatabase($pdo, $products);
                    } else {
                        self::backfillProductImages($pdo, $products);
                    }
                    return $products;
                }

                if (class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
                    $starterProducts = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getInitialProducts();
                    self::syncProductsToDatabase($pdo, $starterProducts);
                    $_SESSION['beverage_product_master_version'] = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProductMasterVersion();
                    return $starterProducts;
                }
            } catch (\Throwable $t) {
                if (\app_requires_database()) {
                    throw $t;
                }
            }
        }
        if (isset($_SESSION['beverage_products']) && is_array($_SESSION['beverage_products']) && $_SESSION['beverage_products'] !== []) {
            return self::mergeStarterProducts($_SESSION['beverage_products'], self::shouldApplyProductMasterUpdate());
        }

        if (class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            return \App\Modules\BeverageWarehouse\BeverageWarehouseService::getInitialProducts();
        }

        return [];
    }

    private static function shouldApplyProductMasterUpdate(): bool
    {
        if (!class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            return false;
        }

        return ($_SESSION['beverage_product_master_version'] ?? '') !== \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProductMasterVersion();
    }

    private static function mergeStarterProducts(array $products, bool $applyMasterUpdate = false): array
    {
        if (!class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            return $products;
        }

        $existingSkus = [];
        $existingNames = [];
        foreach ($products as $index => $product) {
            $sku = strtoupper(trim((string)($product['sku'] ?? '')));
            if ($sku !== '') {
                $existingSkus[$sku] = $index;
            }

            $nameKey = self::normalizeProductName((string)($product['name'] ?? ''));
            if ($nameKey !== '') {
                $existingNames[$nameKey] = $index;
            }
        }

        foreach (\App\Modules\BeverageWarehouse\BeverageWarehouseService::getInitialProducts() as $starterProduct) {
            $sku = strtoupper(trim((string)($starterProduct['sku'] ?? '')));
            $nameKey = self::normalizeProductName((string)($starterProduct['name'] ?? ''));

            if ($sku !== '' && isset($existingSkus[$sku])) {
                $index = $existingSkus[$sku];
                $products[$index] = self::mergeStarterProductMetadata($products[$index], $starterProduct, $applyMasterUpdate);
                continue;
            }

            if ($nameKey !== '' && isset($existingNames[$nameKey])) {
                $index = $existingNames[$nameKey];
                $products[$index] = self::mergeStarterProductMetadata($products[$index], $starterProduct, $applyMasterUpdate);
                $existingSkus[$sku] = $index;
                continue;
            }

            if ($sku !== '') {
                $products[] = $starterProduct;
                $existingSkus[$sku] = count($products) - 1;
            }
        }

        return array_values($products);
    }

    private static function mergeStarterProductMetadata(array $existing, array $starter, bool $applyMasterUpdate = false): array
    {
        $merged = array_merge($existing, $starter);

        foreach (['barcode', 'batch_number', 'expiry_date', 'image'] as $field) {
            if (!empty($existing[$field])) {
                $merged[$field] = $existing[$field];
            }
        }

        if (empty($merged['image']) && class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            $merged['image'] = \App\Modules\BeverageWarehouse\BeverageWarehouseService::inferDefaultProductImage(
                (string)($merged['name'] ?? ''),
                (string)($merged['packaging'] ?? '')
            );
        }

        foreach (['cost_price', 'selling_price', 'wholesale_price', 'retail_price', 'distributor_price', 'crate_deposit', 'stock_crates', 'stock_bottles', 'reorder_level', 'min_stock', 'max_stock'] as $field) {
            if ($applyMasterUpdate && in_array($field, ['cost_price', 'stock_crates', 'stock_bottles'], true)) {
                continue;
            }

            if (isset($existing[$field]) && (float)$existing[$field] > 0) {
                $merged[$field] = $existing[$field];
            }
        }

        return $merged;
    }

    private static function normalizeProductName(string $name): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($name)) ?: '';
    }

    private static function syncProductsToDatabase(\PDO $pdo, array $products): void
    {
        try {
            self::ensureProductCommercialColumns($pdo);
            $selectStmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? OR name = ? ORDER BY id ASC LIMIT 1");
            $updateStmt = $pdo->prepare("UPDATE products SET company_id = ?, sku = ?, barcode = ?, name = ?, brand = ?, category = ?, packaging = ?, packaging_type = ?, inventory_unit = ?, packs_per_pallet = ?, invoice_price = ?, rebate_base_price = ?, minimum_selling_price = ?, wholesale_price = ?, selling_price = ?, cost_price = ?, crate_deposit = ?, stock_crates = ?, stock_bottles = ?, units_per_crate = ?, min_stock = ?, batch_number = ?, expiry_date = ?, image = ? WHERE id = ?");
            $insertStmt = $pdo->prepare("INSERT INTO products (company_id, sku, barcode, name, brand, category, packaging, packaging_type, inventory_unit, packs_per_pallet, invoice_price, rebate_base_price, minimum_selling_price, wholesale_price, selling_price, cost_price, crate_deposit, stock_crates, stock_bottles, units_per_crate, min_stock, batch_number, expiry_date, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($products as $product) {
                $sku = (string)($product['sku'] ?? '');
                $name = (string)($product['name'] ?? '');
                if ($sku === '' || $name === '') {
                    continue;
                }

                $params = [
                    $product['company_id'] ?? 'beverage',
                    $sku,
                    $product['barcode'] ?? null,
                    $name,
                    $product['brand'] ?? '',
                    $product['category'] ?? '',
                    $product['packaging'] ?? '',
                    $product['packaging_type'] ?? null,
                    $product['inventory_unit'] ?? null,
                    (int)($product['packs_per_pallet'] ?? 0),
                    (float)($product['invoice_price'] ?? $product['cost_price'] ?? 0.0),
                    (float)($product['rebate_base_price'] ?? 0.0),
                    (float)($product['minimum_selling_price'] ?? 0.0),
                    (float)($product['wholesale_price'] ?? 0.0),
                    (float)($product['selling_price'] ?? 0.0),
                    (float)($product['cost_price'] ?? 0.0),
                    (float)($product['crate_deposit'] ?? 0.0),
                    (int)($product['stock_crates'] ?? 0),
                    (int)($product['stock_bottles'] ?? 0),
                    (int)($product['units_per_crate'] ?? 0),
                    (int)($product['min_stock'] ?? 0),
                    ($product['batch_number'] ?? '') !== '' ? $product['batch_number'] : null,
                    ($product['expiry_date'] ?? '') !== '' ? $product['expiry_date'] : null,
                    ($product['image'] ?? '') !== '' ? $product['image'] : null,
                ];

                $selectStmt->execute([$sku, $name]);
                $existingId = $selectStmt->fetchColumn();
                if ($existingId) {
                    $updateStmt->execute([...$params, (int)$existingId]);
                } else {
                    $insertStmt->execute($params);
                }
            }
        } catch (\Throwable $t) {
            if (\app_requires_database()) {
                throw $t;
            }
        }
    }

    private static function backfillProductImages(\PDO $pdo, array $products): int
    {
        if (!class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            return 0;
        }

        $updated = 0;
        try {
            self::ensureProductCommercialColumns($pdo);
            $selectStmt = $pdo->prepare("SELECT id, image FROM products WHERE sku = ? OR name = ? ORDER BY id ASC LIMIT 1");
            $updateStmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ? AND (image IS NULL OR image = '')");

            foreach ($products as $product) {
                $sku = strtoupper(trim((string)($product['sku'] ?? '')));
                $name = trim((string)($product['name'] ?? ''));
                if ($sku === '' || $name === '') {
                    continue;
                }

                $image = trim((string)($product['image'] ?? ''));
                if ($image === '') {
                    $image = \App\Modules\BeverageWarehouse\BeverageWarehouseService::inferDefaultProductImage(
                        $name,
                        (string)($product['packaging'] ?? '')
                    );
                }

                if ($image === '') {
                    continue;
                }

                $selectStmt->execute([$sku, $name]);
                $row = $selectStmt->fetch();
                if (!$row || trim((string)($row['image'] ?? '')) !== '') {
                    continue;
                }

                $updateStmt->execute([$image, (int)$row['id']]);
                $updated += $updateStmt->rowCount();
            }
        } catch (\Throwable $t) {
            if (\app_requires_database()) {
                throw $t;
            }
        }

        return $updated;
    }

    public static function saveProduct(array $product): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureProductCommercialColumns($pdo);
                $stmt = $pdo->prepare("INSERT INTO products (id, company_id, sku, barcode, name, brand, category, packaging, packaging_type, inventory_unit, packs_per_pallet, invoice_price, rebate_base_price, minimum_selling_price, wholesale_price, selling_price, cost_price, crate_deposit, stock_crates, stock_bottles, units_per_crate, min_stock, batch_number, expiry_date, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), brand=VALUES(brand), category=VALUES(category), packaging=VALUES(packaging), packaging_type=VALUES(packaging_type), inventory_unit=VALUES(inventory_unit), packs_per_pallet=VALUES(packs_per_pallet), invoice_price=VALUES(invoice_price), rebate_base_price=VALUES(rebate_base_price), minimum_selling_price=VALUES(minimum_selling_price), wholesale_price=VALUES(wholesale_price), selling_price=VALUES(selling_price), cost_price=VALUES(cost_price), crate_deposit=VALUES(crate_deposit), stock_crates=VALUES(stock_crates), stock_bottles=VALUES(stock_bottles), units_per_crate=VALUES(units_per_crate), min_stock=VALUES(min_stock), batch_number=VALUES(batch_number), expiry_date=VALUES(expiry_date), image=VALUES(image)");
                $unitsPerCrate = (int)($product['units_per_crate'] ?? 24);
                $stockCrates = (int)($product['stock_crates'] ?? 0);
                $stockBottles = $stockCrates * $unitsPerCrate;
                $expiryDate = trim((string)($product['expiry_date'] ?? ''));
                $stmt->execute([
                    $product['id'] ?? null,
                    $product['company_id'] ?? 'beverage',
                    $product['sku'] ?? '',
                    $product['barcode'] ?? null,
                    $product['name'] ?? '',
                    $product['brand'] ?? '',
                    $product['category'] ?? '',
                    $product['packaging'] ?? '',
                    $product['packaging_type'] ?? null,
                    $product['inventory_unit'] ?? null,
                    (int)($product['packs_per_pallet'] ?? 0),
                    (float)($product['invoice_price'] ?? $product['cost_price'] ?? 0.0),
                    (float)($product['rebate_base_price'] ?? 0.0),
                    (float)($product['minimum_selling_price'] ?? 0.0),
                    (float)($product['wholesale_price'] ?? $product['selling_price'] ?? 0.0),
                    (float)($product['selling_price'] ?? $product['wholesale_price'] ?? 0.0),
                    (float)($product['cost_price'] ?? 0.0),
                    (float)($product['crate_deposit'] ?? 0.0),
                    $stockCrates,
                    $stockBottles,
                    $unitsPerCrate,
                    (int)($product['min_stock'] ?? 0),
                    $product['batch_number'] ?? null,
                    $expiryDate !== '' ? $expiryDate : null,
                    $product['image'] ?? '',
                ]);
            } catch (\Throwable $t) {
                if (\app_requires_database()) {
                    throw $t;
                }
            }
        }
        $_SESSION['beverage_products'] = self::getProducts();
        return $product;
    }

    private static function ensureProductCommercialColumns(\PDO $pdo): void
    {
        foreach ([
            'packaging_type VARCHAR(40) DEFAULT NULL',
            'inventory_unit VARCHAR(40) DEFAULT NULL',
            'packs_per_pallet INT NOT NULL DEFAULT 0',
            'invoice_price DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'rebate_base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'minimum_selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00',
        ] as $columnSql) {
            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN {$columnSql}");
            } catch (\Throwable $t) {
            }
        }
    }

    private static function ensureCustomerCommercialColumns(\PDO $pdo): void
    {
        foreach ([
            'phone VARCHAR(40) DEFAULT NULL',
            'customer_class VARCHAR(80) NOT NULL DEFAULT "Retail"',
            'distributor_code VARCHAR(80) DEFAULT NULL',
        ] as $columnSql) {
            try {
                $pdo->exec("ALTER TABLE customers ADD COLUMN {$columnSql}");
            } catch (\Throwable $t) {
            }
        }
    }

    private static function ensureSalesCommercialColumns(\PDO $pdo): void
    {
        foreach ([
            'customer_class VARCHAR(80) NOT NULL DEFAULT "Retail"',
            'distributor_code VARCHAR(80) DEFAULT NULL',
            'sales_channel VARCHAR(30) NOT NULL DEFAULT "depot_sale"',
            'employee_code VARCHAR(50) DEFAULT NULL',
            'employee_role VARCHAR(80) DEFAULT NULL',
            'pos_terminal VARCHAR(120) DEFAULT NULL',
            'pos_login_time DATETIME DEFAULT NULL',
        ] as $columnSql) {
            try {
                $pdo->exec("ALTER TABLE sales ADD COLUMN {$columnSql}");
            } catch (\Throwable $t) {
            }
        }
    }

    public static function getCustomers(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureCustomerCommercialColumns($pdo);
                $stmt = $pdo->query("SELECT * FROM customers ORDER BY id ASC");
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    $ledger = [];
                    foreach ($rows as $row) {
                        $id = (int)$row['id'];
                        $row['id'] = $id;
                        $row['wallet_balance'] = (float)$row['wallet_balance'];
                        $row['credit_limit'] = (float)$row['credit_limit'];
                        $row['current_debt'] = (float)$row['current_debt'];
                        $row['crates_held'] = (int)$row['crates_held'];
                        $ledger[$id] = $row;
                    }
                    return $ledger;
                }
            } catch (\Throwable $t) {}
        }
        return $_SESSION['beverage_customers_ledger'] ?? [];
    }

    public static function getSalesHistory(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureSalesCommercialColumns($pdo);
                $stmt = $pdo->query("SELECT * FROM sales ORDER BY id DESC");
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    return array_map(function($row) {
                        $row['id'] = (int)$row['id'];
                        $row['customer_id'] = (int)$row['customer_id'];
                        $row['subtotal'] = (float)$row['subtotal'];
                        $row['crate_deposit_total'] = (float)$row['crate_deposit_total'];
                        $row['discount'] = (float)$row['discount'];
                        $row['grand_total'] = (float)$row['grand_total'];
                        $row['amount_paid'] = (float)$row['amount_paid'];
                        $row['change_due'] = (float)$row['change_due'];
                        $row['items'] = !empty($row['items_json']) ? json_decode($row['items_json'], true) : [];
                        return $row;
                    }, $rows);
                }
            } catch (\Throwable $t) {}
        }
        return $_SESSION['pos_sales_history'] ?? [];
    }

    public static function getPaymentReconciliations(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM payment_reconciliations ORDER BY id DESC");
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    return array_map(function($row) {
                        $row['id'] = (int)$row['id'];
                        $row['amount'] = (float)$row['amount'];
                        $row['ref'] = $row['ref_no'];
                        $row['customer'] = $row['customer_name'];
                        $row['timestamp'] = $row['created_at'];
                        return $row;
                    }, $rows);
                }
            } catch (\Throwable $t) {}
        }
        return $_SESSION['payment_reconciliations'] ?? [];
    }

    public static function getGlJournalEntries(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM gl_journal_entries ORDER BY id DESC");
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    return array_map(function($row) {
                        $row['id'] = (int)$row['id'];
                        $row['amount'] = (float)$row['amount'];
                        $row['date'] = $row['entry_date'];
                        return $row;
                    }, $rows);
                }
            } catch (\Throwable $t) {}
        }
        return $_SESSION['gl_journal_entries'] ?? [];
    }

    /**
     * Record a POS Sale in Real-Time across all ERP modules
     */
    public static function recordSale(array $saleData): array
    {
        self::initSession();

        $items = $saleData['items'] ?? [];
        $company = 'beverage';
        $customerId = (int)($saleData['customer_id'] ?? 1);
        $paymentMethod = $saleData['payment_method'] ?? 'Cash';
        $salesChannel = (string)($saleData['sales_channel'] ?? 'depot_sale');
        $grandTotal = (float)($saleData['grand_total'] ?? 0.0);
        $amountPaid = (float)($saleData['amount_paid'] ?? $grandTotal);
        $receiptNo = (string)($saleData['receipt_no'] ?? ('POS-BEV-' . date('Ymd-His') . '-' . rand(10, 99)));
        $subtotal = (float)($saleData['subtotal'] ?? $grandTotal);
        $crateDeposit = (float)($saleData['crate_deposit'] ?? $saleData['crate_deposit_total'] ?? 0.0);
        $cashier = (string)($saleData['cashier_name'] ?? (\current_user()['name'] ?? 'POS Cashier'));
        $createdAt = (string)($saleData['created_at'] ?? date('Y-m-d H:i:s'));
        $timestamp = (string)($saleData['timestamp'] ?? date('d M Y, h:i A'));

        // 0. Database Transactional Persistence via PDO
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $pdo->beginTransaction();
                self::ensureSalesCommercialColumns($pdo);

                // Insert Sales Record
                $stmt = $pdo->prepare("INSERT INTO sales (receipt_no, company_id, customer_id, customer_name, payment_method, subtotal, crate_deposit_total, discount, grand_total, amount_paid, change_due, cashier_name, items_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE grand_total = VALUES(grand_total)");
                $stmt->execute([
                    $receiptNo,
                    $company,
                    $customerId,
                    $saleData['customer_name'] ?? 'Customer',
                    $paymentMethod,
                    $subtotal,
                    $crateDeposit,
                    (float)($saleData['discount'] ?? 0.0),
                    $grandTotal,
                    $amountPaid,
                    max(0.0, $amountPaid - $grandTotal),
                    $cashier,
                    json_encode($items),
                    $createdAt
                ]);

                $metaStmt = $pdo->prepare('UPDATE sales SET customer_class = ?, distributor_code = ?, sales_channel = ?, employee_code = ?, employee_role = ?, pos_terminal = ?, pos_login_time = ? WHERE receipt_no = ? AND company_id = ?');
                $metaStmt->execute([
                    (string)($saleData['customer_class'] ?? 'Retail'),
                    (string)($saleData['distributor_code'] ?? ''),
                    $salesChannel,
                    $saleData['employee_code'] ?? null,
                    $saleData['employee_role'] ?? null,
                    $saleData['pos_terminal'] ?? null,
                    $saleData['pos_login_time'] ?? null,
                    $receiptNo,
                    $company,
                ]);

                // Deduct Product Stock
                $pStmt = $pdo->prepare("UPDATE products SET stock_crates = GREATEST(0, stock_crates - ?), stock_bottles = GREATEST(0, stock_crates * units_per_crate - (? * units_per_crate)) WHERE sku = ? OR id = ? OR name = ?");
                foreach ($items as $item) {
                    $qty = (int)($item['qty'] ?? 1);
                    $sku = (string)($item['sku'] ?? '');
                    $itemId = (int)($item['id'] ?? 0);
                    $itemName = (string)($item['name'] ?? '');
                    $pStmt->execute([$qty, $qty, $sku, $itemId, $itemName]);
                }

                // Update Customer Debt / Wallet / Crates
                if ($customerId > 0) {
                    if ($paymentMethod === 'Customer Wallet') {
                        $cStmt = $pdo->prepare("UPDATE customers SET wallet_balance = GREATEST(0.00, wallet_balance - ?) WHERE id = ?");
                        $cStmt->execute([$grandTotal, $customerId]);
                    } elseif (str_contains(strtolower((string)$paymentMethod), 'debt') || str_contains(strtolower((string)$paymentMethod), 'credit')) {
                        $cStmt = $pdo->prepare("UPDATE customers SET current_debt = current_debt + ? WHERE id = ?");
                        $cStmt->execute([$grandTotal, $customerId]);
                    }
                    if ($crateDeposit > 0) {
                        $cStmt = $pdo->prepare("UPDATE customers SET crates_held = crates_held + ? WHERE id = ?");
                        $cStmt->execute([self::estimateCratesFromItems($items), $customerId]);
                    }
                }

                // Insert Payment Reconciliation
                $reconCode = 'REC-POS-' . rand(100, 999);
                $reconStatus = str_contains(strtolower((string)$paymentMethod), 'transfer') ? 'Pending POS Confirmation' : 'Reconciled - POS Collection';
                $rStmt = $pdo->prepare("INSERT INTO payment_reconciliations (recon_code, type, ref_no, customer_name, channel, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status)");
                $rStmt->execute([$reconCode, 'POS Sale', $receiptNo, $saleData['customer_name'] ?? 'Customer', $paymentMethod, $grandTotal, $reconStatus, $createdAt]);

                // Insert GL Entry
                $jCode = 'JE-2026-' . rand(100, 999);
                $gStmt = $pdo->prepare("INSERT INTO gl_journal_entries (journal_code, entry_date, description, debit_account, credit_account, amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)");
                $gStmt->execute([$jCode, date('Y-m-d'), "POS Sales Revenue - {$receiptNo}", self::cashAccountForPayment((string)$paymentMethod), '4010 Beverage Sales Revenue', max(0.0, $grandTotal - $crateDeposit), $createdAt]);

                $pdo->commit();
            } catch (\Throwable $t) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }

        // 1. Deduct Inventory in Real-Time from Central Warehouse
        if ($company === 'beverage' && isset($_SESSION['beverage_products'])) {
            foreach ($items as $item) {
                $sku = $item['sku'] ?? '';
                $itemId = (int)($item['id'] ?? 0);
                $qty = (int)($item['qty'] ?? 1);
                foreach ($_SESSION['beverage_products'] as &$prod) {
                    if (
                        ($itemId > 0 && (int)($prod['id'] ?? 0) === $itemId)
                        || ($sku !== '' && strcasecmp($prod['sku'] ?? '', $sku) === 0)
                        || strcasecmp($prod['name'] ?? '', $item['name'] ?? '') === 0
                    ) {
                        $previousCrates = (int)($prod['stock_crates'] ?? 0);
                        $prod['stock_crates'] = max(0, $previousCrates - $qty);
                        $prod['stock_bottles'] = $prod['stock_crates'] * ($prod['units_per_crate'] ?? 24);
                        self::syncInventoryAudit([
                            'reference' => $receiptNo . ':' . ($prod['sku'] ?? $prod['name']),
                            'user' => $cashier,
                            'action' => 'POS Sale Stock Deduction',
                            'item' => $prod['name'] ?? ($item['name'] ?? 'POS Item'),
                            'quantity' => "-{$qty} Crates/Packs",
                            'previous_balance' => "{$previousCrates} Crates/Packs",
                            'new_balance' => "{$prod['stock_crates']} Crates/Packs",
                            'location' => 'Beverage POS Terminal',
                            'timestamp' => $timestamp,
                        ]);
                        break;
                    }
                }
                unset($prod);
            }
        }

        // 2. Update Customer Debt & Wallet in Real-Time
        if (isset($_SESSION['beverage_customers_ledger'][$customerId])) {
            $cust = &$_SESSION['beverage_customers_ledger'][$customerId];
            if ($paymentMethod === 'Customer Wallet') {
                $cust['wallet_balance'] = max(0.0, $cust['wallet_balance'] - $grandTotal);
            } elseif (str_contains(strtolower((string)$paymentMethod), 'debt') || str_contains(strtolower((string)$paymentMethod), 'credit')) {
                $cust['current_debt'] += $grandTotal;
            }
            if ($crateDeposit > 0) {
                $cust['crates_held'] = (int)($cust['crates_held'] ?? 0) + self::estimateCratesFromItems($items);
            }
            unset($cust);
        }

        // 3. Post to Sales History
        $saleRecord = [
            'receipt_no' => $receiptNo,
            'pos_mode' => $company,
            'mode_title' => $saleData['mode_title'] ?? 'EMPRESS TEE BEVERAGE DEPOT POS',
            'customer_id' => $customerId,
            'customer_name' => $saleData['customer_name'] ?? 'Customer',
            'customer_phone' => $saleData['customer_phone'] ?? '',
            'customer_class' => $saleData['customer_class'] ?? 'Retail',
            'distributor_code' => $saleData['distributor_code'] ?? '',
            'payment_method' => $paymentMethod,
            'sales_channel' => $salesChannel,
            'employee_code' => $saleData['employee_code'] ?? null,
            'employee_role' => $saleData['employee_role'] ?? null,
            'pos_terminal' => $saleData['pos_terminal'] ?? null,
            'pos_login_time' => $saleData['pos_login_time'] ?? null,
            'subtotal' => $subtotal,
            'discount' => (float)($saleData['discount'] ?? 0.0),
            'crate_deposit' => $crateDeposit,
            'crate_deposit_total' => $crateDeposit,
            'grand_total' => $grandTotal,
            'amount_paid' => $amountPaid,
            'change_due' => max(0.0, $amountPaid - $grandTotal),
            'cashier_name' => $cashier,
            'branch' => $saleData['branch'] ?? 'Jacroxx Warehouse (Main)',
            'total_cost_price' => (float)($saleData['total_cost_price'] ?? 0.0),
            'total_rebate_amount' => (float)($saleData['total_rebate_amount'] ?? 0.0),
            'total_effective_cost' => (float)($saleData['total_effective_cost'] ?? 0.0),
            'total_profit_before_rebate' => (float)($saleData['total_profit_before_rebate'] ?? 0.0),
            'total_true_profit_after_rebate' => (float)($saleData['total_true_profit_after_rebate'] ?? 0.0),
            'created_at' => $createdAt,
            'timestamp' => $timestamp,
            'qr_code_data' => $saleData['qr_code_data'] ?? "REC:{$receiptNo}|TOT:{$grandTotal}|PAY:{$paymentMethod}",
            'customer_saved' => (bool)($saleData['customer_saved'] ?? false),
            'items' => $items,
        ];
        foreach (['transfer_status', 'transfer_confirmed_by', 'transfer_confirmed_at', 'transfer_approved_by', 'transfer_approved_at'] as $field) {
            if (array_key_exists($field, $saleData)) {
                $saleRecord[$field] = $saleData[$field];
            }
        }
        self::upsertSessionRecord('pos_sales_history', 'receipt_no', $receiptNo, $saleRecord);

        // 4. Post to Payment Reconciliation in Real-Time
        $reconStatus = str_contains(strtolower((string)$paymentMethod), 'transfer')
            ? ($saleRecord['transfer_status'] ?? 'Pending POS Confirmation')
            : 'Reconciled - POS Collection';
        self::upsertSessionRecord('payment_reconciliations', 'ref', $receiptNo, [
            'id' => 'REC-POS-' . rand(100, 999),
            'type' => 'POS Sale',
            'ref' => $receiptNo,
            'customer' => $saleRecord['customer_name'],
            'channel' => $paymentMethod,
            'amount' => $grandTotal,
            'status' => $reconStatus,
            'timestamp' => $createdAt,
        ]);

        // 5. Post Double-Entry Journal Entries in Real-Time
        self::upsertSessionRecord('gl_journal_entries', 'reference', $receiptNo . ':sales', [
            'id' => 'JE-2026-' . rand(100, 999),
            'reference' => $receiptNo . ':sales',
            'date' => date('Y-m-d'),
            'description' => "POS Sales Revenue - {$receiptNo}",
            'debit_account' => self::cashAccountForPayment((string)$paymentMethod),
            'credit_account' => '4010 Beverage Sales Revenue',
            'amount' => max(0.0, $grandTotal - $crateDeposit),
        ]);

        if ($crateDeposit > 0) {
            self::upsertSessionRecord('gl_journal_entries', 'reference', $receiptNo . ':crate_deposit', [
                'id' => 'JE-2026-' . rand(100, 999),
                'reference' => $receiptNo . ':crate_deposit',
                'date' => date('Y-m-d'),
                'description' => "Customer Crate Deposit Liability - {$receiptNo}",
                'debit_account' => self::cashAccountForPayment((string)$paymentMethod),
                'credit_account' => '2050 Customer Crate Deposit Liability',
                'amount' => $crateDeposit,
            ]);
        }

        self::syncFinanceModules($company, $saleRecord, $receiptNo, $paymentMethod, $grandTotal, $subtotal, $crateDeposit, $cashier);
        self::syncPaymentAlertFeed($saleRecord);
        self::recordSyncEvent($company, 'pos_sale', $receiptNo, ['POS sales history', 'Warehouse inventory', 'Inventory audit', 'Payment reconciliation', 'Finance GL', 'Customer ledger']);

        return $saleRecord;
    }

    public static function syncProductMaster(array $product, string $event = 'updated', string $company = 'beverage'): array
    {
        self::initSession();

        $company = 'beverage';
        $sku = (string)($product['sku'] ?? 'PROD-' . rand(1000, 9999));
        $productName = (string)($product['name'] ?? 'Product');
        $stockQty = (int)($product['stock_crates'] ?? $product['stock_qty'] ?? 0);
        $unitsPerCrate = (int)($product['units_per_crate'] ?? 24);
        $wholesalePrice = (float)($product['wholesale_price'] ?? $product['selling_price'] ?? $product['price_per_unit'] ?? 0.0);
        $costPrice = (float)($product['cost_price'] ?? 0.0);
        $reference = "PROD-{$sku}:{$event}";

        $posProduct = [
            'id' => $product['id'] ?? time(),
            'sku' => $sku,
            'name' => $productName,
            'category' => (string)($product['category'] ?? 'General'),
            'packaging' => (string)($product['packaging'] ?? $product['config'] ?? 'Unit'),
            'price_per_unit' => $wholesalePrice,
            'price_type' => (string)($product['price_type'] ?? 'Unit/Pack'),
            'crate_deposit' => (float)($product['crate_deposit'] ?? 0.0),
            'stock_qty' => $stockQty,
            'icon' => (string)($product['icon'] ?? ''),
            'image' => (string)($product['image'] ?? ''),
        ];

        self::upsertSessionRecord("{$company}_registered_products", 'sku', $sku, $posProduct);
        self::upsertSessionRecord("{$company}_product_master", 'sku', $sku, array_merge($product, [
            'synced_at' => date('Y-m-d H:i:s'),
            'sync_event' => $event,
        ]));
        self::upsertSessionRecord("{$company}_price_book", 'sku', $sku, [
            'sku' => $sku,
            'product_name' => $productName,
            'wholesale_price' => $wholesalePrice,
            'retail_price' => (float)($product['retail_price'] ?? $product['selling_price'] ?? $wholesalePrice),
            'distributor_price' => (float)($product['distributor_price'] ?? $wholesalePrice),
            'crate_deposit' => (float)($product['crate_deposit'] ?? 0.0),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::upsertSessionRecord("inventory_valuation_{$company}", 'sku', $sku, [
            'sku' => $sku,
            'product_name' => $productName,
            'stock_qty' => $stockQty,
            'units_per_crate' => $unitsPerCrate,
            'cost_price' => $costPrice,
            'wholesale_price' => $wholesalePrice,
            'stock_value_cost' => $stockQty * $costPrice,
            'stock_value_sales' => $stockQty * $wholesalePrice,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::upsertSessionRecord("procurement_supplier_catalog_{$company}", 'sku', $sku, [
            'sku' => $sku,
            'product_name' => $productName,
            'supplier' => (string)($product['supplier'] ?? 'Supplier Pending'),
            'reorder_level' => (int)($product['reorder_level'] ?? $product['min_stock'] ?? 0),
            'preferred_cost' => $costPrice,
            'last_sync' => date('Y-m-d H:i:s'),
        ]);
        self::upsertSessionRecord("fefo_register_{$company}", 'sku', $sku, [
            'sku' => $sku,
            'product_name' => $productName,
            'batch_number' => (string)($product['batch_number'] ?? 'Batch Pending'),
            'expiry_date' => (string)($product['expiry_date'] ?? ''),
            'stock_qty' => $stockQty,
            'status' => self::expiryStatus((string)($product['expiry_date'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        self::syncInventoryAudit([
            'reference' => $reference,
            'user' => \current_user()['name'] ?? 'Warehouse Admin',
            'action' => $event === 'created' ? 'Product Master Created & Synced' : 'Product Master Updated & Synced',
            'item' => $productName,
            'quantity' => "{$stockQty} Crates/Packs",
            'previous_balance' => $event === 'created' ? 'New SKU' : 'Existing SKU',
            'new_balance' => 'Synced to POS, inventory, procurement, FEFO and finance',
            'location' => 'Central Product Master',
            'timestamp' => date('d M Y, h:i A'),
        ]);

        if ($event === 'created' && $stockQty > 0 && $costPrice > 0) {
            self::syncOpeningInventoryJournal($company, $sku, $productName, $stockQty * $costPrice);
        }

        $modules = ['Warehouse product master', 'POS catalog', 'Inventory valuation', 'Procurement supplier catalog', 'FEFO register', 'Inventory audit'];
        if ($event === 'created' && $stockQty > 0 && $costPrice > 0) {
            $modules[] = 'Finance GL opening stock';
        }
        self::recordSyncEvent($company, 'product_master_' . $event, $sku, $modules);

        return [
            'sku' => $sku,
            'product_name' => $productName,
            'event' => $event,
            'modules' => $modules,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Receive GRN Stock Inbound in Real-Time
     */
    public static function receiveGRN(array $grnData): array
    {
        self::initSession();

        $sku = $grnData['sku'] ?? '';
        $cratesReceived = (int)($grnData['crates_received'] ?? 0);
        $costPrice = (float)($grnData['cost_price'] ?? 0.0);
        $supplier = $grnData['supplier'] ?? 'Supplier';

        if (isset($_SESSION['beverage_products'])) {
            foreach ($_SESSION['beverage_products'] as &$prod) {
                if (strcasecmp($prod['sku'] ?? '', $sku) === 0 || strcasecmp($prod['name'] ?? '', $grnData['name'] ?? '') === 0) {
                    $prod['stock_crates'] += $cratesReceived;
                    $prod['stock_bottles'] = $prod['stock_crates'] * ($prod['units_per_crate'] ?? 24);
                    if ($costPrice > 0) {
                        $prod['cost_price'] = $costPrice;
                    }
                    break;
                }
            }
        }

        // Post AP Payable to GL
        $totalCost = $cratesReceived * $costPrice;
        $_SESSION['gl_journal_entries'][] = [
            'id' => 'JE-GRN-' . rand(100, 999),
            'date' => date('Y-m-d'),
            'description' => "Supplier Inbound GRN Stock Receipt - {$supplier}",
            'debit_account' => '1300 Inventory Asset',
            'credit_account' => '2010 Accounts Payable (Suppliers)',
            'amount' => $totalCost,
        ];

        return [
            'grn_no' => 'GRN-2026-' . rand(1000, 9999),
            'supplier' => $supplier,
            'crates' => $cratesReceived,
            'total_cost' => $totalCost,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Sync Logistics delivery notes/POD confirmations into related ERP modules.
     */
    public static function syncLogisticsDelivery(array $delivery, string $event = 'capture'): array
    {
        self::initSession();

        $company = 'beverage';
        $reference = (string)($delivery['waybill_number'] ?? $delivery['delivery_number'] ?? 'LOG-' . rand(1000, 9999));
        $supplier = (string)($delivery['supplier'] ?? 'Supplier');
        $documentValue = (float)($delivery['document_value'] ?? 0.0);
        $items = $delivery['items'] ?? [];
        $totalQty = 0;

        foreach ($items as $item) {
            $totalQty += (int)($item['quantity'] ?? 0);
        }

        $syncEvent = [
            'id' => 'SYNC-LOG-' . rand(1000, 9999),
            'company_id' => $company,
            'event' => $event,
            'reference' => $reference . ':' . $event,
            'supplier' => $supplier,
            'delivery_number' => $delivery['delivery_number'] ?? $reference,
            'invoice_number' => $delivery['invoice_number'] ?? '',
            'document_value' => $documentValue,
            'modules' => [],
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if (!isset($_SESSION['logistics_sync_events']) || !is_array($_SESSION['logistics_sync_events'])) {
            $_SESSION['logistics_sync_events'] = [];
        }

        if (self::sessionRecordExists($_SESSION['logistics_sync_events'], 'reference', $syncEvent['reference'])) {
            return $syncEvent;
        }

        if ($event === 'capture') {
            self::syncProcurementOrder($delivery, $reference, $supplier, $documentValue);
            self::syncPaymentReconDocument($delivery, $reference, $supplier, $documentValue);
            $syncEvent['modules'][] = 'Procurement PO/GRN queue';
            $syncEvent['modules'][] = 'Payment/document reconciliation queue';
        }

        if ($event === 'pod_confirmed') {
            self::syncWarehouseReceiving($delivery, $reference, $supplier, $totalQty);
            self::syncSupplierPayable($company, $delivery, $reference, $supplier, $documentValue);
            $syncEvent['modules'][] = 'Warehouse inventory audit';
            $syncEvent['modules'][] = 'Finance AP/GL';
        }

        array_unshift($_SESSION['logistics_sync_events'], $syncEvent);

        return $syncEvent;
    }

    private static function syncProcurementOrder(array $delivery, string $reference, string $supplier, float $documentValue): void
    {
        if (!isset($_SESSION['procurement_orders']) || !is_array($_SESSION['procurement_orders'])) {
            $_SESSION['procurement_orders'] = [];
        }

        if (self::sessionRecordExists($_SESSION['procurement_orders'], 'po_number', $reference)) {
            return;
        }

        array_unshift($_SESSION['procurement_orders'], [
            'po_number' => $reference,
            'vendor_name' => $supplier,
            'order_date' => date('Y-m-d'),
            'items' => $delivery['items_summary'] ?? 'Delivery note items',
            'total_amount' => $documentValue,
            'matching_status' => empty($delivery['invoice_number']) ? 'Pending Supplier Invoice' : 'Delivery Note linked to Supplier Invoice',
            'status' => empty($delivery['pod_acknowledged']) ? 'Delivery Captured - Awaiting POD' : 'Received & POD Confirmed',
        ]);
    }

    private static function syncPaymentReconDocument(array $delivery, string $reference, string $supplier, float $documentValue): void
    {
        if (!isset($_SESSION['payment_reconciliations']) || !is_array($_SESSION['payment_reconciliations'])) {
            $_SESSION['payment_reconciliations'] = [];
        }

        if (self::sessionRecordExists($_SESSION['payment_reconciliations'], 'ref', $reference)) {
            return;
        }

        $_SESSION['payment_reconciliations'][] = [
            'id' => 'REC-LOG-' . rand(100, 999),
            'type' => 'Supplier Delivery Note',
            'ref' => $reference,
            'customer' => $supplier,
            'channel' => empty($delivery['invoice_number']) ? 'Invoice Pending' : 'Supplier Invoice ' . $delivery['invoice_number'],
            'amount' => $documentValue,
            'status' => $documentValue > 0 ? 'Needs Finance Review' : 'Document Captured',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    private static function syncWarehouseReceiving(array $delivery, string $reference, string $supplier, int $totalQty): void
    {
        if (class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            \App\Modules\BeverageWarehouse\BeverageWarehouseService::logMovement([
                'reference' => $reference,
                'user' => \current_user()['name'] ?? 'Receiving Supervisor',
                'action' => 'Logistics POD Synced to GRN',
                'item' => $delivery['items_summary'] ?? 'Delivery note items',
                'quantity' => number_format($totalQty) . ' Units',
                'previous_balance' => 'Pending receiving',
                'new_balance' => 'POD confirmed for GRN processing',
                'location' => $delivery['ship_to'] ?? $delivery['destination'] ?? 'Main Warehouse',
                'timestamp' => date('d M Y, h:i A'),
            ]);
            return;
        }

        if (!isset($_SESSION['inventory_audit_trail']) || !is_array($_SESSION['inventory_audit_trail'])) {
            $_SESSION['inventory_audit_trail'] = [];
        }

        if (self::sessionRecordExists($_SESSION['inventory_audit_trail'], 'reference', $reference)) {
            return;
        }

        array_unshift($_SESSION['inventory_audit_trail'], [
            'reference' => $reference,
            'user' => \current_user()['name'] ?? 'Receiving Supervisor',
            'action' => 'Logistics POD Synced to GRN',
            'item' => $delivery['items_summary'] ?? 'Delivery note items',
            'quantity' => number_format($totalQty) . ' Units',
            'previous_balance' => 'Pending receiving',
            'new_balance' => 'POD confirmed for GRN processing',
            'location' => $delivery['ship_to'] ?? $delivery['destination'] ?? 'Main Warehouse',
            'timestamp' => date('d M Y, h:i A'),
        ]);
    }

    private static function syncSupplierPayable(string $company, array $delivery, string $reference, string $supplier, float $documentValue): void
    {
        if ($documentValue <= 0) {
            return;
        }

        $billNumber = $delivery['invoice_number'] ?: 'BILL-' . $reference;
        $sessionKey = "ap_creditors_{$company}";
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }

        if (!self::sessionRecordExists($_SESSION[$sessionKey], 'bill_number', $billNumber)) {
            array_unshift($_SESSION[$sessionKey], [
                'id' => time() + rand(10, 999),
                'supplier_name' => $supplier,
                'bill_number' => $billNumber,
                'bill_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'total_amount' => $documentValue,
                'paid_amount' => 0.00,
                'balance_due' => $documentValue,
                'terms' => $delivery['terms_of_delivery'] ?? 'Net 30 Days',
                'status' => 'Unpaid',
            ]);
        }

        if (class_exists('App\Modules\Finance\DoubleEntryJournalService')) {
            \App\Modules\Finance\DoubleEntryJournalService::createJournalEntry($company, [
                'event_type' => 'logistics_pod_grn',
                'reference' => $reference,
                'narration' => "POD confirmed and supplier payable recognized for {$supplier}",
                'posted_by' => \current_user()['name'] ?? 'Logistics Supervisor',
                'lines' => [
                    ['code' => '1210', 'name' => 'Beverage Finished Goods Inventory', 'debit' => $documentValue, 'credit' => 0.00],
                    ['code' => '2110', 'name' => 'Accounts Payable (Trade Creditors)', 'debit' => 0.00, 'credit' => $documentValue],
                ],
            ]);
        }
    }

    public static function syncTransferApproval(array $transaction): void
    {
        self::initSession();

        $receiptNo = (string)($transaction['receipt_no'] ?? '');
        if ($receiptNo === '') {
            return;
        }

        $status = (string)($transaction['transfer_status'] ?? 'Pending POS Confirmation');
        if (isset($_SESSION['payment_reconciliations']) && is_array($_SESSION['payment_reconciliations'])) {
            foreach ($_SESSION['payment_reconciliations'] as &$recon) {
                if (($recon['ref'] ?? '') === $receiptNo) {
                    $recon['status'] = $status === 'Approved & Posted' ? 'Transfer Approved & Posted' : $status;
                    $recon['timestamp'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($recon);
        }

        self::syncPaymentAlertFeed($transaction);
        self::recordSyncEvent((string)($transaction['pos_mode'] ?? 'beverage'), 'transfer_payment_' . strtolower(str_replace([' ', '&'], ['_', 'and'], $status)), $receiptNo, ['POS transfer screen', 'Payment reconciliation', 'Bank alert audit']);
    }

    private static function upsertSessionRecord(string $sessionKey, string $matchKey, string $matchValue, array $record): void
    {
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }

        foreach ($_SESSION[$sessionKey] as $idx => $existing) {
            if ((string)($existing[$matchKey] ?? '') === $matchValue) {
                $_SESSION[$sessionKey][$idx] = array_merge($existing, $record);
                return;
            }
        }

        array_unshift($_SESSION[$sessionKey], $record);
    }

    private static function syncInventoryAudit(array $entry): void
    {
        if (class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            \App\Modules\BeverageWarehouse\BeverageWarehouseService::logMovement($entry);
            return;
        }

        if (!isset($_SESSION['inventory_audit_trail']) || !is_array($_SESSION['inventory_audit_trail'])) {
            $_SESSION['inventory_audit_trail'] = [];
        }

        $reference = (string)($entry['reference'] ?? '');
        if ($reference !== '' && self::sessionRecordExists($_SESSION['inventory_audit_trail'], 'reference', $reference)) {
            return;
        }

        array_unshift($_SESSION['inventory_audit_trail'], $entry);
    }

    private static function syncPaymentAlertFeed(array $saleRecord): void
    {
        $method = strtolower((string)($saleRecord['payment_method'] ?? ''));
        if (!str_contains($method, 'transfer')) {
            return;
        }

        $receiptNo = (string)($saleRecord['receipt_no'] ?? '');
        $status = (string)($saleRecord['transfer_status'] ?? 'Pending POS Confirmation');
        $alertStatus = $status === 'Approved & Posted' ? 'Matched & Auto-Posted' : $status;

        $paymentAlert = [
            'id' => time() + rand(1, 999),
            'source' => 'POS Transfer Confirmation',
            'bank_name' => 'Configured Bank Alert Mailbox',
            'sender_name' => (string)($saleRecord['customer_name'] ?? 'POS Customer'),
            'transaction_ref' => $receiptNo,
            'amount' => (float)($saleRecord['grand_total'] ?? 0),
            'alert_date' => (string)($saleRecord['timestamp'] ?? date('d M Y, h:i A')),
            'matched_to' => 'POS Receipt ' . $receiptNo,
            'status' => $alertStatus,
            'confidence_score' => $status === 'Approved & Posted' ? '100%' : '85%',
            'reviewed_by' => $saleRecord['transfer_approved_by'] ?? $saleRecord['transfer_confirmed_by'] ?? 'POS Cashier',
        ];

        if (!class_exists('App\Modules\PaymentRecon\PaymentReconService')) {
            $paymentReconPath = dirname(__DIR__) . '/Modules/PaymentRecon/PaymentReconService.php';
            if (file_exists($paymentReconPath)) {
                require_once $paymentReconPath;
            }
        }

        if (class_exists('App\Modules\PaymentRecon\PaymentReconService')) {
            \App\Modules\PaymentRecon\PaymentReconService::recordAlert($paymentAlert);
        }

        self::upsertSessionRecord('payment_alerts', 'transaction_ref', $receiptNo, $paymentAlert);

        $bankFeedKey = 'bank_feeds_' . ($saleRecord['pos_mode'] ?? 'beverage');
        self::upsertSessionRecord($bankFeedKey, 'ref', $receiptNo, [
            'id' => time() + rand(1, 999),
            'bank_name' => 'Configured Bank Alert Mailbox',
            'account_number' => 'POS-TRANSFER',
            'ref' => $receiptNo,
            'date' => date('Y-m-d H:i'),
            'type' => 'Credit',
            'amount' => (float)($saleRecord['grand_total'] ?? 0),
            'matched_pos' => 'POS Receipt ' . $receiptNo,
            'confidence' => $status === 'Approved & Posted' ? '100% Match' : '85% Awaiting Approval',
            'status' => $status === 'Approved & Posted' ? 'Matched' : 'Pending Review',
        ]);
    }

    private static function syncFinanceModules(string $company, array $saleRecord, string $receiptNo, string $paymentMethod, float $grandTotal, float $subtotal, float $crateDeposit, string $cashier): void
    {
        if (class_exists('App\Modules\Finance\DoubleEntryJournalService') && !self::journalExists($company, $receiptNo)) {
            $lines = [
                ['code' => self::cashAccountCodeForPayment($paymentMethod), 'name' => self::cashAccountForPayment($paymentMethod), 'debit' => $grandTotal, 'credit' => 0.00],
                ['code' => '4110', 'name' => 'Beverage Wholesale Sales Revenue', 'debit' => 0.00, 'credit' => max(0.0, $subtotal)],
            ];
            if ($crateDeposit > 0) {
                $lines[] = ['code' => '2050', 'name' => 'Customer Crate Deposit Liability', 'debit' => 0.00, 'credit' => $crateDeposit];
            }

            \App\Modules\Finance\DoubleEntryJournalService::createJournalEntry($company, [
                'event_type' => 'pos_sale',
                'reference' => $receiptNo,
                'narration' => "POS sale posted from {$saleRecord['mode_title']} for {$saleRecord['customer_name']}",
                'posted_by' => $cashier,
                'lines' => $lines,
            ]);
        }

        $method = strtolower($paymentMethod);
        if (str_contains($method, 'debt') || str_contains($method, 'credit')) {
            $sessionKey = "ar_debtors_{$company}";
            if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
                $_SESSION[$sessionKey] = class_exists('App\Modules\Finance\AccountsReceivableService')
                    ? \App\Modules\Finance\AccountsReceivableService::getDebtors($company)
                    : [];
            }
            if (!self::sessionRecordExists($_SESSION[$sessionKey], 'invoice_number', $receiptNo)) {
                array_unshift($_SESSION[$sessionKey], [
                    'id' => time() + rand(100, 999),
                    'customer_name' => $saleRecord['customer_name'],
                    'invoice_number' => $receiptNo,
                    'invoice_date' => date('Y-m-d'),
                    'due_date' => date('Y-m-d', strtotime('+14 days')),
                    'total_amount' => $grandTotal,
                    'paid_amount' => 0.00,
                    'balance_due' => $grandTotal,
                    'aging' => 'Current (0-30 Days)',
                    'credit_limit' => 0.00,
                    'status' => 'Open POS Credit Sale',
                ]);
            }
        }
    }

    private static function syncOpeningInventoryJournal(string $company, string $sku, string $productName, float $amount): void
    {
        $reference = "PROD-{$sku}:opening_inventory";
        if ($amount <= 0 || self::journalExists($company, $reference)) {
            return;
        }

        if (class_exists('App\Modules\Finance\DoubleEntryJournalService')) {
            \App\Modules\Finance\DoubleEntryJournalService::createJournalEntry($company, [
                'event_type' => 'product_master_opening_stock',
                'reference' => $reference,
                'narration' => "Opening stock value recognized for new product {$productName}",
                'posted_by' => \current_user()['name'] ?? 'Warehouse Admin',
                'lines' => [
                    ['code' => '1210', 'name' => 'Beverage Finished Goods Inventory', 'debit' => $amount, 'credit' => 0.00],
                    ['code' => '3010', 'name' => 'Opening Inventory / Stock Adjustment Clearing', 'debit' => 0.00, 'credit' => $amount],
                ],
            ]);
        }
    }

    private static function expiryStatus(string $expiryDate): string
    {
        if ($expiryDate === '') {
            return 'No Expiry Date';
        }

        $days = (int)floor((strtotime($expiryDate) - time()) / 86400);
        if ($days < 0) {
            return 'Expired';
        }
        if ($days <= 30) {
            return 'Expiring Soon';
        }

        return 'Good FEFO';
    }

    private static function journalExists(string $company, string $reference): bool
    {
        $sessionKey = "journal_entries_{$company}";
        foreach ($_SESSION[$sessionKey] ?? [] as $entry) {
            if ((string)($entry['reference'] ?? '') === $reference) {
                return true;
            }
        }

        return false;
    }

    private static function recordSyncEvent(string $company, string $event, string $reference, array $modules): void
    {
        if (!isset($_SESSION['erp_realtime_sync_events']) || !is_array($_SESSION['erp_realtime_sync_events'])) {
            $_SESSION['erp_realtime_sync_events'] = [];
        }

        $syncReference = $reference . ':' . $event;
        if (self::sessionRecordExists($_SESSION['erp_realtime_sync_events'], 'reference', $syncReference)) {
            return;
        }

        array_unshift($_SESSION['erp_realtime_sync_events'], [
            'id' => 'SYNC-' . strtoupper(substr($event, 0, 3)) . '-' . rand(1000, 9999),
            'company_id' => $company,
            'event' => $event,
            'reference' => $syncReference,
            'modules' => $modules,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function estimateCratesFromItems(array $items): int
    {
        $crates = 0;
        foreach ($items as $item) {
            if ((float)($item['crate_deposit'] ?? 0) > 0) {
                $crates += (int)($item['qty'] ?? 0);
            }
        }

        return $crates;
    }

    private static function cashAccountForPayment(string $paymentMethod): string
    {
        $method = strtolower($paymentMethod);
        if (str_contains($method, 'transfer')) {
            return '1010 Bank Account';
        }
        if (str_contains($method, 'card') || str_contains($method, 'pos')) {
            return '1015 POS Card Settlement Account';
        }
        if (str_contains($method, 'wallet')) {
            return '1140 Customer Wallet Liability';
        }
        if (str_contains($method, 'debt') || str_contains($method, 'credit')) {
            return '1150 Accounts Receivable (Trade Debtors)';
        }

        return '1020 Cash Vault';
    }

    private static function cashAccountCodeForPayment(string $paymentMethod): string
    {
        $method = strtolower($paymentMethod);
        if (str_contains($method, 'transfer')) {
            return '1010';
        }
        if (str_contains($method, 'card') || str_contains($method, 'pos')) {
            return '1015';
        }
        if (str_contains($method, 'wallet')) {
            return '1140';
        }
        if (str_contains($method, 'debt') || str_contains($method, 'credit')) {
            return '1150';
        }

        return '1020';
    }

    private static function sessionRecordExists(array $records, string $key, string $value): bool
    {
        foreach ($records as $record) {
            if ((string)($record[$key] ?? '') === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Live Aggregated ERP KPIs
     */
    public static function getLiveKpis(string $company = 'beverage'): array
    {
        self::initSession();

        $totalInventoryValue = 0.0;
        $totalAvailableCrates = 0;
        $lowStockCount = 0;

        if ($company === 'beverage' && isset($_SESSION['beverage_products'])) {
            foreach ($_SESSION['beverage_products'] as $p) {
                $crates = (int)($p['stock_crates'] ?? 0);
                $price = (float)($p['cost_price'] ?? $p['wholesale_price'] ?? 0.0);
                $totalAvailableCrates += $crates;
                $totalInventoryValue += ($crates * $price);
                if ($crates <= (int)($p['min_stock'] ?? 20)) {
                    $lowStockCount++;
                }
            }
        }

        $totalSalesToday = 0.0;
        $totalSalesCount = 0;
        foreach ($_SESSION['pos_sales_history'] ?? [] as $s) {
            if (($s['pos_mode'] ?? 'beverage') !== $company) {
                continue;
            }
            $totalSalesToday += (float)($s['grand_total'] ?? 0.0);
            $totalSalesCount++;
        }

        $totalPendingDebt = 0.0;
        foreach ($_SESSION['beverage_customers_ledger'] ?? [] as $c) {
            $totalPendingDebt += (float)($c['current_debt'] ?? 0.0);
        }

        return [
            'total_inventory_value' => $totalInventoryValue,
            'total_crates' => $totalAvailableCrates,
            'total_sales_today' => $totalSalesToday,
            'sales_count' => $totalSalesCount,
            'pending_debt' => $totalPendingDebt,
            'low_stock_count' => $lowStockCount,
        ];
    }
}
