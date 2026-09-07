<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Core/AppSettingsService.php';
require_once dirname(__DIR__) . '/app/Modules/HrPayroll/HrPayrollService.php';
require_once dirname(__DIR__) . '/app/Modules/HrPayroll/GeoAttendanceService.php';
require_once dirname(__DIR__) . '/app/Modules/Notifications/NotificationService.php';

use App\Modules\HrPayroll\GeoAttendanceService;
use App\Modules\HrPayroll\HrPayrollService;
use App\Modules\Notifications\NotificationService;
use App\Core\AppSettingsService;

$user = require_authenticated_user();
$employee = HrPayrollService::employeeForUser($user);
$hasEmployeeProfile = $employee !== null;

if (!$employee) {
    $employee = [
        'employee_code' => strtoupper(str_replace(['@', '.', '+'], '-', (string)($user['email'] ?? 'STAFF'))),
        'name' => (string)($user['name'] ?? 'Staff Member'),
        'email' => (string)($user['email'] ?? ''),
        'department' => 'Unassigned',
        'designation' => 'Staff',
        'branch' => 'Unassigned',
        'status' => 'Active',
        'employee_role' => (string)($user['role'] ?? 'Staff'),
        'kyc_status' => 'Not Submitted',
        'onboarding_progress' => ['profile' => 50, 'documents' => 0, 'kyc' => 'Not Submitted', 'payroll' => 50, 'role_assigned' => false],
    ];
}

$employeeCode = (string)$employee['employee_code'];
$attendance = array_slice(HrPayrollService::reportRows('attendance'), 0, 250);
$myAttendance = array_values(array_filter($attendance, static fn(array $row): bool => strtoupper((string)($row['employee_code'] ?? '')) === strtoupper($employeeCode)));
$payrollRows = array_values(array_filter(HrPayrollService::reportRows('payroll'), static fn(array $row): bool => strtoupper((string)($row['employee_code'] ?? '')) === strtoupper($employeeCode)));
$documents = HrPayrollService::getEmployeeDocuments($employeeCode);
$leaveRecords = HrPayrollService::getLeaveRecords($employeeCode);
$damageRows = array_values(array_filter(HrPayrollService::getDamageReports(), static fn(array $row): bool => strtoupper((string)($row['employee_code'] ?? '')) === strtoupper($employeeCode)));
$cashierSessions = array_values(array_filter(HrPayrollService::getCashierSessions(), static fn(array $row): bool => strtoupper((string)($row['employee_code'] ?? '')) === strtoupper($employeeCode)));
$notifications = array_slice(NotificationService::getNotifications('beverage'), 0, 6);
$progress = is_array($employee['onboarding_progress'] ?? null) ? $employee['onboarding_progress'] : [];
$latestPayroll = $payrollRows[0] ?? null;
$staffWhatsAppGroupUrl = AppSettingsService::get('STAFF_WHATSAPP_GROUP_URL', '') ?? '';
$canJoinStaffChat = $hasEmployeeProfile && $staffWhatsAppGroupUrl !== '' && in_array((string)($employee['status'] ?? ''), ['Active', 'Onboarding', 'On Leave'], true);

