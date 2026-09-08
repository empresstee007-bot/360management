<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Config/suppliers.php';
require_once __DIR__ . '/../app/Modules/Pos/PosService.php';
require_once __DIR__ . '/../app/Modules/BeverageWarehouse/BeverageWarehouseService.php';
require_once __DIR__ . '/../app/Modules/HrPayroll/HrPayrollService.php';

use App\Modules\Pos\PosService;
use App\Modules\BeverageWarehouse\BeverageWarehouseService;
use App\Modules\HrPayroll\HrPayrollService;

$supplierCatalog = require __DIR__ . '/../app/Config/suppliers.php';

$user = current_user();
if (!$user) {
    redirect('login.php');
}
$role = canonical_role($user['role'] ?? '');

$activeTab = $_GET['tab'] ?? 'pos';
if ($activeTab === 'shift') {
    redirect('beverage_pos.php?tab=pos');
}
$posOnlyTabs = ['pos', 'customers', 'history', 'recon', 'items-sold'];
if ($role === 'pos' && !in_array($activeTab, $posOnlyTabs, true)) {
    flash('Access restricted: POS cashier accounts can only use the POS terminal, customers, sales history and cash drawer pages.');
    redirect('beverage_pos.php?tab=pos');
}
$actionMessage = flash();
$printedReceipt = null;
$posSettings = $_SESSION['beverage_pos_settings'] ?? [
    'terminal_name' => 'Main POS',
    'receipt_business_name' => 'EMPRESS TEE BEVERAGE DEPOT',
    'receipt_footer_note' => 'Thank you for your beverage purchase.',
    'crate_deposit_enabled' => '1',
];

$canManagePricing = class_exists('App\Modules\Pos\PricingRebateService')
    ? \App\Modules\Pos\PricingRebateService::canManagePricing($user)
    : in_array($role, ['admin'], true);
$pendingPriceApprovals = $canManagePricing && class_exists('App\Modules\Pos\PricingRebateService')
    ? \App\Modules\Pos\PricingRebateService::getPriceChangeApprovals('pending', 50)
    : [];

// Handle AJAX Request: Get Tiers for SKU
$requestedAction = $_GET['action'] ?? $_POST['action'] ?? '';
if ($requestedAction === 'get_product_tiers') {
    header('Content-Type: application/json');
    $sku = strtoupper(trim((string)($_GET['sku'] ?? $_POST['sku'] ?? '')));
    $tiers = class_exists('App\Modules\Pos\PricingRebateService') ? \App\Modules\Pos\PricingRebateService::getProductTiers($sku) : [];
    echo json_encode(['success' => true, 'sku' => $sku, 'tiers' => $tiers]);
    exit;
}

