<?php

declare(strict_types=1);

function warehouse_admin_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'box' => '<svg viewBox="0 0 24 24"><path d="M12 3 21 8 12 13 3 8 12 3Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>',
        'switch' => '<svg viewBox="0 0 24 24"><path d="M7 7h13l-3-3"/><path d="m17 10 3-3"/><path d="M17 17H4l3 3"/><path d="m7 14-3 3"/></svg>',
        'cart' => '<svg viewBox="0 0 24 24"><path d="M4 5h2l2.2 10.5a2 2 0 0 0 2 1.5h6.8a2 2 0 0 0 1.9-1.4L21 9H7"/><circle cx="10" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg>',
        'warehouse' => '<svg viewBox="0 0 24 24"><path d="M3 10 12 4l9 6"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>',
        'orders' => '<svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'truck' => '<svg viewBox="0 0 24 24"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>',
        'return' => '<svg viewBox="0 0 24 24"><path d="M9 7 5 11l4 4"/><path d="M5 11h10a4 4 0 0 1 0 8h-1"/></svg>',
        'pod' => '<svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h4"/><path d="M9 14l2 2 4-5"/></svg>',
        'analytics' => '<svg viewBox="0 0 24 24"><path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-3"/></svg>',
        'finance' => '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M6 7v13"/><path d="M18 7v13"/><path d="M4 20h16"/><path d="M8 11h2"/><path d="M14 11h2"/><path d="M8 15h2"/><path d="M14 15h2"/><path d="M12 3v4"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a7 7 0 0 0-1.8-1L14.4 3h-4.8l-.4 3.1a7 7 0 0 0-1.8 1l-2.4-1-2 3.4L5.1 11a7 7 0 0 0 0 2L3 14.5l2 3.4 2.4-1a7 7 0 0 0 1.8 1l.4 3.1h4.8l.4-3.1a7 7 0 0 0 1.8-1l2.4 1 2-3.4-2.1-1.5a7 7 0 0 0 .1-1Z"/></svg>',
        'users' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 17.5A4.5 4.5 0 0 1 21 20"/></svg>',
        'message' => '<svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 1 1 21 12Z"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>',
        'log' => '<svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        'search' => '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>',
        'download' => '<svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>',
        'upload' => '<svg viewBox="0 0 24 24"><path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M5 3h14"/></svg>',
        'globe' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a13 13 0 0 1 0 18"/><path d="M12 3a13 13 0 0 0 0 18"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24"><path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg>',
        'image' => '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 16-5-5L6 19"/></svg>',
        'refresh' => '<svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 0 1-13.7 5.7"/><path d="M4 12A8 8 0 0 1 17.7 6.3"/><path d="M17 3v4h4"/><path d="M7 21v-4H3"/></svg>',
        'sort' => '<svg viewBox="0 0 24 24"><path d="M8 4v16"/><path d="m5 7 3-3 3 3"/><path d="M16 20V4"/><path d="m13 17 3 3 3-3"/></svg>',
        'down' => '<svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>',
        'check' => '<svg viewBox="0 0 24 24"><path d="m5 12 4 4 10-10"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24"><path d="M12 3 22 20H2L12 3Z"/><path d="M12 9v5"/><path d="M12 17h.01"/></svg>',
        'crown' => '<svg viewBox="0 0 24 24"><path d="m3 8 4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8Z"/><path d="M5 19h14"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24"><path d="M10 5H5v14h5"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/></svg>',
        'arrow-right' => '<svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>',
        'arrow-up' => '<svg viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m6 11 6-6 6 6"/></svg>',
    ];

    return '<span class="wa-svg-icon" aria-hidden="true">' . ($icons[$name] ?? $icons['box']) . '</span>';
}

