<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Modules/Finance/ChartOfAccountsService.php';
require_once __DIR__ . '/../app/Modules/Finance/DoubleEntryJournalService.php';
require_once __DIR__ . '/../app/Modules/Finance/AccountsReceivableService.php';
require_once __DIR__ . '/../app/Modules/Finance/AccountsPayableService.php';
require_once __DIR__ . '/../app/Modules/Finance/FinancialStatementService.php';
require_once __DIR__ . '/../app/Modules/Finance/BankReconciliationService.php';
require_once __DIR__ . '/../app/Modules/Finance/AiFinancialAnalystService.php';

use App\Modules\Finance\ChartOfAccountsService;
use App\Modules\Finance\DoubleEntryJournalService;
use App\Modules\Finance\AccountsReceivableService;
use App\Modules\Finance\AccountsPayableService;
use App\Modules\Finance\FinancialStatementService;
use App\Modules\Finance\BankReconciliationService;
use App\Modules\Finance\AiFinancialAnalystService;

// Ensure user authentication
$user = current_user();
if ($user && canonical_role($user['role'] ?? '') === 'pos') {
    flash('Access restricted: POS cashier accounts cannot access finance or admin dashboards.');
    redirect('beverage_pos.php?tab=pos');
}
if (!$user) {
    redirect('login.php');
}

$selectedCompany = 'beverage';
$_SESSION['current_company_id'] = $selectedCompany;

// Data Services Fetching
$accounts = ChartOfAccountsService::getAccounts($selectedCompany);
$journals = DoubleEntryJournalService::getJournalEntries($selectedCompany);
$debtors = AccountsReceivableService::getDebtors($selectedCompany);
$creditors = AccountsPayableService::getCreditors($selectedCompany);
$pnl = FinancialStatementService::getProfitAndLoss($selectedCompany);
$balanceSheet = FinancialStatementService::getBalanceSheet($selectedCompany);
$trialBalance = FinancialStatementService::getTrialBalance($selectedCompany);
$bankFeeds = BankReconciliationService::getBankFeeds($selectedCompany);
$arTotal = array_sum(array_column($debtors, 'balance_due'));
$apTotal = array_sum(array_column($creditors, 'balance_due'));
$bankMatched = count(array_filter($bankFeeds, fn($feed) => ($feed['status'] ?? '') === 'Matched'));
$divisionName = 'Empress Tee - Beverage Depot';

$actionMessage = null;
$aiResponse = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    $financeWriteActions = ['add_account', 'post_jv', 'receive_ar_payment', 'pay_ap_supplier'];
    if (in_array($formAction, $financeWriteActions, true) && !\App\Modules\HrPayroll\HrPayrollService::userHasPermission($user, 'finance.manage')) {
        http_response_code(403);
        $actionMessage = 'Access denied: finance write permission is required for this action.';
    } elseif ($formAction === 'add_account') {
        ChartOfAccountsService::addAccount($selectedCompany, $_POST);
        $accounts = ChartOfAccountsService::getAccounts($selectedCompany);
        $actionMessage = "✅ New Account Code " . e($_POST['code']) . " - " . e($_POST['name']) . " created in Chart of Accounts!";
    } elseif ($formAction === 'post_jv') {
        try {
            $debitCode = (string)($_POST['debit_account'] ?? '1110');
            $creditCode = (string)($_POST['credit_account'] ?? '4110');
            $amount = (float)($_POST['amount'] ?? 0.0);

            $jv = DoubleEntryJournalService::createJournalEntry($selectedCompany, [
                'event_type' => 'manual_jv',
                'reference' => trim((string)($_POST['reference'] ?? 'JV-MANUAL')),
                'narration' => trim((string)($_POST['narration'] ?? 'Manual Journal Entry')),
                'lines' => [
                    ['code' => $debitCode, 'name' => "Account {$debitCode}", 'debit' => $amount, 'credit' => 0.00],
                    ['code' => $creditCode, 'name' => "Account {$creditCode}", 'debit' => 0.00, 'credit' => $amount],
                ]
            ]);
            $journals = DoubleEntryJournalService::getJournalEntries($selectedCompany);
            $actionMessage = "✅ Journal Voucher Posted! Code: {$jv['voucher_number']}. Total Debit: ₦" . number_format($amount, 2);
        } catch (\Throwable $e) {
            $actionMessage = "⚠️ Error Posting Journal: " . $e->getMessage();
        }
    } elseif ($formAction === 'receive_ar_payment') {
        $invNum = (string)($_POST['invoice_number'] ?? '');
        $amt = (float)($_POST['payment_amount'] ?? 0.0);
        AccountsReceivableService::recordPayment($selectedCompany, $invNum, $amt);
        $debtors = AccountsReceivableService::getDebtors($selectedCompany);
        $actionMessage = "✅ Customer Payment of ₦" . number_format($amt, 2) . " recorded for Invoice {$invNum}!";
    } elseif ($formAction === 'pay_ap_supplier') {
        $billNum = (string)($_POST['bill_number'] ?? '');
        $amt = (float)($_POST['payment_amount'] ?? 0.0);
        AccountsPayableService::recordSupplierPayment($selectedCompany, $billNum, $amt);
        $creditors = AccountsPayableService::getCreditors($selectedCompany);
        $actionMessage = "✅ Supplier Payment Voucher of ₦" . number_format($amt, 2) . " posted for Bill {$billNum}!";
    } elseif ($formAction === 'ai_ask_finance') {
        $prompt = trim((string)($_POST['ai_prompt'] ?? ''));
        $aiResponse = AiFinancialAnalystService::askAnalyst($prompt, $selectedCompany);
    }
}

render_header('Finance & Accounting System', $user, false);
?>

