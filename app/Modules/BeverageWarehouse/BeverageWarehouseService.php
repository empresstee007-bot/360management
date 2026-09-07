<?php

declare(strict_types=1);

namespace App\Modules\BeverageWarehouse;

class BeverageWarehouseService
{
    private const DEFAULT_PRODUCT_IMAGE = 'assets/images/beverage_pack_placeholder.svg';
    private const WAREHOUSE_LOCATIONS = [
        'Jacroxx Warehouse',
        'Ijaba Warehouse',
    ];
    private static ?string $lastImageUploadError = null;

    public static function getLastImageUploadError(): ?string
    {
        return self::$lastImageUploadError;
    }

    public static function getProductMasterVersion(): string
    {
        $catalogPath = dirname(__DIR__, 2) . '/Config/beverage_products.php';
        $items = file_exists($catalogPath) ? require $catalogPath : [];

        return md5(json_encode($items));
    }

    private static function resolveProductImage(array $data, array $files, ?string $existingImage = null): string
    {
        self::$lastImageUploadError = null;
        $imageSource = (string)($data['product_image_source'] ?? 'keep');
        $submittedImageUrl = trim((string)($data['product_image_url'] ?? ''));
        $existingImage = trim((string)($existingImage ?? ''));

        $hasUpload = isset($files['product_image'])
            && is_array($files['product_image'])
            && (int)($files['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if (!$hasUpload) {
            if ($submittedImageUrl !== '' && ($imageSource === 'url' || $submittedImageUrl !== $existingImage)) {
                return $submittedImageUrl;
            }

            if ($imageSource === 'upload') {
                self::$lastImageUploadError = 'No image file reached the server. Please choose the file again, then click Update Product.';
            }

            return $existingImage ?: self::DEFAULT_PRODUCT_IMAGE;
        }

        $upload = $files['product_image'];
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::$lastImageUploadError = match ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected image is too large for the server upload limit.',
                UPLOAD_ERR_PARTIAL => 'The selected image only uploaded partially. Please try again.',
                UPLOAD_ERR_NO_FILE => null,
                default => 'The selected image could not be uploaded. Please choose another image.',
            };
            return $existingImage ?: self::DEFAULT_PRODUCT_IMAGE;
        }

        $tmpName = (string)($upload['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            self::$lastImageUploadError = 'The selected image could not be read by the server.';
            return $existingImage ?: self::DEFAULT_PRODUCT_IMAGE;
        }

        $mime = mime_content_type($tmpName) ?: '';
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => '',
        };

        if ($extension === '') {
            self::$lastImageUploadError = 'Only JPG, PNG, GIF, and WebP product images are supported.';
            return $existingImage ?: self::DEFAULT_PRODUCT_IMAGE;
        }

        $targetDir = dirname(__DIR__, 3) . '/public/uploads/products/';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $originalName = pathinfo((string)($upload['name'] ?? 'product'), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName) ?: 'product';
        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName . '.' . $extension;

        if (@move_uploaded_file($tmpName, $targetDir . $fileName)) {
            return 'uploads/products/' . $fileName;
        }