function render_header(string $title, ?array $user = null, bool $standalone = false): void
{
    $GLOBALS['__layout_mode'] = $standalone ? 'standalone' : ($user ? 'app' : 'public');
    $appName = config('app_name');
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $isPosTerminal = $currentPage === 'beverage_pos.php';
    $isWarehouseAdmin = $user !== null && !$standalone && !$isPosTerminal;
    $notificationCount = 0;
    if ($user && class_exists('App\\Modules\\Notifications\\NotificationService')) {
        $notificationCount = \App\Modules\Notifications\NotificationService::unreadCount((string)($user['company_id'] ?? 'beverage'));
    }
    $stylePath = dirname(__DIR__) . '/public/assets/css/style.css';
    $styleVersion = is_file($stylePath) ? (string) filemtime($stylePath) : '1';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f172a">
        <title><?= e($title) ?> | <?= e($appName) ?></title>
        <!-- Google Fonts: Inter & Outfit -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= url('assets/css/style.css?v=' . $styleVersion) ?>">
    </head>
    <body class="<?= $user ? 'app-body' : 'public-body' ?> <?= $isWarehouseAdmin ? 'has-admin-sidebar' : '' ?>">
    <?php if ($standalone): ?>
        <!-- Standalone Dashboard Layout (Self-contained header & sidebar) -->
        <main class="app-content standalone">
    <?php elseif (!$user): ?>
        <!-- Public Navigation Header -->
        <header class="navbar" id="navbar">
            <div class="nav-container">
                <a class="nav-brand" href="<?= url('') ?>">
                    <div class="brand-icon logo-brand-icon">
                        <img class="app-logo-img" src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="Empress Tee Beverage Depot">
                    </div>
                    <div class="brand-text">
                        <strong>EMPRESS <span>TEE</span></strong>
                        <small>BEVERAGE DEPOT ERP</small>
                    </div>
                </a>

                <nav class="nav-menu" id="navMenu">
                    <a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= url('') ?>">Home</a>
                    <a href="<?= url('#overview') ?>">Overview</a>
                    <a href="<?= url('#modules') ?>">ERP Modules</a>
                    <a href="<?= url('#ai-features') ?>">AI Assistant</a>
                    <a href="<?= url('#health-score') ?>">Health Score</a>
                    <a href="<?= url('#about') ?>">About</a>
                </nav>

                <div class="nav-actions">
                    <a class="btn btn-ghost" href="<?= url('login.php') ?>">Sign In</a>
                    <a class="btn btn-primary" href="<?= url('login.php') ?>">Request Demo →</a>
                    <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </header>
        <main class="public-main">
    <?php else: ?>
        <div class="bev-app-layout <?= $isWarehouseAdmin ? 'warehouse-admin-shell' : '' ?>">
            <div class="admin-sidebar-backdrop" data-admin-sidebar-close></div>
            <aside class="bev-sidebar" id="adminSidebar">
                <div>
                    <div class="sidebar-brand" style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;padding:0 0.5rem">
                        <div class="sidebar-brand-icon logo-sidebar-icon" style="font-size:1.8rem;color:#38bdf8"><img class="app-logo-img" src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="Empress Tee Beverage Depot"></div>
                        <div class="sidebar-brand-text">
                            <h2 style="font-family:'Outfit',sans-serif;font-size:1.1rem;font-weight:800;letter-spacing:0.5px;line-height:1.1;color:#ffffff;margin:0">EMPRESS TEE</h2>
                            <small style="font-size:0.68rem;color:#94a3b8;font-weight:600;letter-spacing:1px">BEVERAGE DEPOT</small>
                        </div>
                    </div>

                    <?php 
                    $userRole = canonical_role($user['role'] ?? '');
                    $userCompany = $user['company_id'] ?? 'beverage';

	                    $activeTab = $_GET['tab'] ?? '';
	                    $activeDetail = strtolower(trim((string)($_GET['detail'] ?? '')));
	                    $activeLogisticsView = strtolower(trim((string)($_GET['view'] ?? 'dispatch')));
	                    if (!in_array($activeLogisticsView, ['dispatch', 'returns', 'pod'], true)) {
	                        $activeLogisticsView = 'dispatch';
	                    }
	                    $activeHrView = strtolower(trim((string)($_GET['view'] ?? 'dashboard')));
	                    if (!in_array($activeHrView, ['dashboard', 'employees', 'departments', 'staff-chat', 'leave', 'payroll', 'onboarding', 'kyc', 'documents', 'damage', 'drivers', 'performance', 'discipline', 'reports', 'settings', 'calendar', 'audit', 'compliance'], true)) {
	                        $activeHrView = 'dashboard';
	                    }
	                    $sidebarStockCount = 0;
	                    $sidebarMovementCount = 0;
	                    $sidebarCache = $_SESSION['beverage_sidebar_counts'] ?? null;
	                    if (is_array($sidebarCache) && (time() - (int)($sidebarCache['cached_at'] ?? 0)) < 45) {
	                        $sidebarStockCount = (int)($sidebarCache['stock_count'] ?? 0);
	                        $sidebarMovementCount = (int)($sidebarCache['movement_count'] ?? 0);
	                    } else {
	                        try {
	                            if (class_exists('App\\Modules\\BeverageWarehouse\\BeverageWarehouseService')) {
	                                $sidebarSummaries = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getWarehouseSummaries();
	                                $sidebarStockCount = array_sum(array_map(static fn(array $summary): int => (int)($summary['stock_crates'] ?? 0), $sidebarSummaries));
	                                $sidebarMovementCount = count(array_filter(
	                                    \App\Modules\BeverageWarehouse\BeverageWarehouseService::getTransfers(),
	                                    static fn(array $movement): bool => isset($movement['from'], $movement['to'], $movement['sku'])
	                                ));
	                                $_SESSION['beverage_sidebar_counts'] = [
	                                    'stock_count' => $sidebarStockCount,
	                                    'movement_count' => $sidebarMovementCount,
	                                    'cached_at' => time(),
	                                ];
	                            }
	                        } catch (Throwable) {
	                            $sidebarStockCount = 0;
	                            $sidebarMovementCount = 0;
	                        }
	                    }
	                    $sidebarStockBadge = $sidebarStockCount >= 1000 ? number_format($sidebarStockCount / 1000, 1) . 'k' : number_format($sidebarStockCount);
	                    $warehousePageTitle = 'Dashboard';
	                    $warehousePageSubtitle = 'Overview of your warehouse operations';
	                    if ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-inventory') {
	                        $warehousePageTitle = $activeDetail === 'available' ? 'Stock Levels' : ($activeDetail === 'zones' ? 'Warehouse Zones' : 'Products');
	                        $warehousePageSubtitle = $activeDetail === 'available'
	                            ? 'Review live stock levels across beverage products'
	                            : ($activeDetail === 'zones' ? 'Compare stock across warehouse locations' : 'Manage warehouse products and stock');
	                    } elseif ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-truck-loads') {
	                        $warehousePageTitle = 'Truck Loads';
	                        $warehousePageSubtitle = 'Plan mixed SKU truck loads and receiving';
	                    } elseif ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-physical-count') {
	                        $warehousePageTitle = 'Physical Count';
	                        $warehousePageSubtitle = 'Onboard warehouse stock counts and variance records';
	                    } elseif ($currentPage === 'finance.php' || $currentPage === 'finance_app.php') {
	                        $warehousePageTitle = 'Finance';
	                        $warehousePageSubtitle = 'Track cash, accounts and beverage stock value';
	                    } elseif ($currentPage === 'dashboard.php') {
	                        $logisticsTitle = match ($activeLogisticsView) {
	                            'returns' => ['Returns', 'Track returned stock and delivery exceptions'],
	                            'pod' => ['Delivery POD', 'Confirm receiving, signatures and proof of delivery'],
	                            default => ['Dispatch', 'Manage outgoing deliveries and waybills'],
	                        };
	                        $hrTitle = match ($activeHrView) {
	                            'employees' => ['Employees', 'View staff records and dashboard access'],
	                            'departments' => ['Departments', 'Review workforce distribution'],
	                            'leave' => ['Attendance & Leave', 'Manage duty, leave and attendance'],
	                            'payroll' => ['Payroll', 'Run payroll and review payroll workflow'],
	                            'onboarding' => ['Onboarding', 'Add staff and assign dashboard access'],
	                            'kyc' => ['KYC Verification', 'Verify staff identity and KYC records'],
	                            'documents' => ['Documents', 'Manage protected employee documents'],
	                            'damage' => ['Damage Register', 'Track accountability and deductions'],
	                            'drivers' => ['Drivers', 'Monitor driver accountability'],
	                            'performance' => ['Performance', 'Manage staff reviews'],
	                            'discipline' => ['Disciplinary', 'Manage employee disciplinary records'],
	                            'reports' => ['HR Reports', 'Download HR reports'],
	                            'settings' => ['HR Settings', 'Configure HR rules and roles'],
	                            'calendar' => ['Work Calendar', 'Manage work days and holidays'],
	                            'audit' => ['Audit Trail', 'Review HR changes and payroll workflow'],
	                            'compliance' => ['Statutory Compliance', 'Review payroll compliance obligations'],
	                            default => ['HR & Payroll', 'Manage employees, KYC, payroll and workforce records'],
	                        };
	                        $dashboardTitles = [
	                            'tab-hr-payroll' => $hrTitle,
	                            'tab-geo-attendance' => ['Attendance', 'Clock-in, official duty and attendance audit'],
	                            'tab-logistics' => $logisticsTitle,
	                            'tab-maintenance' => ['Maintenance', 'Track assets, work orders and equipment health'],
	                            'tab-procurement' => ['Procurement', 'Purchase orders, vendors and GRN workflows'],
	                            'tab-payment-recon' => ['Payment Recon', 'Match bank alerts, receipts and transfer payments'],
	                            'tab-health-score' => ['Analytics', 'Monitor warehouse performance and system health'],
	                            'tab-central-ai' => ['AI Assistant', 'Ask for operational insight across the beverage business'],
	                            'tab-dash-finance' => ['Finance', 'Review beverage accounting and reconciliation'],
	                            'tab-notifications' => ['Notifications', 'Review live stock, payment and system alerts'],
	                        ];
	                        if (isset($dashboardTitles[$activeTab])) {
	                            [$warehousePageTitle, $warehousePageSubtitle] = $dashboardTitles[$activeTab];
	                        } elseif ($activeTab !== '') {
	                            $warehousePageTitle = $title;
	                            $warehousePageSubtitle = 'Beverage depot workspace';
	                        }
	                    }

                    if ($userRole === 'pos'): 
                    ?>
                        <div class="bev-nav-group-title">POINT-OF-SALE &amp; SALES TERMINALS</div>
                        <nav class="bev-nav">
	                            <a href="<?= url('beverage_pos.php') ?>" class="bev-nav-item <?= ($currentPage === 'beverage_pos.php') ? 'active' : '' ?>">
	                                <span><img src="<?= e(url('assets/images/coca_cola_bottle.svg')) ?>" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px"></span> Empress Tee Beverage POS Terminal
	                            </a>
                            <a href="<?= url('logout.php') ?>" class="bev-nav-item" style="margin-top:2rem">
                                <?= warehouse_admin_icon('logout') ?> Sign Out
                            </a>
                        </nav>
                    <?php elseif ($isWarehouseAdmin): ?>
	                        <button type="button" class="sidebar-collapse-toggle" id="sidebarCollapseToggle" title="Collapse sidebar" aria-label="Collapse sidebar">
	                            <?= warehouse_admin_icon('menu') ?>
	                        </button>

	                        <div class="sidebar-section-title">DASHBOARD</div>
	                        <nav class="bev-nav warehouse-admin-nav">
	                            <a href="<?= url('beverage_warehouse.php?tab=tab-dash') ?>" class="bev-nav-item <?= ($currentPage === 'beverage_warehouse.php' && ($activeTab === 'tab-dash' || empty($activeTab))) ? 'active' : '' ?>" data-tooltip="Dashboard">
	                                <?= warehouse_admin_icon('dashboard') ?> Dashboard
	                            </a>
	                        </nav>

	                        <div class="sidebar-section-title">WAREHOUSE</div>
	                        <nav class="bev-nav warehouse-admin-nav">
	                            <button type="button" class="bev-nav-item sidebar-submenu-toggle <?= ($currentPage === 'beverage_warehouse.php' && in_array($activeTab, ['tab-inventory', 'tab-truck-loads', 'tab-physical-count'], true)) || ($currentPage === 'beverage_pos.php' && in_array($activeTab, ['transfers', 'pricing-audit'], true)) ? 'active' : '' ?>" data-submenu-toggle="warehouseMenu" data-tooltip="Inventory" aria-expanded="true">
	                                <?= warehouse_admin_icon('box') ?> Inventory <?= warehouse_admin_icon('down') ?>
	                            </button>
	                            <div class="sidebar-submenu open" id="warehouseMenu">
		                            <a href="<?= url('beverage_warehouse.php?tab=tab-inventory') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-inventory' && $activeDetail === '') ? 'active' : '' ?>" data-tooltip="Products">
		                                <?= warehouse_admin_icon('box') ?> Products
		                            </a>
		                            <a href="<?= url('beverage_warehouse.php?tab=tab-inventory&detail=available') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-inventory' && $activeDetail === 'available') ? 'active' : '' ?>" data-tooltip="Stock Levels">
		                                <?= warehouse_admin_icon('analytics') ?> Stock Levels <span class="nav-count-badge"><?= e($sidebarStockBadge) ?></span>
		                            </a>
		                            <a href="<?= url('beverage_pos.php?tab=transfers') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_pos.php' && $activeTab === 'transfers') ? 'active' : '' ?>" data-tooltip="Stock Transfer">
		                                <?= warehouse_admin_icon('switch') ?> Stock Movement <span class="nav-count-badge"><?= number_format($sidebarMovementCount) ?></span>
		                            </a>
	                            <a href="<?= url('beverage_pos.php?tab=pricing-audit') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_pos.php' && $activeTab === 'pricing-audit') ? 'active' : '' ?>" data-tooltip="Inventory Audit">
	                                <?= warehouse_admin_icon('log') ?> Inventory Audit
	                            </a>
	                            <a href="<?= url('beverage_warehouse.php?tab=tab-inventory&detail=zones') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-inventory' && $activeDetail === 'zones') ? 'active' : '' ?>" data-tooltip="Warehouse Zones">
	                                <?= warehouse_admin_icon('warehouse') ?> Warehouse Zones
	                            </a>
	                            <a href="<?= url('beverage_warehouse.php?tab=tab-truck-loads') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-truck-loads') ? 'active' : '' ?>" data-tooltip="Truck Loads">
	                                <?= warehouse_admin_icon('truck') ?> Truck Loads
	                            </a>
	                            <a href="<?= url('beverage_warehouse.php?tab=tab-physical-count') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-physical-count') ? 'active' : '' ?>" data-tooltip="Physical Count">
	                                <?= warehouse_admin_icon('check') ?> Physical Count
	                            </a>
	                            </div>
	                        </nav>

	                        <div class="sidebar-section-title">OPERATIONS</div>
	                        <nav class="bev-nav warehouse-admin-nav">
	                            <button type="button" class="bev-nav-item sidebar-submenu-toggle <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-logistics') || ($currentPage === 'beverage_pos.php' && ($activeTab === 'pos' || empty($activeTab))) ? 'active' : '' ?>" data-submenu-toggle="operationsMenu" data-tooltip="Operations" aria-expanded="true">
	                                <?= warehouse_admin_icon('truck') ?> Operations <?= warehouse_admin_icon('down') ?>
	                            </button>
	                            <div class="sidebar-submenu open" id="operationsMenu">
	                            <a href="<?= url('beverage_pos.php?tab=pos') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_pos.php' && ($activeTab === 'pos' || empty($activeTab))) ? 'active' : '' ?>" data-tooltip="POS Terminal">
	                                <?= warehouse_admin_icon('cart') ?> POS Terminal
	                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-logistics&view=dispatch&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-logistics' && $activeLogisticsView === 'dispatch') ? 'active' : '' ?>" data-tooltip="Dispatch">
		                                <?= warehouse_admin_icon('truck') ?> Dispatch
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-logistics&view=returns&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-logistics' && $activeLogisticsView === 'returns') ? 'active' : '' ?>" data-tooltip="Returns">
		                                <?= warehouse_admin_icon('return') ?> Returns
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-logistics&view=pod&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-logistics' && $activeLogisticsView === 'pod') ? 'active' : '' ?>" data-tooltip="Delivery POD">
		                                <?= warehouse_admin_icon('pod') ?> Delivery POD
		                            </a>
	                            </div>
	                        </nav>

	                        <div class="sidebar-section-title">ADMIN OPERATIONS</div>
	                        <nav class="bev-nav warehouse-admin-nav">
	                            <button type="button" class="bev-nav-item sidebar-submenu-toggle <?= ($currentPage === 'dashboard.php' && in_array($activeTab, ['tab-hr-payroll', 'tab-geo-attendance', 'tab-maintenance', 'tab-procurement', 'tab-payment-recon', 'tab-central-ai'], true)) ? 'active' : '' ?>" data-submenu-toggle="adminOpsMenu" data-tooltip="Admin Operations" aria-expanded="true">
	                                <?= warehouse_admin_icon('users') ?> Admin Ops <?= warehouse_admin_icon('down') ?>
	                            </button>
	                            <div class="sidebar-submenu open" id="adminOpsMenu">
		                            <a href="<?= url('dashboard.php?tab=tab-hr-payroll&view=dashboard&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-hr-payroll' && $activeHrView !== 'staff-chat') ? 'active' : '' ?>" data-tooltip="HR & Payroll">
		                                <?= warehouse_admin_icon('users') ?> HR &amp; Payroll
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-hr-payroll&view=staff-chat&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-hr-payroll' && $activeHrView === 'staff-chat') ? 'active' : '' ?>" data-tooltip="Staff Chat">
		                                <?= warehouse_admin_icon('message') ?> Staff Chat
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-geo-attendance&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-geo-attendance') ? 'active' : '' ?>" data-tooltip="Attendance">
		                                <?= warehouse_admin_icon('calendar') ?> Attendance
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-procurement&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-procurement') ? 'active' : '' ?>" data-tooltip="Procurement">
		                                <?= warehouse_admin_icon('orders') ?> Procurement
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-payment-recon&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-payment-recon') ? 'active' : '' ?>" data-tooltip="Payment Recon">
		                                <?= warehouse_admin_icon('finance') ?> Payment Recon
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-maintenance&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-maintenance') ? 'active' : '' ?>" data-tooltip="Maintenance">
		                                <?= warehouse_admin_icon('warning') ?> Maintenance
		                            </a>
		                            <a href="<?= url('dashboard.php?tab=tab-central-ai&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-central-ai') ? 'active' : '' ?>" data-tooltip="AI Assistant">
		                                <?= warehouse_admin_icon('globe') ?> AI Assistant
		                            </a>
	                            </div>
	                        </nav>

	                        <div class="sidebar-section-title">REPORTS</div>
	                        <nav class="bev-nav warehouse-admin-nav">
	                            <button type="button" class="bev-nav-item sidebar-submenu-toggle <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-health-score') || in_array($currentPage, ['finance.php', 'finance_app.php'], true) || ($currentPage === 'beverage_pos.php' && $activeTab === 'sales-account') || ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-daily-stock') ? 'active' : '' ?>" data-submenu-toggle="reportsMenu" data-tooltip="Reports" aria-expanded="true">
	                                <?= warehouse_admin_icon('analytics') ?> Reports <?= warehouse_admin_icon('down') ?>
	                            </button>
	                            <div class="sidebar-submenu open" id="reportsMenu">
		                            <a href="<?= url('dashboard.php?tab=tab-health-score&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-health-score') ? 'active' : '' ?>" data-tooltip="Analytics">
		                                <?= warehouse_admin_icon('analytics') ?> Analytics <span class="nav-count-badge">4</span>
		                            </a>
		                            <a href="<?= url('beverage_pos.php?tab=sales-account') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_pos.php' && $activeTab === 'sales-account') ? 'active' : '' ?>" data-tooltip="Reports">
		                                <?= warehouse_admin_icon('log') ?> Reports
		                            </a>
	                            <a href="<?= url('beverage_warehouse.php?tab=tab-daily-stock') ?>" class="bev-nav-subitem <?= ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-daily-stock') ? 'active' : '' ?>" data-tooltip="Daily Stock">
	                                <?= warehouse_admin_icon('check') ?> Daily Stock
	                            </a>
	                            <a href="<?= url('finance.php?tab=tab-dash-finance') ?>" class="bev-nav-subitem <?= ($currentPage === 'finance.php' || $currentPage === 'finance_app.php') ? 'active' : '' ?>" data-tooltip="Finance">
	                                <?= warehouse_admin_icon('finance') ?> Finance
	                            </a>
	                            </div>
	                        </nav>

	                        <div class="sidebar-section-title">SYSTEM</div>
	                        <nav class="bev-nav warehouse-admin-nav">
	                            <button type="button" class="bev-nav-item sidebar-submenu-toggle <?= ($currentPage === 'dashboard.php' && in_array($activeTab, ['', 'tab-system-settings', 'tab-notifications'], true)) ? 'active' : '' ?>" data-submenu-toggle="systemMenu" data-tooltip="System" aria-expanded="true">
	                                <?= warehouse_admin_icon('settings') ?> System <?= warehouse_admin_icon('down') ?>
	                            </button>
	                            <div class="sidebar-submenu open" id="systemMenu">
	                            <a href="<?= url('dashboard.php?tab=tab-system-settings&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-system-settings') ? 'active' : '' ?>" data-tooltip="Settings">
		                                <?= warehouse_admin_icon('settings') ?> Settings
		                            </a>
	                            <a href="<?= url('dashboard.php?tab=tab-notifications&company=beverage') ?>" class="bev-nav-subitem <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-notifications') ? 'active' : '' ?>" data-tooltip="Notifications">
	                                <?= warehouse_admin_icon('bell') ?> Notifications
                                    <?php if ($notificationCount > 0): ?><span class="nav-count-badge"><?= number_format($notificationCount) ?></span><?php endif; ?>
	                            </a>
	                            <a href="<?= url('beverage_warehouse.php?tab=tab-dash') ?>" class="bev-nav-subitem" data-tooltip="Activity Log">
	                                <?= warehouse_admin_icon('log') ?> Activity Log
	                            </a>
	                            </div>
	                        </nav>
                    <?php else: ?>
                        <div class="sidebar-section-title" style="font-size:0.65rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin:1rem 0.5rem 0.4rem 0.5rem">OPERATIONS</div>
                        <nav class="bev-nav">
                            <a href="<?= url('dashboard.php') ?>" class="bev-nav-item <?= ($currentPage === 'dashboard.php' && empty($activeTab)) ? 'active' : '' ?>">
                                <span>🖥️</span> Dashboard
                            </a>
                        </nav>

                            <!-- Empress Tee Beverage Depot Modules -->
                            <div class="sidebar-section-title" style="font-size:0.65rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin:1rem 0.5rem 0.4rem 0.5rem">BEVERAGE DEPOT MODULES</div>
                            <nav class="bev-nav">
                                <a href="<?= url('beverage_warehouse.php?tab=tab-dash') ?>" class="bev-nav-item <?= ($currentPage === 'beverage_warehouse.php' && ($activeTab === 'tab-dash' || empty($activeTab))) ? 'active' : '' ?>">
                                    <span><img src="<?= e(url('assets/images/warehouse_overview.svg')) ?>" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px"></span> Overview
                                </a>
                                <a href="<?= url('beverage_pos.php') ?>" class="bev-nav-item <?= ($currentPage === 'beverage_pos.php') ? 'active' : '' ?>">
                                    <span><img src="<?= e(url('assets/images/coca_cola_bottle.svg')) ?>" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px"></span> Beverage POS Terminal
                                </a>
                                <a href="<?= url('beverage_warehouse.php?tab=tab-inventory') ?>" class="bev-nav-item <?= ($currentPage === 'beverage_warehouse.php' && $activeTab === 'tab-inventory') ? 'active' : '' ?>">
                                    <span><img src="<?= e(url('assets/images/coke_crate.svg')) ?>" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px"></span> Warehouse &amp; Inventory
                                </a>
                                <a href="<?= url('dashboard.php?tab=tab-logistics&view=pod&company=beverage') ?>" class="bev-nav-item <?= ($activeTab === 'tab-logistics') ? 'active' : '' ?>">
                                    <span>🚚</span> Beverage Delivery POD
                                </a>
                                <a href="<?= url('dashboard.php?tab=tab-central-ai&company=beverage') ?>" class="bev-nav-item <?= ($activeTab === 'tab-central-ai') ? 'active' : '' ?>">
                                    <span>🤖</span> AI Assistant
                                </a>
                            </nav>

                        <div class="sidebar-section-title" style="font-size:0.65rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin:1rem 0.5rem 0.4rem 0.5rem">OTHER</div>
                        <nav class="bev-nav">
	                            <a href="<?= url('finance.php?tab=tab-dash-finance') ?>" class="bev-nav-item <?= $currentPage === 'finance.php' ? 'active' : '' ?>">
	                                <span>₦</span> Finance &amp; Accounting
	                            </a>
                            <a href="<?= url('dashboard.php?tab=tab-health-score&company=beverage') ?>" class="bev-nav-item <?= ($activeTab === 'tab-health-score') ? 'active' : '' ?>">
                                <span>📈</span> Reports &amp; Analytics
                            </a>
                            <a href="<?= url('dashboard.php?tab=tab-system-settings&company=beverage') ?>" class="bev-nav-item <?= ($currentPage === 'dashboard.php' && $activeTab === 'tab-system-settings') ? 'active' : '' ?>">
                                <?= warehouse_admin_icon('settings') ?> Settings
                            </a>
                        </nav>
                    <?php endif; ?>
                </div>

	                <div class="sidebar-user-box admin-profile-box">
	                    <div class="user-box-info">
	                        <?php if ($isWarehouseAdmin): ?>
	                            <div class="admin-profile-main">
	                                <div class="admin-profile-avatar">BA<span></span></div>
	                                <div>
	                                    <div class="admin-profile-name"><?= e($user['name'] ?? 'Beverage Admin') ?></div>
	                                    <small class="admin-profile-role"><?= e(ucwords(str_replace('_', ' ', $userRole ?: 'Admin'))) ?> · Online</small>
	                                </div>
	                            </div>
                        <?php else: ?>
                            <small style="font-size:0.68rem;color:#38bdf8;font-weight:600">Active Warehouse:</small>
                            <div style="font-size:0.78rem;font-weight:700;color:#ffffff">Jacroxx Warehouse <small style="color:#38bdf8;font-size:0.65rem">(Main)</small> <span style="color:#10b981">●</span></div>
                        <?php endif; ?>
                    </div>
	                    <div class="admin-profile-actions">
	                        <a href="<?= url('dashboard.php?tab=tab-system-settings&company=beverage') ?>" class="admin-profile-action" title="Settings" data-tooltip="Settings"><?= warehouse_admin_icon('settings') ?></a>
	                        <a href="<?= url('dashboard.php') ?>" class="admin-profile-action" title="Profile menu" data-tooltip="Profile menu"><?= warehouse_admin_icon('users') ?></a>
	                    </div>
	                    <a href="<?= url('logout.php') ?>" class="btn-logout-link admin-logout-link" data-tooltip="Logout">
	                            <?= warehouse_admin_icon('logout') ?> <span>Logout</span>
	                    </a>
	                </div>
            </aside>

            <main class="bev-main-content">
                <header class="bev-topbar">
                    <?php if ($isWarehouseAdmin): ?>
                        <div class="warehouse-top-left">
	                            <button type="button" class="warehouse-menu-btn" id="adminMobileMenuToggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"><?= warehouse_admin_icon('menu') ?></button>
                                <span class="warehouse-mobile-logo" aria-hidden="true">
                                    <img src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="">
                                </span>
	                            <div class="bev-page-title">
	                                <h1><?= e($warehousePageTitle) ?></h1>
	                                <p><?= e($warehousePageSubtitle) ?></p>
	                            </div>
                                <div class="warehouse-mobile-actions" aria-hidden="true">
                                    <a class="bev-icon-btn js-notification-link" href="<?= url('dashboard.php?tab=tab-notifications&company=beverage') ?>" aria-label="Notifications">
                                        <?= warehouse_admin_icon('bell') ?>
                                        <?php if ($notificationCount > 0): ?><span class="dot-badge js-notification-badge"><?= number_format($notificationCount) ?></span><?php endif; ?>
                                    </a>
                                    <div class="warehouse-avatar">BA</div>
                                </div>
                        </div>
                        <div class="bev-top-actions">
                            <div class="warehouse-search">
                                <?= warehouse_admin_icon('search') ?>
                                <input type="search" placeholder="Search products, SKUs, orders...">
                            </div>
                            <button type="button" class="warehouse-date-btn"><?= warehouse_admin_icon('calendar') ?> <?= date('M d, Y') ?> <?= warehouse_admin_icon('down') ?></button>
                            <a class="bev-icon-btn js-notification-link" href="<?= url('dashboard.php?tab=tab-notifications&company=beverage') ?>" aria-label="Notifications">
                                <?= warehouse_admin_icon('bell') ?>
                                <?php if ($notificationCount > 0): ?><span class="dot-badge js-notification-badge"><?= number_format($notificationCount) ?></span><?php endif; ?>
                            </a>
                            <div class="warehouse-avatar">BA</div>
                        </div>
                    <?php else: ?>
                        <div class="bev-page-title">
                            <h1><?= e($title) ?></h1>
                            <p>Multi-Tenant • Multi-Location • Real-time Inventory • FEFO Tracking • AI Analytics</p>
                        </div>

                        <div class="bev-top-actions">
                            <button type="button" class="bev-warehouse-select" title="Switch between Jacroxx Warehouse (Main) and Ijaba Warehouse">
                                Jacroxx Warehouse (Main) <?= warehouse_admin_icon('down') ?>
                            </button>
                            <a class="bev-icon-btn js-notification-link" href="<?= url('dashboard.php?tab=tab-notifications&company=beverage') ?>" aria-label="Notifications">
                                <?= warehouse_admin_icon('bell') ?>
                                <?php if ($notificationCount > 0): ?><span class="dot-badge js-notification-badge"><?= number_format($notificationCount) ?></span><?php endif; ?>
                            </a>
                            <div class="bev-user-pill">
                                <div style="width:32px;height:32px;border-radius:50%;background:#0284c7;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.78rem">BC</div>
                                <div>
                                    <strong><?= e($user['name'] ?? 'Beverage Manager') ?></strong>
                                    <small style="color:#64748b"><?= e($user['company_name'] ?? 'Empress Tee ERP') ?></small>
                                </div>
                            </div>
                            <a class="btn-ask-ai" href="<?= url('dashboard.php?tab=tab-central-ai&company=beverage') ?>" style="background:#1d4ed8;color:#fff;border:none;padding:0.45rem 0.9rem;border-radius:6px;font-weight:700;font-size:0.8rem;cursor:pointer;display:flex;align-items:center;gap:0.4rem;text-decoration:none">
                                <span>✨</span> Ask AI Assistant
                            </a>
                        </div>
                    <?php endif; ?>
                </header>
                <div class="bev-body">
    <?php endif; ?>
        <?php if ($message = flash()): ?>
            <div class="toast-notice"><?= e($message) ?></div>
        <?php endif; ?>
    <?php
}

