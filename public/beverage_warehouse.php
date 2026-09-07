<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Modules/BeverageWarehouse/BeverageWarehouseService.php';
require_once dirname(__DIR__) . '/app/Modules/BeverageWarehouse/TruckLoadService.php';
require_once dirname(__DIR__) . '/app/Modules/BeverageWarehouse/ProcurementReceivingService.php';
require_once dirname(__DIR__) . '/app/Modules/BeverageWarehouse/VehicleReconciliationService.php';
require_once dirname(__DIR__) . '/app/Modules/BeverageWarehouse/FinanceGlMapperService.php';
require_once dirname(__DIR__) . '/app/Modules/BeverageWarehouse/AiWarehouseAssistantService.php';
require_once dirname(__DIR__) . '/app/Modules/HrPayroll/HrPayrollService.php';
require_once dirname(__DIR__) . '/app/Modules/HrPayroll/GeoAttendanceService.php';
require_once dirname(__DIR__) . '/app/Modules/Maintenance/MaintenanceService.php';
require_once dirname(__DIR__) . '/app/Modules/Procurement/ProcurementService.php';
require_once dirname(__DIR__) . '/app/Modules/PaymentRecon/PaymentReconService.php';
require_once dirname(__DIR__) . '/app/Modules/AiCore/CentralAiService.php';
require_once dirname(__DIR__) . '/app/Modules/AiCore/SystemHealthService.php';

use App\Modules\BeverageWarehouse\BeverageWarehouseService;
use App\Modules\BeverageWarehouse\TruckLoadService;
use App\Modules\BeverageWarehouse\ProcurementReceivingService;
use App\Modules\BeverageWarehouse\VehicleReconciliationService;

if (!function_exists('beverage_product_image_src')) {
    function beverage_product_image_src(?string $image): string
    {
        $image = trim((string)$image);
        if ($image === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $image) || str_starts_with($image, 'data:') || str_starts_with($image, '/')) {
            return $image;
        }

        return url($image);
    }
}

if (!function_exists('beverage_product_image_fallback_src')) {
    function beverage_product_image_fallback_src(): string
    {
        return '';
    }
}

$user = current_user();
if (!$user) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    redirect('login.php');
}
if (canonical_role($user['role'] ?? '') === 'pos') {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access restricted to admin and warehouse users.']);
        exit;
    }
    flash('Access restricted: POS cashier accounts cannot access the warehouse admin dashboard.');
    redirect('beverage_pos.php?tab=pos');
}

// Handle AJAX / Download Requests: Excel Export & Template
$requestedAction = $_GET['action'] ?? $_POST['action'] ?? '';
if ($requestedAction === 'export_inventory_excel' || $requestedAction === 'export_inventory_csv') {
    BeverageWarehouseService::exportProductsCsv();
}

if ($requestedAction === 'download_inventory_template') {
    BeverageWarehouseService::exportSampleTemplateCsv();
}

// Handle AJAX Request: Search Online Images
if ($requestedAction === 'search_online_images') {
    header('Content-Type: application/json');
    $query = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
    $results = BeverageWarehouseService::searchOnlineImages($query);
    echo json_encode([
        'success' => true,
        'query' => $query,
        'count' => count($results),
        'google' => BeverageWarehouseService::getGoogleImageSearchStatus(),
        'results' => $results,
    ]);
    exit;
}

// Handle AJAX Request: Quick Update Product Image
if ($requestedAction === 'quick_update_product_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
            exit;
        }

        $sku = trim((string)($_POST['sku'] ?? ''));
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));

        if ($sku === '' || $imageUrl === '') {
            echo json_encode(['success' => false, 'message' => 'SKU and image URL are required.']);
            exit;
        }

        $updatedProduct = BeverageWarehouseService::quickUpdateProductImage($sku, $imageUrl);
        if (!$updatedProduct) {
            echo json_encode(['success' => false, 'message' => "Product with SKU '{$sku}' could not be found."]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => "Product image updated successfully for '{$updatedProduct['name']}'!",
            'product' => $updatedProduct,
            'image_src' => beverage_product_image_src($updatedProduct['image']),
        ]);
    } catch (\Throwable $t) {
        http_response_code(500);
        error_log('Quick product image update failed: ' . $t->getMessage());
        echo json_encode(['success' => false, 'message' => 'Image update failed on the server. Please try again.']);
    }
    exit;
}

$allowedWarehouseTabs = ['tab-dash', 'tab-inventory', 'tab-truck-loads', 'tab-daily-stock', 'tab-physical-count'];
if (!isset($_GET['tab']) || !in_array((string)$_GET['tab'], $allowedWarehouseTabs, true)) {
    $_GET['tab'] = 'tab-dash';
}
$activeTab = (string)$_GET['tab'];
$products = in_array($activeTab, ['tab-dash', 'tab-inventory', 'tab-truck-loads', 'tab-physical-count'], true)
    ? BeverageWarehouseService::getProducts()
    : [];