if ($requestedAction === 'simulate_rebate_economics') {
    header('Content-Type: application/json');
    $cost = (float)($_GET['cost_price'] ?? $_POST['cost_price'] ?? 0);
    $price = (float)($_GET['selling_price'] ?? $_POST['selling_price'] ?? 0);
    $rebate = (float)($_GET['rebate_pct'] ?? $_POST['rebate_pct'] ?? 0);
    $qty = (int)($_GET['qty'] ?? $_POST['qty'] ?? 1);
    $res = class_exists('App\Modules\Pos\PricingRebateService')
        ? \App\Modules\Pos\PricingRebateService::calculateEconomics($cost, $price, $rebate, $qty)
        : [];
    echo json_encode(['success' => true, 'economics' => $res]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';
    $posAllowedActions = ['process_sale', 'open_cash_drawer', 'reconcile_shift'];
    if ($role === 'pos' && !in_array($formAction, $posAllowedActions, true)) {
        flash('Access restricted: POS cashier accounts cannot change admin, warehouse, pricing or system settings.');
        redirect('beverage_pos.php?tab=pos');
    }

    if ($formAction === 'approve_preferred_price') {
        if (!$canManagePricing) {
            flash('⚠️ Permission denied: only managers can approve preferred POS prices.');
        } else {
            $approval = \App\Modules\Pos\PricingRebateService::approvePriceChange((string)($_POST['request_id'] ?? ''), $user['name'] ?? 'Manager');
            $salePayload = $approval['sale_payload'] ?? null;
            if ($approval['success'] && is_array($salePayload)) {
                $salePayload['_approved_preferred_price'] = '1';
                $salePayload['form_action'] = 'process_sale';
                $salePayload['pos_mode'] = 'beverage';
                $receipt = PosService::processSale($salePayload);
                flash(!empty($receipt['error']) ? '⚠️ Approval succeeded, but the sale could not be completed: ' . ($receipt['message'] ?? 'Unknown error.') : '✅ Preferred price approved and sale completed. Receipt: ' . ($receipt['receipt_no'] ?? 'created'));
            } else {
                flash('⚠️ ' . ($approval['message'] ?? 'Preferred price approval failed.'));
            }
        }
        redirect('beverage_pos.php?tab=pos');
    } elseif ($formAction === 'reject_preferred_price') {
        if (!$canManagePricing) {
            flash('⚠️ Permission denied: only managers can reject preferred POS prices.');
        } else {
            $approval = \App\Modules\Pos\PricingRebateService::rejectPriceChange((string)($_POST['request_id'] ?? ''), $user['name'] ?? 'Manager');
            flash($approval['success'] ? '✅ Preferred price request rejected.' : '⚠️ ' . ($approval['message'] ?? 'Could not reject request.'));
        }
        redirect('beverage_pos.php?tab=pos');
    } elseif ($formAction === 'open_cash_drawer') {
        $actionData = HrPayrollService::openCashierSession($_POST, $user);
        $drawer = $actionData['session'] ?? [];
        $actionMessage = (string)($actionData['message'] ?? 'Cash drawer opened.') . ' Opening cash: ₦' . number_format((float)($drawer['opening_cash'] ?? 0), 2);
        $activeTab = 'recon';
    } elseif ($formAction === 'save_pricing_tier') {
        if (!$canManagePricing) {
            $actionMessage = "⚠️ Permission Denied: Only Admin or Pricing Manager can modify pricing tiers.";
            $actionMessageType = 'error';
        } else {
            $sku = strtoupper(trim((string)($_POST['sku'] ?? '')));
            $tierRes = \App\Modules\Pos\PricingRebateService::savePricingTier($_POST, $user['name'] ?? 'Admin');
            if ($tierRes['success']) {
                $actionMessage = "✅ " . $tierRes['message'];
            } else {
                $actionMessage = "⚠️ Error: " . ($tierRes['message'] ?? 'Could not save pricing tier.');
            }
            $activeTab = 'pricing-tiers';
            $_GET['sku'] = $sku;
        }
    } elseif ($formAction === 'delete_pricing_tier') {
        if (!$canManagePricing) {
            $actionMessage = "⚠️ Permission Denied: Only Admin or Pricing Manager can delete pricing tiers.";
        } else {
            $tierId = (string)($_POST['tier_id'] ?? '');
            $sku = (string)($_POST['sku'] ?? '');
            $ok = \App\Modules\Pos\PricingRebateService::deletePricingTier($tierId, $sku, $user['name'] ?? 'Admin');
            $actionMessage = $ok ? "🗑️ Pricing Tier deleted successfully." : "⚠️ Could not delete pricing tier.";
            $activeTab = 'pricing-tiers';
            $_GET['sku'] = $sku;
        }
    } elseif ($formAction === 'save_rebate_setting') {
        if (!$canManagePricing) {
            flash("⚠️ Permission Denied: Only Admin or Pricing Manager can modify rebate settings.");
        } else {
            $rebateRes = \App\Modules\Pos\PricingRebateService::saveRebateRule($_POST, $user['name'] ?? 'Admin');
            flash($rebateRes['success'] ? "✅ " . $rebateRes['message'] : "⚠️ " . ($rebateRes['message'] ?? 'Could not save rebate rule.'));
        }
        redirect('beverage_pos.php?tab=rebates');
    } elseif ($formAction === 'delete_rebate_setting') {
        if (!$canManagePricing) {
            flash("⚠️ Permission Denied: Only Admin or Pricing Manager can delete rebate settings.");
        } else {
            $ruleId = (string)($_POST['rule_id'] ?? '');
            $ok = \App\Modules\Pos\PricingRebateService::deleteRebateRule($ruleId, $user['name'] ?? 'Admin');
            flash($ok ? "🗑️ Rebate Rule deleted successfully." : "⚠️ Could not delete rebate rule.");
        }
        redirect('beverage_pos.php?tab=rebates');
    } elseif ($formAction === 'record_rebate_settlement') {
        if (!$canManagePricing) {
            flash("⚠️ Permission Denied: Only Admin or Pricing Manager can record rebate settlements.");
        } else {
            $settlementRes = \App\Modules\Pos\PricingRebateService::recordRebateSettlement($_POST, $user['name'] ?? 'Admin');
            flash($settlementRes['success'] ? "✅ " . $settlementRes['message'] : "⚠️ Could not record rebate settlement.");
        }
        redirect('beverage_pos.php?tab=rebates');
    } elseif ($formAction === 'delete_rebate_settlement') {
        if (!$canManagePricing) {
            flash("⚠️ Permission Denied: Only Admin or Pricing Manager can delete rebate settlements.");
        } else {
            $ok = \App\Modules\Pos\PricingRebateService::deleteRebateSettlement((string)($_POST['settlement_id'] ?? ''), $user['name'] ?? 'Admin');
            flash($ok ? "✅ Rebate settlement deleted." : "⚠️ Could not delete rebate settlement.");
        }
        redirect('beverage_pos.php?tab=rebates');
    } elseif ($formAction === 'save_promotion_rule') {
        if (!$canManagePricing) {
            $actionMessage = "⚠️ Permission Denied: Only Admin or Pricing Manager can modify promotion rules.";
            $actionMessageType = 'error';
        } else {
            $promoRes = \App\Modules\Pos\PricingRebateService::savePromotionRule($_POST, $user['name'] ?? 'Admin');
            $actionMessage = $promoRes['success'] ? "✅ " . $promoRes['message'] : "⚠️ " . ($promoRes['message'] ?? 'Could not save promotion rule.');
            $activeTab = 'promotions';
        }
    } elseif ($formAction === 'delete_promotion_rule') {
        if (!$canManagePricing) {
            $actionMessage = "⚠️ Permission Denied: Only Admin or Pricing Manager can delete promotion rules.";
            $actionMessageType = 'error';
        } else {
            $ok = \App\Modules\Pos\PricingRebateService::deletePromotionRule((string)($_POST['promotion_id'] ?? ''), $user['name'] ?? 'Admin');
            $actionMessage = $ok ? "🗑️ Promotion Rule deleted successfully." : "⚠️ Could not delete promotion rule.";
            $activeTab = 'promotions';
        }
    } elseif ($formAction === 'process_sale') {
        $preferredPriceChanges = [];
        $cartItems = json_decode((string)($_POST['cart_items'] ?? '[]'), true) ?: [];
        foreach ($cartItems as $cartItem) {
            $preferredPrice = (float)($cartItem['preferred_price'] ?? 0);
            $currentPrice = (float)($cartItem['approval_base_price'] ?? $cartItem['price_per_unit'] ?? 0);
            if ($preferredPrice > 0 && abs($preferredPrice - $currentPrice) > 0.009) {
                $preferredPriceChanges[] = ['sku' => (string)($cartItem['sku'] ?? ''), 'name' => (string)($cartItem['name'] ?? ''), 'current_price' => $currentPrice, 'preferred_price' => $preferredPrice, 'qty' => (int)($cartItem['qty'] ?? 1)];
            }
        }
        if ($role === 'pos' && $preferredPriceChanges && empty($_POST['_approved_preferred_price'])) {
            $approval = \App\Modules\Pos\PricingRebateService::requestPreferredSalePriceApproval($_POST, $preferredPriceChanges, $user['name'] ?? 'POS');
            flash($approval['success'] ? '✅ Preferred price submitted for manager approval. The sale will remain pending until approved.' : '⚠️ Could not submit preferred price for approval.');
            redirect('beverage_pos.php?tab=pos');
        }
        $_POST['pos_mode'] = 'beverage';
        $printedReceipt = PosService::processSale($_POST);
        if (!empty($printedReceipt['error'])) {
            $actionMessage = "⚠️ " . ($printedReceipt['message'] ?? 'Sale could not be completed.');
            $actionMessageType = 'error';
            $printedReceipt = null;
        } else {
            $actionMessage = "✅ Beverage Sale Completed via {$_POST['payment_method']}! Receipt #: {$printedReceipt['receipt_no']} — Total: ₦" . number_format($printedReceipt['grand_total'], 2);
        }
        if (!empty($printedReceipt['customer_saved'])) {
            $actionMessage .= " | Customer saved to contacts.";
        }
    } elseif ($formAction === 'create_stock_movement') {
        $movement = BeverageWarehouseService::createTransfer($_POST);
        $actionMessage = (($movement['status'] ?? '') === 'Completed' ? '✅ ' : '⚠️ ') . ($movement['message'] ?? 'Stock movement processed.');
        $activeTab = 'transfers';
    } elseif ($formAction === 'confirm_transfer_paid') {
        $updatedTransfer = PosService::updateTransferPaymentStatus((string)($_POST['receipt_no'] ?? ''), 'confirm_paid');
        $actionMessage = $updatedTransfer
            ? "🏦 Transfer Payment Confirmed! Receipt #: {$updatedTransfer['receipt_no']} | Amount: ₦" . number_format((float)$updatedTransfer['grand_total'], 2) . " is awaiting approval."
            : "⚠️ Transfer payment could not be found for confirmation.";
        $activeTab = $_POST['return_tab'] ?? 'transfers';
    } elseif ($formAction === 'approve_transfer_paid') {
        $updatedTransfer = PosService::updateTransferPaymentStatus((string)($_POST['receipt_no'] ?? ''), 'approve_paid');
        $actionMessage = $updatedTransfer
            ? "✅ Transfer Payment Approved & Posted! Receipt #: {$updatedTransfer['receipt_no']} | Amount: ₦" . number_format((float)$updatedTransfer['grand_total'], 2)
            : "⚠️ Transfer payment could not be found for approval.";
        $activeTab = $_POST['return_tab'] ?? 'transfers';
    } elseif ($formAction === 'reconcile_shift') {
        $shiftSummary = PosService::getDailyShiftSummary('beverage');
        $drawerResult = HrPayrollService::closeCashierSession($_POST, $shiftSummary, $user);
        $drawer = $drawerResult['session'] ?? [];
        $physicalCount = (float)($drawer['counted_cash'] ?? $_POST['physical_cash'] ?? 0);
        $expected = (float)($drawer['expected_cash'] ?? $shiftSummary['expected_closing_cash']);
        $diff = (float)($drawer['variance'] ?? ($physicalCount - $expected));
        $statusStr = $diff == 0 ? 'BALANCED ✅' : ($diff > 0 ? 'SURPLUS 🟢 (+₦' . number_format($diff, 2) . ')' : 'SHORTAGE 🔴 (-₦' . number_format(abs($diff), 2) . ')');
        $actionMessage = "🔒 Cash Drawer Reconciled Successfully! Expected Cash: ₦" . number_format($expected, 2) . " | Counted Cash: ₦" . number_format($physicalCount, 2) . " | Result: {$statusStr}";
        $activeTab = 'recon';
    } elseif ($formAction === 'save_pos_settings') {
        $posSettings = [
            'terminal_name' => trim((string)($_POST['terminal_name'] ?? 'Main POS')) ?: 'Main POS',
            'receipt_business_name' => trim((string)($_POST['receipt_business_name'] ?? 'EMPRESS TEE BEVERAGE DEPOT')) ?: 'EMPRESS TEE BEVERAGE DEPOT',
            'receipt_footer_note' => trim((string)($_POST['receipt_footer_note'] ?? 'Thank you for your beverage purchase.')) ?: 'Thank you for your beverage purchase.',
            'crate_deposit_enabled' => isset($_POST['crate_deposit_enabled']) ? '1' : '0',
        ];
        $_SESSION['beverage_pos_settings'] = $posSettings;
        $actionMessage = "✅ POS settings saved.";
        $activeTab = 'settings';
    }
}

if ($activeTab === 'pricing-audit') {
    $catalog = PosService::getCatalog('beverage');
    $availableProducts = [];
    $totalAvailableStock = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $customers = [];
    $salesHistory = [];
    $beverageSalesHistory = [];
    $salesAccountTotals = [
        'gross_sales' => 0.0,
        'subtotal' => 0.0,
        'crate_deposit' => 0.0,
        'receipts' => 0,
        'items_sold' => 0,
    ];
    $salesByMethod = [
        'Cash' => ['count' => 0, 'total' => 0.0],
        'Bank Transfer' => ['count' => 0, 'total' => 0.0],
        'POS Terminal Card' => ['count' => 0, 'total' => 0.0],
        'Customer Wallet' => ['count' => 0, 'total' => 0.0],
        'Debt / On Credit' => ['count' => 0, 'total' => 0.0],
    ];
    $itemsSoldByProduct = [];
    $commercialReports = [
        'channels' => [],
        'sku_margin' => [],
        'customer_margin' => [],
        'promotion_utilization' => [],
        'below_invoice_profitable' => [],
        'true_losses' => [],
        'overrides' => [],
        'expected_vs_confirmed_rebate' => [],
    ];
    $transferPayments = [];
    $transferPaymentTotal = 0.0;
    $pendingTransferPayments = [];
    $confirmedTransferPayments = [];
    $approvedTransferPayments = [];
    $pendingTransferTotal = 0.0;
    $approvedTransferTotal = 0.0;
    $recentTransferPayments = [];
    try {
        $stockWarehouses = BeverageWarehouseService::getWarehouses();
    } catch (Throwable) {
        $stockWarehouses = ['Jacroxx Warehouse', 'Ijaba Warehouse'];
    }
    try {
        $stockWarehouseSummaries = BeverageWarehouseService::getWarehouseSummaries();
    } catch (Throwable) {
        $stockWarehouseSummaries = [];
    }
    $stockByWarehouseMap = [];
    try {
        $stockMovementLog = array_values(array_filter(
            BeverageWarehouseService::getTransfers(),
            static fn(array $movement): bool => isset($movement['from'], $movement['to'], $movement['sku'])
        ));
    } catch (Throwable) {
        $stockMovementLog = [];
    }
    $shiftSummary = [
        'opening_float' => 0.0,
        'total_cash' => 0.0,
        'expected_closing_cash' => 0.0,
        'total_transactions' => 0,
    ];
    $currentEmployee = [];
    $currentEmployeeCode = '';
    $currentCashierSession = null;
    $cashierSessions = [];
    $aiSupervisorBrief = ['score' => 100, 'status' => 'Stable', 'watch_alerts' => [], 'training_tasks' => []];

    require dirname(__DIR__) . '/app/Views/pos_view.php';
    exit;
}

if ($activeTab === 'rebates') {
    $rebateSuppliers = [];
    foreach (array_keys(is_array($supplierCatalog) ? $supplierCatalog : []) as $supplier) {
        $rebateSuppliers[strtoupper($supplier)] = $supplier;
    }
    try {
        foreach (BeverageWarehouseService::getProducts() as $product) {
            $supplier = trim((string)($product['supplier'] ?? ''));
            if ($supplier !== '') {
                $rebateSuppliers[strtoupper($supplier)] = $supplier;
            }
        }
    } catch (Throwable) {
        $rebateSuppliers = [];
    }
    $catalog = PosService::getCatalog('beverage');
    $availableProducts = [];
    $totalAvailableStock = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $customers = [];
    $salesHistory = [];
    $beverageSalesHistory = [];
    $salesAccountTotals = [
        'gross_sales' => 0.0,
        'subtotal' => 0.0,
        'crate_deposit' => 0.0,
        'receipts' => 0,
        'items_sold' => 0,
    ];
    $salesByMethod = [
        'Cash' => ['count' => 0, 'total' => 0.0],
        'Bank Transfer' => ['count' => 0, 'total' => 0.0],
        'POS Terminal Card' => ['count' => 0, 'total' => 0.0],
        'Customer Wallet' => ['count' => 0, 'total' => 0.0],
        'Debt / On Credit' => ['count' => 0, 'total' => 0.0],
    ];
    $itemsSoldByProduct = [];
    $commercialReports = [
        'channels' => [],
        'sku_margin' => [],
        'customer_margin' => [],
        'promotion_utilization' => [],
        'below_invoice_profitable' => [],
        'true_losses' => [],
        'overrides' => [],
        'expected_vs_confirmed_rebate' => [],
    ];
    $transferPayments = [];
    $transferPaymentTotal = 0.0;
    $pendingTransferPayments = [];
    $confirmedTransferPayments = [];
    $approvedTransferPayments = [];
    $pendingTransferTotal = 0.0;
    $approvedTransferTotal = 0.0;
    $recentTransferPayments = [];
    $stockWarehouses = [];
    $stockWarehouseSummaries = [];
    $stockByWarehouseMap = [];
    $stockMovementLog = [];
    $shiftSummary = [
        'opening_float' => 0.0,
        'total_cash' => 0.0,
        'expected_closing_cash' => 0.0,
        'total_transactions' => 0,
    ];
    $currentEmployee = [];
    $currentEmployeeCode = '';
    $currentCashierSession = null;
    $cashierSessions = [];
    $aiSupervisorBrief = ['score' => 100, 'status' => 'Stable', 'watch_alerts' => [], 'training_tasks' => []];

    require dirname(__DIR__) . '/app/Views/pos_view.php';
    exit;
}

if ($activeTab === 'transfers') {
    $catalog = PosService::getCatalog('beverage');
    $availableProducts = array_values(array_filter($catalog, static fn (array $item): bool => (int)($item['stock_qty'] ?? 0) > 0));
    $totalAvailableStock = array_sum(array_map(static fn (array $item): int => (int)($item['stock_qty'] ?? 0), $availableProducts));
    $lowStockCount = count(array_filter($catalog, static fn (array $item): bool => (int)($item['stock_qty'] ?? 0) > 0 && (int)($item['stock_qty'] ?? 0) <= 100));
    $outOfStockCount = count(array_filter($catalog, static fn (array $item): bool => (int)($item['stock_qty'] ?? 0) <= 0));
    $customers = [];
    $salesHistory = [];
    $beverageSalesHistory = [];
    $salesAccountTotals = [
        'gross_sales' => 0.0,
        'subtotal' => 0.0,
        'crate_deposit' => 0.0,
        'receipts' => 0,
        'items_sold' => 0,
    ];
    $salesByMethod = [
        'Cash' => ['count' => 0, 'total' => 0.0],
        'Bank Transfer' => ['count' => 0, 'total' => 0.0],
        'POS Terminal Card' => ['count' => 0, 'total' => 0.0],
        'Customer Wallet' => ['count' => 0, 'total' => 0.0],
        'Debt / On Credit' => ['count' => 0, 'total' => 0.0],
    ];
    $itemsSoldByProduct = [];
    $commercialReports = [
        'channels' => [],
        'sku_margin' => [],
        'customer_margin' => [],
        'promotion_utilization' => [],
        'below_invoice_profitable' => [],
        'true_losses' => [],
        'overrides' => [],
        'expected_vs_confirmed_rebate' => [],
    ];
    $transferPayments = [];
    $transferPaymentTotal = 0.0;
    $pendingTransferPayments = [];
    $confirmedTransferPayments = [];
    $approvedTransferPayments = [];
    $pendingTransferTotal = 0.0;
    $approvedTransferTotal = 0.0;
    $recentTransferPayments = [];
    try {
        $stockWarehouses = BeverageWarehouseService::getWarehouses();
    } catch (Throwable) {
        $stockWarehouses = ['Jacroxx Warehouse', 'Ijaba Warehouse'];
    }
    try {
        $stockWarehouseSummaries = BeverageWarehouseService::getWarehouseSummaries();
    } catch (Throwable) {
        $stockWarehouseSummaries = [];
    }
    try {
        $stockByWarehouseMap = BeverageWarehouseService::getAllProductWarehouseStock();
    } catch (Throwable) {
        $stockByWarehouseMap = [];
    }
    try {
        $stockMovementLog = array_values(array_filter(
            BeverageWarehouseService::getTransfers(),
            static fn(array $movement): bool => isset($movement['from'], $movement['to'], $movement['sku'])
        ));
    } catch (Throwable) {
        $stockMovementLog = [];
    }
    $shiftSummary = [
        'opening_float' => 0.0,
        'total_cash' => 0.0,
        'expected_closing_cash' => 0.0,
        'total_transactions' => 0,
    ];
    $currentEmployee = [];
    $currentEmployeeCode = '';
    $currentCashierSession = null;
    $cashierSessions = [];
    $aiSupervisorBrief = ['score' => 100, 'status' => 'Stable', 'watch_alerts' => [], 'training_tasks' => []];

    require dirname(__DIR__) . '/app/Views/pos_view.php';
    exit;
}

$catalog = PosService::getCatalog('beverage');
$availableProducts = array_values(array_filter($catalog, static fn (array $item): bool => (int)($item['stock_qty'] ?? 0) > 0));
$totalAvailableStock = array_sum(array_map(static fn (array $item): int => (int)($item['stock_qty'] ?? 0), $availableProducts));
$lowStockCount = count(array_filter($catalog, static fn (array $item): bool => (int)($item['stock_qty'] ?? 0) > 0 && (int)($item['stock_qty'] ?? 0) <= 100));
$outOfStockCount = count(array_filter($catalog, static fn (array $item): bool => (int)($item['stock_qty'] ?? 0) <= 0));
$customers = PosService::getCustomerAccounts('beverage');
$salesHistory = $_SESSION['pos_sales_history'] ?? [];
$beverageSalesHistory = array_values(array_filter(
    $salesHistory,
    static fn (array $tx): bool => (($tx['pos_mode'] ?? 'beverage') === 'beverage')
));
$salesAccountTotals = [
    'gross_sales' => 0.0,
    'subtotal' => 0.0,
    'crate_deposit' => 0.0,
    'receipts' => count($beverageSalesHistory),
    'items_sold' => 0,
];
$salesByMethod = [
    'Cash' => ['count' => 0, 'total' => 0.0],
    'Bank Transfer' => ['count' => 0, 'total' => 0.0],
    'POS Terminal Card' => ['count' => 0, 'total' => 0.0],
    'Customer Wallet' => ['count' => 0, 'total' => 0.0],
    'Debt / On Credit' => ['count' => 0, 'total' => 0.0],
];
$itemsSoldByProduct = [];
foreach ($catalog as $product) {
    $productKey = (string)($product['id'] ?? $product['sku'] ?? $product['name']);
    $itemsSoldByProduct[$productKey] = [
        'name' => (string)($product['name'] ?? 'Product'),
        'sku' => (string)($product['sku'] ?? ''),
        'image' => (string)($product['image'] ?? ''),
        'qty' => 0,
        'total' => 0.0,
    ];
}
foreach ($beverageSalesHistory as $tx) {
    $amount = (float)($tx['grand_total'] ?? 0);
    $salesAccountTotals['gross_sales'] += $amount;
    $salesAccountTotals['subtotal'] += (float)($tx['subtotal'] ?? 0);
    $salesAccountTotals['crate_deposit'] += (float)($tx['crate_deposit'] ?? 0);
    foreach (($tx['items'] ?? []) as $item) {
        $soldQty = (int)($item['qty'] ?? 0);
        $salesAccountTotals['items_sold'] += $soldQty;
        $productKey = (string)($item['id'] ?? $item['sku'] ?? $item['name'] ?? 'manual-product');
        if (!isset($itemsSoldByProduct[$productKey])) {
            $itemsSoldByProduct[$productKey] = [
                'name' => (string)($item['name'] ?? 'Manual Product'),
                'sku' => (string)($item['sku'] ?? ''),
                'image' => (string)($item['image'] ?? ''),
                'qty' => 0,
                'total' => 0.0,
            ];
        }
        $itemsSoldByProduct[$productKey]['qty'] += $soldQty;
        $itemsSoldByProduct[$productKey]['total'] += (float)($item['total'] ?? 0);
    }

    $method = (string)($tx['payment_method'] ?? 'Cash');
    if (!isset($salesByMethod[$method])) {
        $salesByMethod[$method] = ['count' => 0, 'total' => 0.0];
    }
    $salesByMethod[$method]['count']++;
    $salesByMethod[$method]['total'] += $amount;
}
$itemsSoldByProduct = array_values($itemsSoldByProduct);
usort($itemsSoldByProduct, static fn (array $a, array $b): int => ($b['qty'] <=> $a['qty']) ?: strcmp($a['name'], $b['name']));
$commercialReports = [
    'channels' => [
        'depot_sale' => ['label' => 'Depot Sale', 'receipts' => 0, 'qty' => 0, 'revenue' => 0.0, 'invoice_cost' => 0.0, 'rebate' => 0.0, 'promotion' => 0.0, 'effective_cost' => 0.0, 'direct_cost' => 0.0, 'true_profit' => 0.0, 'operational_net_margin' => 0.0],
        'diversion_sale' => ['label' => 'Diversion Sale', 'receipts' => 0, 'qty' => 0, 'revenue' => 0.0, 'invoice_cost' => 0.0, 'rebate' => 0.0, 'promotion' => 0.0, 'effective_cost' => 0.0, 'direct_cost' => 0.0, 'true_profit' => 0.0, 'operational_net_margin' => 0.0],
    ],
    'sku_margin' => [],
    'customer_margin' => [],
    'promotion_utilization' => [],
    'below_invoice_profitable' => [],
    'true_losses' => [],
    'overrides' => [],
    'expected_vs_confirmed_rebate' => [],
];

foreach ($beverageSalesHistory as $tx) {
    $txChannel = class_exists('App\Modules\Pos\PricingRebateService')
        ? \App\Modules\Pos\PricingRebateService::normalizeSalesChannel((string)($tx['sales_channel'] ?? 'depot_sale'))
        : (string)($tx['sales_channel'] ?? 'depot_sale');
    if (!isset($commercialReports['channels'][$txChannel])) {
        $commercialReports['channels'][$txChannel] = ['label' => ucwords(str_replace('_', ' ', $txChannel)), 'receipts' => 0, 'qty' => 0, 'revenue' => 0.0, 'invoice_cost' => 0.0, 'rebate' => 0.0, 'promotion' => 0.0, 'effective_cost' => 0.0, 'direct_cost' => 0.0, 'true_profit' => 0.0, 'operational_net_margin' => 0.0];
    }
    $commercialReports['channels'][$txChannel]['receipts']++;

    foreach (($tx['items'] ?? []) as $item) {
        $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
        $sku = strtoupper(trim((string)($item['sku'] ?? 'UNKNOWN')));
        $name = (string)($item['name'] ?? $sku);
        $customer = (string)($tx['customer_name'] ?? 'Walk-in Customer');
        $channel = class_exists('App\Modules\Pos\PricingRebateService')
            ? \App\Modules\Pos\PricingRebateService::normalizeSalesChannel((string)($item['sales_channel'] ?? $txChannel))
            : (string)($item['sales_channel'] ?? $txChannel);
        $revenue = (float)($item['total'] ?? (((float)($item['actual_selling_price'] ?? $item['price_per_unit'] ?? 0)) * $qty));
        $invoiceCost = (float)($item['cost_price'] ?? $item['invoice_price'] ?? 0) * $qty;
        $rebate = (float)($item['total_rebate_amount'] ?? (((float)($item['rebate_amount'] ?? 0)) * $qty));
        $promotion = (float)($item['total_promotion_benefit'] ?? (((float)($item['promotion_benefit'] ?? 0)) * $qty));
        $effectiveCost = (float)($item['total_effective_cost'] ?? (((float)($item['effective_landing_cost'] ?? $item['effective_cost'] ?? 0)) * $qty));
        if ($effectiveCost <= 0 && $invoiceCost > 0) {
            $effectiveCost = max(0.0, $invoiceCost - $rebate - $promotion);
        }
        $trueProfit = (float)($item['total_true_profit'] ?? ($revenue - $effectiveCost));
        $directCost = (float)($item['total_direct_cost'] ?? (((float)($item['direct_cost_per_unit'] ?? 0)) * $qty));
        $operationalNet = (float)($item['total_operational_net_margin'] ?? ($trueProfit - $directCost));

        foreach (['sku_margin' => $sku, 'customer_margin' => $customer] as $bucket => $key) {
            if (!isset($commercialReports[$bucket][$key])) {
                $commercialReports[$bucket][$key] = ['name' => $bucket === 'sku_margin' ? $name : $key, 'sku' => $sku, 'qty' => 0, 'revenue' => 0.0, 'invoice_cost' => 0.0, 'effective_cost' => 0.0, 'direct_cost' => 0.0, 'true_profit' => 0.0, 'operational_net_margin' => 0.0];
            }
            $commercialReports[$bucket][$key]['qty'] += $qty;
            $commercialReports[$bucket][$key]['revenue'] += $revenue;
            $commercialReports[$bucket][$key]['invoice_cost'] += $invoiceCost;
            $commercialReports[$bucket][$key]['effective_cost'] += $effectiveCost;
            $commercialReports[$bucket][$key]['direct_cost'] += $directCost;
            $commercialReports[$bucket][$key]['true_profit'] += $trueProfit;
            $commercialReports[$bucket][$key]['operational_net_margin'] += $operationalNet;
        }

        if (!isset($commercialReports['channels'][$channel])) {
            $commercialReports['channels'][$channel] = ['label' => ucwords(str_replace('_', ' ', $channel)), 'receipts' => 0, 'qty' => 0, 'revenue' => 0.0, 'invoice_cost' => 0.0, 'rebate' => 0.0, 'promotion' => 0.0, 'effective_cost' => 0.0, 'direct_cost' => 0.0, 'true_profit' => 0.0, 'operational_net_margin' => 0.0];
        }
        $commercialReports['channels'][$channel]['qty'] += $qty;
        $commercialReports['channels'][$channel]['revenue'] += $revenue;
        $commercialReports['channels'][$channel]['invoice_cost'] += $invoiceCost;
        $commercialReports['channels'][$channel]['rebate'] += $rebate;
        $commercialReports['channels'][$channel]['promotion'] += $promotion;
        $commercialReports['channels'][$channel]['effective_cost'] += $effectiveCost;
        $commercialReports['channels'][$channel]['direct_cost'] += $directCost;
        $commercialReports['channels'][$channel]['true_profit'] += $trueProfit;
        $commercialReports['channels'][$channel]['operational_net_margin'] += $operationalNet;

        $promoCode = trim((string)($item['promotion_code'] ?? ''));
        if ($promoCode !== '') {
            if (!isset($commercialReports['promotion_utilization'][$promoCode])) {
                $commercialReports['promotion_utilization'][$promoCode] = ['code' => $promoCode, 'name' => (string)($item['promotion_name'] ?? $promoCode), 'qty' => 0, 'benefit' => 0.0, 'revenue' => 0.0, 'true_profit' => 0.0];
            }
            $commercialReports['promotion_utilization'][$promoCode]['qty'] += $qty;
            $commercialReports['promotion_utilization'][$promoCode]['benefit'] += $promotion;
            $commercialReports['promotion_utilization'][$promoCode]['revenue'] += $revenue;
            $commercialReports['promotion_utilization'][$promoCode]['true_profit'] += $trueProfit;
        }

        $unitSelling = (float)($item['actual_selling_price'] ?? $item['price_per_unit'] ?? ($qty > 0 ? $revenue / $qty : 0));
        $unitCost = (float)($item['cost_price'] ?? $item['invoice_price'] ?? 0);
        if ($unitSelling < $unitCost && $trueProfit > 0) {
            $commercialReports['below_invoice_profitable'][] = ['receipt' => $tx['receipt_no'] ?? '', 'sku' => $sku, 'name' => $name, 'customer' => $customer, 'selling' => $unitSelling, 'invoice_cost' => $unitCost, 'true_profit' => $trueProfit];
        }
        if ($trueProfit < 0) {
            $commercialReports['true_losses'][] = ['receipt' => $tx['receipt_no'] ?? '', 'sku' => $sku, 'name' => $name, 'customer' => $customer, 'revenue' => $revenue, 'effective_cost' => $effectiveCost, 'true_profit' => $trueProfit];
        }
    }
}

foreach (['sku_margin', 'customer_margin', 'promotion_utilization'] as $reportKey) {
    $commercialReports[$reportKey] = array_values($commercialReports[$reportKey]);
    usort($commercialReports[$reportKey], static fn(array $a, array $b): int => ((float)($b['true_profit'] ?? $b['benefit'] ?? 0)) <=> ((float)($a['true_profit'] ?? $a['benefit'] ?? 0)));
}
if (class_exists('App\Modules\Pos\PricingRebateService')) {
    $commercialReports['overrides'] = array_values(array_filter(
        \App\Modules\Pos\PricingRebateService::getAuditLogs(),
        static fn(array $log): bool => ($log['entity_type'] ?? '') === 'price_override'
    ));
    $commercialReports['expected_vs_confirmed_rebate'] = \App\Modules\Pos\PricingRebateService::summarizeExpectedVsConfirmedRebates($salesHistory);
}
$transferPayments = array_values(array_filter($salesHistory, static function (array $tx): bool {
    return str_contains(strtolower((string)($tx['payment_method'] ?? '')), 'transfer')
        && (($tx['pos_mode'] ?? 'beverage') === 'beverage');
}));
$transferPaymentTotal = array_sum(array_map(static fn (array $tx): float => (float)($tx['grand_total'] ?? 0), $transferPayments));
$pendingTransferPayments = array_values(array_filter($transferPayments, static fn (array $tx): bool => (($tx['transfer_status'] ?? 'Pending POS Confirmation') === 'Pending POS Confirmation')));
$confirmedTransferPayments = array_values(array_filter($transferPayments, static fn (array $tx): bool => (($tx['transfer_status'] ?? '') === 'Paid - Awaiting Approval')));
$approvedTransferPayments = array_values(array_filter($transferPayments, static fn (array $tx): bool => (($tx['transfer_status'] ?? '') === 'Approved & Posted')));
$pendingTransferTotal = array_sum(array_map(static fn (array $tx): float => (float)($tx['grand_total'] ?? 0), $pendingTransferPayments));
$approvedTransferTotal = array_sum(array_map(static fn (array $tx): float => (float)($tx['grand_total'] ?? 0), $approvedTransferPayments));
$recentTransferPayments = array_slice($transferPayments, 0, 4);
try {
    $stockWarehouses = BeverageWarehouseService::getWarehouses();
} catch (Throwable) {
    $stockWarehouses = ['Jacroxx Warehouse', 'Ijaba Warehouse'];
}
$stockWarehouseSummaries = [];
$stockByWarehouseMap = [];
$stockMovementLog = [];
$shiftSummary = [
    'opening_float' => 0.0,
    'total_cash' => 0.0,
    'expected_closing_cash' => 0.0,
    'total_transactions' => 0,
    'total_sales' => 0.0,
];
$currentEmployee = [];
$currentEmployeeCode = '';
$currentCashierSession = null;
$cashierSessions = [];
if (in_array($activeTab, ['pos', 'recon'], true)) {
    $shiftSummary = PosService::getDailyShiftSummary('beverage');
    $currentEmployee = HrPayrollService::employeeForUser($user);
    $currentEmployeeCode = (string)($currentEmployee['employee_code'] ?? strtoupper(str_replace(['@', '.', '+'], '-', (string)($user['email'] ?? 'POS'))));
    $currentCashierSession = HrPayrollService::getCurrentCashierSession($currentEmployeeCode, (string)($posSettings['terminal_name'] ?? 'Main POS'));
    if ($activeTab === 'recon') {
        $cashierSessions = HrPayrollService::getCashierSessions();
    }
    if ($currentCashierSession) {
        $shiftSummary['opening_float'] = (float)($currentCashierSession['opening_cash'] ?? 0);
        $shiftSummary['expected_closing_cash'] = (float)$shiftSummary['opening_float'] + (float)$shiftSummary['total_cash'];
    }
}
$aiSupervisorBrief = ['score' => 100, 'status' => 'Stable', 'watch_alerts' => [], 'training_tasks' => []];

// Render View Template
require dirname(__DIR__) . '/app/Views/pos_view.php';
