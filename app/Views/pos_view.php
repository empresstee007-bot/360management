<?php
$posActiveRole = canonical_role($user['role'] ?? '');
$useAdminShell = ($activeTab ?? 'pos') !== 'pos' && $posActiveRole !== 'pos';
$posHeaderTitles = [
    'transfers' => ['Stock Movement', 'Move beverage stock between warehouses'],
    'sales-account' => ['Reports', 'Review POS sales account and cashier closing'],
    'history' => ['Sales', 'Review completed beverage transactions'],
    'inventory' => ['Products', 'View sellable beverage product stock'],
    'customers' => ['Customers', 'Manage walk-in customer contacts'],
    'recon' => ['Cash Drawer', 'Reconcile cashier cash totals'],
    'pricing-tiers' => ['Price Tiers', 'Manage dynamic quantity pricing'],
    'rebates' => ['Rebates', 'Manage rebate rules and margins'],
    'promotions' => ['Promotions', 'Manage supplier promotions and 5+1 schemes'],
    'pricing-audit' => ['Inventory Audit', 'Review stock, pricing and rebate changes'],
    'settings' => ['Settings', 'Manage POS receipt and terminal settings'],
];
[$posHeaderTitle, $posHeaderSubtitle] = $posHeaderTitles[$activeTab] ?? ['Beverage POS', 'Search, add items, collect payment, print receipt.'];
$posSidebarStockCount = isset($stockWarehouseSummaries) && is_array($stockWarehouseSummaries)
    ? array_sum(array_map(static fn(array $summary): int => (int)($summary['stock_crates'] ?? 0), $stockWarehouseSummaries))
    : (int)($totalAvailableStock ?? 0);