function render_footer(bool $showPublicFooter = true, bool $standalone = false): void
{
    $layoutMode = $standalone ? 'standalone' : ($GLOBALS['__layout_mode'] ?? (current_user() ? 'app' : 'public'));
    ?>
    <?php if ($layoutMode === 'standalone'): ?>
        <!-- Standalone Footer -->
        </main>
    <?php elseif ($layoutMode === 'public' && $showPublicFooter): ?>
        </main>
        <!-- Footer -->
        <footer class="footer">
            <div class="footer-container">
                <div class="footer-grid">
                    <div class="footer-brand-col">
                        <a class="footer-brand" href="<?= url('') ?>">
                            <div class="brand-icon logo-brand-icon"><img class="app-logo-img" src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="Empress Tee Beverage Depot"></div>
                            <strong>EMPRESS <span>TEE</span></strong>
                        </a>
                        <p class="footer-desc">
                            Next-generation AI-driven Enterprise Resource Planning platform for beverage depot operations.
                        </p>
                        <div class="footer-social">
                            <a href="mailto:info@360management.name.ng?subject=Empress%20Tee%20ERP%20Support" aria-label="LinkedIn">LN</a>
                            <a href="mailto:info@360management.name.ng?subject=Empress%20Tee%20ERP%20Support" aria-label="Twitter">TW</a>
                            <a href="mailto:info@360management.name.ng?subject=Empress%20Tee%20ERP%20Support" aria-label="YouTube">YT</a>
                        </div>
                    </div>

                    <div class="footer-links-col">
                        <h4>Solutions</h4>
                        <a href="<?= url('#modules') ?>">Multi-Location Inventory</a>
                        <a href="<?= url('#modules') ?>">Sales & POS Terminals</a>
                        <a href="<?= url('#modules') ?>">Payment Alert Recon</a>
                    </div>

                    <div class="footer-links-col">
                        <h4>Platform & AI</h4>
                        <a href="<?= url('#ai-features') ?>">Central AI Assistant</a>
                        <a href="<?= url('#health-score') ?>">System Health Score</a>
                        <a href="<?= url('#ai-features') ?>">Prompt Centre</a>
                        <a href="<?= url('login.php') ?>">Role-Based Portals</a>
                        <a href="<?= url('#overview') ?>">Audit & Governance</a>
                    </div>

                    <div class="footer-contact-col">
                        <h4>Contact & Support</h4>
                        <p>📍 360Management Support Office, Victoria Island, Lagos</p>
                        <p>📞 +234 800 360 ERP (377)</p>
                        <p>✉ support@360management.com</p>
                        <a href="<?= url('login.php') ?>" class="btn btn-outline-sm mt-3">Employee Sign In</a>
                    </div>
                </div>

                <div class="footer-bottom">
                    <p>© <?= date('Y') ?> 360Management ERP. All rights reserved.</p>
                    <div class="footer-legal">
                        <a href="<?= url('#overview') ?>">Privacy Policy</a>
                        <a href="<?= url('#overview') ?>">Terms of Service</a>
                        <a href="<?= url('#health-score') ?>">Security &amp; Compliance</a>
                    </div>
                </div>
            </div>
        </footer>
    <?php elseif ($layoutMode === 'app'): ?>
                </div>
            </main>
        </div>
    <?php else: ?>
        </main>
    <?php endif; ?>
    <script src="<?= url('assets/js/app.js') ?>"></script>
	    <script>
	    function toggleSubmenu(id) {
	        const el = document.getElementById(id);
	        if (!el) return;
	        el.classList.toggle('open');
	    }
	    (function initAdminSidebar() {
	        const sidebar = document.getElementById('adminSidebar');
	        if (!sidebar) return;

	        const collapseKey = 'empressAdminSidebarCollapsed';
	        const submenuKey = 'empressAdminOpenSubmenus';
	        const body = document.body;
	        const mobileQuery = window.matchMedia('(max-width: 1180px)');

	        function syncCollapsedState() {
	            if (mobileQuery.matches) {
	                body.classList.remove('admin-sidebar-collapsed');
	                return;
	            }
	            body.classList.toggle('admin-sidebar-collapsed', localStorage.getItem(collapseKey) === '1');
	        }

	        syncCollapsedState();

	        const savedOpen = new Set((localStorage.getItem(submenuKey) || '').split(',').filter(Boolean));
	        const submenuToggles = document.querySelectorAll('[data-submenu-toggle]');
	        submenuToggles.forEach((toggle) => {
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
	                const openIds = Array.from(document.querySelectorAll('.sidebar-submenu.open')).map((el) => el.id).filter(Boolean);
	                localStorage.setItem(submenuKey, openIds.join(','));
	            });
	        });

	        const collapseToggle = document.getElementById('sidebarCollapseToggle');
	        if (collapseToggle) {
	            collapseToggle.addEventListener('click', () => {
	                if (mobileQuery.matches) {
	                    setMobileSidebar(false);
	                    return;
	                }
	                body.classList.toggle('admin-sidebar-collapsed');
	                localStorage.setItem(collapseKey, body.classList.contains('admin-sidebar-collapsed') ? '1' : '0');
	            });
	        }

	        const mobileToggle = document.getElementById('adminMobileMenuToggle');
	        function setMobileSidebar(open) {
	            body.classList.toggle('admin-sidebar-open', open);
	            if (mobileToggle) {
	                mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	            }
	        }

	        if (mobileToggle) {
	            mobileToggle.addEventListener('click', () => {
	                syncCollapsedState();
	                setMobileSidebar(!body.classList.contains('admin-sidebar-open'));
	            });
	        }
	        document.querySelectorAll('[data-admin-sidebar-close]').forEach((el) => {
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
	    })();
	    (function initRealtimeNotifications() {
	        const badgeTargets = document.querySelectorAll('.js-notification-badge');
	        if (!badgeTargets.length) return;

	        function renderCount(count) {
	            badgeTargets.forEach((badge) => {
	                if (count > 0) {
	                    badge.textContent = String(count);
	                    badge.style.display = '';
	                } else {
	                    badge.style.display = 'none';
	                }
	            });
	        }

	        function refreshNotifications() {
	            fetch('<?= url('api_realtime.php') ?>', { credentials: 'same-origin' })
	                .then((response) => response.ok ? response.json() : null)
	                .then((data) => {
	                    if (!data || !data.ok) return;
	                    if (data.notifications) {
	                        renderCount(Number(data.notifications.unread || 0));
	                    }
	                    window.dispatchEvent(new CustomEvent('app:realtime', { detail: data }));
	                })
	                .catch(() => {});
	        }

	        window.setTimeout(refreshNotifications, 1200);
	        window.setInterval(refreshNotifications, 30000);
	    })();
	    </script>
    </body>
    </html>
    <?php
}
?>