<style>
.fin-container {
    padding: 1.5rem;
    background: #ffffff;
    color: #0f172a;
    min-height: 100vh;
    font-family: 'Inter', sans-serif;
}
.fin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.fin-title-group h1 {
    font-family: 'Outfit', sans-serif;
    font-size: 1.6rem;
    font-weight: 700;
    margin: 0;
    background: linear-gradient(135deg, #059669, #2563eb);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.fin-title-group p {
    margin: 0.25rem 0 0 0;
    color: #64748b;
    font-size: 0.85rem;
}
.fin-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.fin-kpi-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.2rem;
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.fin-kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #059669, #2563eb);
}
.fin-kpi-card span {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    font-weight: 600;
}
.fin-kpi-card h2 {
    font-size: 1.5rem;
    font-family: 'Outfit', sans-serif;
    margin: 0.4rem 0 0.2rem 0;
    color: #0f172a;
}
.fin-kpi-card small {
    font-size: 0.75rem;
    color: #059669;
    font-weight: 600;
}
.fin-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.fin-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.2rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}
.fin-card-head h3 {
    margin: 0;
    font-size: 1.1rem;
    font-family: 'Outfit', sans-serif;
    color: #059669;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.fin-table-wrap {
    overflow-x: auto;
}
.fin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.fin-table th {
    background: #f8fafc;
    color: #475569;
    text-transform: uppercase;
    font-size: 0.72rem;
    letter-spacing: 0.05em;
    padding: 0.8rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.fin-table td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    color: #1e293b;
}
.fin-table tr:hover td {
    background: #f8fafc;
}
.fin-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}
.fin-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.fin-form-group label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
}
.fin-form-group input, .fin-form-group select, .fin-form-group textarea {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 0.6rem 0.8rem;
    color: #0f172a;
    font-size: 0.85rem;
}
.fin-btn {
    background: linear-gradient(135deg, #059669, #10b981);
    color: #ffffff;
    border: none;
    padding: 0.65rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
}
.fin-btn:hover {
    filter: brightness(1.15);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}
.fin-btn-ghost {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #334155;
}
.tab-content { display: none; }
.tab-content.active { display: block; }
.finance-command { display:grid; gap:1rem; }
.finance-hero { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; box-shadow:0 10px 28px rgba(15,23,42,.035); }
.finance-hero-title { display:flex; align-items:center; gap:1rem; }
.finance-hero-icon { width:56px; height:56px; border-radius:10px; background:#ecfdf5; color:#047857; display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:900; }
.finance-hero h2 { margin:0; font-family:'Outfit',sans-serif; font-size:1.6rem; color:#0f172a; }
.finance-hero p { margin:0.22rem 0 0; color:#64748b; font-size:0.86rem; }
.finance-actions { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; justify-content:flex-end; }
.finance-select { background:#fff; border:1px solid #dbe4f0; border-radius:7px; padding:.62rem .85rem; color:#0f172a; font-weight:800; font-size:.78rem; }
.finance-tabbar { display:flex; gap:.55rem; flex-wrap:wrap; }
.finance-tabbar button { background:#fff; color:#334155; border:1px solid #dbe4f0; border-radius:7px; padding:.55rem .78rem; font-weight:800; font-size:.74rem; cursor:pointer; }
.finance-tabbar button:hover { border-color:#10b981; color:#047857; }
.finance-metric-row { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 10px 28px rgba(15,23,42,.035); }
.finance-stat { padding:1rem; border-right:1px solid #e8eef7; min-width:0; }
.finance-stat:last-child { border-right:0; }
.finance-stat small { display:block; color:#64748b; font-size:.64rem; font-weight:900; text-transform:uppercase; }
.finance-stat strong { display:block; color:#0f172a; font-family:'Outfit',sans-serif; font-size:1.18rem; line-height:1.1; margin-top:.3rem; white-space:nowrap; }
.finance-stat span { display:block; color:#64748b; font-size:.68rem; font-weight:700; margin-top:.35rem; }
.finance-grid { display:grid; grid-template-columns:1.45fr 1fr 1fr; gap:1rem; }
.finance-panel { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem; box-shadow:0 10px 28px rgba(15,23,42,.035); min-width:0; }
.finance-panel-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.85rem; }
.finance-panel-head h3 { margin:0; font-family:'Outfit',sans-serif; color:#0f172a; font-size:.95rem; font-weight:800; }
.finance-panel-head span, .finance-panel-head a { color:#2563eb; font-size:.7rem; font-weight:800; text-decoration:none; }
.finance-flow { width:100%; height:210px; display:block; background:linear-gradient(180deg,#fff,#f8fbff); border-bottom:1px solid #eef2f7; }
.finance-bars { display:grid; gap:.75rem; }
.finance-bar-row { display:grid; grid-template-columns:1fr auto; gap:.75rem; align-items:center; font-size:.76rem; }
.finance-track { height:8px; background:#eef2f7; border-radius:99px; overflow:hidden; grid-column:1 / -1; }
.finance-track div { height:100%; border-radius:99px; }
.finance-list { display:grid; gap:.6rem; }
.finance-list-row { display:grid; grid-template-columns:1fr auto; gap:.7rem; padding:.7rem; border:1px solid #e8eef7; border-radius:8px; align-items:center; }
.finance-list-row strong { color:#0f172a; font-size:.78rem; display:block; }
.finance-list-row span { color:#64748b; font-size:.7rem; }
.finance-status { border-radius:99px; padding:.2rem .55rem; font-size:.66rem; font-weight:900; white-space:nowrap; }
.finance-ok { background:#d1fae5; color:#047857; }
.finance-warn { background:#fef3c7; color:#b45309; }
.finance-bad { background:#fee2e2; color:#dc2626; }
@media (max-width:1180px) {
    .finance-metric-row, .finance-grid { grid-template-columns:1fr; }
    .finance-stat { border-right:0; border-bottom:1px solid #e8eef7; }
    .finance-hero { flex-direction:column; align-items:flex-start; }
}

@media (max-width:820px) {
    .fin-container {
        width:100%;
        max-width:100%;
        min-width:0;
        padding:0.9rem !important;
        overflow-x:hidden;
    }
    .finance-hero,
    .fin-header,
    .fin-card-head {
        flex-direction:column !important;
        align-items:stretch !important;
    }
    .finance-hero {
        padding:0.95rem !important;
        border-radius:10px !important;
    }
    .finance-hero-title {
        align-items:flex-start;
        gap:0.75rem;
    }
    .finance-hero-icon {
        width:44px;
        height:44px;
        border-radius:10px;
        font-size:1.18rem;
        flex:0 0 44px;
    }
    .finance-hero h2,
    .fin-title-group h1 {
        font-size:1.12rem !important;
        line-height:1.2;
    }
    .finance-hero p,
    .fin-title-group p {
        font-size:0.78rem !important;
        line-height:1.45;
    }
    .finance-actions,
    .finance-tabbar {
        width:100%;
        justify-content:flex-start !important;
    }
    .finance-tabbar {
        display:grid;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:0.55rem;
    }
    .finance-tabbar button,
    .fin-btn,
    .finance-select {
        width:100%;
        justify-content:center;
        white-space:normal;
        min-height:42px;
    }
    .finance-metric-row,
    .finance-grid,
    .fin-kpi-grid,
    .fin-form-grid {
        grid-template-columns:1fr !important;
    }
    .finance-stat {
        border-right:0;
        border-bottom:1px solid #e8eef7;
        padding:0.85rem;
    }
    .finance-stat strong,
    .fin-kpi-card h2 {
        font-size:1.08rem !important;
        white-space:normal;
        overflow-wrap:anywhere;
    }
    .finance-panel,
    .fin-card,
    .fin-kpi-card {
        padding:0.9rem !important;
        border-radius:10px !important;
        min-width:0;
    }
    .fin-table-wrap {
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
    }
    .fin-table {
        min-width:700px;
    }
    .tab-content form[style*="grid-template-columns"],
    .tab-content form > div[style*="display:flex"],
    .tab-content > div[style*="grid-template-columns:1fr 1fr"],
    .tab-content section[style*="grid-template-columns"] {
        display:grid !important;
        grid-template-columns:1fr !important;
        gap:0.75rem !important;
    }
    .tab-content div[style*="justify-content:space-between"] {
        flex-wrap:wrap;
        gap:0.45rem;
    }
    .tab-content input,
    .tab-content select,
    .tab-content textarea {
        width:100%;
        max-width:100%;
    }
}

@media (max-width:480px) {
    .fin-container {
        padding:0.72rem !important;
    }
    .finance-tabbar {
        grid-template-columns:1fr;
    }
    .finance-hero-title {
        display:grid;
        grid-template-columns:auto 1fr;
    }
}
</style>

<div class="fin-container">
    <!-- Header Banner -->
    <div class="finance-hero">
        <div class="finance-hero-title">
            <div class="finance-hero-icon">₦</div>
            <div>
                <h2>Finance &amp; Accounting</h2>
                <p>General ledger, receivables, payables, bank reconciliation, statements, and beverage depot controls</p>
            </div>
        </div>
        <div class="finance-actions">
            <span class="finance-select" style="display:inline-flex;align-items:center">Empress Tee - Beverage Depot</span>
            <button class="fin-btn" onclick="switchFinTab('tab-jv-finance')">+ Post Journal</button>
            <button class="fin-btn" onclick="switchFinTab('tab-ai-finance')" style="background:linear-gradient(135deg,#2563eb,#3b82f6)">AI Financial Analyst</button>
        </div>
    </div>

    <div class="finance-tabbar" style="margin-bottom:1rem">
        <button type="button" onclick="switchFinTab('tab-dash-finance')">Dashboard</button>
        <button type="button" onclick="switchFinTab('tab-coa-finance')">Chart of Accounts</button>
        <button type="button" onclick="switchFinTab('tab-jv-finance')">Journal Vouchers</button>
        <button type="button" onclick="switchFinTab('tab-ar-finance')">Accounts Receivable</button>
        <button type="button" onclick="switchFinTab('tab-ap-finance')">Accounts Payable</button>
        <button type="button" onclick="switchFinTab('tab-pnl-finance')">P&amp;L</button>
        <button type="button" onclick="switchFinTab('tab-bs-finance')">Balance Sheet</button>
        <button type="button" onclick="switchFinTab('tab-bank-finance')">Bank Recon</button>
    </div>

    <?php if ($actionMessage): ?>
        <div style="padding:1rem 1.2rem;background:#d1fae5;border:1px solid #a7f3d0;border-radius:8px;color:#047857;margin-bottom:1.5rem;font-size:0.9rem;display:flex;align-items:center;justify-content:space-between">
            <span><?= e($actionMessage) ?></span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;color:#047857;cursor:pointer;font-weight:bold">✕</button>
        </div>
    <?php endif; ?>

    <!-- ==========================================
         TAB 1: FINANCIAL DASHBOARD
         ========================================== -->
    <div id="tab-dash-finance" class="tab-content active">
        <div class="finance-command">
            <section class="finance-metric-row">
                <div class="finance-stat"><small>Total Revenue</small><strong>₦<?= number_format($pnl['total_revenue'], 0) ?></strong><span style="color:#059669">↑ 12.4% vs last period</span></div>
                <div class="finance-stat"><small>COGS</small><strong>₦<?= number_format($pnl['total_cogs'], 0) ?></strong><span>Gross margin <?= e((string)$pnl['gross_margin_percent']) ?>%</span></div>
                <div class="finance-stat"><small>Net Profit</small><strong>₦<?= number_format($pnl['net_profit'], 0) ?></strong><span style="color:#059669">Net margin <?= e((string)$pnl['net_margin_percent']) ?>%</span></div>
                <div class="finance-stat"><small>Accounts Receivable</small><strong>₦<?= number_format($arTotal, 0) ?></strong><span style="color:#dc2626"><?= count(array_filter($debtors, fn($d) => ($d['balance_due'] ?? 0) > 0)) ?> open invoices</span></div>
                <div class="finance-stat"><small>Accounts Payable</small><strong>₦<?= number_format($apTotal, 0) ?></strong><span style="color:#d97706">Vendor payables</span></div>
                <div class="finance-stat"><small>Trial Balance</small><strong><?= $trialBalance['total_debit'] === $trialBalance['total_credit'] ? 'Balanced' : 'Review' ?></strong><span>₦<?= number_format($trialBalance['total_debit'], 0) ?> debit</span></div>
            </section>

            <section class="finance-grid">
                <div class="finance-panel">
                    <div class="finance-panel-head"><h3>Cashflow &amp; Profit Trend</h3><span><?= e($divisionName) ?></span></div>
                    <svg class="finance-flow" viewBox="0 0 620 210" preserveAspectRatio="none">
                        <defs><linearGradient id="finArea" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#10b981" stop-opacity=".25"/><stop offset="1" stop-color="#10b981" stop-opacity="0"/></linearGradient></defs>
                        <path d="M0 145 L55 118 L110 132 L165 86 L220 105 L275 74 L330 96 L385 68 L440 83 L495 52 L555 65 L620 38 L620 210 L0 210 Z" fill="url(#finArea)"/>
                        <path d="M0 145 L55 118 L110 132 L165 86 L220 105 L275 74 L330 96 L385 68 L440 83 L495 52 L555 65 L620 38" fill="none" stroke="#10b981" stroke-width="4"/>
                        <path d="M0 175 L55 158 L110 165 L165 132 L220 140 L275 119 L330 128 L385 112 L440 121 L495 99 L555 103 L620 84" fill="none" stroke="#2563eb" stroke-width="3"/>
                    </svg>
                    <div style="display:flex;justify-content:space-between;color:#94a3b8;font-size:.68rem;font-weight:700;margin-top:.45rem"><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span></div>
                </div>

                <div class="finance-panel">
                    <div class="finance-panel-head"><h3>Ledger Health</h3><a href="<?= e(url('finance.php?tab=tab-bs-finance')) ?>" onclick="switchFinTab('tab-bs-finance');return false">View statements</a></div>
                    <div class="finance-bars">
                        <div class="finance-bar-row"><strong>Gross Margin</strong><span><?= e((string)$pnl['gross_margin_percent']) ?>%</span><div class="finance-track"><div style="width:<?= min(100, (float)$pnl['gross_margin_percent']) ?>%;background:#10b981"></div></div></div>
                        <div class="finance-bar-row"><strong>Net Margin</strong><span><?= e((string)$pnl['net_margin_percent']) ?>%</span><div class="finance-track"><div style="width:<?= min(100, (float)$pnl['net_margin_percent']) ?>%;background:#2563eb"></div></div></div>
                        <div class="finance-bar-row"><strong>Bank Match Rate</strong><span><?= count($bankFeeds) ? round(($bankMatched / count($bankFeeds)) * 100) : 0 ?>%</span><div class="finance-track"><div style="width:<?= count($bankFeeds) ? round(($bankMatched / count($bankFeeds)) * 100) : 0 ?>%;background:#7c3aed"></div></div></div>
                        <div class="finance-bar-row"><strong>AR Exposure</strong><span>₦<?= number_format($arTotal, 0) ?></span><div class="finance-track"><div style="width:58%;background:#f59e0b"></div></div></div>
                    </div>
                </div>

                <div class="finance-panel">
                    <div class="finance-panel-head"><h3>Beverage Controls</h3><span>BEVERAGE</span></div>
                    <div class="finance-list">
                        <div class="finance-list-row"><div><strong>Beverage Ledger Scope</strong><span>COA, GL, AR and AP scoped to beverage operations</span></div><span class="finance-status finance-ok">Active</span></div>
                        <div class="finance-list-row"><div><strong>Double Entry Enforcement</strong><span>Debits must equal credits</span></div><span class="finance-status finance-ok">Balanced</span></div>
                        <div class="finance-list-row"><div><strong>Bank Feed Matching</strong><span><?= $bankMatched ?> of <?= count($bankFeeds) ?> feed items matched</span></div><span class="finance-status finance-warn">Review</span></div>
                    </div>
                </div>
            </section>

            <section class="finance-grid" style="grid-template-columns:1.35fr 1fr 1fr">
                <div class="finance-panel">
                    <div class="finance-panel-head"><h3>Recent Double-Entry Journal Postings</h3><a href="<?= e(url('finance.php?tab=tab-jv-finance')) ?>" onclick="switchFinTab('tab-jv-finance');return false">Post JV</a></div>
                    <div class="fin-table-wrap">
                        <table class="fin-table">
                            <thead><tr><th>Voucher</th><th>Narration</th><th>Debit</th><th>Credit</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($journals, 0, 5) as $j): ?>
                                    <tr>
                                        <td><strong><?= e($j['voucher_number']) ?></strong><br><small><?= date('Y-m-d H:i', strtotime($j['date'])) ?></small></td>
                                        <td><strong><?= e($j['narration']) ?></strong><br><small>Ref: <?= e($j['reference']) ?></small></td>
                                        <td style="font-weight:800;color:#059669">₦<?= number_format($j['total_debit'], 2) ?></td>
                                        <td style="font-weight:800;color:#2563eb">₦<?= number_format($j['total_credit'], 2) ?></td>
                                        <td><span class="finance-status finance-ok">Balanced</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="finance-panel">
                    <div class="finance-panel-head"><h3>Receivables Watch</h3><a href="<?= e(url('finance.php?tab=tab-ar-finance')) ?>" onclick="switchFinTab('tab-ar-finance');return false">Open AR</a></div>
                    <div class="finance-list">
                        <?php foreach (array_slice($debtors, 0, 4) as $d): ?>
                            <div class="finance-list-row"><div><strong><?= e($d['customer_name']) ?></strong><span><?= e($d['invoice_number']) ?> • <?= e($d['aging']) ?></span></div><span class="finance-status <?= ($d['balance_due'] ?? 0) > 0 ? 'finance-bad' : 'finance-ok' ?>">₦<?= number_format($d['balance_due'], 0) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="finance-panel">
                    <div class="finance-panel-head"><h3>Payables Watch</h3><a href="<?= e(url('finance.php?tab=tab-ap-finance')) ?>" onclick="switchFinTab('tab-ap-finance');return false">Open AP</a></div>
                    <div class="finance-list">
                        <?php foreach (array_slice($creditors, 0, 4) as $c): ?>
                            <div class="finance-list-row"><div><strong><?= e($c['supplier_name']) ?></strong><span><?= e($c['bill_number']) ?> • <?= e($c['terms']) ?></span></div><span class="finance-status <?= ($c['balance_due'] ?? 0) > 0 ? 'finance-warn' : 'finance-ok' ?>">₦<?= number_format($c['balance_due'], 0) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- ==========================================
         TAB 2: CHART OF ACCOUNTS (COA)
         ========================================== -->
    <div id="tab-coa-finance" class="tab-content">
        <div class="fin-card">
            <div class="fin-card-head">
                <h3>📖 Enterprise Chart of Accounts (COA Tree)</h3>
                <button class="fin-btn" onclick="toggleAddAccountForm()">➕ Add New COA Account</button>
            </div>

            <!-- Add Account Form -->
            <div id="addAccountFormCard" style="display:none;background:#f8fafc;padding:1.2rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1.5rem">
                <h4 style="margin:0 0 0.8rem 0;color:#059669">Create New Chart of Accounts Entry</h4>
                <form method="POST" action="finance.php?tab=tab-coa-finance">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="form_action" value="add_account">
                    <div class="fin-form-grid">
                        <div class="fin-form-group">
                            <label>Account Code *</label>
                            <input type="text" name="code" placeholder="e.g. 1130 / 6510" required>
                        </div>
                        <div class="fin-form-group">
                            <label>Account Title Name *</label>
                            <input type="text" name="name" placeholder="e.g. Office Equipment &amp; Furniture" required>
                        </div>
                        <div class="fin-form-group">
                            <label>Account Type *</label>
                            <select name="type" required>
                                <option value="Asset">Asset (1000s)</option>
                                <option value="Liability">Liability (2000s)</option>
                                <option value="Equity">Equity (3000s)</option>
                                <option value="Revenue">Revenue (4000s)</option>
                                <option value="COGS">COGS (5000s)</option>
                                <option value="Expense">Expense (6000s)</option>
                            </select>
                        </div>
                        <div class="fin-form-group">
                            <label>Parent Header Code</label>
                            <input type="text" name="parent" placeholder="1000">
                        </div>
                    </div>
                    <div style="margin-top:1rem;display:flex;gap:1rem;justify-content:flex-end">
                        <button type="button" class="fin-btn fin-btn-ghost" onclick="toggleAddAccountForm()">Cancel</button>
                        <button type="submit" class="fin-btn">💾 Save Account Entry</button>
                    </div>
                </form>
            </div>

            <div class="fin-table-wrap">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Account Type</th>
                            <th>Parent Code</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                            <tr>
                                <td><code style="font-size:0.9rem;font-weight:700"><?= e($acc['code']) ?></code></td>
                                <td><strong><?= e($acc['name']) ?></strong></td>
                                <td>
                                    <span style="padding:0.2rem 0.5rem;border-radius:6px;font-size:0.75rem;font-weight:600;background:<?= match($acc['type']) { 'Asset' => '#dbeafe', 'Liability' => '#fef3c7', 'Equity' => '#f3e8ff', 'Revenue' => '#d1fae5', 'COGS' => '#ffedd5', default => '#ffe4e6' } ?>;color:#0f172a">
                                        <?= e($acc['type']) ?>
                                    </span>
                                </td>
                                <td><?= e($acc['parent'] ?? 'Root') ?></td>
                                <td><span style="color:#059669;font-size:0.8rem;font-weight:600">● Active</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==========================================
         TAB 3: DOUBLE ENTRY JOURNAL VOUCHERS
         ========================================== -->
    <div id="tab-jv-finance" class="tab-content">
        <div class="fin-card">
            <div class="fin-card-head">
                <h3>⚖️ Post Double-Entry Journal Voucher (JV)</h3>
                <span style="color:#059669;font-size:0.85rem;font-weight:600">Enforced Balance: $\sum \text{Debits} = \sum \text{Credits}$</span>
            </div>

            <form method="POST" action="finance.php?tab=tab-jv-finance">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="form_action" value="post_jv">
                
                <div class="fin-form-grid" style="margin-bottom:1rem">
                    <div class="fin-form-group">
                        <label>Debit Account *</label>
                        <select name="debit_account" required>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= e($a['code']) ?>"><?= e($a['code']) ?> - <?= e($a['name']) ?> (<?= e($a['type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fin-form-group">
                        <label>Credit Account *</label>
                        <select name="credit_account" required>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= e($a['code']) ?>" <?= $a['code'] === '4110' ? 'selected' : '' ?>><?= e($a['code']) ?> - <?= e($a['name']) ?> (<?= e($a['type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fin-form-group">
                        <label>Transaction Amount (₦) *</label>
                        <input type="number" step="0.01" name="amount" placeholder="50000.00" required>
                    </div>
                    <div class="fin-form-group">
                        <label>Reference Voucher #</label>
                        <input type="text" name="reference" placeholder="e.g. REF-2026-001">
                    </div>
                </div>

                <div class="fin-form-group" style="margin-bottom:1.5rem">
                    <label>Journal Narration &amp; Description *</label>
                    <input type="text" name="narration" placeholder="e.g. Monthly utility power bill payment for Victoria Island depot" required>
                </div>

                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="fin-btn">💾 Post Journal Voucher Entry</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================
         TAB 4: ACCOUNTS RECEIVABLE (AR / DEBTORS)
         ========================================== -->
    <div id="tab-ar-finance" class="tab-content">
        <div class="fin-card">
            <div class="fin-card-head">
                <h3>💳 Accounts Receivable (Trade Debtors Aging Schedule)</h3>
            </div>

            <!-- Payment Form Card -->
            <div style="background:#f8fafc;padding:1.2rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1.5rem">
                <h4 style="margin:0 0 0.8rem 0;color:#059669">Record Customer Payment Receipt</h4>
                <form method="POST" action="finance.php?tab=tab-ar-finance" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:1rem;align-items:end">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="form_action" value="receive_ar_payment">
                    <div class="fin-form-group">
                        <label>Select Outstanding Invoice:</label>
                        <select name="invoice_number" required>
                            <?php foreach ($debtors as $d): ?>
                                <?php if ($d['balance_due'] > 0): ?>
                                    <option value="<?= e($d['invoice_number']) ?>"><?= e($d['invoice_number']) ?> - <?= e($d['customer_name']) ?> (Balance: ₦<?= number_format($d['balance_due'], 2) ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fin-form-group">
                        <label>Payment Amount Received (₦):</label>
                        <input type="number" step="0.01" name="payment_amount" placeholder="100000.00" required>
                    </div>
                    <div class="fin-form-group">
                        <label>Payment Reference:</label>
                        <input type="text" name="payment_ref" placeholder="BANK TRF / CHEQUE">
                    </div>
                    <button type="submit" class="fin-btn">💳 Record Receipt</button>
                </form>
            </div>

            <div class="fin-table-wrap">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Invoice #</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Total Invoice</th>
                            <th>Paid Amount</th>
                            <th>Balance Due</th>
                            <th>Aging Period</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debtors as $d): ?>
                            <tr>
                                <td><strong><?= e($d['customer_name']) ?></strong></td>
                                <td><code><?= e($d['invoice_number']) ?></code></td>
                                <td><?= e($d['invoice_date']) ?></td>
                                <td><?= e($d['due_date']) ?></td>
                                <td>₦<?= number_format($d['total_amount'], 2) ?></td>
                                <td style="color:#059669">₦<?= number_format($d['paid_amount'], 2) ?></td>
                                <td style="font-weight:700;color:<?= $d['balance_due'] > 0 ? '#ef4444' : '#059669' ?>">₦<?= number_format($d['balance_due'], 2) ?></td>
                                <td><?= e($d['aging']) ?></td>
                                <td>
                                    <span style="padding:0.25rem 0.6rem;border-radius:99px;font-size:0.75rem;font-weight:600;background:<?= match($d['status']) { 'Paid' => '#d1fae5', 'Partially Paid' => '#ffedd5', default => '#ffe4e6' } ?>;color:#0f172a">
                                        <?= e($d['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==========================================
         TAB 5: ACCOUNTS PAYABLE (AP / CREDITORS)
         ========================================== -->
    <div id="tab-ap-finance" class="tab-content">
        <div class="fin-card">
            <div class="fin-card-head">
                <h3>🏬 Accounts Payable (Trade Creditors &amp; Brewery Payables)</h3>
            </div>

            <!-- Supplier Payment Voucher Form -->
            <div style="background:#f8fafc;padding:1.2rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1.5rem">
                <h4 style="margin:0 0 0.8rem 0;color:#2563eb">Issue Supplier Payment Voucher (PV)</h4>
                <form method="POST" action="finance.php?tab=tab-ap-finance" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:1rem;align-items:end">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="form_action" value="pay_ap_supplier">
                    <div class="fin-form-group">
                        <label>Select Open Supplier Bill:</label>
                        <select name="bill_number" required>
                            <?php foreach ($creditors as $c): ?>
                                <?php if ($c['balance_due'] > 0): ?>
                                    <option value="<?= e($c['bill_number']) ?>"><?= e($c['bill_number']) ?> - <?= e($c['supplier_name']) ?> (Balance: ₦<?= number_format($c['balance_due'], 2) ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fin-form-group">
                        <label>Voucher Amount to Pay (₦):</label>
                        <input type="number" step="0.01" name="payment_amount" placeholder="380000.00" required>
                    </div>
                    <div class="fin-form-group">
                        <label>Bank Source Account:</label>
                        <select name="bank_account">
                            <option>First Bank Operating Acc (2034991028)</option>
                            <option>Zenith Bank Central Acc (1015882049)</option>
                        </select>
                    </div>
                    <button type="submit" class="fin-btn" style="background:linear-gradient(135deg,#2563eb,#3b82f6)">📄 Issue Voucher</button>
                </form>
            </div>

            <div class="fin-table-wrap">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Supplier / Brewery Partner</th>
                            <th>Bill #</th>
                            <th>Bill Date</th>
                            <th>Due Date</th>
                            <th>Total Bill</th>
                            <th>Paid Amount</th>
                            <th>Balance Payable</th>
                            <th>Payment Terms</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($creditors as $c): ?>
                            <tr>
                                <td><strong><?= e($c['supplier_name']) ?></strong></td>
                                <td><code><?= e($c['bill_number']) ?></code></td>
                                <td><?= e($c['bill_date']) ?></td>
                                <td><?= e($c['due_date']) ?></td>
                                <td>₦<?= number_format($c['total_amount'], 2) ?></td>
                                <td style="color:#059669">₦<?= number_format($c['paid_amount'], 2) ?></td>
                                <td style="font-weight:700;color:<?= $c['balance_due'] > 0 ? '#ef4444' : '#059669' ?>">₦<?= number_format($c['balance_due'], 2) ?></td>
                                <td><?= e($c['terms']) ?></td>
                                <td>
                                    <span style="padding:0.25rem 0.6rem;border-radius:99px;font-size:0.75rem;font-weight:600;background:<?= match($c['status']) { 'Paid' => '#d1fae5', 'Partially Paid' => '#ffedd5', default => '#ffe4e6' } ?>;color:#0f172a">
                                        <?= e($c['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==========================================
         TAB 6: PROFIT & LOSS STATEMENT (P&L)
         ========================================== -->
    <div id="tab-pnl-finance" class="tab-content">
        <div class="fin-card">
            <div class="fin-card-head">
                <h3>📈 Profit &amp; Loss Statement (Income Statement)</h3>
                <span style="color:#059669;font-size:0.85rem;font-weight:700"><?= e($pnl['period']) ?></span>
            </div>

            <div style="max-width:700px;margin:0 auto;background:#f8fafc;padding:1.5rem;border-radius:12px;border:1px solid #e2e8f0">
                <div style="display:flex;justify-content:space-between;padding:0.8rem 0;border-bottom:1px solid #cbd5e1;font-size:1.1rem;font-weight:700">
                    <span>Revenue (Gross Sales)</span>
                    <span style="color:#059669">₦<?= number_format($pnl['total_revenue'], 2) ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;padding:0.8rem 0;border-bottom:1px solid #cbd5e1;font-size:1rem;color:#ef4444">
                    <span>Less: Cost of Goods Sold (COGS)</span>
                    <span>(₦<?= number_format($pnl['total_cogs'], 2) ?>)</span>
                </div>

                <div style="display:flex;justify-content:space-between;padding:0.9rem 0;border-bottom:2px solid #0f172a;font-size:1.15rem;font-weight:700;background:#ffffff;margin:0.5rem -1.5rem;padding-left:1.5rem;padding-right:1.5rem">
                    <span>GROSS PROFIT</span>
                    <span style="color:#059669">₦<?= number_format($pnl['gross_profit'], 2) ?> (<?= $pnl['gross_margin_percent'] ?>%)</span>
                </div>

                <div style="display:flex;justify-content:space-between;padding:0.8rem 0;border-bottom:1px solid #cbd5e1;font-size:1rem;color:#ef4444">
                    <span>Less: Operating &amp; Administrative Expenses</span>
                    <span>(₦<?= number_format($pnl['operating_expenses'], 2) ?>)</span>
                </div>

                <div style="display:flex;justify-content:space-between;padding:1.1rem 0;border-bottom:3px double #059669;font-size:1.3rem;font-weight:800;color:#059669">
                    <span>NET OPERATING PROFIT</span>
                    <span>₦<?= number_format($pnl['net_profit'], 2) ?> (<?= $pnl['net_margin_percent'] ?>%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         TAB 7: BALANCE SHEET & TRIAL BALANCE
         ========================================== -->
    <div id="tab-bs-finance" class="tab-content">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
            <!-- Balance Sheet -->
            <div class="fin-card">
                <div class="fin-card-head">
                    <h3>🏛️ Balance Sheet ($\text{Assets} = \text{Liabilities} + \text{Equity}$)</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:0.75rem;font-size:0.92rem">
                    <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #e2e8f0">
                        <span>Current Assets (Cash + AR + Stock)</span>
                        <strong>₦<?= number_format($balanceSheet['current_assets'], 2) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #e2e8f0">
                        <span>Non-Current Property Assets</span>
                        <strong>₦<?= number_format($balanceSheet['non_current_assets'], 2) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:2px solid #0f172a;font-weight:700;color:#059669;font-size:1.05rem">
                        <span>TOTAL ASSETS</span>
                        <span>₦<?= number_format($balanceSheet['total_assets'], 2) ?></span>
                    </div>

                    <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #e2e8f0;margin-top:1rem">
                        <span>Current Liabilities (AP + Deposits)</span>
                        <strong>₦<?= number_format($balanceSheet['current_liabilities'], 2) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #e2e8f0">
                        <span>Long-Term Loan Liabilities</span>
                        <strong>₦<?= number_format($balanceSheet['long_term_liabilities'], 2) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #e2e8f0">
                        <span>Shareholders Equity &amp; Retained Earnings</span>
                        <strong>₦<?= number_format($balanceSheet['equity'], 2) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:2px solid #0f172a;font-weight:700;color:#2563eb;font-size:1.05rem">
                        <span>TOTAL LIABILITIES &amp; EQUITY</span>
                        <span>₦<?= number_format($balanceSheet['total_liabilities_equity'], 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Trial Balance -->
            <div class="fin-card">
                <div class="fin-card-head">
                    <h3>⚖️ Trial Balance ($\sum \text{Debits} = \sum \text{Credits}$)</h3>
                </div>
                <div class="fin-table-wrap">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Account Name</th>
                                <th>Debit (₦)</th>
                                <th>Credit (₦)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trialBalance['lines'] as $tb): ?>
                                <tr>
                                    <td><code><?= e($tb['code']) ?></code></td>
                                    <td><?= e($tb['name']) ?></td>
                                    <td style="color:#059669"><?= $tb['debit'] > 0 ? number_format($tb['debit'], 2) : '-' ?></td>
                                    <td style="color:#2563eb"><?= $tb['credit'] > 0 ? number_format($tb['credit'], 2) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:800;background:#f8fafc">
                                <td colspan="2">TOTAL BALANCES</td>
                                <td style="color:#059669">₦<?= number_format($trialBalance['total_debit'], 2) ?></td>
                                <td style="color:#2563eb">₦<?= number_format($trialBalance['total_credit'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         TAB 8: BANK RECONCILIATION & FEEDS
         ========================================== -->
    <div id="tab-bank-finance" class="tab-content">
        <div class="fin-card">
            <div class="fin-card-head">
                <h3>🏦 Bank Feeds &amp; Auto Credit Alert Reconciler</h3>
            </div>
            <div class="fin-table-wrap">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Bank Account</th>
                            <th>Statement Reference</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Matched POS / ERP Invoice</th>
                            <th>Confidence</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bankFeeds as $b): ?>
                            <tr>
                                <td><strong><?= e($b['bank_name']) ?></strong><br><small><?= e($b['account_number']) ?></small></td>
                                <td><code><?= e($b['ref']) ?></code></td>
                                <td><?= e($b['date']) ?></td>
                                <td><span style="font-weight:700;color:<?= $b['type'] === 'Credit' ? '#059669' : '#ef4444' ?>"><?= e($b['type']) ?></span></td>
                                <td><strong>₦<?= number_format($b['amount'], 2) ?></strong></td>
                                <td><?= e($b['matched_pos']) ?></td>
                                <td><span style="color:#059669;font-weight:600"><?= e($b['confidence']) ?></span></td>
                                <td>
                                    <span style="padding:0.25rem 0.6rem;border-radius:99px;font-size:0.75rem;font-weight:600;background:<?= $b['status'] === 'Matched' ? '#d1fae5' : '#ffedd5' ?>;color:#0f172a">
                                        <?= e($b['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==========================================
         TAB 9: AI FINANCIAL ANALYST ASSISTANT
         ========================================== -->
    <div id="tab-ai-finance" class="tab-content">
        <div class="fin-card">
            <div class="fin-card-head">
                <h3>🤖 AI Financial Analyst &amp; Ratio Modeler</h3>
                <span style="color:#059669;font-size:0.85rem;font-weight:700">Division: <?= strtoupper($selectedCompany) ?></span>
            </div>

            <div style="background:#f8fafc;padding:1rem 1.25rem;border-radius:10px;border:1px solid #cbd5e1;margin-bottom:1.5rem">
                <span style="font-size:0.8rem;color:#64748b;font-weight:700">PROMPT SUGGESTIONS:</span>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.4rem">
                    <button class="fin-btn fin-btn-ghost" onclick="setFinAiPrompt('Analyze company liquid current ratios and solvency health')">💡 Solvency &amp; Ratios</button>
                    <button class="fin-btn fin-btn-ghost" onclick="setFinAiPrompt('Review statutory deductions and payable schedule')">💡 Statutory Schedule</button>
                    <button class="fin-btn fin-btn-ghost" onclick="setFinAiPrompt('Forecast next month operational cash flow surplus')">💡 Cash Flow Forecast</button>
                </div>
            </div>

            <form method="POST" action="finance.php?tab=tab-ai-finance" style="margin-bottom:1.5rem">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="form_action" value="ai_ask_finance">
                <div style="display:flex;gap:0.75rem">
                    <input type="text" id="ai_fin_prompt" name="ai_prompt" placeholder="Ask AI Financial Analyst anything about cash flow, ratios, payables, or AR aging..." style="flex:1;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:0.75rem 1rem;color:#0f172a;font-size:0.9rem" required>
                    <button type="submit" class="fin-btn">✨ Analyze Financials</button>
                </div>
            </form>

            <?php if ($aiResponse): ?>
                <div style="background:#f8fafc;padding:1.25rem;border-radius:10px;border:1px solid #10b981;box-shadow:0 4px 15px rgba(16,185,129,0.1)">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem">
                        <strong style="color:#059669;font-size:1rem">🤖 AI Analysis Result: <?= e($aiResponse['topic']) ?></strong>
                        <span style="background:#d1fae5;color:#047857;padding:0.25rem 0.6rem;border-radius:99px;font-size:0.75rem;font-weight:600"><?= e($aiResponse['confidence']) ?></span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:0.75rem;margin-bottom:1rem">
                        <?php foreach ($aiResponse['ratios'] as $rk => $rv): ?>
                            <div style="background:#ffffff;padding:0.75rem;border-radius:8px;border:1px solid #e2e8f0">
                                <span style="font-size:0.75rem;color:#64748b;font-weight:600;display:block"><?= e($rk) ?></span>
                                <strong style="font-size:1rem;color:#0f172a"><?= e($rv) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="line-height:1.6;color:#1e293b;font-size:0.92rem;margin-bottom:1rem">
                        <?= nl2br(e($aiResponse['answer'])) ?>
                    </div>

                    <?php
                        $actionText = strtolower((string)($aiResponse['suggested_action'] ?? ''));
                        $actionTab = str_contains($actionText, 'payable') || str_contains($actionText, 'supplier')
                            ? 'tab-ap-finance'
                            : (str_contains($actionText, 'receivable') || str_contains($actionText, 'customer')
                                ? 'tab-ar-finance'
                                : (str_contains($actionText, 'journal') || str_contains($actionText, 'jv')
                                    ? 'tab-jv-finance'
                                    : 'tab-pnl-finance'));
                    ?>
                    <div style="padding-top:0.75rem;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:0.8rem;color:#64748b">Recommendation: <strong><?= e($aiResponse['suggested_action']) ?></strong></span>
                        <button type="button" class="fin-btn" onclick="switchFinTab('<?= e($actionTab) ?>')">Open Workflow</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchFinTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    const target = document.getElementById(tabId);
    if (target) {
        target.classList.add('active');
    }
}

function toggleAddAccountForm() {
    const el = document.getElementById('addAccountFormCard');
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function setFinAiPrompt(text) {
    document.getElementById('ai_fin_prompt').value = text;
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') || window.location.hash.replace('#', '');
    if (tabParam && document.getElementById(tabParam)) {
        switchFinTab(tabParam);
    }
});
</script>

<?php
render_footer(false, false);