$posSidebarStockBadge = $posSidebarStockCount >= 1000 ? number_format($posSidebarStockCount / 1000, 1) . 'k' : number_format($posSidebarStockCount);
$posSidebarMovementCount = isset($stockMovementLog) && is_array($stockMovementLog) ? count($stockMovementLog) : 0;
$posActiveLogisticsView = strtolower(trim((string)($_GET['view'] ?? 'dispatch')));
if (!in_array($posActiveLogisticsView, ['dispatch', 'returns', 'pod'], true)) {
    $posActiveLogisticsView = 'dispatch';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empress Tee Beverage Depot POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; color: #121212; height: 100vh; overflow: hidden; }

        /* Full Screen POS Layout */
        .pos-app-layout {
            display: grid;
            grid-template-columns: 216px 1fr;
            grid-template-rows: 1fr 40px;
            height: 100vh;
            width: 100%;
        }

        .pos-sidebar {
            background:
                radial-gradient(circle at 30% 0%, rgba(255, 213, 118, 0.14), transparent 30%),
                linear-gradient(180deg, #5b1027 0%, #3f0915 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.35rem 0.9rem 1rem;
            grid-row: 1 / 3;
            box-shadow: 8px 0 28px rgba(63,9,21,0.18);
            z-index: 10;
        }

        .sidebar-brand {
            display: grid;
            justify-items: center;
            gap: 0.35rem;
            margin-bottom: 2rem;
            padding: 0 0.5rem;
            text-align: center;
        }
        .sidebar-brand-icon {
            width: 76px;
            height: 76px;
            border: 2px solid rgba(245, 202, 108, 0.75);
            border-radius: 50%;
            color: #f5ca6c;
            display: grid;
            place-items: center;
            font-family: Georgia, serif;
            font-size: 2.6rem;
            line-height: 1;
        }
        .sidebar-brand-icon.logo-sidebar-image {
            width: 86px;
            height: 86px;
            padding: 0.22rem;
            border-radius: 18px;
            background: transparent;
            border-color: transparent;
        }
        .sidebar-brand-icon.logo-sidebar-image img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
        }
        .sidebar-brand-text h2 {
            font-family: Georgia, serif;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            line-height: 1.1;
            color: #f5ca6c;
        }
        .sidebar-brand-text small {
            font-size: 0.66rem;
            color: #f5ca6c;
            font-weight: 600;
            letter-spacing: 0.28em;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .sidebar-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.72rem 0.85rem;
            border-radius: 8px;
            color: #fff7ed;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        .sidebar-menu-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .sidebar-menu-item.active {
            background: rgba(139, 23, 58, 0.92);
            color: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);
            font-weight: 700;
        }

        .sidebar-user-box {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            padding: 0.85rem;
            margin-top: auto;
        }
        .user-box-top {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 0.5rem;
        }
        .user-avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #8b173a;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .user-box-info div {
            font-size: 0.8rem;
            font-weight: 700;
            color: #ffffff;
        }
        .user-box-info small {
            font-size: 0.7rem;
            color: #94a3b8;
        }
        .btn-logout-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: #f87171;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 700;
            margin-top: 0.25rem;
        }

        /* Main Workspace Container */
        .pos-main-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 40px);
            overflow-y: auto;
            background: #fafafa;
        }

        /* Top Header Search Bar */
        .pos-top-bar {
            background: #ffffff;
            padding: 1.05rem 1.5rem;
            border-bottom: 1px solid #eceff3;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .pos-search-wrapper {
            position: relative;
            flex: 1;
            max-width: 760px;
        }
        .pos-search-wrapper input {
            width: 100%;
            padding: 0.74rem 1rem 0.74rem 2.6rem;
            border: 1px solid #d7dce3;
            border-radius: 8px;
            font-size: 0.9rem;
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 2px rgba(15,23,42,0.03);
        }
        .pos-search-icon {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .pos-scan-btn {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: #fff7f8;
            border: 1px solid #ead4dc;
            color: #7a1734;
            padding: 0.34rem 0.62rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }
        .top-right-tools {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .bell-btn {
            position: relative;
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
        }
        .bell-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ef4444;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cashier-profile-pill {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            padding: 0.35rem 0.75rem 0.35rem 0.4rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
        }

        /* Simple POS Summary */
        .metrics-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
            padding: 1rem 1.5rem 0 1.5rem;
        }
        .metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 6px 18px rgba(15,23,42,0.06);
        }
        .metric-icon-box {
            width: 56px;
            height: 56px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }
        .metric-info label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .metric-info h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.38rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0.1rem 0;
        }
        .metric-info span {
            font-size: 0.7rem;
            font-weight: 700;
        }

        /* Main Workspace Grid */
        .workspace-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 408px;
            gap: 1rem;
            padding: 1rem 1.5rem 1.25rem 1.5rem;
            align-items: start;
        }

        /* Category Filter Pills */
        .cat-pills-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
            overflow-x: auto;
        }
        .cat-pill {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 0.5rem 0.95rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.15s ease;
        }
        .cat-pill.active {
            background: #7a1734;
            color: #ffffff;
            border-color: #7a1734;
            box-shadow: 0 6px 16px rgba(122,23,52,0.18);
        }
        .pos-inventory-strip { display: none; }
        .pos-inventory-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        .pos-inventory-head h3 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 0.98rem;
            color: #0f172a;
        }
        .pos-inventory-head span {
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            padding: 0.25rem 0.55rem;
            font-size: 0.7rem;
            font-weight: 900;
            white-space: nowrap;
        }
        .pos-stock-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.55rem;
            max-height: 160px;
            overflow-y: auto;
            padding-right: 0.2rem;
        }
        .pos-stock-row {
            border: 1px solid #e8eef7;
            border-radius: 9px;
            padding: 0.55rem;
            background: #fbfdff;
            min-width: 0;
        }
        .pos-stock-row strong {
            display: block;
            color: #0f172a;
            font-size: 0.74rem;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pos-stock-row span {
            display: block;
            margin-top: 0.25rem;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 800;
        }
        .stock-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.16rem 0.38rem;
            font-size: 0.58rem;
            font-weight: 900;
            margin-bottom: 0.35rem;
            white-space: nowrap;
        }
        .stock-good { background: #dcfce7; color: #047857; }
        .stock-low { background: #fff7ed; color: #c2410c; }
        .stock-out { background: #fee2e2; color: #dc2626; }

        /* Product Grid Cards */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(154px, 1fr));
            gap: 0.65rem;
        }
        .product-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.72rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 6px 18px rgba(15,23,42,0.05);
            transition: all 0.2s ease;
            min-height: 248px;
        }
        .product-card:hover {
            border-color: #8b173a;
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(122,23,52,0.12);
        }
        .product-img-box {
            background: #f8fafc;
            border-radius: 6px;
            height: 88px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.45rem;
            position: relative;
            overflow: hidden;
        }
        .product-img-box span {
            font-size: 2.2rem;
        }
        .product-card-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.78rem;
            color: #0f172a;
            margin-bottom: 0.18rem;
            line-height: 1.15;
        }
        .product-card-sub {
            font-size: 0.68rem;
            color: #64748b;
            margin-bottom: 0.45rem;
            line-height: 1.25;
        }
        .product-card-price {
            font-weight: 800;
            font-size: 0.96rem;
            color: #059669;
            margin-bottom: 0.45rem;
        }
        .product-action-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.32rem;
        }
        .stepper-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0.16rem 0.35rem;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .stepper-btn {
            background: none;
            border: none;
            color: #475569;
            font-weight: 800;
            cursor: pointer;
            padding: 0 0.2rem;
        }
        .btn-add-cart {
            background: #7a1734;
            color: #ffffff;
            border: none;
            padding: 0.5rem 0.25rem;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.74rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-add-cart:hover {
            background: #5b1027;
        }
        .btn-add-cart:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        /* Pagination Controls */
        .pagination-box { display: none; }
        .page-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .page-btn.active {
            background: #0284c7;
            color: #fff;
            border-color: #0284c7;
        }

        /* Right Cart Panel */
        .cart-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 10px 28px rgba(15,23,42,0.08);
            position: sticky;
            top: 1rem;
        }
        .cart-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }
        .cart-panel-header h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-clear-cart {
            background: none;
            border: none;
            color: #ef4444;
            font-weight: 700;
            font-size: 0.78rem;
            cursor: pointer;
        }
        .customer-select-box {
            display: grid;
            gap: 0.55rem;
            margin-bottom: 1rem;
        }
        .customer-field-label {
            display: block;
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .customer-select-box select,
        .customer-select-box input[type="text"] {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #0f172a;
            background: #f8fafc;
        }
        .save-customer-row {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .save-customer-row input {
            width: 16px;
            height: 16px;
        }
        .cart-item-list {
            min-height: 180px;
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
            border-top: 1px solid #f1f5f9;
        }
        .cart-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.82rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .cart-item-info {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .cart-item-icon {
            width: 42px;
            height: 42px;
            border-radius: 6px;
            overflow: hidden;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            flex: 0 0 auto;
        }
        .cart-item-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .cart-item-icon span,
        .product-image-placeholder {
            width: 42px;
            height: 42px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #94a3b8;
            font-weight: 800;
        }
        .cart-item-name {
            font-size: 0.78rem;
            font-weight: 700;
            color: #0f172a;
        }
        .cart-item-sub {
            font-size: 0.72rem;
            color: #64748b;
        }
        .cart-item-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .cart-item-qty {
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
        }
        .cart-item-price {
            font-size: 0.85rem;
            font-weight: 800;
            color: #0f172a;
        }
        .cart-item-del {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .cart-financial-summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .fin-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 0.35rem;
        }
        .fin-row.total {
            font-size: 1.25rem;
            font-weight: 800;
            color: #7a1734;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 2px solid #cbd5e1;
        }

        .pay-methods-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.45rem;
            margin-bottom: 1rem;
        }
        .pay-btn-item {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 0.65rem 0.2rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
        }
        .pay-btn-item.active {
            background: #7a1734;
            color: #ffffff;
            border-color: #7a1734;
        }

        .btn-complete-sale {
            width: 100%;
            background: linear-gradient(135deg, #8b173a, #6b102d);
            color: #ffffff;
            border: none;
            padding: 0.85rem;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(122,23,52,0.28);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .pos-dashboard-extra {
            display: grid;
            grid-template-columns: 0.9fr 1.4fr;
            gap: 0.85rem;
            margin-top: 1rem;
        }
        .dashboard-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 8px 22px rgba(15,23,42,0.05);
            min-width: 0;
        }
        .dashboard-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .dashboard-panel-head h3 {
            margin: 0;
            color: #111827;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .dashboard-panel-head a,
        .dashboard-panel-head span {
            color: #7a1734;
            font-size: 0.75rem;
            font-weight: 800;
            text-decoration: none;
        }
        .top-selling-row,
        .recent-sale-row {
            display: grid;
            align-items: center;
            gap: 0.65rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid #eef2f7;
            font-size: 0.78rem;
        }
        .top-selling-row {
            grid-template-columns: 22px 40px minmax(0, 1fr) auto;
        }
        .recent-sale-row {
            grid-template-columns: 34px minmax(0, 1fr) 76px auto;
        }
        .top-selling-row:last-child,
        .recent-sale-row:last-child {
            border-bottom: 0;
        }
        .top-selling-row img,
        .top-selling-row .product-image-placeholder,
        .recent-sale-icon {
            width: 34px;
            height: 34px;
            border-radius: 7px;
            object-fit: cover;
            background: #fff7ed;
            border: 1px solid #eef2f7;
        }
        .recent-sale-icon {
            display: grid;
            place-items: center;
            color: #7a1734;
            font-weight: 900;
        }
        .top-selling-row strong,
        .recent-sale-row strong {
            display: block;
            color: #111827;
            font-size: 0.78rem;
        }
        .top-selling-row span,
        .recent-sale-row span {
            display: block;
            color: #6b7280;
            font-size: 0.7rem;
            margin-top: 0.1rem;
        }
        .payment-chip {
            display: inline-flex;
            justify-content: center;
            border-radius: 5px;
            padding: 0.18rem 0.45rem;
            background: #dcfce7;
            color: #047857;
            font-size: 0.66rem;
            font-weight: 800;
        }

        /* 7. Bottom Status Bar */
        .pos-bottom-status {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.72rem;
            color: #64748b;
            grid-column: 2 / 3;
            grid-row: 2 / 3;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }

        /* Receipt Modal */
        .receipt-modal {
            position: fixed;
            inset: 0;
            background: rgba(8, 13, 27, 0.76);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1.25rem;
            overflow: auto;
        }
        .receipt-modal-panel {
            width: min(1120px, 100%);
            max-height: calc(100vh - 2.5rem);
            overflow: auto;
            background: #f4f7fb;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow: 0 28px 90px rgba(15,23,42,0.38);
        }
        .receipt-modal-toolbar {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1rem;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }
        .receipt-modal-toolbar strong {
            display: block;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
        }
        .receipt-modal-toolbar span {
            display: block;
            color: #64748b;
            margin-top: 0.1rem;
            font-size: 0.78rem;
        }
        .receipt-toolbar-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .receipt-print-btn,
        .receipt-close-btn {
            border: 0;
            border-radius: 10px;
            padding: 0.62rem 0.9rem;
            font-weight: 900;
            cursor: pointer;
            transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease;
        }
        .receipt-print-btn:hover,
        .receipt-close-btn:hover {
            transform: translateY(-1px);
        }
        .receipt-print-btn {
            background: linear-gradient(135deg, #8b0f35, #5f0925);
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(139,15,53,0.22);
        }
        .receipt-close-btn {
            background: #fee2e2;
            color: #b91c1c;
        }
        .receipt-sheets {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            padding: 1rem;
        }
        .receipt-paper {
            background: #ffffff;
            color: #0f172a;
            border: 1px solid #d9e3ef;
            border-radius: 12px;
            padding: 1rem;
            font-family: 'Inter', Arial, sans-serif;
            box-shadow: 0 14px 34px rgba(15,23,42,0.09);
            position: relative;
            overflow: hidden;
        }
        .receipt-paper::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 6px;
            background: linear-gradient(90deg, #8b0f35, #f97316);
        }
        .receipt-paper.office-copy {
            border-top: 0;
        }
        .receipt-paper.customer-copy {
            border-top: 0;
        }
        .receipt-copy-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.3rem 0.75rem;
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            color: #ffffff;
            background: #7f1231;
            text-transform: uppercase;
            margin-top: 0.35rem;
            box-shadow: 0 8px 18px rgba(127,18,49,0.18);
        }
        .receipt-paper.office-copy .receipt-copy-label {
            background: #0f3b6f;
        }
        .receipt-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.8rem;
            align-items: start;
            padding: 0.9rem 0 0.85rem;
            border-bottom: 1px solid #dbe4f0;
        }
        .receipt-brand {
            display: flex;
            gap: 0.7rem;
            align-items: center;
        }
        .receipt-logo {
            width: 62px;
            height: 62px;
            border-radius: 14px;
            background: transparent;
            color: #ffffff;
            display: grid;
            place-items: center;
            font-weight: 900;
            font-family: 'Outfit', sans-serif;
            border: 0;
            padding: 0.18rem;
            overflow: hidden;
        }
        .receipt-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .receipt-brand h3 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1.02rem;
            color: #0f172a;
            letter-spacing: 0.02em;
        }
        .receipt-brand p,
        .receipt-meta span,
        .receipt-note {
            margin: 0.16rem 0 0;
            color: #64748b;
            font-size: 0.72rem;
            line-height: 1.45;
        }
        .receipt-meta {
            text-align: right;
            min-width: 150px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.55rem 0.65rem;
            background: #f8fafc;
        }
        .receipt-meta strong {
            color: #0f172a;
            display: block;
            font-size: 0.84rem;
        }
        .receipt-business-lines {
            display: grid;
            gap: 0.12rem;
            margin-top: 0.42rem;
            color: #64748b;
            font-size: 0.68rem;
            line-height: 1.35;
        }
        .receipt-summary-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.5rem;
            margin: 0.75rem 0 0.2rem;
        }
        .receipt-summary-strip div {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 0.52rem 0.6rem;
        }
        .receipt-summary-strip span {
            display: block;
            color: #64748b;
            font-size: 0.62rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .receipt-summary-strip strong {
            display: block;
            margin-top: 0.14rem;
            font-size: 0.88rem;
            color: #0f172a;
        }
        .receipt-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem;
            margin: 0.8rem 0;
        }
        .receipt-info-cell {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.55rem;
            background: #f8fafc;
        }
        .receipt-info-cell span {
            display: block;
            color: #64748b;
            font-size: 0.66rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .receipt-info-cell strong {
            display: block;
            margin-top: 0.16rem;
            color: #0f172a;
            font-size: 0.82rem;
        }
        .receipt-info-cell small {
            display: block;
            color: #64748b;
            font-size: 0.7rem;
            margin-top: 0.12rem;
        }
        .receipt-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.76rem;
            margin: 0.85rem 0;
        }
        .receipt-items th {
            text-align: left;
            color: #334155;
            font-size: 0.68rem;
            text-transform: uppercase;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 0.5rem 0.25rem;
            background: #f8fafc;
        }
        .receipt-items td {
            padding: 0.58rem 0.25rem;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
        }
        .receipt-items td strong,
        .receipt-items td span {
            display: block;
        }
        .receipt-items td span {
            color: #64748b;
            font-size: 0.68rem;
            margin-top: 0.12rem;
            line-height: 1.35;
        }
        .receipt-items th:nth-child(2),
        .receipt-items td:nth-child(2) {
            text-align: center;
        }
        .receipt-items th:nth-child(3),
        .receipt-items td:nth-child(3),
        .receipt-items th:nth-child(4),
        .receipt-items td:nth-child(4) {
            text-align: right;
        }
        .receipt-totals {
            display: grid;
            gap: 0.35rem;
            margin-top: 0.7rem;
            margin-left: auto;
            max-width: 320px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.72rem;
            background: #fbfdff;
        }
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.82rem;
            color: #334155;
        }
        .receipt-total-row.grand {
            border-top: 2px solid #7f1231;
            padding-top: 0.55rem;
            margin-top: 0.25rem;
            font-size: 1.04rem;
            font-weight: 900;
            color: #7f1231;
        }
        .receipt-office-panel {
            border: 1px solid #cfe0f6;
            border-radius: 10px;
            background: #f7fbff;
            padding: 0.7rem;
            margin-top: 0.85rem;
        }
        .receipt-office-panel h4 {
            margin: 0 0 0.55rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            color: #0f3b6f;
            letter-spacing: 0.04em;
        }
        .receipt-office-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.42rem;
        }
        .receipt-office-grid div {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 0.45rem 0.5rem;
        }
        .receipt-office-grid span {
            display: block;
            color: #64748b;
            font-size: 0.62rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .receipt-office-grid strong {
            display: block;
            margin-top: 0.1rem;
            color: #0f172a;
            font-size: 0.78rem;
        }
        .receipt-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        .receipt-signature-line {
            border-top: 1px solid #94a3b8;
            padding-top: 0.3rem;
            color: #64748b;
            font-size: 0.68rem;
            text-align: center;
        }
        .receipt-footer {
            margin-top: 0.9rem;
            padding-top: 0.7rem;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
        }
        .receipt-footer strong {
            display: block;
            color: #0f172a;
            font-size: 0.78rem;
        }
        .receipt-footer span {
            display: block;
            color: #64748b;
            font-size: 0.72rem;
            line-height: 1.45;
            margin-top: 0.18rem;
        }
        .receipt-verification {
            margin-top: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            color: #64748b;
            font-size: 0.66rem;
        }
        .receipt-code-box {
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            padding: 0.4rem 0.52rem;
            color: #0f172a;
            font-weight: 900;
            letter-spacing: 0.04em;
            background: #ffffff;
            white-space: nowrap;
        }
        @media (max-width: 860px) {
            .receipt-sheets {
                grid-template-columns: 1fr;
            }
            .receipt-modal-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .receipt-header,
            .receipt-info-grid,
            .receipt-summary-strip {
                grid-template-columns: 1fr;
            }
            .receipt-meta {
                text-align: left;
            }
        }
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            body * {
                visibility: hidden !important;
            }
            #receiptModal,
            #receiptModal * {
                visibility: visible !important;
            }
            #receiptModal {
                position: absolute;
                inset: 0;
                background: #ffffff;
                padding: 0;
                overflow: visible;
            }
            .receipt-modal-panel {
                max-height: none;
                width: 100%;
                overflow: visible;
                border: 0;
                box-shadow: none;
                border-radius: 0;
                background: #ffffff;
            }
            .receipt-modal-toolbar {
                display: none;
            }
            .receipt-sheets {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8mm;
                padding: 0;
            }
            .receipt-paper {
                box-shadow: none;
                border-radius: 0;
                border: 1px solid #cbd5e1;
                page-break-inside: avoid;
                page-break-after: auto;
                padding: 8mm;
                font-size: 10pt;
            }
            .receipt-paper::before {
                height: 4px;
            }
            .receipt-items {
                font-size: 8pt;
            }
            .receipt-info-cell,
            .receipt-totals,
            .receipt-office-panel,
            .receipt-summary-strip div {
                break-inside: avoid;
            }
        }
        .transfer-screen {
            padding: 1.5rem;
            display: grid;
            gap: 1rem;
        }
	        .transfer-summary {
	            display: grid;
	            grid-template-columns: repeat(4, minmax(0, 1fr));
	            gap: 1rem;
	        }
        .transfer-card,
        .transfer-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 8px 22px rgba(15,23,42,0.04);
        }
        .transfer-card small {
            display: block;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .transfer-card strong {
            display: block;
            margin-top: 0.3rem;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
        }
        .transfer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
        }
        .transfer-table th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            padding: 0.75rem;
            font-size: 0.7rem;
            text-transform: uppercase;
        }
        .transfer-table td {
            border-top: 1px solid #e8eef7;
            padding: 0.75rem;
            color: #0f172a;
        }
        .transfer-status {
            display: inline-flex;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            padding: 0.25rem 0.55rem;
            font-size: 0.7rem;
            font-weight: 900;
        }
        .transfer-status.pending {
            background: #fff7ed;
            color: #c2410c;
            border-color: #fed7aa;
        }
        .transfer-status.confirmed {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .transfer-action-btn {
            border: none;
            border-radius: 7px;
            padding: 0.42rem 0.65rem;
            font-size: 0.72rem;
            font-weight: 900;
            cursor: pointer;
            white-space: nowrap;
        }
        .transfer-action-btn.confirm {
            background: #059669;
            color: #ffffff;
        }
        .transfer-action-btn.approve {
            background: #1d4ed8;
            color: #ffffff;
        }
        .transfer-action-btn:disabled {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }
        .stock-movement-grid {
            display: grid;
            grid-template-columns: minmax(320px, 0.85fr) minmax(420px, 1.15fr);
            gap: 1rem;
            align-items: start;
        }
        .stock-movement-form {
            display: grid;
            gap: 0.85rem;
        }
        .stock-movement-form label {
            display: block;
            color: #334155;
            font-size: 0.72rem;
            font-weight: 900;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }
        .stock-movement-form select,
        .stock-movement-form input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.68rem 0.75rem;
            color: #0f172a;
            background: #ffffff;
            font-size: 0.84rem;
            outline: none;
        }
        .stock-movement-form select:focus,
        .stock-movement-form input:focus {
            border-color: #8b1238;
            box-shadow: 0 0 0 3px rgba(139,18,56,.1);
        }
        .stock-movement-note {
            border: 1px solid #dbeafe;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.78rem;
            font-weight: 800;
        }
        .stock-movement-submit {
            border: 0;
            border-radius: 9px;
            background: linear-gradient(135deg, #7a102f, #a0163d);
            color: #ffffff;
            padding: 0.8rem 1rem;
            font-weight: 900;
            cursor: pointer;
        }
        .movement-status.completed {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .movement-status.failed {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .transfer-dash-panel {
            margin: 0.85rem 1.5rem 0 1.5rem;
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 8px 22px rgba(15,23,42,0.035);
        }
        .transfer-dash-head,
        .transfer-dash-row {
            display: grid;
            grid-template-columns: 1fr 0.7fr 0.8fr 0.9fr auto;
            gap: 0.75rem;
            align-items: center;
        }
        .transfer-dash-head {
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 900;
            text-transform: uppercase;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e8eef7;
        }
        .transfer-dash-row {
            padding: 0.65rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.82rem;
        }
        .transfer-dash-row:last-child { border-bottom: none; }
        .customer-summary-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        .customer-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 0.85rem;
        }
        .customer-contact-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.9rem;
            display: grid;
            gap: 0.65rem;
            box-shadow: 0 6px 18px rgba(15,23,42,0.035);
        }
        .customer-contact-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .customer-contact-top strong {
            display: block;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            font-size: 0.98rem;
            line-height: 1.2;
        }
        .customer-phone-pill {
            display: inline-flex;
            color: #0369a1;
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            border-radius: 999px;
            padding: 0.22rem 0.5rem;
            font-size: 0.72rem;
            font-weight: 900;
            white-space: nowrap;
        }
        .customer-contact-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem;
        }
        .customer-contact-meta div {
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            padding: 0.55rem;
        }
        .customer-contact-meta span {
            display: block;
            color: #64748b;
            font-size: 0.66rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .customer-contact-meta b {
            display: block;
            margin-top: 0.16rem;
            color: #0f172a;
            font-size: 0.82rem;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 1rem;
        }
        .settings-form {
            display: grid;
            gap: 0.85rem;
        }
        .settings-field label {
            display: block;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }
        .settings-field {
            min-width: 0;
        }
        .settings-field input,
        .settings-field textarea,
        .settings-field select {
            width: 100%;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.68rem;
            color: #0f172a;
            font-size: 0.9rem;
            background: #ffffff;
        }
        .settings-field select {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .settings-field textarea {
            min-height: 92px;
            resize: vertical;
        }
        .settings-toggle {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: #334155;
            font-size: 0.86rem;
            font-weight: 700;
        }
        .settings-toggle input {
            width: 18px;
            height: 18px;
        }
        .pos-mobile-logo,
        .pos-mobile-actions,
        .pos-mobile-bottom-nav,
        .pos-mobile-menu-btn {
            display: none;
        }

        @media (max-width: 1180px) {
            body {
                height: auto;
                overflow: auto;
            }
            .pos-app-layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr auto;
                height: auto;
                min-height: 100vh;
            }
            .pos-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: min(318px, 88vw);
                max-width: calc(100vw - 1.25rem);
                height: 100dvh;
                max-height: 100dvh;
                z-index: 90;
                transform: translateX(-104%);
                visibility: hidden;
                pointer-events: none;
                overflow-y: auto;
                overscroll-behavior: contain;
                transition: transform 0.22s ease, visibility 0.22s ease;
                box-shadow: 28px 0 56px rgba(63, 9, 21, 0.28);
            }
            .pos-sidebar-open .pos-sidebar {
                transform: translateX(0);
                visibility: visible;
                pointer-events: auto;
            }
            .pos-sidebar-backdrop {
                display: none;
            }
            .pos-sidebar-open .pos-sidebar-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 80;
                background: rgba(15, 23, 42, 0.58);
                backdrop-filter: blur(2px);
            }
            .sidebar-brand,
            .sidebar-user-box {
                margin: 0;
            }
            .sidebar-menu {
                min-width: 0;
            }
            .pos-main-container {
                height: auto;
                overflow: visible;
            }
            .pos-bottom-status {
                grid-column: auto;
                grid-row: auto;
                flex-wrap: wrap;
                gap: 0.5rem;
                padding: 0.6rem 1rem;
            }
            .pos-mobile-menu-btn {
                display: inline-flex;
            }
            .workspace-grid {
                grid-template-columns: 1fr;
            }
            .pos-dashboard-extra {
                grid-template-columns: 1fr;
            }
            .settings-grid {
                grid-template-columns: 1fr;
            }
            .cart-panel {
                position: static;
            }
        }
        @media (max-width: 760px) {
            .pos-top-bar {
                align-items: stretch;
                flex-direction: column;
            }
            .pos-search-wrapper {
                max-width: none;
            }
            .top-right-tools {
                display: none;
            }
            .metrics-row {
                grid-template-columns: 1fr;
            }
            .customer-summary-row,
            .customer-contact-meta {
                grid-template-columns: 1fr;
            }
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
            }
            .product-action-row {
                grid-template-columns: 1fr;
            }
            .pay-methods-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
	        @media (max-width: 800px) {
	            .transfer-summary { grid-template-columns: 1fr; }
	            .transfer-panel { overflow-x: auto; }
		            .transfer-table { min-width: 1120px; }
	            .transfer-dash-panel { overflow-x: auto; }
	            .transfer-dash-head,
	            .transfer-dash-row { min-width: 760px; }
	        }
	        .pos-app-layout.pos-admin-shell {
	            grid-template-columns: 224px 1fr;
	            grid-template-rows: 1fr;
	            background: #f8fafc;
	            color: #0f172a;
	        }
	        .pos-sidebar-backdrop {
	            display: none;
	        }
	        .pos-admin-shell .pos-sidebar {
	            height: 100vh;
	            max-height: 100vh;
	            display: flex;
	            flex-direction: column;
	            overflow: hidden;
	            background: #061a35;
	            background-image:
	                linear-gradient(180deg, rgba(59,130,246,0.11) 0%, rgba(7,31,63,0) 34%),
	                linear-gradient(180deg, #082346 0%, #04152d 100%);
	            border-right: 1px solid rgba(148,163,184,0.14);
	            box-shadow: 14px 0 34px rgba(2,8,23,0.14);
	            padding: 1.1rem 0.72rem;
	            grid-row: auto;
	            transition: width 0.2s ease, transform 0.22s ease, box-shadow 0.2s ease;
	        }
	        .pos-admin-shell .pos-sidebar > div:first-child {
	            flex: 1 1 auto;
	            min-height: 0;
	            overflow-y: auto;
	            overflow-x: hidden;
	            padding-bottom: 0.75rem;
	            overscroll-behavior: contain;
	            scrollbar-width: thin;
	        }
	        .pos-admin-shell .pos-sidebar > div:first-child::-webkit-scrollbar {
	            width: 6px;
	        }
	        .pos-admin-shell .pos-sidebar > div:first-child::-webkit-scrollbar-thumb {
	            background: rgba(148,163,184,0.28);
	            border-radius: 999px;
	        }
	        .pos-admin-shell .sidebar-collapse-toggle {
	            width: 100%;
	            height: 32px;
	            border: 1px solid rgba(226,232,240,0.12);
	            border-radius: 10px;
	            background: rgba(255,255,255,0.055);
	            color: #dce7f7;
	            display: inline-flex;
	            align-items: center;
	            justify-content: center;
	            cursor: pointer;
	            margin: 0 0 0.72rem;
	            transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease;
	        }
	        .pos-admin-shell .sidebar-collapse-toggle:hover {
	            background: rgba(255,255,255,0.1);
	            border-color: rgba(147,197,253,0.2);
	            color: #ffffff;
	        }
	        .pos-admin-shell .sidebar-brand {
	            display: flex;
	            justify-items: start;
	            text-align: left;
	            align-items: center;
	            gap: 0.75rem;
	            margin-bottom: 0.62rem;
	            padding: 0.32rem 0.45rem 1rem;
	            border-bottom: 1px solid rgba(226,232,240,0.1);
	        }
	        .pos-admin-shell .sidebar-brand-icon {
	            width: 34px;
	            min-width: 34px;
	            height: 34px;
	            border-radius: 10px;
	            color: #fbbf24;
	            background: linear-gradient(145deg, rgba(251,191,36,0.22), rgba(251,191,36,0.06));
	            border: 1px solid rgba(251,191,36,0.28);
	            box-shadow: inset 0 1px 0 rgba(255,255,255,0.12), 0 10px 22px rgba(2,8,23,0.18);
	            display: inline-flex;
	            align-items: center;
	            justify-content: center;
	            font-size: 1.18rem;
	        }
	        .pos-admin-shell .sidebar-brand-icon.logo-sidebar-image {
	            width: 42px;
	            min-width: 42px;
	            height: 42px;
	            padding: 0.18rem;
	            border-radius: 12px;
	            background: transparent;
	            border: 0;
	        }
	        .pos-admin-shell .sidebar-brand-text h2 {
	            color: #ffffff;
	            font-family: 'Outfit', sans-serif;
	            font-size: 0.86rem;
	            letter-spacing: 0;
	            line-height: 1.05;
	        }
	        .pos-admin-shell .sidebar-brand-text small {
	            color: #9fb4d2;
	            font-size: 0.58rem;
	            letter-spacing: 0.08em;
	        }
	        .pos-admin-shell .sidebar-section-title {
	            color: #89a1c3;
	            font-size: 0.56rem;
	            font-weight: 800;
	            letter-spacing: 0.08em;
	            margin: 0.88rem 0.54rem 0.36rem;
	            text-transform: uppercase;
	        }
	        .pos-admin-shell .sidebar-menu {
	            display: grid;
	            gap: 0.22rem;
	        }
	        .pos-admin-shell .sidebar-menu-item,
	        .pos-admin-shell .sidebar-menu-subitem {
	            display: flex;
	            align-items: center;
	            border: 1px solid transparent;
	            border-radius: 9px;
	            color: #dce7f7;
	            font-size: 0.72rem;
	            font-weight: 700;
	            gap: 0.68rem;
	            line-height: 1.12;
	            padding: 0.58rem 0.66rem 0.58rem 0.72rem;
	            position: relative;
	            overflow: hidden;
	            text-decoration: none;
	            transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease, transform 0.16s ease, box-shadow 0.16s ease;
	        }
	        .pos-admin-shell .sidebar-menu-subitem {
	            margin-left: 0.52rem;
	            padding-left: 0.62rem;
	            font-size: 0.69rem;
	            color: #bfd0e9;
	        }
	        .pos-admin-shell .sidebar-submenu {
	            display: grid;
	            grid-template-rows: 0fr;
	            max-height: 0;
	            opacity: 0;
	            overflow: hidden;
	            margin: 0;
	            padding-left: 0;
	            border-left: 0 solid transparent;
	            transition: grid-template-rows 0.2s ease, max-height 0.2s ease, opacity 0.16s ease, margin 0.2s ease, padding 0.2s ease, border-color 0.2s ease;
	        }
	        .pos-admin-shell .sidebar-submenu.open {
	            grid-template-rows: 1fr;
	            max-height: 720px;
	            opacity: 1;
	            margin: 0.1rem 0 0.32rem 0.52rem;
	            padding-left: 0.45rem;
	            border-left: 1px solid rgba(148,163,184,0.18);
	        }
	        .pos-admin-shell .sidebar-submenu > * {
	            min-height: 0;
	        }
	        .pos-admin-shell .sidebar-submenu-toggle {
	            width: 100%;
	            background: transparent;
	            cursor: pointer;
	            text-align: left;
	        }
	        .pos-admin-shell .sidebar-submenu-toggle .wa-svg-icon:last-child {
	            margin-left: auto;
	            width: 14px;
	            min-width: 14px;
	            height: 14px;
	            opacity: 0.72;
	            transition: transform 0.2s ease;
	        }
	        .pos-admin-shell .sidebar-submenu-toggle[aria-expanded="true"] .wa-svg-icon:last-child {
	            transform: rotate(180deg);
	        }
	        .pos-admin-shell .sidebar-menu-item span,
	        .pos-admin-shell .sidebar-menu-subitem span,
	        .pos-admin-shell .wa-svg-icon {
	            width: 18px;
	            min-width: 18px;
	            height: 18px;
	            display: inline-flex;
	            align-items: center;
	            justify-content: center;
	            color: inherit;
	        }
	        .pos-admin-shell .wa-svg-icon svg {
	            width: 16px;
	            height: 16px;
	            fill: none;
	            stroke: currentColor;
	            stroke-width: 2;
	            stroke-linecap: round;
	            stroke-linejoin: round;
	        }
	        .pos-admin-shell .nav-count-badge {
	            margin-left: auto;
	            min-width: 18px;
	            height: 18px;
	            border-radius: 999px;
	            padding: 0 0.42rem;
	            display: inline-flex;
	            align-items: center;
	            justify-content: center;
	            background: rgba(251,191,36,0.16);
	            border: 1px solid rgba(251,191,36,0.28);
	            color: #fde68a;
	            font-size: 0.58rem;
	            font-weight: 900;
	        }
	        .pos-admin-shell .sidebar-menu-item:hover,
	        .pos-admin-shell .sidebar-menu-subitem:hover {
	            background: rgba(255,255,255,0.075);
	            border-color: rgba(147,197,253,0.12);
	            color: #ffffff;
	            transform: translateX(2px);
	        }
	        .pos-admin-shell .sidebar-menu-item.active,
	        .pos-admin-shell .sidebar-menu-subitem.active {
	            background: linear-gradient(90deg, rgba(37,99,235,0.98), rgba(59,130,246,0.76));
	            border-color: rgba(147,197,253,0.28);
	            color: #ffffff;
	            font-weight: 800;
	            box-shadow: 0 12px 24px rgba(37,99,235,0.24), inset 0 1px 0 rgba(255,255,255,0.16);
	        }
	        .pos-admin-shell .sidebar-menu-item.active::before,
	        .pos-admin-shell .sidebar-menu-subitem.active::before {
	            content: "";
	            position: absolute;
	            left: 0;
	            top: 8px;
	            bottom: 8px;
	            width: 3px;
	            border-radius: 0 999px 999px 0;
	            background: #fbbf24;
	        }
	        .pos-admin-shell .sidebar-menu-item.active .wa-svg-icon,
	        .pos-admin-shell .sidebar-menu-subitem.active .wa-svg-icon {
	            color: #ffffff;
	            filter: drop-shadow(0 3px 7px rgba(15,23,42,0.22));
	        }
	        .pos-admin-shell .sidebar-user-box {
	            background: linear-gradient(145deg, rgba(255,255,255,0.1), rgba(255,255,255,0.045));
	            border-color: rgba(226,232,240,0.12);
	            border-radius: 10px;
	            box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
	        }
	        .pos-admin-shell .admin-profile-box {
	            padding: 0.72rem;
	            margin: 1rem 0 0;
	            display: grid;
	            gap: 0.62rem;
	            flex: 0 0 auto;
	        }
	        .pos-admin-shell .admin-profile-main {
	            display: flex;
	            align-items: center;
	            gap: 0.62rem;
	            min-width: 0;
	        }
	        .pos-admin-shell .admin-profile-avatar {
	            width: 32px;
	            min-width: 32px;
	            height: 32px;
	            border-radius: 50%;
	            background: linear-gradient(145deg, #2563eb, #0f3c9a);
	            color: #ffffff;
	            display: inline-flex;
	            align-items: center;
	            justify-content: center;
	            font-weight: 900;
	            font-size: 0.68rem;
	            position: relative;
	        }
	        .pos-admin-shell .admin-profile-avatar span {
	            position: absolute;
	            width: 8px;
	            height: 8px;
	            right: 1px;
	            bottom: 1px;
	            background: #22c55e;
	            border: 2px solid #082346;
	            border-radius: 50%;
	        }
	        .pos-admin-shell .admin-profile-name {
	            color: #ffffff;
	            font-size: 0.72rem;
	            font-weight: 800;
	            line-height: 1.1;
	            overflow: hidden;
	            text-overflow: ellipsis;
	            white-space: nowrap;
	        }
	        .pos-admin-shell .admin-profile-role {
	            color: #a8bdd8;
	            font-size: 0.62rem;
	            font-weight: 700;
	        }
	        .pos-admin-shell .admin-profile-actions {
	            display: grid;
	            grid-template-columns: 1fr 1fr;
	            gap: 0.42rem;
	        }
	        .pos-admin-shell .admin-profile-action {
	            height: 30px;
	            border: 1px solid rgba(226,232,240,0.12);
	            border-radius: 8px;
	            background: rgba(255,255,255,0.055);
	            color: #dce7f7;
	            display: inline-flex;
	            align-items: center;
	            justify-content: center;
	            cursor: pointer;
	            text-decoration: none;
	            transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease;
	        }
	        .pos-admin-shell .admin-profile-action:hover,
	        .pos-admin-shell .admin-logout-link:hover {
	            background: rgba(255,255,255,0.1);
	            border-color: rgba(147,197,253,0.2);
	            color: #ffffff;
	        }
	        .pos-admin-shell .admin-logout-link {
	            border: 1px solid rgba(248,113,113,0.18);
	            border-radius: 8px;
	            color: #fca5a5;
	            font-size: 0.68rem;
	            margin-top: 0;
	            padding: 0.46rem 0.55rem;
	            justify-content: center;
	            transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease;
	        }
	        .pos-admin-shell .pos-main-container {
	            height: 100vh;
	            background: #f8fafc;
	        }
	        .pos-admin-shell .pos-top-bar {
	            min-height: 66px;
	            padding: 0.78rem 1.25rem;
	            border-bottom: 1px solid #e7edf5;
	        }
	        .pos-admin-shell .pos-top-bar h1 {
	            font-size: 1.05rem !important;
	        }
	        .pos-admin-shell .pos-top-bar p {
	            font-size: 0.66rem !important;
	        }
	        .pos-admin-shell .pos-search-wrapper {
	            max-width: 360px;
	        }
	        .pos-admin-shell .pos-search-wrapper input {
	            height: 36px;
	            padding-top: 0;
	            padding-bottom: 0;
	            font-size: 0.72rem;
	            border-color: #dbe4ef;
	        }
	        .pos-admin-shell .bell-btn {
	            width: 32px;
	            height: 32px;
	            background: #ffffff;
	        }
	        .pos-admin-shell .cashier-profile-pill {
	            font-size: 0.72rem;
	        }
	        .pos-admin-shell .transfer-screen {
	            padding: 1rem 1.25rem;
	            font-size: 0.82rem;
	        }
	        .pos-admin-shell .transfer-screen h2 {
	            font-size: 1.12rem !important;
	        }
	        .pos-admin-shell .transfer-panel h3 {
	            font-size: 0.95rem !important;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .pos-sidebar {
	            width: 72px;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-brand {
	            justify-content: center;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-brand-text,
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-section-title,
	        .pos-sidebar-collapsed .pos-admin-shell .user-box-info,
	        .pos-sidebar-collapsed .pos-admin-shell .admin-profile-actions,
	        .pos-sidebar-collapsed .pos-admin-shell .btn-logout-link span {
	            display: none !important;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-menu {
	            gap: 0.42rem;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-menu-item,
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-menu-subitem {
	            font-size: 0;
	            width: 42px;
	            height: 40px;
	            margin: 0 auto;
	            padding: 0;
	            justify-content: center;
	            gap: 0;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-submenu {
	            display: none;
	            margin-left: 0;
	            padding-left: 0;
	            border-left: 0;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .sidebar-submenu-toggle .wa-svg-icon:last-child {
	            display: none;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .nav-count-badge {
	            position: absolute;
	            top: 5px;
	            right: 5px;
	            min-width: 7px;
	            width: 7px;
	            height: 7px;
	            padding: 0;
	            border: 0;
	            color: transparent;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .admin-profile-box {
	            padding: 0.5rem;
	            justify-items: center;
	            gap: 0.45rem;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .user-box {
	            justify-content: center;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .admin-logout-link {
	            width: 42px;
	            height: 38px;
	            padding: 0;
	            margin: 0 auto;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell .admin-logout-link .wa-svg-icon {
	            margin: 0;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell [data-tooltip] {
	            position: relative;
	        }
	        .pos-sidebar-collapsed .pos-admin-shell [data-tooltip]:hover::after {
	            content: attr(data-tooltip);
	            position: absolute;
	            left: calc(100% + 12px);
	            top: 50%;
	            transform: translateY(-50%);
	            z-index: 50;
	            white-space: nowrap;
	            background: #0f172a;
	            color: #ffffff;
	            border: 1px solid rgba(148,163,184,0.24);
	            box-shadow: 0 14px 28px rgba(2,8,23,0.22);
	            border-radius: 8px;
	            padding: 0.46rem 0.62rem;
	            font-size: 0.7rem;
	            font-weight: 800;
	        }
	        @media (max-width: 1180px) {
	            .pos-app-layout.pos-admin-shell {
	                grid-template-columns: 1fr;
	            }
	            .pos-admin-shell .pos-sidebar {
	                position: fixed;
	                top: 0;
	                bottom: 0;
	                left: 0;
	                width: min(282px, 86vw);
	                height: 100dvh;
	                max-height: 100dvh;
	                z-index: 80;
	                transform: translateX(-104%);
	            }
	            .pos-sidebar-open .pos-admin-shell .pos-sidebar {
	                transform: translateX(0);
	            }
	            .pos-sidebar-open .pos-sidebar-backdrop {
	                display: block;
	                position: fixed;
	                inset: 0;
	                z-index: 70;
	                background: rgba(2,8,23,0.52);
	                backdrop-filter: blur(2px);
	            }
	            .pos-sidebar-collapsed .pos-admin-shell .pos-sidebar {
	                width: min(282px, 86vw);
	            }
	            .pos-sidebar-collapsed .pos-admin-shell .sidebar-brand-text,
	            .pos-sidebar-collapsed .pos-admin-shell .sidebar-section-title,
	            .pos-sidebar-collapsed .pos-admin-shell .user-box-info,
	            .pos-sidebar-collapsed .pos-admin-shell .btn-logout-link span {
	                display: block !important;
	            }
	            .pos-sidebar-collapsed .pos-admin-shell .admin-profile-actions {
	                display: grid !important;
	            }
	            .pos-sidebar-collapsed .pos-admin-shell .sidebar-submenu {
	                display: grid;
	            }
	            .pos-sidebar-collapsed .pos-admin-shell .sidebar-menu-item,
	            .pos-sidebar-collapsed .pos-admin-shell .sidebar-menu-subitem {
	                font-size: 0.72rem;
	                width: auto;
	                height: auto;
	                margin: 0;
	                padding: 0.58rem 0.66rem 0.58rem 0.72rem;
	                justify-content: flex-start;
	                gap: 0.68rem;
	            }
	            .pos-sidebar-collapsed .pos-admin-shell .admin-logout-link {
	                width: auto;
	                height: auto;
	                padding: 0.46rem 0.55rem;
	            }
	        }

        @media (max-width: 640px) {
            html {
                font-size: 14px;
            }

            body {
                height: auto;
                min-height: 100vh;
                overflow-x: hidden;
                overflow-y: auto;
                background: #f8fafc;
            }

            .pos-app-layout {
                display: block;
                width: 100%;
                max-width: 100%;
                min-height: 100vh;
                height: auto;
                overflow-x: hidden;
            }

            .pos-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: min(310px, 88vw);
                z-index: 90;
                transform: translateX(-104%);
                transition: transform 0.22s ease;
                overflow-y: auto;
                padding-bottom: max(1rem, env(safe-area-inset-bottom));
            }

            .pos-sidebar-open .pos-sidebar {
                transform: translateX(0);
            }

            .pos-sidebar-backdrop {
                display: none;
            }

            .pos-sidebar-open .pos-sidebar-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 80;
                background: rgba(15, 23, 42, 0.58);
                backdrop-filter: blur(2px);
            }

            .sidebar-brand {
                grid-template-columns: auto 1fr;
                justify-items: start;
                align-items: center;
                text-align: left;
                margin-bottom: 1.1rem;
            }

            .sidebar-brand-icon.logo-sidebar-image {
                width: 58px;
                height: 58px;
            }

            .sidebar-brand-text h2 {
                font-size: 1rem;
                letter-spacing: 0.03em;
            }

            .sidebar-brand-text small {
                font-size: 0.58rem;
                letter-spacing: 0.12em;
            }

            .sidebar-menu-item,
            .sidebar-menu-subitem {
                min-height: 46px;
                font-size: 0.9rem;
                border-radius: 10px;
            }

            .pos-main-container {
                height: auto;
                min-height: 100vh;
                overflow: visible;
                padding-bottom: calc(78px + env(safe-area-inset-bottom));
            }

            .pos-top-bar {
                position: sticky;
                top: 0;
                z-index: 60;
                display: grid;
                grid-template-columns: auto auto minmax(0, 1fr) auto;
                gap: 0.48rem;
                align-items: center;
                padding: 0.6rem 0.65rem;
                background:
                    linear-gradient(180deg, rgba(255,255,255,0.97), rgba(255,255,255,0.94));
                border-bottom: 1px solid #e2e8f0;
                backdrop-filter: blur(12px);
            }

            .pos-mobile-menu-btn,
            #posMobileMenuToggle {
                width: 42px;
                height: 42px;
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
                background: #ffffff;
                color: #7a1231;
            }

            .pos-mobile-logo {
                display: inline-flex;
                width: 42px;
                height: 42px;
                min-width: 42px;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                overflow: hidden;
                background: #7a1734;
                box-shadow: 0 10px 20px rgba(122, 23, 52, 0.14);
            }

            .pos-mobile-logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                padding: 0.25rem;
            }

            .pos-mobile-title {
                min-width: 0;
            }

            .pos-mobile-actions {
                display: inline-flex;
                align-items: center;
                justify-content: flex-end;
                gap: 0.38rem;
            }

            .pos-top-bar h1 {
                font-size: 1.02rem !important;
                line-height: 1.12;
                margin: 0;
            }

            .pos-top-bar p {
                font-size: 0.68rem !important;
                line-height: 1.35;
                margin: 0.2rem 0 0;
            }

            .top-right-tools,
            .cashier-profile-pill {
                display: none !important;
            }

            .pos-mobile-actions .bell-btn,
            .pos-mobile-actions .user-avatar-circle {
                display: inline-flex !important;
            }

            .pos-mobile-actions .bell-btn {
                width: 36px;
                height: 36px;
                background: #ffffff;
                border: 1px solid #e2e8f0;
            }

            .pos-mobile-actions .user-avatar-circle {
                width: 38px;
                height: 38px;
                font-size: 0.68rem;
                background: #8b173a;
            }

            .pos-search-wrapper {
                grid-column: 1 / -1;
                max-width: none;
                width: 100%;
                order: 4;
            }

            .pos-search-wrapper input {
                height: 38px;
                font-size: 0.78rem;
                border-radius: 10px;
                padding-left: 2.15rem;
                padding-right: 4.1rem;
            }

            .pos-search-icon {
                left: 0.72rem;
                font-size: 0.84rem;
            }

            .pos-scan-btn {
                right: 0.38rem;
                padding: 0.26rem 0.5rem;
                font-size: 0.68rem;
                border-radius: 7px;
            }

            .pos-content-area,
            .transfer-screen {
                padding: 0.58rem !important;
            }

            .metrics-row {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.45rem;
                padding: 0.58rem 0.58rem 0;
            }

            .metric-card {
                min-height: 86px;
                padding: 0.52rem;
                border-radius: 8px;
                gap: 0.38rem;
                align-items: flex-start;
                flex-direction: column;
            }

            .metric-icon-box {
                width: 32px;
                height: 32px;
                min-width: 32px;
                border-radius: 8px;
                font-size: 1rem;
            }

            .metric-info label {
                font-size: 0.52rem;
                line-height: 1.2;
            }

            .metric-info h3 {
                font-size: 0.86rem !important;
                line-height: 1.1;
            }

            .metric-info span {
                font-size: 0.56rem !important;
                line-height: 1.25;
            }

            .workspace-grid {
                grid-template-columns: minmax(0, 1fr) !important;
                gap: 0.6rem;
                padding: 0.58rem;
                width: 100%;
                max-width: 100%;
                overflow: hidden;
            }

            .workspace-grid > * {
                min-width: 0;
                max-width: 100%;
            }

            .cat-pills-bar {
                display: flex;
                overflow-x: auto;
                gap: 0.38rem;
                padding: 0.06rem 0 0.45rem;
                margin-bottom: 0.28rem;
                scrollbar-width: none;
            }

            .cat-pills-bar::-webkit-scrollbar {
                display: none;
            }

            .cat-pill {
                flex: 0 0 auto;
                min-height: 34px;
                padding: 0.42rem 0.62rem;
                border-radius: 999px;
                font-size: 0.68rem;
                white-space: nowrap;
            }

            .pos-inventory-strip {
                display: none;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(min(100%, 150px), 1fr)) !important;
                gap: 0.52rem;
                width: 100%;
                max-width: 100%;
            }

            .product-card {
                min-width: 0;
                min-height: 238px;
                padding: 0.5rem;
                border-radius: 9px;
            }

            .product-img-box {
                height: 58px;
                border-radius: 7px;
                margin-bottom: 0.34rem;
            }

            .product-card-title {
                font-size: 0.7rem;
                line-height: 1.16;
                min-height: 2.32em;
            }

            .product-card-sub,
            .stock-badge {
                font-size: 0.56rem;
                line-height: 1.25;
            }

            .product-card [style*="font-size:0.75rem"] {
                font-size: 0.56rem !important;
                padding: 0.34rem !important;
                margin: 0.28rem 0 0.36rem !important;
            }

            .product-card [id^="price_display_"] {
                font-size: 0.78rem !important;
            }

            .product-card [id^="tier_badge_"] {
                max-width: 86px !important;
                font-size: 0.52rem !important;
                padding: 0.12rem 0.3rem !important;
            }

            .product-card div[style*="color:#64748b"] {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 0.08rem;
            }

            .product-card div[style*="justify-content:space-between"] {
                gap: 0.25rem;
                flex-wrap: wrap;
            }

            .product-action-row {
                grid-template-columns: 1fr !important;
                gap: 0.32rem;
            }

            .stepper-box {
                width: 100%;
                justify-content: space-between;
            }

            .stepper-btn {
                min-width: 28px;
                min-height: 28px;
                font-size: 0.9rem;
            }

            .stepper-box input {
                width: 32px !important;
                font-size: 0.72rem !important;
                padding: 0.18rem 0.1rem !important;
            }

            .add-cart-btn {
                min-height: 40px;
                border-radius: 8px;
                font-size: 0.78rem;
            }

            .btn-add-cart {
                min-height: 34px;
                font-size: 0.68rem;
                padding: 0.44rem 0.2rem;
            }

            .cart-panel {
                position: static !important;
                max-height: none;
                border-radius: 9px;
                padding: 0.68rem;
            }

            .cart-item-row {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 0.55rem;
                align-items: start;
                padding: 0.48rem 0 !important;
            }

            .cart-item-info {
                min-width: 0;
            }

            .cart-item-icon {
                width: 32px !important;
                height: 32px !important;
            }

            .cart-item-name {
                font-size: 0.68rem !important;
                white-space: normal !important;
                line-height: 1.18;
            }

            .cart-item-price,
            .cart-item-total {
                font-size: 0.68rem;
            }

            .cart-financial-summary {
                padding: 0.68rem;
                margin-bottom: 0.68rem;
            }

            .fin-row {
                font-size: 0.72rem;
            }

            .fin-row.total {
                font-size: 1rem;
            }

            .customer-select-box {
                gap: 0.42rem;
                margin-bottom: 0.68rem;
            }

            .customer-select-box select,
            .customer-select-box input[type="text"] {
                min-height: 36px;
                padding: 0.48rem 0.55rem;
                font-size: 0.72rem;
            }

            .cart-panel-header {
                margin-bottom: 0.68rem;
                padding-bottom: 0.55rem;
            }

            .cart-panel-header h3 {
                font-size: 0.86rem;
            }

            .btn-clear-cart {
                font-size: 0.68rem;
            }

            .pay-methods-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.38rem;
            }

            .pay-method-btn {
                min-height: 38px;
                font-size: 0.68rem;
                border-radius: 8px;
            }

            .complete-sale-btn,
            .cart-checkout-btn {
                min-height: 46px;
                border-radius: 10px;
                font-size: 0.76rem !important;
            }

            .btn-complete-sale {
                min-height: 44px;
                padding: 0.66rem;
                border-radius: 9px;
                font-size: 0.74rem;
            }

            .pos-dashboard-extra,
            .settings-grid,
            .customer-summary-row,
            .customer-contact-meta {
                grid-template-columns: 1fr !important;
            }

            .top-selling-panel,
            .recent-sales-panel,
            .settings-panel,
            .customer-card {
                border-radius: 10px;
                padding: 0.85rem !important;
            }

            .dashboard-panel {
                padding: 0.72rem;
                border-radius: 9px;
            }

            .top-selling-row,
            .recent-sale-row {
                gap: 0.45rem;
                font-size: 0.68rem;
            }

            .top-selling-row {
                grid-template-columns: 18px 30px minmax(0, 1fr) auto;
            }

            .recent-sale-row {
                grid-template-columns: 30px minmax(0, 1fr) auto;
            }

            .recent-sale-row > span {
                display: none;
            }

            .transfer-panel {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 10px;
            }

            .transfer-table,
            .data-table {
                min-width: 760px;
            }

            .pos-bottom-status {
                display: none !important;
            }

            .pos-mobile-bottom-nav {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 70;
                min-height: 62px;
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 0.18rem;
                padding: 0.38rem 0.3rem max(0.42rem, env(safe-area-inset-bottom));
                background: rgba(255, 255, 255, 0.98);
                border-top: 1px solid #e2e8f0;
                box-shadow: 0 -14px 30px rgba(15, 23, 42, 0.1);
                backdrop-filter: blur(14px);
            }

            .pos-mobile-bottom-nav a,
            .pos-mobile-bottom-nav button {
                min-width: 0;
                border: 0;
                border-radius: 10px;
                background: transparent;
                color: #475569;
                display: grid;
                place-items: center;
                gap: 0.18rem;
                text-decoration: none;
                font-size: 0.56rem;
                font-weight: 800;
                cursor: pointer;
            }

            .pos-mobile-bottom-nav .mobile-nav-icon {
                font-size: 1rem;
                line-height: 1;
            }

            .pos-mobile-bottom-nav .active {
                background: #fff5f7;
                color: #8b173a;
                box-shadow: inset 0 0 0 1px #f3c6d4;
            }

            .receipt-modal-panel {
                width: min(100vw - 1rem, 760px);
                max-height: calc(100vh - 1rem);
                border-radius: 12px;
            }

            .receipt-sheets {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 430px) {
            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            .product-card {
                min-height: auto;
            }

            .metrics-row {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }

            .pay-methods-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 360px) {
            .pos-top-bar {
                grid-template-columns: auto minmax(0, 1fr) auto;
            }

            .pos-mobile-logo {
                display: none;
            }

            .metrics-row {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            .product-card {
                padding: 0.46rem;
            }

            .product-card-title {
                font-size: 0.66rem;
            }
        }

        @media (max-width: 320px) {
            .product-grid,
            .metrics-row {
                grid-template-columns: 1fr !important;
            }

            .product-card {
                min-height: auto;
            }
        }
	    </style>
</head>
<body>

<div class="pos-app-layout <?= $useAdminShell ? 'pos-admin-shell' : '' ?>">
	<div class="pos-sidebar-backdrop" data-pos-sidebar-close></div>
    <aside class="pos-sidebar" id="posSidebar">
        <div>
	            <div class="sidebar-brand">
	                <div class="sidebar-brand-icon logo-sidebar-image">
	                    <img src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="Empress Tee Beverage Depot">
	                </div>
	                <div class="sidebar-brand-text">
	                    <h2>EMPRESS TEE</h2>
	                    <small><?= $useAdminShell ? 'BEVERAGE DEPOT' : 'POS TERMINAL' ?></small>
	                </div>
	            </div>

		            <?php if ($useAdminShell): ?>
		                <button type="button" class="sidebar-collapse-toggle" id="posSidebarCollapseToggle" title="Collapse sidebar" aria-label="Collapse sidebar">
		                    <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('menu') : '<span></span>' ?>
		                </button>

		                <div class="sidebar-section-title">DASHBOARD</div>
		                <nav class="sidebar-menu">
		                    <a href="<?= url('beverage_warehouse.php?tab=tab-dash') ?>" class="sidebar-menu-item" data-tooltip="Dashboard">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('dashboard') : '<span></span>' ?> Dashboard
		                    </a>
		                </nav>

		                <div class="sidebar-section-title">WAREHOUSE</div>
		                <nav class="sidebar-menu">
		                    <button type="button" class="sidebar-menu-item sidebar-submenu-toggle <?= in_array($activeTab, ['inventory', 'transfers', 'pricing-audit'], true) ? 'active' : '' ?>" data-submenu-toggle="posWarehouseMenu" data-tooltip="Inventory" aria-expanded="true">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('box') : '<span></span>' ?> Inventory <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '<span></span>' ?>
		                    </button>
		                    <div class="sidebar-submenu open" id="posWarehouseMenu">
		                    <a href="<?= url('beverage_warehouse.php?tab=tab-inventory') ?>" class="sidebar-menu-subitem <?= $activeTab === 'inventory' ? 'active' : '' ?>" data-tooltip="Products">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('box') : '<span></span>' ?> Products
		                    </a>
		                    <a href="<?= url('beverage_warehouse.php?tab=tab-inventory&detail=available') ?>" class="sidebar-menu-subitem" data-tooltip="Stock Levels">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('analytics') : '<span></span>' ?> Stock Levels <span class="nav-count-badge"><?= e($posSidebarStockBadge) ?></span>
		                    </a>
		                    <a href="<?= url('beverage_pos.php?tab=transfers') ?>" class="sidebar-menu-subitem <?= $activeTab === 'transfers' ? 'active' : '' ?>" data-tooltip="Stock Transfer">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('switch') : '<span></span>' ?> Stock Movement <span class="nav-count-badge"><?= number_format($posSidebarMovementCount) ?></span>
		                    </a>
		                    <a href="<?= url('beverage_pos.php?tab=pricing-audit') ?>" class="sidebar-menu-subitem <?= $activeTab === 'pricing-audit' ? 'active' : '' ?>" data-tooltip="Inventory Audit">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('log') : '<span></span>' ?> Inventory Audit
		                    </a>
		                    <a href="<?= url('beverage_warehouse.php?tab=tab-inventory&detail=zones') ?>" class="sidebar-menu-subitem" data-tooltip="Warehouse Zones">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('warehouse') : '<span></span>' ?> Warehouse Zones
		                    </a>
		                    </div>
		                </nav>

		                <div class="sidebar-section-title">OPERATIONS</div>
		                <nav class="sidebar-menu">
		                    <button type="button" class="sidebar-menu-item sidebar-submenu-toggle <?= $activeTab === 'pos' ? 'active' : '' ?>" data-submenu-toggle="posOperationsMenu" data-tooltip="Operations" aria-expanded="true">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('truck') : '<span></span>' ?> Operations <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '<span></span>' ?>
		                    </button>
		                    <div class="sidebar-submenu open" id="posOperationsMenu">
		                    <a href="<?= url('beverage_pos.php?tab=pos') ?>" class="sidebar-menu-subitem" data-tooltip="POS Terminal">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('cart') : '<span></span>' ?> POS Terminal
		                    </a>
		                    <a href="<?= url('dashboard.php?tab=tab-logistics&view=dispatch&company=beverage') ?>" class="sidebar-menu-subitem" data-tooltip="Dispatch">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('truck') : '<span></span>' ?> Dispatch
		                    </a>
		                    <a href="<?= url('dashboard.php?tab=tab-logistics&view=returns&company=beverage') ?>" class="sidebar-menu-subitem" data-tooltip="Returns">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('return') : '<span></span>' ?> Returns
		                    </a>
		                    <a href="<?= url('dashboard.php?tab=tab-logistics&view=pod&company=beverage') ?>" class="sidebar-menu-subitem" data-tooltip="Delivery POD">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('pod') : '<span></span>' ?> Delivery POD
		                    </a>
		                    </div>
		                </nav>

		                <div class="sidebar-section-title">REPORTS</div>
		                <nav class="sidebar-menu">
		                    <button type="button" class="sidebar-menu-item sidebar-submenu-toggle <?= $activeTab === 'sales-account' ? 'active' : '' ?>" data-submenu-toggle="posReportsMenu" data-tooltip="Reports" aria-expanded="true">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('analytics') : '<span></span>' ?> Reports <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '<span></span>' ?>
		                    </button>
		                    <div class="sidebar-submenu open" id="posReportsMenu">
		                    <a href="<?= url('dashboard.php?tab=tab-health-score&company=beverage') ?>" class="sidebar-menu-subitem" data-tooltip="Analytics">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('analytics') : '<span></span>' ?> Analytics <span class="nav-count-badge">4</span>
		                    </a>
		                    <a href="<?= url('beverage_pos.php?tab=sales-account') ?>" class="sidebar-menu-subitem <?= $activeTab === 'sales-account' ? 'active' : '' ?>" data-tooltip="Reports">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('log') : '<span></span>' ?> Reports
		                    </a>
			                    <a href="<?= url('finance.php?tab=tab-dash-finance') ?>" class="sidebar-menu-subitem" data-tooltip="Finance">
			                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('finance') : '<span></span>' ?> Finance
			                    </a>
		                    </div>
		                </nav>

		                <?php if ($canManagePricing): ?>
		                <div class="sidebar-section-title">PRICING</div>
		                <nav class="sidebar-menu">
		                    <button type="button" class="sidebar-menu-item sidebar-submenu-toggle <?= in_array($activeTab, ['pricing-tiers', 'rebates', 'promotions'], true) ? 'active' : '' ?>" data-submenu-toggle="posPricingMenu" data-tooltip="Pricing" aria-expanded="true">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('finance') : '<span></span>' ?> Pricing <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '<span></span>' ?>
		                    </button>
		                    <div class="sidebar-submenu open" id="posPricingMenu">
		                        <a href="<?= url('beverage_pos.php?tab=pricing-tiers') ?>" class="sidebar-menu-subitem <?= $activeTab === 'pricing-tiers' ? 'active' : '' ?>" data-tooltip="Price Tiers">
		                            <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('analytics') : '<span></span>' ?> Price Tiers
		                        </a>
		                        <a href="<?= url('beverage_pos.php?tab=rebates') ?>" class="sidebar-menu-subitem <?= $activeTab === 'rebates' ? 'active' : '' ?>" data-tooltip="Rebates">
		                            <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('finance') : '<span></span>' ?> Rebates
		                        </a>
		                        <a href="<?= url('beverage_pos.php?tab=promotions') ?>" class="sidebar-menu-subitem <?= $activeTab === 'promotions' ? 'active' : '' ?>" data-tooltip="Promotions">
		                            <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('settings') : '<span></span>' ?> Promotions
		                        </a>
		                    </div>
		                </nav>
		                <?php endif; ?>

		                <div class="sidebar-section-title">SYSTEM</div>
		                <nav class="sidebar-menu">
		                    <button type="button" class="sidebar-menu-item sidebar-submenu-toggle <?= $activeTab === 'settings' ? 'active' : '' ?>" data-submenu-toggle="posSystemMenu" data-tooltip="System" aria-expanded="true">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('settings') : '<span></span>' ?> System <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '<span></span>' ?>
		                    </button>
		                    <div class="sidebar-submenu open" id="posSystemMenu">
		                    <a href="<?= url('dashboard.php?tab=tab-system-settings&company=beverage') ?>" class="sidebar-menu-subitem" data-tooltip="Settings">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('settings') : '<span></span>' ?> Settings
		                    </a>
		                    <a href="<?= url('beverage_pos.php?tab=pricing-audit') ?>" class="sidebar-menu-subitem <?= $activeTab === 'pricing-audit' ? 'active' : '' ?>" data-tooltip="Activity Log">
		                        <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('log') : '<span></span>' ?> Activity Log
		                    </a>
		                    </div>
		                </nav>
	            <?php else: ?>
	                <nav class="sidebar-menu">
	                    <a href="<?= url('beverage_pos.php?tab=pos') ?>" class="sidebar-menu-item <?= $activeTab === 'pos' ? 'active' : '' ?>">
	                        <span>🖥️</span> Sell
	                    </a>
	                    <a href="<?= url('beverage_pos.php?tab=customers') ?>" class="sidebar-menu-item <?= $activeTab === 'customers' ? 'active' : '' ?>">
	                        <span>👥</span> Customers
	                    </a>
	                    <a href="<?= url('beverage_pos.php?tab=history') ?>" class="sidebar-menu-item <?= $activeTab === 'history' ? 'active' : '' ?>">
	                        <span>🧾</span> Sales History
	                    </a>
	                    <a href="<?= url('beverage_pos.php?tab=recon') ?>" class="sidebar-menu-item <?= $activeTab === 'recon' ? 'active' : '' ?>">
	                        <span>📈</span> Cash Drawer
	                    </a>
	                    <?php if ($canManagePricing): ?>
		                    <a href="<?= url('beverage_pos.php?tab=pricing-tiers') ?>" class="sidebar-menu-item <?= $activeTab === 'pricing-tiers' ? 'active' : '' ?>" style="background:<?= $activeTab === 'pricing-tiers' ? '#0284c7' : 'transparent' ?>">
		                        <span>🏷️</span> Price Tiers
		                    </a>
		                    <a href="<?= url('beverage_pos.php?tab=rebates') ?>" class="sidebar-menu-item <?= $activeTab === 'rebates' ? 'active' : '' ?>" style="background:<?= $activeTab === 'rebates' ? '#0284c7' : 'transparent' ?>">
		                        <span>💰</span> Rebates
		                    </a>
		                    <a href="<?= url('beverage_pos.php?tab=promotions') ?>" class="sidebar-menu-item <?= $activeTab === 'promotions' ? 'active' : '' ?>" style="background:<?= $activeTab === 'promotions' ? '#0284c7' : 'transparent' ?>">
		                        <span>🎯</span> Promotions
		                    </a>
	                    <?php endif; ?>
	                    <?php if ($posActiveRole !== 'pos'): ?>
		                    <a href="<?= url('beverage_pos.php?tab=settings') ?>" class="sidebar-menu-item <?= $activeTab === 'settings' ? 'active' : '' ?>">
		                        <span>⚙</span> Settings
		                    </a>
	                    <?php endif; ?>
	                </nav>
	            <?php endif; ?>
        </div>

	        <div class="sidebar-user-box <?= $useAdminShell ? 'admin-profile-box' : '' ?>">
	            <?php if ($useAdminShell): ?>
	                <div class="admin-profile-main">
	                    <div class="admin-profile-avatar">BA<span></span></div>
	                    <div class="user-box-info">
	                        <div class="admin-profile-name"><?= e($user['name'] ?? 'Beverage Admin') ?></div>
	                        <small class="admin-profile-role"><?= e(ucwords(str_replace('_', ' ', canonical_role($user['role'] ?? 'admin')))) ?> · Online</small>
	                    </div>
	                </div>
	                <div class="admin-profile-actions">
	                    <a href="<?= url('dashboard.php?tab=tab-system-settings&company=beverage') ?>" class="admin-profile-action" title="Settings" data-tooltip="Settings"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('settings') : '<span></span>' ?></a>
	                    <a href="<?= url('dashboard.php') ?>" class="admin-profile-action" title="Profile menu" data-tooltip="Profile menu"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('users') : '<span></span>' ?></a>
	                </div>
	                <a href="<?= url('logout.php') ?>" class="btn-logout-link admin-logout-link" data-tooltip="Logout">
	                    <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('logout') : '<span></span>' ?> <span>Logout</span>
	                </a>
	            <?php else: ?>
	                <div class="user-box-top">
	                    <div class="user-avatar-circle"><?= canonical_role($user['role'] ?? '') === 'pos' ? 'BC' : 'WM' ?></div>
	                    <div class="user-box-info">
	                        <div><?= e($user['name'] ?? 'Beverage Manager') ?></div>
	                        <small><?= canonical_role($user['role'] ?? '') === 'pos' ? 'Terminal: Glass Crate POS' : 'Warehouse Admin' ?></small>
	                    </div>
	                </div>
	                <a href="<?= url('logout.php') ?>" class="btn-logout-link">
	                    <span>↩</span> Log out
	                </a>
	            <?php endif; ?>
	        </div>
    </aside>

	    <main class="pos-main-container">
	        <header class="pos-top-bar">
	            <button type="button" class="pos-mobile-menu-btn" id="posMobileMenuToggle" aria-label="Open navigation" aria-controls="posSidebar" aria-expanded="false">
	                <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('menu') : '<span></span>' ?>
	            </button>
                <span class="pos-mobile-logo" aria-hidden="true">
                    <img src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="">
                </span>
	            <div style="display:flex;align-items:center;gap:0.85rem" class="pos-mobile-title">
	                <div>
	                <h1 style="font-family:'Outfit',sans-serif;font-size:1.15rem;color:#0f172a;margin:0"><?= e($posHeaderTitle) ?></h1>
	                <p style="color:#64748b;font-size:0.78rem;margin-top:0.15rem"><?= e($posHeaderSubtitle) ?></p>
	                </div>
	            </div>
                <div class="pos-mobile-actions" aria-hidden="true">
                    <button type="button" class="bell-btn">
                        🔔
                        <span class="bell-badge">3</span>
                    </button>
                    <div class="user-avatar-circle"><?= canonical_role($user['role'] ?? '') === 'pos' ? 'CA' : 'BA' ?></div>
                </div>
            <div class="pos-search-wrapper">
                <span class="pos-search-icon">🔍</span>
                <input type="text" id="bevSearch" placeholder="<?= $activeTab === 'customers' ? 'Search customer name or phone...' : 'Search beverage, SKU, barcode...' ?>" onkeyup="<?= $activeTab === 'customers' ? 'filterCustomerContacts()' : 'filterBevCatalog()' ?>">
                <?php if ($activeTab === 'pos'): ?>
                    <button type="button" onclick="openCameraBarcodeScanner()" class="pos-scan-btn">Scan</button>
                <?php endif; ?>
            </div>

            <div class="top-right-tools">
                <button type="button" class="bell-btn">
                    🔔
                    <span class="bell-badge">3</span>
                </button>
                <div class="cashier-profile-pill">
                    <div class="user-avatar-circle" style="width:24px;height:24px;font-size:0.68rem">BC</div>
                    <span><?= e($user['name'] ?? 'Beverage Cashier') ?></span>
                </div>
            </div>
        </header>

        <?php if ($actionMessage): ?>
            <div style="margin:1rem 1.5rem 0 1.5rem;background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;padding:0.85rem 1.25rem;border-radius:10px;font-weight:600;font-size:0.88rem">
                <?= e($actionMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'pos'): ?>
            <section class="metrics-row">
                <div class="metric-card">
                    <div class="metric-icon-box" style="background:#e0f2fe;color:#0284c7">📈</div>
                    <div class="metric-info">
                        <label>Today</label>
                        <h3>₦<?= number_format($shiftSummary['total_sales'], 0) ?></h3>
                        <span style="color:#059669">Revenue</span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon-box" style="background:#d1fae5;color:#059669;overflow:hidden"><img src="<?= e(url('assets/images/coca_cola_50cl_crate.svg')) ?>" alt="" style="width:100%;height:100%;object-fit:cover"></div>
                    <div class="metric-info">
                        <label>Items Sold</label>
                        <h3><?= number_format($salesAccountTotals['items_sold'], 0) ?></h3>
                        <span style="color:#059669">Today</span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon-box" style="background:#f3e8ff;color:#7c3aed;overflow:hidden"><img src="<?= e(url('assets/images/coke_crate.svg')) ?>" alt="" style="width:100%;height:100%;object-fit:cover"></div>
                    <div class="metric-info">
                        <label>Warehouse Stock Available</label>
                        <h3><?= number_format($totalAvailableStock) ?></h3>
                        <span style="color:<?= $lowStockCount > 0 ? '#c2410c' : '#059669' ?>"><?= number_format($lowStockCount) ?> low stock · Jacroxx Main</span>
                    </div>
                </div>
            </section>

            <section class="workspace-grid">
                <div>
                    <!-- Category Filter Pills -->
                    <?php
                        $availableCats = [];
                        foreach ($availableProducts as $ap) {
                            $cName = trim((string)($ap['category'] ?? 'Soft Drinks'));
                            if ($cName !== '') {
                                $availableCats[$cName] = ($availableCats[$cName] ?? 0) + 1;
                            }
                        }
                        ksort($availableCats);
                    ?>
	                    <div class="cat-pills-bar">
	                        <button class="cat-pill active" onclick="filterBevCat('all', this)">All Beverages (<?= count($availableProducts) ?>)</button>
                        <?php foreach ($availableCats as $cName => $cCount): ?>
                            <button class="cat-pill" onclick="filterBevCat(<?= htmlspecialchars(json_encode($cName), ENT_QUOTES, 'UTF-8') ?>, this)">
                                <?= e($cName) ?> (<?= $cCount ?>)
                            </button>
                        <?php endforeach; ?>
	                    </div>

	                    <div class="pos-inventory-strip">
	                        <div class="pos-inventory-head">
	                            <h3>Live Warehouse Inventory Available for POS</h3>
	                            <span><?= number_format(count($availableProducts)) ?> products · <?= number_format($totalAvailableStock) ?> crates/packs · <?= number_format($lowStockCount) ?> low stock</span>
	                        </div>
	                        <div class="pos-stock-list">
	                            <?php if (empty($availableProducts)): ?>
	                                <div style="padding:0.75rem 1rem;color:#64748b;font-size:0.78rem">No available stock in warehouse for POS sale.</div>
	                            <?php else: ?>
	                                <?php foreach ($availableProducts as $stockItem): ?>
	                                    <?php
	                                        $stockQty = (int)($stockItem['stock_qty'] ?? 0);
	                                        $stockClass = $stockQty <= 100 ? 'stock-low' : 'stock-good';
	                                    ?>
	                                    <div class="pos-stock-row" data-stock-title="<?= e(strtolower(($stockItem['name'] ?? '') . ' ' . ($stockItem['sku'] ?? '') . ' ' . ($stockItem['category'] ?? ''))) ?>">
	                                        <strong><?= e($stockItem['name'] ?? 'Product') ?></strong>
	                                        <span class="<?= e($stockClass) ?>" style="display:inline-flex;margin-top:0.35rem;border-radius:999px;padding:0.2rem 0.5rem">
	                                            <?= number_format($stockQty) ?> <?= e($stockItem['price_type'] ?? 'Units') ?>
	                                        </span>
	                                    </div>
	                                <?php endforeach; ?>
	                            <?php endif; ?>
	                        </div>
	                    </div>

	                    <!-- Available Product Cards Grid -->
	                    <div class="product-grid" id="bevGrid">
                            <?php if (empty($availableProducts)): ?>
                                <div style="grid-column:1 / -1;background:#ffffff;border:1px dashed #cbd5e1;border-radius:8px;padding:2.5rem 1.5rem;text-align:center;color:#64748b">
                                    <span style="font-size:2rem;display:block;margin-bottom:0.5rem">📦</span>
                                    <strong style="display:block;color:#0f172a;font-family:'Outfit',sans-serif;font-size:1.05rem;margin-bottom:0.35rem">No Available In-Stock Products</strong>
                                    Only products with available stock appear on POS. Please receive stock in Jacroxx / Ijaba Warehouse before selling.
                                </div>
                            <?php else: ?>
	                            <?php foreach ($availableProducts as $item): ?>
	                                <?php
	                                    $stockQty = (int)($item['stock_qty'] ?? 0);
	                                    $stockClass = $stockQty <= 100 ? 'stock-low' : 'stock-good';
	                                    $stockLabel = number_format($stockQty) . ' ' . ($item['price_type'] ?? 'Crates') . ' Available';
	                                    $cost = (float)($item['cost_price'] ?? 0.0);
	                                    $price = (float)($item['price_per_unit'] ?? 0.0);
	                                    $rebate = (float)($item['rebate_info']['rebate_pct'] ?? 0.0);
	                                    $rebateAmt = $cost * ($rebate / 100.0);
	                                    $effCost = max(0.0, $cost - $rebateAmt);
	                                    $trueProfit = $price - $effCost;
	                                    $tiers = $item['pricing_tiers'] ?? [];
	                                    $tierLabel = $item['default_tier_info']['tier_name'] ?? (!empty($tiers) ? 'Configured Tier' : 'No Tier');
	                                    $itemIdSafe = 'item_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($item['id'] ?? $item['sku'] ?? '0'));
	                                ?>
	                                <div class="product-card" data-sku="<?= e($item['sku'] ?? '') ?>" data-cat="<?= e($item['category'] ?? '') ?>" data-title="<?= e(strtolower(($item['name'] ?? '') . ' ' . ($item['sku'] ?? '') . ' ' . ($item['packaging'] ?? ''))) ?>" data-stock="<?= $stockQty ?>" id="card_<?= e($itemIdSafe) ?>">
	                                    <div class="product-img-box" style="display:flex;align-items:center;justify-content:center;overflow:hidden">
	                                        <?php if (!empty($item['image'])): ?>
	                                            <img data-live-image="<?= e($item['sku'] ?? '') ?>" src="<?= e(beverage_product_image_src($item['image'])) ?>" alt="<?= e($item['name']) ?>" onerror="this.onerror=null; this.src='<?= e(beverage_product_image_fallback_src()) ?>';" style="width:100%; height:100%; object-fit:cover; border-radius:8px">
	                                        <?php else: ?>
	                                            <span><?= $item['icon'] ?: '🥤' ?></span>
	                                        <?php endif; ?>
	                                    </div>
	                                    <div>
	                                        <div class="product-card-title"><?= e($item['name']) ?></div>
	                                        <div class="stock-badge <?= e($stockClass) ?>" data-live-stock="<?= e($item['sku'] ?? '') ?>" data-unit-label="<?= e($item['price_type'] ?? 'Crates') ?>"><?= e($stockLabel) ?></div>
	                                        <div class="product-card-sub"><?= e($item['sku'] ?? '') ?> • <?= e($item['packaging']) ?></div>
	                                        
	                                        <!-- Dynamic Pricing & Rebate Live Economics Strip -->
	                                        <div style="margin:0.4rem 0 0.5rem;padding:0.4rem 0.55rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:0.75rem">
	                                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.25rem">
	                                                <span id="tier_badge_<?= e($itemIdSafe) ?>" style="background:#e0f2fe;color:#0369a1;padding:0.15rem 0.45rem;border-radius:4px;font-weight:800;font-size:0.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px">
	                                                    <?= e($tierLabel) ?>
	                                                </span>
	                                                <strong id="price_display_<?= e($itemIdSafe) ?>" style="color:#0f172a;font-size:0.95rem;font-weight:900">
	                                                    ₦<?= number_format($price, 2) ?>
	                                                </strong>
	                                            </div>
	                                            <div style="display:flex;justify-content:space-between;color:#64748b;font-size:0.68rem;line-height:1.3">
	                                                <span>Rebate: <strong id="rebate_display_<?= e($itemIdSafe) ?>" style="color:#2563eb"><?= $rebate ?>%</strong></span>
	                                                <span>Eff. Cost: <strong id="eff_cost_display_<?= e($itemIdSafe) ?>">₦<?= number_format($effCost, 0) ?></strong></span>
	                                                <span>True Profit: <strong id="margin_display_<?= e($itemIdSafe) ?>" style="color:<?= $trueProfit >= 0 ? '#16a34a' : '#dc2626' ?>"><?= $trueProfit >= 0 ? '+₦' : '-₦' ?><?= number_format(abs($trueProfit), 0) ?></strong></span>
	                                            </div>
	                                        </div>
	                                    </div>
	                                    <div class="product-action-row">
	                                        <div class="stepper-box" style="display:flex;align-items:center;gap:0.2rem">
	                                            <button type="button" class="stepper-btn" onclick="stepCardQty('<?= e($itemIdSafe) ?>', -1, <?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)">-</button>
	                                            <input type="number" id="qty_<?= e($itemIdSafe) ?>" value="1" min="1" max="<?= $stockQty ?>" oninput="onCardQtyChange('<?= e($itemIdSafe) ?>', this.value, <?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)" style="width:48px;text-align:center;font-weight:800;border:1px solid #cbd5e1;border-radius:4px;padding:0.25rem 0.15rem;font-size:0.85rem">
	                                            <button type="button" class="stepper-btn" onclick="stepCardQty('<?= e($itemIdSafe) ?>', 1, <?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)">+</button>
	                                        </div>
	                                        <button type="button" class="btn-add-cart" onclick="addCardToCart('<?= e($itemIdSafe) ?>', <?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)">
	                                            Add to Cart
	                                        </button>
	                                    </div>
	                                </div>
	                            <?php endforeach; ?>
                            <?php endif; ?>
	                    </div>

                    <!-- Pagination Controls -->
                    <div class="pagination-box">
                        <button class="page-btn">&lt;&lt;</button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <button class="page-btn">4</button>
                        <button class="page-btn">5</button>
                        <button class="page-btn">&gt;&gt;</button>
                    </div>

                    <?php
                        $topSellingForDashboard = array_values(array_filter(
                            $itemsSoldByProduct,
                            static fn (array $product): bool => (int)($product['qty'] ?? 0) > 0
                        ));
                        $recentSalesForDashboard = array_slice($beverageSalesHistory, 0, 5);
                    ?>
                    <div class="pos-dashboard-extra">
                        <div class="dashboard-panel">
                            <div class="dashboard-panel-head">
                                <h3>Top Selling Products</h3>
                                <span>Today</span>
                            </div>
                            <?php if (empty($topSellingForDashboard)): ?>
                                <div style="color:#64748b;font-size:0.82rem;padding:1rem 0">No product sales yet.</div>
                            <?php else: ?>
                                <?php foreach (array_slice($topSellingForDashboard, 0, 5) as $rank => $product): ?>
                                    <?php $topSellingImage = beverage_product_image_src($product['image'] ?? null); ?>
                                    <div class="top-selling-row">
                                        <span><?= $rank + 1 ?></span>
                                        <?php if ($topSellingImage !== ''): ?>
                                            <img src="<?= e($topSellingImage) ?>" alt="">
                                        <?php else: ?>
                                            <span class="product-image-placeholder">▣</span>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= e($product['name'] ?? 'Beverage Product') ?></strong>
                                            <span><?= number_format((int)($product['qty'] ?? 0)) ?> sold</span>
                                        </div>
                                        <strong>₦<?= number_format((float)($product['total'] ?? 0), 2) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="dashboard-panel">
                            <div class="dashboard-panel-head">
                                <h3>Recent Sales</h3>
                                <a href="<?= url('beverage_pos.php?tab=history') ?>">View all</a>
                            </div>
                            <?php if (empty($recentSalesForDashboard)): ?>
                                <div style="color:#64748b;font-size:0.82rem;padding:1rem 0">No sales have been recorded yet.</div>
                            <?php else: ?>
                                <?php foreach ($recentSalesForDashboard as $tx): ?>
                                    <div class="recent-sale-row">
                                        <div class="recent-sale-icon">#</div>
                                        <div>
                                            <strong>#<?= e($tx['receipt_no'] ?? 'N/A') ?></strong>
                                            <span><?= e($tx['customer_name'] ?? 'Walk-in Customer') ?></span>
                                        </div>
                                        <span><?= e($tx['timestamp'] ?? '') ?></span>
                                        <div style="display:grid;justify-items:end;gap:0.25rem">
                                            <span class="payment-chip"><?= e($tx['payment_method'] ?? 'Cash') ?></span>
                                            <strong>₦<?= number_format((float)($tx['grand_total'] ?? 0), 2) ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Side Cart Panel -->
                <div class="cart-panel">
                    <div class="cart-panel-header">
                        <h3>🛒 CURRENT CART (<span id="txtCartItemCount">0</span> ITEMS)</h3>
                        <button type="button" class="btn-clear-cart" onclick="clearBevCart()">Clear Cart</button>
                    </div>

                    <div class="customer-select-box">
                        <label class="customer-field-label" for="bevCustomer">Customer</label>
                        <select id="bevCustomer" onchange="updateCustomerAccountInfo(this)">
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= e($c['name']) ?>" data-phone="<?= e($c['phone'] ?? '') ?>" data-class="<?= e($c['customer_class'] ?? 'Retail') ?>" data-distributor="<?= e($c['distributor_code'] ?? '') ?>" data-wallet="<?= $c['wallet_balance'] ?>" data-limit="<?= $c['credit_limit'] ?>">
                                    👤 <?= e($c['name']) ?> <?= $c['wallet_balance'] > 0 ? '(Wallet: ₦' . number_format($c['wallet_balance'], 2) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__new_customer" data-phone="" data-class="Retail" data-distributor="" data-wallet="0" data-limit="0">+ New walk-in customer</option>
                        </select>
                        <input type="text" id="bevWalkinCustomerName" placeholder="Type customer name for receipt or contact" oninput="syncWalkinCustomerName()">
                        <input type="text" id="bevWalkinCustomerPhone" placeholder="Phone number" inputmode="tel" oninput="syncWalkinCustomerName()">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.55rem">
                            <select id="bevCustomerClass" onchange="syncWalkinCustomerName()" aria-label="Customer class">
                                <option value="Retail">Retail</option>
                                <option value="Distributor">Distributor</option>
                                <option value="Wholesale">Wholesale</option>
                                <option value="VIP">VIP</option>
                            </select>
                            <input type="text" id="bevDistributorCode" placeholder="Distributor code" oninput="syncWalkinCustomerName()">
                        </div>
                        <label class="save-customer-row">
                            <input type="checkbox" id="bevSaveCustomer" onchange="syncWalkinCustomerName()">
                            Save this customer to contacts
                        </label>
                        <label class="customer-field-label" for="bevSalesChannel" style="margin-top:0.65rem">Sales Type</label>
                        <select id="bevSalesChannel" onchange="onSalesChannelChange()">
                            <option value="depot_sale">Depot Sale</option>
                            <option value="diversion_sale">Diversion Sale</option>
                        </select>
                    </div>

                    <div class="cart-item-list" id="bevCartContainer">
                        <div style="text-align:center;color:#64748b;padding:3rem 1rem">
                            <img src="<?= e(url('assets/images/coca_cola_50cl_crate.svg')) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;display:block;margin:0 auto 0.5rem">
                            Cart is empty. Click beverage products on the left to start sale.
                        </div>
                    </div>

                    <form method="post" action="<?= url('beverage_pos.php?tab=pos') ?>" id="bevCheckoutForm">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="process_sale">
                        <input type="hidden" name="pos_mode" value="beverage">
                        <input type="hidden" name="sales_channel" id="formBevSalesChannel" value="depot_sale">
                        <input type="hidden" name="customer_name" id="formBevCustomer" value="Walk-in Customer">
                        <input type="hidden" name="customer_phone" id="formBevCustomerPhone" value="">
                        <input type="hidden" name="customer_class" id="formBevCustomerClass" value="Retail">
                        <input type="hidden" name="distributor_code" id="formBevDistributorCode" value="">
                        <input type="hidden" name="save_customer" id="formBevSaveCustomer" value="0">
                        <input type="hidden" name="cart_items" id="formBevCartItems" value="[]">
                        <input type="hidden" name="payment_method" id="formBevPayMethod" value="Cash">
                        <input type="hidden" name="promotion_code" id="formBevPromotionCode" value="">
                        <input type="hidden" name="subtotal" id="formBevSubtotal" value="0">
                        <input type="hidden" name="discount" id="formBevDiscount" value="0">
                        <input type="hidden" name="crate_deposit" id="formBevCrateDeposit" value="0">
                        <input type="hidden" name="grand_total" id="formBevGrandTotal" value="0">

                        <div class="cart-financial-summary">
                            <div class="fin-row">
                                <span>Subtotal</span>
                                <strong id="txtBevSubtotal">₦0.00</strong>
                            </div>
                            <div class="fin-row">
                                <span>Crate Deposit (Glass Bottles)</span>
                                <strong id="txtBevCrateDeposit">₦0.00</strong>
                            </div>
                            <div class="fin-row total">
                                <span>TOTAL</span>
                                <strong id="txtBevGrandTotal">₦0.00</strong>
                            </div>
                        </div>

                        <div style="margin-bottom:0.5rem">
                            <?php if (!empty($canManagePricing)): ?>
                                <label class="customer-field-label" for="bevOverrideReason">Manager Override Reason</label>
                                <input type="text" name="override_reason" id="bevOverrideReason" placeholder="Required only for below-threshold diversion sale" style="width:100%;margin-bottom:0.65rem;padding:0.7rem 0.8rem;border:1px solid #dbe4f0;border-radius:8px;font-size:0.82rem">
                            <?php endif; ?>
                            <label style="font-size:0.75rem;font-weight:700;color:#64748b;text-transform:uppercase">PAYMENT METHOD</label>
                            <div class="pay-methods-grid">
                                <button type="button" class="pay-btn-item active" onclick="setBevPayMethod('Cash', this)">💵 Cash</button>
                                <button type="button" class="pay-btn-item" onclick="setBevPayMethod('POS Terminal Card', this)">💳 Card</button>
                                <button type="button" class="pay-btn-item" onclick="setBevPayMethod('Bank Transfer', this)">🏦 Transfer</button>
                                <button type="button" class="pay-btn-item" onclick="setBevPayMethod('Customer Wallet', this)">👛 Wallet</button>
                                <button type="button" class="pay-btn-item" onclick="setBevPayMethod('Debt / On Credit', this)">🧾 Debt</button>
                            </div>
                        </div>

                        <button type="button" class="btn-complete-sale" onclick="submitBevSale()">
                            <span>🖨️</span> COMPLETE SALE / PRINT RECEIPT →
                        </button>
                    </form>
                </div>
            </section>
        <?php elseif ($activeTab === 'inventory'): ?>
            <!-- Inventory View -->
            <div style="padding:1.5rem">
                <div style="background:#fff;padding:1.5rem;border-radius:12px;border:1px solid #e2e8f0">
	                    <h3 style="margin-bottom:1rem"><img src="<?= e(url('assets/images/coke_crate.svg')) ?>" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:0.35rem">Products &amp; Stock Inventory</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">
                                <th style="padding:0.75rem">SKU</th>
                                <th style="padding:0.75rem">Product Name</th>
                                <th style="padding:0.75rem">Category</th>
                                <th style="padding:0.75rem">Packaging</th>
                                <th style="padding:0.75rem">Price</th>
                                <th style="padding:0.75rem">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($availableProducts)): ?>
                                <tr><td colspan="6" style="padding:1rem;text-align:center;color:#64748b">No available in-stock products found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($availableProducts as $it): ?>
                                    <tr style="border-bottom:1px solid #f1f5f9">
                                        <td style="padding:0.75rem"><strong><?= e($it['sku']) ?></strong></td>
                                        <td style="padding:0.75rem"><?= $it['icon'] ?: '🥤' ?> <?= e($it['name']) ?></td>
                                        <td style="padding:0.75rem"><?= e($it['category']) ?></td>
                                        <td style="padding:0.75rem"><?= e($it['packaging']) ?></td>
                                        <td style="padding:0.75rem;color:#059669;font-weight:700">₦<?= number_format((float)$it['price_per_unit'], 2) ?></td>
                                        <td style="padding:0.75rem;font-weight:700"><?= $it['stock_qty'] ?> Units</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
	                </div>
	            </div>
	        <?php elseif ($activeTab === 'items-sold'): ?>
	            <section class="transfer-screen">
	                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
	                    <div>
	                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a">Items Sold Per Product</h2>
	                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.86rem">Product-by-product POS sales quantity and value for the current sales history.</p>
	                    </div>
	                    <a href="<?= url('beverage_pos.php?tab=pos') ?>" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;text-decoration:none;border-radius:8px;padding:0.6rem 0.85rem;font-size:0.78rem;font-weight:900">Back to POS Dashboard</a>
	                </div>

	                <div class="transfer-summary">
	                    <div class="transfer-card"><small>Total Items Sold</small><strong><?= number_format($salesAccountTotals['items_sold']) ?></strong></div>
	                    <div class="transfer-card"><small>Products Listed</small><strong><?= number_format(count($itemsSoldByProduct)) ?></strong></div>
	                    <div class="transfer-card"><small>Gross POS Sales</small><strong>₦<?= number_format($salesAccountTotals['gross_sales'], 2) ?></strong></div>
	                    <div class="transfer-card"><small>Receipt Count</small><strong><?= number_format($salesAccountTotals['receipts']) ?></strong></div>
	                </div>

	                <div class="transfer-panel">
	                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Product Sales Breakdown</h3>
	                    <table class="transfer-table">
	                        <thead>
	                            <tr>
	                                <th>Product</th>
	                                <th>SKU</th>
	                                <th>Quantity Sold</th>
	                                <th>Sales Value</th>
	                                <th>Average Value</th>
	                                <th>Status</th>
	                            </tr>
	                        </thead>
	                        <tbody>
	                            <?php foreach ($itemsSoldByProduct as $soldProduct): ?>
	                                <?php
	                                    $soldQty = (int)$soldProduct['qty'];
	                                    $soldTotal = (float)$soldProduct['total'];
	                                    $averageValue = $soldQty > 0 ? $soldTotal / $soldQty : 0.0;
	                                    $soldProductImage = beverage_product_image_src($soldProduct['image'] ?? null);
	                                ?>
	                                <tr>
	                                    <td>
	                                        <div style="display:flex;align-items:center;gap:0.65rem;min-width:220px">
	                                            <img src="<?= e($soldProductImage !== '' ? $soldProductImage : beverage_product_image_fallback_src()) ?>" alt="<?= e($soldProduct['name']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;background:#fff">
	                                            <strong><?= e($soldProduct['name']) ?></strong>
	                                        </div>
	                                    </td>
	                                    <td><?= e($soldProduct['sku'] ?: 'No SKU') ?></td>
	                                    <td style="font-weight:900;color:#047857"><?= number_format($soldQty) ?></td>
	                                    <td style="font-weight:900;color:#0f172a">₦<?= number_format($soldTotal, 2) ?></td>
	                                    <td>₦<?= number_format($averageValue, 2) ?></td>
	                                    <td><span class="transfer-status <?= $soldQty > 0 ? '' : 'pending' ?>"><?= $soldQty > 0 ? 'Sold' : 'No sales yet' ?></span></td>
	                                </tr>
	                            <?php endforeach; ?>
	                        </tbody>
	                    </table>
	                </div>
	            </section>
	        <?php elseif ($activeTab === 'history'): ?>
	            <!-- Sales History View -->
	            <div style="padding:1.5rem">
                <div style="background:#fff;padding:1.5rem;border-radius:12px;border:1px solid #e2e8f0">
                    <h3 style="margin-bottom:1rem">📊 Sales History &amp; Receipts</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">
                                <th style="padding:0.75rem">Receipt #</th>
                                <th style="padding:0.75rem">Time</th>
                                <th style="padding:0.75rem">Customer</th>
                                <th style="padding:0.75rem">Payment Method</th>
                                <th style="padding:0.75rem">Grand Total</th>
                                <th style="padding:0.75rem">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesHistory as $tx): ?>
                                <tr style="border-bottom:1px solid #f1f5f9">
                                    <td style="padding:0.75rem"><strong><?= e($tx['receipt_no']) ?></strong></td>
                                    <td style="padding:0.75rem"><?= e($tx['timestamp']) ?></td>
                                    <td style="padding:0.75rem">
                                        <?= e($tx['customer_name']) ?>
                                        <?php if (!empty($tx['customer_phone'])): ?>
                                            <div style="color:#64748b;font-size:0.72rem;margin-top:0.12rem"><?= e($tx['customer_phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:0.75rem"><span style="background:#e0f2fe;color:#0369a1;padding:0.25rem 0.5rem;border-radius:4px;font-weight:700;font-size:0.75rem"><?= e($tx['payment_method']) ?></span></td>
                                    <td style="padding:0.75rem;color:#059669;font-weight:800">₦<?= number_format((float)$tx['grand_total'], 2) ?></td>
                                    <td style="padding:0.75rem">
                                        <button type="button" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:0.25rem 0.5rem;border-radius:4px;font-size:0.75rem;font-weight:700;cursor:pointer" onclick="openReprintReceipt(<?= htmlspecialchars(json_encode($tx), ENT_QUOTES, 'UTF-8') ?>)">🖨️ Reprint</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
	                </div>
	            </div>
	        <?php elseif ($activeTab === 'sales-account'): ?>
	            <section class="transfer-screen">
	                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
	                    <div>
	                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a">POS Sales Account</h2>
	                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.86rem">All beverage POS sales grouped for cashier closing, finance posting, and cash reconciliation.</p>
	                    </div>
		                    <div style="display:flex;gap:0.6rem;flex-wrap:wrap">
		                        <a href="<?= url('beverage_pos.php?tab=recon') ?>" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;text-decoration:none;border-radius:8px;padding:0.6rem 0.85rem;font-size:0.78rem;font-weight:900">Open Cash Recon</a>
		                    </div>
	                </div>

	                <div class="transfer-summary">
	                    <div class="transfer-card"><small>Total POS Sales</small><strong>₦<?= number_format($salesAccountTotals['gross_sales'], 2) ?></strong></div>
	                    <div class="transfer-card"><small>Receipts Count</small><strong><?= number_format($salesAccountTotals['receipts']) ?></strong></div>
	                    <div class="transfer-card"><small>Items Sold</small><strong><?= number_format($salesAccountTotals['items_sold']) ?></strong></div>
	                    <div class="transfer-card"><small>Crate Deposit</small><strong>₦<?= number_format($salesAccountTotals['crate_deposit'], 2) ?></strong></div>
	                </div>

	                <div class="transfer-panel">
	                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Reconciliation By Payment Method</h3>
	                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.75rem">
	                        <?php foreach ($salesByMethod as $methodName => $methodData): ?>
	                            <?php
	                                $methodLower = strtolower($methodName);
                                $methodStatus = 'Ready for cash close';
	                                if (str_contains($methodLower, 'transfer')) {
	                                    $methodStatus = count($pendingTransferPayments) > 0 || count($confirmedTransferPayments) > 0
	                                        ? 'Needs transfer confirmation'
	                                        : 'Transfers approved';
	                                } elseif (str_contains($methodLower, 'cash')) {
	                                    $methodStatus = 'Count physical cash';
	                                } elseif (str_contains($methodLower, 'debt') || str_contains($methodLower, 'credit')) {
	                                    $methodStatus = 'Post to customer ledger';
	                                }
	                            ?>
	                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.85rem;min-width:0">
	                                <small style="display:block;color:#64748b;font-weight:900;text-transform:uppercase;font-size:0.68rem"><?= e($methodName) ?></small>
	                                <strong style="display:block;margin-top:0.3rem;color:#0f172a;font-family:'Outfit',sans-serif;font-size:1.05rem">₦<?= number_format((float)$methodData['total'], 2) ?></strong>
	                                <span style="display:block;margin-top:0.3rem;color:#475569;font-size:0.72rem"><?= number_format((int)$methodData['count']) ?> receipts</span>
	                                <span class="transfer-status <?= str_contains(strtolower($methodStatus), 'needs') ? 'pending' : '' ?>" style="margin-top:0.55rem"><?= e($methodStatus) ?></span>
	                            </div>
	                        <?php endforeach; ?>
	                    </div>
	                </div>

	                <div class="transfer-panel">
	                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Depot vs Diversion Profitability</h3>
	                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem">
	                        <?php foreach (($commercialReports['channels'] ?? []) as $channel): ?>
	                            <?php
	                                $trueProfit = (float)($channel['true_profit'] ?? 0);
	                                $revenue = (float)($channel['revenue'] ?? 0);
	                                $marginPct = $revenue > 0 ? ($trueProfit / $revenue) * 100 : 0;
	                            ?>
	                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.9rem">
	                                <small style="display:block;color:#64748b;font-weight:900;text-transform:uppercase;font-size:0.68rem"><?= e((string)($channel['label'] ?? 'Sales Channel')) ?></small>
	                                <strong style="display:block;margin-top:0.35rem;color:#0f172a;font-family:'Outfit',sans-serif;font-size:1.05rem">₦<?= number_format($revenue, 2) ?></strong>
	                                <span style="display:block;margin-top:0.25rem;color:#475569;font-size:0.74rem"><?= number_format((int)($channel['qty'] ?? 0)) ?> packs/crates sold</span>
	                                <div style="display:grid;gap:0.2rem;margin-top:0.65rem;font-size:0.74rem;color:#475569">
	                                    <span>Invoice Cost: ₦<?= number_format((float)($channel['invoice_cost'] ?? 0), 2) ?></span>
	                                    <span>Rebate Benefit: ₦<?= number_format((float)($channel['rebate'] ?? 0), 2) ?></span>
	                                    <span>Promotion Benefit: ₦<?= number_format((float)($channel['promotion'] ?? 0), 2) ?></span>
	                                    <span>Effective Cost: ₦<?= number_format((float)($channel['effective_cost'] ?? 0), 2) ?></span>
	                                    <span>Direct Cost: ₦<?= number_format((float)($channel['direct_cost'] ?? 0), 2) ?></span>
	                                </div>
	                                <span class="transfer-status <?= $trueProfit < 0 ? 'pending' : '' ?>" style="margin-top:0.65rem">True Profit: <?= $trueProfit >= 0 ? '+₦' : '-₦' ?><?= number_format(abs($trueProfit), 2) ?> (<?= number_format($marginPct, 1) ?>%)</span>
	                                <span class="transfer-status <?= ((float)($channel['operational_net_margin'] ?? 0)) < 0 ? 'pending' : '' ?>" style="margin-top:0.35rem">Net Margin: <?= ((float)($channel['operational_net_margin'] ?? 0)) >= 0 ? '+₦' : '-₦' ?><?= number_format(abs((float)($channel['operational_net_margin'] ?? 0)), 2) ?></span>
	                            </div>
	                        <?php endforeach; ?>
	                    </div>
	                </div>

	                <div class="transfer-panel">
	                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">SKU / Customer Margin Reports</h3>
	                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem">
	                        <div>
	                            <h4 style="margin:0 0 0.65rem;color:#334155;font-size:0.85rem">Top SKU Profitability</h4>
	                            <table class="transfer-table">
	                                <thead><tr><th>SKU</th><th>Qty</th><th>Revenue</th><th>True Profit</th></tr></thead>
	                                <tbody>
	                                    <?php foreach (array_slice($commercialReports['sku_margin'] ?? [], 0, 8) as $row): ?>
	                                        <?php $profit = (float)($row['true_profit'] ?? 0); ?>
	                                        <tr><td><strong><?= e((string)$row['name']) ?></strong><small style="display:block;color:#64748b"><?= e((string)$row['sku']) ?></small></td><td><?= number_format((int)$row['qty']) ?></td><td>₦<?= number_format((float)$row['revenue'], 2) ?></td><td style="font-weight:900;color:<?= $profit >= 0 ? '#047857' : '#dc2626' ?>"><?= $profit >= 0 ? '+₦' : '-₦' ?><?= number_format(abs($profit), 2) ?></td></tr>
	                                    <?php endforeach; ?>
	                                </tbody>
	                            </table>
	                        </div>
	                        <div>
	                            <h4 style="margin:0 0 0.65rem;color:#334155;font-size:0.85rem">Customer / Distributor Margin</h4>
	                            <table class="transfer-table">
	                                <thead><tr><th>Customer</th><th>Qty</th><th>Revenue</th><th>True Profit</th></tr></thead>
	                                <tbody>
	                                    <?php foreach (array_slice($commercialReports['customer_margin'] ?? [], 0, 8) as $row): ?>
	                                        <?php $profit = (float)($row['true_profit'] ?? 0); ?>
	                                        <tr><td><strong><?= e((string)$row['name']) ?></strong></td><td><?= number_format((int)$row['qty']) ?></td><td>₦<?= number_format((float)$row['revenue'], 2) ?></td><td style="font-weight:900;color:<?= $profit >= 0 ? '#047857' : '#dc2626' ?>"><?= $profit >= 0 ? '+₦' : '-₦' ?><?= number_format(abs($profit), 2) ?></td></tr>
	                                    <?php endforeach; ?>
	                                </tbody>
	                            </table>
	                        </div>
	                    </div>
	                </div>

	                <div class="transfer-panel">
	                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Commercial Exceptions &amp; Promotions</h3>
	                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem">
	                        <div>
	                            <h4 style="margin:0 0 0.65rem;color:#334155;font-size:0.85rem">Expected vs Confirmed Rebate</h4>
	                            <?php if (empty($commercialReports['expected_vs_confirmed_rebate'])): ?>
	                                <div style="color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:1rem;font-size:0.82rem">No rebate expectation or settlement records yet.</div>
	                            <?php else: ?>
	                                <table class="transfer-table"><thead><tr><th>SKU</th><th>Expected</th><th>Confirmed</th><th>Variance</th></tr></thead><tbody>
	                                <?php foreach (array_slice($commercialReports['expected_vs_confirmed_rebate'], 0, 8) as $row): ?>
	                                    <?php $variance = (float)($row['variance'] ?? 0); ?>
	                                    <tr><td><strong><?= e((string)$row['sku']) ?></strong></td><td>₦<?= number_format((float)$row['expected_amount'], 2) ?></td><td>₦<?= number_format((float)$row['confirmed_amount'], 2) ?></td><td style="font-weight:900;color:<?= $variance >= 0 ? '#047857' : '#dc2626' ?>"><?= $variance >= 0 ? '+₦' : '-₦' ?><?= number_format(abs($variance), 2) ?></td></tr>
	                                <?php endforeach; ?>
	                                </tbody></table>
	                            <?php endif; ?>
	                        </div>
	                        <div>
	                            <h4 style="margin:0 0 0.65rem;color:#334155;font-size:0.85rem">Promotion Utilization</h4>
	                            <?php if (empty($commercialReports['promotion_utilization'])): ?>
	                                <div style="color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:1rem;font-size:0.82rem">No promotion has been used in completed sales yet.</div>
	                            <?php else: ?>
	                                <table class="transfer-table"><thead><tr><th>Promotion</th><th>Qty</th><th>Benefit</th><th>Profit</th></tr></thead><tbody>
	                                <?php foreach ($commercialReports['promotion_utilization'] as $promo): ?>
	                                    <tr><td><strong><?= e((string)$promo['name']) ?></strong><small style="display:block;color:#64748b"><?= e((string)$promo['code']) ?></small></td><td><?= number_format((int)$promo['qty']) ?></td><td>₦<?= number_format((float)$promo['benefit'], 2) ?></td><td>₦<?= number_format((float)$promo['true_profit'], 2) ?></td></tr>
	                                <?php endforeach; ?>
	                                </tbody></table>
	                            <?php endif; ?>
	                        </div>
	                        <div>
	                            <h4 style="margin:0 0 0.65rem;color:#334155;font-size:0.85rem">Below Invoice But Profitable</h4>
	                            <div style="display:grid;gap:0.5rem">
	                                <?php foreach (array_slice($commercialReports['below_invoice_profitable'] ?? [], 0, 5) as $row): ?>
	                                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.75rem;font-size:0.78rem"><strong><?= e((string)$row['name']) ?></strong><span style="display:block;color:#166534">Sold ₦<?= number_format((float)$row['selling'], 2) ?> vs invoice ₦<?= number_format((float)$row['invoice_cost'], 2) ?>, true profit +₦<?= number_format((float)$row['true_profit'], 2) ?></span></div>
	                                <?php endforeach; ?>
	                                <?php if (empty($commercialReports['below_invoice_profitable'])): ?><div style="color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:1rem;font-size:0.82rem">No below-invoice profitable sales yet.</div><?php endif; ?>
	                            </div>
	                        </div>
	                        <div>
	                            <h4 style="margin:0 0 0.65rem;color:#334155;font-size:0.85rem">True Loss-Making Transactions</h4>
	                            <div style="display:grid;gap:0.5rem">
	                                <?php foreach (array_slice($commercialReports['true_losses'] ?? [], 0, 5) as $row): ?>
	                                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:0.75rem;font-size:0.78rem"><strong><?= e((string)$row['name']) ?></strong><span style="display:block;color:#b91c1c"><?= e((string)$row['receipt']) ?> true loss -₦<?= number_format(abs((float)$row['true_profit']), 2) ?></span></div>
	                                <?php endforeach; ?>
	                                <?php if (empty($commercialReports['true_losses'])): ?><div style="color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:1rem;font-size:0.82rem">No true loss-making transactions found.</div><?php endif; ?>
	                            </div>
	                        </div>
	                        <div>
	                            <h4 style="margin:0 0 0.65rem;color:#334155;font-size:0.85rem">Price Override Report</h4>
	                            <div style="display:grid;gap:0.5rem">
	                                <?php foreach (array_slice($commercialReports['overrides'] ?? [], 0, 5) as $log): ?>
	                                    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:0.75rem;font-size:0.78rem"><strong><?= e((string)($log['entity_id'] ?? 'Override')) ?></strong><span style="display:block;color:#9a3412"><?= e((string)($log['user'] ?? 'Admin')) ?> · <?= e((string)($log['timestamp'] ?? '')) ?></span></div>
	                                <?php endforeach; ?>
	                                <?php if (empty($commercialReports['overrides'])): ?><div style="color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:1rem;font-size:0.82rem">No price overrides recorded yet.</div><?php endif; ?>
	                            </div>
	                        </div>
	                    </div>
	                </div>

	                <div class="transfer-panel">
	                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">POS Sales Ledger For Reconciliation</h3>
	                    <?php if (empty($beverageSalesHistory)): ?>
	                        <div style="text-align:center;color:#64748b;padding:2.5rem 1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px">
	                            No POS sales have been recorded yet.
	                        </div>
	                    <?php else: ?>
	                        <table class="transfer-table">
	                            <thead>
	                                <tr>
	                                    <th>Receipt #</th>
	                                    <th>Time</th>
	                                    <th>Customer</th>
	                                    <th>Payment Method</th>
	                                    <th>Subtotal</th>
	                                    <th>Crate Deposit</th>
	                                    <th>Total</th>
	                                    <th>Recon Note</th>
	                                </tr>
	                            </thead>
	                            <tbody>
	                                <?php foreach ($beverageSalesHistory as $tx): ?>
	                                    <?php
	                                        $method = (string)($tx['payment_method'] ?? 'Cash');
                                        $reconNote = 'Include in cash closing';
	                                        if (str_contains(strtolower($method), 'transfer')) {
	                                            $reconNote = (string)($tx['transfer_status'] ?? 'Pending POS Confirmation');
	                                        } elseif (str_contains(strtolower($method), 'debt') || str_contains(strtolower($method), 'credit')) {
	                                            $reconNote = 'Customer ledger posting required';
	                                        }
	                                    ?>
	                                    <tr>
	                                        <td><strong><?= e($tx['receipt_no'] ?? 'N/A') ?></strong></td>
	                                        <td><?= e($tx['timestamp'] ?? '') ?></td>
	                                        <td><?= e($tx['customer_name'] ?? 'Walk-in Customer') ?></td>
	                                        <td><?= e($method) ?></td>
	                                        <td>₦<?= number_format((float)($tx['subtotal'] ?? 0), 2) ?></td>
	                                        <td>₦<?= number_format((float)($tx['crate_deposit'] ?? 0), 2) ?></td>
	                                        <td style="font-weight:900;color:#047857">₦<?= number_format((float)($tx['grand_total'] ?? 0), 2) ?></td>
	                                        <td><span class="transfer-status <?= str_contains(strtolower($reconNote), 'pending') ? 'pending' : (str_contains(strtolower($reconNote), 'awaiting') ? 'confirmed' : '') ?>"><?= e($reconNote) ?></span></td>
	                                    </tr>
	                                <?php endforeach; ?>
	                            </tbody>
	                        </table>
	                    <?php endif; ?>
	                </div>
	            </section>
        <?php elseif ($activeTab === 'customers'): ?>
            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a">POS Customer Page</h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.86rem">All beverage customers saved from the POS terminal, including walk-in customers with phone numbers.</p>
                    </div>
                    <a href="<?= url('beverage_pos.php?tab=pos') ?>" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;text-decoration:none;border-radius:8px;padding:0.6rem 0.85rem;font-size:0.78rem;font-weight:900">Back to Sell</a>
                </div>

                <div class="customer-summary-row">
                    <div class="transfer-card"><small>Total Customers</small><strong><?= number_format(count($customers)) ?></strong></div>
                    <div class="transfer-card"><small>With Phone</small><strong><?= number_format(count(array_filter($customers, static fn (array $customer): bool => !empty($customer['phone'])))) ?></strong></div>
                    <div class="transfer-card"><small>Customer Debt</small><strong>₦<?= number_format(array_sum(array_map(static fn (array $customer): float => (float)($customer['current_debt'] ?? 0), $customers)), 2) ?></strong></div>
                </div>

                <div class="transfer-panel">
                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Customer List</h3>
                    <div class="customer-list-grid" id="customerListGrid">
                        <?php if (empty($customers)): ?>
                            <div class="empty-state" style="grid-column:1 / -1;text-align:center;padding:2rem 1rem;color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px">
                                <strong style="display:block;color:#0f172a;font-family:'Outfit',sans-serif;font-size:1rem;margin-bottom:0.35rem">No customers saved yet</strong>
                                <span style="display:block;font-size:0.84rem;line-height:1.55">Customers added from the POS terminal will appear here with their phone number, wallet, debt, and crate balance.</span>
                                <a href="<?= url('beverage_pos.php?tab=pos') ?>" style="display:inline-flex;margin-top:1rem;background:#7f1234;color:#fff;text-decoration:none;border-radius:8px;padding:0.65rem 0.9rem;font-size:0.78rem;font-weight:900">Add Customer From POS</a>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                                $customerName = (string)($customer['name'] ?? 'Customer');
                                $customerPhone = (string)($customer['phone'] ?? '');
                            ?>
                            <article class="customer-contact-card" data-customer-title="<?= e(strtolower($customerName . ' ' . $customerPhone)) ?>">
                                <div class="customer-contact-top">
                                    <strong><?= e($customerName) ?></strong>
                                    <span class="customer-phone-pill"><?= $customerPhone !== '' ? e($customerPhone) : 'No phone' ?></span>
                                </div>
                                <div class="customer-contact-meta">
                                    <div>
                                        <span>Wallet</span>
                                        <b>₦<?= number_format((float)($customer['wallet_balance'] ?? 0), 2) ?></b>
                                    </div>
                                    <div>
                                        <span>Credit Limit</span>
                                        <b>₦<?= number_format((float)($customer['credit_limit'] ?? 0), 2) ?></b>
                                    </div>
                                    <div>
                                        <span>Current Debt</span>
                                        <b>₦<?= number_format((float)($customer['current_debt'] ?? 0), 2) ?></b>
                                    </div>
                                    <div>
                                        <span>Crates Held</span>
                                        <b><?= number_format((int)($customer['crates_held'] ?? 0)) ?></b>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
	        <?php elseif ($activeTab === 'transfers'): ?>
	            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a">Stock Movement</h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.86rem">Move beverage products between Jacroxx Warehouse and Ijaba Warehouse.</p>
                    </div>
                    <a href="<?= url('beverage_warehouse.php?tab=tab-inventory') ?>" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;text-decoration:none;border-radius:8px;padding:0.6rem 0.85rem;font-size:0.78rem;font-weight:900">Open Warehouse Products</a>
                </div>

	                <div class="transfer-summary">
	                    <?php foreach (($stockWarehouses ?? []) as $warehouseName): ?>
                            <?php $summary = $stockWarehouseSummaries[$warehouseName] ?? ['stock_crates' => 0, 'stock_bottles' => 0, 'sku_count' => 0]; ?>
	                        <div class="transfer-card">
                                <small><?= e($warehouseName) ?></small>
                                <strong><?= number_format((int)($summary['stock_crates'] ?? 0)) ?></strong>
                                <span style="display:block;margin-top:0.25rem;color:#64748b;font-size:0.75rem;font-weight:700"><?= number_format((int)($summary['sku_count'] ?? 0)) ?> SKUs | <?= number_format((int)($summary['stock_bottles'] ?? 0)) ?> units</span>
                            </div>
                        <?php endforeach; ?>
	                    <div class="transfer-card"><small>Total Movements</small><strong><?= number_format(count($stockMovementLog ?? [])) ?></strong></div>
	                    <div class="transfer-card"><small>Pending Transfers</small><strong><?= number_format(count(array_filter($stockMovementLog ?? [], static fn(array $movement): bool => ($movement['status'] ?? '') === 'In Transit'))) ?></strong></div>
	                </div>

                <div class="stock-movement-grid">
                    <div class="transfer-panel">
                        <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Create Stock Movement</h3>
                        <form method="post" action="<?= url('beverage_pos.php?tab=transfers') ?>" class="stock-movement-form" onsubmit="return validateStockMovementForm()">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="create_stock_movement">
                            <div>
                                <label>Product</label>
                                <select name="sku" id="movementSku" required onchange="updateMovementAvailability()">
                                    <option value="">Select product</option>
                                    <?php foreach ($catalog as $product): ?>
                                        <?php
                                            $skuKey = strtoupper(trim((string)($product['sku'] ?? '')));
                                            $stockMap = is_array($stockByWarehouseMap ?? null) ? ($stockByWarehouseMap[$skuKey] ?? []) : [];
                                            $dataAttrs = '';
                                            foreach (($stockWarehouses ?? []) as $warehouseName) {
                                                $dataAttrs .= ' data-stock-' . e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $warehouseName))) . '="' . (int)($stockMap[$warehouseName] ?? 0) . '"';
                                            }
                                        ?>
                                        <option value="<?= e((string)($product['sku'] ?? '')) ?>"<?= $dataAttrs ?>><?= e((string)($product['name'] ?? 'Product')) ?> | <?= e((string)($product['sku'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>From Warehouse</label>
                                <select name="from_location" id="movementFrom" required onchange="updateMovementAvailability()">
                                    <?php foreach (($stockWarehouses ?? []) as $warehouseName): ?>
                                        <option value="<?= e($warehouseName) ?>"><?= e($warehouseName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>To Warehouse</label>
                                <select name="to_location" id="movementTo" required onchange="updateMovementAvailability()">
                                    <?php foreach (($stockWarehouses ?? []) as $index => $warehouseName): ?>
                                        <option value="<?= e($warehouseName) ?>" <?= $index === 1 ? 'selected' : '' ?>><?= e($warehouseName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Quantity to Move</label>
                                <input type="number" name="quantity" id="movementQty" min="1" required placeholder="Enter crates / packs">
                            </div>
                            <div class="stock-movement-note" id="movementAvailabilityNote">Select a product to see available stock.</div>
                            <button type="submit" class="stock-movement-submit">Move Stock</button>
                        </form>
                    </div>

                    <div class="transfer-panel">
                        <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Stock Movement History</h3>
                        <?php if (empty($stockMovementLog)): ?>
                            <div style="text-align:center;color:#64748b;padding:2.5rem 1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px">
                                No warehouse stock movement has been recorded yet.
                            </div>
                        <?php else: ?>
                        <table class="transfer-table">
                            <thead>
                                <tr>
                                    <th>Movement #</th>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Qty</th>
                                    <th>Status</th>
	                                </tr>
	                            </thead>
	                            <tbody>
	                                <?php foreach ($stockMovementLog as $movement): ?>
                                        <?php $movementStatus = strtolower((string)($movement['status'] ?? 'completed')); ?>
	                                    <tr>
	                                        <td><strong><?= e((string)($movement['id'] ?? 'TRF')) ?></strong></td>
	                                        <td><?= e((string)($movement['date'] ?? '')) ?></td>
	                                        <td><?= e((string)($movement['item'] ?? $movement['sku'] ?? 'Product')) ?><div style="color:#64748b;font-size:0.7rem"><?= e((string)($movement['sku'] ?? '')) ?></div></td>
	                                        <td><?= e((string)($movement['from'] ?? '')) ?></td>
	                                        <td><?= e((string)($movement['to'] ?? '')) ?></td>
	                                        <td style="font-weight:900"><?= number_format((int)($movement['quantity'] ?? 0)) ?></td>
	                                        <td><span class="transfer-status movement-status <?= e($movementStatus) ?>"><?= e((string)($movement['status'] ?? 'Completed')) ?></span></td>
	                                    </tr>
	                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($activeTab === 'pricing-tiers'): ?>
            <?php
                $selectedSku = strtoupper(trim((string)($_GET['sku'] ?? ($catalog[0]['sku'] ?? ''))));
                $selectedProduct = null;
                foreach ($catalog as $catItem) {
                    if (strcasecmp((string)($catItem['sku'] ?? ''), $selectedSku) === 0) {
                        $selectedProduct = $catItem;
                        break;
                    }
                }
                if (!$selectedProduct && !empty($catalog)) {
                    $selectedProduct = $catalog[0];
                    $selectedSku = strtoupper((string)($selectedProduct['sku'] ?? ''));
                }
                $productTiers = class_exists('App\Modules\Pos\PricingRebateService') && $selectedSku !== ''
                    ? \App\Modules\Pos\PricingRebateService::getProductTiers($selectedSku)
                    : [];
                $rebateInfo = class_exists('App\Modules\Pos\PricingRebateService') && $selectedProduct
                    ? \App\Modules\Pos\PricingRebateService::resolveRebate($selectedProduct)
                    : ['rebate_pct' => 0.0, 'level' => 'global', 'rule_name' => 'No active rebate'];
                $cost = (float)($selectedProduct['cost_price'] ?? 0);
                $rebatePct = (float)($rebateInfo['rebate_pct'] ?? 0.0);
                $effCost = max(0.0, $cost - ($cost * ($rebatePct / 100.0)));
            ?>
            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a;display:flex;align-items:center;gap:0.5rem">
                            🏷️ Dynamic Quantity Pricing Tiers
                        </h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.85rem">
                            Configure flexible volume price breaks per product and sales type (Depot Sale, Diversion Sale, or Global). Overlapping ranges are automatically prevented per channel.
                        </p>
                    </div>
                    <?php if (!empty($canManagePricing)): ?>
                    <div style="display:flex;gap:0.5rem">
                        <button type="button" onclick="openAddPricingTierModal('<?= e($selectedSku) ?>')" style="background:#0284c7;color:#fff;border:none;border-radius:8px;padding:0.6rem 1rem;font-weight:800;font-size:0.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.4rem;box-shadow:0 4px 12px rgba(2,132,199,0.25)">
                            + Add New Price Tier
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Selector Card -->
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:0.75rem;flex:1;min-width:280px">
                        <label for="tierProductSelect" style="font-weight:800;font-size:0.85rem;color:#334155;white-space:nowrap">Select Product:</label>
                        <select id="tierProductSelect" onchange="window.location.href='<?= url('beverage_pos.php?tab=pricing-tiers&sku=') ?>' + encodeURIComponent(this.value)" style="flex:1;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.88rem;background:#f8fafc;color:#0f172a;font-weight:700">
                            <?php foreach ($catalog as $p): ?>
                                <option value="<?= e($p['sku']) ?>" <?= strcasecmp((string)$p['sku'], $selectedSku) === 0 ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> (SKU: <?= e($p['sku']) ?>) • <?= e($p['packaging']) ?> • Cost: ₦<?= number_format((float)($p['cost_price'] ?? 0), 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selectedProduct): ?>
                        <div style="display:flex;gap:1.25rem;align-items:center;background:#f8fafc;padding:0.5rem 1rem;border-radius:8px;border:1px solid #e2e8f0;font-size:0.8rem">
                            <div><span style="color:#64748b">Cost Price:</span> <strong style="color:#0f172a">₦<?= number_format($cost, 2) ?></strong></div>
                            <div><span style="color:#64748b">Active Rebate:</span> <strong style="color:#2563eb"><?= $rebatePct ?>%</strong> <small>(<?= e($rebateInfo['rule_name'] ?? '') ?>)</small></div>
                            <div><span style="color:#64748b">Effective Cost:</span> <strong style="color:#059669">₦<?= number_format($effCost, 2) ?></strong></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tiers Table Card -->
                <div class="pos-table-card" style="padding:1.25rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                        <div>
                            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;color:#0f172a">
                                Active Pricing Tiers for <?= e($selectedProduct['name'] ?? $selectedSku) ?>
                            </h3>
                            <small style="color:#64748b;font-size:0.75rem">When cashiers enter quantities in POS, the matching tier price is automatically applied in real time.</small>
                        </div>
                        <span style="font-size:0.8rem;background:#f0fdf4;color:#166534;padding:0.3rem 0.6rem;border-radius:6px;border:1px solid #bbf7d0;font-weight:700">
                            <?= count($productTiers) ?> Tier(s) Configured
                        </span>
                    </div>

                    <?php if (empty($productTiers)): ?>
                        <div style="text-align:center;padding:2.5rem 1rem;color:#64748b;background:#f8fafc;border-radius:8px;border:1px dashed #cbd5e1">
                            <span style="font-size:2rem;display:block;margin-bottom:0.4rem">🏷️</span>
                            <strong>No custom quantity tiers configured for this product.</strong>
                            <p style="font-size:0.8rem;margin:0.25rem 0 1rem">Standard wholesale price (₦<?= number_format((float)($selectedProduct['wholesale_price'] ?? $cost), 2) ?>) will be used for all quantities.</p>
                            <?php if (!empty($canManagePricing)): ?>
                            <button type="button" onclick="openAddPricingTierModal('<?= e($selectedSku) ?>')" style="background:#0284c7;color:#fff;border:none;border-radius:6px;padding:0.5rem 1rem;font-weight:700;font-size:0.8rem;cursor:pointer">
                                + Create First Tier (e.g. 1–4, 5+)
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
                            <thead>
                                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">
                                    <th style="padding:0.75rem">Tier Name</th>
                                    <th style="padding:0.75rem">Sales Type</th>
                                    <th style="padding:0.75rem">Quantity Range</th>
                                    <th style="padding:0.75rem">Selling Price / Unit</th>
                                    <th style="padding:0.75rem">Economics &amp; True Profit</th>
                                    <th style="padding:0.75rem">Validity Period</th>
                                    <th style="padding:0.75rem">Status</th>
                                    <th style="padding:0.75rem;text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productTiers as $t): ?>
                                    <?php
                                        $tPrice = (float)($t['selling_price'] ?? 0);
                                        $tMin = (int)($t['min_qty'] ?? 1);
                                        $tMax = (empty($t['max_qty']) || (int)$t['max_qty'] <= 0) ? null : (int)$t['max_qty'];
                                        $rangeText = $tMax ? "{$tMin} – {$tMax} Units" : "{$tMin}+ Units (Bulk)";
                                        $tProfit = $tPrice - $effCost;
                                        $tMarginPct = $tPrice > 0 ? ($tProfit / $tPrice) * 100 : 0;
                                        $tIsActive = isset($t['is_active']) ? (bool)$t['is_active'] : true;
                                    ?>
                                    <tr style="border-bottom:1px solid #f1f5f9;background:<?= $tIsActive ? '#fff' : '#f8fafc' ?>">
                                        <td style="padding:0.75rem">
                                            <strong style="color:#0f172a"><?= e($t['tier_name']) ?></strong>
                                            <small style="display:block;color:#64748b;font-size:0.7rem">ID: <?= e($t['id']) ?></small>
                                        </td>
                                        <td style="padding:0.75rem">
                                            <?php
                                                $channelLabel = match ((string)($t['sales_channel'] ?? 'all')) {
                                                    'depot_sale' => 'Depot Sale',
                                                    'diversion_sale' => 'Diversion Sale',
                                                    default => 'Global',
                                                };
                                            ?>
                                            <span style="background:#eff6ff;color:#1d4ed8;padding:0.25rem 0.55rem;border-radius:999px;font-weight:800;font-size:0.72rem"><?= e($channelLabel) ?></span>
                                            <?php if (!empty($t['min_selling_price'])): ?>
                                                <small style="display:block;color:#dc2626;margin-top:0.2rem">Min: ₦<?= number_format((float)$t['min_selling_price'], 2) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem">
                                            <span style="background:#f1f5f9;color:#334155;padding:0.25rem 0.55rem;border-radius:4px;font-weight:800;font-size:0.8rem">
                                                📦 <?= e($rangeText) ?>
                                            </span>
                                        </td>
                                        <td style="padding:0.75rem">
                                            <strong style="font-size:1rem;color:#0f172a">₦<?= number_format($tPrice, 2) ?></strong>
                                            <?php if ($tPrice < $cost && $tProfit > 0): ?>
                                                <span style="display:block;font-size:0.68rem;color:#059669;font-weight:800">⚡ Below standard cost (Profitable via <?= $rebatePct ?>% rebate)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem">
                                            <div style="font-size:0.75rem;line-height:1.4">
                                                <div>Eff. Cost: <span style="color:#64748b">₦<?= number_format($effCost, 0) ?></span></div>
                                                <div>True Profit: <strong style="color:<?= $tProfit >= 0 ? '#059669' : '#dc2626' ?>"><?= $tProfit >= 0 ? '+₦' : '-₦' ?><?= number_format(abs($tProfit), 2) ?> (<?= number_format($tMarginPct, 1) ?>%)</strong></div>
                                            </div>
                                        </td>
                                        <td style="padding:0.75rem;font-size:0.75rem;color:#64748b">
                                            <div>From: <?= e($t['start_date'] ?? 'Immediate') ?></div>
                                            <div>To: <?= e($t['end_date'] ?? 'Open-ended') ?></div>
                                        </td>
                                        <td style="padding:0.75rem">
                                            <?php if ($tIsActive): ?>
                                                <span style="background:#dcfce7;color:#15803d;padding:0.2rem 0.5rem;border-radius:999px;font-weight:800;font-size:0.72rem">Active</span>
                                            <?php else: ?>
                                                <span style="background:#fee2e2;color:#b91c1c;padding:0.2rem 0.5rem;border-radius:999px;font-weight:800;font-size:0.72rem">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem;text-align:right">
                                            <?php if (!empty($canManagePricing)): ?>
                                            <div style="display:inline-flex;gap:0.35rem">
                                                <button type="button" onclick="openEditPricingTierModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:0.35rem 0.65rem;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer">✏️ Edit</button>
                                                <form method="post" action="<?= url('beverage_pos.php?tab=pricing-tiers') ?>" onsubmit="return confirm('Delete pricing tier <?= e($t['tier_name']) ?>?');" style="display:inline;margin:0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="delete_pricing_tier">
                                                    <input type="hidden" name="tier_id" value="<?= e($t['id']) ?>">
                                                    <input type="hidden" name="sku" value="<?= e($selectedSku) ?>">
                                                    <button type="submit" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:0.35rem 0.65rem;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer">🗑️</button>
                                                </form>
                                            </div>
                                            <?php else: ?>
                                                <span style="color:#94a3b8;font-size:0.75rem">View only</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

        <?php elseif ($activeTab === 'rebates'): ?>
            <?php
                $rebateRules = class_exists('App\Modules\Pos\PricingRebateService')
                    ? \App\Modules\Pos\PricingRebateService::getRebateSettings()
                    : [];
                $rebateSettlements = class_exists('App\Modules\Pos\PricingRebateService')
                    ? \App\Modules\Pos\PricingRebateService::getRebateSettlements()
                    : [];
                $rebateSuppliers = is_array($rebateSuppliers ?? null) ? $rebateSuppliers : [];
                foreach ($rebateRules as $rebateRule) {
                    if (strtolower((string)($rebateRule['level'] ?? '')) !== 'supplier') {
                        continue;
                    }
                    $supplier = trim((string)($rebateRule['target_key'] ?? ''));
                    if ($supplier !== '') {
                        $rebateSuppliers[strtoupper($supplier)] = $supplier;
                    }
                }
                asort($rebateSuppliers, SORT_NATURAL | SORT_FLAG_CASE);
                $rebateSupplierProducts = [];
                foreach (is_array($supplierCatalog ?? null) ? $supplierCatalog : [] as $supplierName => $brands) {
                    $products = [];
                    foreach (is_array($brands) ? $brands : [] as $brand => $packSizes) {
                        foreach (is_array($packSizes) ? $packSizes : [] as $packSize) {
                            $products[] = [
                                'value' => $brand . ' - ' . $packSize,
                                'label' => $brand . ' - ' . $packSize,
                            ];
                        }
                    }
                    $rebateSupplierProducts[$supplierName] = $products;
                }
                $catalogProducts = [];
                $catalogProductsBySupplier = [];
                $supplierBrandMap = [];
                foreach (is_array($supplierCatalog ?? null) ? $supplierCatalog : [] as $supplierName => $brands) {
                    foreach (array_keys(is_array($brands) ? $brands : []) as $brand) {
                        $supplierBrandMap[strtoupper(trim((string)$brand))] = $supplierName;
                    }
                }
                foreach (is_array($catalog ?? null) ? $catalog : [] as $product) {
                    $sku = trim((string)($product['sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $label = trim((string)($product['name'] ?? $sku)) . ' - ' . $sku;
                    $catalogProducts[] = ['value' => $sku, 'label' => $label];
                    $supplier = trim((string)($product['supplier'] ?? ''));
                    if ($supplier === '') {
                        $productName = strtoupper(trim((string)($product['name'] ?? '')));
                        foreach ($supplierBrandMap as $brand => $mappedSupplier) {
                            if ($brand !== '' && str_contains($productName, $brand)) {
                                $supplier = $mappedSupplier;
                                break;
                            }
                        }
                    }
                    $supplier = $supplier !== '' ? $supplier : 'Other / Unassigned';
                    $catalogProductsBySupplier[$supplier] = $catalogProductsBySupplier[$supplier] ?? [];
                    $catalogProductsBySupplier[$supplier][] = ['value' => $sku, 'label' => $label];
                    if ($supplier !== '') {
                        $rebateSupplierProducts[$supplier] = $rebateSupplierProducts[$supplier] ?? [];
                        $rebateSupplierProducts[$supplier][] = ['value' => $sku, 'label' => $label];
                    }
                }
            ?>
            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a;display:flex;align-items:center;gap:0.5rem">
                            💰 Supplier Rebate Management &amp; Economics
                        </h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.85rem">
                            Configure hierarchical supplier rebates. Specificity hierarchy: <strong>Promotion &gt; Product &gt; Brand &gt; Supplier &gt; Global</strong>.
                        </p>
                    </div>
                    <?php if (!empty($canManagePricing)): ?>
                    <div style="display:flex;gap:0.5rem">
                        <button type="button" onclick="openAddRebateModal()" style="background:#16a34a;color:#fff;border:none;border-radius:8px;padding:0.6rem 1rem;font-weight:800;font-size:0.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.4rem;box-shadow:0 4px 12px rgba(22,163,74,0.25)">
                            + Add Rebate Rule
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Hierarchy Banner -->
                <div style="background:linear-gradient(135deg, #0f172a, #1e293b);color:#fff;border-radius:10px;padding:1.25rem;margin-bottom:1.25rem">
                    <strong style="display:block;font-size:0.92rem;margin-bottom:0.5rem;color:#38bdf8">⚡ Rebate Hierarchy Resolution Order (Highest to Lowest Priority):</strong>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:0.75rem;font-size:0.78rem">
                        <div style="background:rgba(255,255,255,0.08);padding:0.6rem 0.8rem;border-radius:6px;border-left:3px solid #f59e0b">
                            <strong style="display:block;color:#fcd34d">1. Promotion Code</strong>
                            <span>Campaign / Flash promo discount</span>
                        </div>
                        <div style="background:rgba(255,255,255,0.08);padding:0.6rem 0.8rem;border-radius:6px;border-left:3px solid #38bdf8">
                            <strong style="display:block;color:#7dd3fc">2. Product SKU</strong>
                            <span>Overrides brand and supplier for exact SKU</span>
                        </div>
                        <div style="background:rgba(255,255,255,0.08);padding:0.6rem 0.8rem;border-radius:6px;border-left:3px solid #a855f7">
                            <strong style="display:block;color:#d8b4fe">3. Brand</strong>
                            <span>e.g. NBC Coca-Cola, 7UP, Big Cola</span>
                        </div>
                        <div style="background:rgba(255,255,255,0.08);padding:0.6rem 0.8rem;border-radius:6px;border-left:3px solid #10b981">
                            <strong style="display:block;color:#6ee7b7">4. Supplier</strong>
                            <span>Manufacturer volume contract</span>
                        </div>
                        <div style="background:rgba(255,255,255,0.08);padding:0.6rem 0.8rem;border-radius:6px;border-left:3px solid #94a3b8">
                            <strong style="display:block;color:#cbd5e1">5. Global Baseline</strong>
                            <span>Standard depot fallback percentage</span>
                        </div>
                    </div>
                </div>

                <!-- Interactive Formula Simulator Sandbox -->
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:1.25rem;margin-bottom:1.25rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem">
                        <h4 style="margin:0;font-family:'Outfit',sans-serif;color:#166534;font-size:0.95rem">
                            🧮 Live Interactive Profit &amp; Rebate Simulator
                        </h4>
                        <small style="color:#15803d;font-weight:700">Formula: Effective Cost = Cost − (Cost × Rebate %) | True Profit = Selling Price − Effective Cost</small>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.75rem;align-items:end;margin-bottom:1rem">
                        <div>
                            <label style="font-size:0.72rem;font-weight:800;color:#15803d;display:block;margin-bottom:0.25rem">Original Cost Price (₦):</label>
                            <input type="number" id="sim_cost" value="5000" oninput="runRebateSimulator()" style="width:100%;padding:0.5rem;border:1px solid #86efac;border-radius:6px;font-weight:800">
                        </div>
                        <div>
                            <label style="font-size:0.72rem;font-weight:800;color:#15803d;display:block;margin-bottom:0.25rem">Rebate %:</label>
                            <input type="number" id="sim_rebate" value="7" step="0.1" oninput="runRebateSimulator()" style="width:100%;padding:0.5rem;border:1px solid #86efac;border-radius:6px;font-weight:800">
                        </div>
                        <div>
                            <label style="font-size:0.72rem;font-weight:800;color:#15803d;display:block;margin-bottom:0.25rem">Selling Price (₦):</label>
                            <input type="number" id="sim_price" value="4800" oninput="runRebateSimulator()" style="width:100%;padding:0.5rem;border:1px solid #86efac;border-radius:6px;font-weight:800">
                        </div>
                        <div>
                            <label style="font-size:0.72rem;font-weight:800;color:#15803d;display:block;margin-bottom:0.25rem">Quantity (Packs/Crates):</label>
                            <input type="number" id="sim_qty" value="5" min="1" oninput="runRebateSimulator()" style="width:100%;padding:0.5rem;border:1px solid #86efac;border-radius:6px;font-weight:800">
                        </div>
                    </div>

                    <div id="sim_results_box" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:0.75rem;background:#fff;padding:0.85rem;border-radius:8px;border:1px solid #86efac;font-size:0.8rem">
                        <div><small style="color:#64748b;display:block">Rebate Amount</small><strong id="sim_res_rebate" style="color:#2563eb;font-size:1.05rem">₦350.00</strong></div>
                        <div><small style="color:#64748b;display:block">Effective Cost</small><strong id="sim_res_eff_cost" style="color:#0f172a;font-size:1.05rem">₦4,650.00</strong></div>
                        <div><small style="color:#64748b;display:block">Profit Before Rebate</small><strong id="sim_res_profit_before" style="color:#dc2626;font-size:1.05rem">-₦200.00</strong></div>
                        <div><small style="color:#64748b;display:block">True Profit / Unit</small><strong id="sim_res_true_profit" style="color:#059669;font-size:1.05rem">+₦150.00</strong></div>
                        <div><small style="color:#64748b;display:block">Batch Total Profit (x5)</small><strong id="sim_res_total_profit" style="color:#059669;font-size:1.05rem">+₦750.00</strong></div>
                    </div>
                </div>

                <div class="pos-table-card" style="padding:1.25rem;margin-bottom:1.25rem">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
                        <div>
                            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;color:#0f172a">Expected vs Confirmed Rebate</h3>
                            <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.78rem">Record supplier confirmations and payments without changing historical sales snapshots.</p>
                        </div>
                        <span style="font-size:0.78rem;background:#f8fafc;color:#334155;border:1px solid #e2e8f0;border-radius:999px;padding:0.35rem 0.65rem;font-weight:800"><?= count($rebateSettlements) ?> settlement(s)</span>
                    </div>
                    <?php if (!empty($canManagePricing)): ?>
                        <form method="post" action="<?= url('beverage_pos.php?tab=rebates') ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;align-items:end;margin-bottom:1rem">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="record_rebate_settlement">
                            <input type="hidden" name="settlement_id" id="rebateSettlementId" value="">
                            <div class="settings-field"><label for="rebateSupplier">Supplier</label><select id="rebateSupplier" name="supplier"><option value="">Select supplier</option><?php foreach ($rebateSuppliers as $supplier): ?><option value="<?= e((string)$supplier) ?>"><?= e((string)$supplier) ?></option><?php endforeach; ?></select></div>
                            <div class="settings-field"><label for="rebateSku">SKU / ALL</label><select id="rebateSku" name="sku"><option value="ALL">ALL</option></select></div>
                            <div class="settings-field"><label>Period Start</label><input id="rebatePeriodStart" type="date" name="period_start" value="<?= date('Y-m-01') ?>"></div>
                            <div class="settings-field"><label>Period End</label><input id="rebatePeriodEnd" type="date" name="period_end" value="<?= date('Y-m-t') ?>"></div>
                            <div class="settings-field"><label>Expected Amount</label><input id="rebateExpectedAmount" type="number" step="0.01" min="0" name="expected_amount" placeholder="0.00"></div>
                            <div class="settings-field"><label>Confirmed Amount</label><input id="rebateConfirmedAmount" type="number" step="0.01" min="0" name="confirmed_amount" placeholder="0.00"></div>
                            <div class="settings-field"><label>Received Amount</label><input id="rebateReceivedAmount" type="number" step="0.01" min="0" name="received_amount" placeholder="0.00"></div>
                            <div class="settings-field"><label>Reference</label><input id="rebateReference" type="text" name="reference" placeholder="Credit note / bank ref"></div>
                            <div class="settings-field"><label>Status</label><select id="rebateStatus" name="status"><option>Expected</option><option selected>Confirmed</option><option>Received</option><option>Disputed</option><option>Reversed</option></select></div>
                            <button type="submit" id="rebateSettlementSubmit" class="btn-complete-sale" style="height:42px;padding:0 1rem">Record Settlement</button>
                            <button type="button" id="rebateSettlementCancel" onclick="resetRebateSettlementForm()" style="display:none;height:42px;padding:0 1rem;border:1px solid #cbd5e1;background:#fff;border-radius:8px;font-weight:800;cursor:pointer">Cancel Edit</button>
                        </form>
                        <script>
                            (function () {
                                const supplierSelect = document.getElementById('rebateSupplier');
                                const skuSelect = document.getElementById('rebateSku');
                                const supplierProducts = <?= json_encode($rebateSupplierProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

                                if (!supplierSelect || !skuSelect) {
                                    return;
                                }

                                supplierSelect.addEventListener('change', function () {
                                    const supplier = supplierSelect.value;
                                    const products = supplierProducts[supplier] || [];
                                    skuSelect.replaceChildren(new Option('ALL', 'ALL'));
                                    products.forEach(function (product) {
                                        skuSelect.add(new Option(product.label, product.value));
                                    });
                                });
                            }());

                            function resetRebateSettlementForm() {
                                document.getElementById('rebateSettlementId').value = '';
                                document.getElementById('rebateSupplier').value = '';
                                document.getElementById('rebateSupplier').dispatchEvent(new Event('change'));
                                document.getElementById('rebateSku').value = 'ALL';
                                document.getElementById('rebatePeriodStart').value = '<?= date('Y-m-01') ?>';
                                document.getElementById('rebatePeriodEnd').value = '<?= date('Y-m-t') ?>';
                                document.getElementById('rebateExpectedAmount').value = '';
                                document.getElementById('rebateConfirmedAmount').value = '';
                                document.getElementById('rebateReceivedAmount').value = '';
                                document.getElementById('rebateReference').value = '';
                                document.getElementById('rebateStatus').value = 'Confirmed';
                                document.getElementById('rebateSettlementSubmit').textContent = 'Record Settlement';
                                document.getElementById('rebateSettlementCancel').style.display = 'none';
                            }

                            function editRebateSettlement(settlement) {
                                document.getElementById('rebateSettlementId').value = settlement.id || '';
                                document.getElementById('rebateSupplier').value = settlement.supplier || '';
                                document.getElementById('rebateSupplier').dispatchEvent(new Event('change'));
                                document.getElementById('rebateSku').value = settlement.sku || 'ALL';
                                document.getElementById('rebatePeriodStart').value = settlement.period_start || '';
                                document.getElementById('rebatePeriodEnd').value = settlement.period_end || '';
                                document.getElementById('rebateExpectedAmount').value = settlement.expected_amount || 0;
                                document.getElementById('rebateConfirmedAmount').value = settlement.confirmed_amount || 0;
                                document.getElementById('rebateReceivedAmount').value = settlement.received_amount || 0;
                                document.getElementById('rebateReference').value = settlement.reference || '';
                                document.getElementById('rebateStatus').value = settlement.status || 'Confirmed';
                                document.getElementById('rebateSettlementSubmit').textContent = 'Update Settlement';
                                document.getElementById('rebateSettlementCancel').style.display = 'inline-block';
                                document.getElementById('rebateSettlementId').scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        </script>
                    <?php endif; ?>
                    <?php if (!empty($rebateSettlements)): ?>
                        <div style="overflow:auto">
                            <table style="width:100%;border-collapse:collapse;font-size:0.82rem">
                                <thead><tr style="background:#f8fafc;text-align:left"><th style="padding:0.65rem">SKU</th><th style="padding:0.65rem">Period</th><th style="padding:0.65rem">Expected</th><th style="padding:0.65rem">Confirmed</th><th style="padding:0.65rem">Received</th><th style="padding:0.65rem">Status</th><th style="padding:0.65rem">Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($rebateSettlements, 0, 8) as $settlement): ?>
                                        <tr style="border-bottom:1px solid #f1f5f9">
                                            <td style="padding:0.65rem"><strong><?= e((string)($settlement['sku'] ?? 'ALL')) ?></strong><small style="display:block;color:#64748b"><?= e((string)($settlement['supplier'] ?? '')) ?></small></td>
                                            <td style="padding:0.65rem;color:#64748b"><?= e((string)($settlement['period_start'] ?? '')) ?> to <?= e((string)($settlement['period_end'] ?? '')) ?></td>
                                            <td style="padding:0.65rem">₦<?= number_format((float)($settlement['expected_amount'] ?? 0), 2) ?></td>
                                            <td style="padding:0.65rem">₦<?= number_format((float)($settlement['confirmed_amount'] ?? 0), 2) ?></td>
                                            <td style="padding:0.65rem">₦<?= number_format((float)($settlement['received_amount'] ?? 0), 2) ?></td>
                                            <td style="padding:0.65rem"><span style="background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:0.2rem 0.5rem;font-weight:800;font-size:0.72rem"><?= e((string)($settlement['status'] ?? 'Confirmed')) ?></span></td>
                                            <td style="padding:0.65rem;white-space:nowrap">
                                                <button type="button" onclick='editRebateSettlement(<?= htmlspecialchars(json_encode($settlement), ENT_QUOTES, "UTF-8") ?>)' style="border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:6px;padding:0.3rem 0.5rem;font-weight:800;cursor:pointer">Edit</button>
                                                <form method="post" action="<?= url('beverage_pos.php?tab=rebates') ?>" style="display:inline" onsubmit="return confirm('Delete this rebate settlement?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="delete_rebate_settlement">
                                                    <input type="hidden" name="settlement_id" value="<?= e((string)($settlement['id'] ?? '')) ?>">
                                                    <button type="submit" style="border:1px solid #fecaca;background:#fef2f2;color:#dc2626;border-radius:6px;padding:0.3rem 0.5rem;font-weight:800;cursor:pointer">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Rules Table Card -->
                <div class="pos-table-card" style="padding:1.25rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                        <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;color:#0f172a">Active Rebate Rules</h3>
                        <span style="font-size:0.8rem;background:#eff6ff;color:#1e40af;padding:0.3rem 0.6rem;border-radius:6px;border:1px solid #bfdbfe;font-weight:700"><?= count($rebateRules) ?> Rule(s) Configured</span>
                    </div>

                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">
                                <th style="padding:0.75rem">Hierarchy Level</th>
                                <th style="padding:0.75rem">Target Key / Scope</th>
                                <th style="padding:0.75rem">Rebate %</th>
                                <th style="padding:0.75rem">Description</th>
                                <th style="padding:0.75rem">Validity</th>
                                <th style="padding:0.75rem">Status</th>
                                <th style="padding:0.75rem;text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rebateRules as $r): ?>
                                <?php
                                    $lvl = strtolower((string)($r['level'] ?? 'global'));
                                    $lvlColors = [
                                        'promotion' => ['bg' => '#fef3c7', 'text' => '#b45309', 'label' => 'Promotion'],
                                        'product' => ['bg' => '#e0f2fe', 'text' => '#0369a1', 'label' => 'Product SKU'],
                                        'brand' => ['bg' => '#f3e8ff', 'text' => '#7e22ce', 'label' => 'Brand'],
                                        'supplier' => ['bg' => '#d1fae5', 'text' => '#047857', 'label' => 'Supplier'],
                                        'global' => ['bg' => '#f1f5f9', 'text' => '#334155', 'label' => 'Global Base'],
                                    ];
                                    $col = $lvlColors[$lvl] ?? $lvlColors['global'];
                                    $isActive = isset($r['is_active']) ? (bool)$r['is_active'] : true;
                                ?>
                                <tr style="border-bottom:1px solid #f1f5f9">
                                    <td style="padding:0.75rem">
                                        <span style="background:<?= $col['bg'] ?>;color:<?= $col['text'] ?>;padding:0.2rem 0.55rem;border-radius:4px;font-weight:800;font-size:0.72rem;text-transform:uppercase">
                                            <?= $col['label'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:0.75rem"><strong style="color:#0f172a"><?= e($r['target_key']) ?></strong></td>
                                    <td style="padding:0.75rem"><strong style="font-size:1.05rem;color:#2563eb"><?= (float)$r['rebate_pct'] ?>%</strong></td>
                                    <td style="padding:0.75rem;color:#475569;font-size:0.78rem"><?= e($r['description'] ?? '') ?></td>
                                    <td style="padding:0.75rem;font-size:0.74rem;color:#64748b">
                                        <?= e($r['start_date'] ?? 'Immediate') ?> to <?= e($r['end_date'] ?? 'Open') ?>
                                    </td>
                                    <td style="padding:0.75rem">
                                        <span style="background:<?= $isActive ? '#dcfce7' : '#fee2e2' ?>;color:<?= $isActive ? '#15803d' : '#b91c1c' ?>;padding:0.2rem 0.5rem;border-radius:999px;font-weight:800;font-size:0.72rem">
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td style="padding:0.75rem;text-align:right">
                                        <?php if (!empty($canManagePricing)): ?>
                                        <div style="display:inline-flex;gap:0.35rem">
                                            <button type="button" onclick="openEditRebateModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:0.35rem 0.65rem;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer">✏️ Edit</button>
                                            <?php if ($lvl !== 'global'): ?>
                                                <form method="post" action="<?= url('beverage_pos.php?tab=rebates') ?>" onsubmit="return confirm('Delete rebate rule?');" style="display:inline;margin:0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="delete_rebate_setting">
                                                    <input type="hidden" name="rule_id" value="<?= e($r['id']) ?>">
                                                    <button type="submit" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:0.35rem 0.65rem;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer">🗑️</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;font-size:0.75rem">View only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($activeTab === 'promotions'): ?>
            <?php
                $promotionRules = class_exists('App\Modules\Pos\PricingRebateService')
                    ? \App\Modules\Pos\PricingRebateService::getPromotionRules()
                    : [];
            ?>
            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a;display:flex;align-items:center;gap:0.5rem">🎯 Promotion Rules</h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.85rem">Configure supplier promotions, 5+1 schemes, fixed benefit allocations, percentage rebates, and channel eligibility.</p>
                    </div>
                    <span style="font-size:0.8rem;background:#eff6ff;color:#1e40af;padding:0.35rem 0.65rem;border-radius:999px;border:1px solid #bfdbfe;font-weight:800"><?= count($promotionRules) ?> rule(s)</span>
                </div>

                <?php if (!empty($canManagePricing)): ?>
                    <form method="post" action="<?= url('beverage_pos.php?tab=promotions') ?>" class="settings-form" style="margin-bottom:1.25rem">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="save_promotion_rule">
                        <h3 style="margin:0 0 0.9rem;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Add Promotion</h3>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem">
                            <div class="settings-field"><label>Promotion Name</label><input type="text" name="promotion_name" required placeholder="RGB 50CL Promo"></div>
                            <div class="settings-field"><label>Promotion Code</label><input type="text" name="promotion_code" placeholder="RGB-50CL-SEP"></div>
                            <div class="settings-field"><label>Supplier</label><input type="text" name="supplier" placeholder="Seven-Up"></div>
                            <div class="settings-field">
                                <label>Eligible SKU / Brand / ALL</label>
                                <select name="eligible_sku">
                                    <option value="ALL">ALL</option>
                                    <?php foreach ($catalog as $p): ?>
                                        <option value="<?= e((string)$p['sku']) ?>"><?= e((string)$p['name']) ?> - <?= e((string)$p['sku']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="settings-field"><label>Qualifying Quantity</label><input type="number" name="qualifying_qty" min="1" value="1"></div>
                            <div class="settings-field"><label>Reward Quantity</label><input type="number" name="reward_qty" min="0" value="0"></div>
                            <div class="settings-field"><label>Reward SKU</label><input type="text" name="reward_sku" placeholder="Optional different reward SKU"></div>
                            <div class="settings-field">
                                <label>Benefit Type</label>
                                <select name="benefit_type">
                                    <option value="amount">Fixed Amount</option>
                                    <option value="percent">Percentage</option>
                                    <option value="free_qty">Free Quantity / 5+1</option>
                                </select>
                            </div>
                            <div class="settings-field"><label>Benefit Value</label><input type="number" name="benefit_value" step="0.01" min="0" placeholder="120000 or 3"></div>
                            <div class="settings-field"><label>Adjustment Factor %</label><input type="number" name="adjustment_factor" step="0.001" min="0" max="100" value="100"></div>
                            <div class="settings-field">
                                <label>Allocation Method</label>
                                <select name="allocation_method">
                                    <option value="per_unit">Per Pack/Crate</option>
                                    <option value="invoice_total">Invoice Total</option>
                                    <option value="truck_load">Truck Load</option>
                                    <option value="pallet">Pallet</option>
                                </select>
                            </div>
                            <div class="settings-field">
                                <label>Sales Channel</label>
                                <select name="sales_channel">
                                    <option value="all">All</option>
                                    <option value="depot_sale">Depot Sale</option>
                                    <option value="diversion_sale">Diversion Sale</option>
                                </select>
                            </div>
                            <div class="settings-field"><label>Customer Class</label><input type="text" name="customer_class" placeholder="Optional"></div>
                            <div class="settings-field"><label>Start Date</label><input type="date" name="start_date" value="<?= date('Y-m-d') ?>"></div>
                            <div class="settings-field"><label>End Date</label><input type="date" name="end_date"></div>
                            <div class="settings-field">
                                <label>Approval Status</label>
                                <select name="approval_status">
                                    <option value="approved">Approved</option>
                                    <option value="draft">Draft</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <label class="settings-toggle" style="margin-top:0.9rem"><input type="checkbox" name="is_active" checked> Active promotion rule</label>
                        <button type="submit" class="btn-complete-sale" style="margin-top:0.9rem">Save Promotion Rule</button>
                    </form>
                <?php endif; ?>

                <div class="pos-table-card" style="padding:1.25rem">
                    <?php if (empty($promotionRules)): ?>
                        <div style="text-align:center;padding:2rem 1rem;color:#64748b">No promotion rules configured yet.</div>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;font-size:0.84rem">
                            <thead>
                                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">
                                    <th style="padding:0.7rem">Promotion</th>
                                    <th style="padding:0.7rem">Eligibility</th>
                                    <th style="padding:0.7rem">Benefit</th>
                                    <th style="padding:0.7rem">Channel</th>
                                    <th style="padding:0.7rem">Dates</th>
                                    <th style="padding:0.7rem">Status</th>
                                    <th style="padding:0.7rem;text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotionRules as $rule): ?>
                                    <tr style="border-bottom:1px solid #f1f5f9">
                                        <td style="padding:0.7rem"><strong><?= e((string)$rule['promotion_name']) ?></strong><small style="display:block;color:#64748b"><?= e((string)$rule['promotion_code']) ?></small></td>
                                        <td style="padding:0.7rem"><?= e((string)$rule['eligible_sku']) ?><small style="display:block;color:#64748b">Qty: <?= number_format((int)$rule['qualifying_qty']) ?>+</small></td>
                                        <td style="padding:0.7rem"><strong><?= e(ucwords(str_replace('_', ' ', (string)$rule['benefit_type']))) ?></strong><small style="display:block;color:#64748b"><?= number_format((float)$rule['benefit_value'], 2) ?> via <?= e((string)$rule['allocation_method']) ?></small></td>
                                        <td style="padding:0.7rem"><?= e(ucwords(str_replace('_', ' ', (string)$rule['sales_channel']))) ?></td>
                                        <td style="padding:0.7rem;color:#64748b"><?= e((string)$rule['start_date']) ?> to <?= e((string)($rule['end_date'] ?? 'Open')) ?></td>
                                        <td style="padding:0.7rem"><span style="background:<?= !empty($rule['is_active']) && ($rule['approval_status'] ?? '') === 'approved' ? '#dcfce7' : '#fee2e2' ?>;color:<?= !empty($rule['is_active']) && ($rule['approval_status'] ?? '') === 'approved' ? '#15803d' : '#b91c1c' ?>;padding:0.2rem 0.5rem;border-radius:999px;font-weight:800;font-size:0.72rem"><?= e((string)$rule['approval_status']) ?></span></td>
                                        <td style="padding:0.7rem;text-align:right">
                                            <?php if (!empty($canManagePricing)): ?>
                                                <form method="post" action="<?= url('beverage_pos.php?tab=promotions') ?>" onsubmit="return confirm('Delete this promotion rule?')" style="display:inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="delete_promotion_rule">
                                                    <input type="hidden" name="promotion_id" value="<?= e((string)$rule['id']) ?>">
                                                    <button type="submit" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:0.35rem 0.65rem;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

        <?php elseif ($activeTab === 'pricing-audit'): ?>
            <?php
                $auditSku = strtoupper(trim((string)($_GET['audit_sku'] ?? '')));
                $auditLogs = class_exists('App\Modules\Pos\PricingRebateService')
                    ? \App\Modules\Pos\PricingRebateService::getAuditLogs($auditSku)
                    : [];
            ?>
            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a;display:flex;align-items:center;gap:0.5rem">
                            📜 Pricing &amp; Rebate Audit Trail
                        </h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.85rem">
                            Immutable audit trail tracking all modifications to cost prices, selling tiers, quantity ranges, rebate percentages, and effective dates.
                        </p>
                    </div>
                    <form method="get" action="<?= url('beverage_pos.php') ?>" style="display:flex;gap:0.4rem;align-items:center">
                        <input type="hidden" name="tab" value="pricing-audit">
                        <input type="text" name="audit_sku" value="<?= e($auditSku) ?>" placeholder="Filter by SKU or Entity..." style="padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem">
                        <button type="submit" style="background:#0284c7;color:#fff;border:none;border-radius:6px;padding:0.55rem 0.85rem;font-weight:700;font-size:0.82rem;cursor:pointer">Filter</button>
                        <?php if ($auditSku !== ''): ?>
                            <a href="<?= url('beverage_pos.php?tab=pricing-audit') ?>" style="color:#64748b;font-size:0.8rem;text-decoration:none;padding:0.55rem">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="transfer-summary">
                    <div class="transfer-card"><small>Total Audit Events</small><strong><?= number_format(count($auditLogs)) ?></strong></div>
                    <div class="transfer-card"><small>Filtered SKU</small><strong><?= $auditSku !== '' ? e($auditSku) : 'All' ?></strong></div>
                    <div class="transfer-card"><small>Tracked Changes</small><strong>Prices, Rebates, Stock</strong></div>
                </div>

                <div class="pos-table-card" style="padding:1.25rem">
                    <?php if (empty($auditLogs)): ?>
                        <div style="text-align:center;padding:2.5rem 1rem;color:#64748b">
                            <span style="font-size:2rem;display:block;margin-bottom:0.4rem">📋</span>
                            <strong style="display:block;color:#0f172a;font-family:'Outfit',sans-serif;margin-bottom:0.35rem">No pricing or rebate audit events recorded yet</strong>
                            <span style="display:block;font-size:0.84rem;line-height:1.55">When cost prices, selling tiers, quantity ranges, rebates, or effective dates change, the permanent audit trail will appear here.</span>
                        </div>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
                            <thead>
                                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">
                                    <th style="padding:0.75rem">Timestamp</th>
                                    <th style="padding:0.75rem">Authorized User</th>
                                    <th style="padding:0.75rem">Action / Event</th>
                                    <th style="padding:0.75rem">Entity Scope</th>
                                    <th style="padding:0.75rem">Old Value</th>
                                    <th style="padding:0.75rem">New Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr style="border-bottom:1px solid #f1f5f9">
                                        <td style="padding:0.75rem;font-size:0.78rem;color:#64748b;white-space:nowrap"><?= e($log['timestamp']) ?></td>
                                        <td style="padding:0.75rem"><strong style="color:#0f172a"><?= e($log['user'] ?? 'Admin') ?></strong></td>
                                        <td style="padding:0.75rem">
                                            <span style="background:#eff6ff;color:#1e40af;padding:0.2rem 0.5rem;border-radius:4px;font-weight:700;font-size:0.72rem">
                                                <?= e($log['action']) ?>
                                            </span>
                                        </td>
                                        <td style="padding:0.75rem"><code style="background:#f1f5f9;padding:0.2rem 0.4rem;border-radius:4px"><?= e($log['entity_id']) ?></code></td>
                                        <td style="padding:0.75rem;font-size:0.75rem;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis">
                                            <?= !empty($log['old_value']) ? e(json_encode($log['old_value'])) : '<span style="color:#94a3b8">None</span>' ?>
                                        </td>
                                        <td style="padding:0.75rem;font-size:0.75rem;color:#059669;max-width:240px;overflow:hidden;text-overflow:ellipsis">
                                            <?= !empty($log['new_value']) ? e(json_encode($log['new_value'])) : '<span style="color:#94a3b8">Deleted</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($activeTab === 'recon'): ?>
            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a">Cash Drawer</h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.85rem">Open the cashier drawer, count cash at close, and record variance against the staff identity.</p>
                    </div>
                    <a href="<?= url('beverage_pos.php?tab=sales-account') ?>" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;text-decoration:none;border-radius:8px;padding:0.6rem 0.85rem;font-size:0.78rem;font-weight:900">Sales Account</a>
                </div>

                <div class="transfer-summary">
                    <div class="transfer-card"><small>Opening Cash</small><strong>₦<?= number_format((float)($shiftSummary['opening_float'] ?? 0), 2) ?></strong></div>
                    <div class="transfer-card"><small>Cash Sales</small><strong>₦<?= number_format((float)($shiftSummary['total_cash'] ?? 0), 2) ?></strong></div>
                    <div class="transfer-card"><small>Expected Cash</small><strong>₦<?= number_format((float)($shiftSummary['expected_closing_cash'] ?? 0), 2) ?></strong></div>
                    <div class="transfer-card"><small>Transactions</small><strong><?= number_format((int)($shiftSummary['total_transactions'] ?? 0)) ?></strong></div>
                </div>

                <div class="settings-grid">
                    <form method="post" action="<?= url('beverage_pos.php?tab=recon') ?>" class="settings-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="open_cash_drawer">
                        <input type="hidden" name="terminal_name" value="<?= e((string)($posSettings['terminal_name'] ?? 'Main POS')) ?>">
                        <h3 style="margin:0 0 0.75rem;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Open Cash Drawer</h3>
                        <div style="display:grid;gap:0.45rem;margin-bottom:1rem;color:#475569;font-size:0.82rem">
                            <span><strong>Cashier:</strong> <?= e((string)($currentEmployee['name'] ?? $user['name'] ?? 'POS Cashier')) ?></span>
                            <span><strong>Employee ID:</strong> <?= e((string)($currentEmployeeCode ?? '')) ?></span>
                            <span><strong>Terminal:</strong> <?= e((string)($posSettings['terminal_name'] ?? 'Main POS')) ?></span>
                            <span><strong>Status:</strong> <?= !empty($currentCashierSession) ? '<span style="color:#047857;font-weight:900">Open</span>' : '<span style="color:#9a3412;font-weight:900">Not Open</span>' ?></span>
                        </div>
                        <div class="settings-field">
                            <label>Opening Cash Float</label>
                            <input type="number" step="0.01" min="0" name="opening_cash" value="<?= e((string)($currentCashierSession['opening_cash'] ?? '0.00')) ?>" <?= !empty($currentCashierSession) ? 'readonly' : '' ?>>
                        </div>
                        <button type="submit" class="btn-complete-sale" style="margin-top:0.5rem" <?= !empty($currentCashierSession) ? 'disabled' : '' ?>>Open Drawer</button>
                    </form>

                    <form method="post" action="<?= url('beverage_pos.php?tab=recon') ?>" class="settings-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="reconcile_shift">
                        <input type="hidden" name="terminal_name" value="<?= e((string)($posSettings['terminal_name'] ?? 'Main POS')) ?>">
                        <input type="hidden" name="opening_cash" value="<?= e((string)($shiftSummary['opening_float'] ?? 0)) ?>">
                        <h3 style="margin:0 0 0.75rem;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Close Cash Drawer</h3>
                        <div style="display:grid;gap:0.55rem;margin-bottom:1rem">
                            <div style="display:flex;justify-content:space-between;gap:1rem;color:#475569;font-size:0.85rem"><span>Opening Cash</span><strong>₦<?= number_format((float)($shiftSummary['opening_float'] ?? 0), 2) ?></strong></div>
                            <div style="display:flex;justify-content:space-between;gap:1rem;color:#475569;font-size:0.85rem"><span>Cash Sales</span><strong>₦<?= number_format((float)($shiftSummary['total_cash'] ?? 0), 2) ?></strong></div>
                            <div style="display:flex;justify-content:space-between;gap:1rem;color:#0f172a;font-size:0.95rem"><span>Expected Cash</span><strong>₦<?= number_format((float)($shiftSummary['expected_closing_cash'] ?? 0), 2) ?></strong></div>
                        </div>
                        <div class="settings-field">
                            <label>Physical Cash Count</label>
                            <input type="number" step="0.01" min="0" name="physical_cash" required placeholder="0.00">
                        </div>
                        <div class="settings-field">
                            <label>Variance Reason</label>
                            <textarea name="variance_reason" placeholder="Required when there is a shortage or surplus"></textarea>
                        </div>
                        <button type="submit" class="btn-complete-sale" style="margin-top:0.5rem">Close Drawer / Save Variance</button>
                    </form>
                </div>

                <div class="transfer-panel" style="margin-top:1rem">
                    <h3 style="margin:0 0 1rem 0;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Recent Cash Drawer Sessions</h3>
                    <?php if (empty($cashierSessions)): ?>
                        <div style="border:1px dashed #cbd5e1;border-radius:10px;padding:1rem;background:#f8fafc;color:#64748b">No cash drawer sessions recorded yet.</div>
                    <?php else: ?>
                        <table class="transfer-table">
                            <thead><tr><th>Drawer</th><th>Cashier</th><th>Opening</th><th>Cash Sales</th><th>Counted</th><th>Variance</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($cashierSessions, 0, 10) as $drawer): ?>
                                    <?php $variance = (float)($drawer['variance'] ?? 0); ?>
                                    <tr>
                                        <td><strong><?= e((string)($drawer['drawer_id'] ?? '')) ?></strong><small style="display:block;color:#64748b"><?= e((string)($drawer['opened_at'] ?? '')) ?></small></td>
                                        <td><?= e((string)($drawer['employee_name'] ?? $drawer['employee_code'] ?? '')) ?></td>
                                        <td>₦<?= number_format((float)($drawer['opening_cash'] ?? 0), 2) ?></td>
                                        <td>₦<?= number_format((float)($drawer['cash_sales'] ?? 0), 2) ?></td>
                                        <td>₦<?= number_format((float)($drawer['counted_cash'] ?? 0), 2) ?></td>
                                        <td style="font-weight:900;color:<?= $variance < 0 ? '#dc2626' : ($variance > 0 ? '#047857' : '#334155') ?>"><?= $variance >= 0 ? '+₦' : '-₦' ?><?= number_format(abs($variance), 2) ?></td>
                                        <td><span class="transfer-status <?= (string)($drawer['status'] ?? '') === 'Open' ? 'confirmed' : '' ?>"><?= e((string)($drawer['status'] ?? '')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($activeTab === 'settings'): ?>
            <section class="transfer-screen">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem">
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;color:#0f172a">POS Settings</h2>
                        <p style="margin:0.25rem 0 0;color:#64748b;font-size:0.85rem">Terminal, receipt, and crate deposit setup for beverage checkout.</p>
                    </div>
                </div>

                <div class="settings-grid">
                    <form method="post" action="<?= url('beverage_pos.php?tab=settings') ?>" class="settings-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="save_pos_settings">

                        <div class="settings-field">
                            <label>Terminal Name</label>
                            <input type="text" name="terminal_name" value="<?= e($posSettings['terminal_name'] ?? 'Main POS') ?>">
                        </div>
                        <div class="settings-field">
                            <label>Receipt Business Name</label>
                            <input type="text" name="receipt_business_name" value="<?= e($posSettings['receipt_business_name'] ?? 'EMPRESS TEE BEVERAGE DEPOT') ?>">
                        </div>
                        <div class="settings-field">
                            <label>Receipt Footer Note</label>
                            <textarea name="receipt_footer_note"><?= e($posSettings['receipt_footer_note'] ?? 'Thank you for your beverage purchase.') ?></textarea>
                        </div>
                        <label class="settings-toggle">
                            <input type="checkbox" name="crate_deposit_enabled" <?= (($posSettings['crate_deposit_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                            Add refundable crate deposit
                        </label>
                        <button type="submit" class="btn-complete-sale" style="margin-top:0.5rem">Save POS Settings</button>
                    </form>

                    <div class="settings-form">
                        <h3 style="margin:0 0 0.75rem;font-family:'Outfit',sans-serif;color:#0f172a;font-size:1rem">Pricing Access</h3>
                        <p style="margin:0 0 1rem;color:#64748b;font-size:0.85rem">Only Super Admin, Admin, and Pricing Manager can edit price tiers or rebate rules.</p>
                        <div style="display:grid;gap:0.6rem;font-size:0.85rem;color:#334155">
                            <div><strong>Your role:</strong> <?= e((string)($user['role'] ?? 'staff')) ?></div>
                            <div><strong>Pricing permission:</strong> <?= !empty($canManagePricing) ? 'Allowed' : 'View only' ?></div>
                            <div><strong>Configured rebate rules:</strong> <?= class_exists('App\Modules\Pos\PricingRebateService') ? count(\App\Modules\Pos\PricingRebateService::getRebateSettings()) : 0 ?></div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <nav class="pos-mobile-bottom-nav" aria-label="POS mobile navigation">
        <a href="<?= url('beverage_pos.php?tab=pos') ?>" class="<?= $activeTab === 'pos' ? 'active' : '' ?>">
            <span class="mobile-nav-icon">🛒</span>
            <span>Sell</span>
        </a>
        <a href="<?= url('beverage_pos.php?tab=customers') ?>" class="<?= $activeTab === 'customers' ? 'active' : '' ?>">
            <span class="mobile-nav-icon">👥</span>
            <span>Customers</span>
        </a>
        <a href="<?= url('beverage_pos.php?tab=history') ?>" class="<?= $activeTab === 'history' ? 'active' : '' ?>">
            <span class="mobile-nav-icon">📄</span>
            <span>History</span>
        </a>
        <a href="<?= url('beverage_pos.php?tab=recon') ?>" class="<?= $activeTab === 'recon' ? 'active' : '' ?>">
            <span class="mobile-nav-icon">◷</span>
            <span>Cash</span>
        </a>
        <button type="button" id="posMobileMoreToggle" aria-label="Open more POS menu">
            <span class="mobile-nav-icon">⋯</span>
            <span>More</span>
        </button>
    </nav>

    <!-- 7. Bottom System Status Bar -->
    <footer class="pos-bottom-status">
        <div class="status-item">
            <span class="status-dot"></span>
            <span>SYSTEM STATUS: Online</span>
        </div>
        <div class="status-item">
            <span>LAST SYNC: 🔄 2 mins ago</span>
        </div>
        <div class="status-item">
            <span>TERMINAL: 🖥️ Glass Crate POS</span>
        </div>
        <div class="status-item">
            <span>DATE &amp; TIME: 📅 <?= date('M d, Y • h:i A') ?></span>
        </div>
        <div class="status-item">
            <span>VERSION: v2.3.1</span>
        </div>
    </footer>
</div>

<!-- Dual Receipt Modal -->
<?php if ($printedReceipt): ?>
<?php
$receiptItems = is_array($printedReceipt['items'] ?? null) ? $printedReceipt['items'] : [];
$receiptSubtotal = (float)($printedReceipt['subtotal'] ?? 0);
$receiptDiscount = (float)($printedReceipt['discount'] ?? 0);
$receiptCrateDeposit = (float)($printedReceipt['crate_deposit'] ?? 0);
$receiptGrandTotal = (float)($printedReceipt['grand_total'] ?? 0);
$receiptAmountPaid = (float)($printedReceipt['amount_paid'] ?? $receiptGrandTotal);
$receiptChangeDue = (float)($printedReceipt['change_due'] ?? max(0, $receiptAmountPaid - $receiptGrandTotal));
$receiptTotalQty = array_reduce($receiptItems, static function ($carry, $item) {
    return $carry + (float)($item['qty'] ?? $item['quantity'] ?? 0);
}, 0.0);
$receiptCopies = [
    [
        'class' => 'customer-copy',
        'label' => 'Customer Copy',
        'note' => 'Goods received in good condition. Keep this receipt for crate deposit, exchange, or account reconciliation.',
        'show_signatures' => false,
    ],
    [
        'class' => 'office-copy',
        'label' => 'Office Copy',
        'note' => 'Retain this copy for end-of-day cashup, stock deduction, and accounts posting.',
        'show_signatures' => true,
    ],
];
?>
<div class="receipt-modal" id="receiptModal">
    <div class="receipt-modal-panel">
        <div class="receipt-modal-toolbar">
            <div>
                <strong>Sale Receipt</strong>
                <span><?= e($printedReceipt['receipt_no'] ?? '') ?></span>
            </div>
            <div class="receipt-toolbar-actions">
                <button type="button" class="receipt-print-btn" onclick="window.print();">Print Both Copies</button>
                <button type="button" class="receipt-close-btn" onclick="document.getElementById('receiptModal').remove()">Close</button>
            </div>
        </div>

        <div class="receipt-sheets">
            <?php foreach ($receiptCopies as $copy): ?>
                <section class="receipt-paper <?= e((string)$copy['class']) ?>">
                    <div class="receipt-copy-label"><?= e((string)$copy['label']) ?></div>

                    <div class="receipt-header">
                        <div class="receipt-brand">
                            <div class="receipt-logo">
                                <img src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="Empress Tee Beverage Depot">
                            </div>
                            <div>
                                <h3><?= e($posSettings['receipt_business_name'] ?? 'EMPRESS TEE BEVERAGE DEPOT') ?></h3>
                                <p>Beverage Wholesale POS Receipt</p>
                                <div class="receipt-business-lines">
                                    <span>Jacroxx Warehouse Main Depot</span>
                                    <span>Official sales and stock-control document</span>
                                </div>
                            </div>
                        </div>
                        <div class="receipt-meta">
                            <strong><?= e($printedReceipt['receipt_no'] ?? '') ?></strong>
                            <span><?= e($printedReceipt['timestamp'] ?? date('Y-m-d H:i:s')) ?></span>
                        </div>
                    </div>

                    <div class="receipt-summary-strip">
                        <div><span>Items</span><strong><?= number_format(count($receiptItems)) ?></strong></div>
                        <div><span>Total Qty</span><strong><?= number_format($receiptTotalQty, $receiptTotalQty == floor($receiptTotalQty) ? 0 : 2) ?></strong></div>
                        <div><span>Branch</span><strong><?= e($printedReceipt['branch'] ?? 'Jacroxx Warehouse') ?></strong></div>
                    </div>

                    <div class="receipt-info-grid">
                        <div class="receipt-info-cell">
                            <span>Customer</span>
                            <strong><?= e($printedReceipt['customer_name'] ?? 'Walk-in Customer') ?></strong>
                            <?php if (!empty($printedReceipt['customer_phone'])): ?>
                                <small><?= e($printedReceipt['customer_phone']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="receipt-info-cell">
                            <span>Cashier</span>
                            <strong><?= e($printedReceipt['cashier_name'] ?? 'Cashier') ?></strong>
                        </div>
                        <div class="receipt-info-cell">
                            <span>Payment</span>
                            <strong><?= e($printedReceipt['payment_method'] ?? 'Cash') ?></strong>
                        </div>
                        <div class="receipt-info-cell">
                            <span>Terminal</span>
                            <strong><?= e($printedReceipt['pos_terminal'] ?? 'Main POS') ?></strong>
                        </div>
                    </div>

                    <table class="receipt-items">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Rate</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receiptItems as $item): ?>
                                <?php
                                $qty = (float)($item['qty'] ?? 0);
                                $total = (float)($item['total'] ?? 0);
                                $rate = isset($item['actual_selling_price']) ? (float)$item['actual_selling_price'] : (isset($item['price_per_unit']) ? (float)$item['price_per_unit'] : ($qty > 0 ? $total / $qty : 0));
                                $tierName = $item['applied_tier_name'] ?? $item['applied_tier'] ?? '';
                                $rebatePct = isset($item['rebate_pct']) ? (float)$item['rebate_pct'] : null;
                                $packSize = $item['pack_size'] ?? $item['pack_crate_size'] ?? $item['total_per_pack'] ?? '';
                                $costPrice = (float)($item['cost_price'] ?? 0);
                                $effectiveCost = (float)($item['effective_cost'] ?? 0);
                                $trueProfit = (float)($item['total_true_profit'] ?? $item['true_profit_loss_after_rebate'] ?? 0);
                                $metaBits = [];
                                if (!empty($item['sku'])) {
                                    $metaBits[] = 'SKU: ' . e((string)$item['sku']);
                                }
                                if (!empty($packSize)) {
                                    $metaBits[] = 'Pack: ' . e((string)$packSize);
                                }
                                if (!empty($tierName)) {
                                    $metaBits[] = 'Tier: ' . e((string)$tierName);
                                }
                                if ($copy['show_signatures']) {
                                    if ($rebatePct !== null) {
                                        $metaBits[] = $rebatePct . '% Rebate';
                                    }
                                    if ($costPrice > 0) {
                                        $metaBits[] = 'Cost: ₦' . number_format($costPrice, 2);
                                    }
                                    if ($effectiveCost > 0) {
                                        $metaBits[] = 'Effective: ₦' . number_format($effectiveCost, 2);
                                    }
                                    $metaBits[] = 'True Profit: ₦' . number_format($trueProfit, 2);
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['name'] ?? $item['product_name'] ?? 'Beverage Item') ?></strong>
                                        <?php if (!empty($metaBits)): ?>
                                            <span><?= implode(' &bull; ', $metaBits) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($qty, $qty == floor($qty) ? 0 : 2) ?></td>
                                    <td>₦<?= number_format($rate, 2) ?></td>
                                    <td>₦<?= number_format($total, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($receiptItems)): ?>
                                <tr><td colspan="4" style="text-align:center;color:#64748b">No item snapshot found for this receipt.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="receipt-totals">
                        <div class="receipt-total-row">
                            <span>Subtotal</span>
                            <strong>₦<?= number_format($receiptSubtotal, 2) ?></strong>
                        </div>
                        <?php if ($receiptDiscount > 0): ?>
                            <div class="receipt-total-row">
                                <span>Discount</span>
                                <strong>-₦<?= number_format($receiptDiscount, 2) ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($receiptCrateDeposit > 0): ?>
                            <div class="receipt-total-row">
                                <span>Crate Deposit</span>
                                <strong>₦<?= number_format($receiptCrateDeposit, 2) ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="receipt-total-row">
                            <span>Amount Paid</span>
                            <strong>₦<?= number_format($receiptAmountPaid, 2) ?></strong>
                        </div>
                        <div class="receipt-total-row">
                            <span>Change Due</span>
                            <strong>₦<?= number_format($receiptChangeDue, 2) ?></strong>
                        </div>
                        <div class="receipt-total-row grand">
                            <span>Grand Total</span>
                            <strong>₦<?= number_format($receiptGrandTotal, 2) ?></strong>
                        </div>
                    </div>

                    <?php if ($copy['show_signatures']): ?>
                        <div class="receipt-office-panel">
                            <h4>Office Control Snapshot</h4>
                            <div class="receipt-office-grid">
                                <div><span>Total Cost</span><strong>₦<?= number_format((float)($printedReceipt['total_cost_price'] ?? 0), 2) ?></strong></div>
                                <div><span>Total Rebate</span><strong>₦<?= number_format((float)($printedReceipt['total_rebate_amount'] ?? 0), 2) ?></strong></div>
                                <div><span>Effective Cost</span><strong>₦<?= number_format((float)($printedReceipt['total_effective_cost'] ?? 0), 2) ?></strong></div>
                                <div><span>True Profit</span><strong>₦<?= number_format((float)($printedReceipt['total_true_profit_after_rebate'] ?? 0), 2) ?></strong></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($copy['show_signatures']): ?>
                        <div class="receipt-signatures">
                            <div class="receipt-signature-line">Cashier Signature</div>
                            <div class="receipt-signature-line">Customer / Receiver Signature</div>
                            <div class="receipt-signature-line">Supervisor Review</div>
                            <div class="receipt-signature-line">Accounts Posting</div>
                        </div>
                    <?php endif; ?>

                    <div class="receipt-footer">
                        <strong><?= $copy['show_signatures'] ? 'Office Record' : 'Thank you for your beverage purchase' ?></strong>
                        <span><?= e($posSettings['receipt_footer_note'] ?? (string)$copy['note']) ?></span>
                        <div class="receipt-verification">
                            <span>Printed by 360Management Beverage POS</span>
                            <span class="receipt-code-box"><?= e(substr((string)($printedReceipt['receipt_no'] ?? 'RECEIPT'), -10)) ?></span>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Add / Edit Pricing Tier -->
<div id="pricingTierModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:1rem">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:520px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;animation:fadeIn 0.2s ease-out">
        <div style="background:#0f172a;color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between">
            <h3 id="pricingTierModalTitle" style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;display:flex;align-items:center;gap:0.5rem">
                🏷️ <span id="pricingTierModalTitleText">Add New Pricing Tier</span>
            </h3>
            <button type="button" onclick="closePricingTierModal()" style="background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1">✕</button>
        </div>
        <form method="post" action="<?= url('beverage_pos.php?tab=pricing-tiers') ?>" id="pricingTierForm" style="padding:1.5rem">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save_pricing_tier">
            <input type="hidden" name="tier_id" id="modal_tier_id" value="">
            <input type="hidden" name="sku" id="modal_tier_sku" value="">

            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Product SKU &amp; Name</label>
                <input type="text" id="modal_tier_product_display" readonly style="width:100%;padding:0.6rem 0.85rem;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700;color:#0f172a">
            </div>

            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Tier Level Name (e.g. 1–4 Retail, 5–19 Wholesale, 20–49 Bulk, 50+ Pallet)</label>
                <input type="text" name="tier_name" id="modal_tier_name" required placeholder="e.g. 1–4 Retail or 50+ Mega Deal" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem">
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Sales Type / Channel</label>
                    <select name="sales_channel" id="modal_sales_channel" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700">
                        <option value="all">Global / Any Sale</option>
                        <option value="depot_sale">Depot Sale</option>
                        <option value="diversion_sale">Diversion Sale</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Minimum Selling Price (₦)</label>
                    <input type="number" name="min_selling_price" id="modal_min_selling_price" step="0.01" min="0" placeholder="Optional threshold" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.5rem">
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Minimum Quantity</label>
                    <input type="number" name="min_qty" id="modal_min_qty" min="1" required value="1" oninput="validateTierRangeLive()" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700">
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Maximum Quantity (Empty for +)</label>
                    <input type="number" name="max_qty" id="modal_max_qty" min="1" placeholder="Leave empty for +" oninput="validateTierRangeLive()" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700">
                </div>
            </div>

            <!-- Overlap status message box -->
            <div id="tierOverlapFeedback" style="display:none;padding:0.5rem 0.75rem;border-radius:6px;font-size:0.75rem;margin-bottom:1rem"></div>

            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Selling Price / Pack or Crate (₦)</label>
                <input type="number" name="selling_price" id="modal_tier_price" step="0.01" min="0" required placeholder="₦5,000.00" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:1rem;font-weight:800;color:#0f172a">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem">
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Effective Start Date</label>
                    <input type="date" name="start_date" id="modal_tier_start_date" value="<?= date('Y-m-d') ?>" style="width:100%;padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem">
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">End Date (Optional)</label>
                    <input type="date" name="end_date" id="modal_tier_end_date" style="width:100%;padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem">
                </div>
            </div>

            <div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:0.5rem">
                <input type="checkbox" name="is_active" id="modal_tier_active" value="1" checked style="width:16px;height:16px">
                <label for="modal_tier_active" style="font-size:0.82rem;font-weight:700;color:#334155">Active Tier (Available immediately in POS)</label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:0.75rem">
                <button type="button" onclick="closePricingTierModal()" style="padding:0.6rem 1rem;border:1px solid #cbd5e1;background:#fff;border-radius:8px;font-weight:700;font-size:0.82rem;cursor:pointer">Cancel</button>
                <button type="submit" id="btnSaveTierSubmit" style="padding:0.6rem 1.25rem;background:#0284c7;color:#fff;border:none;border-radius:8px;font-weight:800;font-size:0.85rem;cursor:pointer">Save Pricing Tier</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add / Edit Rebate Rule -->
<div id="rebateRuleModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:1rem">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:520px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;animation:fadeIn 0.2s ease-out">
        <div style="background:#0f172a;color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;display:flex;align-items:center;gap:0.5rem">
                💰 <span id="rebateModalTitleText">Add Rebate Rule</span>
            </h3>
            <button type="button" onclick="closeRebateModal()" style="background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1">✕</button>
        </div>
        <form method="post" action="<?= url('beverage_pos.php?tab=rebates') ?>" style="padding:1.5rem">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save_rebate_setting">
            <input type="hidden" name="id" id="modal_rebate_id" value="">

            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Hierarchy Scope Level</label>
                <select name="level" id="modal_rebate_level" onchange="onRebateLevelChange()" required style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700">
                    <option value="global">5. Global Baseline (Entire Depot Fallback)</option>
                    <option value="supplier">4. Supplier Contract (e.g. NBC, Nigerian Breweries)</option>
                    <option value="brand">3. Brand Level (e.g. Coca-Cola, 7UP, Maltina)</option>
                    <option value="product">2. Product SKU Level (Specific Item)</option>
                    <option value="promotion">1. Promotion / Campaign Code (Highest Priority)</option>
                </select>
            </div>

            <div style="margin-bottom:1rem">
                <label for="modal_rebate_target" style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Target Key / Identifier (e.g. supplier, brand, SKU, or promo code)</label>
                <input type="text" name="target_key" id="modal_rebate_target" required placeholder="e.g. ALL, COCA-COLA, MEGAPROMO" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700;text-transform:uppercase">
                <select id="modal_rebate_target_select" aria-label="Select rebate target" style="display:none;width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;font-weight:700"></select>
            </div>

            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Rebate Percentage (%)</label>
                <input type="number" name="rebate_pct" id="modal_rebate_pct" step="0.1" min="0" max="100" required placeholder="e.g. 7.5" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:1rem;font-weight:800;color:#2563eb">
            </div>

            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Description / Note</label>
                <input type="text" name="description" id="modal_rebate_desc" placeholder="e.g. NBC Quarterly Volume Rebate" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;margin-bottom:1rem">
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Rebate Base Price (₦) <small style="font-weight:600;color:#64748b;text-transform:none">optional</small></label>
                    <input type="number" name="rebate_base_price" id="modal_rebate_base_price" step="0.01" min="0" placeholder="Leave blank if unavailable" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem">
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Adjustment Factor %</label>
                    <input type="number" name="adjustment_factor" id="modal_rebate_adjustment_factor" step="0.001" min="0" max="100" value="100" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem">
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Formula Type</label>
                    <select name="formula_type" id="modal_rebate_formula_type" style="width:100%;padding:0.6rem 0.85rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem">
                        <option value="standard_pct">Standard percentage</option>
                        <option value="rebate_base_adjusted">Rebate base adjusted</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem">
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">Start Date</label>
                    <input type="date" name="start_date" id="modal_rebate_start" value="<?= date('Y-m-d') ?>" style="width:100%;padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem">
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:800;color:#334155;margin-bottom:0.35rem">End Date (Optional)</label>
                    <input type="date" name="end_date" id="modal_rebate_end" style="width:100%;padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem">
                </div>
            </div>

            <div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:0.5rem">
                <input type="checkbox" name="is_active" id="modal_rebate_active" value="1" checked style="width:16px;height:16px">
                <label for="modal_rebate_active" style="font-size:0.82rem;font-weight:700;color:#334155">Active Rebate Rule</label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:0.75rem">
                <button type="button" onclick="closeRebateModal()" style="padding:0.6rem 1rem;border:1px solid #cbd5e1;background:#fff;border-radius:8px;font-weight:700;font-size:0.82rem;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:0.6rem 1.25rem;background:#16a34a;color:#fff;border:none;border-radius:8px;font-weight:800;font-size:0.85rem;cursor:pointer">Save Rebate Rule</button>
            </div>
        </form>
    </div>
</div>

<script>
let bevCart = [];

function resolveProductImageSrc(img) {
    if (!img) return '';
    img = String(img).trim();
    if (img.startsWith('http://') || img.startsWith('https://') || img.startsWith('data:') || img.startsWith('/')) {
        return img;
    }
    return '<?= url('') ?>/' + img.replace(/^\/+/, '');
}

function escapeReceiptHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function moneyReceipt(value) {
    return '₦' + Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function openReprintReceipt(transaction) {
    const existing = document.getElementById('receiptModal');
    if (existing) existing.remove();

    const receiptNo = escapeReceiptHtml(transaction.receipt_no || '');
    const receiptItems = Array.isArray(transaction.items) ? transaction.items : [];
    const subtotal = Number(transaction.subtotal || 0);
    const discount = Number(transaction.discount || 0);
    const crateDeposit = Number(transaction.crate_deposit || transaction.crate_deposit_total || 0);
    const grandTotal = Number(transaction.grand_total || 0);
    const amountPaid = Number(transaction.amount_paid || grandTotal);
    const changeDue = Number(transaction.change_due || Math.max(0, amountPaid - grandTotal));
    const customerName = escapeReceiptHtml(transaction.customer_name || 'Walk-in Customer');
    const customerPhone = escapeReceiptHtml(transaction.customer_phone || '');
    const cashierName = escapeReceiptHtml(transaction.cashier_name || 'Cashier');
    const paymentMethod = escapeReceiptHtml(transaction.payment_method || 'Cash');
    const timestamp = escapeReceiptHtml(transaction.timestamp || transaction.created_at || '');
    const branchName = escapeReceiptHtml(transaction.branch || 'Jacroxx Warehouse');
    const terminalName = escapeReceiptHtml(transaction.pos_terminal || 'Main POS');
    const logoUrl = '<?= e(url('assets/images/empress_tee_logo.svg')) ?>';
    const receiptBusinessName = escapeReceiptHtml(<?= json_encode((string)($posSettings['receipt_business_name'] ?? 'EMPRESS TEE BEVERAGE DEPOT')) ?>);
    const configuredFooterNote = escapeReceiptHtml(<?= json_encode((string)($posSettings['receipt_footer_note'] ?? '')) ?>);
    const totalQty = receiptItems.reduce((sum, item) => sum + Number(item.qty || item.quantity || 0), 0);

    const itemsRows = (showOffice) => receiptItems.length > 0
        ? receiptItems.map((item) => {
            const qty = Number(item.qty || item.quantity || 0);
            const total = Number(item.total || item.line_total || 0);
            const rate = Number(item.actual_selling_price || item.price_per_unit || (qty > 0 ? total / qty : 0));
            const tierName = item.applied_tier_name || item.applied_tier || '';
            const rebatePct = item.rebate_pct !== undefined && item.rebate_pct !== null ? `${item.rebate_pct}% Rebate` : '';
            const packSize = item.pack_size || item.pack_crate_size || item.total_per_pack || '';
            const costPrice = Number(item.cost_price || 0);
            const effectiveCost = Number(item.effective_cost || 0);
            const trueProfit = Number(item.total_true_profit || item.true_profit_loss_after_rebate || 0);
            const meta = [
                item.sku ? `SKU: ${escapeReceiptHtml(item.sku)}` : '',
                packSize ? `Pack: ${escapeReceiptHtml(packSize)}` : '',
                tierName ? `Tier: ${escapeReceiptHtml(tierName)}` : '',
                showOffice && rebatePct ? rebatePct : '',
                showOffice && costPrice > 0 ? `Cost: ${moneyReceipt(costPrice)}` : '',
                showOffice && effectiveCost > 0 ? `Effective: ${moneyReceipt(effectiveCost)}` : '',
                showOffice ? `True Profit: ${moneyReceipt(trueProfit)}` : ''
            ].filter(Boolean).join(' • ');

            return `
                <tr>
                    <td>
                        <strong>${escapeReceiptHtml(item.name || item.product_name || 'Beverage Item')}</strong>
                        ${meta ? `<span>${meta}</span>` : ''}
                    </td>
                    <td>${qty.toLocaleString()}</td>
                    <td>${moneyReceipt(rate)}</td>
                    <td>${moneyReceipt(total)}</td>
                </tr>
            `;
        }).join('')
        : '<tr><td colspan="4" style="text-align:center;color:#64748b">No item snapshot found for this receipt.</td></tr>';

    const officePanel = `
        <div class="receipt-office-panel">
            <h4>Office Control Snapshot</h4>
            <div class="receipt-office-grid">
                <div><span>Total Cost</span><strong>${moneyReceipt(transaction.total_cost_price || 0)}</strong></div>
                <div><span>Total Rebate</span><strong>${moneyReceipt(transaction.total_rebate_amount || 0)}</strong></div>
                <div><span>Effective Cost</span><strong>${moneyReceipt(transaction.total_effective_cost || 0)}</strong></div>
                <div><span>True Profit</span><strong>${moneyReceipt(transaction.total_true_profit_after_rebate || 0)}</strong></div>
            </div>
        </div>
    `;

    const copyHtml = (copyClass, label, note, showSignatures) => `
        <section class="receipt-paper ${copyClass}">
            <div class="receipt-copy-label">${label}</div>
            <div class="receipt-header">
                <div class="receipt-brand">
                    <div class="receipt-logo"><img src="${logoUrl}" alt="Empress Tee Beverage Depot"></div>
                    <div>
                        <h3>${receiptBusinessName}</h3>
                        <p>Beverage Wholesale POS Receipt</p>
                        <div class="receipt-business-lines">
                            <span>Jacroxx Warehouse Main Depot</span>
                            <span>Official sales and stock-control document</span>
                        </div>
                    </div>
                </div>
                <div class="receipt-meta">
                    <strong>${receiptNo}</strong>
                    <span>${timestamp}</span>
                </div>
            </div>
            <div class="receipt-summary-strip">
                <div><span>Items</span><strong>${receiptItems.length.toLocaleString()}</strong></div>
                <div><span>Total Qty</span><strong>${totalQty.toLocaleString()}</strong></div>
                <div><span>Branch</span><strong>${branchName}</strong></div>
            </div>
            <div class="receipt-info-grid">
                <div class="receipt-info-cell"><span>Customer</span><strong>${customerName}</strong>${customerPhone ? `<small>${customerPhone}</small>` : ''}</div>
                <div class="receipt-info-cell"><span>Cashier</span><strong>${cashierName}</strong></div>
                <div class="receipt-info-cell"><span>Payment</span><strong>${paymentMethod}</strong></div>
                <div class="receipt-info-cell"><span>Terminal</span><strong>${terminalName}</strong></div>
            </div>
            <table class="receipt-items">
                <thead><tr><th>Product</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                <tbody>${itemsRows(showSignatures)}</tbody>
            </table>
            <div class="receipt-totals">
                <div class="receipt-total-row"><span>Subtotal</span><strong>${moneyReceipt(subtotal)}</strong></div>
                ${discount > 0 ? `<div class="receipt-total-row"><span>Discount</span><strong>-${moneyReceipt(discount)}</strong></div>` : ''}
                ${crateDeposit > 0 ? `<div class="receipt-total-row"><span>Crate Deposit</span><strong>${moneyReceipt(crateDeposit)}</strong></div>` : ''}
                <div class="receipt-total-row"><span>Amount Paid</span><strong>${moneyReceipt(amountPaid)}</strong></div>
                <div class="receipt-total-row"><span>Change Due</span><strong>${moneyReceipt(changeDue)}</strong></div>
                <div class="receipt-total-row grand"><span>Grand Total</span><strong>${moneyReceipt(grandTotal)}</strong></div>
            </div>
            ${showSignatures ? officePanel : ''}
            ${showSignatures ? '<div class="receipt-signatures"><div class="receipt-signature-line">Cashier Signature</div><div class="receipt-signature-line">Customer / Receiver Signature</div><div class="receipt-signature-line">Supervisor Review</div><div class="receipt-signature-line">Accounts Posting</div></div>' : ''}
            <div class="receipt-footer">
                <strong>${showSignatures ? 'Office Record' : 'Thank you for your beverage purchase'}</strong>
                <span>${configuredFooterNote || note}</span>
                <div class="receipt-verification">
                    <span>Printed by 360Management Beverage POS</span>
                    <span class="receipt-code-box">${receiptNo.slice(-10) || 'RECEIPT'}</span>
                </div>
            </div>
        </section>
    `;

    const modal = document.createElement('div');
    modal.className = 'receipt-modal';
    modal.id = 'receiptModal';
    modal.innerHTML = `
        <div class="receipt-modal-panel">
            <div class="receipt-modal-toolbar">
                <div><strong>Sale Receipt Reprint</strong><span>${receiptNo}</span></div>
                <div class="receipt-toolbar-actions">
                    <button type="button" class="receipt-print-btn" onclick="window.print();">Print Both Copies</button>
                    <button type="button" class="receipt-close-btn" onclick="document.getElementById('receiptModal').remove()">Close</button>
                </div>
            </div>
            <div class="receipt-sheets">
                ${copyHtml('customer-copy', 'Customer Copy', 'Goods received in good condition. Keep this receipt for crate deposit, exchange, or account reconciliation.', false)}
                ${copyHtml('office-copy', 'Office Copy', 'Retain this copy for end-of-day cashup, stock deduction, and accounts posting.', true)}
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function applyPosRealtimePayload(data) {
    if (!data || !data.ok || !data.inventory || !Array.isArray(data.inventory.products)) {
        return;
    }

    data.inventory.products.forEach((product) => {
        const sku = String(product.sku || '');
        if (!sku) return;
        const safeSku = window.CSS && typeof window.CSS.escape === 'function'
            ? window.CSS.escape(sku)
            : sku.replace(/["\\]/g, '\\$&');

        document.querySelectorAll(`.product-card[data-sku="${safeSku}"]`).forEach((card) => {
            const stockQty = Number(product.stock_crates || 0);
            card.setAttribute('data-stock', String(stockQty));
            if (stockQty <= 0) {
                card.style.opacity = '0.58';
                card.setAttribute('aria-disabled', 'true');
            } else {
                card.style.opacity = '';
                card.removeAttribute('aria-disabled');
            }
        });

        document.querySelectorAll(`[data-live-stock="${safeSku}"]`).forEach((badge) => {
            const stockQty = Number(product.stock_crates || 0);
            const unit = badge.getAttribute('data-unit-label') || 'Crates';
            badge.textContent = `${stockQty.toLocaleString()} ${unit} Available`;
            badge.classList.toggle('stock-low', stockQty <= 100);
            badge.classList.toggle('stock-good', stockQty > 100);
        });

        if (product.image) {
            document.querySelectorAll(`[data-live-image="${safeSku}"]`).forEach((img) => {
                const nextSrc = resolveProductImageSrc(product.image);
                if (nextSrc && img.getAttribute('src') !== nextSrc) {
                    img.setAttribute('src', nextSrc);
                }
            });
        }
    });
}

function startPosRealtimeSync() {
    const endpoint = '<?= url('api_realtime.php') ?>';
    let lastProductsVersion = null;

    const poll = () => {
        fetch(endpoint, {credentials: 'same-origin'})
            .then((response) => response.ok ? response.json() : null)
            .then((data) => {
                if (!data || !data.ok) return;
                const productsVersion = data.version ? data.version.products : null;
                if (productsVersion && productsVersion === lastProductsVersion) return;
                lastProductsVersion = productsVersion;
                applyPosRealtimePayload(data);
            })
            .catch(() => {});
    };

    window.setTimeout(poll, 1200);
    window.setInterval(poll, 30000);
}

startPosRealtimeSync();

/**
 * Resolve dynamic quantity pricing tier in JavaScript
 */
function resolveTierForQtyJS(item, qty) {
    qty = Math.max(1, parseInt(qty || 1));
    const tiers = Array.isArray(item.pricing_tiers) ? item.pricing_tiers : [];
    const basePrice = parseFloat(item.wholesale_price || item.selling_price || item.price_per_unit || item.cost_price || 0);
    const channelEl = document.getElementById('bevSalesChannel');
    const activeChannel = channelEl ? channelEl.value : 'depot_sale';

    let matchedTier = null;
    for (const t of tiers) {
        if (t.is_active === false || t.is_active === 0 || t.is_active === '0') continue;
        const tierChannel = String(t.sales_channel || 'all');
        if (tierChannel !== 'all' && tierChannel !== activeChannel) continue;
        const min = parseInt(t.min_qty || 1);
        const max = (!t.max_qty || parseInt(t.max_qty) <= 0) ? Infinity : parseInt(t.max_qty);
        if (qty >= min && qty <= max) {
            matchedTier = t;
            break;
        }
    }

    if (matchedTier && parseFloat(matchedTier.selling_price) > 0) {
        const rangeStr = (!matchedTier.max_qty || parseInt(matchedTier.max_qty) <= 0)
            ? `${matchedTier.min_qty}+`
            : `${matchedTier.min_qty}–${matchedTier.max_qty}`;
        return {
            tier_id: matchedTier.id || 'TIER',
            tier_name: matchedTier.tier_name || `${rangeStr} Tier`,
            range_label: rangeStr,
            selling_price: parseFloat(matchedTier.selling_price),
            is_custom: true
        };
    }

    return {
        tier_id: 'STANDARD',
        tier_name: 'Standard Price',
        range_label: 'Standard',
        selling_price: basePrice,
        is_custom: false
    };
}

function onSalesChannelChange() {
    const channelEl = document.getElementById('bevSalesChannel');
    const formChannel = document.getElementById('formBevSalesChannel');
    if (channelEl && formChannel) {
        formChannel.value = channelEl.value;
    }
    updateBevCartUI();
}

/**
 * Compute economics (Cost, Rebate, Effective Cost, Profit) in JavaScript
 */
function calculateItemEconomicsJS(costPrice, sellingPrice, rebatePct, qty) {
    qty = Math.max(1, parseInt(qty || 1));
    costPrice = Math.max(0, parseFloat(costPrice || 0));
    sellingPrice = Math.max(0, parseFloat(sellingPrice || 0));
    rebatePct = Math.max(0, parseFloat(rebatePct || 0));

    const unitRebateAmt = costPrice * (rebatePct / 100.0);
    const unitEffCost = Math.max(0, costPrice - unitRebateAmt);
    const unitProfitBefore = sellingPrice - costPrice;
    const unitTrueProfit = sellingPrice - unitEffCost;

    return {
        unit_rebate_amount: unitRebateAmt,
        unit_effective_cost: unitEffCost,
        unit_profit_before_rebate: unitProfitBefore,
        unit_true_profit: unitTrueProfit,
        total_true_profit: unitTrueProfit * qty,
        is_below_cost_profitable: (sellingPrice < costPrice && unitTrueProfit > 0)
    };
}

/**
 * Card real-time dynamic pricing calculation on quantity change
 */
function onCardQtyChange(itemIdSafe, qtyVal, item) {
    const qtyInput = document.getElementById('qty_' + itemIdSafe);
    if (!qtyInput) return;

    let qty = parseInt(qtyVal || 1);
    const maxStock = parseInt(item.stock_qty || 0);
    if (isNaN(qty) || qty < 1) qty = 1;
    if (maxStock > 0 && qty > maxStock) qty = maxStock;
    qtyInput.value = qty;

    const tier = resolveTierForQtyJS(item, qty);
    const cost = parseFloat(item.cost_price || 0);
    const rebatePct = parseFloat(item.rebate_info ? item.rebate_info.rebate_pct : 0.0);
    const econ = calculateItemEconomicsJS(cost, tier.selling_price, rebatePct, qty);

    const elTier = document.getElementById('tier_badge_' + itemIdSafe);
    const elPrice = document.getElementById('price_display_' + itemIdSafe);
    const elRebate = document.getElementById('rebate_display_' + itemIdSafe);
    const elEffCost = document.getElementById('eff_cost_display_' + itemIdSafe);
    const elMargin = document.getElementById('margin_display_' + itemIdSafe);

    if (elTier) elTier.textContent = tier.tier_name;
    if (elPrice) elPrice.textContent = '₦' + tier.selling_price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (elRebate) elRebate.textContent = rebatePct + '%';
    if (elEffCost) elEffCost.textContent = '₦' + Math.round(econ.unit_effective_cost).toLocaleString();
    if (elMargin) {
        const sign = econ.unit_true_profit >= 0 ? '+₦' : '-₦';
        elMargin.textContent = sign + Math.abs(Math.round(econ.unit_true_profit)).toLocaleString();
        elMargin.style.color = econ.unit_true_profit >= 0 ? '#16a34a' : '#dc2626';
    }
}

function stepCardQty(itemIdSafe, delta, item) {
    const qtyInput = document.getElementById('qty_' + itemIdSafe);
    if (!qtyInput) return;
    const current = parseInt(qtyInput.value || 1);
    const next = current + delta;
    onCardQtyChange(itemIdSafe, next, item);
}

function addCardToCart(itemIdSafe, item) {
    const qtyInput = document.getElementById('qty_' + itemIdSafe);
    const qty = parseInt(qtyInput ? qtyInput.value : 1);
    addToBevCartWithQty(item, qty);
}

function addToBevCartWithQty(item, qty) {
    const available = parseInt(item.stock_qty || 0);
    if (available <= 0) {
        alert('This product is out of stock.');
        return;
    }

    qty = Math.max(1, parseInt(qty || 1));
    const itemId = String(item.id || item.sku || item.name);
    const existing = bevCart.find(i => String(i.id) === itemId || (i.sku && item.sku && i.sku === item.sku));
    const existingQty = existing ? existing.qty : 0;
    if (existingQty + qty > available) {
        alert('Only ' + available.toLocaleString() + ' available in inventory for ' + item.name + '.');
        return;
    }

    const totalQty = existing ? (existing.qty + qty) : qty;
    const tier = resolveTierForQtyJS(item, totalQty);
    const cost = parseFloat(item.cost_price || 0);
    const rebatePct = parseFloat(item.rebate_info ? item.rebate_info.rebate_pct : 0.0);
    const econ = calculateItemEconomicsJS(cost, tier.selling_price, rebatePct, totalQty);

    if (existing) {
        existing.qty = totalQty;
        existing.applied_tier = tier.tier_name;
        existing.price_per_unit = tier.selling_price;
        existing.total = tier.selling_price * totalQty;
        existing.rebate_pct = rebatePct;
        existing.effective_cost = econ.unit_effective_cost;
        existing.unit_true_profit = econ.unit_true_profit;
        existing.total_true_profit = econ.total_true_profit;
    } else {
        bevCart.push({
            id: itemId,
            sku: item.sku || '',
            name: item.name,
            cost_price: cost,
            applied_tier: tier.tier_name,
            price_per_unit: tier.selling_price,
            crate_deposit: parseFloat(item.crate_deposit || 0),
            packaging: item.packaging || 'Unit',
            icon: item.icon || '🥤',
            image: item.image || '',
            stock_qty: available,
            rebate_pct: rebatePct,
            effective_cost: econ.unit_effective_cost,
            unit_true_profit: econ.unit_true_profit,
            total_true_profit: econ.total_true_profit,
            pricing_tiers: item.pricing_tiers || [],
            rebate_info: item.rebate_info || {},
            qty: totalQty,
            total: tier.selling_price * totalQty
        });
    }
    updateBevCartUI();
}

function removeBevCartItem(itemId) {
    bevCart = bevCart.filter(i => String(i.id) !== String(itemId));
    updateBevCartUI();
}

function clearBevCart() {
    bevCart = [];
    updateBevCartUI();
}

function updateBevCartUI() {
    const container = document.getElementById('bevCartContainer');
    const countSpan = document.getElementById('txtCartItemCount');
    if (countSpan) countSpan.textContent = bevCart.reduce((sum, i) => sum + i.qty, 0);

    if (!container) return;
    if (bevCart.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;color:#64748b;padding:2.5rem 1rem">
                <span style="font-size:2rem;display:block;margin-bottom:0.5rem">🛒</span>
                Cart is empty. Click beverage products on the left to start sale.
            </div>`;
        setBevPrices(0, 0, 0);
        return;
    }

    let subtotal = 0;
    let crateDeposit = 0;
    let html = '';

    bevCart.forEach(item => {
        // Re-evaluate tier dynamically based on cart quantity
        const tier = resolveTierForQtyJS(item, item.qty);
        item.price_per_unit = tier.selling_price;
        item.applied_tier = tier.tier_name;
        item.total = item.price_per_unit * item.qty;

        const econ = calculateItemEconomicsJS(item.cost_price, item.price_per_unit, item.rebate_pct, item.qty);
        item.effective_cost = econ.unit_effective_cost;
        item.unit_true_profit = econ.unit_true_profit;
        item.total_true_profit = econ.total_true_profit;

        subtotal += item.total;
        crateDeposit += ((item.crate_deposit || 0) * item.qty);
        const imgSrc = item.image ? resolveProductImageSrc(item.image) : '';
        const profitSign = item.unit_true_profit >= 0 ? '+₦' : '-₦';

        html += `
            <div class="cart-item-row" style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0;border-bottom:1px solid #f1f5f9">
                <div class="cart-item-info" style="display:flex;align-items:center;gap:0.6rem;flex:1;min-width:0">
                    <div class="cart-item-icon" style="width:36px;height:36px;border-radius:6px;overflow:hidden;background:#f8fafc;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        ${imgSrc ? `<img src="${imgSrc}" alt="" style="width:100%;height:100%;object-fit:cover">` : '<span>🥤</span>'}
                    </div>
                    <div style="min-width:0;overflow:hidden">
                        <div class="cart-item-name" style="font-weight:700;font-size:0.82rem;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${item.name}</div>
                        <div class="cart-item-sub" style="font-size:0.7rem;color:#64748b">
                            <span style="background:#e0f2fe;color:#0369a1;padding:0.1rem 0.3rem;border-radius:3px;font-weight:700">${item.applied_tier}</span>
                            ₦${item.price_per_unit.toLocaleString()} • True Profit: <strong style="color:${item.unit_true_profit >= 0 ? '#16a34a' : '#dc2626'}">${profitSign}${Math.abs(Math.round(item.unit_true_profit)).toLocaleString()}</strong>
                        </div>
                    </div>
                </div>
                <div class="cart-item-right" style="display:flex;align-items:center;gap:0.5rem;flex-shrink:0">
                    <span class="cart-item-qty" style="font-weight:700;font-size:0.8rem;background:#f1f5f9;padding:0.2rem 0.5rem;border-radius:4px">x${item.qty}</span>
                    <span class="cart-item-price" style="font-weight:800;font-size:0.82rem;color:#059669">₦${item.total.toLocaleString()}</span>
                    <button type="button" class="cart-item-del" onclick="removeBevCartItem('${item.id}')" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:0.9rem">✕</button>
                </div>
            </div>`;
    });

    container.innerHTML = html;

    const grandTotal = subtotal + crateDeposit;

    setBevPrices(subtotal, crateDeposit, grandTotal);
}

// Modal and Tier Management Handlers
function openAddPricingTierModal(sku) {
    document.getElementById('pricingTierModalTitleText').textContent = 'Add New Pricing Tier';
    document.getElementById('modal_tier_id').value = '';
    document.getElementById('modal_tier_sku').value = sku;
    document.getElementById('modal_tier_product_display').value = sku;
    document.getElementById('modal_tier_name').value = '';
    document.getElementById('modal_sales_channel').value = 'all';
    document.getElementById('modal_min_selling_price').value = '';
    document.getElementById('modal_min_qty').value = 1;
    document.getElementById('modal_max_qty').value = '';
    document.getElementById('modal_tier_price').value = '';
    document.getElementById('modal_tier_active').checked = true;
    document.getElementById('tierOverlapFeedback').style.display = 'none';
    document.getElementById('pricingTierModal').style.display = 'flex';
}

function openEditPricingTierModal(tier) {
    document.getElementById('pricingTierModalTitleText').textContent = 'Edit Pricing Tier: ' + (tier.tier_name || '');
    document.getElementById('modal_tier_id').value = tier.id || '';
    document.getElementById('modal_tier_sku').value = tier.sku || '';
    document.getElementById('modal_tier_product_display').value = tier.sku || '';
    document.getElementById('modal_tier_name').value = tier.tier_name || '';
    document.getElementById('modal_sales_channel').value = tier.sales_channel || 'all';
    document.getElementById('modal_min_selling_price').value = tier.min_selling_price || '';
    document.getElementById('modal_min_qty').value = tier.min_qty || 1;
    document.getElementById('modal_max_qty').value = tier.max_qty || '';
    document.getElementById('modal_tier_price').value = tier.selling_price || '';
    document.getElementById('modal_tier_start_date').value = tier.start_date || '<?= date('Y-m-d') ?>';
    document.getElementById('modal_tier_end_date').value = tier.end_date || '';
    document.getElementById('modal_tier_active').checked = !(tier.is_active === false || tier.is_active === 0 || tier.is_active === '0');
    document.getElementById('tierOverlapFeedback').style.display = 'none';
    document.getElementById('pricingTierModal').style.display = 'flex';
}

function closePricingTierModal() {
    document.getElementById('pricingTierModal').style.display = 'none';
}

function validateTierRangeLive() {
    const min = parseInt(document.getElementById('modal_min_qty').value || 1);
    const maxVal = document.getElementById('modal_max_qty').value.trim();
    const max = maxVal ? parseInt(maxVal) : null;
    const feedback = document.getElementById('tierOverlapFeedback');
    const submitBtn = document.getElementById('btnSaveTierSubmit');

    if (max !== null && max < min) {
        feedback.style.display = 'block';
        feedback.style.background = '#fef2f2';
        feedback.style.color = '#dc2626';
        feedback.style.border = '1px solid #fecaca';
        feedback.textContent = '⚠️ Maximum quantity (' + max + ') cannot be less than Minimum quantity (' + min + ').';
        submitBtn.disabled = true;
    } else {
        feedback.style.display = 'block';
        feedback.style.background = '#f0fdf4';
        feedback.style.color = '#15803d';
        feedback.style.border = '1px solid #bbf7d0';
        feedback.textContent = '✅ Range configured: ' + min + (max ? ' to ' + max : '+ (open-ended)');
        submitBtn.disabled = false;
    }
}

// Rebate Modal Handlers
function openAddRebateModal() {
    document.getElementById('rebateModalTitleText').textContent = 'Add New Rebate Rule';
    document.getElementById('modal_rebate_id').value = '';
    document.getElementById('modal_rebate_level').value = 'global';
    document.getElementById('modal_rebate_target').value = 'ALL';
    document.getElementById('modal_rebate_pct').value = '0.0';
    document.getElementById('modal_rebate_desc').value = '';
    document.getElementById('modal_rebate_base_price').value = '';
    document.getElementById('modal_rebate_adjustment_factor').value = '100';
    document.getElementById('modal_rebate_formula_type').value = 'standard_pct';
    document.getElementById('modal_rebate_active').checked = true;
    onRebateLevelChange();
    document.getElementById('rebateRuleModal').style.display = 'flex';
}

function openEditRebateModal(rule) {
    document.getElementById('rebateModalTitleText').textContent = 'Edit Rebate Rule';
    document.getElementById('modal_rebate_id').value = rule.id || '';
    document.getElementById('modal_rebate_level').value = rule.level || 'global';
    document.getElementById('modal_rebate_target').value = rule.target_key || 'ALL';
    document.getElementById('modal_rebate_pct').value = rule.rebate_pct || '';
    document.getElementById('modal_rebate_desc').value = rule.description || '';
    document.getElementById('modal_rebate_base_price').value = rule.rebate_base_price || '';
    document.getElementById('modal_rebate_adjustment_factor').value = rule.adjustment_factor || '100';
    document.getElementById('modal_rebate_formula_type').value = rule.formula_type || 'standard_pct';
    document.getElementById('modal_rebate_start').value = rule.start_date || '<?= date('Y-m-d') ?>';
    document.getElementById('modal_rebate_end').value = rule.end_date || '';
    document.getElementById('modal_rebate_active').checked = !(rule.is_active === false || rule.is_active === 0 || rule.is_active === '0');
    onRebateLevelChange();
    document.getElementById('rebateRuleModal').style.display = 'flex';
}

function closeRebateModal() {
    document.getElementById('rebateRuleModal').style.display = 'none';
}

function onRebateLevelChange() {
    const lvl = document.getElementById('modal_rebate_level').value;
    const targetInput = document.getElementById('modal_rebate_target');
    const targetSelect = document.getElementById('modal_rebate_target_select');
    const supplierProducts = <?= json_encode($rebateSupplierProducts ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const catalogProducts = <?= json_encode($catalogProducts ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const catalogProductsBySupplier = <?= json_encode($catalogProductsBySupplier ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    targetSelect.replaceChildren();
    if (lvl === 'global') {
        targetInput.style.display = 'block';
        targetInput.name = 'target_key';
        targetInput.required = true;
        targetSelect.style.display = 'none';
        targetSelect.required = false;
        targetSelect.removeAttribute('name');
        targetInput.value = 'ALL';
        targetInput.readOnly = true;
    } else if (lvl === 'supplier' || lvl === 'product') {
        targetInput.style.display = 'none';
        targetInput.removeAttribute('name');
        targetInput.required = false;
        targetInput.readOnly = false;
        targetSelect.style.display = 'block';
        targetSelect.name = 'target_key';
        targetSelect.required = true;

        if (lvl === 'supplier') {
            targetSelect.add(new Option('Select supplier', ''));
            Object.keys(supplierProducts).sort().forEach(function (supplier) {
                targetSelect.add(new Option(supplier, supplier));
            });
        } else {
            targetSelect.add(new Option('Select product / SKU', ''));
            Object.keys(catalogProductsBySupplier).sort().forEach(function (supplier) {
                const group = document.createElement('optgroup');
                group.label = supplier;
                (catalogProductsBySupplier[supplier] || []).forEach(function (product) {
                    group.appendChild(new Option(product.label, product.value));
                });
                targetSelect.appendChild(group);
            });
        }
        targetSelect.value = targetInput.value === 'ALL' ? '' : targetInput.value;
    } else {
        targetInput.style.display = 'block';
        targetInput.name = 'target_key';
        targetInput.required = true;
        targetSelect.style.display = 'none';
        targetSelect.required = false;
        targetSelect.removeAttribute('name');
        targetInput.readOnly = false;
        if (targetInput.value === 'ALL') targetInput.value = '';
    }
}

function runRebateSimulator() {
    const cost = parseFloat(document.getElementById('sim_cost').value || 0);
    const rebate = parseFloat(document.getElementById('sim_rebate').value || 0);
    const price = parseFloat(document.getElementById('sim_price').value || 0);
    const qty = Math.max(1, parseInt(document.getElementById('sim_qty').value || 1));

    const rebateAmt = cost * (rebate / 100.0);
    const effCost = Math.max(0, cost - rebateAmt);
    const profitBefore = price - cost;
    const trueProfit = price - effCost;
    const totalProfit = trueProfit * qty;

    document.getElementById('sim_res_rebate').textContent = '₦' + rebateAmt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('sim_res_eff_cost').textContent = '₦' + effCost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const elBefore = document.getElementById('sim_res_profit_before');
    elBefore.textContent = (profitBefore >= 0 ? '+₦' : '-₦') + Math.abs(profitBefore).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    elBefore.style.color = profitBefore >= 0 ? '#059669' : '#dc2626';

    const elTrue = document.getElementById('sim_res_true_profit');
    elTrue.textContent = (trueProfit >= 0 ? '+₦' : '-₦') + Math.abs(trueProfit).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    elTrue.style.color = trueProfit >= 0 ? '#059669' : '#dc2626';

    const elTotal = document.getElementById('sim_res_total_profit');
    elTotal.textContent = (totalProfit >= 0 ? '+₦' : '-₦') + Math.abs(totalProfit).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    elTotal.style.color = totalProfit >= 0 ? '#059669' : '#dc2626';
}

function setBevPrices(subtotal, crateDeposit, grandTotal) {
    const elSub = document.getElementById('txtBevSubtotal');
    if (!elSub) return;
    document.getElementById('txtBevSubtotal').textContent = '₦' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('txtBevCrateDeposit').textContent = '₦' + crateDeposit.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('txtBevGrandTotal').textContent = '₦' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2});

    document.getElementById('formBevSubtotal').value = subtotal;
    document.getElementById('formBevCrateDeposit').value = crateDeposit;
    document.getElementById('formBevGrandTotal').value = grandTotal;
    document.getElementById('formBevCartItems').value = JSON.stringify(bevCart);
}

function updateCustomerAccountInfo(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const nameInput = document.getElementById('bevWalkinCustomerName');
    const phoneInput = document.getElementById('bevWalkinCustomerPhone');
    const classInput = document.getElementById('bevCustomerClass');
    const distributorInput = document.getElementById('bevDistributorCode');
    const saveBox = document.getElementById('bevSaveCustomer');
    if (!nameInput || !phoneInput || !saveBox) return;

    if (selectEl.value === '__new_customer') {
        nameInput.disabled = false;
        phoneInput.value = '';
        phoneInput.disabled = false;
        if (classInput) classInput.value = 'Retail';
        if (distributorInput) distributorInput.value = '';
        saveBox.disabled = false;
        nameInput.focus();
    } else {
        nameInput.disabled = false;
        phoneInput.disabled = false;
        phoneInput.value = opt.dataset.phone || '';
        if (classInput) classInput.value = opt.dataset.class || 'Retail';
        if (distributorInput) distributorInput.value = opt.dataset.distributor || '';
        saveBox.checked = false;
        saveBox.disabled = false;
        if (selectEl.value && !selectEl.value.toLowerCase().includes('walk-in')) {
            nameInput.value = '';
        }
    }
    syncWalkinCustomerName();
}

function syncWalkinCustomerName() {
    const saveBox = document.getElementById('bevSaveCustomer');
    const formSave = document.getElementById('formBevSaveCustomer');
    const phoneInput = document.getElementById('bevWalkinCustomerPhone');
    const formPhone = document.getElementById('formBevCustomerPhone');
    const classInput = document.getElementById('bevCustomerClass');
    const formClass = document.getElementById('formBevCustomerClass');
    const distributorInput = document.getElementById('bevDistributorCode');
    const formDistributor = document.getElementById('formBevDistributorCode');
    if (formSave && saveBox) {
        formSave.value = saveBox.checked ? '1' : '0';
    }
    if (formPhone && phoneInput) {
        formPhone.value = phoneInput.value.trim();
    }
    if (formClass && classInput) {
        formClass.value = classInput.value || 'Retail';
    }
    if (formDistributor && distributorInput) {
        formDistributor.value = distributorInput.value.trim().toUpperCase();
    }
}

function setBevPayMethod(method, btnEl) {
    document.querySelectorAll('.pay-btn-item').forEach(b => b.classList.remove('active'));
    btnEl.classList.add('active');
    document.getElementById('formBevPayMethod').value = method;
}

function submitBevSale() {
    if (bevCart.length === 0) {
        alert('⚠️ Cart is empty. Select beverage items to checkout.');
        return;
    }
    const payMethod = document.getElementById('formBevPayMethod').value;
    const custSelect = document.getElementById('bevCustomer');
    const opt = custSelect.options[custSelect.selectedIndex];
    const wallet = parseFloat(opt.dataset.wallet || 0);
    const grandTotal = parseFloat(document.getElementById('formBevGrandTotal').value || 0);
    const typedCustomer = (document.getElementById('bevWalkinCustomerName')?.value || '').trim();
    const typedPhone = (document.getElementById('bevWalkinCustomerPhone')?.value || '').trim();
    const customerPhone = typedPhone || opt.dataset.phone || '';
    const saveCustomer = document.getElementById('bevSaveCustomer')?.checked || false;
    const selectedCustomer = custSelect.value === '__new_customer' ? '' : custSelect.value;
    const customerName = typedCustomer || selectedCustomer || 'Walk-in Customer';

    if (saveCustomer && customerName.toLowerCase().includes('walk-in')) {
        alert('Enter the customer name before saving to contacts.');
        document.getElementById('bevWalkinCustomerName')?.focus();
        return;
    }
    if (saveCustomer && customerPhone === '') {
        alert('Enter the customer phone number before saving to contacts.');
        document.getElementById('bevWalkinCustomerPhone')?.focus();
        return;
    }

    if (payMethod === 'Customer Wallet' && wallet < grandTotal) {
        alert('⚠️ Insufficient Wallet Balance! Customer wallet has ₦' + wallet.toLocaleString() + ' but total is ₦' + grandTotal.toLocaleString() + '. Please select another payment method or credit sale.');
        return;
    }

    document.getElementById('formBevCustomer').value = customerName;
    document.getElementById('formBevCustomerPhone').value = customerPhone;
    syncWalkinCustomerName();
    document.getElementById('bevCheckoutForm').submit();
}

function filterBevCat(cat, btnEl) {
    document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    const target = String(cat).toLowerCase().trim();
    document.querySelectorAll('.product-card').forEach(card => {
        const c = String(card.dataset.cat || '').toLowerCase().trim();
        if (target === 'all' || c === target || c.includes(target) || target.includes(c)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function filterBevCatalog() {
    const q = document.getElementById('bevSearch').value.toLowerCase();
    document.querySelectorAll('.product-card').forEach(card => {
        const title = card.dataset.title || '';
        if (title.includes(q)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
    document.querySelectorAll('.pos-stock-row').forEach(row => {
        const title = row.dataset.stockTitle || '';
        row.style.display = title.includes(q) ? 'block' : 'none';
    });
}

function filterCustomerContacts() {
    const q = document.getElementById('bevSearch').value.toLowerCase();
    document.querySelectorAll('.customer-contact-card').forEach(card => {
        const title = card.dataset.customerTitle || '';
        card.style.display = title.includes(q) ? 'grid' : 'none';
    });
}

function stockWarehouseDataKey(value) {
    return 'stock' + String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-([a-z0-9])/g, function(_, chr) {
            return chr.toUpperCase();
        });
}

function updateMovementAvailability() {
    const skuSelect = document.getElementById('movementSku');
    const fromSelect = document.getElementById('movementFrom');
    const toSelect = document.getElementById('movementTo');
    const qtyInput = document.getElementById('movementQty');
    const note = document.getElementById('movementAvailabilityNote');

    if (!skuSelect || !fromSelect || !note) return;

    const selectedOption = skuSelect.options[skuSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        note.textContent = 'Select a product to see available stock.';
        if (qtyInput) qtyInput.removeAttribute('max');
        return;
    }

    const fromWarehouse = fromSelect.value;
    const toWarehouse = toSelect ? toSelect.value : '';
    const available = parseInt(selectedOption.dataset[stockWarehouseDataKey(fromWarehouse)] || '0', 10);
    note.textContent = `${available.toLocaleString()} crates/packs available in ${fromWarehouse}.`;
    if (qtyInput) {
        qtyInput.max = String(Math.max(0, available));
        if (available > 0 && parseInt(qtyInput.value || '0', 10) > available) {
            qtyInput.value = String(available);
        }
    }

    if (toWarehouse && fromWarehouse === toWarehouse) {
        note.textContent += ' Choose a different destination warehouse before moving stock.';
    }
}

function validateStockMovementForm() {
    const skuSelect = document.getElementById('movementSku');
    const fromSelect = document.getElementById('movementFrom');
    const toSelect = document.getElementById('movementTo');
    const qtyInput = document.getElementById('movementQty');

    if (!skuSelect || !fromSelect || !toSelect || !qtyInput) return true;

    const selectedOption = skuSelect.options[skuSelect.selectedIndex];
    const available = selectedOption ? parseInt(selectedOption.dataset[stockWarehouseDataKey(fromSelect.value)] || '0', 10) : 0;
    const qty = parseInt(qtyInput.value || '0', 10);

    if (!selectedOption || !selectedOption.value) {
        alert('Please select a product to move.');
        return false;
    }

    if (fromSelect.value === toSelect.value) {
        alert('Please choose two different warehouses.');
        return false;
    }

    if (qty <= 0 || qty > available) {
        alert(`You can only move up to ${available.toLocaleString()} crates/packs from ${fromSelect.value}.`);
        return false;
    }

    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    updateBevCartUI();
    updateMovementAvailability();
    initPosAdminSidebar();
});

function initPosAdminSidebar() {
    const sidebar = document.querySelector('.pos-sidebar');
    if (!sidebar) return;

    const collapseKey = 'empressPosAdminSidebarCollapsed';
    const submenuKey = 'empressPosAdminOpenSubmenus';
    const mobileQuery = window.matchMedia('(max-width: 1180px)');

    function syncCollapsedState() {
        if (mobileQuery.matches || !document.querySelector('.pos-admin-shell')) {
            document.body.classList.remove('pos-sidebar-collapsed');
            return;
        }
        document.body.classList.toggle('pos-sidebar-collapsed', localStorage.getItem(collapseKey) === '1');
    }

    function setMobileSidebar(open) {
        document.body.classList.toggle('pos-sidebar-open', open);
        document.querySelectorAll('#posMobileMenuToggle, #posMobileMoreToggle').forEach((button) => {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    syncCollapsedState();

    const savedOpen = new Set((localStorage.getItem(submenuKey) || '').split(',').filter(Boolean));
    document.querySelectorAll('.pos-admin-shell [data-submenu-toggle]').forEach((toggle) => {
        const id = toggle.getAttribute('data-submenu-toggle');
        const menu = id ? document.getElementById(id) : null;
        if (!menu) return;
        if (savedOpen.size > 0) {
            menu.classList.toggle('open', savedOpen.has(id));
        }
        toggle.setAttribute('aria-expanded', menu.classList.contains('open') ? 'true' : 'false');
        toggle.addEventListener('click', () => {
            menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', menu.classList.contains('open') ? 'true' : 'false');
            const openIds = Array.from(document.querySelectorAll('.pos-admin-shell .sidebar-submenu.open')).map((el) => el.id).filter(Boolean);
            localStorage.setItem(submenuKey, openIds.join(','));
        });
    });

    const collapseToggle = document.getElementById('posSidebarCollapseToggle');
    if (collapseToggle) {
        collapseToggle.addEventListener('click', () => {
            if (mobileQuery.matches) {
                setMobileSidebar(false);
                return;
            }
            document.body.classList.toggle('pos-sidebar-collapsed');
            localStorage.setItem(collapseKey, document.body.classList.contains('pos-sidebar-collapsed') ? '1' : '0');
        });
    }

    document.querySelectorAll('#posMobileMenuToggle, #posMobileMoreToggle').forEach((mobileToggle) => {
        mobileToggle.addEventListener('click', () => {
            syncCollapsedState();
            setMobileSidebar(!document.body.classList.contains('pos-sidebar-open'));
        });
    });
    document.querySelectorAll('[data-pos-sidebar-close]').forEach((el) => {
        el.addEventListener('click', () => setMobileSidebar(false));
    });
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setMobileSidebar(false));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMobileSidebar(false);
    });
    mobileQuery.addEventListener('change', () => {
        setMobileSidebar(false);
        syncCollapsedState();
    });
}
</script>

<!-- Live Webcam Camera Barcode Scanner Modal -->
<div id="cameraScannerModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.85); align-items:center; justify-content:center; z-index:9999; padding:1rem">
    <div style="background:#ffffff; width:520px; max-width:95vw; border-radius:12px; padding:1.5rem; box-shadow:0 20px 40px rgba(0,0,0,0.3); text-align:center">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:1rem">
            <h3 style="font-family:'Outfit',sans-serif; font-size:1.15rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:0.4rem">
                📷 Live Webcam Barcode Scanner
            </h3>
            <button type="button" onclick="closeCameraScanner()" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#64748b; cursor:pointer">&times;</button>
        </div>

        <div style="background:#000; border-radius:8px; overflow:hidden; position:relative; height:220px; margin-bottom:1rem; display:flex; align-items:center; justify-content:center">
            <video id="barcodeScannerVideo" autoplay playsinline style="width:100%; height:100%; object-fit:cover"></video>
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:220px; height:90px; border:2px dashed #38bdf8; border-radius:8px; pointer-events:none; box-shadow:0 0 0 9999px rgba(0,0,0,0.4)"></div>
            <div style="position:absolute; bottom:10px; color:#fff; font-size:0.75rem; font-weight:700; background:rgba(0,0,0,0.6); padding:0.25rem 0.6rem; border-radius:4px">Align Barcode within Blue Frame</div>
        </div>

        <div style="margin-bottom:1rem">
            <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Or Scan / Enter Barcode SKU Manually:</label>
            <div style="display:flex; gap:0.5rem">
                <input type="text" id="manualBarcodeScanInput" placeholder="Enter product barcode or SKU" style="flex:1; padding:0.55rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                <button type="button" onclick="simulateBarcodeScan()" style="background:#1d4ed8; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:700; font-size:0.82rem; cursor:pointer">Scan</button>
            </div>
        </div>

        <div style="display:flex; gap:0.5rem; justify-content:center">
            <button type="button" onclick="closeCameraScanner()" style="background:#ef4444; color:#fff; border:none; border-radius:6px; padding:0.4rem 0.85rem; font-size:0.78rem; font-weight:700; cursor:pointer">Close</button>
        </div>
    </div>
</div>

<script>
let cameraStream = null;

function openCameraBarcodeScanner() {
    var modal = document.getElementById('cameraScannerModal');
    if (modal) modal.style.display = 'flex';

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function(stream) {
                cameraStream = stream;
                var video = document.getElementById('barcodeScannerVideo');
                if (video) video.srcObject = stream;
            })
            .catch(function(err) {
                console.log('Webcam active fallback:', err);
            });
    }
}

function closeCameraScanner() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    var modal = document.getElementById('cameraScannerModal');
    if (modal) modal.style.display = 'none';
}

function simulateBarcodeScan() {
    var val = document.getElementById('manualBarcodeScanInput').value.trim();
    if (!val) return;
    alert('✅ Barcode Scanned: ' + val);
    closeCameraScanner();
}

</script>

</body>
</html>