        self::$lastImageUploadError = 'The product image folder is not writable. Please check public/uploads/products permissions.';
        return $existingImage ?: self::DEFAULT_PRODUCT_IMAGE;
    }

    /**
     * Convert dual unit of measure quantities (Crates/Cartons to Bottles and vice versa)
     */
    public static function convertUom(int $crates, int $looseBottles, int $unitsPerCrate = 24): array
    {
        $totalBottles = ($crates * $unitsPerCrate) + $looseBottles;
        $calculatedCrates = (int)floor($totalBottles / $unitsPerCrate);
        $remainingBottles = $totalBottles % $unitsPerCrate;

        return [
            'total_bottles' => $totalBottles,
            'crates' => $calculatedCrates,
            'loose_bottles' => $remainingBottles,
            'formatted' => "{$calculatedCrates} Crates, {$remainingBottles} Bottles"
        ];
    }

    /**
     * Get list of managed warehouses
     */
    public static function getWarehouses(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['warehouse_locations'] = self::WAREHOUSE_LOCATIONS;

        return $_SESSION['warehouse_locations'];
    }

    public static function normalizeWarehouse(?string $warehouse): string
    {
        $warehouses = self::getWarehouses();
        $warehouse = trim((string)$warehouse);

        return in_array($warehouse, $warehouses, true) ? $warehouse : ($warehouses[0] ?? 'Jacroxx Warehouse');
    }

    /**
     * Start with no products; real stock should be added by the user.
     */
    public static function getInitialProducts(): array
    {
        $catalogPath = dirname(__DIR__, 2) . '/Config/beverage_products.php';
        $items = file_exists($catalogPath) ? require $catalogPath : [];

        return array_map(static function (array $item, int $index): array {
            $name = (string)($item['name'] ?? '');
            $unit = strtoupper(trim((string)($item['unit'] ?? 'PACK')));
            $unitsPerPack = max(0, (int)($item['units_per_pack'] ?? 0));
            $stockPacks = (int)($item['stock_crates'] ?? 0);
            $product = [
                'id' => 1000 + $index,
                'company_id' => 'beverage',
                'sku' => strtoupper(trim((string)($item['sku'] ?? self::makeSku($name)))),
                'barcode' => '',
                'name' => $name,
                'brand' => self::inferBrand($name),
                'category' => self::inferCategory($name),
                'flavour' => self::inferFlavour($name),
                'size' => self::inferSize($name),
                'packaging' => $unit,
                'config' => $unitsPerPack > 0 ? "{$unit} {$unitsPerPack}" : "{$unit} - confirm quantity",
                'units_per_crate' => $unitsPerPack,
                'cost_price' => (float)($item['cost_price'] ?? 0.0),
                'selling_price' => (float)($item['selling_price'] ?? $item['cost_price'] ?? 0.0),
                'wholesale_price' => (float)($item['wholesale_price'] ?? $item['cost_price'] ?? 0.0),
                'retail_price' => (float)($item['retail_price'] ?? $item['cost_price'] ?? 0.0),
                'distributor_price' => (float)($item['distributor_price'] ?? $item['cost_price'] ?? 0.0),
                'crate_deposit' => (float)($item['crate_deposit'] ?? 0.0),
                'supplier' => '',
                'reorder_level' => 0,
                'min_stock' => 0,
                'max_stock' => 0,
                'stock_crates' => $stockPacks,
                'stock_bottles' => $stockPacks * $unitsPerPack,
                'batch_number' => '',
                'expiry_date' => '',
                'image' => trim((string)($item['image'] ?? '')) ?: self::inferDefaultProductImage($name, $unit),
            ];
            return $product;
        }, $items, array_keys($items));
    }

    public static function inferDefaultProductImage(string $name, string $packaging = ''): string
    {
        $needle = strtoupper($name . ' ' . $packaging);

        if (str_contains($needle, 'COCA') || str_contains($needle, 'COKE')) {
            if (str_contains($needle, 'CRATE') || str_contains($needle, 'CASE') || str_contains($needle, 'EMPT')) {
                return 'assets/images/coca_cola_50cl_crate.png';
            }

            if (str_contains($needle, 'CAN')) {
                return 'assets/images/coca_cola.svg';
            }

            return 'assets/images/coca_cola_bottle.svg';
        }

        if (str_contains($needle, 'FANTA')) {
            return (str_contains($needle, 'CRATE') || str_contains($needle, 'RGB'))
                ? 'assets/images/fanta_crate.svg'
                : 'assets/images/fanta_bottle.svg';
        }

        if (str_contains($needle, 'SPRITE')) {
            return (str_contains($needle, 'CRATE') || str_contains($needle, 'RGB'))
                ? 'assets/images/sprite_crate.svg'
                : 'assets/images/sprite_bottle.svg';
        }

        if (str_contains($needle, 'EVA') || str_contains($needle, 'WATER') || str_contains($needle, 'AQUAFINA') || str_contains($needle, 'NIRVANA') || str_contains($needle, 'CIELLO') || str_contains($needle, 'CWAY') || str_contains($needle, 'LA AQUA')) {
            return (str_contains($needle, 'PACK') || str_contains($needle, 'X12') || str_contains($needle, '12'))
                ? 'assets/images/eva_pack.svg'
                : 'assets/images/eva_water_bottle.svg';
        }

        if (str_contains($needle, 'MALT') || str_contains($needle, 'CHIEF')) {
            return (str_contains($needle, 'CRATE') || str_contains($needle, 'PACK'))
                ? 'assets/images/maltina_crate.svg'
                : 'assets/images/maltina_bottle.svg';
        }

        if (str_contains($needle, 'PEPSI') || str_contains($needle, '7UP') || str_contains($needle, 'MIRINDA') || str_contains($needle, 'LIMCA') || str_contains($needle, 'TEEM') || str_contains($needle, 'BIG COLA') || str_contains($needle, 'PLANET')) {
            return 'assets/images/coke_crate.svg';
        }

        if (str_contains($needle, '5ALIVE') || str_contains($needle, 'PULPY') || str_contains($needle, 'CHAPMAN') || str_contains($needle, 'VYBE')) {
            return 'assets/images/promo_banner.svg';
        }

        return self::DEFAULT_PRODUCT_IMAGE;
    }

    private static function makeSku(string $name): string
    {
        $sku = preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)) ?: 'BEV-PRODUCT';
        return 'BEV-' . trim($sku, '-');
    }

    private static function inferBrand(string $name): string
    {
        foreach (['SUPA COMMANDO', 'BIG COLA', 'COCA-COLA', 'SCHWEPPES', 'ROCKSTAR', 'PREDATOR', 'AQUAFINA', 'LACAZERA', 'MIRINDA', 'JACK DANIEL', 'NIRVANA', 'PEPSI', '5ALIVE', 'SPRITE', 'FANTA', 'COKE', '7UP', 'CHAPMAN', 'CIELLO', 'CLIMAX', 'CWAY', 'DUDU', 'EVA', 'LIPTON', 'TEEM', 'LIMCA', 'PLANET', 'PULPY', 'HYDR8', 'VYBE', 'CHIEF MALT'] as $brand) {
            if (str_contains($name, $brand)) {
                return $brand;
            }
        }

        return '';
    }

    private static function inferCategory(string $name): string
    {
        if (str_contains($name, 'WATER') || str_contains($name, 'NIRVANA') || str_contains($name, 'CIELLO') || str_contains($name, 'CWAY')) {
            return 'Water';
        }

        if (str_contains($name, '5ALIVE') || str_contains($name, 'PULPY')) {
            return 'Juice';
        }

        if (str_contains($name, 'SUPA COMMANDO') || str_contains($name, 'SUPA KOMMANDO') || str_contains($name, 'CLIMAX') || str_contains($name, 'MONSTER') || str_contains($name, 'PREDATOR') || str_contains($name, 'ROCKSTAR')) {
            return 'Energy Drinks';
        }

        if (str_contains($name, 'YOGURT')) {
            return 'Yogurt';
        }

        if (str_contains($name, 'EMPTIES')) {
            return 'Empties';
        }

        if (str_contains($name, 'JACK DANIEL')) {
            return 'Alcohol';
        }

        return 'Soft Drinks';
    }

    private static function inferFlavour(string $name): string
    {
        foreach (['LEMON', 'PULPY', 'MANGO', 'BERRYBLAST', 'CITRUS', 'TROPICAL', 'ZERO'] as $flavour) {
            if (str_contains($name, $flavour)) {
                return ucfirst(strtolower($flavour));
            }
        }

        return '';
    }

    private static function inferSize(string $name): string
    {
        if (preg_match('/(\d+(?:\.\d+)?\s*(?:LTR|L|CL|ML))/i', $name, $match)) {
            return strtoupper(str_replace(' ', '', $match[1]));
        }

        return '';
    }

    private static function inferPackaging(string $name): string
    {
        if (str_contains($name, 'CAN')) {
            return 'Can';
        }

        if (str_contains($name, 'PET')) {
            return 'PET Plastic';
        }

        if (str_contains($name, 'DISPENSER')) {
            return 'Dispenser Bottle';
        }

        if (str_contains($name, 'EMPTIES')) {
            return 'Empty Crates/Bottles';
        }

        return 'Bottle';
    }

    /**
     * Get all active products
     */
    public static function getProducts(): array
    {
        if (class_exists('App\Core\UnifiedDataEngine')) {
            return \App\Core\UnifiedDataEngine::getProducts();
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!isset($_SESSION['beverage_products']) || !is_array($_SESSION['beverage_products'])) {
            $_SESSION['beverage_products'] = self::getInitialProducts();
        }

        return $_SESSION['beverage_products'];
    }

    public static function getProductsForWarehouse(?string $warehouse = null, bool $includeZeroStock = false): array
    {
        $warehouse = self::normalizeWarehouse($warehouse);
        $products = self::getProducts();
        $stockByWarehouse = self::getWarehouseStockMap($products);
        $warehouseProducts = [];

        foreach ($products as $product) {
            $sku = strtoupper(trim((string)($product['sku'] ?? '')));
            $stockCrates = (int)($stockByWarehouse[$sku][$warehouse] ?? 0);
            if (!$includeZeroStock && $stockCrates <= 0) {
                continue;
            }

            $unitsPerCrate = (int)($product['units_per_crate'] ?? 0);
            $product['warehouse_location'] = $warehouse;
            $product['stock_crates'] = $stockCrates;
            $product['stock_bottles'] = $stockCrates * max(0, $unitsPerCrate);
            $warehouseProducts[] = $product;
        }

        return $warehouseProducts;
    }

    public static function getWarehouseSummaries(): array
    {
        $warehouses = self::getWarehouses();
        $products = self::getProducts();
        $stockByWarehouse = self::getWarehouseStockMap($products);
        $summaries = [];

        foreach ($warehouses as $warehouse) {
            $summaries[$warehouse] = [
                'warehouse' => $warehouse,
                'sku_count' => 0,
                'stock_crates' => 0,
                'stock_bottles' => 0,
                'stock_value' => 0.0,
            ];
        }

        foreach ($products as $product) {
            $sku = strtoupper(trim((string)($product['sku'] ?? '')));
            $unitsPerCrate = max(0, (int)($product['units_per_crate'] ?? 0));
            $unitPrice = (float)($product['cost_price'] ?? $product['wholesale_price'] ?? 0);

            foreach ($warehouses as $warehouse) {
                $stockCrates = (int)($stockByWarehouse[$sku][$warehouse] ?? 0);
                if ($stockCrates <= 0) {
                    continue;
                }

                $summaries[$warehouse]['sku_count']++;
                $summaries[$warehouse]['stock_crates'] += $stockCrates;
                $summaries[$warehouse]['stock_bottles'] += $stockCrates * $unitsPerCrate;
                $summaries[$warehouse]['stock_value'] += $stockCrates * $unitPrice;
            }
        }

        return $summaries;
    }

    public static function getProductWarehouseStock(string $sku): array
    {
        $sku = strtoupper(trim($sku));
        $warehouses = self::getWarehouses();
        $stockByWarehouse = self::getWarehouseStockMap(self::getProducts());
        $stock = [];

        foreach ($warehouses as $warehouse) {
            $stock[$warehouse] = (int)($stockByWarehouse[$sku][$warehouse] ?? 0);
        }

        return $stock;
    }

    public static function getAllProductWarehouseStock(): array
    {
        return self::getWarehouseStockMap(self::getProducts());
    }

    public static function setProductWarehouseStock(string $sku, ?string $warehouse, int $stockCrates): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sku = strtoupper(trim($sku));
        if ($sku === '') {
            return 0;
        }

        $warehouse = self::normalizeWarehouse($warehouse);
        $products = self::getProducts();
        $stockByWarehouse = self::getWarehouseStockMap($products);
        $stockByWarehouse[$sku][$warehouse] = max(0, $stockCrates);
        $_SESSION['beverage_warehouse_stock'] = $stockByWarehouse;
        self::persistWarehouseStock($sku, $warehouse, (int)$stockByWarehouse[$sku][$warehouse]);

        return array_sum(array_map('intval', $stockByWarehouse[$sku] ?? []));
    }

    private static function getWarehouseStockMap(array $products): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $warehouses = self::getWarehouses();
        $defaultWarehouse = $warehouses[0] ?? 'Jacroxx Warehouse';
        $stockByWarehouse = self::loadWarehouseStockFromDatabase();
        if ($stockByWarehouse === []) {
            $stockByWarehouse = $_SESSION['beverage_warehouse_stock'] ?? [];
        }
        if (!is_array($stockByWarehouse)) {
            $stockByWarehouse = [];
        }

        foreach ($products as $product) {
            $sku = strtoupper(trim((string)($product['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            if (!isset($stockByWarehouse[$sku]) || !is_array($stockByWarehouse[$sku])) {
                $stockByWarehouse[$sku] = [];
            }

            foreach ($warehouses as $warehouse) {
                $stockByWarehouse[$sku][$warehouse] = max(0, (int)($stockByWarehouse[$sku][$warehouse] ?? 0));
            }

            $productTotal = max(0, (int)($product['stock_crates'] ?? 0));
            $warehouseTotal = array_sum(array_map('intval', $stockByWarehouse[$sku]));
            if ($warehouseTotal !== $productTotal) {
                $stockByWarehouse[$sku][$defaultWarehouse] = max(0, (int)($stockByWarehouse[$sku][$defaultWarehouse] ?? 0) + ($productTotal - $warehouseTotal));
            }
        }

        $_SESSION['beverage_warehouse_stock'] = $stockByWarehouse;
        self::persistWarehouseStockMap($stockByWarehouse);

        return $stockByWarehouse;
    }

    private static function loadWarehouseStockFromDatabase(): array
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return [];
        }

        try {
            self::ensureWarehouseStockTable($pdo);
            $stmt = $pdo->query('SELECT sku, warehouse, stock_crates FROM product_warehouse_stock WHERE company_id = "beverage"');
            $rows = $stmt ? $stmt->fetchAll() : [];
            $map = [];
            foreach ($rows as $row) {
                $sku = strtoupper(trim((string)($row['sku'] ?? '')));
                $warehouse = self::normalizeWarehouse($row['warehouse'] ?? null);
                if ($sku === '') {
                    continue;
                }
                $map[$sku][$warehouse] = max(0, (int)($row['stock_crates'] ?? 0));
            }

            return $map;
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function persistWarehouseStock(string $sku, string $warehouse, int $stockCrates): void
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo || $sku === '') {
            return;
        }

        try {
            self::ensureWarehouseStockTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO product_warehouse_stock (company_id, sku, warehouse, stock_crates, updated_at)
                 VALUES ("beverage", ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE stock_crates = VALUES(stock_crates), updated_at = VALUES(updated_at)'
            );
            $stmt->execute([strtoupper(trim($sku)), self::normalizeWarehouse($warehouse), max(0, $stockCrates)]);
        } catch (\Throwable $t) {
        }
    }

    private static function persistWarehouseStockMap(array $stockByWarehouse): void
    {
        foreach ($stockByWarehouse as $sku => $warehouseRows) {
            if (!is_array($warehouseRows)) {
                continue;
            }
            foreach ($warehouseRows as $warehouse => $qty) {
                self::persistWarehouseStock((string)$sku, (string)$warehouse, (int)$qty);
            }
        }
    }

    private static function ensureWarehouseStockTable(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS product_warehouse_stock (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                sku VARCHAR(100) NOT NULL,
                warehouse VARCHAR(120) NOT NULL,
                stock_crates INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_product_warehouse_stock (company_id, sku, warehouse),
                INDEX idx_product_warehouse_stock_lookup (company_id, warehouse, sku)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Add a new product to the catalog with image attachment support
     */
    public static function addProduct(array $data, array $files = []): array
    {
        $products = self::getProducts();

        $sku = strtoupper(trim((string)($data['sku'] ?? '')));
        if (empty($sku)) {
            $sku = 'BEV-' . strtoupper(substr(md5(uniqid()), 0, 6));
        }

        $unitsPerCrate = (int)($data['units_per_crate'] ?? 24);
        if ($unitsPerCrate <= 0) $unitsPerCrate = 24;

        $warehouseLocation = self::normalizeWarehouse($data['warehouse_location'] ?? null);
        $initialCrates = (int)($data['stock_crates'] ?? 0);
        $initialBottles = $initialCrates * $unitsPerCrate;

        $imageUrl = self::resolveProductImage($data, $files);

        $newProduct = [
            'id' => time(),
            'sku' => $sku,
            'barcode' => trim((string)($data['barcode'] ?? '')),
            'name' => trim((string)($data['name'] ?? '')),
            'brand' => trim((string)($data['brand'] ?? '')),
            'category' => trim((string)($data['category'] ?? '')),
            'flavour' => trim((string)($data['flavour'] ?? '')),
            'size' => trim((string)($data['size'] ?? '')),
            'packaging' => trim((string)($data['packaging'] ?? '')),
            'config' => trim((string)($data['config'] ?? "Crate {$unitsPerCrate}")),
            'units_per_crate' => $unitsPerCrate,
            'packaging_type' => trim((string)($data['packaging_type'] ?? self::inferPackaging($data['name'] ?? ''))),
            'inventory_unit' => trim((string)($data['inventory_unit'] ?? $data['unit'] ?? 'Pack/Crate')),
            'packs_per_pallet' => max(0, (int)($data['packs_per_pallet'] ?? 0)),
            'invoice_price' => (float)($data['invoice_price'] ?? $data['cost_price'] ?? 0.0),
            'rebate_base_price' => (float)($data['rebate_base_price'] ?? 0.0),
            'minimum_selling_price' => (float)($data['minimum_selling_price'] ?? 0.0),
            'cost_price' => (float)($data['cost_price'] ?? 0.0),
            'selling_price' => (float)($data['selling_price'] ?? 0.0),
            'wholesale_price' => (float)($data['wholesale_price'] ?? 0.0),
            'retail_price' => (float)($data['retail_price'] ?? 0.0),
            'distributor_price' => (float)($data['distributor_price'] ?? 0.0),
            'crate_deposit' => (float)($data['crate_deposit'] ?? 0.0),
            'supplier' => trim((string)($data['supplier'] ?? '')),
            'reorder_level' => (int)($data['reorder_level'] ?? 0),
            'min_stock' => (int)($data['min_stock'] ?? 0),
            'max_stock' => (int)($data['max_stock'] ?? 0),
            'stock_crates' => $initialCrates,
            'stock_bottles' => $initialBottles,
            'warehouse_location' => $warehouseLocation,
            'batch_number' => trim((string)($data['batch_number'] ?? '')),
            'expiry_date' => trim((string)($data['expiry_date'] ?? '')),
            'image' => $imageUrl,
        ];

        array_unshift($products, $newProduct);
        $_SESSION['beverage_products'] = $products;
        self::setProductWarehouseStock($sku, $warehouseLocation, $initialCrates);

        if (class_exists('App\Core\UnifiedDataEngine')) {
            \App\Core\UnifiedDataEngine::saveProduct($newProduct);
            \App\Core\UnifiedDataEngine::syncProductMaster($newProduct, 'created', 'beverage');
        }

        if (class_exists('App\Modules\Pos\PricingRebateService')) {
            \App\Modules\Pos\PricingRebateService::logAudit(
                'product_price',
                $sku,
                'Product Price Created',
                [],
                [
                    'cost_price' => $newProduct['cost_price'],
                    'selling_price' => $newProduct['selling_price'],
                    'wholesale_price' => $newProduct['wholesale_price'],
                ],
                current_user()['name'] ?? 'Warehouse Admin'
            );
        }

        // Record Audit Trail
        self::logMovement([
            'user' => current_user()['name'] ?? 'Warehouse Admin',
            'action' => 'Product Created',
            'item' => $newProduct['name'],
            'quantity' => "{$initialCrates} Crates",
            'previous_balance' => '0 Crates',
            'new_balance' => "{$initialCrates} Crates",
            'location' => $warehouseLocation,
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return $newProduct;
    }

    /**
     * Update an existing product
     */
    public static function updateProduct(string $sku, array $data, array $files = []): ?array
    {
        $products = self::getProducts();
        $foundIndex = null;

        foreach ($products as $idx => $p) {
            if (strcasecmp($p['sku'], $sku) === 0 || (isset($p['id']) && (int)$p['id'] === (int)($data['product_id'] ?? 0))) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === null) {
            return null;
        }

        $existing = $products[$foundIndex];

        $imageUrl = self::resolveProductImage($data, $files, $existing['image'] ?? self::DEFAULT_PRODUCT_IMAGE);

        $unitsPerCrate = (int)($data['units_per_crate'] ?? $existing['units_per_crate'] ?? 24);
        if ($unitsPerCrate <= 0) $unitsPerCrate = 24;

        $warehouseLocation = self::normalizeWarehouse($data['warehouse_location'] ?? ($existing['warehouse_location'] ?? null));
        $stockCrates = (int)($data['stock_crates'] ?? $existing['stock_crates'] ?? 0);
        $totalStockCrates = self::setProductWarehouseStock((string)($existing['sku'] ?? $sku), $warehouseLocation, $stockCrates);

        $updatedProduct = array_merge($existing, [
            'name' => trim((string)($data['name'] ?? $existing['name'])),
            'category' => trim((string)($data['category'] ?? $existing['category'])),
            'packaging' => trim((string)($data['packaging'] ?? $existing['packaging'])),
            'units_per_crate' => $unitsPerCrate,
            'packaging_type' => trim((string)($data['packaging_type'] ?? $existing['packaging_type'] ?? self::inferPackaging($existing['name'] ?? ''))),
            'inventory_unit' => trim((string)($data['inventory_unit'] ?? $existing['inventory_unit'] ?? $existing['unit'] ?? 'Pack/Crate')),
            'packs_per_pallet' => max(0, (int)($data['packs_per_pallet'] ?? $existing['packs_per_pallet'] ?? 0)),
            'invoice_price' => (float)($data['invoice_price'] ?? $existing['invoice_price'] ?? $existing['cost_price'] ?? 0),
            'rebate_base_price' => (float)($data['rebate_base_price'] ?? $existing['rebate_base_price'] ?? 0),
            'minimum_selling_price' => (float)($data['minimum_selling_price'] ?? $existing['minimum_selling_price'] ?? 0),
            'wholesale_price' => (float)($data['wholesale_price'] ?? $existing['wholesale_price']),
            'crate_deposit' => (float)($data['crate_deposit'] ?? $existing['crate_deposit']),
            'stock_crates' => $totalStockCrates,
            'stock_bottles' => $totalStockCrates * $unitsPerCrate,
            'warehouse_location' => $warehouseLocation,
            'expiry_date' => trim((string)($data['expiry_date'] ?? $existing['expiry_date'])),
            'image' => $imageUrl,
        ]);

        $products[$foundIndex] = $updatedProduct;
        $_SESSION['beverage_products'] = $products;

        if (class_exists('App\Core\UnifiedDataEngine')) {
            \App\Core\UnifiedDataEngine::saveProduct($updatedProduct);
            \App\Core\UnifiedDataEngine::syncProductMaster($updatedProduct, 'updated', 'beverage');
        }

        $priceAuditOld = [];
        $priceAuditNew = [];
        foreach (['cost_price', 'selling_price', 'wholesale_price'] as $priceField) {
            $oldValue = (float)($existing[$priceField] ?? 0);
            $newValue = (float)($updatedProduct[$priceField] ?? 0);
            if (abs($oldValue - $newValue) > 0.0001) {
                $priceAuditOld[$priceField] = $oldValue;
                $priceAuditNew[$priceField] = $newValue;
            }
        }
        if (!empty($priceAuditNew) && class_exists('App\Modules\Pos\PricingRebateService')) {
            \App\Modules\Pos\PricingRebateService::logAudit(
                'product_price',
                $updatedProduct['sku'] ?? $sku,
                'Product Price Updated',
                $priceAuditOld,
                $priceAuditNew,
                current_user()['name'] ?? 'Warehouse Admin'
            );
        }

        // Log Audit Movement
        self::logMovement([
            'user' => current_user()['name'] ?? 'Warehouse Admin',
            'action' => 'Product Details Updated',
            'item' => $updatedProduct['name'],
            'quantity' => "{$stockCrates} Crates",
            'previous_balance' => "{$existing['stock_crates']} Crates",
            'new_balance' => "{$totalStockCrates} Total Crates",
            'location' => $warehouseLocation,
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return $updatedProduct;
    }

    /**
     * Stock Issuing to Field / Promoter / Agent / Distributor
     */
    public static function issueStockToField(array $data): array
    {
        $recipient = trim((string)($data['recipient_name'] ?? 'Promoter #1'));
        $purpose = trim((string)($data['purpose_campaign'] ?? 'Activation Promo'));
        $qty = (int)($data['quantity_issued'] ?? 10);
        $itemName = trim((string)($data['item_name'] ?? 'Coca-Cola 50cl'));

        $record = [
            'id' => 'ISS-' . time(),
            'recipient' => $recipient,
            'purpose' => $purpose,
            'item_name' => $itemName,
            'quantity' => $qty,
            'status' => 'Issued & Assigned',
            'timestamp' => date('d M Y, h:i A'),
            'qr_code' => "ISS:{$recipient}|QTY:{$qty}|ITEM:{$itemName}",
        ];

        $_SESSION['issued_stock_log'] = $_SESSION['issued_stock_log'] ?? [];
        array_unshift($_SESSION['issued_stock_log'], $record);

        self::logMovement([
            'user' => current_user()['name'] ?? 'Warehouse Admin',
            'action' => 'Stock Issued to Field',
            'item' => $itemName,
            'quantity' => "-{$qty} Crates",
            'previous_balance' => '1,250 Crates',
            'new_balance' => (1250 - $qty) . ' Crates',
            'location' => "Field Agent: {$recipient}",
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return $record;
    }

    /**
     * Search product images online (APIs + Curated High-Definition Beverage Library)
     *
     * @param string $query
     * @return array
     */
    public static function searchOnlineImages(string $query): array
    {
        $query = trim($query);
        $results = [];
        $cleanQuery = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $query));
        $keywords = array_values(array_filter(explode(' ', $cleanQuery), fn($w) => strlen($w) > 1));

        // 1. Curated High Quality Beverage Database
        $curatedCatalog = [
            [
                'title' => 'Coca-Cola Glass Bottle Crate (24x50cl)',
                'url' => 'assets/images/coca_cola_50cl_crate.png',
                'thumb' => 'assets/images/coca_cola_50cl_crate.png',
                'source' => 'Depot Product Library',
                'tags' => ['coca', 'cola', 'coke', 'crate', 'bottle', '50cl', 'glass', 'rgb', 'soft'],
            ],
            [
                'title' => 'Coca-Cola Red Classic Crate (24 Bottles)',
                'url' => 'assets/images/coke_crate.svg',
                'thumb' => 'assets/images/coke_crate.svg',
                'source' => 'Depot Product Library',
                'tags' => ['coca', 'cola', 'coke', 'crate', 'soft', 'drink'],
            ],
            [
                'title' => 'Coca-Cola Glass Contour Bottle 50cl',
                'url' => 'assets/images/coca_cola_bottle.svg',
                'thumb' => 'assets/images/coca_cola_bottle.svg',
                'source' => 'Depot Product Library',
                'tags' => ['coca', 'cola', 'coke', 'bottle', 'glass', 'contour', '35cl', '50cl'],
            ],
            [
                'title' => 'Coca-Cola Classic Chilled Can (33cl)',
                'url' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['coca', 'cola', 'coke', 'can', '33cl', 'zero'],
            ],
            [
                'title' => 'Coca-Cola PET Bottles Pack',
                'url' => 'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['coca', 'cola', 'coke', 'pet', 'pack', '60cl', '35cl', 'mama', '1litre'],
            ],
            [
                'title' => 'Fanta Orange Crate (24 Bottles)',
                'url' => 'assets/images/fanta_crate.svg',
                'thumb' => 'assets/images/fanta_crate.svg',
                'source' => 'Depot Product Library',
                'tags' => ['fanta', 'orange', 'apple', 'crate', 'bottle', 'soft'],
            ],
            [
                'title' => 'Fanta Orange Glass Bottle',
                'url' => 'assets/images/fanta_bottle.svg',
                'thumb' => 'assets/images/fanta_bottle.svg',
                'source' => 'Depot Product Library',
                'tags' => ['fanta', 'orange', 'bottle', 'glass', '35cl', '50cl'],
            ],
            [
                'title' => 'Fanta Orange Chilled Can (33cl)',
                'url' => 'https://images.unsplash.com/photo-1624517452488-04869289c4ca?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1624517452488-04869289c4ca?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['fanta', 'orange', 'can', '33cl', 'pet'],
            ],
            [
                'title' => 'Sprite Refreshing Green Crate',
                'url' => 'assets/images/sprite_crate.svg',
                'thumb' => 'assets/images/sprite_crate.svg',
                'source' => 'Depot Product Library',
                'tags' => ['sprite', 'crate', 'green', 'soft', 'bottle'],
            ],
            [
                'title' => 'Sprite Lemon-Lime Glass Bottle',
                'url' => 'assets/images/sprite_bottle.svg',
                'thumb' => 'assets/images/sprite_bottle.svg',
                'source' => 'Depot Product Library',
                'tags' => ['sprite', 'bottle', 'glass', 'lemon', 'lime', '35cl', '50cl'],
            ],
            [
                'title' => 'Sprite Ice Cold Can (33cl)',
                'url' => 'https://images.unsplash.com/photo-1625772299848-391b6a87d7b3?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1625772299848-391b6a87d7b3?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['sprite', 'can', '33cl', 'pet', 'pack'],
            ],
            [
                'title' => 'Eva Premium Water Shrink Pack (12x75cl)',
                'url' => 'assets/images/eva_pack.svg',
                'thumb' => 'assets/images/eva_pack.svg',
                'source' => 'Depot Product Library',
                'tags' => ['eva', 'water', 'pack', '75cl', '150cl', '12', 'table'],
            ],
            [
                'title' => 'Eva Table Water Bottle (75cl/1.5L)',
                'url' => 'assets/images/eva_water_bottle.svg',
                'thumb' => 'assets/images/eva_water_bottle.svg',
                'source' => 'Depot Product Library',
                'tags' => ['eva', 'water', 'bottle', '75cl', '150cl', 'hydration', 'nirvana', 'aqua'],
            ],
            [
                'title' => 'Pure Natural Mineral Water Bottles (Pack)',
                'url' => 'https://images.unsplash.com/photo-1548839140-29a749e1bc4e?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1548839140-29a749e1bc4e?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['water', 'eva', 'aquafina', 'nirvana', 'cway', 'table', 'la', 'pack', '150cl', '75cl', 'bottle'],
            ],
            [
                'title' => 'Maltina Premium Malt Drink Crate',
                'url' => 'assets/images/maltina_crate.svg',
                'thumb' => 'assets/images/maltina_crate.svg',
                'source' => 'Depot Product Library',
                'tags' => ['maltina', 'malt', 'amstel', 'crate', 'nourishing'],
            ],
            [
                'title' => 'Maltina Nourishing Malt Bottle',
                'url' => 'assets/images/maltina_bottle.svg',
                'thumb' => 'assets/images/maltina_bottle.svg',
                'source' => 'Depot Product Library',
                'tags' => ['maltina', 'malt', 'amstel', 'bottle', 'chief', 'glass', '30cl', '33cl'],
            ],
            [
                'title' => 'Amstel Malta Premium Can / Bottle',
                'url' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=300&auto=format&fit=crop&q=80',
                'source' => 'Depot Product Library',
                'tags' => ['malt', 'amstel', 'maltina', 'chief', 'can', '33cl'],
            ],
            [
                'title' => 'Malta Guinness Rich Non-Alcoholic Malt Drink (33cl)',
                'url' => 'https://images.unsplash.com/photo-1584225064785-c62a8b43d148?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1584225064785-c62a8b43d148?w=300&auto=format&fit=crop&q=80',
                'source' => 'Depot Product Library',
                'tags' => ['malta', 'guinness', 'malt', 'can', 'bottle', '33cl'],
            ],
            [
                'title' => 'Guinness Foreign Extra Stout Dark Beer Bottle (60cl/33cl)',
                'url' => 'https://images.unsplash.com/photo-1527281400683-1aae777175f8?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1527281400683-1aae777175f8?w=300&auto=format&fit=crop&q=80',
                'source' => 'Depot Product Library',
                'tags' => ['guinness', 'stout', 'extra', 'foreign', 'beer', 'glass', 'bottle', '60cl', '33cl', 'dark'],
            ],
            [
                'title' => 'Heineken Premium Quality Lager Beer Can / Bottle (33cl/60cl)',
                'url' => 'https://images.unsplash.com/photo-1584225064785-c62a8b43d148?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1584225064785-c62a8b43d148?w=300&auto=format&fit=crop&q=80',
                'source' => 'Depot Product Library',
                'tags' => ['heineken', 'lager', 'beer', 'can', 'bottle', 'star', '33cl', '60cl'],
            ],
            [
                'title' => 'Pepsi Cola Chilled Bottle / PET',
                'url' => 'https://images.unsplash.com/photo-1553456558-aff63285bdd1?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1553456558-aff63285bdd1?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['pepsi', 'cola', 'fizz', 'pet', '50cl', '60cl', '40cl', 'lite', 'blue', '15ltr'],
            ],
            [
                'title' => '7Up Refreshing Lemon Lime PET / Bottle',
                'url' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['7up', 'seven', 'up', 'pet', 'free', '40cl', '50cl', '60cl', 'lemon', 'tang'],
            ],
            [
                'title' => 'Mirinda Orange & Fruity Soft Drink',
                'url' => 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['mirinda', 'orange', 'pineapple', 'apple', 'pet', '40cl', '50cl', '60cl'],
            ],
            [
                'title' => 'Monster Energy Drink High Performance Can',
                'url' => 'https://images.unsplash.com/photo-1622543925917-763c34d1a86e?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1622543925917-763c34d1a86e?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['monster', 'energy', 'can', 'predator', 'climax', 'rockstar', 'supa', 'commando', 'power'],
            ],
            [
                'title' => 'Energy Drink Power Can (Predator / Climax / Fearless)',
                'url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['predator', 'climax', 'fearless', 'energy', 'gold', 'green', 'supa', 'commando', 'rockstar', 'can'],
            ],
            [
                'title' => '5Alive Juice & Pulpy Fruit Drink (85cl/90cl)',
                'url' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['5alive', 'pulpy', 'juice', 'orange', 'lemon', 'mango', 'berryblast', 'citrus', 'tropical', '85cl', '75cl', '90cl', '30cl', 'fruit', 'chi', 'chivita', 'exotic'],
            ],
            [
                'title' => 'Big Cola / Planet Cola Carbonated Drink',
                'url' => 'https://images.unsplash.com/photo-1567103472667-6898f3a79cf2?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1567103472667-6898f3a79cf2?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['big', 'cola', 'planet', '250ml', '650ml', '360ml', '525ml', 'pet', 'carbonated'],
            ],
            [
                'title' => 'Schweppes & Tonic / Soda Can 33cl',
                'url' => 'https://images.unsplash.com/photo-1527661591475-527312dd65f5?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1527661591475-527312dd65f5?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['schweppes', 'soda', 'tonic', 'chapman', 'bitter', 'lemon', '33cl', 'can', '40cl'],
            ],
            [
                'title' => 'Dudu Yogurt / Hollandia Dairy Drink Pack',
                'url' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=300&auto=format&fit=crop&q=80',
                'source' => 'Unsplash Beverage HD',
                'tags' => ['dudu', 'yogurt', 'hollandia', 'dairy', 'milk', 'drink', 'pack'],
            ],
            [
                'title' => 'Premium Spirits & Whiskey Bottle (Jack Daniel)',
                'url' => 'https://images.unsplash.com/photo-1527281400683-1aae777175f8?w=600&auto=format&fit=crop&q=80',
                'thumb' => 'https://images.unsplash.com/photo-1527281400683-1aae777175f8?w=300&auto=format&fit=crop&q=80',
                'source' => 'Depot Product Library',
                'tags' => ['jack', 'daniel', 'whiskey', 'spirits', 'bottle', 'premium'],
            ],
        ];

        // 1. Live Google Programmable Search. Configure GOOGLE_IMAGE_SEARCH_API_KEY
        // and GOOGLE_IMAGE_SEARCH_CX in production to enable real-time product photos.
        $results = array_merge($results, self::searchGoogleProductImages($query));

        // 2. Match curated library
        $scoredCurated = [];
        foreach ($curatedCatalog as $item) {
            $score = 0;
            $titleLower = strtolower($item['title']);
            foreach ($keywords as $kw) {
                if (in_array($kw, $item['tags'], true)) {
                    $score += 3;
                } elseif (str_contains($titleLower, $kw)) {
                    $score += 2;
                }
            }
            if ($score > 0 || empty($keywords)) {
                $scoredCurated[] = ['score' => $score, 'item' => $item];
            }
        }

        usort($scoredCurated, fn($a, $b) => $b['score'] <=> $a['score']);
        foreach ($scoredCurated as $entry) {
            $results[] = $entry['item'];
        }

        if (count($results) >= 8) {
            return self::dedupeImageResults($results);
        }

        // 3. Try Open Food Facts API (if cURL / network accessible)
        if (function_exists('curl_init') && !empty($query)) {
            try {
                $qEncoded = urlencode($query);
                $apiUrl = "https://world.openfoodfacts.org/cgi/search.pl?search_terms={$qEncoded}&search_simple=1&action=process&json=1&page_size=6";
                $ch = @curl_init($apiUrl);
                if ($ch) {
                    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    @curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                    @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                    @curl_setopt($ch, CURLOPT_USERAGENT, '360ManagementBeverageDepot/1.0');
                    @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = @curl_exec($ch);
                    if ($response) {
                        $json = @json_decode($response, true);
                        if (!empty($json['products'])) {
                            foreach ($json['products'] as $p) {
                                $img = $p['image_front_url'] ?? $p['image_url'] ?? $p['image_small_url'] ?? '';
                                $thumb = $p['image_front_small_url'] ?? $p['image_small_url'] ?? $img;
                                $name = trim((string)($p['product_name'] ?? $p['generic_name'] ?? 'Beverage Product'));
                                if ($img !== '') {
                                    $results[] = [
                                        'title' => $name . (!empty($p['brands']) ? ' (' . $p['brands'] . ')' : ''),
                                        'url' => $img,
                                        'thumb' => $thumb ?: $img,
                                        'source' => 'Open Food Facts Database',
                                        'tags' => ['openfoodfacts', 'online'],
                                    ];
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore network timeouts gracefully
            }
        }

        // 4. Try Wikimedia Commons API
        if (function_exists('curl_init') && !empty($query) && count($results) < 8) {
            try {
                $qEncoded = urlencode($query . ' beverage bottle can');
                $wikiUrl = "https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrnamespace=6&gsrsearch={$qEncoded}&gsrlimit=6&prop=imageinfo&iiprop=url|mime|thumburl&iiurlwidth=300&format=json";
                $ch = @curl_init($wikiUrl);
                if ($ch) {
                    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    @curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                    @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                    @curl_setopt($ch, CURLOPT_USERAGENT, '360ManagementBeverageDepot/1.0 (info@beveragedepot.ng)');
                    @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = @curl_exec($ch);
                    if ($response) {
                        $json = @json_decode($response, true);
                        if (!empty($json['query']['pages'])) {
                            foreach ($json['query']['pages'] as $page) {
                                $info = $page['imageinfo'][0] ?? null;
                                if ($info && !empty($info['url'])) {
                                    $mime = (string)($info['mime'] ?? '');
                                    if (str_starts_with($mime, 'image/')) {
                                        $titleClean = str_replace(['File:', '.jpg', '.jpeg', '.png', '.webp', '_'], ['', '', '', '', '', ' '], (string)($page['title'] ?? 'Beverage'));
                                        $results[] = [
                                            'title' => trim($titleClean),
                                            'url' => $info['url'],
                                            'thumb' => $info['thumburl'] ?? $info['url'],
                                            'source' => 'Wikimedia Commons',
                                            'tags' => ['wikimedia', 'commons', 'cc'],
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore network timeouts gracefully
            }
        }

        return self::dedupeImageResults($results);
    }

    private static function dedupeImageResults(array $results): array
    {
        $unique = [];
        $final = [];

        foreach ($results as $result) {
            $url = trim((string)($result['url'] ?? ''));
            if ($url === '' || isset($unique[$url])) {
                continue;
            }

            $unique[$url] = true;
            $final[] = $result;
        }

        return $final;
    }

    public static function getGoogleImageSearchStatus(): array
    {
        $apiKey = self::googleImageSearchApiKey();
        $cx = self::googleImageSearchCx();

        return [
            'configured' => $apiKey !== '' && $cx !== '' && function_exists('curl_init'),
            'has_api_key' => $apiKey !== '',
            'has_search_engine' => $cx !== '',
            'curl_available' => function_exists('curl_init'),
        ];
    }

    private static function searchGoogleProductImages(string $query): array
    {
        $query = trim($query);
        $status = self::getGoogleImageSearchStatus();
        if ($query === '' || !$status['configured']) {
            return [];
        }

        $searchTerms = trim($query . ' beverage product pack crate bottle can Nigeria');
        $apiUrl = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
            'key' => self::googleImageSearchApiKey(),
            'cx' => self::googleImageSearchCx(),
            'searchType' => 'image',
            'num' => 8,
            'safe' => 'active',
            'q' => $searchTerms,
        ]);

        try {
            $ch = @curl_init($apiUrl);
            if (!$ch) {
                return [];
            }

            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            @curl_setopt($ch, CURLOPT_USERAGENT, '360ManagementBeverageDepot/1.0');
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = @curl_exec($ch);
            $statusCode = (int)@curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            @curl_close($ch);

            if (!$response || $statusCode < 200 || $statusCode >= 300) {
                return [];
            }

            $json = @json_decode($response, true);
            if (empty($json['items']) || !is_array($json['items'])) {
                return [];
            }

            $results = [];
            foreach ($json['items'] as $item) {
                $imageUrl = trim((string)($item['link'] ?? ''));
                if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
                    continue;
                }

                $imageMeta = is_array($item['image'] ?? null) ? $item['image'] : [];
                $thumb = trim((string)($imageMeta['thumbnailLink'] ?? '')) ?: $imageUrl;
                $displayLink = trim((string)($item['displayLink'] ?? ''));
                $title = trim((string)($item['title'] ?? 'Google Image Result'));

                $results[] = [
                    'title' => $title !== '' ? $title : 'Google Image Result',
                    'url' => $imageUrl,
                    'thumb' => $thumb,
                    'source' => $displayLink !== '' ? 'Google Images - ' . $displayLink : 'Google Images',
                    'tags' => ['google', 'live-search', 'product-photo'],
                ];
            }

            return $results;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function googleImageSearchApiKey(): string
    {
        return function_exists('env_value') ? trim((string)\env_value('GOOGLE_IMAGE_SEARCH_API_KEY', '')) : '';
    }

    private static function googleImageSearchCx(): string
    {
        return function_exists('env_value') ? trim((string)\env_value('GOOGLE_IMAGE_SEARCH_CX', '')) : '';
    }

    /**
     * Quick Update Product Image (via online search selection or direct URL)
     */
    public static function quickUpdateProductImage(string $sku, string $imageUrl): ?array
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return null;
        }

        $products = self::getProducts();
        $foundIndex = null;

        foreach ($products as $idx => $p) {
            if (strcasecmp((string)($p['sku'] ?? ''), $sku) === 0) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === null) {
            return null;
        }

        $existing = $products[$foundIndex];
        $updatedProduct = array_merge($existing, [
            'image' => $imageUrl,
        ]);

        $products[$foundIndex] = $updatedProduct;
        $_SESSION['beverage_products'] = $products;

        if (class_exists('App\Core\UnifiedDataEngine')) {
            try {
                \App\Core\UnifiedDataEngine::saveProduct($updatedProduct);
                \App\Core\UnifiedDataEngine::syncProductMaster($updatedProduct, 'updated', 'beverage');
            } catch (\Throwable $t) {
                error_log('Beverage product image persistence failed: ' . $t->getMessage());
            }
        }

        try {
            self::logMovement([
                'user' => current_user()['name'] ?? 'Warehouse Admin',
                'action' => 'Product Image Updated (Online Search)',
                'item' => (string)($updatedProduct['name'] ?? $sku) . ' (' . (string)($updatedProduct['sku'] ?? $sku) . ')',
                'quantity' => 'Image Updated',
                'previous_balance' => 'Old Image',
                'new_balance' => 'New Image Attached',
                'location' => 'Jacroxx Warehouse',
                'timestamp' => date('d M Y, h:i A'),
            ]);
        } catch (\Throwable $t) {
            error_log('Beverage product image audit failed: ' . $t->getMessage());
        }

        return $updatedProduct;
    }

    /**
     * Stock Transfers (Warehouse -> Warehouse / Field)
     */
    public static function createTransfer(array $data): array
    {
        $from = trim((string)($data['from_location'] ?? ''));
        $to = trim((string)($data['to_location'] ?? ''));
        $allowedWarehouses = self::getWarehouses();
        $from = in_array($from, $allowedWarehouses, true) ? $from : '';
        $to = in_array($to, $allowedWarehouses, true) ? $to : '';
        $qty = (int)($data['quantity'] ?? 0);
        $item = trim((string)($data['item_name'] ?? ''));
        $sku = strtoupper(trim((string)($data['sku'] ?? '')));

        if ($sku === '' && $item !== '') {
            foreach (self::getProducts() as $product) {
                if (strcasecmp((string)($product['name'] ?? ''), $item) === 0) {
                    $sku = strtoupper((string)($product['sku'] ?? ''));
                    break;
                }
            }
        }

        $productName = $item;
        $products = self::getProducts();
        foreach ($products as $idx => $product) {
            if ($sku !== '' && strcasecmp((string)($product['sku'] ?? ''), $sku) === 0) {
                $productName = (string)($product['name'] ?? $sku);
                $item = $productName;
                break;
            }
        }

        $stockByWarehouse = self::getWarehouseStockMap($products);
        $availableFrom = $sku !== '' ? (int)($stockByWarehouse[$sku][$from] ?? 0) : 0;
        $canMove = $from !== '' && $to !== '' && $from !== $to && $sku !== '' && $qty > 0 && $availableFrom >= $qty;

        if ($canMove) {
            $stockByWarehouse[$sku][$from] = max(0, $availableFrom - $qty);
            $stockByWarehouse[$sku][$to] = max(0, (int)($stockByWarehouse[$sku][$to] ?? 0) + $qty);
            $_SESSION['beverage_warehouse_stock'] = $stockByWarehouse;
            self::persistWarehouseStock($sku, $from, (int)$stockByWarehouse[$sku][$from]);
            self::persistWarehouseStock($sku, $to, (int)$stockByWarehouse[$sku][$to]);

            $totalStockCrates = array_sum(array_map('intval', $stockByWarehouse[$sku] ?? []));
            foreach ($products as $idx => $product) {
                if (strcasecmp((string)($product['sku'] ?? ''), $sku) === 0) {
                    $unitsPerCrate = max(0, (int)($product['units_per_crate'] ?? 0));
                    $products[$idx]['stock_crates'] = $totalStockCrates;
                    $products[$idx]['stock_bottles'] = $totalStockCrates * $unitsPerCrate;
                    if (class_exists('App\Core\UnifiedDataEngine')) {
                        \App\Core\UnifiedDataEngine::saveProduct($products[$idx]);
                    }
                    break;
                }
            }
            $_SESSION['beverage_products'] = $products;
        }

        $transfer = [
            'id' => 'TRF-' . rand(1000, 9999),
            'from' => $from,
            'to' => $to,
            'item' => $item,
            'sku' => $sku,
            'quantity' => $qty,
            'available_before' => $availableFrom,
            'status' => $canMove ? 'Completed' : 'Failed',
            'message' => $canMove
                ? "Moved {$qty} crates/packs of {$productName} from {$from} to {$to}."
                : 'Could not move stock. Check product, quantity, warehouse, and available stock.',
            'date' => date('d M Y, h:i A'),
        ];

        $_SESSION['stock_transfers_log'] = $_SESSION['stock_transfers_log'] ?? [];
        array_unshift($_SESSION['stock_transfers_log'], $transfer);
        self::persistStockTransfer($transfer);

        self::logMovement([
            'user' => current_user()['name'] ?? 'Warehouse Admin',
            'action' => $canMove ? 'Warehouse Stock Transfer Completed' : 'Warehouse Stock Transfer Failed',
            'item' => $productName !== '' ? $productName : ($sku ?: 'Unknown Product'),
            'quantity' => "{$qty} Crates/Packs",
            'previous_balance' => "{$availableFrom} at {$from}",
            'new_balance' => $canMove
                ? ((int)($stockByWarehouse[$sku][$from] ?? 0)) . " at {$from}, " . ((int)($stockByWarehouse[$sku][$to] ?? 0)) . " at {$to}"
                : 'No stock moved',
            'location' => "{$from} to {$to}",
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return $transfer;
    }

    public static function getTransfers(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $dbTransfers = self::loadStockTransfersFromDatabase();
        if ($dbTransfers !== []) {
            $_SESSION['stock_transfers_log'] = $dbTransfers;
            return $dbTransfers;
        }

        return $_SESSION['stock_transfers_log'] ?? [];
    }

    private static function persistStockTransfer(array $transfer): void
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return;
        }

        try {
            self::ensureStockTransfersTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO stock_transfers
                    (transfer_id, company_id, from_location, to_location, sku, item_name, quantity, available_before, status, message, transfer_json, created_at)
                 VALUES
                    (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), message = VALUES(message), transfer_json = VALUES(transfer_json)'
            );
            $stmt->execute([
                (string)($transfer['id'] ?? uniqid('TRF-', true)),
                (string)($transfer['from'] ?? ''),
                (string)($transfer['to'] ?? ''),
                strtoupper(trim((string)($transfer['sku'] ?? ''))),
                (string)($transfer['item'] ?? ''),
                (int)($transfer['quantity'] ?? 0),
                (int)($transfer['available_before'] ?? 0),
                (string)($transfer['status'] ?? 'Completed'),
                (string)($transfer['message'] ?? ''),
                json_encode($transfer, JSON_UNESCAPED_SLASHES),
                self::movementTimestampToSql((string)($transfer['date'] ?? '')),
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function loadStockTransfersFromDatabase(int $limit = 300): array
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return [];
        }

        try {
            self::ensureStockTransfersTable($pdo);
            $limit = max(1, min(1000, $limit));
            $stmt = $pdo->query(
                "SELECT transfer_id, from_location, to_location, sku, item_name, quantity, available_before, status, message, transfer_json, created_at
                 FROM stock_transfers
                 WHERE company_id = 'beverage'
                 ORDER BY created_at DESC, id DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];

            return array_map(static function (array $row): array {
                $saved = json_decode((string)($row['transfer_json'] ?? ''), true);
                $transfer = is_array($saved) ? $saved : [];
                $createdAt = (string)($row['created_at'] ?? '');
                $transfer['id'] = (string)($row['transfer_id'] ?? $transfer['id'] ?? '');
                $transfer['from'] = (string)($row['from_location'] ?? $transfer['from'] ?? '');
                $transfer['to'] = (string)($row['to_location'] ?? $transfer['to'] ?? '');
                $transfer['sku'] = (string)($row['sku'] ?? $transfer['sku'] ?? '');
                $transfer['item'] = (string)($row['item_name'] ?? $transfer['item'] ?? '');
                $transfer['quantity'] = (int)($row['quantity'] ?? $transfer['quantity'] ?? 0);
                $transfer['available_before'] = (int)($row['available_before'] ?? $transfer['available_before'] ?? 0);
                $transfer['status'] = (string)($row['status'] ?? $transfer['status'] ?? '');
                $transfer['message'] = (string)($row['message'] ?? $transfer['message'] ?? '');
                $transfer['date'] = $createdAt !== '' ? date('d M Y, h:i A', strtotime($createdAt)) : (string)($transfer['date'] ?? '');

                return $transfer;
            }, $rows);
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function ensureStockTransfersTable(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS stock_transfers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transfer_id VARCHAR(80) NOT NULL UNIQUE,
                company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                from_location VARCHAR(120) NOT NULL,
                to_location VARCHAR(120) NOT NULL,
                sku VARCHAR(100) NOT NULL,
                item_name VARCHAR(180) DEFAULT NULL,
                quantity INT NOT NULL DEFAULT 0,
                available_before INT NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT "Completed",
                message TEXT DEFAULT NULL,
                transfer_json LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_stock_transfers_company_date (company_id, created_at),
                INDEX idx_stock_transfers_sku (company_id, sku)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function getDailyStockReport(?string $date = null, ?string $warehouse = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $date = self::normalizeReportDate($date);
        $warehouseLabel = self::normalizeReportWarehouse($warehouse);
        $record = self::loadDailyStockRecord($date, $warehouseLabel);

        if (!$record) {
            $openingSnapshot = self::getCurrentStockSnapshot($warehouseLabel);
            $record = [
                'report_date' => $date,
                'warehouse' => $warehouseLabel,
                'opening_snapshot' => $openingSnapshot,
                'closing_snapshot' => null,
                'opening_captured_at' => date('Y-m-d H:i:s'),
                'closing_captured_at' => null,
                'created_by' => current_user()['name'] ?? 'System',
            ];
            self::saveDailyStockRecord($record);
        }

        $opening = $record['opening_snapshot'] ?? self::getCurrentStockSnapshot($warehouseLabel);
        $closingIsCaptured = !empty($record['closing_snapshot']);
        $closing = $closingIsCaptured ? $record['closing_snapshot'] : self::getCurrentStockSnapshot($warehouseLabel);

        return self::buildDailyStockReport($date, $warehouseLabel, $opening, $closing, [
            'opening_captured_at' => $record['opening_captured_at'] ?? null,
            'closing_captured_at' => $record['closing_captured_at'] ?? null,
            'closing_status' => $closingIsCaptured ? 'Captured' : 'Live Estimate',
            'created_by' => $record['created_by'] ?? 'System',
        ]);
    }

    public static function captureDailyOpeningStock(?string $date = null, ?string $warehouse = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $date = self::normalizeReportDate($date);
        $warehouseLabel = self::normalizeReportWarehouse($warehouse);
        $record = self::loadDailyStockRecord($date, $warehouseLabel) ?? [
            'report_date' => $date,
            'warehouse' => $warehouseLabel,
            'closing_snapshot' => null,
            'closing_captured_at' => null,
            'created_by' => current_user()['name'] ?? 'System',
        ];

        $record['opening_snapshot'] = self::getCurrentStockSnapshot($warehouseLabel);
        $record['opening_captured_at'] = date('Y-m-d H:i:s');
        self::saveDailyStockRecord($record);
        self::logMovement([
            'user' => current_user()['name'] ?? 'Warehouse Admin',
            'action' => 'Opening Stock Captured',
            'item' => 'Daily Stock Report',
            'quantity' => $warehouseLabel,
            'previous_balance' => $date,
            'new_balance' => number_format((int)($record['opening_snapshot']['totals']['stock_crates'] ?? 0)) . ' Crates/Packs',
            'location' => $warehouseLabel,
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return self::getDailyStockReport($date, $warehouseLabel);
    }

    public static function captureDailyClosingStock(?string $date = null, ?string $warehouse = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $date = self::normalizeReportDate($date);
        $warehouseLabel = self::normalizeReportWarehouse($warehouse);
        $record = self::loadDailyStockRecord($date, $warehouseLabel);

        if (!$record) {
            $record = [
                'report_date' => $date,
                'warehouse' => $warehouseLabel,
                'opening_snapshot' => self::getCurrentStockSnapshot($warehouseLabel),
                'opening_captured_at' => date('Y-m-d H:i:s'),
                'created_by' => current_user()['name'] ?? 'System',
            ];
        }

        $record['closing_snapshot'] = self::getCurrentStockSnapshot($warehouseLabel);
        $record['closing_captured_at'] = date('Y-m-d H:i:s');
        self::saveDailyStockRecord($record);
        self::logMovement([
            'user' => current_user()['name'] ?? 'Warehouse Admin',
            'action' => 'Closing Stock Captured',
            'item' => 'Daily Stock Report',
            'quantity' => $warehouseLabel,
            'previous_balance' => $date,
            'new_balance' => number_format((int)($record['closing_snapshot']['totals']['stock_crates'] ?? 0)) . ' Crates/Packs',
            'location' => $warehouseLabel,
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return self::getDailyStockReport($date, $warehouseLabel);
    }

    private static function normalizeReportDate(?string $date): string
    {
        $date = trim((string)$date);
        if ($date === '' || strtotime($date) === false) {
            return date('Y-m-d');
        }

        return date('Y-m-d', strtotime($date));
    }

    private static function normalizeReportWarehouse(?string $warehouse): string
    {
        $warehouse = trim((string)$warehouse);
        if ($warehouse === '' || strcasecmp($warehouse, 'All Warehouses') === 0) {
            return 'All Warehouses';
        }

        return self::normalizeWarehouse($warehouse);
    }

    private static function getCurrentStockSnapshot(string $warehouseLabel): array
    {
        $products = self::getProducts();
        $stockByWarehouse = self::getWarehouseStockMap($products);
        $warehouses = $warehouseLabel === 'All Warehouses' ? self::getWarehouses() : [$warehouseLabel];
        $rows = [];
        $totals = [
            'sku_count' => 0,
            'stock_crates' => 0,
            'stock_units' => 0,
            'stock_value' => 0.0,
        ];

        foreach ($products as $product) {
            $sku = strtoupper(trim((string)($product['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $stockCrates = 0;
            foreach ($warehouses as $warehouse) {
                $stockCrates += (int)($stockByWarehouse[$sku][$warehouse] ?? 0);
            }

            $unitsPerCrate = max(1, (int)($product['units_per_crate'] ?? 1));
            $costPrice = (float)($product['cost_price'] ?? $product['wholesale_price'] ?? 0);
            $stockUnits = $stockCrates * $unitsPerCrate;
            $stockValue = $stockCrates * $costPrice;

            if ($stockCrates > 0) {
                $totals['sku_count']++;
            }
            $totals['stock_crates'] += $stockCrates;
            $totals['stock_units'] += $stockUnits;
            $totals['stock_value'] += $stockValue;

            $rows[$sku] = [
                'sku' => $sku,
                'name' => (string)($product['name'] ?? $sku),
                'category' => (string)($product['category'] ?? ''),
                'warehouse' => $warehouseLabel,
                'pack_size' => $unitsPerCrate,
                'unit_label' => (string)($product['unit'] ?? $product['packaging'] ?? 'Pack/Crate'),
                'cost_price' => $costPrice,
                'stock_crates' => $stockCrates,
                'stock_units' => $stockUnits,
                'stock_value' => $stockValue,
            ];
        }

        return [
            'captured_at' => date('Y-m-d H:i:s'),
            'warehouse' => $warehouseLabel,
            'totals' => $totals,
            'rows' => $rows,
        ];
    }

    private static function buildDailyStockReport(string $date, string $warehouseLabel, array $opening, array $closing, array $meta = []): array
    {
        $openingRows = $opening['rows'] ?? [];
        $closingRows = $closing['rows'] ?? [];
        $allSkus = array_values(array_unique(array_merge(array_keys($openingRows), array_keys($closingRows))));
        sort($allSkus);
        $rows = [];

        foreach ($allSkus as $sku) {
            $open = $openingRows[$sku] ?? [];
            $close = $closingRows[$sku] ?? [];
            $name = (string)($close['name'] ?? $open['name'] ?? $sku);
            $packSize = (int)($close['pack_size'] ?? $open['pack_size'] ?? 1);
            $costPrice = (float)($close['cost_price'] ?? $open['cost_price'] ?? 0);
            $openingStock = (int)($open['stock_crates'] ?? 0);
            $closingStock = (int)($close['stock_crates'] ?? 0);
            $openingUnits = (int)($open['stock_units'] ?? 0);
            $closingUnits = (int)($close['stock_units'] ?? 0);

            $rows[] = [
                'sku' => $sku,
                'name' => $name,
                'category' => (string)($close['category'] ?? $open['category'] ?? ''),
                'warehouse' => $warehouseLabel,
                'pack_size' => $packSize,
                'unit_label' => (string)($close['unit_label'] ?? $open['unit_label'] ?? 'Pack/Crate'),
                'cost_price' => $costPrice,
                'opening_stock' => $openingStock,
                'closing_stock' => $closingStock,
                'stock_change' => $closingStock - $openingStock,
                'opening_units' => $openingUnits,
                'closing_units' => $closingUnits,
                'unit_change' => $closingUnits - $openingUnits,
                'opening_value' => (float)($open['stock_value'] ?? 0),
                'closing_value' => (float)($close['stock_value'] ?? 0),
                'value_change' => (float)($close['stock_value'] ?? 0) - (float)($open['stock_value'] ?? 0),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => abs($b['stock_change']) <=> abs($a['stock_change']));

        $openingTotals = $opening['totals'] ?? [];
        $closingTotals = $closing['totals'] ?? [];

        return [
            'report_date' => $date,
            'warehouse' => $warehouseLabel,
            'opening_captured_at' => $meta['opening_captured_at'] ?? ($opening['captured_at'] ?? null),
            'closing_captured_at' => $meta['closing_captured_at'] ?? ($closing['captured_at'] ?? null),
            'closing_status' => $meta['closing_status'] ?? 'Live Estimate',
            'created_by' => $meta['created_by'] ?? 'System',
            'totals' => [
                'opening_skus' => (int)($openingTotals['sku_count'] ?? 0),
                'closing_skus' => (int)($closingTotals['sku_count'] ?? 0),
                'opening_crates' => (int)($openingTotals['stock_crates'] ?? 0),
                'closing_crates' => (int)($closingTotals['stock_crates'] ?? 0),
                'crate_change' => (int)($closingTotals['stock_crates'] ?? 0) - (int)($openingTotals['stock_crates'] ?? 0),
                'opening_units' => (int)($openingTotals['stock_units'] ?? 0),
                'closing_units' => (int)($closingTotals['stock_units'] ?? 0),
                'unit_change' => (int)($closingTotals['stock_units'] ?? 0) - (int)($openingTotals['stock_units'] ?? 0),
                'opening_value' => (float)($openingTotals['stock_value'] ?? 0),
                'closing_value' => (float)($closingTotals['stock_value'] ?? 0),
                'value_change' => (float)($closingTotals['stock_value'] ?? 0) - (float)($openingTotals['stock_value'] ?? 0),
            ],
            'rows' => $rows,
        ];
    }

    private static function loadDailyStockRecord(string $date, string $warehouseLabel): ?array
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if ($pdo) {
            try {
                self::ensureDailyStockReportsTable($pdo);
                $stmt = $pdo->prepare("SELECT * FROM daily_stock_reports WHERE company_id = 'beverage' AND report_date = ? AND warehouse = ? LIMIT 1");
                $stmt->execute([$date, $warehouseLabel]);
                $row = $stmt->fetch();
                if ($row) {
                    return [
                        'report_date' => $row['report_date'],
                        'warehouse' => $row['warehouse'],
                        'opening_snapshot' => json_decode((string)$row['opening_snapshot'], true) ?: null,
                        'closing_snapshot' => json_decode((string)($row['closing_snapshot'] ?? ''), true) ?: null,
                        'opening_captured_at' => $row['opening_captured_at'] ?? null,
                        'closing_captured_at' => $row['closing_captured_at'] ?? null,
                        'created_by' => $row['created_by'] ?? 'System',
                    ];
                }
            } catch (\Throwable $t) {
            }
        }

        $key = self::dailyStockReportKey($date, $warehouseLabel);
        return $_SESSION['daily_stock_reports'][$key] ?? null;
    }

    private static function saveDailyStockRecord(array $record): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $date = self::normalizeReportDate($record['report_date'] ?? null);
        $warehouseLabel = self::normalizeReportWarehouse($record['warehouse'] ?? null);
        $record['report_date'] = $date;
        $record['warehouse'] = $warehouseLabel;
        $_SESSION['daily_stock_reports'][self::dailyStockReportKey($date, $warehouseLabel)] = $record;

        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return;
        }

        try {
            self::ensureDailyStockReportsTable($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO daily_stock_reports
                    (company_id, report_date, warehouse, opening_snapshot, closing_snapshot, opening_captured_at, closing_captured_at, created_by, updated_at)
                VALUES
                    ('beverage', ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    opening_snapshot = VALUES(opening_snapshot),
                    closing_snapshot = VALUES(closing_snapshot),
                    opening_captured_at = VALUES(opening_captured_at),
                    closing_captured_at = VALUES(closing_captured_at),
                    created_by = VALUES(created_by),
                    updated_at = NOW()
            ");
            $stmt->execute([
                $date,
                $warehouseLabel,
                json_encode($record['opening_snapshot'] ?? [], JSON_UNESCAPED_SLASHES),
                !empty($record['closing_snapshot']) ? json_encode($record['closing_snapshot'], JSON_UNESCAPED_SLASHES) : null,
                $record['opening_captured_at'] ?? null,
                $record['closing_captured_at'] ?? null,
                $record['created_by'] ?? 'System',
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function ensureDailyStockReportsTable(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS daily_stock_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id VARCHAR(64) NOT NULL DEFAULT 'beverage',
                report_date DATE NOT NULL,
                warehouse VARCHAR(120) NOT NULL,
                opening_snapshot LONGTEXT NOT NULL,
                closing_snapshot LONGTEXT NULL,
                opening_captured_at DATETIME NULL,
                closing_captured_at DATETIME NULL,
                created_by VARCHAR(160) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_daily_stock_report (company_id, report_date, warehouse)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private static function dailyStockReportKey(string $date, string $warehouseLabel): string
    {
        return $date . '|' . strtolower($warehouseLabel);
    }

    public static function capturePhysicalStockCount(array $data, string $userName = 'Warehouse Admin'): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $warehouse = self::normalizeWarehouse($data['count_warehouse'] ?? null);
        $countDate = self::normalizeReportDate($data['count_date'] ?? null);
        $countName = trim((string)($data['count_name'] ?? ''));
        $countedBy = trim((string)($data['counted_by'] ?? $userName));
        $notes = trim((string)($data['notes'] ?? ''));
        $physicalCounts = is_array($data['physical_counts'] ?? null) ? $data['physical_counts'] : [];
        $damagedCounts = is_array($data['damaged_counts'] ?? null) ? $data['damaged_counts'] : [];

        if ($physicalCounts === [] && $damagedCounts === []) {
            return [
                'success' => false,
                'message' => 'Enter at least one physical or damaged count before saving.',
            ];
        }

        $products = self::getProductsForWarehouse($warehouse, true);
        $productMap = [];
        foreach ($products as $product) {
            $sku = strtoupper(trim((string)($product['sku'] ?? '')));
            if ($sku !== '') {
                $productMap[$sku] = $product;
            }
        }

        $rows = [];
        $totals = [
            'total_skus' => 0,
            'system_crates' => 0,
            'counted_crates' => 0,
            'damaged_crates' => 0,
            'total_physical_crates' => 0,
            'variance_crates' => 0,
            'variance_value' => 0.0,
            'damaged_value' => 0.0,
        ];

        $countSkus = array_values(array_unique(array_merge(array_keys($physicalCounts), array_keys($damagedCounts))));
        foreach ($countSkus as $sku) {
            $sku = strtoupper(trim((string)$sku));
            if ($sku === '' || !isset($productMap[$sku])) {
                continue;
            }

            $rawCount = trim((string)($physicalCounts[$sku] ?? ''));
            $rawDamaged = trim((string)($damagedCounts[$sku] ?? ''));
            if ($rawCount === '' && $rawDamaged === '') {
                continue;
            }

            $countedCrates = max(0, (int)str_replace(',', '', $rawCount));
            $damagedCrates = max(0, (int)str_replace(',', '', $rawDamaged));
            $totalPhysicalCrates = $countedCrates + $damagedCrates;
            $product = $productMap[$sku];
            $systemCrates = (int)($product['stock_crates'] ?? 0);
            $varianceCrates = $totalPhysicalCrates - $systemCrates;
            $packSize = max(1, (int)($product['units_per_crate'] ?? 1));
            $costPrice = (float)($product['cost_price'] ?? $product['wholesale_price'] ?? 0);
            $varianceValue = $varianceCrates * $costPrice;
            $damagedValue = $damagedCrates * $costPrice;

            $rows[] = [
                'sku' => $sku,
                'name' => (string)($product['name'] ?? $sku),
                'category' => (string)($product['category'] ?? ''),
                'warehouse' => $warehouse,
                'pack_size' => $packSize,
                'unit_label' => (string)($product['unit'] ?? $product['packaging'] ?? 'Pack/Crate'),
                'cost_price' => $costPrice,
                'system_crates' => $systemCrates,
                'counted_crates' => $countedCrates,
                'damaged_crates' => $damagedCrates,
                'total_physical_crates' => $totalPhysicalCrates,
                'variance_crates' => $varianceCrates,
                'variance_units' => $varianceCrates * $packSize,
                'variance_value' => $varianceValue,
                'damaged_value' => $damagedValue,
            ];

            $totals['total_skus']++;
            $totals['system_crates'] += $systemCrates;
            $totals['counted_crates'] += $countedCrates;
            $totals['damaged_crates'] += $damagedCrates;
            $totals['total_physical_crates'] += $totalPhysicalCrates;
            $totals['variance_crates'] += $varianceCrates;
            $totals['variance_value'] += $varianceValue;
            $totals['damaged_value'] += $damagedValue;
        }

        if ($rows === []) {
            return [
                'success' => false,
                'message' => 'No valid product counts were found. Please enter counts beside the products.',
            ];
        }

        usort($rows, static fn(array $a, array $b): int => abs($b['variance_crates']) <=> abs($a['variance_crates']));

        $countId = 'PHY-' . date('Ymd-His') . '-' . random_int(100, 999);
        $record = [
            'count_id' => $countId,
            'count_name' => $countName !== '' ? $countName : 'Physical Count ' . date('d M Y'),
            'warehouse' => $warehouse,
            'count_date' => $countDate,
            'counted_by' => $countedBy !== '' ? $countedBy : $userName,
            'status' => 'Pending Approval',
            'posting_status' => ((int)$totals['variance_crates'] === 0) ? 'Balanced Pending' : 'Variance Pending',
            'notes' => $notes,
            'totals' => $totals,
            'rows' => $rows,
            'created_by' => $userName,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $_SESSION['physical_stock_counts'][$countId] = $record;
        self::persistPhysicalStockCount($record);
        self::logMovement([
            'user' => $userName,
            'action' => 'Physical Count Captured',
            'item' => $record['count_name'],
            'quantity' => number_format((int)$totals['total_skus']) . ' SKUs counted',
            'previous_balance' => number_format((int)$totals['system_crates']) . ' System Crates/Packs',
            'new_balance' => number_format((int)$totals['total_physical_crates']) . ' Physical Crates/Packs (' . number_format((int)$totals['damaged_crates']) . ' damaged)',
            'location' => $warehouse,
            'reference' => $countId,
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return [
            'success' => true,
            'message' => "Physical count saved for {$warehouse}. Variance: " . number_format((int)$totals['variance_crates']) . ' crates/packs.',
            'count' => $record,
        ];
    }

    public static function getPhysicalStockCounts(int $limit = 20): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $dbCounts = self::loadPhysicalStockCountsFromDatabase($limit);
        if ($dbCounts !== []) {
            $_SESSION['physical_stock_counts'] = [];
            foreach ($dbCounts as $record) {
                $_SESSION['physical_stock_counts'][(string)$record['count_id']] = $record;
            }
            return $dbCounts;
        }

        $counts = array_values($_SESSION['physical_stock_counts'] ?? []);
        usort($counts, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

        return array_slice($counts, 0, max(1, $limit));
    }

    public static function approvePhysicalStockCount(string $countId, string $userName = 'Warehouse Admin'): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $countId = trim($countId);
        $record = self::findPhysicalStockCount($countId);
        if (!$record) {
            return ['success' => false, 'message' => 'Physical count record was not found.'];
        }

        if (($record['status'] ?? '') === 'Approved & Posted') {
            return ['success' => false, 'message' => 'This physical count has already been approved and posted.'];
        }

        $warehouse = self::normalizeWarehouse($record['warehouse'] ?? null);
        $rows = is_array($record['rows'] ?? null) ? $record['rows'] : [];
        $postedRows = [];
        $postedTotals = [
            'system_before' => 0,
            'good_stock_after' => 0,
            'damaged_crates' => 0,
            'confirmed_sold_or_missing' => 0,
            'positive_adjustment' => 0,
        ];

        foreach ($rows as $row) {
            $sku = strtoupper(trim((string)($row['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $systemCrates = max(0, (int)($row['system_crates'] ?? 0));
            $goodCrates = max(0, (int)($row['counted_crates'] ?? 0));
            $damagedCrates = max(0, (int)($row['damaged_crates'] ?? 0));
            $physicalTotal = $goodCrates + $damagedCrates;
            $confirmedSoldOrMissing = max(0, $systemCrates - $physicalTotal);
            $positiveAdjustment = max(0, $physicalTotal - $systemCrates);
            $totalStockAfter = self::setProductWarehouseStock($sku, $warehouse, $goodCrates);
            self::syncProductTotalStockAfterWarehousePost($sku, $totalStockAfter);

            $postedRows[] = array_merge($row, [
                'stock_after_approval' => $goodCrates,
                'confirmed_sold_or_missing' => $confirmedSoldOrMissing,
                'positive_adjustment' => $positiveAdjustment,
                'posted_at' => date('Y-m-d H:i:s'),
            ]);

            $postedTotals['system_before'] += $systemCrates;
            $postedTotals['good_stock_after'] += $goodCrates;
            $postedTotals['damaged_crates'] += $damagedCrates;
            $postedTotals['confirmed_sold_or_missing'] += $confirmedSoldOrMissing;
            $postedTotals['positive_adjustment'] += $positiveAdjustment;

            self::logMovement([
                'user' => $userName,
                'action' => 'Physical Count Approved',
                'item' => (string)($row['name'] ?? $sku),
                'sku' => $sku,
                'quantity' => number_format($goodCrates) . ' Good, ' . number_format($damagedCrates) . ' Damaged',
                'previous_balance' => number_format($systemCrates) . ' System Crates/Packs',
                'new_balance' => number_format($goodCrates) . ' Available Crates/Packs',
                'location' => $warehouse,
                'reference' => $countId,
                'timestamp' => date('d M Y, h:i A'),
            ]);

            if ($confirmedSoldOrMissing > 0) {
                self::logMovement([
                    'user' => $userName,
                    'action' => 'Confirmed Sold/Missing From Physical Count',
                    'item' => (string)($row['name'] ?? $sku),
                    'sku' => $sku,
                    'quantity' => number_format($confirmedSoldOrMissing) . ' Crates/Packs',
                    'previous_balance' => number_format($systemCrates) . ' System Crates/Packs',
                    'new_balance' => number_format($goodCrates) . ' Available Crates/Packs',
                    'location' => $warehouse,
                    'reference' => $countId . '-SHORT-' . $sku,
                    'timestamp' => date('d M Y, h:i A'),
                ]);
            }
        }

        $record['rows'] = $postedRows;
        $record['status'] = 'Approved & Posted';
        $record['posting_status'] = 'Stock Updated';
        $record['approved_by'] = $userName;
        $record['approved_at'] = date('Y-m-d H:i:s');
        $record['posted_totals'] = $postedTotals;
        $_SESSION['physical_stock_counts'][$countId] = $record;
        self::persistPhysicalStockCount($record);

        return [
            'success' => true,
            'message' => 'Physical count approved. Available stock now reflects the confirmed good quantity. Confirmed sold/missing: ' . number_format((int)$postedTotals['confirmed_sold_or_missing']) . ' crates/packs.',
            'count' => $record,
        ];
    }

    private static function findPhysicalStockCount(string $countId): ?array
    {
        foreach (self::getPhysicalStockCounts(200) as $record) {
            if ((string)($record['count_id'] ?? '') === $countId) {
                return $record;
            }
        }

        return $_SESSION['physical_stock_counts'][$countId] ?? null;
    }

    private static function syncProductTotalStockAfterWarehousePost(string $sku, int $totalStockCrates): void
    {
        $products = self::getProducts();
        foreach ($products as $idx => $product) {
            if (strcasecmp((string)($product['sku'] ?? ''), $sku) !== 0) {
                continue;
            }

            $unitsPerCrate = max(1, (int)($product['units_per_crate'] ?? 1));
            $products[$idx]['stock_crates'] = max(0, $totalStockCrates);
            $products[$idx]['stock_bottles'] = max(0, $totalStockCrates) * $unitsPerCrate;
            $_SESSION['beverage_products'] = $products;

            if (class_exists('App\Core\UnifiedDataEngine')) {
                \App\Core\UnifiedDataEngine::saveProduct($products[$idx]);
                \App\Core\UnifiedDataEngine::syncProductMaster($products[$idx], 'updated', 'beverage');
            }
            return;
        }
    }

    private static function persistPhysicalStockCount(array $record): void
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return;
        }

        try {
            self::ensurePhysicalStockCountsTable($pdo);
            $totals = $record['totals'] ?? [];
            $stmt = $pdo->prepare(
                'INSERT INTO physical_stock_counts
                    (count_id, company_id, count_name, warehouse, count_date, counted_by, total_skus, system_crates, counted_crates, variance_crates, variance_value, status, notes, count_json, created_by, created_at)
                 VALUES
                    (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE count_json = VALUES(count_json)'
            );
            $stmt->execute([
                (string)$record['count_id'],
                (string)$record['count_name'],
                (string)$record['warehouse'],
                (string)$record['count_date'],
                (string)$record['counted_by'],
                (int)($totals['total_skus'] ?? 0),
                (int)($totals['system_crates'] ?? 0),
                (int)($totals['counted_crates'] ?? 0),
                (int)($totals['variance_crates'] ?? 0),
                (float)($totals['variance_value'] ?? 0),
                (string)$record['status'],
                (string)$record['notes'],
                json_encode($record, JSON_UNESCAPED_SLASHES),
                (string)($record['created_by'] ?? ''),
                (string)($record['created_at'] ?? date('Y-m-d H:i:s')),
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function loadPhysicalStockCountsFromDatabase(int $limit = 20): array
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return [];
        }

        try {
            self::ensurePhysicalStockCountsTable($pdo);
            $limit = max(1, min(100, $limit));
            $stmt = $pdo->query(
                "SELECT count_json FROM physical_stock_counts WHERE company_id = 'beverage' ORDER BY created_at DESC, id DESC LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];

            return array_values(array_filter(array_map(static function (array $row): ?array {
                $record = json_decode((string)($row['count_json'] ?? ''), true);
                return is_array($record) ? $record : null;
            }, $rows)));
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function ensurePhysicalStockCountsTable(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS physical_stock_counts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                count_id VARCHAR(80) NOT NULL UNIQUE,
                company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                count_name VARCHAR(180) NOT NULL,
                warehouse VARCHAR(160) NOT NULL,
                count_date DATE NOT NULL,
                counted_by VARCHAR(160) DEFAULT NULL,
                total_skus INT NOT NULL DEFAULT 0,
                system_crates INT NOT NULL DEFAULT 0,
                counted_crates INT NOT NULL DEFAULT 0,
                variance_crates INT NOT NULL DEFAULT 0,
                variance_value DECIMAL(14,2) NOT NULL DEFAULT 0,
                status VARCHAR(60) NOT NULL DEFAULT "Draft",
                notes TEXT DEFAULT NULL,
                count_json LONGTEXT NOT NULL,
                created_by VARCHAR(160) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_physical_counts_company_date (company_id, count_date),
                INDEX idx_physical_counts_warehouse (company_id, warehouse)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Log Audit Trail Movement
     */
    public static function logMovement(array $entry): void
    {
        if (empty($entry)) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!isset($_SESSION['inventory_audit_trail'])) {
            $_SESSION['inventory_audit_trail'] = [];
        }

        $entry['id'] = $entry['id'] ?? ('MOV-' . date('Ymd-His') . '-' . random_int(100, 999));
        $entry['reference'] = $entry['reference'] ?? $entry['id'];
        $entry['timestamp'] = $entry['timestamp'] ?? date('d M Y, h:i A');
        foreach ($_SESSION['inventory_audit_trail'] as $existing) {
            if (
                !empty($entry['reference'])
                && (string)($existing['reference'] ?? '') === (string)$entry['reference']
                && (string)($existing['action'] ?? '') === (string)($entry['action'] ?? '')
            ) {
                return;
            }
        }
        array_unshift($_SESSION['inventory_audit_trail'], $entry);
        self::persistInventoryMovement($entry);
    }

    /**
     * Get Audit Trail
     */
    public static function getAuditTrail(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $dbTrail = self::loadInventoryMovementsFromDatabase();
        if ($dbTrail !== []) {
            $_SESSION['inventory_audit_trail'] = $dbTrail;
            return $dbTrail;
        }

        return $_SESSION['inventory_audit_trail'] ?? [];
    }

    private static function persistInventoryMovement(array $entry): void
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return;
        }

        try {
            self::ensureInventoryMovementsTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO inventory_movements
                    (movement_id, company_id, reference, user_name, action, item_name, sku, quantity_label, previous_balance, new_balance, location, metadata_json, created_at)
                 VALUES
                    (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE metadata_json = VALUES(metadata_json)'
            );
            $createdAt = self::movementTimestampToSql((string)($entry['timestamp'] ?? ''));
            $stmt->execute([
                (string)($entry['id'] ?? $entry['reference'] ?? uniqid('MOV-', true)),
                (string)($entry['reference'] ?? $entry['id'] ?? ''),
                (string)($entry['user'] ?? ''),
                (string)($entry['action'] ?? ''),
                (string)($entry['item'] ?? ''),
                strtoupper(trim((string)($entry['sku'] ?? ''))),
                (string)($entry['quantity'] ?? ''),
                (string)($entry['previous_balance'] ?? ''),
                (string)($entry['new_balance'] ?? ''),
                (string)($entry['location'] ?? ''),
                json_encode($entry, JSON_UNESCAPED_SLASHES),
                $createdAt,
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function loadInventoryMovementsFromDatabase(int $limit = 300): array
    {
        $pdo = class_exists('App\Core\Database') ? \App\Core\Database::getConnection() : null;
        if (!$pdo) {
            return [];
        }

        try {
            self::ensureInventoryMovementsTable($pdo);
            $limit = max(1, min(1000, $limit));
            $stmt = $pdo->query(
                "SELECT movement_id, reference, user_name, action, item_name, sku, quantity_label, previous_balance, new_balance, location, metadata_json, created_at
                 FROM inventory_movements
                 WHERE company_id = 'beverage'
                 ORDER BY created_at DESC, id DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];

            return array_map(static function (array $row): array {
                $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
                $entry = is_array($metadata) ? $metadata : [];
                $createdAt = (string)($row['created_at'] ?? '');
                $entry['id'] = (string)($row['movement_id'] ?? $entry['id'] ?? '');
                $entry['reference'] = (string)($row['reference'] ?? $entry['reference'] ?? '');
                $entry['user'] = (string)($row['user_name'] ?? $entry['user'] ?? '');
                $entry['action'] = (string)($row['action'] ?? $entry['action'] ?? '');
                $entry['item'] = (string)($row['item_name'] ?? $entry['item'] ?? '');
                $entry['sku'] = (string)($row['sku'] ?? $entry['sku'] ?? '');
                $entry['quantity'] = (string)($row['quantity_label'] ?? $entry['quantity'] ?? '');
                $entry['previous_balance'] = (string)($row['previous_balance'] ?? $entry['previous_balance'] ?? '');
                $entry['new_balance'] = (string)($row['new_balance'] ?? $entry['new_balance'] ?? '');
                $entry['location'] = (string)($row['location'] ?? $entry['location'] ?? '');
                $entry['timestamp'] = $createdAt !== '' ? date('d M Y, h:i A', strtotime($createdAt)) : (string)($entry['timestamp'] ?? '');

                return $entry;
            }, $rows);
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function ensureInventoryMovementsTable(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS inventory_movements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                movement_id VARCHAR(80) NOT NULL UNIQUE,
                company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                reference VARCHAR(120) DEFAULT NULL,
                user_name VARCHAR(160) DEFAULT NULL,
                action VARCHAR(160) NOT NULL,
                item_name VARCHAR(180) DEFAULT NULL,
                sku VARCHAR(100) DEFAULT NULL,
                quantity_label VARCHAR(120) DEFAULT NULL,
                previous_balance VARCHAR(180) DEFAULT NULL,
                new_balance VARCHAR(180) DEFAULT NULL,
                location VARCHAR(180) DEFAULT NULL,
                metadata_json LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_inventory_movements_company_date (company_id, created_at),
                INDEX idx_inventory_movements_sku (company_id, sku)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private static function movementTimestampToSql(string $timestamp): string
    {
        $time = strtotime($timestamp);
        return $time ? date('Y-m-d H:i:s', $time) : date('Y-m-d H:i:s');
    }

    /**
     * Export all current inventory products to Excel/CSV
     */
    public static function exportProductsCsv(): void
    {
        $products = self::getProducts();
        $filename = 'Jacroxx_Inventory_Export_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Header row
        fputcsv($out, [
            'SKU',
            'Product Name',
            'Category',
            'Packaging UOM',
            'Available Stock (Crates)',
            'Units Per Crate',
            'Wholesale Price (NGN)',
            'Crate Deposit Value (NGN)',
            'FEFO Expiry Date (YYYY-MM-DD)',
            'Image URL / Path',
            'Warehouse Location',
        ]);

        foreach ($products as $p) {
            $stockCrates = (int)($p['stock_crates'] ?? 0);
            $unitsPerCrate = (int)($p['units_per_crate'] ?? 24);
            $wholesalePrice = (float)($p['wholesale_price'] ?? $p['cost_price'] ?? 0);
            $crateDeposit = (float)($p['crate_deposit'] ?? 0);
            $expiryDate = trim((string)($p['expiry_date'] ?? ''));

            fputcsv($out, [
                $p['sku'] ?? '',
                $p['name'] ?? '',
                $p['category'] ?? 'Soft Drinks',
                $p['packaging'] ?? 'Crate of 24',
                $stockCrates,
                $unitsPerCrate,
                number_format($wholesalePrice, 2, '.', ''),
                number_format($crateDeposit, 2, '.', ''),
                $expiryDate,
                $p['image'] ?? '',
                'Jacroxx Warehouse',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * Export a blank/sample Excel template for inventory updates
     */
    public static function exportSampleTemplateCsv(): void
    {
        $filename = 'Jacroxx_Inventory_Import_Template.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Header row
        fputcsv($out, [
            'SKU',
            'Product Name',
            'Category',
            'Packaging UOM',
            'Available Stock (Crates)',
            'Units Per Crate',
            'Wholesale Price (NGN)',
            'Crate Deposit Value (NGN)',
            'FEFO Expiry Date (YYYY-MM-DD)',
            'Image URL / Path',
            'Warehouse Location',
        ]);

        // Sample instruction rows
        $samples = [
            [
                'COKE-50CL-RGB',
                'Coca-Cola 50cl Glass Bottle',
                'Soft Drinks',
                'Crate of 24',
                '450',
                '24',
                '5200.00',
                '300.00',
                date('Y-m-d', strtotime('+90 days')),
                'assets/images/coca_cola_50cl_crate.png',
                'Jacroxx Warehouse',
            ],
            [
                'EVA-75CL-PET',
                'Eva Premium Water 75cl',
                'Water',
                'Pack of 12',
                '320',
                '12',
                '2800.00',
                '0.00',
                date('Y-m-d', strtotime('+180 days')),
                'assets/images/eva_pack.svg',
                'Jacroxx Warehouse',
            ],
            [
                'MAL-33CL-CAN',
                'Maltina Nourishing Malt 33cl Can',
                'Malt',
                'Case of 24 Cans',
                '180',
                '24',
                '6400.00',
                '0.00',
                '2026-11-30',
                'assets/images/maltina_crate.svg',
                'Jacroxx Warehouse',
            ],
        ];

        foreach ($samples as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    /**
     * Import products from uploaded CSV / Excel file
     */
    public static function importProductsCsv(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [
                'success' => false,
                'message' => 'Uploaded file could not be read.',
                'updated' => 0,
                'added' => 0,
                'total_processed' => 0,
                'errors' => ['File unreadable'],
            ];
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false || trim($fileContent) === '') {
            return [
                'success' => false,
                'message' => 'The uploaded file is empty.',
                'updated' => 0,
                'added' => 0,
                'total_processed' => 0,
                'errors' => ['Empty file'],
            ];
        }

        // Remove UTF-8 BOM if present
        if (str_starts_with($fileContent, "\xEF\xBB\xBF")) {
            $fileContent = substr($fileContent, 3);
        }

        // Auto-detect delimiter
        $firstLine = strtok($fileContent, "\r\n") ?: '';
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $lines = preg_split("/\r\n|\n|\r/", $fileContent);
        if (empty($lines)) {
            return [
                'success' => false,
                'message' => 'No readable lines in the uploaded file.',
                'updated' => 0,
                'added' => 0,
                'total_processed' => 0,
                'errors' => ['No lines'],
            ];
        }

        $headerRow = str_getcsv(array_shift($lines), $delimiter, '"', '\\');
        if (empty($headerRow)) {
            return [
                'success' => false,
                'message' => 'Header row missing in CSV file.',
                'updated' => 0,
                'added' => 0,
                'total_processed' => 0,
                'errors' => ['Missing header'],
            ];
        }

        // Normalize header columns
        $colMap = [];
        foreach ($headerRow as $idx => $headerName) {
            $clean = strtolower(trim((string)$headerName));
            $clean = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-', '(', ')', '/'], '_', $clean));

            if (str_contains($clean, 'sku')) {
                $colMap['sku'] = $idx;
            } elseif (str_contains($clean, 'name') || str_contains($clean, 'product') || str_contains($clean, 'desc') || str_contains($clean, 'item')) {
                if (!isset($colMap['name'])) $colMap['name'] = $idx;
            } elseif (str_contains($clean, 'cat')) {
                $colMap['category'] = $idx;
            } elseif (str_contains($clean, 'pack') || str_contains($clean, 'uom')) {
                $colMap['packaging'] = $idx;
            } elseif (str_contains($clean, 'stock') || str_contains($clean, 'qty') || str_contains($clean, 'quantity') || str_contains($clean, 'crates')) {
                if (!isset($colMap['stock_crates'])) $colMap['stock_crates'] = $idx;
            } elseif (str_contains($clean, 'units_per') || str_contains($clean, 'unit_per') || str_contains($clean, 'bottles_per')) {
                $colMap['units_per_crate'] = $idx;
            } elseif (str_contains($clean, 'wholesale') || str_contains($clean, 'cost') || str_contains($clean, 'price')) {
                if (!isset($colMap['wholesale_price'])) $colMap['wholesale_price'] = $idx;
            } elseif (str_contains($clean, 'deposit')) {
                $colMap['crate_deposit'] = $idx;
            } elseif (str_contains($clean, 'expiry') || str_contains($clean, 'date') || str_contains($clean, 'fefo')) {
                $colMap['expiry_date'] = $idx;
            } elseif (str_contains($clean, 'image') || str_contains($clean, 'photo') || str_contains($clean, 'url')) {
                $colMap['image'] = $idx;
            }
        }

        if (!isset($colMap['sku']) && !isset($colMap['name'])) {
            return [
                'success' => false,
                'message' => 'Could not detect required columns (SKU or Product Name). Please use the downloadable template.',
                'updated' => 0,
                'added' => 0,
                'total_processed' => 0,
                'errors' => ['Missing SKU / Product Name column'],
            ];
        }

        $existingProducts = self::getProducts();
        $productsBySku = [];
        foreach ($existingProducts as $idx => $p) {
            $skuKey = strtoupper(trim((string)($p['sku'] ?? '')));
            if ($skuKey !== '') {
                $productsBySku[$skuKey] = $idx;
            }
        }

        $updatedCount = 0;
        $addedCount = 0;
        $errors = [];
        $lineNo = 1;

        foreach ($lines as $lineStr) {
            $lineNo++;
            $lineStr = trim($lineStr);
            if ($lineStr === '') {
                continue;
            }

            $row = str_getcsv($lineStr, $delimiter, '"', '\\');
            if (empty($row) || count(array_filter($row)) === 0) {
                continue;
            }

            $sku = isset($colMap['sku']) ? strtoupper(trim((string)($row[$colMap['sku']] ?? ''))) : '';
            $name = isset($colMap['name']) ? trim((string)($row[$colMap['name']] ?? '')) : '';

            if ($sku === '' && $name === '') {
                continue;
            }

            if ($sku === '') {
                $sku = 'BEV-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $name), 0, 6)) . '-' . rand(100, 999);
            }

            $category = isset($colMap['category']) ? trim((string)($row[$colMap['category']] ?? 'Soft Drinks')) : 'Soft Drinks';
            $packaging = isset($colMap['packaging']) ? trim((string)($row[$colMap['packaging']] ?? 'Crate of 24')) : 'Crate of 24';
            
            // Clean number fields
            $rawStock = isset($colMap['stock_crates']) ? trim((string)($row[$colMap['stock_crates']] ?? '0')) : '0';
            $stockCrates = max(0, (int)preg_replace('/[^0-9]/', '', $rawStock));

            $rawUnits = isset($colMap['units_per_crate']) ? trim((string)($row[$colMap['units_per_crate']] ?? '24')) : '24';
            $unitsPerCrate = max(1, (int)preg_replace('/[^0-9]/', '', $rawUnits));

            $rawPrice = isset($colMap['wholesale_price']) ? trim((string)($row[$colMap['wholesale_price']] ?? '0')) : '0';
            $wholesalePrice = max(0.0, (float)preg_replace('/[^0-9.]/', '', $rawPrice));

            $rawDeposit = isset($colMap['crate_deposit']) ? trim((string)($row[$colMap['crate_deposit']] ?? '0')) : '0';
            $crateDeposit = max(0.0, (float)preg_replace('/[^0-9.]/', '', $rawDeposit));

            $expiryDate = isset($colMap['expiry_date']) ? trim((string)($row[$colMap['expiry_date']] ?? '')) : '';
            if ($expiryDate !== '' && strtotime($expiryDate) !== false) {
                $expiryDate = date('Y-m-d', strtotime($expiryDate));
            } else {
                $expiryDate = '';
            }

            $image = isset($colMap['image']) ? trim((string)($row[$colMap['image']] ?? '')) : '';

            if (isset($productsBySku[$sku])) {
                // Update existing product
                $targetIndex = $productsBySku[$sku];
                $existing = $existingProducts[$targetIndex];

                $updated = [
                    'id' => $existing['id'] ?? ($targetIndex + 1),
                    'sku' => $sku,
                    'name' => $name !== '' ? $name : ($existing['name'] ?? $sku),
                    'category' => $category !== '' ? $category : ($existing['category'] ?? 'Soft Drinks'),
                    'packaging' => $packaging !== '' ? $packaging : ($existing['packaging'] ?? 'Crate of 24'),
                    'units_per_crate' => $unitsPerCrate ?: ($existing['units_per_crate'] ?? 24),
                    'wholesale_price' => $wholesalePrice > 0 ? $wholesalePrice : (float)($existing['wholesale_price'] ?? 0),
                    'crate_deposit' => $crateDeposit >= 0 ? $crateDeposit : (float)($existing['crate_deposit'] ?? 0),
                    'stock_crates' => $stockCrates,
                    'stock_bottles' => $stockCrates * ($unitsPerCrate ?: 24),
                    'expiry_date' => $expiryDate !== '' ? $expiryDate : ($existing['expiry_date'] ?? ''),
                    'image' => $image !== '' ? $image : ($existing['image'] ?? ''),
                ];

                $existingProducts[$targetIndex] = $updated;
                $updatedCount++;

                if (class_exists('App\Core\UnifiedDataEngine')) {
                    \App\Core\UnifiedDataEngine::saveProduct($updated);
                    \App\Core\UnifiedDataEngine::syncProductMaster($updated, 'updated', 'beverage');
                }
            } else {
                // Insert new product
                $newIndex = count($existingProducts);
                $newProduct = [
                    'id' => $newIndex + 1,
                    'sku' => $sku,
                    'name' => $name !== '' ? $name : 'Product ' . $sku,
                    'category' => $category ?: 'Soft Drinks',
                    'packaging' => $packaging ?: 'Crate of 24',
                    'units_per_crate' => $unitsPerCrate ?: 24,
                    'wholesale_price' => $wholesalePrice,
                    'crate_deposit' => $crateDeposit,
                    'stock_crates' => $stockCrates,
                    'stock_bottles' => $stockCrates * ($unitsPerCrate ?: 24),
                    'expiry_date' => $expiryDate,
                    'image' => $image,
                ];

                $existingProducts[] = $newProduct;
                $productsBySku[$sku] = $newIndex;
                $addedCount++;

                if (class_exists('App\Core\UnifiedDataEngine')) {
                    \App\Core\UnifiedDataEngine::saveProduct($newProduct);
                    \App\Core\UnifiedDataEngine::syncProductMaster($newProduct, 'created', 'beverage');
                }
            }
        }

        $_SESSION['beverage_products'] = $existingProducts;

        $totalProcessed = $updatedCount + $addedCount;

        // Log audit trail event
        self::logMovement([
            'user' => current_user()['name'] ?? 'Warehouse Manager',
            'action' => 'Bulk Inventory Updated via Excel Import',
            'item' => "Excel Batch ({$totalProcessed} items)",
            'quantity' => "{$updatedCount} updated, {$addedCount} new",
            'previous_balance' => 'Excel Upload',
            'new_balance' => 'Synced Master',
            'location' => 'Jacroxx Warehouse',
            'timestamp' => date('d M Y, h:i A'),
        ]);

        return [
            'success' => true,
            'message' => "Successfully processed Excel inventory file: {$updatedCount} items updated, {$addedCount} new items created.",
            'updated' => $updatedCount,
            'added' => $addedCount,
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }
}