$aiSupervisorBrief = ['score' => 100, 'status' => 'Stable', 'summary' => 'Stable', 'watch_alerts' => [], 'training_tasks' => []];
if ($activeTab === 'tab-dash' && class_exists('App\Modules\AiCore\AiSupervisorService')) {
    $aiSupervisorBrief = \App\Modules\AiCore\AiSupervisorService::getSupervisorBrief('beverage');
}
$actionMessage = null;
$actionMessageType = 'success';
$grnResult = null;
$reconResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'import_excel_inventory') {
        $hasFile = isset($_FILES['excel_file']) && is_array($_FILES['excel_file']) && (int)($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if (!$hasFile) {
            $actionMessageType = 'error';
            $actionMessage = "⚠️ Please select a valid CSV or Excel spreadsheet file (.csv) to import.";
        } else {
            $tmpPath = (string)($_FILES['excel_file']['tmp_name'] ?? '');
            $importResult = BeverageWarehouseService::importProductsCsv($tmpPath);
            $products = BeverageWarehouseService::getProducts();
            if ($importResult['success']) {
                $actionMessageType = 'success';
                $actionMessage = "📊 " . $importResult['message'];
            } else {
                $actionMessageType = 'error';
                $actionMessage = "⚠️ Import Failed: " . ($importResult['message'] ?? 'Could not parse spreadsheet file.');
            }
        }
    } elseif ($formAction === 'add_product') {
        $addedProduct = BeverageWarehouseService::addProduct($_POST, $_FILES);
        $imageUploadError = BeverageWarehouseService::getLastImageUploadError();
        $products = BeverageWarehouseService::getProducts();
        $actionMessageType = $imageUploadError ? 'warning' : 'success';
        $actionMessage = $imageUploadError
            ? "⚠️ Product saved, but image upload failed: {$imageUploadError}"
            : "✅ New Beverage Product Registered & Synced to POS Catalog! '{$addedProduct['name']}' (SKU: {$addedProduct['sku']}) added with image attachment & stock of {$addedProduct['stock_crates']} Crates.";
    } elseif ($formAction === 'update_product') {
        $sku = $_POST['sku'] ?? '';
        $updated = BeverageWarehouseService::updateProduct($sku, $_POST, $_FILES);
        if ($updated) {
            $imageUploadError = BeverageWarehouseService::getLastImageUploadError();
            $products = BeverageWarehouseService::getProducts();
            $actionMessageType = $imageUploadError ? 'warning' : 'success';
            $actionMessage = $imageUploadError
                ? "⚠️ Product updated, but image upload failed: {$imageUploadError}"
                : "✏️ Product Updated Successfully! '{$updated['name']}' (SKU: {$updated['sku']}) pricing, stock & image attachment saved and synced to POS catalog.";
        }
    } elseif ($formAction === 'receive_po') {
        $grnResult = ProcurementReceivingService::processReceiving([
            'po_number' => $_POST['po_number'] ?? '',
            'supplier' => $_POST['supplier'] ?? '',
            'sku' => $_POST['sku'] ?? '',
            'warehouse' => $_POST['warehouse'] ?? '',
            'ordered_qty' => (int)($_POST['ordered_qty'] ?? 0),
            'delivered_qty' => (int)($_POST['delivered_qty'] ?? 0),
            'rejected_qty' => (int)($_POST['rejected_qty'] ?? 0),
            'damaged_qty' => (int)($_POST['damaged_qty'] ?? 0),
            'promo_free_qty' => (int)($_POST['promo_free_qty'] ?? 0),
            'invoice_cost' => (float)($_POST['invoice_cost'] ?? 0),
            'rebate_base_price' => (float)($_POST['rebate_base_price'] ?? 0),
            'expected_rebate_pct' => (float)($_POST['expected_rebate_pct'] ?? 0),
            'rebate_adjustment_factor' => (float)($_POST['rebate_adjustment_factor'] ?? 100),
            'promotion_code' => $_POST['promotion_code'] ?? '',
            'promotion_benefit_per_unit' => (float)($_POST['promotion_benefit_per_unit'] ?? 0),
        ]);
        $actionMessage = "✅ GRN generated successfully! Code: {$grnResult['grn_number']}. Stock accepted: {$grnResult['accepted_qty']} Crates/Packs. Effective landing cost: ₦" . number_format((float)($grnResult['effective_landing_cost'] ?? 0), 2) . ".";
    } elseif ($formAction === 'reconcile_trip') {
        $reconResult = VehicleReconciliationService::reconcileTrip(
            (int)($_POST['opening_crates'] ?? 50),
            (int)($_POST['loaded_crates'] ?? 100),
            (int)($_POST['sold_crates'] ?? 115),
            (int)($_POST['damaged_crates'] ?? 2),
            (int)($_POST['returned_crates'] ?? 5),
            (int)($_POST['physical_crates'] ?? 28)
        );
        $actionMessage = "✅ Trip Reconciled! Status: {$reconResult['status']}. Variance: {$reconResult['variance_formatted']}.";
    } elseif ($formAction === 'capture_daily_opening_stock') {
        $reportDate = trim((string)($_POST['report_date'] ?? date('Y-m-d')));
        $reportWarehouse = trim((string)($_POST['report_warehouse'] ?? 'All Warehouses'));
        $dailyStockReport = BeverageWarehouseService::captureDailyOpeningStock($reportDate, $reportWarehouse);
        $actionMessage = "✅ Opening stock captured for {$dailyStockReport['warehouse']} on {$dailyStockReport['report_date']}.";
    } elseif ($formAction === 'capture_daily_closing_stock') {
        $reportDate = trim((string)($_POST['report_date'] ?? date('Y-m-d')));
        $reportWarehouse = trim((string)($_POST['report_warehouse'] ?? 'All Warehouses'));
        $dailyStockReport = BeverageWarehouseService::captureDailyClosingStock($reportDate, $reportWarehouse);
        $actionMessage = "✅ Closing stock captured for {$dailyStockReport['warehouse']} on {$dailyStockReport['report_date']}.";
    } elseif ($formAction === 'capture_physical_stock_count') {
        $physicalCountResult = BeverageWarehouseService::capturePhysicalStockCount($_POST, $user['name'] ?? 'Warehouse Admin');
        $actionMessageType = !empty($physicalCountResult['success']) ? 'success' : 'error';
        $actionMessage = (!empty($physicalCountResult['success']) ? '✅ ' : '⚠️ ') . ($physicalCountResult['message'] ?? 'Physical count saved.');
        $activeTab = 'tab-physical-count';
    } elseif ($formAction === 'approve_physical_stock_count') {
        $physicalCountResult = BeverageWarehouseService::approvePhysicalStockCount((string)($_POST['count_id'] ?? ''), $user['name'] ?? 'Warehouse Admin');
        $actionMessageType = !empty($physicalCountResult['success']) ? 'success' : 'error';
        $actionMessage = (!empty($physicalCountResult['success']) ? '✅ ' : '⚠️ ') . ($physicalCountResult['message'] ?? 'Physical count approval failed.');
        $activeTab = 'tab-physical-count';
    } elseif ($formAction === 'save_truck_load_settings') {
        $settings = TruckLoadService::saveSettings($_POST, $user['name'] ?? 'Warehouse Admin');
        $actionMessage = "✅ Truck loading settings saved. Capacity: {$settings['default_pallet_capacity']} pallets.";
        $activeTab = 'tab-truck-loads';
    } elseif ($formAction === 'create_truck_load') {
        $loadResult = TruckLoadService::createLoad($_POST, $user['name'] ?? 'Warehouse Admin');
        $actionMessageType = !empty($loadResult['success']) ? 'success' : 'error';
        $actionMessage = (!empty($loadResult['success']) ? '✅ ' : '⚠️ ') . ($loadResult['message'] ?? 'Truck load processed.');
        $activeTab = 'tab-truck-loads';
    } elseif ($formAction === 'finalize_truck_load') {
        $loadResult = TruckLoadService::finalizeLoad((string)($_POST['load_id'] ?? ''), $user['name'] ?? 'Warehouse Admin');
        $actionMessageType = !empty($loadResult['success']) ? 'success' : 'error';
        $actionMessage = (!empty($loadResult['success']) ? '✅ ' : '⚠️ ') . ($loadResult['message'] ?? 'Truck load processed.');
        $activeTab = 'tab-truck-loads';
    }
}

$dailyStockReportDate = trim((string)($_GET['report_date'] ?? $_POST['report_date'] ?? date('Y-m-d')));
$dailyStockReportWarehouse = trim((string)($_GET['report_warehouse'] ?? $_GET['warehouse'] ?? $_POST['report_warehouse'] ?? 'All Warehouses'));
$dailyStockReport = $dailyStockReport ?? null;
$truckLoadSettings = null;
$truckLoads = [];
if ($activeTab === 'tab-daily-stock') {
    $dailyStockReport = BeverageWarehouseService::getDailyStockReport($dailyStockReportDate, $dailyStockReportWarehouse);
} elseif ($activeTab === 'tab-truck-loads') {
    $truckLoadSettings = TruckLoadService::getSettings();
    $truckLoads = TruckLoadService::getLoads();
}

// Render View Template
require dirname(__DIR__) . '/app/Views/warehouse_view.php';