$money = static fn(float $value): string => '₦' . number_format($value, 2);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Self-Service</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #dbe4f0;
            --brand: #8a0f35;
            --brand-dark: #4b071f;
            --blue: #2563eb;
            --green: #059669;
            --soft: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f4f7fb;
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }
        a { color: inherit; }
        .shell {
            width: min(1180px, calc(100vw - 28px));
            margin: 24px auto 42px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: #ffd166;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            font-weight: 900;
            box-shadow: 0 12px 30px rgba(138, 15, 53, .24);
        }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 24px; letter-spacing: 0; }
        .muted { color: var(--muted); }
        .avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #fff;
            background: #0b2246;
            font-weight: 900;
        }
        .hero {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 12px 34px rgba(15, 23, 42, .06);
        }
        .profile {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .profile-photo {
            width: 86px;
            height: 86px;
            border-radius: 22px;
            background: linear-gradient(135deg, #eff6ff, #fff1f2);
            display: grid;
            place-items: center;
            color: var(--brand);
            font-size: 28px;
            font-weight: 900;
            border: 1px solid var(--line);
            flex: 0 0 auto;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
        }
        .pill.good { background: #dcfce7; color: #047857; }
        .pill.warn { background: #fff7ed; color: #c2410c; }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
        }
        .stat small {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .stat strong {
            display: block;
            margin-top: 5px;
            font-size: 21px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .head h2 { font-size: 16px; }
        .list { display: grid; gap: 9px; }
        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 0;
            border-top: 1px solid #edf2f7;
            font-size: 14px;
        }
        .row:first-child { border-top: 0; }
        .row small {
            display: block;
            margin-top: 2px;
            color: var(--muted);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 13px;
            border-radius: 11px;
            color: #fff;
            background: var(--brand);
            font-weight: 900;
            font-size: 13px;
            text-decoration: none;
            border: 0;
        }
        .progress {
            display: grid;
            gap: 8px;
        }
        .progress-line {
            display: grid;
            grid-template-columns: 92px 1fr auto;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #334155;
        }
        .track {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .track div {
            height: 100%;
            border-radius: inherit;
            background: var(--blue);
        }
        @media (max-width: 760px) {
            .shell { width: min(100vw - 20px, 540px); margin-top: 14px; }
            .topbar, .profile { align-items: flex-start; }
            .hero, .grid, .stats { grid-template-columns: 1fr; }
            .profile-photo { width: 72px; height: 72px; border-radius: 18px; }
            h1 { font-size: 21px; }
            .panel, .stat { padding: 14px; border-radius: 14px; }
            .row { align-items: flex-start; }
            .progress-line { grid-template-columns: 82px 1fr 42px; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="logo">ET</div>
                <div>
                    <h1>Employee Self-Service</h1>
                    <p class="muted">Profile, attendance, payroll and HR records</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <a class="btn" href="<?= e(url('logout.php')) ?>" style="background:#fff;color:var(--brand);border:1px solid var(--line)">Logout</a>
                <div class="avatar"><?= e(strtoupper(substr((string)$employee['name'], 0, 1) . substr((string)($employee['name'] ?? ' '), 1, 1))) ?></div>
            </div>
        </header>

        <section class="hero">
            <div class="panel profile">
                <div class="profile-photo"><?= e(strtoupper(substr((string)$employee['name'], 0, 2))) ?></div>
                <div>
                    <h2><?= e((string)$employee['name']) ?></h2>
                    <p class="muted"><?= e($employeeCode) ?> · <?= e((string)$employee['designation']) ?></p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                        <span class="pill good"><?= e((string)$employee['status']) ?></span>
                        <span class="pill"><?= e((string)$employee['department']) ?></span>
                        <span class="pill"><?= e((string)$employee['branch']) ?></span>
                    </div>
                    <?php if ($canJoinStaffChat): ?>
                        <a class="btn" href="<?= e($staffWhatsAppGroupUrl) ?>" target="_blank" rel="noopener noreferrer" style="margin-top:12px;background:#047857">Open Staff WhatsApp</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel">
                <div class="head"><h2>Onboarding Progress</h2><span class="pill <?= (string)($employee['kyc_status'] ?? '') === 'Verified' ? 'good' : 'warn' ?>"><?= e((string)($employee['kyc_status'] ?? 'Not Submitted')) ?></span></div>
                <div class="progress">
                    <?php foreach (['profile' => 'Profile', 'documents' => 'Documents', 'payroll' => 'Payroll'] as $key => $label): ?>
                        <?php $pct = max(0, min(100, (int)($progress[$key] ?? 0))); ?>
                        <div class="progress-line"><span><?= e($label) ?></span><div class="track"><div style="width:<?= $pct ?>%"></div></div><strong><?= $pct ?>%</strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="stats">
            <div class="stat"><small>Attendance Records</small><strong><?= number_format(count($myAttendance)) ?></strong><span class="muted">Recent history</span></div>
            <div class="stat"><small>Latest Net Pay</small><strong><?= $latestPayroll ? e($money((float)$latestPayroll['net_pay'])) : '₦0.00' ?></strong><span class="muted"><?= e((string)($latestPayroll['month'] ?? 'No payroll')) ?></span></div>
            <div class="stat"><small>Documents</small><strong><?= number_format(count($documents)) ?></strong><span class="muted">HR files on record</span></div>
            <div class="stat"><small>Deductions</small><strong><?= e($money(array_sum(array_map(static fn(array $row): float => (float)($row['deduction_amount'] ?? 0), $damageRows)))) ?></strong><span class="muted">Approved liability</span></div>
        </section>

        <section class="grid">
            <div class="panel">
                <div class="head"><h2>Payroll &amp; Payslips</h2><span class="pill"><?= number_format(count($payrollRows)) ?></span></div>
                <div class="list">
                    <?php foreach (array_slice($payrollRows, 0, 6) as $row): ?>
                        <div class="row">
                            <div><strong><?= e((string)$row['month']) ?></strong><small>Gross <?= e($money((float)$row['gross_pay'])) ?> · Deductions <?= e($money((float)$row['total_deductions'])) ?></small></div>
                            <div style="text-align:right"><strong><?= e($money((float)$row['net_pay'])) ?></strong><small><?= e((string)$row['payment_status']) ?></small><a class="btn" href="<?= e(url('payslip.php?period=' . rawurlencode((string)$row['period_id']) . '&employee=' . rawurlencode($employeeCode))) ?>" target="_blank" style="margin-top:6px">Payslip</a></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($payrollRows)): ?><p class="muted">No payroll record is available yet.</p><?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="head"><h2>Attendance</h2><span class="pill"><?= date('M Y') ?></span></div>
                <div class="list">
                    <?php foreach (array_slice($myAttendance, 0, 8) as $row): ?>
                        <div class="row"><div><strong><?= e((string)$row['date']) ?></strong><small><?= e((string)$row['branch']) ?> · <?= e((string)$row['method']) ?></small></div><div style="text-align:right"><strong><?= e((string)$row['status']) ?></strong><small><?= e((string)$row['clock_in']) ?> - <?= e((string)($row['clock_out'] ?: '-')) ?></small></div></div>
                    <?php endforeach; ?>
                    <?php if (empty($myAttendance)): ?><p class="muted">No attendance record is available yet.</p><?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="head"><h2>Leave, Duty &amp; Documents</h2><span class="pill"><?= count($leaveRecords) + count($documents) ?></span></div>
                <div class="list">
                    <?php foreach (array_slice($leaveRecords, 0, 4) as $leave): ?>
                        <div class="row"><div><strong><?= e((string)$leave['leave_type']) ?></strong><small><?= e((string)$leave['start_date']) ?> to <?= e((string)$leave['end_date']) ?></small></div><span class="pill"><?= e((string)$leave['status']) ?></span></div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($documents, 0, 4) as $doc): ?>
                        <div class="row"><div><strong><?= e((string)$doc['document_type']) ?></strong><small><?= e((string)$doc['uploaded_at']) ?></small></div><span class="pill"><?= e((string)$doc['status']) ?></span></div>
                    <?php endforeach; ?>
                    <?php if (empty($leaveRecords) && empty($documents)): ?><p class="muted">No leave or document record is available yet.</p><?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="head"><h2>Accountability &amp; Notices</h2><span class="pill"><?= count($damageRows) + count($cashierSessions) ?></span></div>
                <div class="list">
                    <?php foreach (array_slice($damageRows, 0, 3) as $damage): ?>
                        <div class="row"><div><strong><?= e((string)$damage['incident_number']) ?></strong><small><?= e((string)$damage['asset']) ?> · <?= e($money((float)$damage['approved_liability'])) ?></small></div><span class="pill warn"><?= e((string)$damage['investigation_status']) ?></span></div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($cashierSessions, 0, 3) as $drawer): ?>
                        <div class="row"><div><strong><?= e((string)$drawer['drawer_id']) ?></strong><small><?= e((string)$drawer['terminal_name']) ?> · variance <?= e($money((float)$drawer['variance'])) ?></small></div><span class="pill"><?= e((string)$drawer['status']) ?></span></div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($notifications, 0, 3) as $notice): ?>
                        <div class="row"><div><strong><?= e((string)$notice['title']) ?></strong><small><?= e((string)$notice['message']) ?></small></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
