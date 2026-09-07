<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Modules/HrPayroll/HrPayrollService.php';

use App\Modules\HrPayroll\HrPayrollService;

$user = require_authenticated_user();
$periodId = trim((string)($_GET['period'] ?? ''));
$employeeCode = trim((string)($_GET['employee'] ?? ''));
$payslip = HrPayrollService::getPayslip($periodId, $employeeCode);

if (!$payslip) {
    http_response_code(404);
    exit('Payslip not found.');
}

$role = canonical_role((string)($user['role'] ?? ''));
$allowedEmployee = strtoupper((string)(HrPayrollService::employeeForUser($user)['employee_code'] ?? ''));
$canViewPayroll = HrPayrollService::userHasPermission($user, 'payroll.view');
if (!$canViewPayroll && $allowedEmployee !== strtoupper($employeeCode)) {
    http_response_code(403);
    exit('You can only view your own payslip.');
}

$record = $payslip['record'];
$period = $payslip['period'];
$employee = $payslip['employee'];
$money = static fn(float $value): string => '₦' . number_format($value, 2);
$monthLabel = date('F Y', strtotime((string)$period['month'] . '-01')) ?: (string)$period['month'];
$issuedAt = (string)($period['updated_at'] ?? $period['created_at'] ?? date('Y-m-d H:i:s'));
$status = (string)($record['payment_status'] ?? $period['status'] ?? 'Calculated');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip <?= e((string)($record['payslip_code'] ?? '')) ?></title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #dbe4f0;
            --brand: #8a0f35;
            --brand-dark: #4b071f;
            --soft: #f8fafc;
            --good: #07884f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef3f8;
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }
        .page {
            width: min(920px, calc(100vw - 28px));
            margin: 28px auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .top {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 30px;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .brand-mark {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 900;
            color: #ffd166;
        }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 24px; letter-spacing: 0; }
        .sub { color: rgba(255, 255, 255, 0.78); font-size: 13px; }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 13px;
            border-radius: 999px;
            color: #fff;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.22);
            font-size: 13px;
            font-weight: 800;
        }
        .content { padding: 28px 30px 32px; }
        .summary {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 18px;
            margin-bottom: 22px;
        }
        .box {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
            background: #fff;
        }
        .box h2 {
            font-size: 14px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 12px;
        }
        .identity {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 18px;
        }
        .item span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 800;
        }
        .item strong {
            display: block;
            margin-top: 3px;
            font-size: 15px;
        }
        .net {
            display: grid;
            gap: 8px;
            align-content: center;
            min-height: 100%;
            background: linear-gradient(180deg, #f8fafc, #fff);
        }
        .net strong {
            font-size: 34px;
            color: var(--good);
        }
        .tables {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th {
            color: var(--muted);
            text-align: left;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: .04em;
            padding: 0 0 8px;
        }
        td {
            border-top: 1px solid var(--line);
            padding: 11px 0;
        }
        td:last-child, th:last-child { text-align: right; }
        .total-row td {
            font-weight: 900;
            color: var(--ink);
        }
        .footer {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 13px;
        }
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin: 18px auto 0;
            width: min(920px, calc(100vw - 28px));
        }
        .btn {
            border: 0;
            border-radius: 12px;
            padding: 12px 16px;
            color: #fff;
            background: var(--brand);
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        .btn.secondary {
            color: var(--brand);
            background: #fff;
            border: 1px solid var(--line);
        }
        @media (max-width: 720px) {
            .top, .summary, .tables, .identity, .footer {
                grid-template-columns: 1fr;
            }
            .top, .footer {
                flex-direction: column;
            }
            .content, .top { padding: 22px; }
            .net strong { font-size: 28px; }
        }
        @media print {
            body { background: #fff; }
            .page {
                width: 100%;
                margin: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a class="btn secondary" href="<?= e(url('dashboard.php?tab=tab-hr-payroll')) ?>">Back to Payroll</a>
        <button class="btn" type="button" onclick="window.print()">Print Payslip</button>
    </div>
    <main class="page">
        <section class="top">
            <div class="brand">
                <div class="brand-mark">ET</div>
                <div>
                    <h1><?= e((string)$payslip['company_name']) ?></h1>
                    <p class="sub">Payroll Payslip · <?= e($monthLabel) ?></p>
                </div>
            </div>
            <div>
                <span class="badge"><?= e($status) ?></span>
                <p class="sub" style="margin-top:8px;text-align:right"><?= e((string)($record['payslip_code'] ?? '')) ?></p>
            </div>
        </section>

        <section class="content">
            <div class="summary">
                <div class="box">
                    <h2>Employee Details</h2>
                    <div class="identity">
                        <div class="item"><span>Name</span><strong><?= e((string)($record['name'] ?? '')) ?></strong></div>
                        <div class="item"><span>Employee Code</span><strong><?= e((string)($record['employee_code'] ?? '')) ?></strong></div>
                        <div class="item"><span>Designation</span><strong><?= e((string)($record['designation'] ?? '')) ?></strong></div>
                        <div class="item"><span>Department</span><strong><?= e((string)($record['department'] ?? '')) ?></strong></div>
                        <div class="item"><span>Branch</span><strong><?= e((string)($record['branch'] ?? '')) ?></strong></div>
                        <div class="item"><span>Bank</span><strong><?= e((string)($employee['bank_name'] ?? 'On file')) ?></strong></div>
                    </div>
                </div>
                <div class="box net">
                    <div class="item"><span>Net Pay</span><strong><?= e($money((float)($record['net_pay'] ?? 0))) ?></strong></div>
                    <div class="item"><span>Issued</span><strong><?= e($issuedAt) ?></strong></div>
                </div>
            </div>

            <div class="tables">
                <div class="box">
                    <h2>Earnings</h2>
                    <table>
                        <thead><tr><th>Description</th><th>Amount</th></tr></thead>
                        <tbody>
                            <tr><td>Basic Salary</td><td><?= e($money((float)($record['base_salary'] ?? 0))) ?></td></tr>
                            <tr><td>Allowances</td><td><?= e($money((float)($record['allowances'] ?? 0))) ?></td></tr>
                            <tr><td>Bonus</td><td><?= e($money((float)($record['bonus'] ?? 0))) ?></td></tr>
                            <tr><td>Commission</td><td><?= e($money((float)($record['commission'] ?? 0))) ?></td></tr>
                            <tr class="total-row"><td>Gross Pay</td><td><?= e($money((float)($record['gross_pay'] ?? 0))) ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="box">
                    <h2>Deductions</h2>
                    <table>
                        <thead><tr><th>Description</th><th>Amount</th></tr></thead>
                        <tbody>
                            <tr><td>PAYE</td><td><?= e($money((float)($record['tax_paye'] ?? 0))) ?></td></tr>
                            <tr><td>Pension</td><td><?= e($money((float)($record['pension'] ?? 0))) ?></td></tr>
                            <tr><td>Attendance</td><td><?= e($money((float)($record['attendance_deductions'] ?? 0))) ?></td></tr>
                            <tr><td>Damage / Liability</td><td><?= e($money((float)($record['damage_deductions'] ?? 0))) ?></td></tr>
                            <tr><td>Other Deductions</td><td><?= e($money((float)($record['other_deductions'] ?? 0))) ?></td></tr>
                            <tr class="total-row"><td>Total Deductions</td><td><?= e($money((float)($record['total_deductions'] ?? 0))) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="footer">
                <span>This payslip was generated from the permanent payroll snapshot for <?= e($monthLabel) ?>.</span>
                <span>Authorized by <?= e((string)($period['updated_by'] ?? $period['created_by'] ?? 'HR Admin')) ?></span>
            </div>
        </section>
    </main>
</body>
</html>
