<?php
render_header('Beverage Warehouse Management System', $user);
?>
<style>
    .warehouse-admin-shell .overview-dashboard {
        gap: 1.05rem;
    }
    .warehouse-admin-shell .overview-kpis {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 1rem;
    }
    .warehouse-admin-shell .overview-kpi,
    .warehouse-admin-shell .overview-card,
    .warehouse-admin-shell .warehouse-metrics-card {
        border: 1px solid #dfe7f0;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15,23,42,0.035);
    }
    .warehouse-admin-shell .overview-kpi {
        min-height: 154px;
        padding: 1.1rem 1.05rem;
    }
    .warehouse-admin-shell .overview-kpi-top {
        gap: 0.95rem;
    }
    .warehouse-admin-shell .overview-kpi-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #dbeafe !important;
        color: #2563eb;
        border: 1px solid rgba(37,99,235,0.18);
    }
    .warehouse-admin-shell .overview-kpi:nth-child(2) .overview-kpi-icon { background:#d1fae5 !important; }
    .warehouse-admin-shell .overview-kpi:nth-child(3) .overview-kpi-icon { background:#ede9fe !important; }
    .warehouse-admin-shell .overview-kpi:nth-child(4) .overview-kpi-icon { background:#ffedd5 !important; }
    .warehouse-admin-shell .overview-kpi:nth-child(5) .overview-kpi-icon { background:#fee2e2 !important; }
    .warehouse-admin-shell .overview-kpi:nth-child(1) .overview-kpi-icon { color:#2563eb; }
    .warehouse-admin-shell .overview-kpi:nth-child(2) .overview-kpi-icon { color:#16a34a; }
    .warehouse-admin-shell .overview-kpi:nth-child(3) .overview-kpi-icon { color:#7c3aed; }
    .warehouse-admin-shell .overview-kpi:nth-child(4) .overview-kpi-icon { color:#f97316; }
    .warehouse-admin-shell .overview-kpi:nth-child(5) .overview-kpi-icon { color:#ef4444; }
    .warehouse-admin-shell .overview-kpi small,
    .warehouse-admin-shell .overview-card-head h3,
    .warehouse-admin-shell .warehouse-metrics-head h3,
    .warehouse-admin-shell .warehouse-metric small {
        color: #243757;
        letter-spacing: 0;
    }
    .warehouse-admin-shell .overview-kpi strong {
        font-size: 1.36rem;
        margin-top: 0.28rem;
    }
    .warehouse-admin-shell .spark {
        height: 42px;
        margin-top: 0.85rem;
    }
    .warehouse-admin-shell .warehouse-metrics-card {
        padding: 1rem 1.2rem;
    }
    .warehouse-admin-shell .warehouse-metrics-head {
        display: none;
    }
    .warehouse-admin-shell .warehouse-metrics-grid {
        grid-template-columns: repeat(10, minmax(0, 1fr));
        gap: 0;
    }
    .warehouse-admin-shell .warehouse-metric {
        background: #fff;
        border: 0;
        border-right: 1px solid #dbe4ef;
        border-radius: 0;
        padding: 0.55rem 0.95rem;
    }
    .warehouse-admin-shell .warehouse-metric:last-child {
        border-right: 0;
    }
    .warehouse-admin-shell .overview-row-main {
        grid-template-columns: 1.35fr 1fr 1.25fr;
    }
    .warehouse-admin-shell .overview-row-bottom {
        grid-template-columns: 1.1fr 0.85fr 1.1fr;
    }
    .warehouse-admin-shell .line-chart,
    .warehouse-admin-shell .bar-chart {
        height: 190px;
    }
    .warehouse-admin-shell .zone-map {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        height: auto;
        background: transparent;
        border: 0;
    }
    .warehouse-admin-shell .zone-map:before {
        display: none;
    }
    .warehouse-admin-shell .zone {
        position: static;
        width: auto !important;
        height: 104px !important;
        border-radius: 6px;
        border: 1px solid #dbeafe;
    }
    .warehouse-admin-shell .quick-ops {
        grid-template-columns: repeat(5, 1fr);
    }
    .warehouse-admin-shell .quick-op:nth-child(6) {
        display: none;
    }
    .warehouse-admin-shell .warehouse-page {
        gap: 1.05rem;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-hero {
        border-color: #dfe7f0;
        border-radius: 10px;
        padding: 1rem 1.1rem;
        box-shadow: 0 10px 24px rgba(15,23,42,0.035);
    }
    .warehouse-admin-shell .warehouse-page .warehouse-hero-icon {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #dbeafe;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-hero h2 {
        font-size: 1.2rem;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-hero p {
        font-size: 0.78rem;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-select,
    .warehouse-admin-shell .warehouse-page .warehouse-register-btn {
        height: 38px;
        border-radius: 8px;
        padding: 0 0.85rem;
        font-size: 0.76rem;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-metric-row {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        border-color: #dfe7f0;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15,23,42,0.035);
    }
    .warehouse-admin-shell .warehouse-page .warehouse-stat {
        padding: 0.85rem;
    }
    .warehouse-admin-shell .metric-link {
        cursor: pointer;
        transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }
    .warehouse-admin-shell .metric-link:hover {
        border-color: #2563eb;
        box-shadow: 0 12px 24px rgba(37,99,235,.12);
        transform: translateY(-1px);
    }
    .warehouse-admin-shell .metric-link.active {
        border-color: #2563eb;
        box-shadow: 0 12px 24px rgba(37,99,235,.14);
    }
    .warehouse-admin-shell .warehouse-page .warehouse-stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 50%;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-stat strong {
        font-size: 1.05rem;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-grid-4,
    .warehouse-admin-shell .warehouse-page > .warehouse-card:nth-of-type(3) {
        display: none;
    }
    .warehouse-admin-shell .warehouse-page > .warehouse-card {
        border-color: #dfe7f0;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15,23,42,0.035);
    }
    .warehouse-admin-shell .warehouse-page .warehouse-card-head h3 {
        font-size: 0.95rem !important;
        color: #0f172a;
    }
    .warehouse-admin-shell .warehouse-page .inventory-count-badge {
        background: #eef4ff;
        color: #1d4ed8;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        padding: 0.24rem 0.55rem;
        font-size: 0.68rem;
    }
    .warehouse-admin-shell .warehouse-page .inventory-search-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
    }
    .warehouse-admin-shell .warehouse-page .inventory-search-box {
        max-width: none;
        min-width: 0;
    }
    .warehouse-admin-shell .warehouse-page .inventory-search-box input,
    .warehouse-admin-shell .warehouse-page .inventory-filter-select {
        height: 38px;
        border-color: #dbe4ef;
        border-radius: 8px;
        font-size: 0.76rem;
        background: #ffffff;
    }
    .warehouse-admin-shell .warehouse-page .details-btn .wa-svg-icon,
    .warehouse-admin-shell .warehouse-page .inventory-reset-btn .wa-svg-icon,
    .warehouse-admin-shell .warehouse-page .warehouse-register-btn .wa-svg-icon,
    .warehouse-admin-shell .warehouse-page .fefo-good .wa-svg-icon,
    .warehouse-admin-shell .warehouse-page .fefo-warn .wa-svg-icon {
        width: 15px;
        min-width: 15px;
        height: 15px;
    }
    .warehouse-admin-shell .warehouse-page .details-btn .wa-svg-icon svg,
    .warehouse-admin-shell .warehouse-page .inventory-reset-btn .wa-svg-icon svg,
    .warehouse-admin-shell .warehouse-page .warehouse-register-btn .wa-svg-icon svg,
    .warehouse-admin-shell .warehouse-page .fefo-good .wa-svg-icon svg,
    .warehouse-admin-shell .warehouse-page .fefo-warn .wa-svg-icon svg {
        width: 15px;
        height: 15px;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-stat-icon .wa-svg-icon {
        width: 21px;
        min-width: 21px;
        height: 21px;
    }
    .warehouse-admin-shell .warehouse-page .warehouse-stat-icon .wa-svg-icon svg {
        width: 21px;
        height: 21px;
    }
    .warehouse-admin-shell .warehouse-page .inventory-search-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
    }
    .warehouse-admin-shell .warehouse-page .inventory-search-icon .wa-svg-icon,
    .warehouse-admin-shell .warehouse-page .inventory-search-icon .wa-svg-icon svg {
        width: 17px;
        min-width: 17px;
        height: 17px;
    }
    .warehouse-admin-shell .warehouse-page .inventory-filters-group {
        justify-content: flex-end;
        gap: 0.45rem;
    }
    .warehouse-admin-shell .warehouse-page .inventory-filter-select {
        max-width: 168px;
        padding: 0 0.62rem;
    }
    .warehouse-admin-shell .warehouse-page .inventory-reset-btn,
    .warehouse-admin-shell .warehouse-page .details-btn {
        min-height: 34px;
        border-radius: 7px;
        padding: 0.4rem 0.62rem;
        font-size: 0.7rem;
        line-height: 1;
        white-space: nowrap;
    }
    .warehouse-admin-shell .warehouse-page .details-btn.icon-only {
        width: 34px;
        min-width: 34px;
        height: 34px;
        padding: 0;
        justify-content: center;
        gap: 0;
    }
    .warehouse-admin-shell .warehouse-page .details-btn.icon-only .wa-svg-icon {
        margin: 0;
    }
    .warehouse-admin-shell .warehouse-page .inventory-table-wrap {
        border-color: #dfe7f0;
        border-radius: 9px;
        overflow: auto;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
    }
    .warehouse-admin-shell .warehouse-page .top-table {
        min-width: 1180px;
        font-size: 0.76rem;
        background: #ffffff;
    }
    .warehouse-admin-shell .warehouse-page .top-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8fafc;
        color: #64748b;
        padding: 0.7rem 0.65rem;
        font-size: 0.64rem;
        border-bottom: 1px solid #dfe7f0;
        letter-spacing: 0;
    }
    .warehouse-admin-shell .warehouse-page .top-table td {
        padding: 0.64rem 0.65rem;
        color: #334155;
        border-bottom: 1px solid #eef2f7;
    }
    .warehouse-admin-shell .warehouse-page .top-table tbody tr:hover {
        background: #f8fbff;
    }
    .warehouse-admin-shell .warehouse-page .top-table td:first-child {
        min-width: 260px;
    }
    .warehouse-admin-shell .warehouse-page .product-row-name {
        display: block;
        max-width: 210px;
        color: #0f172a;
        font-size: 0.8rem;
        line-height: 1.25;
    }
    .warehouse-admin-shell .warehouse-page .top-table-img {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        object-fit: contain;
        padding: 0.15rem;
        background: #ffffff;
    }
    .warehouse-admin-shell .warehouse-page .top-table td:last-child > div {
        width: 224px;
        flex-wrap: wrap !important;
        justify-content: flex-end;
    }
    .warehouse-admin-shell .warehouse-page .fefo-good,
    .warehouse-admin-shell .warehouse-page .fefo-warn,
    .warehouse-admin-shell .warehouse-page .status-pill {
        border-radius: 999px;
        padding: 0.2rem 0.5rem;
        font-size: 0.63rem !important;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        line-height: 1;
    }
    .warehouse-admin-shell .warehouse-page #quick_available_sort_btn {
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    @media (max-width: 1360px) {
        .warehouse-admin-shell .overview-kpis {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
        }
        .warehouse-admin-shell .warehouse-metrics-grid {
            grid-template-columns: repeat(auto-fit, minmax(128px, 1fr)) !important;
        }
        .warehouse-admin-shell .overview-row-main,
        .warehouse-admin-shell .overview-row-3,
        .warehouse-admin-shell .overview-row-bottom,
        .warehouse-admin-shell .overview-chart-grid,
        .warehouse-admin-shell .warehouse-grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)) !important;
        }
    }

    @media (max-width: 1180px) {
        body.has-admin-sidebar {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        .warehouse-admin-shell.bev-app-layout {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) !important;
            width: 100% !important;
            max-width: 100% !important;
            height: 100dvh;
            min-height: 100dvh;
            overflow: hidden;
        }
        .warehouse-admin-shell .bev-sidebar {
            position: fixed !important;
            top: 0;
            bottom: 0;
            left: 0;
            width: min(282px, 86vw) !important;
            z-index: 80;
            transform: translateX(-104%);
        }
        .admin-sidebar-open .warehouse-admin-shell .bev-sidebar {
            transform: translateX(0);
        }
        .warehouse-admin-shell .bev-main-content {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            height: 100dvh;
            margin-left: 0 !important;
            flex: none !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }
        .warehouse-admin-shell .bev-topbar,
        .warehouse-admin-shell .bev-body,
        .warehouse-admin-shell .overview-dashboard,
        .warehouse-admin-shell .warehouse-page,
        .warehouse-admin-shell .tab-module-wrap,
        .warehouse-admin-shell .overview-card,
        .warehouse-admin-shell .warehouse-card,
        .warehouse-admin-shell .warehouse-metrics-card {
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        .warehouse-admin-shell .overview-kpis,
        .warehouse-admin-shell .warehouse-metrics-grid,
        .warehouse-admin-shell .overview-row-main,
        .warehouse-admin-shell .overview-row-3,
        .warehouse-admin-shell .overview-row-bottom {
            grid-template-columns: 1fr 1fr;
        }
        .warehouse-admin-shell .warehouse-search {
            width: 100%;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-metric-row {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .warehouse-admin-shell .warehouse-page .inventory-search-toolbar {
            grid-template-columns: 1fr;
        }
        .warehouse-admin-shell .warehouse-page .inventory-filters-group {
            justify-content: flex-start;
        }
    }
    @media (max-width: 760px) {
        .warehouse-admin-shell .bev-topbar,
        .warehouse-admin-shell .bev-top-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .warehouse-admin-shell .overview-kpis,
        .warehouse-admin-shell .warehouse-metrics-grid,
        .warehouse-admin-shell .overview-row-main,
        .warehouse-admin-shell .overview-row-3,
        .warehouse-admin-shell .overview-row-bottom,
        .warehouse-admin-shell .quick-ops,
        .warehouse-admin-shell .zone-map {
            grid-template-columns: 1fr;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-hero,
        .warehouse-admin-shell .warehouse-page .warehouse-hero-actions {
            align-items: stretch;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-metric-row {
            grid-template-columns: 1fr;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-stat {
            border-right: 0;
            border-bottom: 1px solid #e8eef7;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-stat:last-child {
            border-bottom: 0;
        }
        .warehouse-admin-shell .warehouse-page .inventory-filter-select,
        .warehouse-admin-shell .warehouse-page .details-btn,
        .warehouse-admin-shell .warehouse-page #quick_available_sort_btn {
            width: 100%;
            max-width: none;
            justify-content: center;
        }
        .warehouse-admin-shell .warehouse-page .details-btn.icon-only {
            width: 34px;
            max-width: 34px;
        }
    }

    /* 5 Top KPI Cards Row matching mockup */
    .kpi-cards-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
    .kpi-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; }
    .kpi-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
    .kpi-icon-box { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .kpi-card-title { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-card-value { font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.2rem; }
    .kpi-card-sub { font-size: 0.7rem; font-weight: 700; color: #059669; }

    /* 3 Columns Row 1 */
    .mid-row-1 { display: grid; grid-template-columns: 2fr 1.2fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
    .dash-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
    .dash-box-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .dash-box-head h3 { font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 800; color: #0f172a; }

    /* Category Progress Bars */
    .prog-item { margin-bottom: 0.75rem; }
    .prog-item-head { display: flex; justify-content: space-between; font-size: 0.78rem; font-weight: 700; margin-bottom: 0.25rem; }
    .prog-bar-track { background: #f1f5f9; border-radius: 10px; height: 8px; overflow: hidden; }
    .prog-bar-fill { height: 100%; border-radius: 10px; }

    /* 3 Columns Row 2 */
    .mid-row-2 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
    .expiry-item { display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px solid #f1f5f9; }
    .expiry-pill { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; }

    /* Driver Table */
    .driver-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
    .driver-table th { text-align: left; color: #64748b; font-weight: 700; padding: 0.4rem 0; border-bottom: 1px solid #e2e8f0; }
    .driver-table td { padding: 0.45rem 0; border-bottom: 1px solid #f1f5f9; }

    /* AI Insights Card */
    .insight-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.65rem; margin-bottom: 0.5rem; display: flex; gap: 0.6rem; align-items: start; }
    .insight-item-title { font-size: 0.8rem; font-weight: 700; color: #0f172a; }
    .insight-item-sub { font-size: 0.72rem; color: #64748b; margin-top: 0.1rem; }

    /* Bottom Row */
    .bottom-row { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; }
    .quick-ops-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.6rem; }
    .quick-op-btn { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 0.4rem; text-align: center; font-size: 0.75rem; font-weight: 700; color: #334155; cursor: pointer; transition: all 0.15s ease; }
    .quick-op-btn:hover { border-color: #1d4ed8; background: #f0f9ff; color: #1d4ed8; transform: translateY(-2px); }
    .quick-op-btn span { font-size: 1.5rem; display: block; margin-bottom: 0.35rem; }

    .feed-item { display: flex; gap: 0.6rem; font-size: 0.76rem; border-bottom: 1px solid #f1f5f9; padding: 0.5rem 0; }
    .feed-time { color: #94a3b8; font-weight: 600; width: 60px; flex-shrink: 0; }

    .overview-dashboard { display: grid; gap: 1rem; }
    .overview-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1rem; }
    .warehouse-metrics-card { background:#fff; border:1px solid #e8eef7; border-radius:6px; padding:0.95rem 1rem; box-shadow:0 8px 20px rgba(15,23,42,0.03); }
    .warehouse-metrics-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:0.75rem; }
    .warehouse-metrics-head h3 { margin:0; color:#0f172a; font-family:'Outfit',sans-serif; font-size:0.82rem; font-weight:800; text-transform:uppercase; }
    .warehouse-metrics-head span { color:#64748b; font-size:0.68rem; font-weight:700; }
    .warehouse-metrics-grid { display:grid; grid-template-columns:repeat(10, minmax(0, 1fr)); gap:0.55rem; }
    .warehouse-metric { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:0.65rem; min-width:0; }
    .warehouse-metric small { display:block; color:#64748b; font-size:0.58rem; line-height:1.15; font-weight:900; text-transform:uppercase; min-height:1.35rem; }
    .warehouse-metric strong { display:block; color:#0f172a; font-family:'Outfit',sans-serif; font-size:0.98rem; line-height:1.1; margin-top:0.35rem; white-space:nowrap; }
    .warehouse-metric.good strong { color:#15803d; }
    .warehouse-metric.warn strong { color:#d97706; }
    .warehouse-metric.danger strong { color:#dc2626; }
    .overview-kpi { min-height: 118px; background:#fff; border:1px solid #e8eef7; border-radius:6px; padding:1rem; box-shadow:0 8px 20px rgba(15,23,42,0.035); overflow:hidden; }
    .overview-kpi-top { display:flex; align-items:flex-start; gap:0.8rem; }
    .overview-kpi-icon { width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.15rem; flex:0 0 auto; overflow:hidden; }
    .overview-kpi-icon img, .quick-op-icon img { width:100%; height:100%; object-fit:cover; display:block; }
    .overview-kpi-icon .wa-svg-icon,
    .quick-op-icon .wa-svg-icon { width:24px; min-width:24px; height:24px; }
    .overview-kpi-icon .wa-svg-icon svg,
    .quick-op-icon .wa-svg-icon svg { width:24px; height:24px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .overview-kpi small { display:block; color:#64748b; font-size:0.66rem; font-weight:800; text-transform:uppercase; margin-bottom:0.3rem; }
    .overview-kpi strong { display:block; color:#0f172a; font-family:'Outfit',sans-serif; font-size:1.35rem; line-height:1; }
    .overview-kpi span { display:block; color:#64748b; font-size:0.68rem; font-weight:700; margin-top:0.25rem; }
    .overview-kpi .good { color:#16a34a; }
    .overview-kpi .danger { color:#ef4444; }
    .spark { width:100%; height:34px; margin-top:0.6rem; display:block; }
    .overview-row-main { display:grid; grid-template-columns: 2fr 1.1fr; gap:1rem; }
    .overview-row-3 { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem; }
    .overview-row-bottom { display:grid; grid-template-columns: 1.15fr 1fr 1.15fr; gap:1rem; }
    .overview-card { background:#fff; border:1px solid #e8eef7; border-radius:6px; padding:1rem; box-shadow:0 8px 20px rgba(15,23,42,0.03); min-width:0; }
    .overview-card-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:0.85rem; }
    .overview-card-head h3 { margin:0; color:#0f172a; font-family:'Outfit',sans-serif; font-size:0.82rem; font-weight:800; text-transform:uppercase; }
    .overview-card-head a, .overview-card-head button { color:#2563eb; background:#fff; border:1px solid #e2e8f0; border-radius:5px; padding:0.25rem 0.45rem; font-size:0.68rem; font-weight:800; text-decoration:none; }
    .overview-chart-grid { display:grid; grid-template-columns:1.25fr 1fr; gap:1rem; align-items:end; }
    .chart-label { font-size:0.68rem; color:#64748b; font-weight:800; margin-bottom:0.45rem; }
    .legend { display:flex; gap:0.8rem; flex-wrap:wrap; font-size:0.66rem; color:#64748b; font-weight:800; margin-bottom:0.4rem; }
    .line-chart { width:100%; height:178px; background:linear-gradient(180deg,#fff 0,#f8fbff 100%); border-bottom:1px solid #eef2f7; }
    .bar-chart { width:100%; height:178px; }
    .axis-row { display:flex; justify-content:space-between; color:#94a3b8; font-size:0.62rem; font-weight:700; margin-top:0.35rem; }
    .category-row { display:grid; grid-template-columns:1fr 58px 1.1fr 58px; gap:0.75rem; align-items:center; font-size:0.72rem; padding:0.55rem 0; border-bottom:1px solid #f1f5f9; }
    .category-row:last-child { border-bottom:0; }
    .progress-track { height:7px; background:#eef2f7; border-radius:99px; overflow:hidden; }
    .progress-fill { height:100%; border-radius:99px; }
    .status-pill { font-size:0.62rem; font-weight:800; border-radius:99px; padding:0.16rem 0.45rem; justify-self:start; }
    .status-high { color:#15803d; background:#dcfce7; }
    .status-mid { color:#a16207; background:#fef3c7; }
    .status-low { color:#dc2626; background:#fee2e2; }
    .zone-map { position:relative; height:154px; background:#f8fafc; border:1px solid #e2e8f0; overflow:hidden; border-radius:4px; }
    .zone-map:before { content:""; position:absolute; inset:0; background:linear-gradient(90deg,#e2e8f0 1px,transparent 1px),linear-gradient(#e2e8f0 1px,transparent 1px); background-size:32px 28px; opacity:.55; }
    .zone { position:absolute; border:1px solid rgba(15,23,42,.08); border-radius:2px; display:flex; align-items:center; justify-content:center; flex-direction:column; font-size:0.68rem; font-weight:800; color:#0f172a; text-decoration:none; transition:border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
    .zone:hover { border-color:#2563eb; box-shadow:0 12px 22px rgba(37,99,235,.14); transform:translateY(-1px); }
    .zone strong { font-size:1rem; }
    .expiry-list, .insight-list, .activity-list { display:grid; gap:0.55rem; }
    .expiry-row { display:grid; grid-template-columns:34px 1fr auto; gap:0.65rem; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:0.55rem; }
    .expiry-row:last-child { border-bottom:0; padding-bottom:0; }
    .product-thumb { width:30px; height:38px; object-fit:cover; border-radius:5px; border:1px solid #e2e8f0; background:#fff; }
    .product-thumb-placeholder { display:inline-flex; align-items:center; justify-content:center; color:#94a3b8; font-weight:800; }
    .row-title { font-size:0.72rem; font-weight:800; color:#0f172a; }
    .row-sub { font-size:0.64rem; color:#64748b; margin-top:0.12rem; }
    .expiry-warning { color:#d97706; font-size:0.64rem; font-weight:800; white-space:nowrap; }
    .qty { color:#1e293b; font-size:0.74rem; font-weight:800; text-align:right; }
    .driver-table { width:100%; border-collapse:collapse; font-size:0.7rem; }
    .driver-table th { color:#64748b; font-weight:800; text-align:left; border-bottom:1px solid #e2e8f0; padding:0.35rem 0.25rem; }
    .driver-table td { border-bottom:1px solid #f1f5f9; padding:0.42rem 0.25rem; }
    .driver-avatar { width:20px; height:20px; border-radius:50%; background:#fde68a; color:#7c2d12; display:inline-flex; align-items:center; justify-content:center; font-size:0.62rem; font-weight:900; margin-right:0.35rem; }
    .quick-ops { display:grid; grid-template-columns:repeat(6,1fr); gap:0.65rem; }
    .quick-op { border:1px solid #eef2f7; background:#fff; border-radius:6px; min-height:78px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0.4rem; color:#334155; font-size:0.62rem; font-weight:800; cursor:pointer; }
    .quick-op-icon { width:32px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; overflow:hidden; }
    .insight-row, .activity-row { display:grid; grid-template-columns:auto 1fr auto; gap:0.6rem; align-items:start; padding-bottom:0.5rem; border-bottom:1px solid #f1f5f9; }
    .insight-row:last-child, .activity-row:last-child { border-bottom:0; padding-bottom:0; }
    .mini-badge { background:#eff6ff; color:#2563eb; border-radius:4px; padding:0.12rem 0.35rem; font-size:0.58rem; font-weight:900; }
    .footer-status { display:flex; justify-content:space-between; align-items:center; color:#64748b; font-size:0.68rem; padding:0.4rem 0.1rem 0; }
    @media (max-width: 1180px) {
        .overview-kpis { grid-template-columns: repeat(2, minmax(0,1fr)); }
        .warehouse-metrics-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
        .overview-row-main, .overview-row-3, .overview-row-bottom, .overview-chart-grid { grid-template-columns:1fr; }
        .quick-ops { grid-template-columns:repeat(3,1fr); }
    }

    @media (max-width: 1024px) {
        .warehouse-admin-shell .overview-kpis {
            grid-template-columns: repeat(auto-fit, minmax(168px, 1fr)) !important;
            gap: 0.8rem;
        }
        .warehouse-admin-shell .warehouse-metrics-grid {
            grid-template-columns: repeat(auto-fit, minmax(118px, 1fr)) !important;
            gap: 0.55rem;
        }
        .warehouse-admin-shell .overview-row-main,
        .warehouse-admin-shell .overview-row-3,
        .warehouse-admin-shell .overview-row-bottom,
        .warehouse-admin-shell .overview-chart-grid {
            grid-template-columns: 1fr !important;
        }
        .warehouse-admin-shell .overview-card {
            min-width: 0;
        }
        .warehouse-admin-shell .line-chart,
        .warehouse-admin-shell .bar-chart {
            height: 165px;
        }
        .warehouse-admin-shell .quick-ops {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 820px) {
        .warehouse-admin-shell .overview-kpis {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) !important;
        }
        .warehouse-admin-shell .warehouse-metrics-grid {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)) !important;
        }
        .warehouse-admin-shell .warehouse-metric {
            border: 1px solid #e8eef7 !important;
            border-radius: 9px !important;
            background: #f8fafc;
        }
        .warehouse-admin-shell .zone-map {
            grid-template-columns: 1fr 1fr;
        }
        .warehouse-admin-shell .category-row {
            grid-template-columns: 1fr 60px 1fr auto;
        }
        .warehouse-admin-shell .warehouse-page .inventory-table-wrap {
            border: 0;
            background: transparent;
            box-shadow: none;
            overflow: visible;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable {
            min-width: 0 !important;
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
            display: block;
            background: transparent;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable thead {
            display: none;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable tbody {
            display: grid;
            gap: 0.72rem;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable tr.inventory-row {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(116px, 0.7fr) minmax(142px, 0.9fr) auto;
            align-items: center;
            gap: 0.7rem;
            width: 100%;
            padding: 0.68rem 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.045);
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td {
            padding: 0 !important;
            border-bottom: 0;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(2),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(4),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(5),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(6),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(8) {
            display: none;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(1) {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 0;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(3) {
            display: none;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(7),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(8),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9) {
            min-width: 0;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(5) {
            display: block;
            grid-column: 2;
            grid-row: 1 / span 2;
            text-align: center;
            color: #059669;
            font-weight: 900;
            font-size: 0.88rem;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(5)::after {
            content: "Available Stock";
            display: block;
            margin-top: 0.18rem;
            color: #475569;
            font-size: 0.6rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(7) {
            display: block;
            grid-column: 3;
            grid-row: 1;
            text-align: center;
            color: #0f172a;
            font-weight: 900;
            font-size: 0.76rem;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(7)::after {
            content: "Stock Value";
            display: block;
            margin-top: 0.18rem;
            color: #475569;
            font-size: 0.6rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(8) {
            display: block;
            grid-column: 3;
            grid-row: 2;
            justify-self: center;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9) {
            grid-column: 4;
            grid-row: 1 / span 2;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9) > div {
            width: auto !important;
            display: grid !important;
            grid-template-columns: repeat(3, 40px);
            gap: 0.42rem !important;
            justify-content: end !important;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable .details-btn.icon-only {
            width: 40px;
            min-width: 40px;
            max-width: 40px;
            height: 40px;
            border-radius: 9px;
        }
        .warehouse-admin-shell .warehouse-page .product-row-name {
            max-width: none;
            font-size: 0.86rem;
            line-height: 1.18;
        }
        .warehouse-admin-shell .warehouse-page .top-table-img {
            width: 52px;
            height: 52px;
            border-radius: 10px;
        }
        .warehouse-admin-shell .warehouse-page .mobile-product-category {
            display: inline-flex;
            margin-top: 0.28rem;
            font-size: 0.58rem !important;
            padding: 0.14rem 0.42rem;
        }
        .warehouse-admin-shell .warehouse-page .mobile-product-sku,
        .warehouse-admin-shell .warehouse-page .mobile-product-pack {
            line-height: 1.25;
        }
    }

    @media (max-width: 640px) {
        .warehouse-admin-shell .overview-dashboard {
            gap: 0.78rem;
        }
        .warehouse-admin-shell .overview-kpis {
            grid-template-columns: repeat(auto-fit, minmax(142px, 1fr)) !important;
            gap: 0.62rem !important;
        }
        .warehouse-admin-shell .overview-kpi {
            min-height: 112px;
            padding: 0.72rem !important;
            border-radius: 10px !important;
        }
        .warehouse-admin-shell .overview-kpi-top {
            gap: 0.62rem;
            align-items: center;
        }
        .warehouse-admin-shell .overview-kpi-icon {
            width: 38px !important;
            height: 38px !important;
            min-width: 38px;
            border-radius: 10px !important;
        }
        .warehouse-admin-shell .overview-kpi-icon .wa-svg-icon,
        .warehouse-admin-shell .overview-kpi-icon .wa-svg-icon svg {
            width: 20px;
            min-width: 20px;
            height: 20px;
        }
        .warehouse-admin-shell .overview-kpi small {
            font-size: 0.58rem !important;
            line-height: 1.1;
            margin-bottom: 0.18rem;
        }
        .warehouse-admin-shell .overview-kpi strong {
            font-size: 1.08rem !important;
            line-height: 1.05;
            white-space: normal;
            word-break: break-word;
        }
        .warehouse-admin-shell .overview-kpi span {
            font-size: 0.62rem;
            line-height: 1.2;
        }
        .warehouse-admin-shell .spark {
            height: 24px;
            margin-top: 0.48rem;
        }
        .warehouse-admin-shell .warehouse-metrics-card {
            padding: 0.72rem !important;
            border-radius: 10px !important;
        }
        .warehouse-admin-shell .warehouse-metrics-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 0.55rem !important;
        }
        .warehouse-admin-shell .warehouse-metric {
            border: 1px solid #e8eef7 !important;
            border-radius: 9px !important;
            padding: 0.62rem !important;
            background: #f8fafc !important;
        }
        .warehouse-admin-shell .warehouse-metric small {
            min-height: 0;
            font-size: 0.56rem !important;
            line-height: 1.15;
        }
        .warehouse-admin-shell .warehouse-metric strong {
            font-size: 0.94rem !important;
            white-space: normal;
            word-break: break-word;
        }
        .warehouse-admin-shell .overview-row-main,
        .warehouse-admin-shell .overview-row-3,
        .warehouse-admin-shell .overview-row-bottom,
        .warehouse-admin-shell .overview-chart-grid {
            grid-template-columns: 1fr !important;
            gap: 0.78rem !important;
        }
        .warehouse-admin-shell .overview-card {
            padding: 0.82rem !important;
            border-radius: 10px !important;
        }
        .warehouse-admin-shell .overview-card-head {
            margin-bottom: 0.68rem;
            align-items: center;
        }
        .warehouse-admin-shell .overview-card-head h3 {
            font-size: 0.84rem !important;
            line-height: 1.2;
        }
        .warehouse-admin-shell .overview-card-head a,
        .warehouse-admin-shell .overview-card-head button {
            min-height: 32px;
            padding: 0.34rem 0.5rem;
            font-size: 0.64rem;
            border-radius: 8px;
            white-space: nowrap;
        }
        .warehouse-admin-shell .legend {
            gap: 0.5rem;
            font-size: 0.6rem;
        }
        .warehouse-admin-shell .line-chart,
        .warehouse-admin-shell .bar-chart {
            height: 132px !important;
        }
        .warehouse-admin-shell .axis-row {
            font-size: 0.52rem;
            gap: 0.35rem;
            overflow: hidden;
        }
        .warehouse-admin-shell .category-row {
            grid-template-columns: 1fr auto;
            gap: 0.45rem 0.65rem;
            font-size: 0.68rem;
            padding: 0.55rem 0;
        }
        .warehouse-admin-shell .category-row .progress-track {
            grid-column: 1 / -1;
        }
        .warehouse-admin-shell .category-row .status-pill {
            justify-self: end;
        }
        .warehouse-admin-shell .zone-map {
            grid-template-columns: 1fr !important;
            min-height: auto !important;
            gap: 0.58rem;
        }
        .warehouse-admin-shell .zone {
            min-height: 82px;
            height: auto !important;
            border-radius: 10px;
            padding: 0.65rem;
        }
        .warehouse-admin-shell .expiry-row,
        .warehouse-admin-shell .insight-row,
        .warehouse-admin-shell .activity-row {
            grid-template-columns: auto 1fr;
            gap: 0.55rem;
        }
        .warehouse-admin-shell .expiry-row > div:last-child,
        .warehouse-admin-shell .activity-row > .row-sub:last-child {
            grid-column: 2;
            justify-self: start;
            text-align: left;
        }
        .warehouse-admin-shell .driver-table {
            min-width: 520px;
        }
        .warehouse-admin-shell .quick-ops {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 0.58rem !important;
        }
        .warehouse-admin-shell .quick-op {
            min-height: 76px;
            border-radius: 10px;
            font-size: 0.68rem !important;
            line-height: 1.15;
            padding: 0.65rem 0.45rem !important;
        }
        .warehouse-admin-shell .quick-op:nth-child(6) {
            display: flex;
        }
        .warehouse-admin-shell .quick-op-icon {
            width: 34px !important;
            height: 34px !important;
            border-radius: 9px !important;
        }
        .warehouse-admin-shell .footer-status {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.32rem;
            padding-bottom: 0.7rem;
            font-size: 0.62rem;
        }
        .warehouse-admin-shell .warehouse-card-head {
            align-items: flex-start;
            flex-direction: column;
        }
        .warehouse-admin-shell .warehouse-card-head a {
            align-self: flex-start;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-grid-4 {
            display: none !important;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-hero {
            padding: 0.92rem !important;
            align-items: flex-start;
            flex-direction: row !important;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-hero-title {
            align-items: center;
            gap: 0.78rem;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-hero-icon {
            width: 46px !important;
            height: 46px !important;
            border-radius: 50% !important;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-hero h2 {
            font-size: 1rem !important;
            line-height: 1.18;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-hero p {
            display: block;
            font-size: 0.74rem !important;
            line-height: 1.35;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-hero-actions {
            display: none;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-metric-row {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 0.62rem;
            padding: 0;
            border: 0;
            box-shadow: none;
            background: transparent;
            overflow: visible;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-stat {
            display: block;
            min-height: 124px;
            padding: 0.86rem !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.035);
        }
        .warehouse-admin-shell .warehouse-page .warehouse-stat-icon {
            width: 42px !important;
            height: 42px !important;
            border-radius: 50% !important;
            margin-bottom: 0.62rem;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-stat small {
            font-size: 0.58rem;
            line-height: 1.15;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-stat strong {
            font-size: 1.05rem !important;
            margin-top: 0.26rem;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-stat span {
            font-size: 0.68rem;
            line-height: 1.28;
        }
        .warehouse-admin-shell .warehouse-page .zones-row {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 0.72rem;
        }
        .warehouse-admin-shell .warehouse-page .zone-card {
            padding: 0.72rem;
            border-radius: 11px;
        }
        .warehouse-admin-shell .warehouse-page .zone-card-top {
            gap: 0.62rem;
        }
        .warehouse-admin-shell .warehouse-page .zone-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
        }
        .warehouse-admin-shell .warehouse-page .zone-card strong {
            font-size: 0.76rem;
            line-height: 1.2;
        }
        .warehouse-admin-shell .warehouse-page .zone-card span {
            font-size: 0.66rem;
            line-height: 1.2;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-card-head[style] {
            align-items: flex-start !important;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-card-head[style] > div:first-child {
            width: 100%;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-card-head[style] > div:last-child {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            width: 100%;
            gap: 0.48rem !important;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-card-head[style] > div:last-child .details-btn {
            min-height: 42px;
            justify-content: center;
            padding: 0.42rem !important;
        }
        .warehouse-admin-shell .warehouse-page .warehouse-card-head[style] > div:last-child .details-btn:nth-child(3) {
            display: none;
        }
    }

    @media (max-width: 540px) {
        .warehouse-admin-shell .overview-kpis {
            grid-template-columns: 1fr !important;
        }
        .warehouse-admin-shell .overview-kpi {
            min-height: 96px;
        }
        .warehouse-admin-shell .overview-kpi-top {
            align-items: center;
        }
        .warehouse-admin-shell .warehouse-metrics-grid {
            grid-template-columns: 1fr !important;
        }
        .warehouse-admin-shell .category-row {
            grid-template-columns: 1fr auto;
        }
        .warehouse-admin-shell .category-row .progress-track {
            grid-column: 1 / -1;
        }
        .warehouse-admin-shell .quick-ops {
            grid-template-columns: 1fr 1fr !important;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable tr.inventory-row {
            grid-template-columns: 1fr;
            gap: 0.45rem;
            padding: 0.82rem;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(1),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(4),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(5),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(6),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(7),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(8) {
            display: flex;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(2),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(3) {
            display: none !important;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            width: 100%;
            padding: 0.42rem 0 !important;
            border-bottom: 1px solid #eef2f7;
            text-align: right;
            font-size: 0.76rem;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td::before {
            color: #64748b;
            font-size: 0.6rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            text-align: left;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(1) {
            justify-content: flex-start;
            text-align: left;
            border-bottom: 1px solid #eef2f7;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(1)::before {
            display: none;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(2)::before { content: "SKU"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(3)::before { content: "Category"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(4)::before { content: "Packaging"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(5)::before { content: "Available Stock"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(6)::before { content: "Unit Cost"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(7)::before { content: "Stock Value"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(8)::before { content: "FEFO Status"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9)::before { content: "Actions"; }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(4),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(5),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(6),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(7),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(8),
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9) {
            grid-column: auto;
            grid-row: auto;
            margin: 0;
            justify-self: stretch;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(5)::after,
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(7)::after {
            display: none;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9) {
            display: block !important;
            border-bottom: 0;
            text-align: left;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9)::before {
            display: block;
            margin-bottom: 0.45rem;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable td:nth-child(9) > div {
            width: 100% !important;
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            justify-content: stretch !important;
        }
        .warehouse-admin-shell .warehouse-page #topInventoryTable .details-btn.icon-only {
            width: 100%;
            min-width: 0;
            max-width: none;
        }
    }

    @media (max-width: 390px) {
        .warehouse-admin-shell .overview-kpis,
        .warehouse-admin-shell .warehouse-metrics-grid,
        .warehouse-admin-shell .quick-ops {
            grid-template-columns: 1fr !important;
        }
        .warehouse-admin-shell .overview-kpi {
            min-height: 96px;
        }
    }
</style>
<style>
    .inventory-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .inventory-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 0.85rem;
    }
    .inventory-header h2 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .inventory-header p {
        font-size: 0.82rem;
        color: #64748b;
        margin: 0.2rem 0 0;
    }
    .inventory-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(104px, 1fr));
        gap: 0.6rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.85rem;
        margin-bottom: 1.25rem;
        text-align: center;
    }
    .inventory-summary-grid small {
        color: #64748b;
        font-size: 0.65rem;
        font-weight: 700;
        display: block;
    }
    .inventory-summary-grid strong {
        font-size: 0.95rem;
        color: #0f172a;
        display: block;
        margin-top: 0.15rem;
    }
    .inventory-metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .inventory-metric {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 1rem;
        min-width: 0;
    }
    .inventory-metric small {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        display: block;
    }
    .inventory-metric h3 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.25rem;
        font-weight: 800;
        margin: 0.2rem 0;
        line-height: 1.15;
    }
    .inventory-metric span {
        font-size: 0.72rem;
        font-weight: 700;
        display: block;
        line-height: 1.35;
    }
    .inventory-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.85rem;
        margin-bottom: 0.85rem;
    }
    .inventory-upload-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.75rem;
    }
    .inventory-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        background: #ffffff;
    }
    .inventory-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .inventory-table th {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        color: #64748b;
        font-size: 0.72rem;
        text-transform: uppercase;
        padding: 0.7rem;
        white-space: nowrap;
    }
    .inventory-table td {
        padding: 0.7rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .inventory-table tbody tr:last-child td {
        border-bottom: none;
    }
    .inventory-table img {
        width: 42px;
        height: 42px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
    }
    .inventory-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.75rem;
    }
    .inventory-title-row h3 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .warehouse-page { display:grid; gap:1rem; }
    .warehouse-hero { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; box-shadow:0 10px 28px rgba(15,23,42,.035); }
    .warehouse-hero-title { display:flex; align-items:center; gap:1rem; }
    .warehouse-hero-icon { width:56px; height:56px; border-radius:10px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .warehouse-hero-icon img { width:44px; height:44px; object-fit:cover; }
    .warehouse-hero h2 { margin:0; font-family:'Outfit',sans-serif; font-size:1.6rem; color:#0f172a; }
    .warehouse-hero p { margin:0.22rem 0 0; color:#64748b; font-size:0.86rem; }
    .warehouse-hero-actions { display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap; justify-content:flex-end; }
    .warehouse-select { border:1px solid #dbe4f0; background:#fff; color:#0f172a; border-radius:7px; padding:0.65rem 0.85rem; font-size:0.78rem; font-weight:800; }
    .warehouse-register-btn { background:#0f62fe; color:#fff; border:0; border-radius:7px; padding:0.7rem 1.05rem; font-size:0.82rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:0.55rem; box-shadow:0 8px 20px rgba(15,98,254,.22); }
    .warehouse-metric-row { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:0; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 10px 28px rgba(15,23,42,.035); }
    .warehouse-stat { padding:1rem; display:flex; gap:0.75rem; align-items:center; border-right:1px solid #e8eef7; min-width:0; }
    .warehouse-stat:last-child { border-right:0; }
    .warehouse-stat-icon { width:42px; height:42px; border-radius:9px; display:flex; align-items:center; justify-content:center; overflow:hidden; flex:0 0 auto; }
    .warehouse-stat-icon img { width:32px; height:32px; object-fit:cover; }
    .warehouse-stat small { display:block; color:#64748b; font-size:0.64rem; font-weight:900; text-transform:uppercase; }
    .warehouse-stat strong { display:block; color:#0f172a; font-family:'Outfit',sans-serif; font-size:1.25rem; line-height:1.1; margin-top:0.2rem; }
    .warehouse-stat span { display:block; font-size:0.68rem; font-weight:700; margin-top:0.3rem; color:#64748b; }
    .warehouse-grid-4 { display:grid; grid-template-columns:1.28fr 1fr 1.08fr 1.1fr; gap:1rem; }
    .warehouse-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem; box-shadow:0 10px 28px rgba(15,23,42,.035); min-width:0; }
    .warehouse-card-head { display:flex; align-items:center; justify-content:space-between; gap:0.75rem; margin-bottom:0.8rem; }
    .warehouse-card-head h3 { margin:0; color:#0f172a; font-family:'Outfit',sans-serif; font-size:0.95rem; font-weight:800; }
    .warehouse-card-head p { margin:0.25rem 0 0; color:#64748b; font-size:0.72rem; }
    .donut-wrap { display:grid; grid-template-columns:180px 1fr; gap:1rem; align-items:center; }
    .donut-chart { width:180px; height:180px; border-radius:50%; background:conic-gradient(#10b981 0 69.5%, #60a5fa 69.5% 82.5%, #a855f7 82.5% 98.5%, #ef4444 98.5% 100%); display:flex; align-items:center; justify-content:center; box-shadow:0 16px 28px rgba(16,185,129,.18); }
    .donut-hole { width:88px; height:88px; border-radius:50%; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; }
    .donut-hole strong { color:#0f172a; font-family:'Outfit',sans-serif; font-size:1.3rem; }
    .donut-hole span { color:#64748b; font-size:0.62rem; font-weight:800; }
    .legend-list { display:grid; gap:0.72rem; font-size:0.76rem; }
    .legend-line { display:grid; grid-template-columns:10px 1fr auto; gap:0.55rem; align-items:center; color:#334155; }
    .legend-dot { width:8px; height:8px; border-radius:50%; }
    .health-gauge { width:190px; height:96px; margin:0 auto; border-radius:190px 190px 0 0; background:conic-gradient(from 270deg at 50% 100%, #10b981 0deg 166deg, #e2e8f0 166deg 180deg); position:relative; overflow:hidden; }
    .health-gauge:after { content:""; position:absolute; left:26px; right:26px; bottom:0; height:70px; background:#fff; border-radius:120px 120px 0 0; }
    .health-score { text-align:center; margin-top:-1.8rem; position:relative; z-index:2; }
    .health-score strong { color:#059669; font-family:'Outfit',sans-serif; font-size:2rem; }
    .health-score span { color:#059669; display:block; font-weight:800; margin-top:0.1rem; }
    .health-mini { display:grid; grid-template-columns:repeat(3,1fr); border-top:1px solid #e2e8f0; margin-top:1rem; padding-top:0.85rem; text-align:center; }
    .health-mini strong { color:#059669; display:block; }
    .health-mini span { color:#64748b; font-size:0.7rem; }
    .alert-list, .movement-list { display:grid; gap:0.65rem; }
    .alert-row, .movement-row { display:grid; grid-template-columns:auto 1fr auto; gap:0.65rem; align-items:center; border:1px solid #e8eef7; border-radius:8px; padding:0.72rem; }
    .alert-icon, .movement-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .alert-icon img, .movement-icon img { width:24px; height:24px; object-fit:cover; }
    .alert-row strong, .movement-row strong { display:block; color:#0f172a; font-size:0.76rem; }
    .alert-row span, .movement-row span { color:#64748b; font-size:0.7rem; }
    .link-mini { color:#0f62fe; font-size:0.7rem; font-weight:800; text-decoration:none; white-space:nowrap; }
    .zones-row { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:1rem; }
    .zone-card { border:1px solid #e2e8f0; border-radius:9px; padding:0.9rem; background:#fff; color:inherit; text-decoration:none; transition:border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
    .zone-card:hover, .zone-card.active { border-color:#2563eb; box-shadow:0 12px 24px rgba(37,99,235,.12); transform:translateY(-1px); }
    .zone-card.active { background:#f8fbff; }
    .zone-card-top { display:flex; align-items:center; gap:0.75rem; margin-bottom:0.7rem; }
    .zone-icon { width:38px; height:38px; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .zone-icon img { width:30px; height:30px; object-fit:cover; }
    .zone-card strong { color:#0f172a; font-size:0.86rem; display:block; }
    .zone-card span { color:#64748b; font-size:0.74rem; font-weight:700; }
    .zone-bar { height:5px; border-radius:99px; background:#e8eef7; overflow:hidden; }
    .zone-bar div { height:100%; border-radius:99px; }
    .top-table { width:100%; border-collapse:collapse; font-size:0.78rem; min-width:1080px; }
    .top-table th { background:#f8fafc; color:#64748b; text-align:left; font-size:0.66rem; text-transform:uppercase; letter-spacing:.02em; padding:0.72rem; border-bottom:1px solid #e2e8f0; }
    .top-table th.sortable-th { cursor:pointer; user-select:none; transition:background 0.15s ease, color 0.15s ease; }
    .top-table th.sortable-th:hover { background:#e2e8f0; color:#0f172a; }
    .top-table th.sortable-th.sorted-active { background:#eff6ff; color:#1d4ed8; font-weight:900; }
    .sort-icon { font-size:0.75rem; margin-left:0.3rem; opacity:0.6; display:inline-flex; vertical-align:middle; }
    .sort-icon .wa-svg-icon { width:0.9rem; height:0.9rem; }
    .sortable-th.sorted-active .sort-icon { opacity:1; color:#1d4ed8; font-weight:900; }
    .top-table td { padding:0.72rem; border-bottom:1px solid #eef2f7; vertical-align:middle; }
    .top-table-img { width:38px; height:38px; border-radius:7px; border:1px solid #e2e8f0; object-fit:cover; background:#fff; transition:transform 0.15s ease; }
    .top-table-img:hover { transform:scale(1.15); box-shadow:0 4px 10px rgba(0,0,0,0.12); }
    .fefo-good { color:#047857; background:#d1fae5; border-radius:6px; padding:0.22rem 0.55rem; font-size:0.66rem; font-weight:800; }
    .fefo-warn { color:#c2410c; background:#ffedd5; border-radius:6px; padding:0.22rem 0.55rem; font-size:0.66rem; font-weight:800; }
    .fefo-danger { color:#b91c1c; background:#fee2e2; border-radius:6px; padding:0.22rem 0.55rem; font-size:0.66rem; font-weight:800; }
    .details-btn { border:1px solid #bfdbfe; color:#0f62fe; background:#fff; border-radius:6px; padding:0.42rem 0.75rem; font-weight:800; font-size:0.72rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.3rem; transition:all 0.15s ease; }
    .details-btn:hover { transform:translateY(-1px); box-shadow:0 2px 6px rgba(15,98,254,0.15); }
    
    /* Top Inventory Search & Filter Toolbar */
    .inventory-search-toolbar { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; justify-content:space-between; margin-bottom:1rem; padding:0.75rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; }
    .inventory-search-box { position:relative; flex:1; min-width:260px; max-width:440px; }
    .inventory-search-box input { width:100%; padding:0.55rem 2.2rem 0.55rem 2.2rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.82rem; background:#ffffff; color:#0f172a; outline:none; transition:border-color 0.15s ease, box-shadow 0.15s ease; }
    .inventory-search-box input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.12); }
    .inventory-search-icon { position:absolute; left:0.7rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:0.85rem; pointer-events:none; }
    .inventory-search-clear { position:absolute; right:0.6rem; top:50%; transform:translateY(-50%); color:#94a3b8; cursor:pointer; font-size:0.85rem; padding:0.2rem; display:none; }
    .inventory-search-clear:hover { color:#0f172a; }
    .inventory-filters-group { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; }
    .inventory-filter-select { border:1px solid #cbd5e1; background:#ffffff; color:#334155; border-radius:6px; padding:0.52rem 0.7rem; font-size:0.76rem; font-weight:700; outline:none; cursor:pointer; }
    .inventory-filter-select:focus { border-color:#2563eb; }
    .inventory-count-badge { display:inline-flex; align-items:center; gap:0.35rem; font-size:0.72rem; font-weight:800; color:#475569; background:#e2e8f0; padding:0.25rem 0.6rem; border-radius:99px; }
    .inventory-reset-btn { border:1px solid #e2e8f0; background:#ffffff; color:#64748b; border-radius:6px; padding:0.52rem 0.75rem; font-size:0.75rem; font-weight:700; cursor:pointer; transition:all 0.15s ease; }
    .inventory-reset-btn:hover { background:#f1f5f9; color:#0f172a; }

    /* Online Image Finder Modal & Gallery */
    .image-finder-chip { display:inline-flex; align-items:center; padding:0.28rem 0.65rem; border-radius:99px; background:#f1f5f9; color:#475569; font-size:0.72rem; font-weight:700; cursor:pointer; border:1px solid #e2e8f0; transition:all 0.15s ease; }
    .image-finder-chip:hover, .image-finder-chip.active { background:#2563eb; color:#ffffff; border-color:#2563eb; }
    .image-results-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:0.9rem; max-height:440px; overflow-y:auto; padding:0.4rem; }
    .image-result-card { background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; transition:all 0.18s ease; position:relative; }
    .image-result-card:hover { border-color:#2563eb; transform:translateY(-2px); box-shadow:0 8px 18px rgba(37,99,235,0.12); }
    .image-card-thumb-wrap { width:100%; height:130px; background:#f8fafc; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative; }
    .image-card-thumb { width:100%; height:100%; object-fit:contain; padding:0.35rem; transition:transform 0.2s ease; }
    .image-result-card:hover .image-card-thumb { transform:scale(1.06); }
    .image-card-badge { position:absolute; bottom:6px; left:6px; background:rgba(15,23,42,0.75); backdrop-filter:blur(3px); color:#ffffff; font-size:0.58rem; font-weight:800; padding:0.15rem 0.4rem; border-radius:4px; }
    .image-card-body { padding:0.6rem 0.7rem; display:flex; flex-direction:column; gap:0.4rem; flex:1; justify-content:space-between; }
    .image-card-title { font-size:0.73rem; font-weight:700; color:#0f172a; line-height:1.25; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .image-card-apply-btn { width:100%; background:#2563eb; color:#ffffff; border:none; border-radius:5px; padding:0.45rem 0.5rem; font-size:0.72rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:0.3rem; transition:background 0.15s ease; }
    .image-card-apply-btn:hover { background:#1d4ed8; }
    .image-card-apply-btn.applied { background:#16a34a; }
    
    /* Toast Notification */
    .beverage-toast { position:fixed; bottom:24px; right:24px; z-index:100000; background:#0f172a; color:#ffffff; padding:0.85rem 1.25rem; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,0.25); display:flex; align-items:center; gap:0.75rem; font-size:0.84rem; font-weight:700; transform:translateY(100px); opacity:0; transition:all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    .beverage-toast.show { transform:translateY(0); opacity:1; }
    .beverage-toast.toast-success { border-left:4px solid #10b981; }
    .beverage-toast.toast-error { border-left:4px solid #ef4444; }

    @media (max-width: 760px) {
        .inventory-panel {
            padding: 1rem;
        }
        .inventory-header {
            flex-direction: column;
        }
        .inventory-header button {
            width: 100%;
            justify-content: center;
        }
        .inventory-table {
            min-width: 860px;
        }
        .warehouse-hero, .warehouse-hero-actions { align-items:flex-start; flex-direction:column; }
        .warehouse-metric-row, .warehouse-grid-4, .zones-row { grid-template-columns:1fr; }
        .donut-wrap { grid-template-columns:1fr; justify-items:center; }
        .inventory-search-toolbar { flex-direction:column; align-items:stretch; }
        .inventory-search-box { max-width:100%; }
    }
</style>
            <?php if ($actionMessage): ?>
                <?php $messageStyle = $actionMessageType === 'warning' ? 'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa' : 'background:#d1fae5;color:#065f46;border:1px solid #a7f3d0'; ?>
                <div style="<?= e($messageStyle) ?>;padding:0.85rem 1.25rem;border-radius:10px;font-weight:600;font-size:0.88rem;margin-bottom:1.25rem">
                    <?= e($actionMessage) ?>
                </div>
            <?php endif; ?>

            <?php 
            $activeTab = $_GET['tab'] ?? 'tab-dash';
            try {
                $warehouses = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')
                    ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getWarehouses()
                    : ['Jacroxx Warehouse', 'Ijaba Warehouse'];
            } catch (\Throwable $t) {
                $warehouses = ['Jacroxx Warehouse', 'Ijaba Warehouse'];
            }
            try {
                $selectedWarehouse = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')
                    ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::normalizeWarehouse($_GET['warehouse'] ?? ($warehouses[0] ?? null))
                    : ($warehouses[0] ?? 'Jacroxx Warehouse');
            } catch (\Throwable $t) {
                $selectedWarehouse = $warehouses[0] ?? 'Jacroxx Warehouse';
            }
            $inventoryDetail = strtolower(trim((string)($_GET['detail'] ?? '')));
            $warehouseProducts = $products;
            $warehouseAllProducts = $products;
            $warehouseTableProducts = $products;
            $warehouseSummaries = [];
            if (in_array($activeTab, ['tab-dash', 'tab-inventory'], true)) {
                try {
                    $warehouseProducts = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')
                        ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProductsForWarehouse($selectedWarehouse)
                        : $products;
                    $warehouseAllProducts = ($activeTab === 'tab-inventory' && class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService'))
                        ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProductsForWarehouse($selectedWarehouse, true)
                        : $warehouseProducts;
                    $warehouseTableProducts = ($activeTab === 'tab-inventory' && $inventoryDetail === 'out-of-stock')
                        ? $warehouseAllProducts
                        : $warehouseProducts;
                } catch (\Throwable $t) {
                    $warehouseProducts = $products;
                    $warehouseAllProducts = $products;
                    $warehouseTableProducts = $products;
                }
                try {
                    $warehouseSummaries = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')
                        ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getWarehouseSummaries()
                        : [];
                } catch (\Throwable $t) {
                    $warehouseSummaries = [];
                }
            }
            if ($activeTab === 'tab-truck-loads'):
                try {
                    $truckLoadSettings = $truckLoadSettings ?? \App\Modules\BeverageWarehouse\TruckLoadService::getSettings();
                } catch (\Throwable $t) {
                    $truckLoadSettings = ['default_pallet_capacity' => 12, 'allow_partial_pallets' => true];
                }
                try {
                    $truckLoads = $truckLoads ?? \App\Modules\BeverageWarehouse\TruckLoadService::getLoads();
                } catch (\Throwable $t) {
                    $truckLoads = [];
                }
            ?>
                <style>
                    .daily-field { display:flex; flex-direction:column; gap:0.28rem; font-size:0.72rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.02em; }
                    .daily-field input, .daily-field select { height:42px; border:1px solid #dbe5f3; border-radius:10px; padding:0 0.8rem; color:#0f172a; font-weight:800; background:#fff; min-width:170px; }
                    @media (max-width: 760px) {
                        .truck-load-line { grid-template-columns:1fr !important; }
                        .daily-field, .daily-field input, .daily-field select { width:100%; min-width:0; }
                    }
                </style>
                <div class="warehouse-page">
                    <section class="warehouse-hero">
                        <div class="warehouse-hero-title">
                            <div class="warehouse-hero-icon" style="color:#f97316"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('truck') : '' ?></div>
                            <div>
                                <h2>Truck Load &amp; Pallet Allocation</h2>
                                <p>Plan mixed SKU truck loads from warehouse stock without double-deducting inventory.</p>
                            </div>
                        </div>
                    </section>

                    <section class="warehouse-card" style="margin-bottom:1rem">
                        <div class="warehouse-card-head"><div><h3>Truck Configuration</h3><p>Default full-truck capacity is configurable.</p></div></div>
                        <form method="POST" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="save_truck_load_settings">
                            <label class="daily-field">Pallet Capacity
                                <input type="number" name="default_pallet_capacity" min="1" value="<?= (int)($truckLoadSettings['default_pallet_capacity'] ?? 12) ?>">
                            </label>
                            <label style="display:flex;align-items:center;gap:0.45rem;font-weight:800;color:#334155;font-size:0.8rem;padding-bottom:0.7rem">
                                <input type="checkbox" name="allow_partial_pallets" <?= !empty($truckLoadSettings['allow_partial_pallets']) ? 'checked' : '' ?>>
                                Allow partial pallets
                            </label>
                            <button class="details-btn" type="submit" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe">Save Settings</button>
                        </form>
                    </section>

                    <section class="warehouse-card" style="margin-bottom:1rem">
                        <div class="warehouse-card-head"><div><h3>Commercial GRN Receiving</h3><p>Capture supplier invoice cost, expected rebate and promotion benefit when stock is received.</p></div></div>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="receive_po">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem">
                                <label class="daily-field">PO / Invoice No.
                                    <input type="text" name="po_number" placeholder="PO / invoice reference">
                                </label>
                                <label class="daily-field">Supplier
                                    <input type="text" name="supplier" placeholder="Seven-Up / NBC">
                                </label>
                                <label class="daily-field">SKU
                                    <select name="sku">
                                        <option value="">Select SKU</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= e((string)($p['sku'] ?? '')) ?>"><?= e((string)($p['name'] ?? 'Product')) ?> - <?= e((string)($p['sku'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="daily-field">Warehouse
                                    <select name="warehouse">
                                        <?php foreach ($warehouses as $wh): ?>
                                            <option value="<?= e((string)$wh) ?>"><?= e((string)$wh) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="daily-field">Ordered Qty
                                    <input type="number" name="ordered_qty" min="0" value="0">
                                </label>
                                <label class="daily-field">Delivered Qty
                                    <input type="number" name="delivered_qty" min="0" value="0">
                                </label>
                                <label class="daily-field">Rejected Qty
                                    <input type="number" name="rejected_qty" min="0" value="0">
                                </label>
                                <label class="daily-field">Damaged Qty
                                    <input type="number" name="damaged_qty" min="0" value="0">
                                </label>
                                <label class="daily-field">Promo Free Qty
                                    <input type="number" name="promo_free_qty" min="0" value="0">
                                </label>
                                <label class="daily-field">Invoice Cost
                                    <input type="number" step="0.01" min="0" name="invoice_cost" placeholder="0.00">
                                </label>
                                <label class="daily-field">Rebate Base
                                    <input type="number" step="0.01" min="0" name="rebate_base_price" placeholder="0.00">
                                </label>
                                <label class="daily-field">Expected Rebate %
                                    <input type="number" step="0.001" min="0" name="expected_rebate_pct" placeholder="0">
                                </label>
                                <label class="daily-field">Adjustment %
                                    <input type="number" step="0.001" min="0" name="rebate_adjustment_factor" value="100">
                                </label>
                                <label class="daily-field">Promotion Code
                                    <input type="text" name="promotion_code" placeholder="Optional">
                                </label>
                                <label class="daily-field">Promo Benefit / Unit
                                    <input type="number" step="0.01" min="0" name="promotion_benefit_per_unit" placeholder="0.00">
                                </label>
                            </div>
                            <button class="details-btn" type="submit" style="margin-top:0.9rem;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('download') : '' ?> Post GRN &amp; Update Stock</button>
                        </form>
                    </section>

                    <section class="warehouse-card" style="margin-bottom:1rem">
                        <div class="warehouse-card-head"><div><h3>Create Load Plan</h3><p>Use one row per SKU/pallet group. Mixed loads calculate by each SKU's pallet quantity.</p></div></div>
                        <form method="POST" id="truckLoadForm">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="create_truck_load">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;margin-bottom:0.8rem">
                                <label class="daily-field">Receiving Customer / Distributor
                                    <input type="text" name="customer" placeholder="Distributor name">
                                </label>
                                <label class="daily-field">Sales Type
                                    <select name="sales_channel">
                                        <option value="diversion_sale">Diversion Sale</option>
                                        <option value="depot_sale">Depot Sale</option>
                                    </select>
                                </label>
                            </div>
                            <div id="truckLoadLines" style="display:grid;gap:0.65rem">
                                <?php for ($i = 0; $i < 4; $i++): ?>
                                    <div class="truck-load-line" style="display:grid;grid-template-columns:minmax(220px,2fr) repeat(3,minmax(130px,1fr));gap:0.55rem">
                                        <select name="load_sku[]" class="warehouse-select">
                                            <option value="">Select SKU</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= e((string)$product['sku']) ?>"><?= e((string)$product['name']) ?> (<?= e((string)$product['sku']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="warehouse-select" type="number" step="0.25" min="0" name="load_pallets[]" placeholder="Pallets">
                                        <input class="warehouse-select" type="number" min="1" name="load_packs_per_pallet[]" placeholder="Per pallet (optional)">
                                        <select class="warehouse-select" name="load_source_warehouse[]">
                                            <?php foreach ($warehouses as $warehouseName): ?>
                                                <option value="<?= e($warehouseName) ?>"><?= e($warehouseName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <button class="details-btn" type="submit" style="margin-top:0.9rem;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('truck') : '' ?> Create Load Plan</button>
                        </form>
                    </section>

                    <section class="warehouse-card">
                        <div class="warehouse-card-head"><div><h3>Recent Load Plans</h3><p>Inventory is deducted only when finalized through stock movement.</p></div></div>
                        <div class="inventory-table-wrap" style="margin:0">
                            <table class="top-table">
                                <thead><tr><th>Load</th><th>Customer</th><th>Channel</th><th>Pallets</th><th>Packs</th><th>Crates</th><th>Value</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php foreach ($truckLoads as $load): ?>
                                        <?php $totals = $load['calculation']['totals'] ?? []; ?>
                                        <tr>
                                            <td><strong><?= e((string)$load['id']) ?></strong><div style="font-size:0.68rem;color:#64748b"><?= e((string)($load['created_at'] ?? '')) ?></div></td>
                                            <td><?= e((string)($load['customer'] ?? '')) ?></td>
                                            <td><?= e(ucwords(str_replace('_', ' ', (string)($load['sales_channel'] ?? '')))) ?></td>
                                            <td><?= number_format((float)($totals['pallets'] ?? 0), 2) ?></td>
                                            <td><?= number_format((int)($totals['packs'] ?? 0)) ?></td>
                                            <td><?= number_format((int)($totals['crates'] ?? 0)) ?></td>
                                            <td>₦<?= number_format((float)($totals['value'] ?? 0), 2) ?></td>
                                            <td><span class="<?= (($load['status'] ?? '') === 'Finalized') ? 'fefo-good' : 'fefo-warn' ?>"><?= e((string)($load['status'] ?? 'Planned')) ?></span></td>
                                            <td>
                                                <?php if (($load['status'] ?? 'Planned') !== 'Finalized'): ?>
                                                    <form method="POST" onsubmit="return confirm('Finalize this truck load and deduct stock once?')" style="margin:0">
                                                        <input type="hidden" name="form_action" value="finalize_truck_load">
                                                        <input type="hidden" name="load_id" value="<?= e((string)$load['id']) ?>">
                                                        <button class="details-btn" type="submit" style="padding:0.38rem 0.55rem;font-size:0.68rem;background:#ecfdf5;color:#15803d;border:1px solid #bbf7d0">Finalize</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color:#64748b;font-size:0.72rem"><?= e((string)($load['finalized_at'] ?? 'Posted')) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($truckLoads)): ?>
                                        <tr><td colspan="9" style="text-align:center;color:#64748b;padding:2rem">No truck load plans created yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            <?php elseif ($activeTab === 'tab-physical-count'):
                $countWarehouseValue = \App\Modules\BeverageWarehouse\BeverageWarehouseService::normalizeWarehouse($_GET['warehouse'] ?? ($_POST['count_warehouse'] ?? ($warehouses[0] ?? 'Jacroxx Warehouse')));
                $countProducts = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProductsForWarehouse($countWarehouseValue, true);
                usort($countProducts, static fn(array $a, array $b): int => ((int)($b['stock_crates'] ?? 0)) <=> ((int)($a['stock_crates'] ?? 0)));
                $recentPhysicalCounts = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getPhysicalStockCounts(8);
                $countTotalSkus = count($countProducts);
                $countSystemCrates = array_sum(array_map(static fn(array $p): int => (int)($p['stock_crates'] ?? 0), $countProducts));
                $countSystemValue = array_sum(array_map(static fn(array $p): float => ((int)($p['stock_crates'] ?? 0)) * ((float)($p['cost_price'] ?? $p['wholesale_price'] ?? 0)), $countProducts));
                $formatMoney = static fn(float $amount): string => '₦' . number_format($amount, 2);
            ?>
                <style>
                    .physical-count-shell { display:grid; grid-template-columns:minmax(0, 1.65fr) minmax(300px, .8fr); gap:1rem; align-items:start; }
                    .physical-count-toolbar { display:flex; align-items:flex-end; justify-content:space-between; gap:0.85rem; flex-wrap:wrap; margin-bottom:1rem; padding:0.85rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; }
                    .physical-count-form-row { display:flex; gap:0.65rem; align-items:flex-end; flex-wrap:wrap; }
                    .count-field { display:flex; flex-direction:column; gap:0.3rem; font-size:0.68rem; font-weight:900; color:#475569; text-transform:uppercase; letter-spacing:0.02em; }
                    .count-field input, .count-field select, .count-field textarea { border:1px solid #dbe5f3; border-radius:10px; padding:0.68rem 0.8rem; color:#0f172a; font-weight:800; background:#fff; min-width:170px; outline:none; transition:border-color .18s ease, box-shadow .18s ease; }
                    .count-field input:focus, .count-field select:focus, .count-field textarea:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.11); }
                    .count-field textarea { min-height:84px; resize:vertical; text-transform:none; font-weight:700; line-height:1.45; }
                    .physical-count-kpis { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:0.75rem; margin-bottom:1rem; }
                    .physical-count-kpi { border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:1rem; box-shadow:0 10px 26px rgba(15,23,42,.05); min-width:0; }
                    .physical-count-kpi small { display:block; color:#64748b; font-size:0.64rem; font-weight:900; text-transform:uppercase; letter-spacing:.03em; margin-bottom:.35rem; }
                    .physical-count-kpi strong { display:block; color:#0f172a; font-size:1.25rem; line-height:1.1; font-family:'Outfit',sans-serif; overflow-wrap:anywhere; }
                    .physical-count-kpi span { color:#64748b; font-size:0.72rem; font-weight:700; display:block; margin-top:0.25rem; }
                    .physical-count-table { min-width:760px; }
                    .physical-count-product { display:flex; align-items:center; gap:0.65rem; min-width:220px; }
                    .physical-count-product img { width:44px; height:44px; border-radius:9px; object-fit:contain; background:#f8fafc; border:1px solid #e2e8f0; padding:0.15rem; flex:0 0 auto; }
                    .physical-count-product strong { display:block; color:#0f172a; font-size:0.82rem; line-height:1.25; }
                    .physical-count-product small { display:block; color:#64748b; font-size:0.68rem; margin-top:0.2rem; }
                    .count-input { width:112px; min-width:96px !important; height:40px; text-align:center; border:1px solid #cbd5e1; border-radius:9px; color:#0f172a; font-weight:900; background:#fff; }
                    .damaged-count-input { border-color:#fed7aa; background:#fff7ed; color:#9a3412; }
                    .damaged-count-input:focus { border-color:#f97316; box-shadow:0 0 0 3px rgba(249,115,22,.14); }
                    .count-input:focus { border-color:#8a0f35; box-shadow:0 0 0 3px rgba(138,15,53,.12); outline:none; }
                    .variance-preview { font-weight:900; color:#64748b; white-space:nowrap; }
                    .variance-preview.positive { color:#059669; }
                    .variance-preview.negative { color:#dc2626; }
                    .recent-count-list { display:grid; gap:0.7rem; }
                    .recent-count-card { border:1px solid #e2e8f0; border-radius:12px; padding:0.9rem; background:#fff; box-shadow:0 8px 20px rgba(15,23,42,.035); }
                    .recent-count-card strong { display:block; color:#0f172a; font-size:0.84rem; }
                    .recent-count-card span { display:block; color:#64748b; font-size:0.72rem; margin-top:0.18rem; font-weight:700; }
                    .count-status { display:inline-flex; align-items:center; border-radius:999px; padding:0.22rem 0.52rem; font-size:0.66rem; font-weight:900; margin-top:0.45rem; }
                    .count-status.ok { background:#dcfce7; color:#166534; }
                    .count-status.warn { background:#fff7ed; color:#c2410c; }
                    @media (max-width: 980px) {
                        .physical-count-shell { grid-template-columns:1fr; }
                        .physical-count-kpis { grid-template-columns:repeat(2, minmax(0, 1fr)); }
                    }
                    @media (max-width: 620px) {
                        .warehouse-page { gap:0.8rem; }
                        .warehouse-hero { padding:1rem; border-radius:14px; }
                        .warehouse-hero-title { align-items:flex-start; }
                        .warehouse-hero h2 { font-size:1.15rem; }
                        .warehouse-hero p { font-size:0.78rem; line-height:1.45; }
                        .warehouse-card { padding:0.85rem; border-radius:14px; }
                        .physical-count-toolbar { padding:0.75rem; align-items:stretch; }
                        .physical-count-form-row, .physical-count-form-row .count-field, .count-field input, .count-field select, .count-field textarea { width:100%; min-width:0; }
                        .physical-count-kpis { grid-template-columns:1fr; }
                        .physical-count-kpi { padding:0.85rem; }
                        .count-input { width:100%; }
                        .physical-count-table { min-width:0; border-collapse:separate !important; border-spacing:0 0.72rem !important; }
                        .physical-count-table thead { display:none; }
                        .physical-count-table tbody, .physical-count-table tr, .physical-count-table td { display:block; width:100%; }
                        .physical-count-table tr { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem; box-shadow:0 10px 24px rgba(15,23,42,.045); }
                        .physical-count-table td { border:0 !important; padding:0.48rem 0 !important; display:flex; justify-content:space-between; gap:0.9rem; align-items:center; font-size:0.78rem; }
                        .physical-count-table td::before { content:attr(data-label); color:#64748b; font-size:0.66rem; font-weight:900; text-transform:uppercase; letter-spacing:.02em; flex:0 0 auto; }
                        .physical-count-table td:first-child { display:block; padding-top:0 !important; }
                        .physical-count-table td:first-child::before { display:none; }
                        .physical-count-product { min-width:0; align-items:flex-start; }
                        .physical-count-product img { width:50px; height:50px; }
                        .physical-count-table td:nth-child(5), .physical-count-table td:nth-child(6) { display:block; }
                        .physical-count-table td:nth-child(5)::before, .physical-count-table td:nth-child(6)::before { display:block; margin-bottom:0.35rem; }
                        .variance-preview { white-space:normal; text-align:right; }
                    }
                </style>
                <div class="warehouse-page">
                    <section class="warehouse-hero">
                        <div class="warehouse-hero-title">
                            <div class="warehouse-hero-icon" style="color:#2563eb"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?></div>
                            <div>
                                <h2>Physical Stock Count</h2>
                                <p>Capture counted stock per warehouse and keep a permanent variance record before any stock adjustment.</p>
                            </div>
                        </div>
                        <form method="GET" class="physical-count-form-row">
                            <input type="hidden" name="tab" value="tab-physical-count">
                            <label class="count-field">Warehouse
                                <select name="warehouse">
                                    <?php foreach ($warehouses as $warehouseName): ?>
                                        <option value="<?= e($warehouseName) ?>" <?= $countWarehouseValue === $warehouseName ? 'selected' : '' ?>><?= e($warehouseName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit" class="details-btn" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('search') : '' ?> Load Products</button>
                        </form>
                    </section>

                    <div class="physical-count-kpis">
                        <div class="physical-count-kpi"><small>Products to Count</small><strong><?= number_format($countTotalSkus) ?></strong><span><?= e($countWarehouseValue) ?></span></div>
                        <div class="physical-count-kpi"><small>System Stock</small><strong><?= number_format($countSystemCrates) ?></strong><span>Crates / packs before physical count</span></div>
                        <div class="physical-count-kpi"><small>System Value</small><strong><?= e($formatMoney((float)$countSystemValue)) ?></strong><span>Based on current cost prices</span></div>
                    </div>

                    <div class="physical-count-shell">
                        <section class="warehouse-card">
                            <div class="warehouse-card-head">
                                <div>
                                    <h3>Count Sheet</h3>
                                    <p>Enter good stock counted and any damaged stock found. Blank rows are skipped.</p>
                                </div>
                            </div>
                            <form method="POST" id="physicalCountForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="capture_physical_stock_count">
                                <div class="physical-count-toolbar">
                                    <div class="physical-count-form-row">
                                        <label class="count-field">Count Date
                                            <input type="date" name="count_date" value="<?= e(date('Y-m-d')) ?>">
                                        </label>
                                        <label class="count-field">Warehouse
                                            <select name="count_warehouse">
                                                <?php foreach ($warehouses as $warehouseName): ?>
                                                    <option value="<?= e($warehouseName) ?>" <?= $countWarehouseValue === $warehouseName ? 'selected' : '' ?>><?= e($warehouseName) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="count-field">Count Name
                                            <input type="text" name="count_name" value="Physical Count <?= e(date('d M Y')) ?>">
                                        </label>
                                        <label class="count-field">Counted By
                                            <input type="text" name="counted_by" value="<?= e(current_user()['name'] ?? 'Warehouse Admin') ?>">
                                        </label>
                                    </div>
                                    <button type="submit" class="warehouse-register-btn"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?> Save Physical Count</button>
                                </div>

                                <div class="inventory-table-wrap" style="margin:0">
                                    <table class="top-table physical-count-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Pack Size</th>
                                                <th>System Stock</th>
                                                <th>Physical Count</th>
                                                <th>Damaged</th>
                                                <th>Variance</th>
                                                <th>Cost Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($countProducts as $product): ?>
                                                <?php
                                                    $sku = strtoupper((string)($product['sku'] ?? ''));
                                                    $systemStock = (int)($product['stock_crates'] ?? 0);
                                                    $costPrice = (float)($product['cost_price'] ?? $product['wholesale_price'] ?? 0);
                                                    $imageSrc = beverage_product_image_src($product['image'] ?? '');
                                                ?>
                                                <tr>
                                                    <td data-label="Product">
                                                        <div class="physical-count-product">
                                                            <?php if ($imageSrc !== ''): ?>
                                                                <img src="<?= e($imageSrc) ?>" alt="">
                                                            <?php endif; ?>
                                                            <div>
                                                                <strong><?= e((string)($product['name'] ?? $sku)) ?></strong>
                                                                <small><?= e((string)($product['category'] ?? 'Beverage')) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td data-label="SKU" style="font-family:monospace;font-weight:800"><?= e($sku) ?></td>
                                                    <td data-label="Pack Size"><?= number_format(max(1, (int)($product['units_per_crate'] ?? 1))) ?> per <?= e((string)($product['packaging'] ?? 'pack')) ?></td>
                                                    <td data-label="System Stock"><strong><?= number_format($systemStock) ?></strong></td>
                                                    <td data-label="Physical Count">
                                                        <input class="count-input physical-count-input" type="number" min="0" step="1" name="physical_counts[<?= e($sku) ?>]" data-system="<?= e((string)$systemStock) ?>" data-cost="<?= e((string)$costPrice) ?>" placeholder="Count">
                                                    </td>
                                                    <td data-label="Damaged">
                                                        <input class="count-input damaged-count-input" type="number" min="0" step="1" name="damaged_counts[<?= e($sku) ?>]" data-sku="<?= e($sku) ?>" placeholder="Damaged">
                                                    </td>
                                                    <td data-label="Variance" class="variance-preview" data-variance-for="<?= e($sku) ?>">-</td>
                                                    <td data-label="Cost Value"><?= e($formatMoney($systemStock * $costPrice)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($countProducts)): ?>
                                        <tr><td colspan="8" style="text-align:center;color:#64748b;padding:2rem">No products found for this warehouse.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <label class="count-field" style="margin-top:0.9rem">Notes
                                    <textarea name="notes" placeholder="Optional count notes, damaged stock remarks, missing product details..."></textarea>
                                </label>
                            </form>
                        </section>

                        <section class="warehouse-card">
                            <div class="warehouse-card-head">
                                <div>
                                    <h3>Recent Counts</h3>
                                    <p>Saved physical count sessions stay unchanged for audit review.</p>
                                </div>
                            </div>
                            <div class="recent-count-list">
                                <?php foreach ($recentPhysicalCounts as $count): ?>
                                    <?php
                                        $totals = $count['totals'] ?? [];
                                        $variance = (int)($totals['variance_crates'] ?? 0);
                                        $status = (string)($count['status'] ?? 'Pending Approval');
                                        $statusOk = $status === 'Approved & Posted' && $variance === 0;
                                        $isPending = $status !== 'Approved & Posted';
                                        $postedTotals = $count['posted_totals'] ?? [];
                                    ?>
                                    <div class="recent-count-card">
                                        <strong><?= e((string)($count['count_name'] ?? $count['count_id'] ?? 'Physical Count')) ?></strong>
                                        <span><?= e((string)($count['warehouse'] ?? 'Warehouse')) ?> · <?= e(date('d M Y', strtotime((string)($count['count_date'] ?? date('Y-m-d'))))) ?></span>
                                        <span><?= number_format((int)($totals['total_skus'] ?? 0)) ?> SKUs · System <?= number_format((int)($totals['system_crates'] ?? 0)) ?> · Good <?= number_format((int)($totals['counted_crates'] ?? 0)) ?> · Damaged <?= number_format((int)($totals['damaged_crates'] ?? 0)) ?></span>
                                        <span>Variance value: <?= e($formatMoney((float)($totals['variance_value'] ?? 0))) ?></span>
                                        <?php if (!$isPending): ?>
                                            <span>Posted stock: <?= number_format((int)($postedTotals['good_stock_after'] ?? $totals['counted_crates'] ?? 0)) ?> available · Sold/missing <?= number_format((int)($postedTotals['confirmed_sold_or_missing'] ?? 0)) ?></span>
                                        <?php endif; ?>
                                        <span class="count-status <?= $statusOk ? 'ok' : 'warn' ?>"><?= e($isPending ? 'Pending Approval' : 'Approved & Posted') ?><?= $variance !== 0 ? ' · ' . (($variance > 0 ? '+' : '') . number_format($variance) . ' variance') : '' ?></span>
                                        <?php if ($isPending): ?>
                                            <form method="POST" onsubmit="return confirm('Approve this physical count and update available stock?');" style="margin-top:0.7rem">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form_action" value="approve_physical_stock_count">
                                                <input type="hidden" name="count_id" value="<?= e((string)($count['count_id'] ?? '')) ?>">
                                                <button type="submit" class="details-btn" style="width:100%;justify-content:center;background:#f0fdf4;color:#166534;border-color:#86efac"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?> Approve &amp; Update Stock</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($recentPhysicalCounts)): ?>
                                    <div style="border:1px dashed #cbd5e1;border-radius:10px;padding:1rem;color:#64748b;font-weight:700">No physical counts saved yet.</div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </div>
            <?php elseif ($activeTab === 'tab-daily-stock'):
                $dailyStockReport = $dailyStockReport ?? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getDailyStockReport(date('Y-m-d'), 'All Warehouses');
                $dailyTotals = $dailyStockReport['totals'] ?? [];
                $dailyRows = $dailyStockReport['rows'] ?? [];
                $reportDateValue = (string)($dailyStockReport['report_date'] ?? date('Y-m-d'));
                $reportWarehouseValue = (string)($dailyStockReport['warehouse'] ?? 'All Warehouses');
                $openingCapturedAt = (string)($dailyStockReport['opening_captured_at'] ?? '');
                $closingCapturedAt = (string)($dailyStockReport['closing_captured_at'] ?? '');
                $formatMoney = static fn(float $amount): string => '₦' . number_format($amount, 2);
                $stockDeltaClass = ((int)($dailyTotals['crate_change'] ?? 0)) < 0 ? '#dc2626' : '#059669';
            ?>
                <style>
                    .daily-stock-toolbar { display:flex; align-items:flex-end; justify-content:space-between; gap:0.8rem; flex-wrap:wrap; margin-bottom:1rem; }
                    .daily-stock-form { display:flex; gap:0.65rem; align-items:flex-end; flex-wrap:wrap; }
                    .daily-field { display:flex; flex-direction:column; gap:0.28rem; font-size:0.72rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.02em; }
                    .daily-field input, .daily-field select { height:42px; border:1px solid #dbe5f3; border-radius:10px; padding:0 0.8rem; color:#0f172a; font-weight:800; background:#fff; min-width:170px; }
                    .daily-stock-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:0.8rem; margin-bottom:1rem; }
                    .daily-stock-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1rem; box-shadow:0 8px 22px rgba(15,23,42,.05); }
                    .daily-stock-card small { display:block; color:#64748b; font-size:0.68rem; font-weight:900; text-transform:uppercase; letter-spacing:.03em; margin-bottom:.35rem; }
                    .daily-stock-card strong { display:block; color:#0f172a; font-size:1.28rem; line-height:1.1; }
                    .daily-stock-card span { display:block; color:#64748b; font-size:.76rem; margin-top:.28rem; font-weight:700; }
                    .daily-stock-meta { display:flex; flex-wrap:wrap; gap:0.5rem; color:#64748b; font-size:0.78rem; font-weight:700; }
                    .daily-stock-meta span { background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px; padding:0.35rem 0.65rem; }
                    .daily-stock-delta { font-weight:900; color:<?= e($stockDeltaClass) ?>; }
                    @media (max-width: 760px) {
                        .daily-stock-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
                        .daily-stock-form, .daily-stock-form .daily-field, .daily-field input, .daily-field select { width:100%; min-width:0; }
                        .daily-stock-form .details-btn { flex:1 1 100%; justify-content:center; }
                    }
                    @media (max-width: 420px) {
                        .daily-stock-grid { grid-template-columns:1fr; }
                    }
                </style>
                <div class="warehouse-page">
                    <section class="warehouse-hero">
                        <div class="warehouse-hero-title">
                            <div class="warehouse-hero-icon" style="color:#2563eb"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('analytics') : '' ?></div>
                            <div>
                                <h2>Daily Opening &amp; Closing Stock</h2>
                                <p>Permanent stock snapshots by date and warehouse for end-of-day reporting.</p>
                            </div>
                        </div>
                    </section>

                    <section class="warehouse-card">
                        <div class="daily-stock-toolbar">
                            <form method="GET" class="daily-stock-form">
                                <input type="hidden" name="tab" value="tab-daily-stock">
                                <label class="daily-field">Report Date
                                    <input type="date" name="report_date" value="<?= e($reportDateValue) ?>">
                                </label>
                                <label class="daily-field">Warehouse
                                    <select name="report_warehouse">
                                        <option value="All Warehouses" <?= $reportWarehouseValue === 'All Warehouses' ? 'selected' : '' ?>>All Warehouses</option>
                                        <?php foreach ($warehouses as $warehouseName): ?>
                                            <option value="<?= e($warehouseName) ?>" <?= $reportWarehouseValue === $warehouseName ? 'selected' : '' ?>><?= e($warehouseName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit" class="details-btn" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('search') : '' ?> View Report</button>
                            </form>
                            <div class="daily-stock-meta">
                                <span>Opening: <?= e($openingCapturedAt !== '' ? date('d M Y, h:i A', strtotime($openingCapturedAt)) : 'Not captured') ?></span>
                                <span>Closing: <?= e($dailyStockReport['closing_status'] ?? 'Live Estimate') ?><?= $closingCapturedAt !== '' ? ' - ' . e(date('d M Y, h:i A', strtotime($closingCapturedAt))) : '' ?></span>
                            </div>
                        </div>

                        <div class="daily-stock-toolbar" style="align-items:center">
                            <form method="POST" class="daily-stock-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="capture_daily_opening_stock">
                                <input type="hidden" name="report_date" value="<?= e($reportDateValue) ?>">
                                <input type="hidden" name="report_warehouse" value="<?= e($reportWarehouseValue) ?>">
                                <button type="submit" class="details-btn" style="background:#f0fdf4;color:#166534;border:1px solid #86efac"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?> Capture Opening Stock</button>
                            </form>
                            <form method="POST" class="daily-stock-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="capture_daily_closing_stock">
                                <input type="hidden" name="report_date" value="<?= e($reportDateValue) ?>">
                                <input type="hidden" name="report_warehouse" value="<?= e($reportWarehouseValue) ?>">
                                <button type="submit" class="details-btn" style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('download') : '' ?> Capture Closing Stock</button>
                            </form>
                        </div>

                        <div class="daily-stock-grid">
                            <div class="daily-stock-card"><small>Opening Stock</small><strong><?= number_format((int)($dailyTotals['opening_crates'] ?? 0)) ?></strong><span>Crates / packs, <?= number_format((int)($dailyTotals['opening_units'] ?? 0)) ?> units</span></div>
                            <div class="daily-stock-card"><small>Closing Stock</small><strong><?= number_format((int)($dailyTotals['closing_crates'] ?? 0)) ?></strong><span>Crates / packs, <?= number_format((int)($dailyTotals['closing_units'] ?? 0)) ?> units</span></div>
                            <div class="daily-stock-card"><small>Stock Change</small><strong class="daily-stock-delta"><?= ((int)($dailyTotals['crate_change'] ?? 0)) > 0 ? '+' : '' ?><?= number_format((int)($dailyTotals['crate_change'] ?? 0)) ?></strong><span><?= ((int)($dailyTotals['unit_change'] ?? 0)) > 0 ? '+' : '' ?><?= number_format((int)($dailyTotals['unit_change'] ?? 0)) ?> unit movement</span></div>
                            <div class="daily-stock-card"><small>Closing Value</small><strong><?= e($formatMoney((float)($dailyTotals['closing_value'] ?? 0))) ?></strong><span>Opening: <?= e($formatMoney((float)($dailyTotals['opening_value'] ?? 0))) ?></span></div>
                        </div>

                        <div class="inventory-table-wrap" style="margin:0">
                            <table class="top-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Pack Size</th>
                                        <th>Opening</th>
                                        <th>Closing</th>
                                        <th>Change</th>
                                        <th>Opening Value</th>
                                        <th>Closing Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dailyRows as $row): ?>
                                        <?php $rowDelta = (int)($row['stock_change'] ?? 0); ?>
                                        <tr>
                                            <td><strong><?= e((string)$row['name']) ?></strong><div style="font-size:0.68rem;color:#64748b"><?= e((string)$row['category']) ?></div></td>
                                            <td style="font-family:monospace;font-weight:800"><?= e((string)$row['sku']) ?></td>
                                            <td><?= number_format((int)$row['pack_size']) ?> per pack/crate</td>
                                            <td style="font-weight:800"><?= number_format((int)$row['opening_stock']) ?></td>
                                            <td style="font-weight:800"><?= number_format((int)$row['closing_stock']) ?></td>
                                            <td style="font-weight:900;color:<?= $rowDelta < 0 ? '#dc2626' : '#059669' ?>"><?= $rowDelta > 0 ? '+' : '' ?><?= number_format($rowDelta) ?></td>
                                            <td><?= e($formatMoney((float)$row['opening_value'])) ?></td>
                                            <td style="font-weight:800"><?= e($formatMoney((float)$row['closing_value'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($dailyRows)): ?>
                                        <tr><td colspan="8" style="text-align:center;color:#64748b;padding:2rem">No stock rows available for this report.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            <?php elseif ($activeTab === 'tab-inventory'): 
            ?>
                <!-- =================================================================
                     DEDICATED VIEW: Stock, FEFO & Crates
                     ================================================================= -->
                    <?php
                    $totalSkus = count($warehouseProducts);
                    $totalStockCrates = array_sum(array_map(fn($p) => (int)($p['stock_crates'] ?? 0), $warehouseProducts));
                    $totalBottlesForInventory = array_sum(array_map(fn($p) => (int)($p['stock_bottles'] ?? 0), $warehouseProducts));
                    $lowStockCount = count(array_filter($warehouseProducts, fn($p) => (int)($p['stock_crates'] ?? 0) > 0 && (int)($p['stock_crates'] ?? 0) <= 10));
                    $expiredOrExpiringCount = count(array_filter($warehouseProducts, static function (array $p): bool {
                        $expiry = trim((string)($p['expiry_date'] ?? ''));
                        return $expiry !== '' && strtotime($expiry) !== false && strtotime($expiry) <= strtotime('+30 days');
                    }));
                    $outOfStockCount = count(array_filter($warehouseAllProducts, static fn(array $p): bool => (int)($p['stock_crates'] ?? 0) === 0));
                    $inventoryTransfers = [];
                    try {
                        $inventoryTransfers = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getTransfers();
                        $pendingTransfers = count(array_filter($inventoryTransfers, static fn(array $transfer): bool => ($transfer['status'] ?? '') === 'In Transit'));
                    } catch (\Throwable $t) {
                        $pendingTransfers = 0;
                    }
                    $calculatedStockValue = array_sum(array_map(fn($p) => ((int)($p['stock_crates'] ?? 0)) * ((float)($p['cost_price'] ?? $p['wholesale_price'] ?? 0)), $warehouseProducts));
                    $stockValueShort = $calculatedStockValue >= 1000000
                        ? '₦' . number_format($calculatedStockValue / 1000000, 1) . 'M'
                        : '₦' . number_format($calculatedStockValue, 2);
                    $inventoryHeroTitle = 'Warehouse & Inventory';
                    $inventoryHeroSubtitle = 'Showing products and stock currently available in ' . $selectedWarehouse;
                    if ($inventoryDetail === 'available') {
                        $inventoryHeroTitle = 'Stock Levels';
                        $inventoryHeroSubtitle = 'Showing live stock levels and available beverage products in ' . $selectedWarehouse;
                    } elseif ($inventoryDetail === 'zones') {
                        $inventoryHeroTitle = 'Warehouse Zones';
                        $inventoryHeroSubtitle = 'Compare stock distribution between warehouse locations.';
                    } elseif ($inventoryDetail === 'low-stock') {
                        $inventoryHeroTitle = 'Low Stock Products';
                        $inventoryHeroSubtitle = 'Products that need attention in ' . $selectedWarehouse;
                    } elseif ($inventoryDetail === 'fefo') {
                        $inventoryHeroTitle = 'FEFO & Expiry Watch';
                        $inventoryHeroSubtitle = 'Products expiring within 30 days in ' . $selectedWarehouse;
                    } elseif ($inventoryDetail === 'out-of-stock') {
                        $inventoryHeroTitle = 'Out of Stock Products';
                        $inventoryHeroSubtitle = 'Products registered in the catalog but not currently stocked in ' . $selectedWarehouse;
                    }
                    $inventoryDetailUrl = static fn(string $detail): string => url('beverage_warehouse.php?tab=tab-inventory&warehouse=' . rawurlencode($selectedWarehouse) . '&detail=' . rawurlencode($detail));
                    ?>
	                <div class="warehouse-page">
	                    <section class="warehouse-hero">
	                        <div class="warehouse-hero-title">
	                            <div class="warehouse-hero-icon" style="color:#2563eb"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('box') : '' ?></div>
	                            <div>
	                                <h2><?= e($inventoryHeroTitle) ?></h2>
	                                <p><?= e($inventoryHeroSubtitle) ?></p>
	                            </div>
	                        </div>
	                        <div class="warehouse-hero-actions">
	                            <select class="warehouse-select" aria-label="Warehouse location" onchange="window.location.href='<?= url('beverage_warehouse.php?tab=tab-inventory&warehouse=') ?>' + encodeURIComponent(this.value)">
                                    <?php foreach ($warehouses as $warehouseName): ?>
                                        <option value="<?= e($warehouseName) ?>" <?= $warehouseName === $selectedWarehouse ? 'selected' : '' ?>><?= e($warehouseName) ?></option>
                                    <?php endforeach; ?>
                                </select>
	                            <button type="button" class="warehouse-register-btn" onclick="openRegisterProductModal()"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('plus') : '' ?> Register Product <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '' ?></button>
	                        </div>
	                    </section>

	                    <section class="warehouse-metric-row">
	                        <div class="warehouse-stat metric-link <?= $inventoryDetail === 'all' ? 'active' : '' ?>" onclick="window.location.href='<?= e($inventoryDetailUrl('all')) ?>'" role="button" tabindex="0"><div class="warehouse-stat-icon" style="background:#f3e8ff;color:#7c3aed"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('box') : '' ?></div><div><small>Total SKUs</small><strong><?= number_format($totalSkus) ?></strong><span>Real products registered</span></div></div>
	                        <div class="warehouse-stat metric-link <?= $inventoryDetail === 'units' ? 'active' : '' ?>" onclick="window.location.href='<?= e($inventoryDetailUrl('units')) ?>'" role="button" tabindex="0"><div class="warehouse-stat-icon" style="background:#eff6ff;color:#2563eb"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('warehouse') : '' ?></div><div><small>Total Units</small><strong><?= number_format($totalBottlesForInventory) ?></strong><span>Bottles / units in stock</span></div></div>
	                        <div class="warehouse-stat metric-link <?= $inventoryDetail === 'available' ? 'active' : '' ?>" onclick="window.location.href='<?= e($inventoryDetailUrl('available')) ?>'" role="button" tabindex="0"><div class="warehouse-stat-icon" style="background:#dcfce7;color:#16a34a"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?></div><div><small>Available Stock</small><strong><?= number_format($totalStockCrates) ?></strong><span>Crates / packs available</span></div></div>
	                        <div class="warehouse-stat metric-link <?= $inventoryDetail === 'stock-value' ? 'active' : '' ?>" onclick="window.location.href='<?= e($inventoryDetailUrl('stock-value')) ?>'" role="button" tabindex="0"><div class="warehouse-stat-icon" style="background:#fff7ed;color:#f97316"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('analytics') : '' ?></div><div><small>Stock Value</small><strong><?= e($stockValueShort) ?></strong><span>From real stock prices</span></div></div>
	                        <div class="warehouse-stat metric-link <?= $inventoryDetail === 'low-stock' ? 'active' : '' ?>" onclick="window.location.href='<?= e($inventoryDetailUrl('low-stock')) ?>'" role="button" tabindex="0"><div class="warehouse-stat-icon" style="background:#fee2e2;color:#ef4444"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('warning') : '' ?></div><div><small>Low Stock</small><strong style="color:#dc2626"><?= number_format($lowStockCount) ?></strong><span style="color:#dc2626">Needs attention</span></div></div>
	                        <div class="warehouse-stat metric-link <?= $inventoryDetail === 'fefo' ? 'active' : '' ?>" onclick="window.location.href='<?= e($inventoryDetailUrl('fefo')) ?>'" role="button" tabindex="0"><div class="warehouse-stat-icon" style="background:#ffedd5;color:#ea580c"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('bell') : '' ?></div><div><small>Expired / FEFO</small><strong style="color:#ea580c"><?= number_format($expiredOrExpiringCount) ?></strong><span style="color:#ea580c">Expires within 30 days</span></div></div>
	                    </section>

	                    <section class="warehouse-grid-4">
	                        <div class="warehouse-card">
	                            <div class="warehouse-card-head"><h3>Stock Overview</h3></div>
	                            <div class="donut-wrap">
	                                <div class="donut-chart"><div class="donut-hole"><strong><?= number_format($totalBottlesForInventory) ?></strong><span>Total Units</span></div></div>
	                                <div class="legend-list">
	                                    <div class="legend-line"><i class="legend-dot" style="background:#10b981"></i><span>Available<br><strong><?= number_format($totalStockCrates) ?> crates</strong></span></div>
	                                    <div class="legend-line"><i class="legend-dot" style="background:#60a5fa"></i><span>Reserved<br><strong>0 crates</strong></span></div>
	                                    <div class="legend-line"><i class="legend-dot" style="background:#a855f7"></i><span>In Transit<br><strong><?= number_format($pendingTransfers) ?> transfers</strong></span></div>
	                                    <div class="legend-line"><i class="legend-dot" style="background:#ef4444"></i><span>Damaged<br><strong>0 crates</strong></span></div>
	                                </div>
	                            </div>
	                        </div>

		                        <div class="warehouse-card">
		                            <div class="warehouse-card-head"><div><h3>Inventory Health</h3><p>Overall Stock Health</p></div></div>
		                            <div class="health-gauge"></div>
		                            <div class="health-score"><strong><?= $totalSkus > 0 ? 'Ready' : 'No Data' ?></strong><span><?= $totalSkus > 0 ? 'Real products active' : 'Add products to begin' ?></span></div>
		                            <div class="health-mini"><div><strong><?= number_format($totalSkus) ?></strong><span>SKUs</span></div><div><strong style="color:#ea580c"><?= number_format($lowStockCount) ?></strong><span>Low stock</span></div><div><strong><?= count($warehouses) ?></strong><span>Warehouses</span></div></div>
		                        </div>

		                        <div class="warehouse-card">
		                            <div class="warehouse-card-head"><div><h3>AI Warehouse Watch</h3><p>Training and exception monitor</p></div><span class="fefo-good"><?= e((string)($aiSupervisorBrief['score'] ?? 100)) ?>%</span></div>
		                            <div class="alert-list">
		                                <?php foreach (array_slice($aiSupervisorBrief['watch_alerts'] ?? [], 0, 2) as $alert): ?>
		                                    <div class="alert-row"><div class="alert-icon" style="background:#eff6ff"><img src="<?= e(url('assets/images/warehouse_overview.svg')) ?>" alt=""></div><div><strong><?= e($alert['title']) ?></strong><span><?= e($alert['action']) ?></span></div></div>
		                                <?php endforeach; ?>
		                                <?php foreach (array_slice($aiSupervisorBrief['training_tasks'] ?? [], 0, 2) as $task): ?>
		                                    <div class="alert-row"><div class="alert-icon" style="background:#ecfdf5"><img src="<?= e(url('assets/images/coke_crate.svg')) ?>" alt=""></div><div><strong><?= e($task['module']) ?> Training</strong><span><?= e($task['lesson']) ?></span></div></div>
		                                <?php endforeach; ?>
		                            </div>
		                        </div>

		                        <div class="warehouse-card">
	                            <div class="warehouse-card-head"><h3>Stock Alerts</h3></div>
	                            <div class="alert-list">
	                                <div class="alert-row"><div class="alert-icon" style="background:#fee2e2"><img src="<?= e(url('assets/images/warehouse_overview.svg')) ?>" alt=""></div><div><strong>Low Stock</strong><span><?= number_format($lowStockCount) ?> products need reordering</span></div><a class="link-mini" href="<?= e(url('beverage_warehouse.php?tab=tab-inventory&detail=low-stock')) ?>">View</a></div>
	                                <div class="alert-row"><div class="alert-icon" style="background:#ffedd5"><img src="<?= e(url('assets/images/warehouse_overview.svg')) ?>" alt=""></div><div><strong>FEFO Expiring Soon</strong><span><?= number_format($expiredOrExpiringCount) ?> products expiring within 30 days</span></div><a class="link-mini" href="<?= e(url('beverage_warehouse.php?tab=tab-inventory&detail=fefo')) ?>">View</a></div>
	                                <div class="alert-row"><div class="alert-icon" style="background:#fee2e2"><img src="<?= e(url('assets/images/warehouse_overview.svg')) ?>" alt=""></div><div><strong>Out of Stock</strong><span><?= number_format($outOfStockCount) ?> products not stocked here</span></div><a class="link-mini" href="<?= e($inventoryDetailUrl('out-of-stock')) ?>">View</a></div>
	                            </div>
	                            <div style="text-align:center;margin-top:0.85rem"><a class="link-mini" href="<?= e(url('dashboard.php?tab=tab-notifications&company=beverage')) ?>">View all alerts <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('arrow-right') : '' ?></a></div>
	                        </div>

	                        <div class="warehouse-card">
	                            <div class="warehouse-card-head"><h3>Recent Movements</h3><button type="button" class="warehouse-select" style="padding:0.38rem 0.55rem">Today <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '' ?></button></div>
	                            <div class="movement-list">
                                    <?php
                                    try {
                                        $recentMovements = array_slice(\App\Modules\BeverageWarehouse\BeverageWarehouseService::getAuditTrail(), 0, 4);
                                    } catch (\Throwable $t) {
                                        $recentMovements = [];
                                    }
                                    ?>
                                    <?php if (empty($recentMovements)): ?>
                                        <div style="color:#64748b;font-size:0.8rem;padding:0.7rem 0">No warehouse movements yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($recentMovements as $movement): ?>
	                                        <div class="movement-row"><div class="movement-icon" style="background:#e0f2fe"><img src="<?= e(url('assets/images/warehouse_overview.svg')) ?>" alt=""></div><div><strong><?= e((string)($movement['action'] ?? 'Movement')) ?></strong></div><span><?= e((string)($movement['quantity'] ?? '')) ?>&nbsp;&nbsp;<?= e((string)($movement['timestamp'] ?? '')) ?></span></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
	                            </div>
	                            <div style="text-align:center;margin-top:0.85rem"><a class="link-mini" href="<?= e(url('beverage_pos.php?tab=transfers')) ?>">View all movements <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('arrow-right') : '' ?></a></div>
	                        </div>
	                    </section>

	                    <section class="warehouse-card" id="warehouseZonesPanel">
	                        <div class="warehouse-card-head"><div><h3>Warehouse Zones</h3><p>Real-time stock distribution across warehouse zones</p></div></div>
	                        <div class="zones-row">
	                            <?php foreach ($warehouses as $index => $zone): ?>
                                    <?php
                                    $zoneSummary = $warehouseSummaries[$zone] ?? ['stock_crates' => 0, 'sku_count' => 0];
                                    $zoneCrates = (int)($zoneSummary['stock_crates'] ?? 0);
                                    $allWarehouseCrates = max(1, array_sum(array_map(static fn(array $summary): int => (int)($summary['stock_crates'] ?? 0), $warehouseSummaries)));
                                    $pct = $allWarehouseCrates > 0 ? min(100, (int)round(($zoneCrates / $allWarehouseCrates) * 100)) : 0;
                                    $color = $index === 0 ? '#10b981' : '#2563eb';
                                    $zoneUrl = url('beverage_warehouse.php?tab=tab-inventory&warehouse=' . rawurlencode($zone));
                                    ?>
	                                <a class="zone-card <?= $zone === $selectedWarehouse ? 'active' : '' ?>" href="<?= e($zoneUrl) ?>">
	                                    <div class="zone-card-top"><div class="zone-icon" style="background:<?= e($color) ?>22"><img src="<?= e(url('assets/images/warehouse_overview.svg')) ?>" alt=""></div><div><strong><?= e($zone) ?></strong><span><?= number_format($zoneCrates) ?> crates / <?= number_format((int)($zoneSummary['sku_count'] ?? 0)) ?> SKUs</span></div></div>
	                                    <div class="zone-bar"><div style="width:<?= (int)$pct ?>%;background:<?= e($color) ?>"></div></div>
	                                    <div style="text-align:right;color:<?= e($color) ?>;font-weight:800;font-size:0.76rem;margin-top:0.35rem"><?= (int)$pct ?>%</div>
	                                </a>
	                            <?php endforeach; ?>
	                        </div>
	                    </section>

	                    <section class="warehouse-card" id="warehouseProductsPanel">
	                        <div class="warehouse-card-head" style="flex-wrap:wrap;gap:0.75rem;margin-bottom:0.75rem">
	                            <div style="display:flex;align-items:center;gap:0.65rem">
	                                <h3 style="margin:0;font-size:1.05rem">Products in <?= e($selectedWarehouse) ?></h3>
	                                <span id="inventory_count_badge" class="inventory-count-badge">Showing <?= count($warehouseTableProducts) ?> of <?= count($warehouseTableProducts) ?> items</span>
	                            </div>
	                            <div style="display:flex;gap:0.45rem;align-items:center;flex-wrap:wrap">
	                                <a href="<?= url('beverage_warehouse.php?action=export_inventory_excel') ?>" class="details-btn" style="background:#f0fdf4;color:#166534;border:1px solid #86efac;text-decoration:none" title="Download inventory spreadsheet (.csv / Excel format) to edit offline"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('download') : '' ?> Export to Excel</a>
	                                <button type="button" class="details-btn" style="background:#fefce8;color:#854d0e;border:1px solid #fde047" onclick="openExcelImportModal()" title="Upload updated Excel / CSV spreadsheet to sync inventory &amp; prices"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('upload') : '' ?> Import from Excel</button>
	                                <button type="button" class="details-btn" style="background:#f8fafc;color:#0f766e;border:1px solid #99f6e4" onclick="openOnlineImageSearchModalGeneral()"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('globe') : '' ?> Image Library</button>
	                                <button type="button" class="details-btn" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe" onclick="openRegisterProductModal()"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('plus') : '' ?> Add New Product</button>
	                            </div>
	                        </div>

	                        <!-- Search & Filter Controls -->
	                        <div class="inventory-search-toolbar">
	                            <div class="inventory-search-box">
	                                <span class="inventory-search-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('search') : '' ?></span>
	                                <input type="text" id="inventory_search_input" placeholder="Search by Product Name, SKU, Category, Packaging..." oninput="filterInventoryTable()">
	                                <span id="inventory_search_clear" class="inventory-search-clear" onclick="clearInventorySearch()" title="Clear Search">✕</span>
	                            </div>

	                            <div class="inventory-filters-group">
	                                <select id="inventory_category_filter" class="inventory-filter-select" onchange="filterInventoryTable()">
	                                    <option value="">All Categories</option>
	                                    <?php
	                                    $catOptions = array_unique(array_filter(array_map(fn($p) => trim((string)($p['category'] ?? '')), $warehouseTableProducts)));
	                                    sort($catOptions);
	                                    foreach ($catOptions as $cat):
	                                    ?>
	                                        <option value="<?= e(strtolower($cat)) ?>"><?= e($cat) ?></option>
	                                    <?php endforeach; ?>
	                                </select>

	                                <select id="inventory_stock_filter" class="inventory-filter-select" onchange="filterInventoryTable()">
	                                    <option value="">All Stock Levels</option>
	                                    <option value="in_stock">In Stock (&gt; 0)</option>
	                                    <option value="low_stock">Low Stock (1 - 10 Crates)</option>
	                                    <option value="out_of_stock">Out of Stock (0)</option>
	                                    <option value="high_stock">High Stock (&gt; 50 Crates)</option>
	                                </select>

	                                <select id="inventory_fefo_filter" class="inventory-filter-select" onchange="filterInventoryTable()">
	                                    <option value="">All FEFO Status</option>
	                                    <option value="good">Good Condition (60+ days)</option>
	                                    <option value="expiring">Expiring Soon (≤ 30 days)</option>
	                                </select>

	                                <select id="inventory_sort_filter" class="inventory-filter-select" onchange="filterInventoryTable()">
	                                    <option value="stock_desc">Available Stock: High to Low</option>
	                                    <option value="stock_asc">Available Stock: Low to High</option>
	                                    <option value="in_stock_first">In-Stock Products First</option>
	                                    <option value="value_desc">Stock Value: High to Low</option>
	                                    <option value="value_asc">Stock Value: Low to High</option>
	                                    <option value="price_desc">Unit Cost: High to Low</option>
	                                    <option value="price_asc">Unit Cost: Low to High</option>
	                                    <option value="name_asc">Product Name: A to Z</option>
	                                    <option value="name_desc">Product Name: Z to A</option>
	                                    <option value="sku_asc">SKU Code: A to Z</option>
	                                    <option value="default">Default Catalog Order</option>
	                                </select>

	                                <button type="button" class="details-btn" style="background:#f0f9ff;color:#0284c7;border:1px solid #bae6fd" onclick="toggleSortByAvailableStock()" id="quick_available_sort_btn" title="Toggle Sort by Available Product Stock"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?> Sort by Available Stock</button>
	                                <button type="button" class="inventory-reset-btn" onclick="resetInventoryFilters()" title="Reset All Filters"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('refresh') : '' ?> Reset</button>
	                            </div>
	                        </div>

	                        <div class="inventory-table-wrap" style="margin:0">
	                            <table class="top-table" id="topInventoryTable">
	                                <thead>
	                                    <tr>
	                                        <th class="sortable-th" id="th_col_name" onclick="sortTableByColumn('name')" title="Click to sort by Product Name">Product <span id="sort_icon_name" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?></span></th>
	                                        <th class="sortable-th" id="th_col_sku" onclick="sortTableByColumn('sku')" title="Click to sort by SKU Code">SKU <span id="sort_icon_sku" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?></span></th>
	                                        <th class="sortable-th" id="th_col_category" onclick="sortTableByColumn('category')" title="Click to sort by Category">Category <span id="sort_icon_category" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?></span></th>
	                                        <th class="sortable-th" id="th_col_packaging" onclick="sortTableByColumn('packaging')" title="Click to sort by Packaging">Packaging <span id="sort_icon_packaging" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?></span></th>
	                                        <th class="sortable-th sorted-active" id="th_col_stock" onclick="sortTableByColumn('stock')" title="Click to sort per Available Product Stock" style="background:#eff6ff;color:#1d4ed8">Available Stock <span id="sort_icon_stock" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '' ?></span></th>
	                                        <th class="sortable-th" id="th_col_price" onclick="sortTableByColumn('price')" title="Click to sort by Unit Cost">Unit Cost <span id="sort_icon_price" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?></span></th>
	                                        <th class="sortable-th" id="th_col_value" onclick="sortTableByColumn('value')" title="Click to sort by Stock Value">Stock Value <span id="sort_icon_value" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?></span></th>
	                                        <th class="sortable-th" id="th_col_fefo" onclick="sortTableByColumn('fefo')" title="Click to sort by FEFO Status">FEFO Status <span id="sort_icon_fefo" class="sort-icon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?></span></th>
	                                        <th>Action</th>
	                                    </tr>
	                                </thead>
	                                <tbody id="topInventoryTbody">
	                                    <?php foreach ($warehouseTableProducts as $p): ?>
	                                        <?php
	                                        $productImageSrc = beverage_product_image_src($p['image'] ?? null);
	                                        $productJson = $p;
	                                        $productJson['image_src'] = $productImageSrc;
	                                        $stockCrates = (int)($p['stock_crates'] ?? 0);
	                                        $unitPrice = (float)($p['cost_price'] ?? $p['wholesale_price'] ?? 0);
	                                        $stockValue = $stockCrates * $unitPrice;
	                                        $isExpiring = false;
	                                        $expiry = trim((string)($p['expiry_date'] ?? ''));
	                                        if ($expiry !== '' && strtotime($expiry) !== false && strtotime($expiry) <= strtotime('+30 days')) {
	                                            $isExpiring = true;
	                                        }
	                                        $fefoType = $isExpiring ? 'expiring' : 'good';
	                                        $packagingLabel = str_contains((string)($p['packaging'] ?? ''), 'PET') ? 'PET Crate' : (((float)($p['crate_deposit'] ?? 0) > 0) ? 'Glass Crate' : (string)($p['packaging'] ?? ''));
	                                        ?>
	                                        <tr class="inventory-row" 
	                                            id="product-row-<?= e((string)($p['sku'] ?? '')) ?>"
	                                            data-name="<?= e(strtolower((string)($p['name'] ?? ''))) ?>"
	                                            data-sku="<?= e(strtolower((string)($p['sku'] ?? ''))) ?>"
	                                            data-category="<?= e(strtolower((string)($p['category'] ?? ''))) ?>"
	                                            data-packaging="<?= e(strtolower((string)$packagingLabel)) ?>"
	                                            data-stock="<?= (int)$stockCrates ?>"
	                                            data-price="<?= (float)$unitPrice ?>"
	                                            data-value="<?= (float)$stockValue ?>"
	                                            data-fefo="<?= e($fefoType) ?>">
	                                            <td>
	                                                <div class="mobile-product-main" style="display:flex;align-items:center;gap:0.7rem">
	                                                    <img class="top-table-img product-row-img-<?= e((string)($p['sku'] ?? '')) ?>" src="<?= e($productImageSrc) ?>" alt="<?= e((string)($p['name'] ?? 'Product')) ?>" onerror="this.onerror=null; this.src='<?= e(beverage_product_image_fallback_src()) ?>';">
	                                                    <div>
	                                                        <strong class="product-row-name"><?= e((string)($p['name'] ?? 'Product')) ?></strong>
	                                                        <div class="mobile-product-sku" style="font-size:0.68rem;color:#64748b"><?= e((string)($p['sku'] ?? '')) ?></div>
	                                                        <div class="mobile-product-pack" style="font-size:0.68rem;color:#64748b"><?= e((string)($p['packaging'] ?? '')) ?></div>
	                                                        <span class="mobile-product-category status-pill status-high"><?= e((string)($p['category'] ?? '')) ?></span>
	                                                    </div>
	                                                </div>
	                                            </td>
	                                            <td style="font-family:monospace;font-weight:700"><?= e((string)($p['sku'] ?? '')) ?></td>
	                                            <td><span class="status-pill status-high" style="font-size:0.66rem"><?= e((string)($p['category'] ?? '')) ?></span></td>
	                                            <td><?= e($packagingLabel) ?></td>
	                                            <td style="color:#059669;font-weight:800"><?= number_format($stockCrates) ?> <?= ($p['category'] ?? '') === 'Water' ? 'Packs' : 'Crates' ?></td>
	                                            <td style="font-weight:800">₦<?= number_format($unitPrice, 2) ?></td>
	                                            <td style="font-weight:800">₦<?= number_format($stockValue, 2) ?></td>
	                                            <td>
	                                                <?php if ($isExpiring): ?>
	                                                    <span class="fefo-warn"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('warning') : '' ?> Expiring Soon</span>
	                                                <?php else: ?>
	                                                    <span class="fefo-good"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?> Good (<?= ($p['category'] ?? '') === 'Water' ? '120+' : (((float)($p['crate_deposit'] ?? 0) > 0) ? '75' : '60+') ?> days)</span>
	                                                <?php endif; ?>
	                                            </td>
	                                            <td>
	                                                <div style="display:flex;gap:0.35rem;align-items:center;flex-wrap:nowrap">
		                                                    <button type="button" class="details-btn icon-only" data-product='<?= json_encode($productJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' onclick="openEditProductModalFromBtn(this)" title="Edit Details" aria-label="Edit Details"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('edit') : '' ?></button>
		                                                    <button type="button" class="details-btn icon-only" style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0" data-product='<?= json_encode($productJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' onclick="openOnlineImageSearchFromBtn(this)" title="Find Online Image & Update" aria-label="Find Online Image and Update"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('image') : '' ?></button>
		                                                    <button type="button" class="details-btn icon-only" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe" data-product='<?= json_encode($productJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>' onclick="openProductAuditModalFromBtn(this)" title="View Audit Trail" aria-label="View Audit Trail"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('log') : '' ?></button>
	                                                </div>
	                                            </td>
	                                        </tr>
	                                    <?php endforeach; ?>
	                                    <tr id="noInventoryMatchRow" style="display:none">
	                                        <td colspan="9" style="text-align:center;padding:2.5rem 1rem;color:#64748b;background:#f8fafc">
	                                            <div style="display:flex;justify-content:center;color:#94a3b8;margin-bottom:0.4rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('search') : '' ?></div>
	                                            <strong style="font-size:0.92rem;color:#0f172a;display:block">No products showing for <?= e($selectedWarehouse) ?></strong>
	                                            <p style="font-size:0.78rem;margin:0.25rem 0 0.8rem">Try another warehouse, search term, category, or clear your filters.</p>
	                                            <button type="button" class="inventory-reset-btn" onclick="resetInventoryFilters()" style="padding:0.45rem 1rem;font-weight:800;color:#2563eb;border-color:#bfdbfe;background:#eff6ff">Clear All Filters</button>
	                                        </td>
	                                    </tr>
	                                </tbody>
	                            </table>
	                        </div>
	                    </section>
	                </div>
            <?php else: ?>
                <!-- Default Overview Tab (tab-dash) -->
                <?php
                try {
                    $warehouses = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')
                        ? \App\Modules\BeverageWarehouse\BeverageWarehouseService::getWarehouses()
                        : ['Jacroxx Warehouse', 'Ijaba Warehouse'];
                } catch (\Throwable $t) {
                    $warehouses = ['Jacroxx Warehouse', 'Ijaba Warehouse'];
                }
                try {
                    $liveKpis = class_exists('App\Core\UnifiedDataEngine') ? \App\Core\UnifiedDataEngine::getLiveKpis('beverage') : [];
                } catch (\Throwable $t) {
                    $liveKpis = [];
                }
                $totalSkus = count($products);
                $rawCrates = (int)($liveKpis['total_crates'] ?? array_sum(array_map(fn($p) => (int)($p['stock_crates'] ?? 0), $products)));
                $rawBottles = (int)array_sum(array_map(fn($p) => (int)($p['stock_bottles'] ?? 0), $products));
                $rawInventoryValue = (float)($liveKpis['total_inventory_value'] ?? array_sum(array_map(fn($p) => ((int)($p['stock_crates'] ?? 0)) * ((float)($p['cost_price'] ?? $p['wholesale_price'] ?? 0)), $products)));
                $lowStockCount = count(array_filter($products, fn($p) => (int)($p['stock_crates'] ?? 0) > 0 && (int)($p['stock_crates'] ?? 0) <= 10));
                $outOfStockCount = count(array_filter($products, fn($p) => (int)($p['stock_crates'] ?? 0) === 0));
                $expiredOrExpiringCount = count(array_filter($products, static function (array $p): bool {
                    $expiry = trim((string)($p['expiry_date'] ?? ''));
                    return $expiry !== '' && strtotime($expiry) !== false && strtotime($expiry) <= strtotime('+30 days');
                }));
                try {
                    $pendingTransfers = count(array_filter(\App\Modules\BeverageWarehouse\BeverageWarehouseService::getTransfers(), static fn(array $transfer): bool => ($transfer['status'] ?? '') === 'In Transit'));
                } catch (\Throwable $t) {
                    $pendingTransfers = 0;
                }
                $inventoryValue = number_format($rawInventoryValue, 2);
                $availableCrates = number_format($rawCrates);
                $totalBottles = number_format($rawBottles);
                $dashboardDetail = strtolower(trim((string)($_GET['detail'] ?? '')));
                $dashboardDetailUrl = static fn(string $detail): string => url('beverage_warehouse.php?tab=tab-dash&detail=' . rawurlencode($detail));
                $dashboardCards = [
                    ['key' => 'all', 'label' => 'Total SKUs', 'value' => number_format($totalSkus), 'hint' => 'Real products registered', 'icon' => 'box', 'color' => '#7c3aed'],
                    ['key' => 'units', 'label' => 'Total Units', 'value' => $totalBottles, 'hint' => 'Bottles / units in stock', 'icon' => 'warehouse', 'color' => '#2563eb'],
                    ['key' => 'available', 'label' => 'Available Stock', 'value' => $availableCrates, 'hint' => 'Crates / packs available', 'icon' => 'check', 'color' => '#16a34a'],
                    ['key' => 'stock-value', 'label' => 'Stock Value', 'value' => '₦' . ($rawInventoryValue >= 1000000 ? number_format($rawInventoryValue / 1000000, 1) . 'M' : $inventoryValue), 'hint' => 'From real stock prices', 'icon' => 'analytics', 'color' => '#f97316'],
                    ['key' => 'low-stock', 'label' => 'Low Stock', 'value' => number_format($lowStockCount), 'hint' => 'Needs attention', 'icon' => 'warning', 'color' => '#ef4444'],
                    ['key' => 'fefo', 'label' => 'Expired / FEFO', 'value' => number_format($expiredOrExpiringCount), 'hint' => 'Expires within 30 days', 'icon' => 'bell', 'color' => '#ea580c'],
                ];
                ?>
                <div class="overview-dashboard">
                    <section class="overview-kpis">
                        <?php foreach ($dashboardCards as $card): ?>
                            <div class="overview-kpi metric-link <?= $dashboardDetail === $card['key'] ? 'active' : '' ?>" onclick="window.location.href='<?= e($dashboardDetailUrl($card['key'])) ?>'" role="button" tabindex="0">
                                <div class="overview-kpi-top">
                                    <div class="overview-kpi-icon" style="background:<?= e($card['color']) ?>22;color:<?= e($card['color']) ?>"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon($card['icon']) : '' ?></div>
                                    <div><small><?= e($card['label']) ?></small><strong><?= e($card['value']) ?></strong><span><?= e($card['hint']) ?></span></div>
                                </div>
                                <svg class="spark" viewBox="0 0 180 42"><path d="M0 31 L18 28 L36 30 L54 22 L72 27 L90 16 L108 26 L126 19 L144 28 L162 15 L180 21" fill="none" stroke="<?= e($card['color']) ?>" stroke-width="3"/></svg>
                            </div>
                        <?php endforeach; ?>
	                    </section>

	                    <section class="warehouse-metrics-card">
	                        <div class="warehouse-metrics-head">
	                            <h3>Key Dashboard Metrics</h3>
	                            <span>Warehouse-wide inventory control summary</span>
	                        </div>
	                        <div class="warehouse-metrics-grid">
	                            <div class="warehouse-metric metric-link" onclick="window.location.href='<?= e($dashboardDetailUrl('all')) ?>'" role="button" tabindex="0"><small>Total SKUs</small><strong><?= number_format($totalSkus) ?></strong></div>
	                            <div class="warehouse-metric metric-link" onclick="window.location.href='<?= e($dashboardDetailUrl('units')) ?>'" role="button" tabindex="0"><small>Total Stock Units</small><strong><?= $totalBottles ?></strong></div>
	                            <div class="warehouse-metric good metric-link" onclick="window.location.href='<?= e($dashboardDetailUrl('available')) ?>'" role="button" tabindex="0"><small>Available Stock</small><strong><?= $availableCrates ?></strong></div>
	                            <div class="warehouse-metric warn"><small>Reserved Stock</small><strong>0</strong></div>
	                            <div class="warehouse-metric"><small>Issued to Field</small><strong>0</strong></div>
	                            <div class="warehouse-metric danger metric-link" onclick="window.location.href='<?= e($dashboardDetailUrl('low-stock')) ?>'" role="button" tabindex="0"><small>Low Stock Items</small><strong><?= number_format($lowStockCount) ?></strong></div>
	                            <div class="warehouse-metric danger"><small>Damaged Items</small><strong>0</strong></div>
	                            <div class="warehouse-metric warn"><small>Pending Transfers</small><strong><?= number_format($pendingTransfers) ?></strong></div>
	                            <div class="warehouse-metric"><small>Pending Returns</small><strong>0</strong></div>
	                            <div class="warehouse-metric metric-link" onclick="window.location.href='<?= e($dashboardDetailUrl('stock-value')) ?>'" role="button" tabindex="0"><small>Stock Value</small><strong>₦<?= $inventoryValue ?></strong></div>
	                        </div>
	                    </section>

                        <?php if ($dashboardDetail !== ''): ?>
                            <?php
                            $dashboardDetailProducts = $products;
                            $dashboardDetailTitle = 'Dashboard Details';
                            if ($dashboardDetail === 'low-stock') {
                                $dashboardDetailTitle = 'Low Stock Products';
                                $dashboardDetailProducts = array_values(array_filter($products, static fn(array $p): bool => (int)($p['stock_crates'] ?? 0) > 0 && (int)($p['stock_crates'] ?? 0) <= 10));
                            } elseif ($dashboardDetail === 'fefo') {
                                $dashboardDetailTitle = 'Expired / FEFO Products';
                                $dashboardDetailProducts = array_values(array_filter($products, static function (array $p): bool {
                                    $expiry = trim((string)($p['expiry_date'] ?? ''));
                                    return $expiry !== '' && strtotime($expiry) !== false && strtotime($expiry) <= strtotime('+30 days');
                                }));
                            } elseif ($dashboardDetail === 'stock-value') {
                                $dashboardDetailTitle = 'Products by Stock Value';
                                usort($dashboardDetailProducts, static function (array $a, array $b): int {
                                    $aValue = ((int)($a['stock_crates'] ?? 0)) * ((float)($a['cost_price'] ?? $a['wholesale_price'] ?? 0));
                                    $bValue = ((int)($b['stock_crates'] ?? 0)) * ((float)($b['cost_price'] ?? $b['wholesale_price'] ?? 0));
                                    return $bValue <=> $aValue;
                                });
                            } elseif ($dashboardDetail === 'units') {
                                $dashboardDetailTitle = 'Products by Total Units';
                                usort($dashboardDetailProducts, static fn(array $a, array $b): int => ((int)($b['stock_bottles'] ?? 0)) <=> ((int)($a['stock_bottles'] ?? 0)));
                            } else {
                                $dashboardDetailTitle = 'Registered Beverage Products';
                                usort($dashboardDetailProducts, static fn(array $a, array $b): int => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
                            }
                            ?>
                            <section class="warehouse-card">
                                <div class="warehouse-card-head">
                                    <div>
                                        <h3><?= e($dashboardDetailTitle) ?></h3>
                                        <p>Dashboard detail from current beverage warehouse stock.</p>
                                    </div>
                                    <a href="<?= e(url('beverage_warehouse.php?tab=tab-dash')) ?>">Close Details</a>
                                </div>
                                <?php if (empty($dashboardDetailProducts)): ?>
                                    <div style="color:#64748b;font-size:0.84rem;padding:1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px">No records found for this dashboard card yet.</div>
                                <?php else: ?>
                                    <div class="inventory-table-wrap" style="margin:0">
                                        <table class="top-table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>SKU</th>
                                                    <th>Category</th>
                                                    <th>Stock</th>
                                                    <th>Total Units</th>
                                                    <th>Unit Cost</th>
                                                    <th>Stock Value</th>
                                                    <th>Expiry</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($dashboardDetailProducts, 0, 25) as $p): ?>
                                                    <?php
                                                    $stockCrates = (int)($p['stock_crates'] ?? 0);
                                                    $stockBottles = (int)($p['stock_bottles'] ?? 0);
                                                    $unitPrice = (float)($p['cost_price'] ?? $p['wholesale_price'] ?? 0);
                                                    $stockValue = $stockCrates * $unitPrice;
                                                    ?>
                                                    <tr>
                                                        <td><strong><?= e((string)($p['name'] ?? 'Product')) ?></strong></td>
                                                        <td style="font-family:monospace;font-weight:700"><?= e((string)($p['sku'] ?? '')) ?></td>
                                                        <td><?= e((string)($p['category'] ?? '')) ?></td>
                                                        <td style="font-weight:800;color:#059669"><?= number_format($stockCrates) ?></td>
                                                        <td><?= number_format($stockBottles) ?></td>
                                                        <td>₦<?= number_format($unitPrice, 2) ?></td>
                                                        <td style="font-weight:800">₦<?= number_format($stockValue, 2) ?></td>
                                                        <td><?= e((string)($p['expiry_date'] ?? 'No date')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>

	                    <section class="overview-row-main">
                        <div class="overview-card">
                            <div class="overview-card-head"><h3>Inventory Trend</h3><button type="button">This Month <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '' ?></button></div>
                            <div class="legend"><span style="color:#2563eb">■ Inventory Value (₦)</span></div>
                            <svg class="line-chart" viewBox="0 0 520 180" preserveAspectRatio="none">
                                <defs><linearGradient id="inventoryArea" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#2563eb" stop-opacity=".24"/><stop offset="1" stop-color="#2563eb" stop-opacity="0"/></linearGradient></defs>
                                <path d="M0 120 L46 103 L92 78 L138 61 L184 73 L230 86 L276 70 L322 61 L368 89 L414 72 L468 42 L520 27 L520 180 L0 180 Z" fill="url(#inventoryArea)"/>
                                <path d="M0 120 L46 103 L92 78 L138 61 L184 73 L230 86 L276 70 L322 61 L368 89 L414 72 L468 42 L520 27" fill="none" stroke="#2563eb" stroke-width="4"/>
                                <g fill="#2563eb"><circle cx="92" cy="78" r="4"/><circle cx="184" cy="73" r="4"/><circle cx="322" cy="61" r="4"/><circle cx="468" cy="42" r="4"/></g>
                            </svg>
                            <div class="axis-row"><span>May 1</span><span>May 6</span><span>May 11</span><span>May 16</span><span>May 21</span><span>May 26</span><span>May 31</span></div>
                        </div>

                        <div class="overview-card">
                            <div class="overview-card-head"><h3>Inventory Flow</h3></div>
                            <div class="legend"><span style="color:#22c55e">■ Received</span><span style="color:#2563eb">■ Dispatched</span><span style="color:#f97316">■ Returns</span></div>
                            <svg class="bar-chart" viewBox="0 0 260 180">
                                <?php foreach ([92,118,86,96] as $i => $h): $x = 24 + ($i * 56); ?>
                                    <rect x="<?= $x ?>" y="<?= 170 - $h ?>" width="28" height="<?= $h ?>" fill="#22c55e" rx="3"/>
                                    <rect x="<?= $x ?>" y="<?= 170 - ($h * .58) ?>" width="28" height="<?= $h * .58 ?>" fill="#2563eb" rx="3"/>
                                    <rect x="<?= $x ?>" y="<?= 154 ?>" width="28" height="16" fill="#f97316" rx="3"/>
                                <?php endforeach; ?>
                            </svg>
                            <div class="axis-row"><span>Wk 19</span><span>Wk 20</span><span>Wk 21</span><span>Wk 22</span></div>
                        </div>

                        <div class="overview-card">
                            <div class="overview-card-head"><h3>Category Stock Summary</h3><button type="button">By Crates <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '' ?></button></div>
                            <?php
                            $categoryTotals = [];
                            foreach ($products as $product) {
                                $categoryName = trim((string)($product['category'] ?? 'Uncategorized')) ?: 'Uncategorized';
                                $categoryTotals[$categoryName] = ($categoryTotals[$categoryName] ?? 0) + (int)($product['stock_crates'] ?? 0);
                            }
                            arsort($categoryTotals);
                            $categories = [];
                            foreach ($categoryTotals as $name => $stock) {
                                $pct = $rawCrates > 0 ? min(100, (int)round(($stock / $rawCrates) * 100)) : 0;
                                $statusClass = $stock <= 10 ? 'status-low' : ($stock <= 50 ? 'status-mid' : 'status-high');
                                $status = $stock <= 10 ? 'Low' : ($stock <= 50 ? 'Moderate' : 'Healthy');
                                $categories[] = [$name, number_format($stock), $pct, '#2563eb', $statusClass, $status];
                            }
                            ?>
                            <?php if (empty($categories)): ?>
                                <div style="color:#64748b;font-size:0.82rem;padding:0.9rem 0">No category stock yet.</div>
                            <?php endif; ?>
                            <?php foreach ($categories as [$name, $stock, $pct, $color, $statusClass, $status]): ?>
                                <div class="category-row">
                                    <strong><?= e($name) ?></strong>
                                    <span><?= e($stock) ?></span>
                                    <div class="progress-track"><div class="progress-fill" style="width:<?= (int)$pct ?>%; background:<?= e($color) ?>"></div></div>
                                    <span class="status-pill <?= e($statusClass) ?>"><?= e($status) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="overview-row-3">
                        <div class="overview-card">
                            <div class="overview-card-head"><h3>Warehouse Zone Utilization</h3><a href="<?= e(url('beverage_warehouse.php?tab=tab-inventory&warehouse=' . rawurlencode($warehouses[0] ?? $selectedWarehouse))) ?>">View Products</a></div>
                            <div class="zone-map">
                                <?php
                                $dashTotalWarehouseCrates = max(1, array_sum(array_map(static fn(array $summary): int => (int)($summary['stock_crates'] ?? 0), $warehouseSummaries)));
                                $dashWarehouseOne = $warehouses[0] ?? 'Warehouse 1';
                                $dashWarehouseTwo = $warehouses[1] ?? 'Warehouse 2';
                                $dashWarehouseOnePct = min(100, (int)round((((int)($warehouseSummaries[$dashWarehouseOne]['stock_crates'] ?? 0)) / $dashTotalWarehouseCrates) * 100));
                                $dashWarehouseTwoPct = min(100, (int)round((((int)($warehouseSummaries[$dashWarehouseTwo]['stock_crates'] ?? 0)) / $dashTotalWarehouseCrates) * 100));
                                ?>
                                <a class="zone" href="<?= e(url('beverage_warehouse.php?tab=tab-inventory&warehouse=' . rawurlencode($dashWarehouseOne))) ?>" style="left:4%;top:10%;width:42%;height:76%;background:rgba(34,197,94,.18)"><?= e($dashWarehouseOne) ?><strong><?= (int)$dashWarehouseOnePct ?>%</strong></a>
                                <a class="zone" href="<?= e(url('beverage_warehouse.php?tab=tab-inventory&warehouse=' . rawurlencode($dashWarehouseTwo))) ?>" style="left:52%;top:10%;width:42%;height:76%;background:rgba(37,99,235,.16)"><?= e($dashWarehouseTwo) ?><strong><?= (int)$dashWarehouseTwoPct ?>%</strong></a>
                            </div>
                        </div>

                        <div class="overview-card">
                            <div class="overview-card-head"><h3>FEFO Expiry Alerts</h3><a href="<?= e(url('beverage_warehouse.php?tab=tab-inventory&detail=fefo')) ?>">View All</a></div>
	                            <div class="expiry-list">
	                                <?php
                                    $expiryRows = [];
                                    foreach ($products as $product) {
                                        $expiryDate = trim((string)($product['expiry_date'] ?? ''));
                                        if ($expiryDate === '' || strtotime($expiryDate) === false) {
                                            continue;
                                        }
                                        $daysLeft = (int)floor((strtotime($expiryDate) - time()) / 86400);
                                        if ($daysLeft <= 30) {
                                            $expiryRows[] = [$product, $daysLeft];
                                        }
                                    }
                                    usort($expiryRows, static fn(array $a, array $b): int => $a[1] <=> $b[1]);
	                                ?>
                                    <?php if (empty($expiryRows)): ?>
                                        <div style="color:#64748b;font-size:0.82rem;padding:0.9rem 0">No expiry warnings yet.</div>
                                    <?php endif; ?>
	                                <?php foreach (array_slice($expiryRows, 0, 3) as [$expiryProduct, $daysLeft]): ?>
                                        <?php
                                        $name = $expiryProduct['name'] ?? 'Product';
                                        $pack = $expiryProduct['packaging'] ?? ($expiryProduct['config'] ?? '');
                                        $img = beverage_product_image_src($expiryProduct['image'] ?? null);
                                        $warning = $daysLeft < 0 ? 'Expired' : 'Expiring in ' . $daysLeft . ' days';
                                        $qty = (int)($expiryProduct['stock_crates'] ?? 0);
                                        ?>
	                                    <div class="expiry-row">
                                            <?php if ($img !== ''): ?>
	                                            <img class="product-thumb" src="<?= e($img) ?>" alt="<?= e($name) ?>" onerror="this.style.display='none';">
                                            <?php else: ?>
                                                <span class="product-thumb product-thumb-placeholder"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('image') : '' ?></span>
                                            <?php endif; ?>
	                                        <div><div class="row-title"><?= e($name) ?></div><div class="row-sub"><?= e($pack) ?></div></div>
	                                        <div><div class="expiry-warning"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('warning') : '' ?> <?= e($warning) ?></div><div class="qty"><?= e($qty) ?><br><span class="row-sub">Crates</span></div></div>
	                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="overview-card">
                            <div class="overview-card-head"><h3>Driver &amp; Delivery Performance</h3><a href="<?= e(url('dashboard.php?tab=tab-logistics&view=pod&company=beverage')) ?>">View All</a></div>
                            <table class="driver-table">
                                <thead><tr><th>Driver</th><th>Deliveries</th><th>On-time %</th><th>Variance</th></tr></thead>
                                <tbody>
                                    <tr><td colspan="4" style="color:#64748b;padding:0.9rem 0">No driver delivery records yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="overview-row-bottom">
                        <div class="overview-card">
                            <div class="overview-card-head"><h3>Quick Operations</h3></div>
                            <div class="quick-ops">
                                <a class="quick-op" href="<?= e(url('dashboard.php?tab=tab-procurement&company=beverage')) ?>"><span class="quick-op-icon" style="background:#eff6ff;color:#2563eb"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('pod') : '' ?></span>New GRN</a>
                                <a class="quick-op" href="<?= e(url('beverage_pos.php?tab=transfers')) ?>"><span class="quick-op-icon" style="background:#ecfdf5;color:#16a34a"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('switch') : '' ?></span>Stock Transfer</a>
                                <a class="quick-op" href="<?= e(url('dashboard.php?tab=tab-procurement&company=beverage')) ?>"><span class="quick-op-icon" style="background:#f5f3ff;color:#7c3aed"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('orders') : '' ?></span>Create Order</a>
                                <a class="quick-op" href="<?= e(url('dashboard.php?tab=tab-logistics&view=returns&company=beverage')) ?>"><span class="quick-op-icon" style="background:#fff7ed;color:#f97316"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('return') : '' ?></span>New Return</a>
                                <button class="quick-op" onclick="openRegisterProductModal()"><span class="quick-op-icon" style="background:#ecfeff;color:#0f766e"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('box') : '' ?></span>Add Product</button>
                                <a class="quick-op" href="<?= e(url('dashboard.php?tab=tab-logistics&view=dispatch&company=beverage')) ?>"><span class="quick-op-icon" style="background:#eff6ff;color:#0284c7"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('truck') : '' ?></span>Dispatch</a>
                            </div>
                        </div>

                        <div class="overview-card">
                            <div class="overview-card-head"><h3>AI Warehouse Insights</h3><span class="mini-badge">Beta</span></div>
                            <div class="insight-list">
                                <div class="insight-row"><strong style="color:#2563eb">▥</strong><div><div class="row-title">No warehouse insights yet</div><div class="row-sub">Add real products, stock movement, and transfers between the two warehouses to generate insights.</div></div></div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <div class="overview-card-head"><h3>Warehouse Activity Feed</h3><a href="<?= e(url('beverage_pos.php?tab=pricing-audit')) ?>">View All</a></div>
                            <div class="activity-list">
                                <?php
                                try {
                                    $activityRows = array_slice(\App\Modules\BeverageWarehouse\BeverageWarehouseService::getAuditTrail(), 0, 5);
                                } catch (\Throwable $t) {
                                    $activityRows = [];
                                }
                                ?>
                                <?php if (empty($activityRows)): ?>
                                    <div style="color:#64748b;font-size:0.82rem;padding:0.9rem 0">No warehouse activity yet.</div>
                                <?php endif; ?>
                                <?php foreach ($activityRows as $activity): ?>
                                    <div class="activity-row"><span class="row-sub"><?= e((string)($activity['timestamp'] ?? '')) ?></span><div><div class="row-title"><?= e((string)($activity['action'] ?? 'Warehouse activity')) ?><?= !empty($activity['item']) ? ': ' . e((string)$activity['item']) : '' ?></div></div><span class="row-sub"><?= e((string)($activity['user'] ?? '')) ?></span></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>

                    <div class="footer-status">
                        <span>↻ Last Sync: 2 mins ago</span>
                        <span><strong style="color:#16a34a">●</strong> System Status: All Systems Operational</span>
                        <span>© 2026 Empress Tee Beverage Depot. All rights reserved.</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

<!-- =================================================================
     REGISTER NEW BEVERAGE PRODUCT MODAL WITH IMAGE ATTACHMENT UPLOAD
     ================================================================= -->
<div id="registerProductModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.75); align-items:center; justify-content:center; z-index:9999; padding:1rem">
    <div style="background:#ffffff; width:680px; max-width:95vw; border-radius:12px; padding:1.5rem; box-shadow:0 20px 40px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; font-family:'Inter',sans-serif">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:1.25rem">
            <div>
                <h3 style="font-family:'Outfit',sans-serif; font-size:1.2rem; font-weight:800; color:#0f172a; margin:0">
                    <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('plus') : '' ?> Register New Beverage Product (With Image Attachment)
                </h3>
                <p style="font-size:0.78rem; color:#64748b; margin-top:0.15rem">Add SKUs, Units of Measure, Pricing, Initial Stock &amp; Product Image</p>
            </div>
            <button type="button" onclick="document.getElementById('registerProductModal').style.display='none'" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#64748b; cursor:pointer">&times;</button>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateProductImageChoice(this)">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="add_product">
            <input type="hidden" name="warehouse_location" value="<?= e($selectedWarehouse) ?>">
            <input type="hidden" name="product_image_source" id="add_product_image_source" value="keep">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem">
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Product / Drink Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Monster Energy Drink 50cl" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">SKU Code (Auto-generated if empty)</label>
                    <input type="text" name="sku" placeholder="e.g. BEV-MONSTER-50CL" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Category *</label>
                    <select name="category" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; background:#fff">
                        <option value="Soft Drinks">Soft Drinks</option>
                        <option value="Water">Water &amp; Hydration</option>
                        <option value="Malt">Malt Drinks</option>
                        <option value="Energy Drinks">Energy Drinks</option>
                        <option value="Glass Bottles">Returnable Glass Bottles</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Packaging UOM *</label>
                    <select name="packaging" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; background:#fff">
                        <option value="Crate of 24">Crate of 24 Bottles</option>
                        <option value="Pack of 12">Pack of 12 PET Bottles</option>
                        <option value="Case of 24 Cans">Case of 24 Cans</option>
                        <option value="Glass Bottle Crate">Returnable Glass Bottle Crate</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Wholesale Price (₦) *</label>
                    <input type="number" step="0.01" name="wholesale_price" required placeholder="4500.00" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Crate Deposit Value (₦)</label>
                    <input type="number" step="0.01" name="crate_deposit" placeholder="1500.00" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Initial Stock in <?= e($selectedWarehouse) ?> (Crates/Cartons)</label>
                    <input type="number" name="stock_crates" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">FEFO Batch Expiry Date</label>
                    <input type="date" name="expiry_date" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
            </div>

	            <!-- Image Attachment Upload Field -->
	            <div style="background:#f8fafc; border:1px dashed #0284c7; border-radius:8px; padding:1rem; margin-bottom:1.25rem">
	                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem">
	                    <label style="font-size:0.8rem; font-weight:800; color:#0284c7">
	                        Attach Product Image / Photo *
	                    </label>
	                    <button type="button" onclick="openOnlineImageSearchForModal('add_image_url', 'add_image_preview', document.querySelector('#registerProductModal input[name=name]').value, '')" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; padding:0.25rem 0.6rem; border-radius:5px; font-size:0.72rem; font-weight:800; cursor:pointer;display:inline-flex;align-items:center;gap:0.3rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('globe') : '' ?> Search Online Images &amp; Auto-Fill</button>
	                </div>
	                <p style="font-size:0.72rem; color:#64748b; margin-bottom:0.75rem">Upload an image file, paste a web URL, or search online beverage images to display on POS terminals &amp; warehouse inventory</p>
	                <div style="display:flex; align-items:center; gap:0.85rem; margin-bottom:0.75rem">
	                    <img id="add_image_preview" src="<?= e(beverage_product_image_fallback_src()) ?>" alt="Preview" onerror="this.onerror=null; this.src='<?= e(beverage_product_image_fallback_src()) ?>';" style="width:52px; height:52px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1; background:#fff">
	                    <div id="add_product_image_status" style="font-size:0.72rem; color:#64748b">Selected image preview. Choose a file, paste an image URL, or search online.</div>
	                </div>
	                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0.65rem">
	                    <div>
	                        <span style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem">Option A: Upload File</span>
	                        <label for="add_product_image" style="display:inline-flex; width:100%; align-items:center; justify-content:center; gap:0.35rem; background:#ffffff; border:1px solid #cbd5e1; color:#1d4ed8; border-radius:6px; padding:0.48rem 0.5rem; font-size:0.75rem; font-weight:800; cursor:pointer">📁 Choose File</label>
	                        <input type="file" id="add_product_image" name="product_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp" onchange="previewSelectedProductImage(this, 'add_image_preview')" style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none">
	                    </div>
	                    <div>
	                        <span style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem">Option B: Image URL</span>
	                        <input type="text" id="add_image_url" name="product_image_url" placeholder="https://..." oninput="previewProductImageUrl(this.value, 'add_image_preview')" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.78rem">
	                    </div>
	                    <div>
	                        <span style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem">Option C: Online Search</span>
	                        <button type="button" onclick="openOnlineImageSearchForModal('add_image_url', 'add_image_preview', document.querySelector('#registerProductModal input[name=name]').value, '')" style="width:100%; background:#eff6ff; border:1px solid #93c5fd; color:#1d4ed8; border-radius:6px; padding:0.5rem; font-size:0.75rem; font-weight:800; cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:0.3rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('search') : '' ?> Find Online</button>
	                    </div>
	                </div>
	            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem">
                <button type="button" onclick="document.getElementById('registerProductModal').style.display='none'" style="background:#f1f5f9; border:1px solid #cbd5e1; padding:0.6rem 1.25rem; border-radius:6px; font-weight:700; font-size:0.85rem; cursor:pointer; color:#334155">Cancel</button>
                <button type="submit" style="background:#1d4ed8; color:#ffffff; border:none; padding:0.6rem 1.5rem; border-radius:6px; font-weight:800; font-size:0.85rem; cursor:pointer; box-shadow:0 4px 12px rgba(29,78,216,0.3)">Save Product &amp; Sync to POS</button>
            </div>
        </form>
    </div>
</div>

<!-- =================================================================
     EDIT BEVERAGE PRODUCT MODAL
     ================================================================= -->
<div id="editProductModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.75); align-items:center; justify-content:center; z-index:9999; padding:1rem">
    <div style="background:#ffffff; width:680px; max-width:95vw; border-radius:12px; padding:1.5rem; box-shadow:0 20px 40px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; font-family:'Inter',sans-serif">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:1.25rem">
            <div>
                <h3 style="font-family:'Outfit',sans-serif; font-size:1.2rem; font-weight:800; color:#0f172a; margin:0">
                    <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('edit') : '' ?> Edit Beverage Product Details
                </h3>
                <p style="font-size:0.78rem; color:#64748b; margin-top:0.15rem">Update SKU Details, Unit Prices, Stock Quantities &amp; Product Image</p>
            </div>
            <button type="button" onclick="document.getElementById('editProductModal').style.display='none'" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#64748b; cursor:pointer">&times;</button>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateProductImageChoice(this)">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="update_product">
            <input type="hidden" name="sku" id="edit_sku">
            <input type="hidden" name="warehouse_location" value="<?= e($selectedWarehouse) ?>">
            <input type="hidden" name="product_image_source" id="edit_product_image_source" value="keep">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem">
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Product / Drink Name *</label>
                    <input type="text" name="name" id="edit_name" required style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">SKU Code (Read Only)</label>
                    <input type="text" id="edit_sku_display" readonly style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; background:#f1f5f9; font-family:monospace; font-weight:700">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Category *</label>
                    <select name="category" id="edit_category" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; background:#fff">
                        <option value="Soft Drinks">Soft Drinks</option>
                        <option value="Water">Water &amp; Hydration</option>
                        <option value="Malt">Malt Drinks</option>
                        <option value="Energy Drinks">Energy Drinks</option>
                        <option value="Glass Bottles">Returnable Glass Bottles</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Packaging UOM *</label>
                    <select name="packaging" id="edit_packaging" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem; background:#fff">
                        <option value="Crate of 24">Crate of 24 Bottles</option>
                        <option value="Pack of 12">Pack of 12 PET Bottles</option>
                        <option value="Case of 24 Cans">Case of 24 Cans</option>
                        <option value="Glass Bottle Crate">Returnable Glass Bottle Crate</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Wholesale Price (₦) *</label>
                    <input type="number" step="0.01" name="wholesale_price" id="edit_wholesale_price" required style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Crate Deposit Value (₦)</label>
                    <input type="number" step="0.01" name="crate_deposit" id="edit_crate_deposit" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">Current Stock in <?= e($selectedWarehouse) ?> (Crates/Cartons)</label>
                    <input type="number" name="stock_crates" id="edit_stock_crates" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#334155; display:block; margin-bottom:0.3rem">FEFO Batch Expiry Date</label>
                    <input type="date" name="expiry_date" id="edit_expiry_date" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.85rem">
                </div>
            </div>

            <!-- Image Attachment Field -->
            <div style="background:#f8fafc; border:1px dashed #0284c7; border-radius:8px; padding:1rem; margin-bottom:1.25rem">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem">
                    <label style="font-size:0.8rem; font-weight:800; color:#0284c7">
                        Update Product Image / Attachment
                    </label>
                    <button type="button" onclick="openOnlineImageSearchForModal('edit_image_url', 'edit_image_preview', document.getElementById('edit_name').value, document.getElementById('edit_sku').value)" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; padding:0.25rem 0.6rem; border-radius:5px; font-size:0.72rem; font-weight:800; cursor:pointer;display:inline-flex;align-items:center;gap:0.3rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('globe') : '' ?> Search Online Images &amp; Auto-Fill</button>
                </div>
                <div style="display:flex; align-items:center; gap:0.85rem; margin-bottom:0.75rem">
                    <img id="edit_image_preview" src="<?= e(beverage_product_image_fallback_src()) ?>" alt="Preview" onerror="this.onerror=null; this.src='<?= e(beverage_product_image_fallback_src()) ?>';" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1; background:#fff">
                    <div id="edit_product_image_status" style="font-size:0.72rem; color:#64748b">Current Image Preview. Upload a new file, update URL, or search online images.</div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0.65rem">
                    <div>
                        <span style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem">Option A: Upload File</span>
                        <label for="edit_product_image" style="display:inline-flex; width:100%; align-items:center; justify-content:center; gap:0.35rem; background:#ffffff; border:1px solid #cbd5e1; color:#1d4ed8; border-radius:6px; padding:0.48rem 0.5rem; font-size:0.75rem; font-weight:800; cursor:pointer">📁 Choose File</label>
                        <input type="file" id="edit_product_image" name="product_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp" onchange="previewSelectedProductImage(this, 'edit_image_preview')" style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none">
                    </div>
                    <div>
                        <span style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem">Option B: Image Web URL</span>
                        <input type="text" name="product_image_url" id="edit_image_url" placeholder="https://..." oninput="previewProductImageUrl(this.value, 'edit_image_preview')" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.78rem">
                    </div>
                    <div>
                        <span style="font-size:0.7rem; font-weight:700; color:#475569; display:block; margin-bottom:0.2rem">Option C: Online Search</span>
                        <button type="button" onclick="openOnlineImageSearchForModal('edit_image_url', 'edit_image_preview', document.getElementById('edit_name').value, document.getElementById('edit_sku').value)" style="width:100%; background:#eff6ff; border:1px solid #93c5fd; color:#1d4ed8; border-radius:6px; padding:0.5rem; font-size:0.75rem; font-weight:800; cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:0.3rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('search') : '' ?> Find Online</button>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem">
                <button type="button" onclick="document.getElementById('editProductModal').style.display='none'" style="background:#f1f5f9; border:1px solid #cbd5e1; padding:0.6rem 1.25rem; border-radius:6px; font-weight:700; font-size:0.85rem; cursor:pointer; color:#334155">Cancel</button>
                <button type="submit" style="background:#1d4ed8; color:#ffffff; border:none; padding:0.6rem 1.5rem; border-radius:6px; font-weight:800; font-size:0.85rem; cursor:pointer; box-shadow:0 4px 12px rgba(29,78,216,0.3)">Update Product &amp; Sync POS</button>
            </div>
        </form>
    </div>
</div>

<!-- =================================================================
     ONLINE BEVERAGE PRODUCT IMAGE FINDER MODAL
     ================================================================= -->
<div id="onlineImageSearchModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); backdrop-filter:blur(5px); z-index:10000; align-items:center; justify-content:center; padding:1.25rem">
    <div style="background:#ffffff; width:820px; max-width:96vw; border-radius:14px; box-shadow:0 24px 50px rgba(0,0,0,0.3); max-height:92vh; display:flex; flex-direction:column; overflow:hidden; font-family:'Inter',sans-serif">
        
        <!-- Modal Header -->
        <div style="padding:1.15rem 1.5rem; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:space-between; flex-shrink:0">
            <div style="display:flex; align-items:center; gap:0.85rem">
                <div style="width:42px; height:42px; border-radius:8px; background:#eff6ff;color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:1.25rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('globe') : '' ?></div>
                <div>
                    <h3 style="margin:0; font-family:'Outfit',sans-serif; font-size:1.15rem; color:#ffffff; display:flex; align-items:center; gap:0.5rem">
                        Online Beverage Image Finder
                        <span id="finder_target_badge" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#ffffff; font-size:0.65rem; padding:0.15rem 0.5rem; border-radius:99px; font-weight:700">Live Search</span>
                    </h3>
                    <p style="margin:0.2rem 0 0; font-size:0.75rem; color:#94a3b8">Search Google and trusted beverage sources, then save the correct product photo</p>
                </div>
            </div>
            <button type="button" onclick="closeOnlineImageSearchModal()" style="background:transparent; border:none; color:#94a3b8; font-size:1.5rem; cursor:pointer; padding:0.2rem 0.5rem; line-height:1">&times;</button>
        </div>

        <!-- Modal Search Bar & Chips -->
        <div style="padding:1rem 1.5rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; flex-shrink:0">
            <form onsubmit="event.preventDefault(); triggerOnlineImageSearch();" style="display:flex; gap:0.6rem; margin-bottom:0.75rem">
                <div style="position:relative; flex:1">
                    <span style="position:absolute; left:0.8rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:0.9rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('search') : '' ?></span>
                    <input type="text" id="finder_search_query" placeholder="Enter drink name (e.g. Coca-Cola 50cl, Maltina Can, Eva Water, Monster)..." style="width:100%; padding:0.65rem 2rem 0.65rem 2.4rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem; outline:none; background:#fff">
                    <span id="finder_query_clear" onclick="clearFinderQuery()" style="position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); color:#94a3b8; cursor:pointer; font-size:0.85rem; display:none">✕</span>
                </div>
                <button type="submit" style="background:#2563eb; color:#ffffff; border:none; border-radius:8px; padding:0.65rem 1.25rem; font-size:0.82rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:0.4rem; box-shadow:0 4px 12px rgba(37,99,235,0.25)">
                    Search Google + Online
                </button>
            </form>

            <!-- Quick Keyword Chips & External Search Deep Links -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem">
                <div style="display:flex; gap:0.35rem; flex-wrap:wrap; align-items:center">
                    <span style="font-size:0.7rem; font-weight:800; color:#64748b; text-transform:uppercase">Quick Keywords:</span>
                    <button type="button" class="image-finder-chip" onclick="appendFinderKeyword('Bottle')">🍾 Bottle</button>
                    <button type="button" class="image-finder-chip" onclick="appendFinderKeyword('Crate')"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('box') : '' ?> Crate</button>
                    <button type="button" class="image-finder-chip" onclick="appendFinderKeyword('Can')">🥫 Can</button>
                    <button type="button" class="image-finder-chip" onclick="appendFinderKeyword('Pack')">🛍️ Pack</button>
                    <button type="button" class="image-finder-chip" onclick="appendFinderKeyword('PET')">💧 PET</button>
                    <button type="button" class="image-finder-chip" onclick="appendFinderKeyword('Nigeria')">🇳🇬 Nigeria</button>
                </div>
                <div style="display:flex; gap:0.4rem; align-items:center">
                    <button type="button" onclick="openGoogleImageSearch()" style="background:#ffffff; border:1px solid #cbd5e1; color:#334155; font-size:0.72rem; font-weight:700; padding:0.3rem 0.6rem; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:0.3rem">
                        <span style="color:#4285f4; font-weight:900">G</span> Google Images ↗
                    </button>
                    <button type="button" onclick="openBingImageSearch()" style="background:#ffffff; border:1px solid #cbd5e1; color:#334155; font-size:0.72rem; font-weight:700; padding:0.3rem 0.6rem; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:0.3rem">
                        <span style="color:#008373; font-weight:900">b</span> Bing Images ↗
                    </button>
                </div>
            </div>
        </div>

        <!-- Custom Image URL Quick Paste Row -->
        <div style="padding:0.6rem 1.5rem; background:#eff6ff; border-bottom:1px solid #dbeafe; display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-shrink:0">
            <div style="display:flex; align-items:center; gap:0.5rem; flex:1">
                <span style="font-size:0.72rem; font-weight:800; color:#1e40af; white-space:nowrap">🔗 Or Paste Any Image Web Link:</span>
                <input type="text" id="finder_custom_url" placeholder="https://example.com/drink-photo.jpg" style="flex:1; padding:0.38rem 0.6rem; border:1px solid #bfdbfe; border-radius:6px; font-size:0.76rem; background:#fff">
            </div>
            <button type="button" onclick="applyCustomFinderUrl()" style="background:#1d4ed8; color:#ffffff; border:none; padding:0.4rem 0.85rem; border-radius:6px; font-size:0.75rem; font-weight:800; cursor:pointer">Use Link</button>
        </div>

        <!-- Results Gallery Grid Container -->
        <div style="padding:1.25rem 1.5rem; overflow-y:auto; flex:1; background:#ffffff" id="finder_results_container">
            <div id="finder_loading_spinner" style="display:none; text-align:center; padding:3rem 1rem">
                <div style="display:inline-block; width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:#2563eb; border-radius:50%; animation:spin 0.8s linear infinite"></div>
                <div style="margin-top:0.8rem; font-size:0.82rem; font-weight:700; color:#64748b">Searching official online libraries &amp; beverage catalogs...</div>
            </div>

            <div id="finder_empty_state" style="display:none; text-align:center; padding:3rem 1rem; color:#64748b">
                <div style="font-size:2.5rem; margin-bottom:0.5rem">📷</div>
                <strong style="font-size:0.95rem; color:#0f172a; display:block">No images found for this query</strong>
                <p style="font-size:0.8rem; margin:0.35rem auto 1rem; max-width:400px">Try broadening your search term or click below to search on Google Images and paste the image address above.</p>
                <button type="button" onclick="openGoogleImageSearch()" style="background:#2563eb; color:#ffffff; border:none; padding:0.55rem 1.25rem; border-radius:6px; font-size:0.8rem; font-weight:800; cursor:pointer">Open Google Images Search ↗</button>
            </div>

            <div id="finder_results_grid" class="image-results-grid">
                <!-- Dynamically populated with image cards -->
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="padding:0.9rem 1.5rem; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-shrink:0">
            <div id="finder_footer_status" style="font-size:0.74rem; color:#64748b">
                <span>Search a product name, then choose the correct picture to update POS and warehouse images.</span>
            </div>
            <button type="button" onclick="closeOnlineImageSearchModal()" style="background:#e2e8f0; color:#334155; border:none; padding:0.55rem 1.25rem; border-radius:6px; font-weight:700; font-size:0.82rem; cursor:pointer">Close</button>
        </div>
    </div>
</div>

<!-- =================================================================
     EXCEL / CSV BULK IMPORT MODAL
     ================================================================= -->
<div id="excelImportModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); backdrop-filter:blur(5px); z-index:9999; align-items:center; justify-content:center; padding:1.25rem">
    <div style="background:#ffffff; width:720px; max-width:96vw; border-radius:14px; box-shadow:0 24px 50px rgba(0,0,0,0.3); max-height:92vh; display:flex; flex-direction:column; overflow:hidden; font-family:'Inter',sans-serif">
        
        <!-- Modal Header -->
        <div style="padding:1.15rem 1.5rem; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:space-between; flex-shrink:0">
            <div style="display:flex; align-items:center; gap:0.85rem">
                <div style="width:42px; height:42px; border-radius:8px; background:linear-gradient(135deg, #16a34a, #059669); display:flex; align-items:center; justify-content:center; font-size:1.35rem">📊</div>
                <div>
                    <h3 style="margin:0; font-family:'Outfit',sans-serif; font-size:1.15rem; color:#ffffff">
                        Import &amp; Update Inventory from Excel
                    </h3>
                    <p style="margin:0.2rem 0 0; font-size:0.75rem; color:#94a3b8">Bulk update stock quantities, wholesale prices, FEFO dates &amp; register new SKUs</p>
                </div>
            </div>
            <button type="button" onclick="closeExcelImportModal()" style="background:transparent; border:none; color:#94a3b8; font-size:1.5rem; cursor:pointer; padding:0.2rem 0.5rem; line-height:1">&times;</button>
        </div>

        <!-- Form Content -->
        <form method="POST" action="<?= url('beverage_warehouse.php') ?>" enctype="multipart/form-data" onsubmit="return handleExcelImportSubmit(this)" style="display:flex; flex-direction:column; flex:1; overflow-y:auto; margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="import_excel_inventory">

            <div style="padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem">
                
                <!-- Step 1: Download Templates / Current Sheet -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.25rem">
                    <div style="font-size:0.8rem; font-weight:800; color:#0f172a; margin-bottom:0.4rem; display:flex; align-items:center; gap:0.4rem">
                        <span style="background:#2563eb; color:#fff; border-radius:50%; width:20px; height:20px; display:inline-flex; align-items:center; justify-content:center; font-size:0.7rem">1</span>
                        Step 1: Download Current Sheet or Blank Template
                    </div>
                    <p style="font-size:0.75rem; color:#64748b; margin-bottom:0.75rem">Export your current active inventory to edit in Excel, or download a clean template with pre-filled headers and samples.</p>
                    
                    <div style="display:flex; gap:0.6rem; flex-wrap:wrap">
                        <a href="<?= url('beverage_warehouse.php?action=export_inventory_excel') ?>" class="details-btn" style="background:#f0fdf4; color:#15803d; border:1px solid #86efac; text-decoration:none; padding:0.45rem 0.85rem">
                            <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('download') : '' ?> Download Current Inventory (.csv / Excel)
                        </a>
                        <a href="<?= url('beverage_warehouse.php?action=download_inventory_template') ?>" class="details-btn" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; text-decoration:none; padding:0.45rem 0.85rem">
                            📄 Download Blank Excel Template
                        </a>
                    </div>
                </div>

                <!-- Step 2: Upload Updated Spreadsheet -->
                <div style="background:#ffffff; border:2px dashed #3b82f6; border-radius:10px; padding:1.5rem; text-align:center; transition:all 0.2s ease" id="excel_drop_zone">
                    <div style="font-size:2.2rem; margin-bottom:0.5rem">📁</div>
                    <strong style="font-size:0.95rem; color:#0f172a; display:block; margin-bottom:0.25rem">Select your edited Excel / CSV file</strong>
                    <p style="font-size:0.75rem; color:#64748b; margin-bottom:1rem">Supports Microsoft Excel CSV (.csv), semicolon separated, and tab-delimited files</p>

                    <label for="excel_file_input" style="background:#2563eb; color:#ffffff; padding:0.6rem 1.4rem; border-radius:8px; font-weight:800; font-size:0.82rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.4rem; box-shadow:0 4px 12px rgba(37,99,235,0.25)">
                        📂 Browse File on Computer
                    </label>
                    <input type="file" id="excel_file_input" name="excel_file" accept=".csv,.txt,text/csv,application/vnd.ms-excel" onchange="previewSelectedExcelFile(this)" style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none" required>

                    <div id="excel_selected_file_box" style="display:none; margin-top:1rem; padding:0.75rem; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; text-align:left">
                        <div style="display:flex; align-items:center; justify-content:space-between">
                            <div>
                                <strong id="excel_file_name" style="font-size:0.82rem; color:#1e40af; display:block">filename.csv</strong>
                                <small id="excel_file_meta" style="font-size:0.7rem; color:#64748b">File size: 12 KB</small>
                            </div>
                            <span style="font-size:1.1rem; color:#16a34a;display:inline-flex;align-items:center;gap:0.35rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?> Ready to Import</span>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Column Reference & Sync Rules -->
                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:0.85rem 1rem; background:#f8fafc">
                    <strong style="font-size:0.75rem; color:#334155; display:block; margin-bottom:0.35rem">⚡ How Syncing Works:</strong>
                    <ul style="margin:0; padding-left:1.2rem; font-size:0.72rem; color:#64748b; line-height:1.5">
                        <li><strong>Existing Products (Matched by SKU):</strong> Stock crates, wholesale price, crate deposits, packaging &amp; expiry dates will be updated immediately.</li>
                        <li><strong>New Products:</strong> Rows with new SKU codes will be automatically added to the warehouse catalog &amp; synced to POS terminals.</li>
                        <li><strong>Blank Image Cells:</strong> Leaving image URL empty keeps the product's existing image intact.</li>
                    </ul>
                </div>

            </div>

            <!-- Modal Footer -->
            <div style="padding:1rem 1.5rem; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-shrink:0">
                <button type="button" onclick="closeExcelImportModal()" style="background:#e2e8f0; color:#334155; border:none; padding:0.6rem 1.25rem; border-radius:6px; font-weight:700; font-size:0.82rem; cursor:pointer">Cancel</button>
                <button type="submit" id="excel_submit_btn" style="background:#16a34a; color:#ffffff; border:none; padding:0.6rem 1.6rem; border-radius:6px; font-weight:800; font-size:0.85rem; cursor:pointer; box-shadow:0 4px 12px rgba(22,163,74,0.3); display:inline-flex; align-items:center; gap:0.4rem">
                    <?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('upload') : '' ?> Import &amp; Sync Inventory Master
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Product Audit History Modal -->
<div id="productAuditModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:1.5rem">
    <div style="background:#ffffff; border-radius:12px; max-width:920px; width:100%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 45px rgba(0,0,0,0.25); overflow:hidden">
        <div style="padding:1.25rem 1.5rem; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:space-between">
            <div style="display:flex; align-items:center; gap:0.85rem">
                <img id="audit_modal_img" src="<?= e(beverage_product_image_fallback_src()) ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:8px; border:2px solid #38bdf8; background:#fff">
                <div>
                    <h3 id="audit_modal_title" style="margin:0; font-family:'Outfit',sans-serif; font-size:1.15rem; color:#ffffff">Product Audit Trail</h3>
                    <small id="audit_modal_subtitle" style="color:#94a3b8; font-size:0.75rem">Real-time movement history &amp; inventory audit log</small>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('productAuditModal').style.display='none'" style="background:transparent; border:none; color:#94a3b8; font-size:1.5rem; cursor:pointer; padding:0 0.5rem">&times;</button>
        </div>

        <div style="padding:1.25rem 1.5rem; overflow-y:auto; flex:1">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem">
                <h4 style="margin:0; font-family:'Outfit',sans-serif; font-size:0.95rem; color:#0f172a;display:flex;align-items:center;gap:0.35rem"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('log') : '' ?> Complete Stock Movement History</h4>
                <small style="color:#64748b; font-size:0.75rem">Auto-recorded by POS Sales, Warehouse GRN &amp; Admin updates</small>
            </div>

            <div style="overflow-x:auto; border:1px solid #e2e8f0; border-radius:8px">
                <table style="width:100%; border-collapse:collapse; font-size:0.8rem">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0">
                            <th style="padding:0.65rem 0.85rem; text-align:left; color:#475569; font-size:0.7rem; text-transform:uppercase">Timestamp</th>
                            <th style="padding:0.65rem 0.85rem; text-align:left; color:#475569; font-size:0.7rem; text-transform:uppercase">Action / Event</th>
                            <th style="padding:0.65rem 0.85rem; text-align:left; color:#475569; font-size:0.7rem; text-transform:uppercase">User</th>
                            <th style="padding:0.65rem 0.85rem; text-align:left; color:#475569; font-size:0.7rem; text-transform:uppercase">Qty Change</th>
                            <th style="padding:0.65rem 0.85rem; text-align:left; color:#475569; font-size:0.7rem; text-transform:uppercase">Balance Change</th>
                            <th style="padding:0.65rem 0.85rem; text-align:left; color:#475569; font-size:0.7rem; text-transform:uppercase">Location / Reference</th>
                        </tr>
                    </thead>
                    <tbody id="audit_modal_tbody">
                        <!-- Populated dynamically by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div style="padding:1rem 1.5rem; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end">
            <button type="button" onclick="document.getElementById('productAuditModal').style.display='none'" style="background:#0f172a; color:#ffffff; border:none; padding:0.55rem 1.25rem; border-radius:6px; font-weight:700; font-size:0.82rem; cursor:pointer">Close Audit Trail</button>
        </div>
    </div>
</div>

<!-- Dynamic Toast Notification Container -->
<div id="beverageToast" class="beverage-toast">
    <span id="beverageToastIcon"><?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?></span>
    <span id="beverageToastMsg">Action completed successfully</span>
</div>

<script>
const BEVERAGE_APP_BASE_URL = <?= json_encode(url('')) ?>;
const BEVERAGE_CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const WA_ICON_SORT = `<?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('sort') : '' ?>`;
const WA_ICON_DOWN = `<?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('down') : '' ?>`;
const WA_ICON_UP = `<?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('arrow-up') : '' ?>`;
const WA_ICON_ARROW_RIGHT = `<?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('arrow-right') : '' ?>`;
const WA_ICON_REFRESH = `<?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('refresh') : '' ?>`;
const WA_ICON_CHECK = `<?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('check') : '' ?>`;
const WA_ICON_WARNING = `<?= function_exists('warehouse_admin_icon') ? warehouse_admin_icon('warning') : '' ?>`;
const INVENTORY_DETAIL_PRESET = <?= json_encode((string)($_GET['detail'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
<?php
try {
    $allAuditLogsForClient = class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService') ? App\Modules\BeverageWarehouse\BeverageWarehouseService::getAuditTrail() : [];
} catch (\Throwable $t) {
    $allAuditLogsForClient = [];
}
?>
window.__ALL_AUDIT_LOGS = <?= json_encode($allAuditLogsForClient, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Online Image Finder State
let currentFinderTarget = {
    mode: 'table', // 'table', 'edit_modal', 'add_modal', 'general'
    sku: '',
    name: '',
    targetUrlInputId: '',
    targetPreviewId: ''
};
let currentImageSearchController = null;
let currentImageSearchTimeout = null;

/* =========================================================================
   TOP INVENTORY ITEMS LIVE SEARCH & MULTI-PARAMETER SORTING LOGIC
   ========================================================================= */
let currentSortState = {
    column: 'stock',
    direction: 'desc'
};

function filterInventoryTable() {
    const searchInput = document.getElementById('inventory_search_input');
    const categorySelect = document.getElementById('inventory_category_filter');
    const stockSelect = document.getElementById('inventory_stock_filter');
    const fefoSelect = document.getElementById('inventory_fefo_filter');
    const sortSelect = document.getElementById('inventory_sort_filter');
    const clearBtn = document.getElementById('inventory_search_clear');

    if (!searchInput) return;

    const searchTerm = (searchInput.value || '').trim().toLowerCase();
    const selectedCategory = (categorySelect ? categorySelect.value : '').trim().toLowerCase();
    const selectedStock = (stockSelect ? stockSelect.value : '').trim().toLowerCase();
    const selectedFefo = (fefoSelect ? fefoSelect.value : '').trim().toLowerCase();
    const selectedSort = (sortSelect ? sortSelect.value : 'stock_desc');

    if (clearBtn) {
        clearBtn.style.display = searchTerm !== '' ? 'block' : 'none';
    }

    const rows = Array.from(document.querySelectorAll('#topInventoryTbody tr.inventory-row'));
    let matchCount = 0;

    rows.forEach(row => {
        const name = (row.dataset.name || '');
        const sku = (row.dataset.sku || '');
        const category = (row.dataset.category || '');
        const packaging = (row.dataset.packaging || '');
        const stock = parseInt(row.dataset.stock || '0', 10);
        const fefo = (row.dataset.fefo || '');

        let matchesSearch = true;
        if (searchTerm !== '') {
            matchesSearch = name.includes(searchTerm) || 
                            sku.includes(searchTerm) || 
                            category.includes(searchTerm) || 
                            packaging.includes(searchTerm);
        }

        let matchesCategory = true;
        if (selectedCategory !== '') {
            matchesCategory = category.includes(selectedCategory) || selectedCategory.includes(category);
        }

        let matchesStock = true;
        if (selectedStock === 'in_stock') {
            matchesStock = stock > 0;
        } else if (selectedStock === 'low_stock') {
            matchesStock = stock > 0 && stock <= 10;
        } else if (selectedStock === 'out_of_stock') {
            matchesStock = stock === 0;
        } else if (selectedStock === 'high_stock') {
            matchesStock = stock > 50;
        }

        let matchesFefo = true;
        if (selectedFefo === 'good') {
            matchesFefo = fefo === 'good';
        } else if (selectedFefo === 'expiring') {
            matchesFefo = fefo === 'expiring';
        }

        if (matchesSearch && matchesCategory && matchesStock && matchesFefo) {
            row.style.display = '';
            matchCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Sort visible rows according to selectedSort
    if (selectedSort !== 'default') {
        const tbody = document.getElementById('topInventoryTbody');
        const emptyRow = document.getElementById('noInventoryMatchRow');
        
        rows.sort((a, b) => {
            const stockA = parseInt(a.dataset.stock || '0', 10);
            const stockB = parseInt(b.dataset.stock || '0', 10);
            const priceA = parseFloat(a.dataset.price || '0');
            const priceB = parseFloat(b.dataset.price || '0');
            const valueA = parseFloat(a.dataset.value || (stockA * priceA));
            const valueB = parseFloat(b.dataset.value || (stockB * priceB));
            const nameA = (a.dataset.name || '');
            const nameB = (b.dataset.name || '');
            const skuA = (a.dataset.sku || '');
            const skuB = (b.dataset.sku || '');
            const catA = (a.dataset.category || '');
            const catB = (b.dataset.category || '');
            const packA = (a.dataset.packaging || '');
            const packB = (b.dataset.packaging || '');

            if (selectedSort === 'stock_desc') {
                return stockB - stockA || nameA.localeCompare(nameB);
            } else if (selectedSort === 'stock_asc') {
                return stockA - stockB || nameA.localeCompare(nameB);
            } else if (selectedSort === 'in_stock_first') {
                if ((stockA > 0) !== (stockB > 0)) {
                    return stockA > 0 ? -1 : 1;
                }
                return stockB - stockA || nameA.localeCompare(nameB);
            } else if (selectedSort === 'value_desc') {
                return valueB - valueA || stockB - stockA;
            } else if (selectedSort === 'value_asc') {
                return valueA - valueB || stockA - stockB;
            } else if (selectedSort === 'price_desc') {
                return priceB - priceA || nameA.localeCompare(nameB);
            } else if (selectedSort === 'price_asc') {
                return priceA - priceB || nameA.localeCompare(nameB);
            } else if (selectedSort === 'name_asc') {
                return nameA.localeCompare(nameB);
            } else if (selectedSort === 'name_desc') {
                return nameB.localeCompare(nameA);
            } else if (selectedSort === 'sku_asc') {
                return skuA.localeCompare(skuB);
            } else if (selectedSort === 'category_asc') {
                return catA.localeCompare(catB) || nameA.localeCompare(nameB);
            } else if (selectedSort === 'packaging_asc') {
                return packA.localeCompare(packB) || nameA.localeCompare(nameB);
            } else if (selectedSort === 'fefo_asc') {
                return (a.dataset.fefo || '').localeCompare(b.dataset.fefo || '');
            }
            return 0;
        });

        rows.forEach(r => tbody.appendChild(r));
        if (emptyRow) tbody.appendChild(emptyRow);
    }

    // Update Header Sort Icons
    updateHeaderSortIndicators(selectedSort);

    // Empty state handling
    const noMatchRow = document.getElementById('noInventoryMatchRow');
    if (noMatchRow) {
        noMatchRow.style.display = matchCount === 0 ? '' : 'none';
    }

    // Update count badge
    const badge = document.getElementById('inventory_count_badge');
    if (badge) {
        badge.textContent = `Showing ${matchCount} of ${rows.length} items`;
    }
}

function applyInventoryDetailPreset() {
    const stockSelect = document.getElementById('inventory_stock_filter');
    const fefoSelect = document.getElementById('inventory_fefo_filter');
    const sortSelect = document.getElementById('inventory_sort_filter');
    const searchInput = document.getElementById('inventory_search_input');
    const preset = (INVENTORY_DETAIL_PRESET || '').toLowerCase();

    if (!preset || (!stockSelect && !fefoSelect && !sortSelect)) {
        return;
    }

    if (searchInput) searchInput.value = '';
    if (stockSelect) stockSelect.value = '';
    if (fefoSelect) fefoSelect.value = '';

    if (preset === 'low-stock' && stockSelect) {
        stockSelect.value = 'low_stock';
    } else if (preset === 'available' && stockSelect) {
        stockSelect.value = 'in_stock';
    } else if (preset === 'out-of-stock' && stockSelect) {
        stockSelect.value = 'out_of_stock';
    } else if (preset === 'fefo' && fefoSelect) {
        fefoSelect.value = 'expiring';
    }

    if (sortSelect) {
        if (preset === 'stock-value') {
            sortSelect.value = 'value_desc';
            currentSortState = { column: 'value', direction: 'desc' };
        } else {
            sortSelect.value = 'stock_desc';
            currentSortState = { column: 'stock', direction: 'desc' };
        }
    }
}

document.addEventListener('keydown', function(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('metric-link')) {
        return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        target.click();
    }
});

function updateHeaderSortIndicators(selectedSort) {
    const sortHeaders = [
        { id: 'name', thId: 'th_col_name', iconId: 'sort_icon_name', ascKey: 'name_asc', descKey: 'name_desc' },
        { id: 'sku', thId: 'th_col_sku', iconId: 'sort_icon_sku', ascKey: 'sku_asc', descKey: '' },
        { id: 'category', thId: 'th_col_category', iconId: 'sort_icon_category', ascKey: 'category_asc', descKey: '' },
        { id: 'packaging', thId: 'th_col_packaging', iconId: 'sort_icon_packaging', ascKey: 'packaging_asc', descKey: '' },
        { id: 'stock', thId: 'th_col_stock', iconId: 'sort_icon_stock', ascKey: 'stock_asc', descKey: 'stock_desc' },
        { id: 'price', thId: 'th_col_price', iconId: 'sort_icon_price', ascKey: 'price_asc', descKey: 'price_desc' },
        { id: 'value', thId: 'th_col_value', iconId: 'sort_icon_value', ascKey: 'value_asc', descKey: 'value_desc' },
        { id: 'fefo', thId: 'th_col_fefo', iconId: 'sort_icon_fefo', ascKey: 'fefo_asc', descKey: '' }
    ];

    sortHeaders.forEach(col => {
        const th = document.getElementById(col.thId);
        const icon = document.getElementById(col.iconId);
        if (!th || !icon) return;

        if (selectedSort === col.descKey || (col.id === 'stock' && (selectedSort === 'stock_desc' || selectedSort === 'in_stock_first'))) {
            th.classList.add('sorted-active');
            th.style.background = '#eff6ff';
            th.style.color = '#1d4ed8';
            icon.innerHTML = WA_ICON_DOWN;
        } else if (selectedSort === col.ascKey) {
            th.classList.add('sorted-active');
            th.style.background = '#eff6ff';
            th.style.color = '#1d4ed8';
            icon.innerHTML = WA_ICON_UP;
        } else {
            th.classList.remove('sorted-active');
            th.style.background = '';
            th.style.color = '';
            icon.innerHTML = WA_ICON_SORT;
        }
    });

    // Update Quick Available Stock button label
    const quickBtn = document.getElementById('quick_available_sort_btn');
    if (quickBtn) {
        if (selectedSort === 'stock_desc') {
            quickBtn.innerHTML = WA_ICON_SORT + ' Available Stock: High to Low';
            quickBtn.style.background = '#dbeafe';
            quickBtn.style.color = '#1e40af';
        } else if (selectedSort === 'stock_asc') {
            quickBtn.innerHTML = WA_ICON_SORT + ' Available Stock: Low to High';
            quickBtn.style.background = '#dbeafe';
            quickBtn.style.color = '#1e40af';
        } else if (selectedSort === 'in_stock_first') {
            quickBtn.innerHTML = WA_ICON_CHECK + ' In-Stock Products First';
            quickBtn.style.background = '#dcfce7';
            quickBtn.style.color = '#15803d';
        } else {
            quickBtn.innerHTML = WA_ICON_SORT + ' Sort by Available Stock';
            quickBtn.style.background = '#f0f9ff';
            quickBtn.style.color = '#0284c7';
        }
    }
}

function sortTableByColumn(columnName) {
    const sortSelect = document.getElementById('inventory_sort_filter');
    if (!sortSelect) return;

    if (currentSortState.column === columnName) {
        currentSortState.direction = currentSortState.direction === 'desc' ? 'asc' : 'desc';
    } else {
        currentSortState.column = columnName;
        currentSortState.direction = (columnName === 'stock' || columnName === 'value' || columnName === 'price') ? 'desc' : 'asc';
    }

    let sortValue = 'default';
    if (columnName === 'stock') {
        sortValue = currentSortState.direction === 'desc' ? 'stock_desc' : 'stock_asc';
    } else if (columnName === 'price') {
        sortValue = currentSortState.direction === 'desc' ? 'price_desc' : 'price_asc';
    } else if (columnName === 'value') {
        sortValue = currentSortState.direction === 'desc' ? 'value_desc' : 'value_asc';
    } else if (columnName === 'name') {
        sortValue = currentSortState.direction === 'desc' ? 'name_desc' : 'name_asc';
    } else if (columnName === 'sku') {
        sortValue = 'sku_asc';
    } else if (columnName === 'category') {
        sortValue = 'category_asc';
    } else if (columnName === 'packaging') {
        sortValue = 'packaging_asc';
    } else if (columnName === 'fefo') {
        sortValue = 'fefo_asc';
    }

    sortSelect.value = sortValue;
    filterInventoryTable();

    const colLabels = {
        stock: 'Available Stock',
        price: 'Unit Cost',
        value: 'Stock Value',
        name: 'Product Name',
        sku: 'SKU Code',
        category: 'Category',
        packaging: 'Packaging',
        fefo: 'FEFO Expiry Status'
    };

    showToast(`Sorted by ${colLabels[columnName] || columnName} (${currentSortState.direction === 'desc' ? 'Highest / Z-A' : 'Lowest / A-Z'})`, 'success');
}

function toggleSortByAvailableStock() {
    const sortSelect = document.getElementById('inventory_sort_filter');
    if (!sortSelect) return;

    const currentVal = sortSelect.value;
    let nextVal = 'stock_desc';

    if (currentVal === 'stock_desc') {
        nextVal = 'stock_asc';
        currentSortState = { column: 'stock', direction: 'asc' };
    } else if (currentVal === 'stock_asc') {
        nextVal = 'in_stock_first';
        currentSortState = { column: 'stock', direction: 'desc' };
    } else {
        nextVal = 'stock_desc';
        currentSortState = { column: 'stock', direction: 'desc' };
    }

    sortSelect.value = nextVal;
    filterInventoryTable();

    const msgs = {
        'stock_desc': 'Sorted by Available Stock: Highest First',
        'stock_asc': 'Sorted by Available Stock: Lowest First',
        'in_stock_first': 'Sorted: Available In-Stock Products First'
    };

    showToast(msgs[nextVal] || 'Sorted by Available Stock', 'success');
}

function clearInventorySearch() {
    const searchInput = document.getElementById('inventory_search_input');
    if (searchInput) {
        searchInput.value = '';
        filterInventoryTable();
        searchInput.focus();
    }
}

function resetInventoryFilters() {
    const searchInput = document.getElementById('inventory_search_input');
    const categorySelect = document.getElementById('inventory_category_filter');
    const stockSelect = document.getElementById('inventory_stock_filter');
    const fefoSelect = document.getElementById('inventory_fefo_filter');
    const sortSelect = document.getElementById('inventory_sort_filter');

    if (searchInput) searchInput.value = '';
    if (categorySelect) categorySelect.value = '';
    if (stockSelect) stockSelect.value = '';
    if (fefoSelect) fefoSelect.value = '';
    if (sortSelect) sortSelect.value = 'stock_desc';

    currentSortState = { column: 'stock', direction: 'desc' };
    filterInventoryTable();
    showToast('Reset all filters & sorted by Available Stock', 'success');
}

/* =========================================================================
   ONLINE IMAGE FINDER & LIVE SEARCH SYSTEM
   ========================================================================= */
function openOnlineImageSearchFromBtn(btn) {
    let p = {};
    if (btn) {
        try {
            const rawData = btn.getAttribute('data-product');
            p = JSON.parse(rawData);
        } catch (e) {
            console.error(e);
        }
    }

    currentFinderTarget = {
        mode: 'table',
        sku: p.sku || '',
        name: p.name || '',
        targetUrlInputId: '',
        targetPreviewId: ''
    };

    const targetBadge = document.getElementById('finder_target_badge');
    if (targetBadge) {
        targetBadge.textContent = p.name ? `Product: ${p.name}` : 'Catalog Search';
    }

    const queryInput = document.getElementById('finder_search_query');
    if (queryInput) {
        queryInput.value = p.name || '';
    }

    const modal = document.getElementById('onlineImageSearchModal');
    if (modal) {
        modal.style.display = 'flex';
        triggerOnlineImageSearch();
    }
}

function openOnlineImageSearchModalGeneral() {
    currentFinderTarget = {
        mode: 'general',
        sku: '',
        name: '',
        targetUrlInputId: '',
        targetPreviewId: ''
    };

    const targetBadge = document.getElementById('finder_target_badge');
    if (targetBadge) {
        targetBadge.textContent = 'Beverage Catalog Search';
    }

    const queryInput = document.getElementById('finder_search_query');
    if (queryInput) {
        queryInput.value = 'Coca-Cola';
    }

    const modal = document.getElementById('onlineImageSearchModal');
    if (modal) {
        modal.style.display = 'flex';
        triggerOnlineImageSearch();
    }
}

function openOnlineImageSearchForModal(urlInputId, previewId, productName, productSku) {
    currentFinderTarget = {
        mode: 'modal_input',
        sku: productSku || '',
        name: productName || '',
        targetUrlInputId: urlInputId,
        targetPreviewId: previewId
    };

    const targetBadge = document.getElementById('finder_target_badge');
    if (targetBadge) {
        targetBadge.textContent = productName ? `Auto-filling for: ${productName}` : 'Select Image';
    }

    const queryInput = document.getElementById('finder_search_query');
    if (queryInput) {
        queryInput.value = productName || '';
    }

    const modal = document.getElementById('onlineImageSearchModal');
    if (modal) {
        modal.style.display = 'flex';
        triggerOnlineImageSearch();
    }
}

function closeOnlineImageSearchModal() {
    const modal = document.getElementById('onlineImageSearchModal');
    if (currentImageSearchController) {
        currentImageSearchController.abort();
        currentImageSearchController = null;
    }
    if (currentImageSearchTimeout) {
        clearTimeout(currentImageSearchTimeout);
        currentImageSearchTimeout = null;
    }
    if (modal) {
        modal.style.display = 'none';
    }
}

function clearFinderQuery() {
    const queryInput = document.getElementById('finder_search_query');
    if (queryInput) {
        queryInput.value = '';
        queryInput.focus();
    }
}

function appendFinderKeyword(keyword) {
    const queryInput = document.getElementById('finder_search_query');
    if (queryInput) {
        const currentVal = queryInput.value.trim();
        if (!currentVal.toLowerCase().includes(keyword.toLowerCase())) {
            queryInput.value = currentVal ? `${currentVal} ${keyword}` : keyword;
        }
        triggerOnlineImageSearch();
    }
}

function triggerOnlineImageSearch() {
    const queryInput = document.getElementById('finder_search_query');
    const query = (queryInput ? queryInput.value : '').trim();

    const spinner = document.getElementById('finder_loading_spinner');
    const emptyState = document.getElementById('finder_empty_state');
    const grid = document.getElementById('finder_results_grid');

    if (spinner) spinner.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (grid) grid.innerHTML = '';
    updateFinderStatus({count: 0, loading: true});

    if (currentImageSearchController) {
        currentImageSearchController.abort();
    }
    if (currentImageSearchTimeout) {
        clearTimeout(currentImageSearchTimeout);
    }

    currentImageSearchController = new AbortController();
    currentImageSearchTimeout = setTimeout(() => {
        if (currentImageSearchController) {
            currentImageSearchController.abort();
        }
    }, 12000);

    const url = `${BEVERAGE_APP_BASE_URL}/beverage_warehouse.php?action=search_online_images&q=${encodeURIComponent(query)}`;

    fetch(url, { signal: currentImageSearchController.signal })
        .then(response => response.json())
        .then(data => {
            if (currentImageSearchTimeout) {
                clearTimeout(currentImageSearchTimeout);
                currentImageSearchTimeout = null;
            }
            currentImageSearchController = null;
            if (spinner) spinner.style.display = 'none';
            updateFinderStatus(data);
            if (data && data.results && data.results.length > 0) {
                renderFinderResults(data.results);
            } else {
                if (emptyState) emptyState.style.display = 'block';
            }
        })
        .catch(err => {
            if (currentImageSearchTimeout) {
                clearTimeout(currentImageSearchTimeout);
                currentImageSearchTimeout = null;
            }
            currentImageSearchController = null;
            if (err && err.name !== 'AbortError') {
                console.error('Image Search Error:', err);
            }
            if (spinner) spinner.style.display = 'none';
            updateFinderStatus({count: 0, error: true, aborted: err && err.name === 'AbortError'});
            if (emptyState) emptyState.style.display = 'block';
        });
}

function updateFinderStatus(data) {
    const statusEl = document.getElementById('finder_footer_status');
    if (!statusEl) return;

    if (data && data.loading) {
        statusEl.innerHTML = '<span>Searching online image sources. This can take a few seconds when live Google keys are enabled.</span>';
        return;
    }

    if (data && data.error) {
        const message = data.aborted
            ? 'Image search timed out. Try a shorter product name or use Google Images, then paste the image URL.'
            : 'Image search could not complete. Use the Google Images button, then paste the image URL.';
        statusEl.innerHTML = `<span>${message}</span>`;
        return;
    }

    const count = data && typeof data.count !== 'undefined' ? Number(data.count) : 0;
    const google = data && data.google ? data.google : null;
    const googleActive = !!(google && google.configured);
    const googleMessage = googleActive
        ? 'Google live image search is active.'
        : 'Google API keys are not configured yet, so built-in sources and manual Google Images are available.';

    statusEl.innerHTML = `<span><strong>${count}</strong> image${count === 1 ? '' : 's'} found. ${googleMessage}</span>`;
}

function renderFinderResults(results) {
    const grid = document.getElementById('finder_results_grid');
    if (!grid) return;

    grid.innerHTML = '';

    results.forEach((item, index) => {
        const card = document.createElement('div');
        card.className = 'image-result-card';
        
        const isTableDirect = currentFinderTarget.mode === 'table' && currentFinderTarget.sku !== '';
        const btnLabel = isTableDirect ? 'Update &amp; Save' : 'Use This Image';

        const resolvedSrc = resolveProductImageSrc(item.url || item.thumb);
        const resolvedThumb = resolveProductImageSrc(item.thumb || item.url);

        card.innerHTML = `
            <div class="image-card-thumb-wrap">
                <img class="image-card-thumb" src="${escapeHtml(resolvedThumb)}" alt="${escapeHtml(item.title)}" onerror="this.onerror=null; this.src='${BEVERAGE_APP_BASE_URL}/assets/images/warehouse_overview.svg';">
                <span class="image-card-badge">${escapeHtml(item.source || 'Online Photo')}</span>
            </div>
            <div class="image-card-body">
                <div class="image-card-title" title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</div>
                <button type="button" class="image-card-apply-btn" onclick="applyImageFromCard('${escapeHtml(item.url)}', '${escapeHtml(item.title)}', this)">
                    ${btnLabel}
                </button>
            </div>
        `;

        grid.appendChild(card);
    });
}

function applyImageFromCard(imageUrl, title, btn) {
    if (btn) {
        btn.classList.add('applied');
        btn.innerHTML = WA_ICON_CHECK + ' Selected';
    }

    if (currentFinderTarget.mode === 'modal_input') {
        // Apply directly to target inputs in the form
        if (currentFinderTarget.targetUrlInputId) {
            const urlInput = document.getElementById(currentFinderTarget.targetUrlInputId);
            if (urlInput) {
                urlInput.value = imageUrl;
            }
        }
        if (currentFinderTarget.targetPreviewId) {
            setProductPreview(currentFinderTarget.targetPreviewId, resolveProductImageSrc(imageUrl));
            setProductImageSource(currentFinderTarget.targetPreviewId, 'url');
            setProductImageStatus(currentFinderTarget.targetPreviewId, 'Online Image Selected: ' + title, '#059669');
        }

        showToast('Selected online image for product.', 'success');
        closeOnlineImageSearchModal();
    } else if (currentFinderTarget.mode === 'table' && currentFinderTarget.sku) {
        // Quick AJAX update for that product in the table!
        quickUpdateProductImageViaAjax(currentFinderTarget.sku, imageUrl, title);
    } else {
        // General selection
        showToast(`Copied image link: ${title}`, 'success');
        closeOnlineImageSearchModal();
    }
}

function applyCustomFinderUrl() {
    const customInput = document.getElementById('finder_custom_url');
    if (!customInput || !customInput.value.trim()) {
        alert('Please paste a valid image web URL.');
        return;
    }

    const imageUrl = customInput.value.trim();
    applyImageFromCard(imageUrl, 'Custom Web Image', null);
}

function quickUpdateProductImageViaAjax(sku, imageUrl, title) {
    const formData = new FormData();
    formData.append('action', 'quick_update_product_image');
    formData.append('sku', sku);
    formData.append('image_url', imageUrl);
    formData.append('csrf_token', BEVERAGE_CSRF_TOKEN);

    fetch(`${BEVERAGE_APP_BASE_URL}/beverage_warehouse.php`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            // Update table thumbnail live without reloading!
            const imgEls = document.querySelectorAll(`.product-row-img-${CSS.escape(sku)}`);
            imgEls.forEach(img => {
                img.src = resolveProductImageSrc(imageUrl);
            });

            // Update row data-product attribute
            const row = document.getElementById(`product-row-${sku}`);
            if (row) {
                const editBtn = row.querySelector('.details-btn[data-product]');
                if (editBtn) {
                    try {
                        const p = JSON.parse(editBtn.getAttribute('data-product'));
                        p.image = imageUrl;
                        p.image_src = resolveProductImageSrc(imageUrl);
                        editBtn.setAttribute('data-product', JSON.stringify(p));
                    } catch(e) {}
                }
            }

            showToast(`Product image updated and synced to POS for ${sku}.`, 'success');
            closeOnlineImageSearchModal();
        } else {
            alert('Could not update image: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('AJAX update error:', err);
        alert('Error communicating with server.');
    });
}

function openGoogleImageSearch() {
    const queryInput = document.getElementById('finder_search_query');
    const query = (queryInput ? queryInput.value : '').trim() || 'Coca Cola 50cl';
    window.open(`https://www.google.com/search?tbm=isch&q=${encodeURIComponent(query + ' beverage')}`, '_blank');
}

function openBingImageSearch() {
    const queryInput = document.getElementById('finder_search_query');
    const query = (queryInput ? queryInput.value : '').trim() || 'Coca Cola 50cl';
    window.open(`https://www.bing.com/images/search?q=${encodeURIComponent(query + ' beverage')}`, '_blank');
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('beverageToast');
    const msgEl = document.getElementById('beverageToastMsg');
    const iconEl = document.getElementById('beverageToastIcon');

    if (!toast || !msgEl) return;

    msgEl.textContent = message;
    iconEl.innerHTML = type === 'error' ? WA_ICON_WARNING : WA_ICON_CHECK;
    toast.className = `beverage-toast ${type === 'error' ? 'toast-error' : 'toast-success'} show`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 4000);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* =========================================================================
   EXISTING AUDIT TRAIL & MODAL HELPERS
   ========================================================================= */
function openProductAuditModalFromBtn(btn) {
    try {
        var rawData = btn.getAttribute('data-product');
        var p = JSON.parse(rawData);

        document.getElementById('audit_modal_title').innerText = (p.name || 'Product') + ' (' + (p.sku || 'SKU') + ')';
        document.getElementById('audit_modal_subtitle').innerText = 'Category: ' + (p.category || 'General') + ' | Available Stock: ' + (p.stock_crates || 0) + ' Crates/Packs (' + (p.stock_bottles || 0) + ' Bottles)';
        document.getElementById('audit_modal_img').src = p.image_src || resolveProductImageSrc(p.image);

        var allLogs = window.__ALL_AUDIT_LOGS || [];
        var pName = (p.name || '').toLowerCase();
        var pSku = (p.sku || '').toLowerCase();

        var filteredLogs = allLogs.filter(function(log) {
            var itemStr = (log.item || '').toLowerCase();
            var refStr = (log.reference || '').toLowerCase();
            var actionStr = (log.action || '').toLowerCase();
            return itemStr.includes(pName) || itemStr.includes(pSku) || refStr.includes(pSku) || pName.includes(itemStr);
        });

        var tbody = document.getElementById('audit_modal_tbody');
        tbody.innerHTML = '';

        if (filteredLogs.length === 0) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="6" style="padding:1.6rem 1rem;text-align:center;color:#64748b;background:#f8fafc">'
                + '<strong style="display:block;color:#0f172a;margin-bottom:0.25rem">No audit history found for this product yet.</strong>'
                + '<span>Future product edits, image updates, stock movement, POS sales, GRN, and admin updates will appear here.</span>'
                + '</td>';
            tbody.appendChild(emptyTr);
            document.getElementById('productAuditModal').style.display = 'flex';
            return;
        }

        filteredLogs.forEach(function(log) {
            var tr = document.createElement('tr');
            var isDeduction = (log.quantity || '').startsWith('-');
            var qtyColor = isDeduction ? '#dc2626' : '#059669';
            tr.innerHTML = '<td style="padding:0.65rem 0.85rem; border-bottom:1px solid #f1f5f9">' + (log.timestamp || '') + '</td>'
                + '<td style="padding:0.65rem 0.85rem; border-bottom:1px solid #f1f5f9"><strong>' + (log.action || 'Stock Movement') + '</strong></td>'
                + '<td style="padding:0.65rem 0.85rem; border-bottom:1px solid #f1f5f9">' + (log.user || 'Storeman') + '</td>'
                + '<td style="padding:0.65rem 0.85rem; border-bottom:1px solid #f1f5f9; font-weight:800; color:' + qtyColor + '">' + (log.quantity || '') + '</td>'
                + '<td style="padding:0.65rem 0.85rem; border-bottom:1px solid #f1f5f9"><span style="color:#64748b">' + (log.previous_balance || 'N/A') + '</span> ' + WA_ICON_ARROW_RIGHT + ' <strong>' + (log.new_balance || '') + '</strong></td>'
                + '<td style="padding:0.65rem 0.85rem; border-bottom:1px solid #f1f5f9">' + (log.location || 'Warehouse Zone') + '</td>';
            tbody.appendChild(tr);
        });

        document.getElementById('productAuditModal').style.display = 'flex';
    } catch(err) {
        console.error('Error opening audit modal:', err);
        alert('Could not open audit trail: ' + err.message);
    }
}

function resolveProductImageSrc(image) {
    if (!image) {
        return BEVERAGE_APP_BASE_URL + '/assets/images/warehouse_overview.svg';
    }
    if (/^(https?:)?\/\//i.test(image) || image.startsWith('data:') || image.startsWith('/')) {
        return image;
    }
    return BEVERAGE_APP_BASE_URL + '/' + image.replace(/^\/+/, '');
}

function setProductPreview(previewId, src) {
    const preview = document.getElementById(previewId);
    if (!preview || !src) return;

    if (preview.dataset.objectUrl) {
        URL.revokeObjectURL(preview.dataset.objectUrl);
        delete preview.dataset.objectUrl;
    }

    preview.src = src;
}

function setProductImageStatus(previewId, text, color) {
    const statusId = previewId === 'edit_image_preview' ? 'edit_product_image_status' : 'add_product_image_status';
    const status = document.getElementById(statusId);
    if (!status) return;
    status.textContent = text;
    status.style.color = color || '#64748b';
}

function setProductImageSource(previewId, source) {
    const sourceId = previewId === 'edit_image_preview' ? 'edit_product_image_source' : 'add_product_image_source';
    const sourceInput = document.getElementById(sourceId);
    if (sourceInput) sourceInput.value = source;
}

function validateProductImageChoice(form) {
    const sourceInput = form.querySelector('input[name="product_image_source"]');
    const fileInput = form.querySelector('input[name="product_image"]');
    if (sourceInput && sourceInput.value === 'upload' && (!fileInput || !fileInput.files || fileInput.files.length === 0)) {
        alert('Please choose the local image file again before saving.');
        return false;
    }
    return true;
}

function previewSelectedProductImage(input, previewId) {
    const file = input.files && input.files[0] ? input.files[0] : null;
    if (!file) {
        setProductImageStatus(previewId, 'No local image selected');
        setProductImageSource(previewId, 'keep');
        return;
    }

    if (!file.type || !file.type.startsWith('image/')) {
        alert('Please select a valid image file.');
        input.value = '';
        setProductImageStatus(previewId, 'No local image selected');
        setProductImageSource(previewId, 'keep');
        return;
    }

    const preview = document.getElementById(previewId);
    if (!preview) return;

    if (preview.dataset.objectUrl) {
        URL.revokeObjectURL(preview.dataset.objectUrl);
    }

    const objectUrl = URL.createObjectURL(file);
    preview.dataset.objectUrl = objectUrl;
    preview.src = objectUrl;

    const urlInputId = previewId === 'edit_image_preview' ? 'edit_image_url' : 'add_image_url';
    const urlInput = document.getElementById(urlInputId);
    if (urlInput) urlInput.value = '';

    setProductImageSource(previewId, 'upload');
    setProductImageStatus(previewId, 'Selected: ' + file.name, '#059669');
}

function previewProductImageUrl(value, previewId) {
    const imageUrl = value.trim();
    if (!imageUrl) {
        setProductImageStatus(previewId, 'No local image selected');
        setProductImageSource(previewId, 'keep');
        return;
    }

    const fileInputId = previewId === 'edit_image_preview' ? 'edit_product_image' : 'add_product_image';
    const fileInput = document.getElementById(fileInputId);
    if (fileInput) fileInput.value = '';

    setProductImageSource(previewId, 'url');
    setProductImageStatus(previewId, 'Using image URL', '#1d4ed8');
    setProductPreview(previewId, resolveProductImageSrc(imageUrl));
}

function openRegisterProductModal() {
    var modal = document.getElementById('registerProductModal');
    if (modal) {
        var addFile = document.getElementById('add_product_image');
        var addUrl = document.getElementById('add_image_url');
        if (addFile) addFile.value = '';
        if (addUrl) addUrl.value = '';
        setProductImageSource('add_image_preview', 'keep');
        setProductImageStatus('add_image_preview', 'No local image selected');
        setProductPreview('add_image_preview', '');
        modal.style.display = 'flex';
    } else {
        alert('Register Product Modal not found');
    }
}

function openEditProductModalFromBtn(btn) {
    try {
        var rawData = btn.getAttribute('data-product');
        var p = JSON.parse(rawData);
        document.getElementById('edit_sku').value = p.sku || '';
        document.getElementById('edit_sku_display').value = p.sku || '';
        document.getElementById('edit_name').value = p.name || '';
        document.getElementById('edit_category').value = p.category || 'Soft Drinks';
        document.getElementById('edit_packaging').value = p.packaging || 'Crate of 24';
        document.getElementById('edit_wholesale_price').value = p.wholesale_price || 0;
        document.getElementById('edit_crate_deposit').value = p.crate_deposit || 0;
        document.getElementById('edit_stock_crates').value = p.stock_crates || 0;
        document.getElementById('edit_expiry_date').value = p.expiry_date || '';
        document.getElementById('edit_image_url').value = p.image || '';
        var editFile = document.getElementById('edit_product_image');
        if (editFile) editFile.value = '';
        setProductImageSource('edit_image_preview', 'keep');
        setProductImageStatus('edit_image_preview', 'No new local image selected');
        setProductPreview('edit_image_preview', p.image_src || resolveProductImageSrc(p.image));
        
        var modal = document.getElementById('editProductModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    } catch(err) {
        console.error('Error opening edit modal:', err);
        alert('Could not open edit modal: ' + err.message);
    }
}

/* =========================================================================
   EXCEL / CSV BULK IMPORT MODAL LOGIC
   ========================================================================= */
function openExcelImportModal() {
    const modal = document.getElementById('excelImportModal');
    if (modal) {
        const fileInput = document.getElementById('excel_file_input');
        if (fileInput) fileInput.value = '';
        const previewBox = document.getElementById('excel_selected_file_box');
        if (previewBox) previewBox.style.display = 'none';
        modal.style.display = 'flex';
    }
}

function closeExcelImportModal() {
    const modal = document.getElementById('excelImportModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function previewSelectedExcelFile(input) {
    const file = input.files && input.files[0] ? input.files[0] : null;
    const previewBox = document.getElementById('excel_selected_file_box');
    const nameEl = document.getElementById('excel_file_name');
    const metaEl = document.getElementById('excel_file_meta');

    if (!file) {
        if (previewBox) previewBox.style.display = 'none';
        return;
    }

    if (nameEl) nameEl.textContent = file.name;
    if (metaEl) {
        const sizeKb = (file.size / 1024).toFixed(1);
        metaEl.textContent = `File size: ${sizeKb} KB • Type: ${file.type || 'CSV / Spreadsheet'}`;
    }
    if (previewBox) previewBox.style.display = 'block';
}

function handleExcelImportSubmit(form) {
    const fileInput = form.querySelector('input[type="file"]');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please select an Excel or CSV file to import.');
        return false;
    }

    const submitBtn = document.getElementById('excel_submit_btn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = WA_ICON_REFRESH + ' Processing Spreadsheet &amp; Syncing Master...';
    }

    return true;
}

function initializePhysicalCountVariance() {
    const inputs = document.querySelectorAll('.physical-count-input, .damaged-count-input');
    if (!inputs.length) return;

    inputs.forEach((input) => {
        const updateVariance = () => {
            const sku = input.dataset.sku || input.name.replace(/^(physical_counts|damaged_counts)\[/, '').replace(/\]$/, '');
            const target = document.querySelector(`[data-variance-for="${CSS.escape(sku)}"]`);
            if (!target) return;

            const physicalInput = document.querySelector(`input[name="physical_counts[${CSS.escape(sku)}]"]`);
            const damagedInput = document.querySelector(`input[name="damaged_counts[${CSS.escape(sku)}]"]`);
            const rawPhysical = physicalInput ? (physicalInput.value || '').trim() : '';
            const rawDamaged = damagedInput ? (damagedInput.value || '').trim() : '';
            target.classList.remove('positive', 'negative');
            if (rawPhysical === '' && rawDamaged === '') {
                target.textContent = '-';
                return;
            }

            const counted = Math.max(0, parseInt(rawPhysical, 10) || 0);
            const damaged = Math.max(0, parseInt(rawDamaged, 10) || 0);
            const systemSource = physicalInput || input;
            const system = parseInt(systemSource.dataset.system || '0', 10) || 0;
            const totalPhysical = counted + damaged;
            const variance = totalPhysical - system;
            const damagedLabel = damaged > 0 ? ` (${damaged.toLocaleString()} damaged)` : '';
            target.textContent = `${variance > 0 ? '+' : ''}${variance.toLocaleString()} crates/packs${damagedLabel}`;
            if (variance > 0) target.classList.add('positive');
            if (variance < 0) target.classList.add('negative');
        };

        input.addEventListener('input', updateVariance);
        updateVariance();
    });
}

// Automatically sort and initialize table on load
document.addEventListener('DOMContentLoaded', function() {
    applyInventoryDetailPreset();
    initializePhysicalCountVariance();
    if (typeof filterInventoryTable === 'function') {
        filterInventoryTable();

        if ((INVENTORY_DETAIL_PRESET || '').toLowerCase() === 'zones') {
            const zonesPanel = document.getElementById('warehouseZonesPanel');
            if (zonesPanel) {
                setTimeout(() => zonesPanel.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
            }
        }
    }
});
</script>

<?php render_footer(); ?>
