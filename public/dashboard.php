<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/AppSettingsService.php';
require_once dirname(__DIR__) . '/app/Modules/HrPayroll/HrPayrollService.php';
require_once dirname(__DIR__) . '/app/Modules/HrPayroll/GeoAttendanceService.php';
require_once dirname(__DIR__) . '/app/Modules/Logistics/LogisticsService.php';
require_once dirname(__DIR__) . '/app/Modules/Maintenance/MaintenanceService.php';
require_once dirname(__DIR__) . '/app/Modules/Procurement/ProcurementService.php';
require_once dirname(__DIR__) . '/app/Modules/PaymentRecon/PaymentReconService.php';
require_once dirname(__DIR__) . '/app/Modules/Notifications/NotificationService.php';
require_once dirname(__DIR__) . '/app/Modules/Notifications/MailDeliveryService.php';
require_once dirname(__DIR__) . '/app/Modules/AiCore/CentralAiService.php';
require_once dirname(__DIR__) . '/app/Modules/AiCore/AiSupervisorService.php';
require_once dirname(__DIR__) . '/app/Modules/AiCore/SystemHealthService.php';

use App\Modules\HrPayroll\HrPayrollService;
use App\Modules\HrPayroll\GeoAttendanceService;
use App\Modules\Logistics\LogisticsService;
use App\Modules\Maintenance\MaintenanceService;
use App\Modules\Procurement\ProcurementService;
use App\Modules\PaymentRecon\PaymentReconService;
use App\Modules\Notifications\NotificationService;
use App\Modules\Notifications\MailDeliveryService;
use App\Modules\AiCore\CentralAiService;
use App\Modules\AiCore\AiSupervisorService;
use App\Modules\AiCore\SystemHealthService;
use App\Core\AppSettingsService;

$requestedTab = $_GET['tab'] ?? '';
$allowedTabs = ['', 'tab-hr-payroll', 'tab-geo-attendance', 'tab-logistics', 'tab-maintenance', 'tab-procurement', 'tab-payment-recon', 'tab-notifications', 'tab-central-ai', 'tab-health-score', 'tab-system-settings'];
if (!in_array($requestedTab, $allowedTabs, true)) {
    flash('Only Beverage Depot operations are available in this project.');
    redirect('beverage_warehouse.php');
}

$user = current_user();
if ($user && canonical_role($user['role'] ?? '') === 'pos') {
    flash('⚠️ Access Restricted: POS & Sales staff are strictly limited to Point-of-Sale & Sales functions.');
    redirect('beverage_pos.php?tab=pos');
}
if ($user) {
    $userCompany = $user['company_id'] ?? 'beverage';
    $userEmail = $user['email'] ?? '';
    $tab = $requestedTab;

    if ($userEmail === 'beverage_admin@360management.com' || $userCompany === 'beverage') {
        if (empty($tab)) {
            redirect('beverage_warehouse.php');
        }
    }
}
if (!$user) {
    redirect('login.php');
}

$requestedAction = $_GET['action'] ?? '';
if ($requestedAction === 'export_hr_report') {
    if (!HrPayrollService::userHasPermission($user, 'reports.employee')) {
        HrPayrollService::recordAccessDenied('export_hr_report', $user);
        http_response_code(403);
        exit('Access denied: you do not have permission to export HR reports.');
    }
    HrPayrollService::exportReportCsv((string)($_GET['report'] ?? 'employees'));
}

$activeTab = $_GET['tab'] ?? '';
$actionMessage = null;
$actionData = null;
$companyContext = 'beverage';

// Handle form submissions across active ERP modules.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';
    $hrActionViews = [
        'add_employee' => 'onboarding',
        'save_employee_kyc' => 'kyc',
        'upload_employee_document' => 'documents',
        'create_damage_report' => 'damage',
        'update_damage_status' => 'damage',
        'create_leave_record' => 'leave',
        'create_performance_review' => 'performance',
        'create_disciplinary_record' => 'discipline',
        'save_hr_settings' => 'settings',
        'save_employee_role' => 'settings',
        'save_work_calendar_day' => 'calendar',
        'save_staff_chat_settings' => 'staff-chat',
        'toggle_staff_access' => 'employees',
        'process_payroll' => 'payroll',
        'update_payroll_status' => 'payroll',
    ];
    if (isset($hrActionViews[(string)$formAction])) {
        $_GET['view'] = $hrActionViews[(string)$formAction];
    }
    $requiredHrPermission = HrPayrollService::permissionForDashboardAction($formAction, $_POST);

    if ($requiredHrPermission !== null && !HrPayrollService::userHasPermission($user, $requiredHrPermission)) {
        HrPayrollService::recordAccessDenied((string)$formAction, $user);
        $actionMessage = 'Access denied: your role is not allowed to perform this HR/payroll action.';
        $activeTab = in_array($formAction, ['geo_clock_in', 'geo_clock_out', 'mark_hr_duty'], true) ? 'tab-geo-attendance' : 'tab-hr-payroll';
    } elseif ($formAction === 'geo_clock_in') {
        $actionData = GeoAttendanceService::clockIn($_POST, $user['company_id'] ?? 'beverage');
        $actionMessage = "📍 GPS Clock-In Recorded! Employee: {$actionData['employee_name']}. Distance: {$actionData['distance_meters']}m from branch. Status: {$actionData['status']}.";
        $activeTab = 'tab-geo-attendance';
    } elseif ($formAction === 'geo_clock_out') {
        $actionData = GeoAttendanceService::clockOut($_POST, $user['company_id'] ?? 'beverage');
        $actionMessage = "Clock-out recorded for {$actionData['employee_name']}. Duration: " . number_format((int)($actionData['duration_minutes'] ?? 0)) . " minutes.";
        $activeTab = 'tab-geo-attendance';
    } elseif ($formAction === 'mark_hr_duty') {
        $actionData = GeoAttendanceService::markDuty($_POST);
        $actionMessage = "Attendance exception saved for {$actionData['employee_name']}: {$actionData['status']}.";
        $activeTab = 'tab-geo-attendance';
    } elseif ($formAction === 'add_employee') {
        $actionData = HrPayrollService::addEmployee($_POST);
        $actionMessage = "👤 New Staff Added! {$actionData['name']} ({$actionData['employee_code']}) — Role-Based Dashboard Assigned: {$actionData['dashboard_label']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'save_employee_kyc') {
        $actionData = HrPayrollService::saveKyc($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = "KYC updated for {$actionData['employee_code']}: {$actionData['status']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'upload_employee_document') {
        $actionData = HrPayrollService::saveEmployeeDocument($_POST, $_FILES, $user['name'] ?? 'HR Admin');
        $actionMessage = "Employee document captured for {$actionData['employee_code']}: {$actionData['document_type']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'create_damage_report') {
        $actionData = HrPayrollService::createDamageReport($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = "Damage/accountability report created: {$actionData['incident_number']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'update_damage_status') {
        $actionData = HrPayrollService::updateDamageStatus((string)($_POST['incident_number'] ?? ''), (string)($_POST['status'] ?? ''), $_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = (string)($actionData['message'] ?? 'Damage status updated.');
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'create_leave_record') {
        $actionData = HrPayrollService::createLeaveRecord($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = "Leave/official duty record created for {$actionData['employee_code']}: {$actionData['leave_type']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'create_performance_review') {
        $actionData = HrPayrollService::createPerformanceReview($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = "Performance review saved for {$actionData['employee_code']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'create_disciplinary_record') {
        $actionData = HrPayrollService::createDisciplinaryRecord($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = "Disciplinary record saved for {$actionData['employee_code']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'save_hr_settings') {
        $actionData = HrPayrollService::saveSettings($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = 'HR workforce settings saved.';
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'save_employee_role') {
        $actionData = HrPayrollService::saveRole($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = "Employee role saved: {$actionData['role_name']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'save_work_calendar_day') {
        $actionData = HrPayrollService::saveWorkCalendarDay($_POST, $user['name'] ?? 'HR Admin');
        $actionMessage = "Work calendar saved for {$actionData['calendar_date']}: {$actionData['day_type']}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'save_staff_chat_settings') {
        $role = canonical_role($user['role'] ?? '');
        if (!in_array($role, ['super_admin', 'admin'], true)) {
            $actionMessage = 'Access denied: only Super Admin or Admin can change the staff chat link.';
        } else {
            $settingsPayload = AppSettingsService::all();
            $settingsPayload['STAFF_WHATSAPP_GROUP_URL'] = (string)($_POST['STAFF_WHATSAPP_GROUP_URL'] ?? '');
            $actionData = AppSettingsService::save($settingsPayload, $user['name'] ?? 'Admin');
            $actionMessage = (string)($actionData['message'] ?? 'Staff chat settings saved.');
        }
        $activeTab = 'tab-hr-payroll';
        $_GET['view'] = 'staff-chat';
    } elseif ($formAction === 'toggle_staff_access') {
        $empId = (int)($_POST['employee_id'] ?? 0);
        $actionData = HrPayrollService::toggleStaffAccess($empId);
        $statusStr = !empty($actionData['has_dashboard_access']) ? 'GRANTED' : 'REVOKED';
        $actionMessage = "⚙️ Access Updated! Dashboard Permission for {$actionData['name']} has been {$statusStr}.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'process_payroll') {
        $actionData = HrPayrollService::processMonthlyPayroll($_POST['payroll_month'] ?? '2026-08');
        $actionMessage = "💰 Payroll Processed for {$_POST['payroll_month']}! Total Net Payable: ₦" . number_format($actionData['total_net_payroll'], 2) . " for {$actionData['employee_count']} active employees.";
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'update_payroll_status') {
        $actionData = HrPayrollService::updatePayrollStatus((string)($_POST['period_id'] ?? ''), (string)($_POST['payroll_status'] ?? ''), $user['name'] ?? 'HR Admin');
        $actionMessage = (string)($actionData['message'] ?? 'Payroll status updated.');
        $activeTab = 'tab-hr-payroll';
    } elseif ($formAction === 'create_dispatch') {
        $_POST['division'] = $_POST['division'] ?? $companyContext;
        $actionData = LogisticsService::createDispatch($_POST);
        $returnView = strtolower(trim((string)($_POST['return_view'] ?? 'dispatch')));
        $_GET['view'] = in_array($returnView, ['dispatch', 'returns', 'pod'], true) ? $returnView : 'dispatch';
        $actionLabel = ($_GET['view'] ?? 'dispatch') === 'returns' ? 'Return / Exception Record Created' : 'Waybill Dispatch Created';
        $actionMessage = "🚚 {$actionLabel}! Waybill #: {$actionData['waybill_number']}. Driver: {$actionData['driver_name']} -> Destination: {$actionData['destination']}.";
        $activeTab = 'tab-logistics';
    } elseif ($formAction === 'confirm_pod') {
        $waybill = $_POST['waybill_number'] ?? '';
        LogisticsService::confirmProofOfDelivery($waybill);
        $_GET['view'] = 'pod';
        $actionMessage = "📄 Proof of Delivery (POD) Confirmed for Waybill {$waybill}!";
        $activeTab = 'tab-logistics';
    } elseif ($formAction === 'create_work_order') {
        $actionData = MaintenanceService::createWorkOrder($_POST);
        $actionMessage = "🛠️ Maintenance Work Order Created! Code: {$actionData['wo_code']} for Asset: {$actionData['asset_name']}. Estimated Cost: ₦" . number_format($actionData['cost_estimate'], 2);
        $activeTab = 'tab-maintenance';
    } elseif ($formAction === 'create_procurement_po') {
        $actionData = ProcurementService::createRequisition($_POST);
        $actionMessage = "🛒 Purchase Order Created! PO #: {$actionData['po_number']} with Vendor: {$actionData['vendor_name']}. Total: ₦" . number_format($actionData['total_amount'], 2);
        $activeTab = 'tab-procurement';
    } elseif ($formAction === 'parse_bank_alert') {
        $actionData = PaymentReconService::parseSimulatedEmailAlert($_POST['raw_payload'] ?? '', [
            'bank_name' => $_POST['bank_name'] ?? null,
            'source' => 'Manual Mail Alert Paste',
        ]);
        $actionMessage = "🏦 Bank Alert Processed! Sender: {$actionData['sender_name']} | Amount: ₦" . number_format($actionData['amount'], 2) . " | Status: {$actionData['status']}";
        $activeTab = 'tab-payment-recon';
    } elseif ($formAction === 'save_mail_connector') {
        if (!HrPayrollService::userHasPermission($user, 'payment_recon.manage')) {
            HrPayrollService::recordAccessDenied((string)$formAction, $user);
            $actionMessage = 'Access denied: payment reconciliation settings require administrator permission.';
        } else {
            $actionData = PaymentReconService::saveMailConnectorConfig($_POST);
            $actionMessage = "📬 Mail Connector Saved! Bank: {$actionData['bank_name']} | Mailbox: {$actionData['mailbox_email']} | Status: {$actionData['status']}";
        }
        $activeTab = 'tab-payment-recon';
    } elseif ($formAction === 'sync_mail_alerts') {
        $actionData = PaymentReconService::syncMailboxAlerts();
        $syncStatus = $actionData['message'] ?? "Mailbox Sync Complete! {$actionData['imported']} new alert(s), {$actionData['duplicates']} duplicate(s), {$actionData['processed']} processed for {$actionData['bank_name']}.";
        $actionMessage = "Mail Alert Sync: {$syncStatus}";
        $activeTab = 'tab-payment-recon';
    } elseif ($formAction === 'ask_central_ai') {
        $prompt = trim((string)($_POST['ai_prompt'] ?? ''));
        $actionData = CentralAiService::askCentralAi($prompt, $user['company_id'] ?? 'beverage');
        $activeTab = 'tab-central-ai';
    } elseif ($formAction === 'save_system_settings') {
        $role = canonical_role($user['role'] ?? '');
        if (!in_array($role, ['super_admin', 'admin'], true)) {
            $actionMessage = 'Access denied: only Super Admin or Admin can change production settings.';
        } else {
            $actionData = AppSettingsService::save($_POST, $user['name'] ?? 'Admin');
            $actionMessage = (string)($actionData['message'] ?? 'Settings saved.');
        }
        $activeTab = 'tab-system-settings';
    }
}

// Load only the active module. Menu clicks were slow because this route used to
// hydrate every ERP module for every tab.
$employees = [];
$hrTotalEmployees = 0;
$hrOnPayroll = 0;
$hrDepartments = [];
$hrGrossPayroll = 0.0;
$hrTotalAllowances = 0.0;
$hrPaye = $hrPension = $hrNhf = $hrWht = $hrNssf = $hrStatutoryDeductions = $hrNetPayroll = 0.0;
$attendanceLogs = [];
$todayAttendanceLogs = [];
$hrPresentCount = 0;
$hrSettings = [];
$hrRoleCatalog = [];
$hrPermissionCatalog = [];
$hrDashboardMetrics = [];
$hrAbsentCount = 0;
$hrLateCount = 0;
$hrPresentPercent = 0;
$hrAbsentPercent = 0;
$hrLatePercent = 0;
$hrKycRecords = [];
$hrDamageReports = [];
$hrLeaveRecords = [];
$hrPerformanceReviews = [];
$hrDisciplinaryRecords = [];
$hrPayrollPeriods = [];
$hrAuditLogs = [];
$hrDocuments = [];
$hrKycProviderStatus = ['configured' => false, 'provider' => 'Provn', 'message' => 'Not checked'];
$hrTodayCalendar = ['label' => 'Working Day', 'type' => 'working'];
$hrCalendarRecords = [];
$hrDrivers = [];
$hrDriverAccountability = [];
$hrView = strtolower(trim((string)($_GET['view'] ?? 'dashboard')));
if (!in_array($hrView, ['dashboard', 'employees', 'departments', 'staff-chat', 'leave', 'payroll', 'onboarding', 'kyc', 'documents', 'damage', 'drivers', 'performance', 'discipline', 'reports', 'settings', 'calendar', 'audit', 'compliance'], true)) {
    $hrView = 'dashboard';
}
$hrViewCopy = [
    'dashboard' => ['title' => 'HR & Statutory Payroll Management', 'subtitle' => 'Manage workforce records, payroll, compliance, dashboard access and employee wellbeing.'],
    'employees' => ['title' => 'Employees', 'subtitle' => 'View staff records, dashboard access and employment status.'],
    'departments' => ['title' => 'Departments', 'subtitle' => 'Review employee distribution across teams and locations.'],
    'staff-chat' => ['title' => 'Staff WhatsApp Chat', 'subtitle' => 'Give employed staff one official WhatsApp room for announcements and team communication.'],
    'leave' => ['title' => 'Attendance & Leave', 'subtitle' => 'Manage leave, official duty and attendance overview.'],
    'payroll' => ['title' => 'Payroll Management', 'subtitle' => 'Run payroll, review payroll snapshots and update workflow status.'],
    'onboarding' => ['title' => 'Onboarding', 'subtitle' => 'Add new staff and generate role-based dashboard access.'],
    'kyc' => ['title' => 'KYC Verification', 'subtitle' => 'Capture employee KYC records and Provn verification status.'],
    'documents' => ['title' => 'Documents', 'subtitle' => 'Store and review protected employee documents.'],
    'damage' => ['title' => 'Damage Register', 'subtitle' => 'Track staff accountability, investigations and deductions.'],
    'drivers' => ['title' => 'Drivers', 'subtitle' => 'Monitor driver documents, deliveries and pending POD accountability.'],
    'performance' => ['title' => 'Performance', 'subtitle' => 'Save reviews, scores and employee performance notes.'],
    'discipline' => ['title' => 'Disciplinary', 'subtitle' => 'Manage warnings, queries and disciplinary workflow.'],
    'reports' => ['title' => 'HR Reports', 'subtitle' => 'Download workforce, payroll, KYC and audit CSV reports.'],
    'settings' => ['title' => 'HR Settings', 'subtitle' => 'Configure payroll rules, KYC provider and HR permissions.'],
    'calendar' => ['title' => 'Work Calendar', 'subtitle' => 'Manage working days, holidays and special closures.'],
    'audit' => ['title' => 'Audit Trail', 'subtitle' => 'Review recent HR and payroll workflow changes.'],
    'compliance' => ['title' => 'Statutory Compliance', 'subtitle' => 'Review PAYE, pension, NHF, WHT and NSSF obligations.'],
];
$logisticsView = strtolower(trim((string)($_GET['view'] ?? 'dispatch')));
if (!in_array($logisticsView, ['dispatch', 'returns', 'pod'], true)) {
    $logisticsView = 'dispatch';
}
$logisticsViewCopy = [
    'dispatch' => [
        'title' => 'Dispatch',
        'subtitle' => 'Create outgoing waybills and monitor active beverage deliveries.',
        'badge' => 'Dispatch Desk',
        'queue_title' => 'Dispatch Queue',
        'queue_empty' => 'No dispatch records yet.',
    ],
    'returns' => [
        'title' => 'Returns',
        'subtitle' => 'Track returned stock, rejected deliveries and document exceptions.',
        'badge' => 'Returns Desk',
        'queue_title' => 'Return / Exception Queue',
        'queue_empty' => 'No return or exception records yet.',
    ],
    'pod' => [
        'title' => 'Delivery POD',
        'subtitle' => 'Confirm receiving signatures, OTP evidence and completed proof of delivery.',
        'badge' => 'POD Control',
        'queue_title' => 'Pending POD Queue',
        'queue_empty' => 'No delivery records are waiting for POD confirmation.',
    ],
];
$deliveries = [];
$logisticsMetrics = ['delivery_notes' => 0, 'active_dispatches' => 0, 'pod_signed' => 0, 'value_in_transit' => 0, 'pending_pod' => 0, 'document_exceptions' => 0];
$pendingDeliveries = [];
$returnDeliveries = [];
$visibleLogisticsDeliveries = [];
$logisticsSyncEvents = [];
$assets = [];
$workOrders = [];
$vendors = [];
$logisticsSupplierOptions = [['company_name' => 'Empress Tee Beverage Depot']];
$logisticsDefaultSupplier = '';
$purchaseOrders = [];
$bankAlerts = [];
$mailConnector = ['enabled' => false, 'status' => 'Not loaded'];
$bankProfiles = [];
$notifications = [];
$mailDelivery = ['status' => 'Not loaded', 'from' => '', 'transport' => ''];
$systemSettings = [];
$staffWhatsAppGroupUrl = '';
$employedStaff = [];
$healthOverview = ['overall_score' => 0, 'grade' => 'N/A', 'last_assessed' => date('Y-m-d H:i'), 'ai_summary' => 'Open Health Score to calculate the system status.', 'categories' => []];
$aiSupervisorBrief = ['score' => 100, 'status' => 'Stable', 'summary' => 'Open AI Supervisor to load live insights.', 'watch_alerts' => [], 'training_tasks' => []];

$loadHrSummary = static function () use (&$employees, &$hrTotalEmployees, &$hrOnPayroll, &$hrDepartments, &$hrGrossPayroll, &$hrTotalAllowances, &$hrPaye, &$hrPension, &$hrNhf, &$hrWht, &$hrNssf, &$hrStatutoryDeductions, &$hrNetPayroll): void {
    $employees = HrPayrollService::getEmployees();
    $hrTotalEmployees = count($employees);
    $hrOnPayroll = count(array_filter($employees, static fn (array $emp): bool => ($emp['status'] ?? 'Active') === 'Active'));
    $hrDepartments = [];
    $hrGrossPayroll = 0.0;
    $hrTotalAllowances = 0.0;
    foreach ($employees as $emp) {
        $department = (string)($emp['department'] ?? 'General');
        $hrDepartments[$department] = ($hrDepartments[$department] ?? 0) + 1;
        $hrGrossPayroll += (float)($emp['base_salary'] ?? 0) + (float)($emp['allowances'] ?? 0);
        $hrTotalAllowances += (float)($emp['allowances'] ?? 0);
    }
    $hrPaye = round($hrGrossPayroll * 0.10, 2);
    $hrPension = round($hrGrossPayroll * 0.08, 2);
    $hrNhf = round($hrGrossPayroll * 0.025, 2);
    $hrWht = round($hrGrossPayroll * 0.015, 2);
    $hrNssf = round($hrGrossPayroll * 0.01, 2);
    $hrStatutoryDeductions = $hrPaye + $hrPension + $hrNhf + $hrWht + $hrNssf;
    $hrNetPayroll = max(0, $hrGrossPayroll - $hrStatutoryDeductions);
};

$loadAttendanceSummary = static function () use (&$attendanceLogs, &$todayAttendanceLogs, &$hrPresentCount, &$hrDashboardMetrics, &$hrAbsentCount, &$hrLateCount, &$hrPresentPercent, &$hrAbsentPercent, &$hrLatePercent, &$hrOnPayroll): void {
    $attendanceLogs = GeoAttendanceService::getAttendanceLogs();
    $todayAttendanceLogs = array_values(array_filter($attendanceLogs, static fn(array $log): bool => str_starts_with((string)($log['attendance_date'] ?? $log['clock_in'] ?? ''), date('Y-m-d'))));
    $hrPresentCount = count($todayAttendanceLogs);
    $hrDashboardMetrics = HrPayrollService::getDashboardMetrics();
    $hrAbsentCount = (int)($hrDashboardMetrics['absent_today'] ?? max(0, $hrOnPayroll - $hrPresentCount));
    $hrLateCount = count(array_filter($todayAttendanceLogs, static function (array $log): bool {
        return stripos((string)($log['lateness'] ?? ''), 'late') !== false;
    }));
    $hrAttendanceBase = max(1, $hrOnPayroll);
    $hrPresentPercent = min(100, round(($hrPresentCount / $hrAttendanceBase) * 100));
    $hrAbsentPercent = min(100, round(($hrAbsentCount / $hrAttendanceBase) * 100));
    $hrLatePercent = min(100, round(($hrLateCount / $hrAttendanceBase) * 100));
};

if ($activeTab === 'tab-hr-payroll') {
    $loadHrSummary();
    $loadAttendanceSummary();
    $staffWhatsAppGroupUrl = AppSettingsService::get('STAFF_WHATSAPP_GROUP_URL', '') ?? '';
    $employedStaff = array_values(array_filter($employees, static fn(array $emp): bool => in_array((string)($emp['status'] ?? ''), ['Active', 'Onboarding', 'On Leave'], true)));
    $hrSettings = HrPayrollService::getSettings();
    $hrRoleCatalog = HrPayrollService::getRoles();
    $hrPermissionCatalog = HrPayrollService::permissionCatalog();
    $hrKycRecords = HrPayrollService::getKycRecords();
    $hrDamageReports = HrPayrollService::getDamageReports();
    $hrLeaveRecords = HrPayrollService::getLeaveRecords();
    $hrPerformanceReviews = HrPayrollService::getPerformanceReviews();
    $hrDisciplinaryRecords = HrPayrollService::getDisciplinaryRecords();
    $hrPayrollPeriods = HrPayrollService::getPayrollPeriods();
    $hrAuditLogs = HrPayrollService::getAuditLogs(8);
    $hrDocuments = HrPayrollService::getEmployeeDocuments();
    $hrKycProviderStatus = HrPayrollService::kycProviderStatus((string)($hrSettings['kyc_provider'] ?? 'Provn'));
    $hrTodayCalendar = HrPayrollService::classifyWorkDate();
    $hrCalendarRecords = HrPayrollService::getWorkCalendar(date('Y-m-01'), date('Y-m-t'));
    $hrDrivers = HrPayrollService::getDrivers();
    $hrDriverAccountability = HrPayrollService::getDriverAccountabilityRows();
} elseif ($activeTab === 'tab-geo-attendance') {
    $loadHrSummary();
    $loadAttendanceSummary();
} elseif ($activeTab === 'tab-logistics') {
    $deliveries = LogisticsService::getDeliveries($companyContext);
    $logisticsMetrics = LogisticsService::getMetrics($companyContext);
    $pendingDeliveries = array_values(array_filter($deliveries, static fn(array $delivery): bool => empty($delivery['pod_acknowledged'])));
    $returnDeliveries = array_values(array_filter($deliveries, static function (array $delivery): bool {
        $status = strtolower((string)($delivery['status'] ?? ''));
        $documentType = strtolower((string)($delivery['document_type'] ?? ''));
        $evidence = strtolower((string)($delivery['pod_evidence_status'] ?? ''));
        $summary = strtolower((string)($delivery['items_summary'] ?? ''));

        return str_contains($status, 'return')
            || str_contains($status, 'exception')
            || str_contains($status, 'review')
            || str_contains($documentType, 'return')
            || str_contains($documentType, 'exception')
            || str_contains($evidence, 'return')
            || str_contains($summary, 'return');
    }));
    $visibleLogisticsDeliveries = match ($logisticsView) {
        'returns' => $returnDeliveries,
        'pod' => $pendingDeliveries,
        default => $deliveries,
    };
    $logisticsSyncEvents = array_values(array_filter($_SESSION['logistics_sync_events'] ?? [], static function (array $sync) use ($companyContext): bool {
        return ($sync['company_id'] ?? 'beverage') === $companyContext;
    }));
    $hrDrivers = HrPayrollService::getDrivers();
    $vendors = ProcurementService::getVendors();
    $logisticsSupplierOptions = array_values(array_filter($vendors, static function (array $vendor): bool {
        $category = strtolower((string)($vendor['category'] ?? ''));
        return str_contains($category, 'beverage');
    }));
    if (empty($logisticsSupplierOptions)) {
        $logisticsSupplierOptions[] = ['company_name' => 'Empress Tee Beverage Depot'];
    }
} elseif ($activeTab === 'tab-maintenance') {
    $assets = MaintenanceService::getAssets();
    $workOrders = MaintenanceService::getWorkOrders();
} elseif ($activeTab === 'tab-procurement') {
    $purchaseOrders = ProcurementService::getPurchaseOrders();
} elseif ($activeTab === 'tab-payment-recon') {
    $bankAlerts = PaymentReconService::getAlerts();
    $mailConnector = PaymentReconService::getMailConnectorConfig();
    $bankProfiles = PaymentReconService::getBankProfiles();
} elseif ($activeTab === 'tab-notifications') {
    $notifications = NotificationService::getNotifications($companyContext);
    $mailConnector = PaymentReconService::getMailConnectorConfig();
    $mailDelivery = MailDeliveryService::getStatus();
} elseif ($activeTab === 'tab-central-ai') {
    $aiSupervisorBrief = AiSupervisorService::getSupervisorBrief($companyContext);
} elseif ($activeTab === 'tab-health-score') {
    $healthOverview = SystemHealthService::getHealthOverview();
} elseif ($activeTab === 'tab-system-settings') {
    $mailDelivery = MailDeliveryService::getStatus();
    $systemSettings = AppSettingsService::all();
    $staffWhatsAppGroupUrl = (string)($systemSettings['STAFF_WHATSAPP_GROUP_URL'] ?? '');
}

// Render Unified Sidebar Layout Header
render_header('360Management Enterprise Control Center', $user, false);
?>

<style>
.tab-module-wrap {
    padding: 1.5rem;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
    font-family: 'Inter', sans-serif;
}
.tab-module-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.tab-module-desc {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}
.mod-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.mod-kpi-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
}
.mod-kpi-card small {
    color: #64748b;
    font-size: 0.8rem;
    display: block;
    margin-bottom: 0.25rem;
}
.mod-kpi-card strong {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    display: block;
}
.mod-kpi-card span {
    font-size: 0.75rem;
    font-weight: 600;
    color: #10b981;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    font-size: 0.88rem;
}
.data-table th {
    background: #f1f5f9;
    color: #334155;
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 700;
    border-bottom: 2px solid #e2e8f0;
}
.data-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    color: #1e293b;
}
.form-box {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.35rem;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.88rem;
}
.btn-submit {
    background: #2563eb;
    color: #ffffff;
    font-weight: 700;
    padding: 0.6rem 1.25rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.btn-submit:hover { background: #1d4ed8; }
.badge-status {
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-block;
}
.badge-green { background: #d1fae5; color: #047857; }
.badge-yellow { background: #fef3c7; color: #b45309; }
.badge-blue { background: #dbeafe; color: #1d4ed8; }
.badge-red { background: #fee2e2; color: #b91c1c; }
.recon-mail-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
    gap: 1rem;
    align-items: start;
}
@media (max-width: 980px) {
    .recon-mail-grid { grid-template-columns: 1fr; }
}
.logistics-shell {
    background: #f6f8fb;
    border: 1px solid #dbe4f0;
    border-radius: 16px;
    padding: 1rem;
    font-family: 'Inter', sans-serif;
}
.logistics-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1.15rem 1.25rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    margin-bottom: 1rem;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.05);
}
.logistics-hero h2 {
    margin: 0;
    color: #071a44;
    font-size: 1.5rem;
    letter-spacing: 0;
}
.logistics-hero p {
    margin: 0.25rem 0 0;
    color: #64748b;
    font-size: 0.88rem;
}
.logistics-badge {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    border-radius: 999px;
    padding: 0.45rem 0.75rem;
    font-weight: 800;
    font-size: 0.78rem;
    white-space: nowrap;
}
.logistics-grid {
    display: grid;
    grid-template-columns: minmax(360px, 0.9fr) minmax(520px, 1.35fr);
    gap: 1rem;
    align-items: start;
}
.logistics-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}
.delivery-note-card {
    background: #ffffff;
    border: 1px solid #dbe4f0;
    border-radius: 14px;
    padding: 0;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.logistics-panel form .form-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}
.logistics-panel .form-group input,
.logistics-panel .form-group select,
.logistics-panel .form-group textarea {
    min-height: 42px;
    border-radius: 9px;
    border-color: #d7e0eb;
    background: #fbfdff;
}
.logistics-panel .form-group textarea {
    min-height: 88px;
    resize: vertical;
}
.logistics-panel .btn-submit {
    width: 100%;
    border-radius: 9px;
    padding: 0.75rem 1rem;
    background: #0f62fe;
}
.logistics-panel h3,
.delivery-note-card h3 {
    margin: 0;
    color: #0f172a;
    font-size: 1rem;
}
.logistics-panel-sub {
    color: #64748b;
    font-size: 0.78rem;
    margin: 0.25rem 0 1rem;
}
.delivery-note-stack {
    display: grid;
    gap: 0.75rem;
}
.delivery-note-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin: 0;
}
.delivery-note-summary {
    list-style: none;
    cursor: pointer;
    padding: 0.9rem 1rem;
    align-items: center;
    transition: background 0.18s ease, border-color 0.18s ease;
}
.delivery-note-summary > div {
    flex: 1;
    min-width: 0;
}
.delivery-note-summary .badge-status {
    white-space: nowrap;
}
.delivery-note-summary:hover {
    background: #f8fbff;
}
.delivery-note-summary::-webkit-details-marker {
    display: none;
}
.delivery-note-summary::after {
    content: "View";
    color: #2563eb;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    border-radius: 999px;
    padding: 0.35rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 800;
    align-self: center;
}
.delivery-note-card[open] .delivery-note-summary::after {
    content: "Hide";
    background: #f8fafc;
    color: #475569;
    border-color: #cbd5e1;
}
.delivery-note-body {
    padding: 1rem;
    border-top: 1px solid #e2e8f0;
    overflow-x: auto;
}
.delivery-note-head small {
    display: block;
    color: #64748b;
    font-weight: 700;
    margin-top: 0.2rem;
    line-height: 1.35;
}
.doc-field-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(130px, 1fr));
    gap: 0.65rem;
    margin-bottom: 1rem;
}
.doc-field {
    background: #fbfdff;
    border: 1px solid #e5edf7;
    border-radius: 10px;
    padding: 0.65rem;
}
.doc-field span {
    display: block;
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
}
.doc-field strong {
    display: block;
    color: #0f172a;
    font-size: 0.86rem;
    margin-top: 0.2rem;
}
.delivery-items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    margin: 0.75rem 0;
    min-width: 680px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.delivery-items-table th,
.delivery-items-table td {
    border-bottom: 1px solid #e2e8f0;
    padding: 0.55rem;
    text-align: left;
    color: #334155;
}
.delivery-items-table th {
    color: #475569;
    background: #f1f5f9;
    font-size: 0.7rem;
    text-transform: uppercase;
}
.pod-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.75rem;
}
.pod-checks span {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    border-radius: 8px;
    padding: 0.35rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 800;
}
.doc-warning {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;
}
.logistics-kpi {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.logistics-kpi .mod-kpi-card {
    margin: 0;
    background: #ffffff;
    border-radius: 14px;
    border-color: #e2e8f0;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.035);
}
.logistics-kpi .mod-kpi-card strong {
    font-size: 1.2rem;
}
.logistics-kpi .mod-kpi-card span {
    color: #047857;
}
@media (max-width: 1100px) {
    .logistics-grid {
        grid-template-columns: 1fr;
    }
    .logistics-kpi {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .doc-field-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 720px) {
    .logistics-hero,
    .delivery-note-head {
        flex-direction: column;
    }
    .logistics-grid,
    .doc-field-grid,
    .logistics-kpi,
    .logistics-panel form .form-grid {
        grid-template-columns: 1fr;
    }
    .logistics-shell {
        padding: 0.75rem;
    }
}
.hr-shell {
    background: #f7f8fc;
    border: 1px solid #dfe6f1;
    border-radius: 16px;
    padding: 1rem;
    font-family: 'Inter', sans-serif;
}
.hr-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.15rem;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.045);
    margin-bottom: 1rem;
}
.hr-hero h2 {
    margin: 0;
    color: #071a44;
    font-size: 1.45rem;
    letter-spacing: 0;
}
.hr-hero p {
    margin: 0.25rem 0 0;
    color: #64748b;
    font-size: 0.84rem;
}
.hr-actions {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.hr-search {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 280px;
    background: #f8fafc;
    border: 1px solid #dbe4f0;
    border-radius: 9px;
    padding: 0.55rem 0.7rem;
}
.hr-search input {
    border: 0;
    outline: 0;
    background: transparent;
    width: 100%;
    color: #0f172a;
    font-size: 0.8rem;
}
.hr-staff-chat-grid {
    display: grid;
    grid-template-columns: minmax(260px, 0.75fr) minmax(320px, 1.25fr);
    gap: 1rem;
    align-items: start;
}
.hr-chat-card {
    border: 1px solid #dbeafe;
    background: linear-gradient(145deg, #ffffff, #f6fbff);
    border-radius: 16px;
    padding: 1rem;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.055);
}
.hr-chat-card-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}
.hr-chat-avatar {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    background: #052e2b;
    color: #ffffff;
    display: grid;
    place-items: center;
    overflow: hidden;
    flex: 0 0 auto;
}
.hr-chat-avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 0.3rem;
}
.hr-chat-title strong {
    display: block;
    color: #0f172a;
    font-size: 1rem;
}
.hr-chat-title span {
    display: block;
    color: #64748b;
    font-size: 0.78rem;
    margin-top: 0.12rem;
}
.hr-chat-status {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0 0 0.9rem;
    padding: 0.35rem 0.6rem;
    border-radius: 999px;
    background: #ecfdf5;
    color: #047857;
    font-size: 0.72rem;
    font-weight: 900;
}
.hr-chat-status.not-ready {
    background: #fff7ed;
    color: #c2410c;
}
.hr-chat-preview {
    background: #e8f5ef;
    border: 1px solid #c7eadb;
    border-radius: 14px;
    padding: 0.85rem;
    display: grid;
    gap: 0.6rem;
    min-height: 230px;
}
.hr-chat-bubble {
    width: fit-content;
    max-width: 88%;
    border-radius: 13px;
    padding: 0.58rem 0.7rem;
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
}
.hr-chat-bubble.outgoing {
    margin-left: auto;
    background: #dcf8c6;
}
.hr-chat-bubble strong {
    display: block;
    font-size: 0.7rem;
    color: #047857;
    margin-bottom: 0.2rem;
}
.hr-chat-bubble span {
    display: block;
    font-size: 0.8rem;
    line-height: 1.45;
}
.hr-chat-bubble small {
    display: block;
    text-align: right;
    color: #64748b;
    font-size: 0.64rem;
    margin-top: 0.22rem;
}
.hr-chat-setup {
    margin-top: 0.9rem;
    display: grid;
    gap: 0.6rem;
}
.hr-chat-setup input {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 0.7rem 0.8rem;
    font-size: 0.82rem;
    outline: 0;
}
.hr-chat-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
    margin-top: 0.9rem;
}
.hr-chat-staff-list {
    display: grid;
    gap: 0.55rem;
}
.hr-chat-staff-card {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.75rem;
    align-items: center;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 12px;
    padding: 0.7rem 0.8rem;
}
.hr-chat-staff-card strong {
    display: block;
    color: #0f172a;
    font-size: 0.86rem;
}
.hr-chat-staff-card span {
    display: block;
    color: #64748b;
    font-size: 0.74rem;
    margin-top: 0.12rem;
}
.hr-btn {
    border: 1px solid #7c3aed;
    background: #ffffff;
    color: #6d28d9;
    border-radius: 8px;
    padding: 0.62rem 0.85rem;
    font-size: 0.76rem;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
}
.hr-btn.primary {
    background: #6d28d9;
    color: #ffffff;
}
.hr-metrics {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.035);
    margin-bottom: 1rem;
}
.hr-stat {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.7rem;
    padding: 1rem;
    border-right: 1px solid #e8eef7;
    min-width: 0;
}
.hr-stat:last-child {
    border-right: 0;
}
.hr-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-weight: 900;
    color: #6d28d9;
    background: #f3e8ff;
}
.hr-stat small {
    color: #64748b;
    font-size: 0.65rem;
    font-weight: 900;
    text-transform: uppercase;
}
.hr-stat strong {
    display: block;
    color: #0f172a;
    font-size: 1.15rem;
    margin-top: 0.2rem;
}
.hr-stat span {
    display: block;
    color: #059669;
    font-size: 0.68rem;
    font-weight: 800;
    margin-top: 0.25rem;
}
.hr-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 1rem;
}
.hr-menu,
.hr-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.035);
}
.hr-menu {
    padding: 0.75rem;
    align-self: start;
}
.hr-menu a,
.hr-quick-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    border-radius: 8px;
    padding: 0.68rem 0.75rem;
    color: #172554;
    text-decoration: none;
    font-size: 0.78rem;
    font-weight: 800;
}
button.hr-quick-row {
    width: 100%;
    border: 0;
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    text-align: left;
}
.hr-menu a.active,
.hr-menu a:hover,
.hr-quick-row:hover {
    background: #f3e8ff;
    color: #6d28d9;
}
.hr-page-hidden {
    display: none !important;
}
.hr-subpage-nav {
    display: flex;
    gap: 0.55rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.hr-subpage-nav a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0.45rem 0.75rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    color: #334155;
    background: #fff;
    text-decoration: none;
    font-size: 0.74rem;
    font-weight: 900;
}
.hr-subpage-nav a.active,
.hr-subpage-nav a:hover {
    background: #6d28d9;
    border-color: #6d28d9;
    color: #fff;
}
.hr-content {
    display: grid;
    gap: 1rem;
}
.hr-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0.8rem;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.03);
}
.hr-filter-tabs {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
}
.hr-filter-tabs button {
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 999px;
    color: #334155;
    cursor: pointer;
    font-size: 0.72rem;
    font-weight: 900;
    padding: 0.42rem 0.7rem;
}
.hr-filter-tabs button.active,
.hr-filter-tabs button:hover {
    background: #6d28d9;
    border-color: #6d28d9;
    color: #ffffff;
}
.hr-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1.25fr 1fr;
    gap: 1rem;
}
.hr-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
.hr-view-dashboard .hr-grid-3 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.hr-view-dashboard .hr-grid-4 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.hr-view-dashboard .hr-content {
    align-items: start;
}
.hr-panel {
    padding: 1rem;
    min-width: 0;
}
.hr-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}
.hr-panel-head h3 {
    margin: 0;
    color: #0f172a;
    font-size: 0.94rem;
}
.hr-panel-head span,
.hr-panel-head a {
    color: #6d28d9;
    font-size: 0.7rem;
    font-weight: 900;
    text-decoration: none;
}
.hr-donut {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    margin: 0 auto;
    background: conic-gradient(#a855f7 0 25%, #3b82f6 25% 45%, #10b981 45% 59%, #f97316 59% 71%, #ef4444 71% 82%, #cbd5e1 82% 100%);
    display: grid;
    place-items: center;
}
.hr-donut-core {
    width: 98px;
    height: 98px;
    border-radius: 50%;
    background: #fff;
    display: grid;
    place-items: center;
    text-align: center;
}
.hr-donut-core strong {
    font-size: 1.25rem;
    color: #0f172a;
}
.hr-payroll-chart {
    width: 100%;
    height: 210px;
    display: block;
    background: linear-gradient(180deg, #ffffff, #fbf7ff);
    border: 1px solid #f1e7ff;
    border-radius: 12px;
}
.hr-list {
    display: grid;
    gap: 0.55rem;
}
.hr-list-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem;
    border: 1px solid #e8eef7;
    border-radius: 9px;
}
.hr-list-row strong {
    color: #0f172a;
    font-size: 0.78rem;
}
.hr-list-row span {
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 700;
}
.hr-dept-row {
    display: grid;
    gap: 0.35rem;
}
.hr-dept-head {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    color: #172554;
    font-size: 0.76rem;
    font-weight: 900;
}
.hr-track {
    height: 7px;
    background: #eef2f7;
    border-radius: 999px;
    overflow: hidden;
}
.hr-track div {
    height: 100%;
    border-radius: 999px;
    background: #8b5cf6;
}
.hr-pill {
    border-radius: 999px;
    padding: 0.25rem 0.55rem;
    font-size: 0.66rem;
    font-weight: 900;
    white-space: nowrap;
}
.hr-ok { background: #dcfce7; color: #047857; }
.hr-warn { background: #fff7ed; color: #c2410c; }
.hr-bad { background: #fee2e2; color: #dc2626; }
.hr-purple { background: #f3e8ff; color: #6d28d9; }
.hr-mini-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}
.hr-mini {
    border: 1px solid #e8eef7;
    border-radius: 10px;
    padding: 0.75rem;
    background: #fbfdff;
}
.hr-mini small {
    display: block;
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 800;
}
.hr-mini strong {
    display: block;
    color: #0f172a;
    font-size: 1rem;
    margin-top: 0.2rem;
}
.hr-form-details {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #ffffff;
    overflow: hidden;
}
.hr-form-details summary {
    cursor: pointer;
    list-style: none;
    padding: 0.9rem 1rem;
    font-weight: 900;
    color: #6d28d9;
    background: #faf5ff;
}
.hr-form-details summary::-webkit-details-marker {
    display: none;
}
.hr-form-body {
    padding: 1rem;
    border-top: 1px solid #e9d5ff;
}
.hr-table-wrap {
    overflow-x: auto;
}
.hr-table {
    width: 100%;
    min-width: 860px;
    border-collapse: collapse;
    font-size: 0.78rem;
}
.hr-table th {
    background: #f8fafc;
    color: #475569;
    text-align: left;
    padding: 0.7rem 0.75rem;
    text-transform: uppercase;
    font-size: 0.66rem;
}
.hr-table td {
    border-top: 1px solid #e8eef7;
    padding: 0.7rem 0.75rem;
    color: #172554;
}
.hr-empty-row {
    display: none;
    color: #64748b;
    font-weight: 800;
    text-align: center;
    padding: 1rem;
}
.hr-form-details[open] {
    box-shadow: 0 14px 36px rgba(109, 40, 217, 0.11);
}
@media (max-width: 1180px) {
    .hr-layout,
    .hr-grid-3,
    .hr-grid-4,
    .hr-staff-chat-grid {
        grid-template-columns: 1fr;
    }
    .hr-menu {
        display: none;
    }
    .hr-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 720px) {
    .hr-hero {
        flex-direction: column;
        align-items: flex-start;
    }
    .hr-actions,
    .hr-btn,
    .hr-search {
        width: 100%;
    }
    .hr-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .hr-chat-actions {
        grid-template-columns: 1fr;
    }
    .hr-metrics,
    .hr-mini-grid {
        grid-template-columns: 1fr;
    }
    .hr-stat {
        border-right: 0;
        border-bottom: 1px solid #e8eef7;
    }
}

@media (max-width: 820px) {
    .tab-module-wrap {
        width: 100%;
        max-width: 100%;
        padding: 0.9rem !important;
        border-radius: 10px;
        overflow-x: hidden;
    }
    .tab-module-title {
        font-size: 1.08rem !important;
        line-height: 1.25;
        align-items: flex-start;
    }
    .tab-module-desc {
        font-size: 0.78rem !important;
        line-height: 1.45;
        margin-bottom: 1rem;
    }
    .mod-kpi-grid,
    .form-grid,
    .recon-mail-grid,
    .logistics-grid,
    .logistics-kpi,
    .doc-field-grid,
    .logistics-panel form .form-grid,
    .hr-layout,
    .hr-grid-3,
    .hr-grid-4,
    .hr-staff-chat-grid,
    .hr-metrics,
    .hr-mini-grid {
        grid-template-columns: 1fr !important;
    }
    .logistics-shell,
    .hr-shell {
        padding: 0.85rem !important;
        border-radius: 10px !important;
        overflow-x: hidden;
    }
    .logistics-hero,
    .delivery-note-head,
    .hr-hero,
    .hr-toolbar,
    .hr-panel-head {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .hr-actions,
    .hr-filter-tabs,
    .pod-checks {
        width: 100%;
        justify-content: flex-start !important;
    }
    .hr-search,
    .hr-btn,
    .btn-submit,
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        min-width: 0;
    }
    .hr-table-wrap,
    .delivery-note-body {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .tab-module-wrap .data-table {
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .data-table,
    .hr-table,
    .delivery-items-table {
        min-width: 700px;
    }
    .delivery-note-summary {
        display: grid !important;
        grid-template-columns: 1fr auto;
        gap: 0.7rem;
    }
    .hr-subpage-nav {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 0.3rem;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .hr-subpage-nav::-webkit-scrollbar {
        display: none;
    }
    .hr-subpage-nav a {
        flex: 0 0 auto;
    }
    .hr-form-details form[style*="grid-template-columns"],
    .logistics-panel div[style*="grid-template-columns:repeat(2"] {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 520px) {
    .mod-kpi-card,
    .form-box,
    .logistics-panel,
    .delivery-note-card,
    .hr-panel,
    .hr-stat {
        padding: 0.82rem !important;
        border-radius: 10px !important;
    }
    .logistics-hero h2,
    .hr-hero h2 {
        font-size: 1.1rem !important;
    }
    .logistics-badge,
    .badge-status,
    .hr-pill {
        white-space: normal;
        text-align: center;
    }
    .hr-filter-tabs button {
        flex: 1 1 100%;
    }
}
</style>

<?php if ($actionMessage): ?>
    <div style="background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;padding:1rem 1.25rem;border-radius:10px;margin-bottom:1.5rem;font-weight:600">
        <?= e($actionMessage) ?>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'tab-hr-payroll'): ?>
<!-- =================================================================
     MODULE 5: HR AND PAYROLL MANAGEMENT & ROLE-BASED DASHBOARD GENERATION
     ================================================================= -->
<div class="hr-shell hr-view-<?= e($hrView) ?>">
    <div class="hr-hero">
        <div>
            <h2><?= e($hrViewCopy[$hrView]['title']) ?></h2>
            <p><?= e($hrViewCopy[$hrView]['subtitle']) ?></p>
        </div>
        <div class="hr-actions">
            <label class="hr-search">
                <span>Search</span>
                <input type="search" id="hrEmployeeSearch" placeholder="employees, department, role">
            </label>
            <a class="hr-btn" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=onboarding&company=' . $companyContext) ?>">Generate Staff Dashboard</a>
            <a class="hr-btn primary" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=payroll&company=' . $companyContext) ?>">Run Payroll</a>
        </div>
    </div>

    <section class="hr-metrics">
        <div class="hr-stat"><div class="hr-stat-icon">HR</div><div><small>Total Employees</small><strong><?= number_format($hrTotalEmployees) ?></strong><span>Active workforce</span></div></div>
        <div class="hr-stat"><div class="hr-stat-icon">₦</div><div><small>On Payroll</small><strong><?= number_format($hrOnPayroll) ?></strong><span><?= $hrTotalEmployees ? round(($hrOnPayroll / $hrTotalEmployees) * 100, 1) : 0 ?>%</span></div></div>
        <div class="hr-stat"><div class="hr-stat-icon">DP</div><div><small>Departments</small><strong><?= number_format(count($hrDepartments)) ?></strong><span>Active</span></div></div>
        <div class="hr-stat"><div class="hr-stat-icon">GP</div><div><small>Total Payroll</small><strong>₦<?= number_format($hrGrossPayroll, 0) ?></strong><span>Gross pay</span></div></div>
        <div class="hr-stat"><div class="hr-stat-icon">SD</div><div><small>Statutory Deductions</small><strong>₦<?= number_format($hrStatutoryDeductions, 0) ?></strong><span style="color:#dc2626">This month</span></div></div>
        <div class="hr-stat"><div class="hr-stat-icon">AP</div><div><small>Pending Approvals</small><strong>18</strong><span style="color:#dc2626">Requires action</span></div></div>
    </section>

    <div class="hr-layout">
        <aside class="hr-menu">
            <a class="<?= $hrView === 'dashboard' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=dashboard&company=' . $companyContext) ?>">HR Dashboard</a>
            <a class="<?= $hrView === 'employees' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=employees&company=' . $companyContext) ?>">Employees</a>
            <a class="<?= $hrView === 'departments' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=departments&company=' . $companyContext) ?>">Departments</a>
            <a class="<?= $hrView === 'staff-chat' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=staff-chat&company=' . $companyContext) ?>">Staff Chat</a>
            <a class="<?= $hrView === 'leave' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=leave&company=' . $companyContext) ?>">Attendance &amp; Leave</a>
            <a class="<?= $hrView === 'payroll' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=payroll&company=' . $companyContext) ?>">Payroll Management</a>
            <a class="<?= $hrView === 'onboarding' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=onboarding&company=' . $companyContext) ?>">Onboarding</a>
            <a class="<?= $hrView === 'kyc' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=kyc&company=' . $companyContext) ?>">KYC Verification</a>
            <a class="<?= $hrView === 'documents' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=documents&company=' . $companyContext) ?>">Documents</a>
            <a class="<?= $hrView === 'damage' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=damage&company=' . $companyContext) ?>">Damage Register</a>
            <a class="<?= $hrView === 'drivers' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=drivers&company=' . $companyContext) ?>">Drivers</a>
            <a class="<?= $hrView === 'performance' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=performance&company=' . $companyContext) ?>">Performance</a>
            <a class="<?= $hrView === 'discipline' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=discipline&company=' . $companyContext) ?>">Disciplinary</a>
            <a class="<?= $hrView === 'reports' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=reports&company=' . $companyContext) ?>">Reports</a>
            <a class="<?= $hrView === 'settings' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=settings&company=' . $companyContext) ?>">HR Settings</a>
            <a class="<?= $hrView === 'calendar' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=calendar&company=' . $companyContext) ?>">Work Calendar</a>
            <a class="<?= $hrView === 'audit' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=audit&company=' . $companyContext) ?>">Audit Trail</a>
            <a class="<?= $hrView === 'compliance' ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=compliance&company=' . $companyContext) ?>">Statutory Compliance</a>
            <a href="<?= url('dashboard.php?tab=tab-hr-payroll&view=onboarding&company=' . $companyContext) ?>">Add New Employee</a>
            <a href="<?= url('dashboard.php?tab=tab-hr-payroll&view=payroll&company=' . $companyContext) ?>">Run Payroll</a>
        </aside>

        <main class="hr-content" id="hr-dashboard">
            <nav class="hr-subpage-nav" aria-label="HR sections">
                <?php foreach (['dashboard' => 'Overview', 'employees' => 'Employees', 'departments' => 'Departments', 'staff-chat' => 'Staff Chat', 'kyc' => 'KYC', 'payroll' => 'Payroll', 'leave' => 'Leave', 'documents' => 'Documents', 'damage' => 'Damage', 'drivers' => 'Drivers', 'performance' => 'Performance', 'discipline' => 'Discipline', 'settings' => 'Settings', 'calendar' => 'Calendar', 'audit' => 'Audit', 'compliance' => 'Compliance', 'reports' => 'Reports'] as $viewKey => $viewLabel): ?>
                    <a class="<?= $hrView === $viewKey ? 'active' : '' ?>" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=' . $viewKey . '&company=' . $companyContext) ?>"><?= e($viewLabel) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="hr-toolbar<?= in_array($hrView, ['dashboard', 'employees'], true) ? '' : ' hr-page-hidden' ?>">
                <div class="hr-filter-tabs" aria-label="Employee filters">
                    <button type="button" class="active" data-hr-filter="all">All Employees</button>
                    <button type="button" data-hr-filter="dashboard">Dashboard Access</button>
                    <button type="button" data-hr-filter="no-access">No Access</button>
                    <button type="button" data-hr-filter="payroll">On Payroll</button>
                </div>
                <span id="hrEmployeeCount" style="color:#64748b;font-size:0.76rem;font-weight:900"><?= number_format($hrTotalEmployees) ?> employees shown</span>
            </div>

            <section class="hr-grid-3">
                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'onboarding'], true) ? '' : ' hr-page-hidden' ?>" id="hr-onboarding">
                    <div class="hr-panel-head"><h3>Workforce Control</h3><span>Live HR status</span></div>
                    <div class="hr-mini-grid">
                        <div class="hr-mini"><small>Present Today</small><strong><?= number_format((int)$hrDashboardMetrics['present_today']) ?></strong></div>
                        <div class="hr-mini"><small>Late Today</small><strong><?= number_format((int)$hrDashboardMetrics['late_today']) ?></strong></div>
                        <div class="hr-mini"><small>Absent Today</small><strong><?= number_format((int)$hrDashboardMetrics['absent_today']) ?></strong></div>
                        <div class="hr-mini"><small>Official Duty</small><strong><?= number_format((int)$hrDashboardMetrics['official_duty']) ?></strong></div>
                        <div class="hr-mini"><small>Pending KYC</small><strong><?= number_format((int)$hrDashboardMetrics['pending_kyc']) ?></strong></div>
                        <div class="hr-mini"><small>Damage Review</small><strong><?= number_format((int)$hrDashboardMetrics['pending_damage_investigations']) ?></strong></div>
                    </div>
                </div>

                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'departments'], true) ? '' : ' hr-page-hidden' ?>" id="hr-departments">
                    <div class="hr-panel-head"><h3>Employee Distribution</h3><span>By Department</span></div>
                    <div class="hr-donut"><div class="hr-donut-core"><div><strong><?= number_format($hrTotalEmployees) ?></strong><br><span style="font-size:0.72rem;color:#64748b;font-weight:800">Employees</span></div></div></div>
                    <div class="hr-list" style="margin-top:0.85rem">
                        <?php foreach ($hrDepartments as $department => $count): ?>
                            <?php $deptPercent = $hrTotalEmployees ? round(($count / $hrTotalEmployees) * 100) : 0; ?>
                            <div class="hr-dept-row">
                                <div class="hr-dept-head"><span><?= e($department) ?></span><span><?= number_format($count) ?> staff · <?= $deptPercent ?>%</span></div>
                                <div class="hr-track"><div style="width:<?= $deptPercent ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'payroll'], true) ? '' : ' hr-page-hidden' ?>">
                    <div class="hr-panel-head"><h3>Payroll Summary</h3><span><?= date('M Y') ?></span></div>
                    <svg class="hr-payroll-chart" viewBox="0 0 620 210" preserveAspectRatio="none">
                        <defs><linearGradient id="hrPayrollFill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#a855f7" stop-opacity=".32"/><stop offset="1" stop-color="#a855f7" stop-opacity="0"/></linearGradient></defs>
                        <path d="M0 160 L70 158 L140 148 L210 132 L280 139 L350 102 L420 122 L500 94 L620 76 L620 210 L0 210 Z" fill="url(#hrPayrollFill)"/>
                        <polyline points="0,160 70,158 140,148 210,132 280,139 350,102 420,122 500,94 620,76" fill="none" stroke="#9333ea" stroke-width="4"/>
                    </svg>
                    <div class="hr-mini-grid" style="margin-top:0.8rem">
                        <div class="hr-mini"><small>Gross Payroll</small><strong>₦<?= number_format($hrGrossPayroll, 0) ?></strong></div>
                        <div class="hr-mini"><small>Net Payroll</small><strong>₦<?= number_format($hrNetPayroll, 0) ?></strong></div>
                        <div class="hr-mini"><small>Deductions</small><strong>₦<?= number_format($hrStatutoryDeductions, 0) ?></strong></div>
                        <div class="hr-mini"><small>Allowances</small><strong>₦<?= number_format($hrTotalAllowances, 0) ?></strong></div>
                    </div>
                </div>

                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'compliance'], true) ? '' : ' hr-page-hidden' ?>" id="hr-compliance">
                    <div class="hr-panel-head"><h3>Statutory Compliance Status</h3><span><?= date('M Y') ?></span></div>
                    <div class="hr-list">
                        <div class="hr-list-row"><strong>PAYE</strong><span>₦<?= number_format($hrPaye, 0) ?> <em class="hr-pill hr-ok">Paid</em></span></div>
                        <div class="hr-list-row"><strong>Pension (8%)</strong><span>₦<?= number_format($hrPension, 0) ?> <em class="hr-pill hr-ok">Paid</em></span></div>
                        <div class="hr-list-row"><strong>NHF (2.5%)</strong><span>₦<?= number_format($hrNhf, 0) ?> <em class="hr-pill hr-ok">Paid</em></span></div>
                        <div class="hr-list-row"><strong>WHT</strong><span>₦<?= number_format($hrWht, 0) ?> <em class="hr-pill hr-ok">Paid</em></span></div>
                        <div class="hr-list-row"><strong>NSSF</strong><span>₦<?= number_format($hrNssf, 0) ?> <em class="hr-pill hr-ok">Paid</em></span></div>
                    </div>
                </div>
            </section>

            <section class="hr-grid-4">
                <div class="hr-panel<?= $hrView === 'leave' ? '' : ' hr-page-hidden' ?>" id="hr-leave">
                    <div class="hr-panel-head"><h3>Leave Overview</h3><span><?= date('M Y') ?></span></div>
                    <div class="hr-mini-grid">
                        <div class="hr-mini"><small>Total Requests</small><strong><?= number_format(count($hrLeaveRecords)) ?></strong></div>
                        <div class="hr-mini"><small>On Leave Today</small><strong><?= number_format(count(array_filter($hrLeaveRecords, static fn(array $leave): bool => (string)($leave['status'] ?? '') === 'Approved' && (string)($leave['start_date'] ?? '') <= date('Y-m-d') && (string)($leave['end_date'] ?? '') >= date('Y-m-d')))) ?></strong></div>
                        <div class="hr-mini"><small>Approved</small><strong><?= number_format(count(array_filter($hrLeaveRecords, static fn(array $leave): bool => (string)($leave['status'] ?? '') === 'Approved'))) ?></strong></div>
                        <div class="hr-mini"><small>Pending Approval</small><strong><?= number_format(count(array_filter($hrLeaveRecords, static fn(array $leave): bool => (string)($leave['status'] ?? '') === 'Pending'))) ?></strong></div>
                    </div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-leave') ?>" style="margin-top:1rem">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="create_leave_record">
                        <div class="form-grid">
                            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_code" required></div>
                            <div class="form-group"><label>Type</label><select name="leave_type"><option>Official Duty</option><option>Delivery Assignment</option><option>Field Work</option><option>Approved Absence</option><option>Annual Leave</option><option>Sick Leave</option><option>Emergency Leave</option></select></div>
                            <div class="form-group"><label>Start</label><input type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>"></div>
                            <div class="form-group"><label>End</label><input type="date" name="end_date" value="<?= e(date('Y-m-d')) ?>"></div>
                            <div class="form-group"><label>Status</label><select name="status"><option>Pending</option><option>Approved</option><option>Rejected</option><option>Closed</option></select></div>
                            <div class="form-group"><label>Reason</label><input type="text" name="reason"></div>
                        </div>
                        <button type="submit" class="hr-btn primary">Save Leave / Duty</button>
                    </form>
                </div>
                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'leave'], true) ? '' : ' hr-page-hidden' ?>">
                    <div class="hr-panel-head"><h3>Attendance Overview</h3><span><?= date('M Y') ?></span></div>
                    <div class="hr-donut" style="width:150px;height:150px;background:conic-gradient(#10b981 0 <?= $hrPresentPercent ?>%, #ef4444 <?= $hrPresentPercent ?>% <?= min(100, $hrPresentPercent + $hrAbsentPercent) ?>%, #fbbf24 <?= min(100, $hrPresentPercent + $hrAbsentPercent) ?>% 100%)"><div class="hr-donut-core" style="width:84px;height:84px"><div><strong><?= $hrPresentPercent ?>%</strong><br><span style="font-size:0.72rem;color:#64748b;font-weight:800">Present</span></div></div></div>
                    <div class="hr-list" style="margin-top:0.8rem">
                        <div class="hr-list-row"><strong>Present</strong><span><?= number_format($hrPresentCount) ?> (<?= $hrPresentPercent ?>%)</span></div>
                        <div class="hr-list-row"><strong>Absent</strong><span><?= number_format($hrAbsentCount) ?> (<?= $hrAbsentPercent ?>%)</span></div>
                        <div class="hr-list-row"><strong>Late</strong><span><?= number_format($hrLateCount) ?> (<?= $hrLatePercent ?>%)</span></div>
                    </div>
                </div>
                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'calendar'], true) ? '' : ' hr-page-hidden' ?>">
                    <div class="hr-panel-head"><h3>Upcoming Events</h3><span>Calendar</span></div>
                    <div class="hr-list">
                        <div class="hr-list-row"><strong>Payroll Processing</strong><span>May 25 - May 28</span></div>
                        <div class="hr-list-row"><strong>PAYE Remittance</strong><span>Due May 31</span></div>
                        <div class="hr-list-row"><strong>Pension Remittance</strong><span>Due Jun 10</span></div>
                        <div class="hr-list-row"><strong>NHF Remittance</strong><span>Due Jun 15</span></div>
                    </div>
                </div>
                <div class="hr-panel<?= $hrView === 'dashboard' ? '' : ' hr-page-hidden' ?>">
                    <div class="hr-panel-head"><h3>Quick Actions</h3><span>Tools</span></div>
                    <a class="hr-quick-row" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=onboarding&company=' . $companyContext) ?>">Add New Employee <span>›</span></a>
                    <a class="hr-quick-row" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=payroll&company=' . $companyContext) ?>">Run Payroll <span>›</span></a>
                    <a class="hr-quick-row" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=leave&company=' . $companyContext) ?>">Approve Leave Requests <span class="hr-pill hr-purple">13</span></a>
                    <a class="hr-quick-row" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=compliance&company=' . $companyContext) ?>">Upload Statutory Payment <span>›</span></a>
                    <a class="hr-quick-row" href="<?= url('dashboard.php?tab=tab-hr-payroll&view=reports&company=' . $companyContext) ?>">Generate Reports <span>›</span></a>
                </div>
            </section>

            <section class="hr-panel<?= $hrView === 'reports' ? '' : ' hr-page-hidden' ?>" id="hr-reports">
                <div class="hr-panel-head"><h3>HR Reports &amp; Exports</h3><span>CSV downloads</span></div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.65rem">
                    <?php foreach ([
                        'employees' => 'Employee Directory',
                        'attendance' => 'Attendance',
                        'payroll' => 'Payroll',
                        'damage' => 'Damage Liability',
                        'kyc' => 'KYC Status',
                        'cashier' => 'Cash Drawer Variance',
                        'leave' => 'Leave & Duty',
                        'calendar' => 'Work Calendar',
                        'drivers' => 'Driver Accountability',
                        'performance' => 'Performance',
                        'disciplinary' => 'Disciplinary',
                        'audit' => 'Audit Trail',
                    ] as $reportKey => $reportLabel): ?>
                        <a class="hr-btn" href="<?= url('dashboard.php?action=export_hr_report&report=' . rawurlencode($reportKey)) ?>" style="justify-content:center;text-align:center;text-decoration:none"><?= e($reportLabel) ?></a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hr-panel<?= $hrView === 'staff-chat' ? '' : ' hr-page-hidden' ?>" id="hr-staff-chat">
                <div class="hr-panel-head">
                    <h3>Official Staff WhatsApp Room</h3>
                    <span><?= number_format(count($employedStaff)) ?> employed staff</span>
                </div>
                <div class="hr-staff-chat-grid">
                    <div class="hr-chat-card">
                        <div class="hr-chat-card-head">
                            <div class="hr-chat-avatar">
                                <img src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="Empress Tee">
                            </div>
                            <div class="hr-chat-title">
                                <strong>Empress Tee Staff Chat</strong>
                                <span>WhatsApp room for employed staff</span>
                            </div>
                        </div>
                        <div class="hr-chat-status<?= $staffWhatsAppGroupUrl === '' ? ' not-ready' : '' ?>">
                            <span><?= $staffWhatsAppGroupUrl === '' ? 'Setup needed' : 'Active' ?></span>
                            <span><?= $staffWhatsAppGroupUrl === '' ? 'WhatsApp link not saved' : 'Official link connected' ?></span>
                        </div>
                        <div class="hr-chat-preview" aria-label="Staff chat preview">
                            <div class="hr-chat-bubble">
                                <strong>Admin</strong>
                                <span>Good morning team. Use this room for depot announcements, staff coordination and urgent updates.</span>
                                <small>9:00 AM</small>
                            </div>
                            <div class="hr-chat-bubble outgoing">
                                <strong>Operations</strong>
                                <span>Stock count and delivery notices should be posted here after confirmation.</span>
                                <small>9:05 AM</small>
                            </div>
                            <div class="hr-chat-bubble">
                                <strong>HR</strong>
                                <span>Only active, onboarding and on-leave employees should receive the group link.</span>
                                <small>9:10 AM</small>
                            </div>
                        </div>
                        <div class="hr-chat-actions">
                            <?php if ($staffWhatsAppGroupUrl !== ''): ?>
                                <a class="hr-btn primary" href="<?= e($staffWhatsAppGroupUrl) ?>" target="_blank" rel="noopener noreferrer" style="justify-content:center;text-align:center">Open WhatsApp Group</a>
                                <a class="hr-btn" href="https://wa.me/?text=<?= rawurlencode('Join the official Empress Tee Staff WhatsApp room: ' . $staffWhatsAppGroupUrl) ?>" target="_blank" rel="noopener noreferrer" style="justify-content:center;text-align:center">Share Invite</a>
                            <?php else: ?>
                                <span class="hr-btn primary" style="justify-content:center;text-align:center;opacity:0.7">Waiting for Link</span>
                                <a class="hr-btn" href="<?= url('dashboard.php?tab=tab-system-settings&company=' . $companyContext) ?>" style="justify-content:center;text-align:center">System Settings</a>
                            <?php endif; ?>
                        </div>
                        <?php if (in_array(canonical_role($user['role'] ?? ''), ['super_admin', 'admin'], true)): ?>
                            <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll&view=staff-chat&company=' . $companyContext) ?>" class="hr-chat-setup">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_action" value="save_staff_chat_settings">
                                <label style="font-size:0.72rem;font-weight:900;color:#334155;text-transform:uppercase">Official WhatsApp Invite Link</label>
                                <input type="url" name="STAFF_WHATSAPP_GROUP_URL" value="<?= e($staffWhatsAppGroupUrl) ?>" placeholder="https://chat.whatsapp.com/...">
                                <button type="submit" class="hr-btn primary" style="justify-content:center">Save Staff Chat Link</button>
                            </form>
                        <?php endif; ?>
                        <small style="display:block;margin-top:0.75rem;color:#64748b;line-height:1.45">Staff can open the official chat from HR and from employee self-service once the invite link is saved.</small>
                    </div>
                    <div class="hr-chat-card">
                        <div class="hr-panel-head" style="margin-bottom:0.8rem">
                            <h3>Staff Access List</h3>
                            <span><?= number_format(count(array_filter($employedStaff, static fn(array $staff): bool => !empty($staff['phone'])))) ?> with phone</span>
                        </div>
                        <?php if (empty($employedStaff)): ?>
                            <div class="hr-empty-row" style="display:block">No employed staff records yet.</div>
                        <?php endif; ?>
                        <div class="hr-chat-staff-list">
                            <?php foreach (array_slice($employedStaff, 0, 12) as $staff): ?>
                                <div class="hr-chat-staff-card">
                                    <div>
                                        <strong><?= e((string)($staff['name'] ?? 'Staff')) ?></strong>
                                        <span><?= e((string)($staff['employee_code'] ?? '')) ?> · <?= e((string)($staff['department'] ?? 'Unassigned')) ?></span>
                                        <span><?= e((string)($staff['phone'] ?? 'No phone')) ?></span>
                                    </div>
                                    <span class="hr-pill <?= !empty($staff['phone']) ? 'hr-ok' : 'hr-warn' ?>"><?= !empty($staff['phone']) ? 'Ready' : 'No phone' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="hr-panel<?= $hrView === 'drivers' ? '' : ' hr-page-hidden' ?>" id="hr-drivers">
                <div class="hr-panel-head"><h3>Driver Accountability</h3><span><?= count($hrDriverAccountability) ?> drivers</span></div>
                <?php if (empty($hrDriverAccountability)): ?>
                    <div class="hr-empty-row" style="display:block">No driver employee profiles yet.</div>
                <?php else: ?>
                    <div class="hr-table-wrap">
                        <table class="hr-table">
                            <thead><tr><th>Driver</th><th>License</th><th>Vehicle</th><th>Deliveries</th><th>Pending POD</th><th>Damage Cases</th></tr></thead>
                            <tbody>
                                <?php foreach ($hrDriverAccountability as $driverRow): ?>
                                    <tr>
                                        <td><strong><?= e((string)$driverRow['name']) ?></strong><br><span style="color:#64748b"><?= e((string)$driverRow['employee_code']) ?> · <?= e((string)$driverRow['phone']) ?></span></td>
                                        <td><?= e((string)($driverRow['license_number'] ?: 'Not captured')) ?><br><span style="color:#64748b"><?= e((string)($driverRow['license_expiry'] ?: 'No expiry')) ?></span></td>
                                        <td><?= e((string)($driverRow['vehicle_assignment'] ?: 'Unassigned')) ?></td>
                                        <td><?= number_format((int)$driverRow['deliveries']) ?></td>
                                        <td><span class="hr-pill <?= (int)$driverRow['pending_pod'] > 0 ? 'hr-warn' : 'hr-ok' ?>"><?= number_format((int)$driverRow['pending_pod']) ?></span></td>
                                        <td><span class="hr-pill <?= (int)$driverRow['damage_cases'] > 0 ? 'hr-bad' : 'hr-ok' ?>"><?= number_format((int)$driverRow['damage_cases']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <details class="hr-form-details<?= $hrView === 'onboarding' ? '' : ' hr-page-hidden' ?>" id="hr-add-staff" <?= $hrView === 'onboarding' ? 'open' : '' ?>>
                <summary>Add New Employee &amp; Generate Staff Dashboard</summary>
                <div class="hr-form-body">
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="add_employee">
                        <div class="form-grid">
                            <div class="form-group"><label>Staff Full Name</label><input type="text" name="name" required placeholder="e.g. Oluwaseun Davies"></div>
                            <div class="form-group"><label>Email Address</label><input type="email" name="email" required placeholder="staff@360management.com"></div>
                            <div class="form-group"><label>Phone Number</label><input type="text" name="phone" placeholder="080..."></div>
                            <div class="form-group"><label>Employment Status</label><select name="status"><option>Applicant</option><option>Onboarding</option><option selected>Active</option><option>Suspended</option><option>On Leave</option><option>Resigned</option><option>Terminated</option><option>Inactive</option></select></div>
                            <div class="form-group"><label>Department</label><select name="department" required><option value="Operations &amp; Depot">Operations &amp; Depot</option><option value="Sales &amp; POS">Sales &amp; POS</option><option value="Finance &amp; Accounts">Finance &amp; Accounts</option><option value="Logistics &amp; Dispatch">Logistics &amp; Dispatch</option></select></div>
                            <div class="form-group"><label>Job Designation</label><input type="text" name="designation" required placeholder="e.g. Depot Sales Supervisor"></div>
                            <div class="form-group"><label>Employee Role</label><select name="employee_role">
                                <?php foreach ($hrRoleCatalog as $roleOption): ?>
                                    <?php if (empty($roleOption['is_active'])) { continue; } ?>
                                    <option value="<?= e((string)$roleOption['role_name']) ?>"><?= e((string)$roleOption['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div class="form-group"><label>Branch Location</label><select name="branch" required><option value="Jacroxx Warehouse">Jacroxx Warehouse (Main)</option><option value="Ijaba Warehouse">Ijaba Warehouse</option></select></div>
                            <div class="form-group" id="assignedDashBox"><label>Assign Role-Based Dashboard</label><select name="assigned_dashboard" required><option value="beverage_warehouse">Beverage Depot ERP</option><option value="employee_self_service">Employee Self-Service</option><option value="pos_beverage">Beverage POS Terminal</option><option value="finance">Finance &amp; Accounting</option><option value="logistics">Logistics &amp; Delivery POD</option></select></div>
                            <div class="form-group"><label>Base Monthly Salary (₦)</label><input type="number" step="0.01" name="base_salary" required></div>
                            <div class="form-group"><label>Monthly Allowances (₦)</label><input type="number" step="0.01" name="allowances" required></div>
                            <div class="form-group"><label>Driver License Number</label><input type="text" name="driver_license_number" placeholder="For drivers"></div>
                            <div class="form-group"><label>License Expiry Date</label><input type="date" name="driver_license_expiry"></div>
                            <div class="form-group"><label>Vehicle Assignment</label><input type="text" name="vehicle_assignment" placeholder="e.g. AAB 225 XC"></div>
                            <div class="form-group"><label>Driver Notes</label><input type="text" name="driver_notes" placeholder="Fuel/accountability notes"></div>
                            <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name"></div>
                            <div class="form-group"><label>Account Number</label><input type="text" name="account_number" inputmode="numeric"></div>
                            <div class="form-group"><label>Account Name</label><input type="text" name="account_name"></div>
                            <div class="form-group"><label>KYC Status</label><select name="kyc_status"><option>Not Submitted</option><option>Pending</option><option>Verified</option><option>Failed</option><option>Requires Review</option></select></div>
                            <div class="form-group"><label>Dashboard Access</label><label style="display:flex;align-items:center;gap:0.5rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:0.7rem"><input type="checkbox" name="has_dashboard_access" value="1" checked onchange="document.getElementById('assignedDashBox').style.opacity = this.checked ? '1' : '0.45'"> Grant ERP dashboard access</label></div>
                        </div>
                        <button type="submit" class="btn-submit" style="background:#6d28d9">Add Staff &amp; Save Permissions →</button>
                    </form>
                </div>
            </details>

            <details class="hr-form-details<?= $hrView === 'payroll' ? '' : ' hr-page-hidden' ?>" id="hr-run-payroll" <?= $hrView === 'payroll' ? 'open' : '' ?>>
                <summary>Run Monthly Payroll Processor</summary>
                <div class="hr-form-body">
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll') ?>" style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:end">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="process_payroll">
                        <div class="form-group" style="margin:0"><label>Select Payroll Month</label><input type="month" name="payroll_month" value="<?= e(date('Y-m')) ?>" required></div>
                        <button type="submit" class="btn-submit" style="background:#6d28d9">Execute Payroll Run →</button>
                    </form>
                </div>
            </details>

            <section class="hr-grid-3" style="grid-template-columns:1fr 1fr 1fr">
                <div class="hr-panel<?= $hrView === 'kyc' ? '' : ' hr-page-hidden' ?>" id="hr-kyc">
                    <div class="hr-panel-head"><h3>KYC Verification</h3><span><?= e((string)$hrSettings['kyc_provider']) ?></span></div>
                    <div class="hr-mini" style="margin-bottom:1rem"><small>Provider Status</small><strong><?= e((string)$hrKycProviderStatus['name']) ?></strong><span><?= e((string)$hrKycProviderStatus['message']) ?></span></div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-kyc') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="save_employee_kyc">
                        <div class="form-grid">
                            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_code" required></div>
                            <div class="form-group"><label>Status</label><select name="status"><option>Pending</option><option>Verified</option><option>Failed</option><option>Requires Review</option></select></div>
                            <div class="form-group"><label>NIN</label><input type="text" name="nin" inputmode="numeric"></div>
                            <div class="form-group"><label>BVN</label><input type="text" name="bvn" inputmode="numeric"></div>
                            <div class="form-group"><label>Provider</label><select name="provider"><option value="Provn" <?= ($hrSettings['kyc_provider'] ?? 'Provn') === 'Provn' ? 'selected' : '' ?>>Provn</option><option value="Manual Review" <?= ($hrSettings['kyc_provider'] ?? '') === 'Manual Review' ? 'selected' : '' ?>>Manual Review</option></select></div>
                            <div class="form-group"><label>Reference</label><input type="text" name="reference"></div>
                        </div>
                        <button type="submit" class="hr-btn primary">Save KYC Verification</button>
                    </form>
                    <div class="hr-list" style="margin-top:1rem">
                        <?php foreach (array_slice($hrKycRecords, 0, 4) as $kyc): ?>
                            <div class="hr-list-row"><strong><?= e((string)$kyc['employee_code']) ?></strong><span><?= e((string)$kyc['status']) ?> · BVN <?= e((string)($kyc['bvn'] ?? '')) ?></span></div>
                        <?php endforeach; ?>
                        <?php if (empty($hrKycRecords)): ?><div class="hr-empty-row" style="display:block">No KYC records yet.</div><?php endif; ?>
                    </div>
                </div>

                <div class="hr-panel<?= $hrView === 'documents' ? '' : ' hr-page-hidden' ?>" id="hr-documents">
                    <div class="hr-panel-head"><h3>Employee Documents</h3><span>Protected HR storage</span></div>
                    <form method="post" enctype="multipart/form-data" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-documents') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="upload_employee_document">
                        <div class="form-grid">
                            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_code" required></div>
                            <div class="form-group"><label>Document Type</label><select name="document_type"><option>Passport Photograph</option><option>CV</option><option>Offer Letter</option><option>Employment Letter</option><option>NIN Document</option><option>Bank Information</option><option>Guarantor Information</option><option>Driver License</option><option>Certificate</option><option>Other HR Document</option></select></div>
                            <div class="form-group" style="grid-column:1/-1"><label>Document File</label><input type="file" name="employee_document"></div>
                            <div class="form-group"><label>Status</label><select name="status"><option>Pending</option><option>Verified</option><option>Requires Review</option><option>Expired</option></select></div>
                        </div>
                        <button type="submit" class="hr-btn primary">Capture Document</button>
                    </form>
                    <div class="hr-list" style="margin-top:1rem">
                        <?php foreach (array_slice($hrDocuments, 0, 4) as $doc): ?>
                            <div class="hr-list-row"><strong><?= e((string)$doc['document_type']) ?></strong><span><?= e((string)$doc['employee_code']) ?> · <?= e((string)$doc['status']) ?></span></div>
                        <?php endforeach; ?>
                        <?php if (empty($hrDocuments)): ?><div class="hr-empty-row" style="display:block">No employee documents captured yet.</div><?php endif; ?>
                    </div>
                </div>

                <div class="hr-panel<?= $hrView === 'damage' ? '' : ' hr-page-hidden' ?>" id="hr-damage">
                    <div class="hr-panel-head"><h3>Damage &amp; Accountability</h3><span>Approval required before deduction</span></div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-damage') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="create_damage_report">
                        <div class="form-grid">
                            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_code" required></div>
                            <div class="form-group"><label>Status</label><select name="status"><option>Reported</option><option>Under Investigation</option><option>Employee Response</option><option>Reviewed</option><option>Approved</option><option>Rejected</option><option>Deduction Scheduled</option><option>Closed</option></select></div>
                            <div class="form-group"><label>Asset / Item</label><input type="text" name="asset" required></div>
                            <div class="form-group"><label>Estimated Loss (₦)</label><input type="number" step="0.01" min="0" name="estimated_loss"></div>
                            <div class="form-group"><label>Approved Liability (₦)</label><input type="number" step="0.01" min="0" name="approved_liability"></div>
                            <div class="form-group"><label>Installments</label><input type="number" min="1" name="installments" value="1"></div>
                            <div class="form-group" style="grid-column:1/-1"><label>Description</label><input type="text" name="description"></div>
                        </div>
                        <button type="submit" class="hr-btn primary">Create Damage Report</button>
                    </form>
                    <div class="hr-list" style="margin-top:1rem">
                        <?php foreach (array_slice($hrDamageReports, 0, 4) as $report): ?>
                            <div style="border-top:1px solid #e2e8f0;padding:0.7rem 0">
                                <div class="hr-list-row" style="border:0;padding:0 0 0.45rem"><strong><?= e((string)$report['incident_number']) ?></strong><span><?= e((string)$report['employee_code']) ?> · ₦<?= number_format((float)$report['estimated_loss'], 0) ?> · <?= e((string)$report['investigation_status']) ?></span></div>
                                <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-damage') ?>" style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="form_action" value="update_damage_status">
                                    <input type="hidden" name="incident_number" value="<?= e((string)$report['incident_number']) ?>">
                                    <input type="number" step="0.01" min="0" name="approved_liability" value="<?= e((string)($report['approved_liability'] ?? 0)) ?>" style="max-width:140px" aria-label="Approved liability">
                                    <input type="number" min="1" name="installments" value="<?= e((string)($report['installments'] ?? 1)) ?>" style="max-width:90px" aria-label="Installments">
                                    <select name="status" aria-label="Damage status" style="max-width:190px">
                                        <?php foreach (['Under Investigation', 'Employee Response', 'Reviewed', 'Approved', 'Rejected', 'Deduction Scheduled', 'Closed'] as $damageStatus): ?>
                                            <option <?= (string)($report['investigation_status'] ?? '') === $damageStatus ? 'selected' : '' ?>><?= e($damageStatus) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="hr-btn" style="padding:0.35rem 0.55rem">Update</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($hrDamageReports)): ?><div class="hr-empty-row" style="display:block">No damage reports yet.</div><?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="hr-grid-3" style="grid-template-columns:1fr 1fr">
                <div class="hr-panel<?= $hrView === 'performance' ? '' : ' hr-page-hidden' ?>" id="hr-performance">
                    <div class="hr-panel-head"><h3>Performance Reviews</h3><span><?= count($hrPerformanceReviews) ?> records</span></div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-performance') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="create_performance_review">
                        <div class="form-grid">
                            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_code" required></div>
                            <div class="form-group"><label>Period</label><input type="month" name="period_label" value="<?= e(date('Y-m')) ?>"></div>
                            <div class="form-group"><label>Overall Score</label><input type="number" min="0" max="100" step="0.1" name="score" required></div>
                            <div class="form-group"><label>Attendance</label><input type="number" min="0" max="100" step="0.1" name="attendance_score"></div>
                            <div class="form-group"><label>Punctuality</label><input type="number" min="0" max="100" step="0.1" name="punctuality_score"></div>
                            <div class="form-group"><label>Operations</label><input type="number" min="0" max="100" step="0.1" name="operations_score"></div>
                            <div class="form-group" style="grid-column:1/-1"><label>Commendations / Notes</label><input type="text" name="review_notes"></div>
                        </div>
                        <button type="submit" class="hr-btn primary">Save Performance Review</button>
                    </form>
                    <div class="hr-list" style="margin-top:1rem">
                        <?php foreach (array_slice($hrPerformanceReviews, 0, 4) as $review): ?>
                            <div class="hr-list-row"><strong><?= e((string)$review['employee_code']) ?></strong><span><?= e((string)$review['period_label']) ?> · <?= number_format((float)$review['score'], 1) ?>%</span></div>
                        <?php endforeach; ?>
                        <?php if (empty($hrPerformanceReviews)): ?><div class="hr-empty-row" style="display:block">No performance reviews yet.</div><?php endif; ?>
                    </div>
                </div>

                <div class="hr-panel<?= $hrView === 'discipline' ? '' : ' hr-page-hidden' ?>" id="hr-discipline">
                    <div class="hr-panel-head"><h3>Disciplinary Records</h3><span><?= count($hrDisciplinaryRecords) ?> records</span></div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-discipline') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="create_disciplinary_record">
                        <div class="form-grid">
                            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_code" required></div>
                            <div class="form-group"><label>Action Type</label><select name="action_type"><option>Verbal Warning</option><option>Written Warning</option><option>Query</option><option>Suspension</option><option>Final Warning</option><option>Termination Recommendation</option></select></div>
                            <div class="form-group"><label>Status</label><select name="status"><option>Created</option><option>Under Review</option><option>Approved</option><option>Closed</option></select></div>
                            <div class="form-group"><label>Evidence Ref</label><input type="text" name="evidence_ref" placeholder="Document ID or note"></div>
                            <div class="form-group" style="grid-column:1/-1"><label>Reason</label><input type="text" name="reason" required></div>
                        </div>
                        <button type="submit" class="hr-btn primary">Save Disciplinary Record</button>
                    </form>
                    <div class="hr-list" style="margin-top:1rem">
                        <?php foreach (array_slice($hrDisciplinaryRecords, 0, 4) as $discipline): ?>
                            <div class="hr-list-row"><strong><?= e((string)$discipline['action_type']) ?></strong><span><?= e((string)$discipline['employee_code']) ?> · <?= e((string)$discipline['status']) ?></span></div>
                        <?php endforeach; ?>
                        <?php if (empty($hrDisciplinaryRecords)): ?><div class="hr-empty-row" style="display:block">No disciplinary records yet.</div><?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="hr-grid-3" style="grid-template-columns:1fr 1fr">
                <div class="hr-panel<?= $hrView === 'settings' ? '' : ' hr-page-hidden' ?>" id="hr-settings">
                    <div class="hr-panel-head"><h3>HR &amp; Workforce Settings</h3><span>Configurable rules</span></div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-settings') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="save_hr_settings">
                        <div class="form-grid">
                            <div class="form-group"><label>Employee Prefix</label><input type="text" name="employee_number_prefix" value="<?= e((string)$hrSettings['employee_number_prefix']) ?>"></div>
                            <div class="form-group"><label>Opening Time</label><input type="time" name="opening_time" value="<?= e((string)$hrSettings['opening_time']) ?>"></div>
                            <div class="form-group"><label>Grace Period (minutes)</label><input type="number" min="0" name="grace_minutes" value="<?= (int)$hrSettings['grace_minutes'] ?>"></div>
                            <div class="form-group"><label>Payroll Cycle</label><select name="payroll_cycle"><option value="monthly" <?= ($hrSettings['payroll_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option><option value="weekly" <?= ($hrSettings['payroll_cycle'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option></select></div>
                            <div class="form-group"><label>KYC Provider</label><select name="kyc_provider"><option value="Provn" <?= ($hrSettings['kyc_provider'] ?? 'Provn') === 'Provn' ? 'selected' : '' ?>>Provn</option><option value="Manual Review" <?= ($hrSettings['kyc_provider'] ?? '') === 'Manual Review' ? 'selected' : '' ?>>Manual Review</option></select></div>
                            <div class="form-group"><label>Payroll Payment Provider</label><input type="text" name="payment_provider" value="<?= e((string)$hrSettings['payment_provider']) ?>"></div>
                            <div class="form-group"><label>POS Employee Required</label><label style="display:flex;align-items:center;gap:0.5rem"><input type="checkbox" name="pos_employee_required" value="1" <?= !empty($hrSettings['pos_employee_required']) ? 'checked' : '' ?>> Require employee identity</label></div>
                            <div class="form-group" style="grid-column:1/-1">
                                <label>Normal Working Days</label>
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $workDay): ?>
                                        <label style="display:flex;align-items:center;gap:0.35rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:999px;padding:0.45rem 0.7rem;font-size:0.78rem;font-weight:800;color:#334155">
                                            <input type="checkbox" name="working_days[]" value="<?= e($workDay) ?>" <?= in_array($workDay, (array)($hrSettings['working_days'] ?? []), true) ? 'checked' : '' ?>>
                                            <?= e($workDay) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="hr-btn primary">Save HR Settings</button>
                    </form>
                    <div class="hr-panel-head" style="margin-top:1.25rem"><h3>Roles &amp; Permissions</h3><span><?= number_format(count($hrRoleCatalog)) ?> roles</span></div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-settings') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="save_employee_role">
                        <div class="form-grid">
                            <div class="form-group"><label>Role Name</label><input type="text" name="role_name" placeholder="e.g. Stock Auditor" required></div>
                            <div class="form-group"><label>Role Key</label><input type="text" name="role_key" placeholder="auto-created if blank"></div>
                            <div class="form-group"><label>Status</label><label style="display:flex;align-items:center;gap:0.5rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:0.7rem"><input type="checkbox" name="is_active" value="1" checked> Active role</label></div>
                            <div class="form-group" style="grid-column:1/-1">
                                <label>Permissions</label>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.45rem">
                                    <?php foreach ($hrPermissionCatalog as $permissionKey => $permissionLabel): ?>
                                        <label style="display:flex;align-items:center;gap:0.45rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:0.55rem;font-size:0.75rem;font-weight:800;color:#334155">
                                            <input type="checkbox" name="permissions[]" value="<?= e($permissionKey) ?>">
                                            <?= e($permissionLabel) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="hr-btn primary">Save Employee Role</button>
                    </form>
                    <div class="hr-list" style="margin-top:1rem">
                        <?php foreach ($hrRoleCatalog as $roleRow): ?>
                            <div class="hr-list-row">
                                <strong><?= e((string)$roleRow['role_name']) ?></strong>
                                <span><?= !empty($roleRow['is_system']) ? 'System' : 'Custom' ?> · <?= !empty($roleRow['is_active']) ? 'Active' : 'Inactive' ?> · <?= number_format(count((array)($roleRow['permissions'] ?? []))) ?> permission(s)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="hr-panel<?= $hrView === 'calendar' ? '' : ' hr-page-hidden' ?>" id="hr-calendar">
                    <div class="hr-panel-head"><h3>Work Calendar</h3><span><?= e((string)($hrTodayCalendar['day_type'] ?? 'Working Day')) ?></span></div>
                    <div class="hr-mini" style="margin-bottom:1rem"><small>Today</small><strong><?= e(date('D, M j')) ?></strong><span><?= e((string)($hrTodayCalendar['description'] ?? 'Configured working day')) ?></span></div>
                    <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-calendar') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="save_work_calendar_day">
                        <div class="form-grid">
                            <div class="form-group"><label>Date</label><input type="date" name="calendar_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                            <div class="form-group"><label>Day Type</label><select name="day_type"><option>Working Day</option><option>Weekend</option><option>Public Holiday</option><option>Company Holiday</option><option>Special Closure</option></select></div>
                            <div class="form-group" style="grid-column:1/-1"><label>Description</label><input type="text" name="description" placeholder="e.g. Independence Day, stock-taking closure"></div>
                        </div>
                        <button type="submit" class="hr-btn primary">Save Calendar Day</button>
                    </form>
                    <div class="hr-list" style="margin-top:1rem">
                        <?php foreach (array_slice($hrCalendarRecords, 0, 6) as $calendarDay): ?>
                            <div class="hr-list-row"><strong><?= e((string)$calendarDay['calendar_date']) ?></strong><span><?= e((string)$calendarDay['day_type']) ?> · <?= e((string)($calendarDay['description'] ?? '')) ?></span></div>
                        <?php endforeach; ?>
                        <?php if (empty($hrCalendarRecords)): ?><div class="hr-empty-row" style="display:block">No calendar overrides for this month.</div><?php endif; ?>
                    </div>
                </div>

                <div class="hr-panel<?= in_array($hrView, ['audit', 'payroll'], true) ? '' : ' hr-page-hidden' ?>" id="hr-audit">
                    <div class="hr-panel-head"><h3>Employee Audit Trail</h3><span>Latest actions</span></div>
                    <div class="hr-list">
                        <?php foreach ($hrAuditLogs as $audit): ?>
                            <div class="hr-list-row"><strong><?= e((string)$audit['action']) ?></strong><span><?= e((string)($audit['employee_code'] ?? '')) ?> · <?= e((string)$audit['created_at']) ?></span></div>
                        <?php endforeach; ?>
                        <?php if (empty($hrAuditLogs)): ?><div class="hr-empty-row" style="display:block">No HR audit records yet.</div><?php endif; ?>
                    </div>
                    <div class="hr-panel-head" style="margin-top:1rem"><h3>Payroll Workflow</h3><span><?= count($hrPayrollPeriods) ?> runs</span></div>
                    <div class="hr-list">
                        <?php foreach (array_slice($hrPayrollPeriods, 0, 3) as $period): ?>
                            <?php
                                $statusFlow = ['Reviewed', 'Approved', 'Payment Processing', 'Paid', 'Closed'];
                                $currentStatus = (string)($period['status'] ?? 'Calculated');
                                $currentStatusIndex = array_search($currentStatus, $statusFlow, true);
                                $currentStatusIndex = $currentStatusIndex === false ? -1 : $currentStatusIndex;
                                $nextStatuses = $currentStatus === 'Closed' ? [] : array_slice($statusFlow, $currentStatusIndex + 1, 2);
                            ?>
                            <div style="border:1px solid #e2e8f0;border-radius:10px;padding:0.75rem;background:#fff">
                                <div class="hr-list-row" style="border:0;padding:0 0 0.55rem"><strong><?= e((string)$period['month']) ?></strong><span><?= e($currentStatus) ?> · ₦<?= number_format((float)$period['total_net_payroll'], 0) ?></span></div>
                                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.55rem">
                                    <?php foreach ($nextStatuses as $nextStatus): ?>
                                        <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll#hr-audit') ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="form_action" value="update_payroll_status">
                                            <input type="hidden" name="period_id" value="<?= e((string)$period['period_id']) ?>">
                                            <input type="hidden" name="payroll_status" value="<?= e($nextStatus) ?>">
                                            <button type="submit" class="hr-btn" style="padding:0.35rem 0.55rem"><?= e($nextStatus) ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                                    <?php foreach (array_slice($period['records'] ?? [], 0, 4) as $payRecord): ?>
                                        <a class="hr-pill hr-purple" target="_blank" href="<?= url('payslip.php?period=' . rawurlencode((string)$period['period_id']) . '&employee=' . rawurlencode((string)$payRecord['employee_code'])) ?>">Payslip <?= e((string)$payRecord['employee_code']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($hrPayrollPeriods)): ?><div class="hr-empty-row" style="display:block">No payroll run yet.</div><?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="hr-grid-3" style="grid-template-columns:1.5fr 0.7fr">
                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'employees'], true) ? '' : ' hr-page-hidden' ?>" id="hr-employees">
                    <div class="hr-panel-head"><h3><?= $hrView === 'employees' ? 'Employee Directory' : 'Recent Employees' ?></h3><a href="<?= url('dashboard.php?tab=tab-hr-payroll&view=onboarding&company=' . $companyContext) ?>">Add staff</a></div>
                    <div class="hr-table-wrap">
                        <table class="hr-table">
                            <thead><tr><th>Employee ID</th><th>Employee Name</th><th>Department</th><th>Designation</th><th>Status</th><th>Dashboard</th><th>Action</th></tr></thead>
                            <tbody id="hrEmployeeTableBody">
                                <?php foreach ($employees as $e): ?>
                                    <tr data-hr-row data-access="<?= !empty($e['has_dashboard_access']) ? 'dashboard' : 'no-access' ?>" data-payroll="<?= ($e['status'] ?? 'Active') === 'Active' ? 'payroll' : 'inactive' ?>" data-search="<?= e(strtolower(($e['employee_code'] ?? '') . ' ' . ($e['name'] ?? '') . ' ' . ($e['email'] ?? '') . ' ' . ($e['department'] ?? '') . ' ' . ($e['designation'] ?? '') . ' ' . ($e['branch'] ?? ''))) ?>">
                                        <td><strong><?= e($e['employee_code']) ?></strong></td>
                                        <td><strong><?= e($e['name']) ?></strong><br><span style="color:#64748b"><?= e($e['email'] ?? 'staff@360management.com') ?></span></td>
                                        <td><?= e($e['department']) ?></td>
                                        <td><?= e($e['designation']) ?></td>
                                        <td><span class="hr-pill hr-ok"><?= e($e['status'] ?? 'Active') ?></span></td>
                                        <td><?= !empty($e['has_dashboard_access']) ? '<span class="hr-pill hr-purple">' . e($e['dashboard_label'] ?? 'Beverage ERP') . '</span>' : '<span class="hr-pill hr-warn">No Access</span>' ?></td>
                                        <td>
                                            <?php if (!empty($e['has_dashboard_access'])): ?>
                                                <a href="<?= url($e['dashboard_url'] ?? 'beverage_warehouse.php') ?>" class="hr-btn" style="padding:0.35rem 0.55rem;margin-right:0.3rem">Open</a>
                                            <?php endif; ?>
                                            <form method="post" action="<?= url('dashboard.php?tab=tab-hr-payroll') ?>" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="form_action" value="toggle_staff_access">
                                                <input type="hidden" name="employee_id" value="<?= (int)$e['id'] ?>">
                                                <button type="submit" class="hr-btn" style="padding:0.35rem 0.55rem"><?= !empty($e['has_dashboard_access']) ? 'Revoke' : 'Grant' ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="hrEmployeeEmptyRow" style="display:none"><td colspan="7"><div class="hr-empty-row" style="display:block">No employees match this search/filter.</div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="hr-panel<?= in_array($hrView, ['dashboard', 'payroll'], true) ? '' : ' hr-page-hidden' ?>" id="hr-payroll">
                    <div class="hr-panel-head"><h3>Payroll Snapshot</h3><span><?= date('M Y') ?></span></div>
                    <div class="hr-list">
                        <div class="hr-list-row"><strong>Basic Pay</strong><span>₦<?= number_format(max(0, $hrGrossPayroll - $hrTotalAllowances), 0) ?></span></div>
                        <div class="hr-list-row"><strong>Allowances</strong><span>₦<?= number_format($hrTotalAllowances, 0) ?></span></div>
                        <div class="hr-list-row"><strong>Gross Pay</strong><span class="hr-pill hr-purple">₦<?= number_format($hrGrossPayroll, 0) ?></span></div>
                        <div class="hr-list-row"><strong>Total Deductions</strong><span class="hr-pill hr-bad">₦<?= number_format($hrStatutoryDeductions, 0) ?></span></div>
                        <div class="hr-list-row"><strong>Net Pay</strong><span class="hr-pill hr-ok">₦<?= number_format($hrNetPayroll, 0) ?></span></div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<?php elseif ($activeTab === 'tab-geo-attendance'): ?>
<!-- =================================================================
     MODULE 6: GEO-FENCING AND ATTENDANCE MANAGEMENT
     ================================================================= -->
<div class="tab-module-wrap">
    <div class="tab-module-title"><span>📍</span> Geo-Fencing &amp; GPS Attendance Management</div>
    <div class="tab-module-desc">GPS-based clock-in/out using Haversine formula distance calculation against branch location radius, selfie photograph &amp; device verification.</div>

    <div class="mod-kpi-grid">
        <div class="mod-kpi-card">
            <small>Authorized Branch Radius</small>
            <strong>250 Meters</strong>
            <span>Victoria Island Depot Geo-Fence</span>
        </div>
        <div class="mod-kpi-card">
            <small>Today's Attendance Rate</small>
            <strong>96.4%</strong>
            <span>Verified inside Geo-Fence</span>
        </div>
        <div class="mod-kpi-card">
            <small>Late Arrivals</small>
            <strong>2 Staff</strong>
            <span>Flagged by System</span>
        </div>
        <div class="mod-kpi-card">
            <small>Unauthorized Radius Attempts</small>
            <strong>0 Flagged</strong>
            <span class="badge-status badge-green">Clean Audit</span>
        </div>
    </div>

    <div class="form-box">
        <h4 style="margin:0 0 1rem 0;color:#0f172a">Attendance Terminal</h4>
        <form method="post" action="<?= url('dashboard.php?tab=tab-geo-attendance') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="form_action" value="geo_clock_in">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee Name</label>
                    <input type="text" name="employee_name" required>
                </div>
                <div class="form-group">
                    <label>Employee Code</label>
                    <input type="text" name="employee_code" required>
                </div>
                <div class="form-group">
                    <label>Attendance Method</label>
                    <select name="attendance_method"><option value="pin">Employee PIN</option><option value="qr">QR Code</option><option value="card">Employee Card</option><option value="nfc">NFC Ready</option><option value="biometric">Biometric Ready</option></select>
                </div>
                <div class="form-group">
                    <label>Branch</label>
                    <select name="branch_key"><option value="jacroxx">Jacroxx Warehouse</option><option value="ijaba">Ijaba Warehouse</option></select>
                </div>
                <div class="form-group">
                    <label>Device Latitude</label>
                    <input type="number" step="0.0001" name="lat" required>
                </div>
                <div class="form-group">
                    <label>Device Longitude</label>
                    <input type="number" step="0.0001" name="lng" required>
                </div>
                <div class="form-group">
                    <label>Device ID</label>
                    <input type="text" name="device_id" required>
                </div>
            </div>
            <button type="submit" class="btn-submit" style="background:#7c3aed">Submit GPS Clock-In →</button>
        </form>
    </div>

    <section class="hr-grid-3" style="grid-template-columns:1fr 1fr">
        <div class="form-box">
            <h4 style="margin:0 0 1rem 0;color:#0f172a">Clock Out</h4>
            <form method="post" action="<?= url('dashboard.php?tab=tab-geo-attendance') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="geo_clock_out">
                <div class="form-grid">
                    <div class="form-group"><label>Employee Code</label><input type="text" name="employee_code" required></div>
                    <div class="form-group"><label>Device ID</label><input type="text" name="device_id" required></div>
                </div>
                <button type="submit" class="btn-submit" style="background:#0f766e">Record Clock-Out</button>
            </form>
        </div>
        <div class="form-box">
            <h4 style="margin:0 0 1rem 0;color:#0f172a">Official Duty / Approved Absence</h4>
            <form method="post" action="<?= url('dashboard.php?tab=tab-geo-attendance') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="mark_hr_duty">
                <div class="form-grid">
                    <div class="form-group"><label>Employee Code</label><input type="text" name="employee_code" required></div>
                    <div class="form-group"><label>Status</label><select name="duty_status"><option>Official Duty</option><option>Delivery Assignment</option><option>Field Work</option><option>Approved Absence</option><option>Annual Leave</option><option>Sick Leave</option><option>Emergency Leave</option></select></div>
                    <div class="form-group" style="grid-column:1/-1"><label>Reason</label><input type="text" name="reason"></div>
                </div>
                <button type="submit" class="btn-submit" style="background:#2563eb">Save Attendance Exception</button>
            </form>
        </div>
    </section>

    <h4 style="margin:1.5rem 0 0.5rem 0;color:#0f172a">Live Attendance Audit Log</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Branch</th>
                <th>Clock In Time</th>
                <th>Clock Out</th>
                <th>GPS Distance</th>
                <th>Device ID</th>
                <th>Method</th>
                <th>Lateness</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendanceLogs as $a): ?>
                <tr>
                    <td><strong><?= e($a['employee_name']) ?></strong> (<?= e($a['employee_code']) ?>)</td>
                    <td><?= e($a['branch_name']) ?></td>
                    <td><?= e($a['clock_in']) ?></td>
                    <td><?= e((string)($a['clock_out'] ?? '—')) ?></td>
                    <td><strong><?= e((string)$a['distance_meters']) ?>m</strong></td>
                    <td><code><?= e($a['device_id']) ?></code></td>
                    <td><?= e((string)($a['attendance_method'] ?? 'pin')) ?></td>
                    <td><?= e($a['lateness']) ?></td>
                    <td><span class="badge-status badge-green"><?= e((string)($a['status'] ?? $a['geo_status'] ?? 'Working')) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($activeTab === 'tab-logistics'): ?>
<!-- =================================================================
     MODULE 10: LOGISTICS AND DELIVERY MANAGEMENT
     ================================================================= -->
<div class="logistics-shell">
    <div class="logistics-hero">
        <div>
            <h2><?= e($logisticsViewCopy[$logisticsView]['title']) ?></h2>
            <p><?= e($logisticsViewCopy[$logisticsView]['subtitle']) ?></p>
        </div>
        <span class="logistics-badge"><?= e($logisticsViewCopy[$logisticsView]['badge']) ?></span>
    </div>

    <div class="logistics-kpi">
        <div class="mod-kpi-card">
            <small>Delivery Notes Captured</small>
            <strong><?= number_format((int)$logisticsMetrics['delivery_notes']) ?></strong>
            <span>Beverage only</span>
        </div>
        <div class="mod-kpi-card">
            <small>Active Dispatches</small>
            <strong><?= number_format((int)$logisticsMetrics['active_dispatches']) ?></strong>
            <span>Awaiting POD / receiving</span>
        </div>
        <div class="mod-kpi-card">
            <small>POD Signed</small>
            <strong><?= number_format((int)$logisticsMetrics['pod_signed']) ?></strong>
            <span>Stamped and acknowledged</span>
        </div>
        <div class="mod-kpi-card">
            <small>Document Value</small>
            <strong>₦<?= number_format((float)$logisticsMetrics['value_in_transit'], 2) ?></strong>
            <span>Invoice-linked value</span>
        </div>
        <div class="mod-kpi-card">
            <small>Pending OTP / POD</small>
            <strong><?= number_format((int)$logisticsMetrics['pending_pod']) ?></strong>
            <span>Needs receiving action</span>
        </div>
        <div class="mod-kpi-card">
            <small>Document Exceptions</small>
            <strong><?= number_format((int)$logisticsMetrics['document_exceptions']) ?></strong>
            <span>Review before posting GRN</span>
        </div>
    </div>

    <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-bottom:1rem">
        <a href="<?= url('dashboard.php?tab=tab-logistics&view=dispatch&company=' . $companyContext) ?>" class="btn-small <?= $logisticsView === 'dispatch' ? 'btn-primary' : '' ?>" style="text-decoration:none">Dispatch</a>
        <a href="<?= url('dashboard.php?tab=tab-logistics&view=returns&company=' . $companyContext) ?>" class="btn-small <?= $logisticsView === 'returns' ? 'btn-primary' : '' ?>" style="text-decoration:none">Returns</a>
        <a href="<?= url('dashboard.php?tab=tab-logistics&view=pod&company=' . $companyContext) ?>" class="btn-small <?= $logisticsView === 'pod' ? 'btn-primary' : '' ?>" style="text-decoration:none">Delivery POD</a>
    </div>

    <div class="logistics-grid">
        <?php if ($logisticsView === 'dispatch'): ?>
        <div class="logistics-panel">
            <h3>Capture Delivery Note / Dispatch</h3>
            <p class="logistics-panel-sub">Use this for the same information shown on supplier delivery notes and invoices.</p>
            <form method="post" action="<?= url('dashboard.php?tab=tab-logistics&view=dispatch&company=' . $companyContext) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="create_dispatch">
                <input type="hidden" name="return_view" value="dispatch">
                <input type="hidden" name="division" value="<?= e($companyContext) ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Supplier / Depot</label>
                        <select name="supplier" required>
                            <?php foreach ($logisticsSupplierOptions as $supplierOption): ?>
                                <?php $supplierName = (string)($supplierOption['company_name'] ?? ''); ?>
                                <option value="<?= e($supplierName) ?>" <?= $supplierName === $logisticsDefaultSupplier ? 'selected' : '' ?>>
                                    <?= e($supplierName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="supplier_other" placeholder="Type another supplier name" style="margin-top:0.5rem">
                    </div>
                    <div class="form-group">
                        <label>Document Type</label>
                        <select name="document_type">
                            <option value="Delivery Note - Warehouse Copy">Delivery Note - Warehouse Copy</option>
                            <option value="Sales Invoice / Delivery Evidence">Sales Invoice / Delivery Evidence</option>
                            <option value="Invoice / Payment Advice">Invoice / Payment Advice</option>
                            <option value="Internal Dispatch Waybill">Internal Dispatch Waybill</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Delivery Number</label>
                        <input type="text" name="delivery_number" value="80271624">
                    </div>
                    <div class="form-group">
                        <label>Delivery Date</label>
                        <input type="text" name="delivery_date" value="<?= date('d.m.Y') ?>">
                    </div>
                    <div class="form-group">
                        <label>Invoice Number</label>
                        <input type="text" name="invoice_number" value="">
                    </div>
                    <div class="form-group">
                        <label>Sales / Order Number</label>
                        <input type="text" name="sales_order_number" value="1290455">
                    </div>
                    <div class="form-group">
                        <label>Customer Number</label>
                        <input type="text" name="customer_number" value="1004107">
                    </div>
                    <div class="form-group">
                        <label>Vehicle No.</label>
                        <input type="text" name="vehicle_no" value="AAB 225 XC">
                    </div>
                    <div class="form-group">
                        <label>Carrier</label>
                        <input type="text" name="carrier">
                    </div>
                    <div class="form-group">
                        <label>Driver / Receiver</label>
                        <select name="driver_employee_code" style="margin-bottom:0.5rem">
                            <option value="">Manual / supplier driver</option>
                            <?php foreach ($hrDrivers as $driverOption): ?>
                                <option value="<?= e((string)$driverOption['employee_code']) ?>">
                                    <?= e((string)$driverOption['employee_code']) ?> - <?= e((string)$driverOption['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="driver_name" required>
                    </div>
                    <div class="form-group">
                        <label>Ship To</label>
                        <input type="text" name="ship_to" required>
                    </div>
                    <div class="form-group">
                        <label>Destination</label>
                        <input type="text" name="destination" required>
                    </div>
                    <div class="form-group">
                        <label>Gross Weight KG</label>
                        <input type="number" step="0.01" name="gross_weight_kg">
                    </div>
                    <div class="form-group">
                        <label>Volume CL</label>
                        <input type="number" step="0.01" name="volume_cl" value="104300.00">
                    </div>
                    <div class="form-group">
                        <label>Document Value (₦)</label>
                        <input type="number" step="0.01" name="document_value" value="0.00">
                    </div>
                    <div class="form-group">
                        <label>OTP Code</label>
                        <input type="text" name="otp_code" value="841587">
                    </div>
                    <div class="form-group" style="grid-column:1 / -1">
                        <label>Item Summary</label>
                        <textarea name="items_summary" rows="3" required>Pepsi PET 60cl x 12, Supa Komando Energy PET 30cl x 12, Teem Bitterlemon PET 50cl x 12</textarea>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Capture Delivery Note →</button>
            </form>
        </div>
        <?php elseif ($logisticsView === 'returns'): ?>
        <div class="logistics-panel">
            <h3>Return / Exception Capture</h3>
            <p class="logistics-panel-sub">Use this when stock is returned, rejected, short-delivered or needs document review.</p>
            <form method="post" action="<?= url('dashboard.php?tab=tab-logistics&view=returns&company=' . $companyContext) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="create_dispatch">
                <input type="hidden" name="return_view" value="returns">
                <input type="hidden" name="division" value="<?= e($companyContext) ?>">
                <input type="hidden" name="document_type" value="Return / Delivery Exception">
                <input type="hidden" name="status" value="Return / Document Review">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Supplier / Depot</label>
                        <select name="supplier" required>
                            <?php foreach ($logisticsSupplierOptions as $supplierOption): ?>
                                <?php $supplierName = (string)($supplierOption['company_name'] ?? ''); ?>
                                <option value="<?= e($supplierName) ?>" <?= $supplierName === $logisticsDefaultSupplier ? 'selected' : '' ?>>
                                    <?= e($supplierName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="supplier_other" placeholder="Type another supplier name" style="margin-top:0.5rem">
                    </div>
                    <div class="form-group"><label>Return / Delivery Number</label><input type="text" name="delivery_number" required></div>
                    <div class="form-group"><label>Return Date</label><input type="text" name="delivery_date" value="<?= date('d.m.Y') ?>"></div>
                    <div class="form-group"><label>Invoice Number</label><input type="text" name="invoice_number"></div>
                    <div class="form-group"><label>Vehicle No.</label><input type="text" name="vehicle_no"></div>
                    <div class="form-group"><label>Driver / Receiver</label><input type="text" name="driver_name" required></div>
                    <div class="form-group"><label>Source / Customer</label><input type="text" name="ship_to" required></div>
                    <div class="form-group"><label>Warehouse Destination</label><input type="text" name="destination" required></div>
                    <div class="form-group"><label>Document Value (₦)</label><input type="number" step="0.01" name="document_value" value="0.00"></div>
                    <div class="form-group" style="grid-column:1 / -1">
                        <label>Returned Items / Issue Summary</label>
                        <textarea name="items_summary" rows="3" required placeholder="Example: damaged crates, rejected delivery, short delivery, expired stock"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Record Return / Exception →</button>
            </form>
        </div>
        <?php else: ?>
        <div class="logistics-panel">
            <h3>POD Control Desk</h3>
            <p class="logistics-panel-sub">Confirm only deliveries with verified receiving evidence, OTP, signature or warehouse stamp.</p>
            <div class="pod-checks">
                <span>Receiver name and signature checked</span>
                <span>Warehouse stamp confirmed</span>
                <span>OTP or delivery evidence verified</span>
                <span>Quantity exceptions reviewed</span>
                <span>Finance can reconcile delivered value</span>
            </div>
            <div class="mod-kpi-card" style="margin-top:1rem">
                <small>Waiting for POD</small>
                <strong><?= number_format(count($pendingDeliveries)) ?></strong>
                <span>Open delivery records</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="delivery-note-stack">
            <div class="logistics-panel">
                <h3>Document Intelligence Checklist</h3>
                <p class="logistics-panel-sub">The register follows the paper workflow from your delivery notes.</p>
                <div class="pod-checks">
                    <span>Supplier invoice / delivery no.</span>
                    <span>Order and customer reference</span>
                    <span>Sold-to and ship-to details</span>
                    <span>Carrier and vehicle number</span>
                    <span>Batch, quantity and unit</span>
                    <span>OTP / receiver signature</span>
                    <span>Warehouse and security stamps</span>
                    <span>Finance value match</span>
                </div>
            </div>

            <div class="logistics-panel">
                <h3>Synced Modules</h3>
                <p class="logistics-panel-sub">Delivery capture and POD confirmation now update these module queues.</p>
                <div class="pod-checks">
                    <span>Procurement PO / GRN queue</span>
                    <span>Warehouse receiving audit</span>
                    <span>Finance AP / GL posting</span>
                    <span>Payment reconciliation review</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.55rem;margin-top:0.85rem">
                    <a href="<?= url('dashboard.php?tab=tab-procurement&company=' . $companyContext) ?>" style="text-decoration:none;color:#1d4ed8;font-size:0.76rem;font-weight:800;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:0.55rem">Open Procurement</a>
                    <a href="<?= url('beverage_warehouse.php?tab=tab-inventory') ?>" style="text-decoration:none;color:#047857;font-size:0.76rem;font-weight:800;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:0.55rem">Open Warehouse</a>
                    <a href="<?= url('finance.php?tab=tab-ap-finance&company=' . $companyContext) ?>" style="text-decoration:none;color:#7c2d12;font-size:0.76rem;font-weight:800;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:0.55rem">Open Finance AP</a>
                    <a href="<?= url('dashboard.php?tab=tab-payment-recon&company=' . $companyContext) ?>" style="text-decoration:none;color:#4338ca;font-size:0.76rem;font-weight:800;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:0.55rem">Open Payment Recon</a>
                </div>
                <?php if (!empty($logisticsSyncEvents)): ?>
                    <div style="margin-top:0.85rem;display:grid;gap:0.45rem">
                        <?php foreach (array_slice($logisticsSyncEvents, 0, 3) as $sync): ?>
                            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:0.55rem;background:#f8fafc">
                                <strong style="display:block;color:#0f172a;font-size:0.76rem"><?= e((string)($sync['supplier'] ?? 'Supplier')) ?></strong>
                                <span style="display:block;color:#64748b;font-size:0.7rem"><?= e((string)($sync['event'] ?? 'sync')) ?> · <?= e((string)($sync['timestamp'] ?? '')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="logistics-panel">
                <h3><?= e($logisticsViewCopy[$logisticsView]['queue_title']) ?></h3>
                <p class="logistics-panel-sub">
                    <?= $logisticsView === 'pod' ? 'Open each delivery to confirm proof of delivery.' : ($logisticsView === 'returns' ? 'Return and exception documents needing warehouse review.' : 'All captured delivery and dispatch documents.') ?>
                </p>
            </div>

            <?php if (empty($visibleLogisticsDeliveries)): ?>
                <div class="logistics-panel">
                    <h3><?= e($logisticsViewCopy[$logisticsView]['queue_empty']) ?></h3>
                    <p class="logistics-panel-sub">New records will appear here automatically after they are captured.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($visibleLogisticsDeliveries as $d): ?>
                <details class="delivery-note-card" <?= !$d['pod_acknowledged'] ? 'open' : '' ?>>
                    <summary class="delivery-note-head delivery-note-summary">
                        <div>
                            <h3><?= e($d['supplier']) ?></h3>
                            <small>
                                <?= e($d['document_type']) ?> · <?= e($d['delivery_number'] ?: $d['waybill_number']) ?> ·
                                <?= e($d['ship_to'] ?: $d['destination']) ?>
                            </small>
                        </div>
                        <span class="badge-status <?= $d['pod_acknowledged'] ? 'badge-green' : (stripos((string)$d['status'], 'review') !== false ? 'badge-red' : 'badge-yellow') ?>"><?= e($d['status']) ?></span>
                    </summary>

                    <div class="delivery-note-body">
                        <div class="doc-field-grid">
                            <div class="doc-field"><span>Delivery No.</span><strong><?= e($d['delivery_number'] ?: $d['waybill_number']) ?></strong></div>
                            <div class="doc-field"><span>Delivery Date</span><strong><?= e($d['delivery_date'] ?: 'Pending') ?></strong></div>
                            <div class="doc-field"><span>Invoice No.</span><strong><?= e($d['invoice_number'] ?: 'Pending') ?></strong></div>
                            <div class="doc-field"><span>Order / Ref</span><strong><?= e($d['sales_order_number'] ?: $d['reference_number'] ?: 'Pending') ?></strong></div>
                            <div class="doc-field"><span>Customer No.</span><strong><?= e($d['customer_number'] ?: 'N/A') ?></strong></div>
                            <div class="doc-field"><span>Ship To</span><strong><?= e($d['ship_to'] ?: $d['destination']) ?></strong></div>
                            <div class="doc-field"><span>Carrier</span><strong><?= e($d['carrier']) ?></strong></div>
                            <div class="doc-field"><span>Vehicle</span><strong><?= e($d['vehicle_no'] ?: $d['vehicle']) ?></strong></div>
                            <div class="doc-field"><span>Driver</span><strong><?= e($d['driver_name'] ?: 'Pending') ?></strong></div>
                            <div class="doc-field"><span>Driver ID</span><strong><?= e((string)($d['driver_employee_code'] ?? 'Manual')) ?></strong></div>
                            <div class="doc-field"><span>Weight</span><strong><?= number_format((float)$d['gross_weight_kg'], 2) ?> KG</strong></div>
                            <div class="doc-field"><span>Volume</span><strong><?= number_format((float)$d['volume_cl'], 2) ?> CL</strong></div>
                            <div class="doc-field"><span>Value</span><strong>₦<?= number_format((float)$d['document_value'], 2) ?></strong></div>
                            <div class="doc-field"><span>OTP</span><strong><?= e($d['otp_code'] ?: 'Not required') ?></strong></div>
                        </div>

                        <table class="delivery-items-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Item Description</th>
                                    <th>Batch</th>
                                    <th>Qty</th>
                                    <th>Unit</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($d['items'] ?? []) as $item): ?>
                                    <tr>
                                        <td><strong><?= e((string)($item['material'] ?? '')) ?></strong></td>
                                        <td><?= e((string)($item['description'] ?? '')) ?></td>
                                        <td><?= e((string)($item['batch'] ?? '')) ?></td>
                                        <td><?= number_format((float)($item['quantity'] ?? 0)) ?></td>
                                        <td><?= e((string)($item['unit'] ?? '')) ?></td>
                                        <td>₦<?= number_format((float)($item['value'] ?? 0), 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="pod-checks">
                            <?php foreach (($d['checks'] ?? []) as $check): ?>
                                <span class="<?= stripos((string)$check, 'pending') !== false ? 'doc-warning' : '' ?>"><?= e((string)$check) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin:0.8rem 0 0;color:#64748b;font-size:0.8rem"><strong style="color:#334155">POD evidence:</strong> <?= e($d['pod_evidence_status']) ?></p>

                        <?php if (!$d['pod_acknowledged']): ?>
                            <form method="post" action="<?= url('dashboard.php?tab=tab-logistics&view=pod&company=' . $companyContext) ?>" style="margin-top:0.85rem">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="form_action" value="confirm_pod">
                                <input type="hidden" name="waybill_number" value="<?= e($d['waybill_number']) ?>">
                                <button type="submit" class="btn-submit" style="padding:0.45rem 0.8rem;font-size:0.78rem;background:#059669">Confirm Receiving / POD</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($activeTab === 'tab-maintenance'): ?>
<!-- =================================================================
     MODULE 12: MAINTENANCE MANAGEMENT
     ================================================================= -->
<div class="tab-module-wrap">
    <div class="tab-module-title"><span>🛠️</span> Preventive &amp; Corrective Maintenance Management</div>
    <div class="tab-module-desc">Asset register for warehouse equipment, generators, trucks, preventive maintenance schedules, corrective work order generation, and downtime logging.</div>

    <div class="form-box">
        <h4 style="margin:0 0 1rem 0;color:#0f172a">📋 Create Asset Maintenance Work Order</h4>
        <form method="post" action="<?= url('dashboard.php?tab=tab-maintenance') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="form_action" value="create_work_order">
            <div class="form-grid">
                <div class="form-group">
                    <label>Target Asset</label>
                    <select name="asset_code">
                        <option value="">Select real asset</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Asset Name</label>
                    <input type="text" name="asset_name" required>
                </div>
                <div class="form-group">
                    <label>Assigned Technician</label>
                    <input type="text" name="technician" required>
                </div>
                <div class="form-group">
                    <label>Maintenance Type</label>
                    <select name="type">
                        <option value="Preventive Maintenance">Preventive Maintenance</option>
                        <option value="Corrective Calibration">Corrective Calibration</option>
                        <option value="Emergency Repair">Emergency Repair</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estimated Cost (₦)</label>
                    <input type="number" step="0.01" name="cost_estimate" required>
                </div>
                <div class="form-group" style="grid-column: span 2">
                    <label>Fault / Task Description</label>
                    <input type="text" name="description" required>
                </div>
            </div>
            <button type="submit" class="btn-submit" style="background:#d97706">Issue Maintenance Work Order →</button>
        </form>
    </div>

    <h4 style="margin:1.5rem 0 0.5rem 0;color:#0f172a">Active Maintenance Work Orders</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>WO Code</th>
                <th>Asset</th>
                <th>Technician</th>
                <th>Type</th>
                <th>Description</th>
                <th>Cost Estimate</th>
                <th>Logged Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($workOrders as $w): ?>
                <tr>
                    <td><strong><?= e($w['wo_code']) ?></strong></td>
                    <td><?= e($w['asset_name']) ?></td>
                    <td><?= e($w['technician']) ?></td>
                    <td><span class="badge-status badge-blue"><?= e($w['type']) ?></span></td>
                    <td><?= e($w['description']) ?></td>
                    <td><strong>₦<?= number_format((float)$w['cost_estimate'], 2) ?></strong></td>
                    <td><?= e($w['logged_date']) ?></td>
                    <td><span class="badge-status badge-yellow"><?= e($w['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($activeTab === 'tab-procurement'): ?>
<!-- =================================================================
     MODULE 8 & 9: VENDOR AND PROCUREMENT MANAGEMENT
     ================================================================= -->
<div class="tab-module-wrap">
    <div class="tab-module-title"><span>🛒</span> Vendor &amp; Procurement Management (3-Way Matching)</div>
    <div class="tab-module-desc">Purchase requisitions, RFQs, vendor due-diligence checklists, and 3-way matching between PO <-> GRN <-> Supplier Invoice.</div>

    <div class="form-box">
        <h4 style="margin:0 0 1rem 0;color:#0f172a">📝 Create Purchase Requisition (PO)</h4>
        <form method="post" action="<?= url('dashboard.php?tab=tab-procurement') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="form_action" value="create_procurement_po">
            <div class="form-grid">
                <div class="form-group">
                    <label>Vendor / Supplier</label>
                    <input type="text" name="vendor_name" required>
                </div>
                <div class="form-group">
                    <label>Items Package Description</label>
                    <input type="text" name="items" required>
                </div>
                <div class="form-group">
                    <label>Total Order Amount (₦)</label>
                    <input type="number" step="0.01" name="total_amount" required>
                </div>
            </div>
            <button type="submit" class="btn-submit">Generate Purchase Order →</button>
        </form>
    </div>

    <h4 style="margin:1.5rem 0 0.5rem 0;color:#0f172a">Purchase Orders &amp; 3-Way Reconciliation</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>PO Number</th>
                <th>Vendor</th>
                <th>Order Date</th>
                <th>Items Package</th>
                <th>Total Amount</th>
                <th>3-Way Match Status</th>
                <th>Order Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($purchaseOrders as $po): ?>
                <tr>
                    <td><strong><?= e($po['po_number']) ?></strong></td>
                    <td><?= e($po['vendor_name']) ?></td>
                    <td><?= e($po['order_date']) ?></td>
                    <td><?= e($po['items']) ?></td>
                    <td><strong>₦<?= number_format((float)$po['total_amount'], 2) ?></strong></td>
                    <td><span class="badge-status badge-green"><?= e($po['matching_status']) ?></span></td>
                    <td><span class="badge-status badge-blue"><?= e($po['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($activeTab === 'tab-payment-recon'): ?>
<!-- =================================================================
     MODULE 7: AUTOMATED PAYMENT ALERT COLLECTION
     ================================================================= -->
<div class="tab-module-wrap">
    <div class="tab-module-title"><span>🏦</span> Automated Bank Payment Alert Recon Engine</div>
    <div class="tab-module-desc">Parses bank credit alerts from email (IMAP) and statement files (CSV/PDF), matches payments against invoices, flags duplicates, and routes unmatched alerts for manual review.</div>

    <div class="recon-mail-grid">
        <div class="form-box">
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem">
                <div>
                    <h4 style="margin:0;color:#0f172a">📬 Specific Bank Mail Integration</h4>
                    <p style="margin:0.35rem 0 0;color:#64748b;font-size:0.85rem">Connect a dedicated mailbox to read credit alerts from one selected bank and post them into payment reconciliation.</p>
                </div>
                <span class="badge-status <?= !empty($mailConnector['enabled']) ? 'badge-green' : 'badge-yellow' ?>"><?= e((string)$mailConnector['status']) ?></span>
            </div>
            <form method="post" action="<?= url('dashboard.php?tab=tab-payment-recon') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="save_mail_connector">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Bank To Monitor</label>
                        <select name="bank_name" id="paymentBankSelect" required>
                            <?php foreach ($bankProfiles as $bankName => $profile): ?>
                                <option value="<?= e($bankName) ?>" data-sender="<?= e($profile['sender']) ?>" <?= ($mailConnector['bank_name'] ?? '') === $bankName ? 'selected' : '' ?>><?= e($bankName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Alert Mailbox</label>
                        <input type="email" name="mailbox_email" value="<?= e((string)$mailConnector['mailbox_email']) ?>" placeholder="bankalerts@empresstee.com" required>
                    </div>
                    <div class="form-group">
                        <label>IMAP Host</label>
                        <input type="text" name="imap_host" value="<?= e((string)$mailConnector['imap_host']) ?>" placeholder="imap.gmail.com" required>
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="number" name="imap_port" value="<?= e((string)$mailConnector['imap_port']) ?>" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="imap_encryption">
                            <option value="ssl" <?= ($mailConnector['imap_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="tls" <?= ($mailConnector['imap_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="none" <?= ($mailConnector['imap_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bank Sender Email</label>
                        <input type="email" name="bank_sender" id="paymentBankSender" value="<?= e((string)$mailConnector['bank_sender']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Mailbox App Password / OAuth Token</label>
                        <input type="password" name="imap_secret" placeholder="<?= !empty($mailConnector['has_secret']) ? 'Secret saved outside visible form' : 'Use secure app password or OAuth token' ?>">
                    </div>
                    <div class="form-group">
                        <label>Auto Scan</label>
                        <label style="display:flex;align-items:center;gap:0.5rem;background:#ffffff;border:1px solid #dbe4f0;border-radius:8px;padding:0.58rem 0.75rem">
                            <input type="checkbox" name="enabled" value="1" <?= !empty($mailConnector['enabled']) ? 'checked' : '' ?>>
                            Read new credit alerts from this bank
                        </label>
                    </div>
                </div>
                <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
                    <button type="submit" class="btn-submit">Save Mail Connector</button>
                    <span style="font-size:0.82rem;color:#64748b">Last sync: <?= e((string)$mailConnector['last_sync']) ?></span>
                </div>
            </form>
            <form method="post" action="<?= url('dashboard.php?tab=tab-payment-recon') ?>" style="margin-top:0.75rem">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="sync_mail_alerts">
                <button type="submit" class="btn-submit" style="background:#059669">Sync Mail Alerts Now →</button>
            </form>
        </div>

        <div class="form-box">
            <h4 style="margin:0 0 1rem 0;color:#0f172a">📧 Manual Bank Alert Parser</h4>
            <form method="post" action="<?= url('dashboard.php?tab=tab-payment-recon') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="parse_bank_alert">
                <div class="form-group">
                    <label>Bank</label>
                    <select name="bank_name">
                        <?php foreach ($bankProfiles as $bankName => $profile): ?>
                            <option value="<?= e($bankName) ?>" <?= ($mailConnector['bank_name'] ?? '') === $bankName ? 'selected' : '' ?>><?= e($bankName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Paste Raw Bank Credit Alert Email / SMS</label>
                    <textarea name="raw_payload" rows="7" required>GTBank Credit Alert: Acct 012399481. Amt: NGN 1,450,000.00. From: ALHAJI UMAR & SONS LTD. Ref: GTB-CR-20260809-8842. Date: 09-Aug-2026 09:24 AM.</textarea>
                </div>
                <button type="submit" class="btn-submit" style="margin-top:0.75rem;background:#0f172a">Parse &amp; Reconcile →</button>
            </form>
        </div>
    </div>

    <h4 style="margin:1.5rem 0 0.5rem 0;color:#0f172a">Parsed Bank Alerts &amp; Audit Trail</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Source</th>
                <th>Bank</th>
                <th>Sender Name</th>
                <th>Transaction Ref</th>
                <th>Amount</th>
                <th>Alert Date</th>
                <th>Matched Invoice</th>
                <th>Recon Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bankAlerts as $ba): ?>
                <tr>
                    <td><small><?= e($ba['source']) ?></small></td>
                    <td><?= e($ba['bank_name']) ?></td>
                    <td><strong><?= e($ba['sender_name']) ?></strong></td>
                    <td><code><?= e($ba['transaction_ref']) ?></code></td>
                    <td><strong>₦<?= number_format((float)$ba['amount'], 2) ?></strong></td>
                    <td><?= e($ba['alert_date']) ?></td>
                    <td><?= e($ba['matched_to']) ?></td>
                    <td><span class="badge-status <?= str_contains($ba['status'], 'Matched') ? 'badge-green' : 'badge-yellow' ?>"><?= e($ba['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($activeTab === 'tab-central-ai'): ?>
<!-- =================================================================
     MODULE 16 & 1: CENTRAL ERP AI ASSISTANT CONSOLE
     ================================================================= -->
	<div class="tab-module-wrap">
	    <div class="tab-module-title"><span>🤖</span> Central ERP AI Assistant Console</div>
	    <div class="tab-module-desc">Natural language AI interface plus live watch/training supervision across Sales, Inventory, HR, Payroll, Geo-Fence, Logistics, Maintenance, Finance and Payment Recon.</div>

	    <div style="background:#ffffff;border:1px solid #dbe4f0;border-radius:12px;padding:1rem;margin-bottom:1.5rem;box-shadow:0 10px 28px rgba(15,23,42,.04)">
	        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
	            <div>
	                <h3 style="margin:0;color:#0f172a;font-size:1.05rem">AI Watch &amp; Training Supervisor</h3>
	                <p style="margin:0.3rem 0 0;color:#64748b;font-size:0.86rem"><?= e($aiSupervisorBrief['summary']) ?></p>
	            </div>
	            <span class="badge-status <?= $aiSupervisorBrief['score'] >= 90 ? 'badge-green' : ($aiSupervisorBrief['score'] >= 80 ? 'badge-yellow' : 'badge-red') ?>"><?= e((string)$aiSupervisorBrief['score']) ?>% · <?= e($aiSupervisorBrief['status']) ?></span>
	        </div>
	        <div style="display:grid;grid-template-columns:1.15fr 0.85fr;gap:1rem">
	            <div style="display:grid;gap:0.65rem">
	                <h4 style="margin:0;color:#0f172a;font-size:0.9rem">Live Watch Alerts</h4>
	                <?php foreach (array_slice($aiSupervisorBrief['watch_alerts'], 0, 4) as $alert): ?>
	                    <div style="display:grid;grid-template-columns:auto 1fr;gap:0.7rem;align-items:flex-start;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.8rem">
	                        <span class="badge-status <?= ($alert['severity'] ?? '') === 'critical' ? 'badge-red' : (($alert['severity'] ?? '') === 'warning' ? 'badge-yellow' : 'badge-green') ?>"><?= e(strtoupper((string)$alert['severity'])) ?></span>
	                        <div>
	                            <strong style="display:block;color:#0f172a;font-size:0.86rem"><?= e($alert['title']) ?> · <?= e($alert['module']) ?></strong>
	                            <span style="display:block;color:#64748b;font-size:0.78rem;margin-top:0.18rem"><?= e($alert['message']) ?></span>
	                            <span style="display:block;color:#1d4ed8;font-size:0.76rem;font-weight:800;margin-top:0.35rem"><?= e($alert['action']) ?></span>
	                        </div>
	                    </div>
	                <?php endforeach; ?>
	            </div>
	            <div style="display:grid;gap:0.65rem">
	                <h4 style="margin:0;color:#0f172a;font-size:0.9rem">Training Prompts</h4>
	                <?php foreach (array_slice($aiSupervisorBrief['training_tasks'], 0, 4) as $task): ?>
	                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.8rem">
	                        <strong style="display:block;color:#0f172a;font-size:0.84rem"><?= e($task['module']) ?></strong>
	                        <span style="display:block;color:#334155;font-size:0.78rem;margin-top:0.25rem"><?= e($task['lesson']) ?></span>
	                        <span style="display:block;color:#64748b;font-size:0.72rem;margin-top:0.25rem"><?= e($task['why']) ?></span>
	                    </div>
	                <?php endforeach; ?>
	            </div>
	        </div>
	    </div>

	    <div class="form-box">
        <h4 style="margin:0 0 1rem 0;color:#0f172a">💬 Ask Central AI Assistant</h4>
        <form method="post" action="<?= url('dashboard.php?tab=tab-central-ai') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="form_action" value="ask_central_ai">
            <div style="display:flex;gap:0.75rem">
                <input type="text" name="ai_prompt" placeholder="e.g. Show today's sales, explain beverage stock status, prepare payroll summary..." value="<?= e($_POST['ai_prompt'] ?? '') ?>" required style="flex:1">
                <button type="submit" class="btn-submit" style="background:#2563eb">Query Central AI →</button>
            </div>
        </form>
    </div>

    <?php if (!empty($actionData) && is_string($actionData)): ?>
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:1.5rem;margin-top:1.5rem">
            <h4 style="margin:0 0 1rem 0;color:#2563eb">✨ Central AI Response</h4>
            <div style="line-height:1.7;color:#1e293b"><?= nl2br(e($actionData)) ?></div>
        </div>
    <?php endif; ?>
</div>

<?php elseif ($activeTab === 'tab-notifications'): ?>
<div class="tab-module-wrap">
    <div class="tab-module-title"><?= warehouse_admin_icon('bell') ?> Notification Center</div>
    <div class="tab-module-desc">Operational alerts from stock, FEFO, payment reconciliation, and system events.</div>

    <div class="mod-kpi-grid">
        <div class="mod-kpi-card"><small>Unread Alerts</small><strong><?= number_format(NotificationService::unreadCount($companyContext)) ?></strong><span>Live operational count</span></div>
        <div class="mod-kpi-card"><small>Total Notifications</small><strong><?= number_format(count($notifications)) ?></strong><span>Current inbox</span></div>
        <div class="mod-kpi-card"><small>Mail Connector</small><strong><?= e(ucwords(str_replace('_', ' ', (string)($mailConnector['status'] ?? 'not_connected')))) ?></strong><span><?= e((string)($mailConnector['mailbox_email'] ?? 'No mailbox')) ?></span></div>
        <div class="mod-kpi-card"><small>Outbound Mail</small><strong><?= e((string)$mailDelivery['status']) ?></strong><span><?= e((string)($mailDelivery['from'] ?: 'MAIL_FROM not set')) ?></span></div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0, 1fr);gap:0.85rem">
        <?php if (empty($notifications)): ?>
            <div style="border:1px solid #e2e8f0;border-radius:10px;padding:1rem;color:#64748b;background:#f8fafc">No active notifications right now.</div>
        <?php endif; ?>
        <?php foreach ($notifications as $notice): ?>
            <?php
            $type = (string)($notice['type'] ?? 'info');
            $color = $type === 'danger' ? '#dc2626' : ($type === 'warning' ? '#f97316' : '#2563eb');
            $href = (string)($notice['url'] ?? '');
            ?>
            <a href="<?= e($href !== '' ? url($href) : url('dashboard.php?tab=tab-notifications&company=beverage')) ?>" style="display:flex;align-items:flex-start;gap:0.85rem;text-decoration:none;color:#0f172a;border:1px solid #e2e8f0;border-radius:10px;padding:1rem;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,0.04)">
                <span style="width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:<?= e($color) ?>;background:<?= e($color) ?>14;flex:0 0 auto"><?= warehouse_admin_icon($type === 'danger' ? 'warning' : 'bell') ?></span>
                <span style="display:block;flex:1">
                    <strong style="display:block;font-size:0.95rem"><?= e((string)($notice['title'] ?? 'Notification')) ?></strong>
                    <small style="display:block;color:#64748b;margin-top:0.2rem"><?= e((string)($notice['message'] ?? '')) ?></small>
                </span>
                <small style="color:#94a3b8;white-space:nowrap"><?= e((string)($notice['created_at'] ?? '')) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($activeTab === 'tab-system-settings'): ?>
<div class="tab-module-wrap">
    <div class="tab-module-title"><?= warehouse_admin_icon('settings') ?> System Settings</div>
    <div class="tab-module-desc">Production credentials for outbound mail, payment alert mailbox scanning, and Google product image search.</div>

    <div class="mod-kpi-grid">
        <div class="mod-kpi-card"><small>Outbound Mail</small><strong><?= e((string)$mailDelivery['status']) ?></strong><span><?= e((string)($mailDelivery['transport'] ?? 'php_mail')) ?></span></div>
        <div class="mod-kpi-card"><small>SMTP From</small><strong style="font-size:1rem"><?= e((string)($mailDelivery['from'] ?: 'Not set')) ?></strong><span>Used for admin emails</span></div>
        <div class="mod-kpi-card"><small>Payment Mailbox</small><strong style="font-size:1rem"><?= e((string)($systemSettings['PAYMENT_RECON_IMAP_USER'] ?: 'Not set')) ?></strong><span>Bank alert reader</span></div>
        <div class="mod-kpi-card"><small>Google Images</small><strong><?= AppSettingsService::secretIsSaved('GOOGLE_IMAGE_SEARCH_API_KEY') && !empty($systemSettings['GOOGLE_IMAGE_SEARCH_CX']) ? 'Configured' : 'Not configured' ?></strong><span>Product image search</span></div>
    </div>

    <form method="post" action="<?= url('dashboard.php?tab=tab-system-settings&company=beverage') ?>" class="form-box">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="save_system_settings">

        <h4 style="margin:0 0 1rem 0;color:#0f172a">Mail Delivery</h4>
        <div class="form-grid">
            <div class="form-group">
                <label>Enable Outbound Mail</label>
                <label style="display:flex;align-items:center;gap:0.5rem;background:#ffffff;border:1px solid #dbe4f0;border-radius:8px;padding:0.58rem 0.75rem">
                    <input type="checkbox" name="MAIL_ENABLED" value="1" <?= ($systemSettings['MAIL_ENABLED'] ?? '') === '1' ? 'checked' : '' ?>>
                    Send login details and system emails
                </label>
            </div>
            <div class="form-group">
                <label>From Email</label>
                <input type="email" name="MAIL_FROM" value="<?= e((string)($systemSettings['MAIL_FROM'] ?? '')) ?>" placeholder="info@360management.name.ng">
            </div>
            <div class="form-group">
                <label>SMTP Host</label>
                <input type="text" name="MAIL_SMTP_HOST" value="<?= e((string)($systemSettings['MAIL_SMTP_HOST'] ?? '')) ?>" placeholder="mail.360management.name.ng">
            </div>
            <div class="form-group">
                <label>SMTP Port</label>
                <input type="number" name="MAIL_SMTP_PORT" min="1" value="<?= e((string)($systemSettings['MAIL_SMTP_PORT'] ?? '465')) ?>">
            </div>
            <div class="form-group">
                <label>SMTP Encryption</label>
                <select name="MAIL_SMTP_ENCRYPTION">
                    <?php foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($systemSettings['MAIL_SMTP_ENCRYPTION'] ?? 'ssl') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>SMTP Username</label>
                <input type="text" name="MAIL_SMTP_USER" value="<?= e((string)($systemSettings['MAIL_SMTP_USER'] ?? '')) ?>" placeholder="info@360management.name.ng">
            </div>
            <div class="form-group">
                <label>SMTP Password</label>
                <input type="password" name="MAIL_SMTP_PASS" placeholder="<?= AppSettingsService::secretIsSaved('MAIL_SMTP_PASS') ? 'Password saved - enter only to replace' : 'Enter SMTP password' ?>">
            </div>
        </div>

        <h4 style="margin:1.5rem 0 1rem 0;color:#0f172a">Payment Recon Mailbox</h4>
        <div class="form-grid">
            <div class="form-group">
                <label>IMAP Username</label>
                <input type="email" name="PAYMENT_RECON_IMAP_USER" value="<?= e((string)($systemSettings['PAYMENT_RECON_IMAP_USER'] ?? '')) ?>" placeholder="bankalerts@360management.name.ng">
            </div>
            <div class="form-group">
                <label>IMAP App Password / Token</label>
                <input type="password" name="PAYMENT_RECON_IMAP_SECRET" placeholder="<?= AppSettingsService::secretIsSaved('PAYMENT_RECON_IMAP_SECRET') ? 'Secret saved - enter only to replace' : 'Enter IMAP secret' ?>">
            </div>
        </div>

        <h4 style="margin:1.5rem 0 1rem 0;color:#0f172a">Google Product Image Search</h4>
        <div class="form-grid">
            <div class="form-group">
                <label>Google API Key</label>
                <input type="password" name="GOOGLE_IMAGE_SEARCH_API_KEY" placeholder="<?= AppSettingsService::secretIsSaved('GOOGLE_IMAGE_SEARCH_API_KEY') ? 'API key saved - enter only to replace' : 'Enter Google API key' ?>">
            </div>
            <div class="form-group">
                <label>Programmable Search Engine ID</label>
                <input type="text" name="GOOGLE_IMAGE_SEARCH_CX" value="<?= e((string)($systemSettings['GOOGLE_IMAGE_SEARCH_CX'] ?? '')) ?>" placeholder="Search engine CX">
            </div>
        </div>

        <h4 style="margin:1.5rem 0 1rem 0;color:#0f172a">Provn KYC Verification</h4>
        <div class="form-grid">
            <div class="form-group">
                <label>Provn Base URL</label>
                <input type="url" name="HR_KYC_PROVN_BASE_URL" value="<?= e((string)($systemSettings['HR_KYC_PROVN_BASE_URL'] ?? '')) ?>" placeholder="https://api.provn.example">
            </div>
            <div class="form-group">
                <label>Provn API Key</label>
                <input type="password" name="HR_KYC_PROVN_API_KEY" placeholder="<?= AppSettingsService::secretIsSaved('HR_KYC_PROVN_API_KEY') ? 'API key saved - enter only to replace' : 'Enter Provn API key' ?>">
            </div>
            <div class="form-group">
                <label>Provn API Secret</label>
                <input type="password" name="HR_KYC_PROVN_API_SECRET" placeholder="<?= AppSettingsService::secretIsSaved('HR_KYC_PROVN_API_SECRET') ? 'API secret saved - enter only to replace' : 'Enter Provn API secret' ?>">
            </div>
        </div>

        <h4 style="margin:1.5rem 0 1rem 0;color:#0f172a">Beverage Commercial Costing</h4>
        <div class="form-grid">
            <div class="form-group">
                <label>Direct Cost Per Pack/Crate (₦)</label>
                <input type="number" step="0.01" min="0" name="BEVERAGE_DIRECT_COST_PER_UNIT" value="<?= e((string)($systemSettings['BEVERAGE_DIRECT_COST_PER_UNIT'] ?? '0')) ?>" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Direct Cost Percent of Selling Price</label>
                <input type="number" step="0.001" min="0" name="BEVERAGE_DIRECT_COST_PERCENT" value="<?= e((string)($systemSettings['BEVERAGE_DIRECT_COST_PERCENT'] ?? '0')) ?>" placeholder="0">
            </div>
        </div>

        <h4 style="margin:1.5rem 0 1rem 0;color:#0f172a">Staff WhatsApp Room</h4>
        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
                <label>Official Staff WhatsApp Group Link</label>
                <input type="url" name="STAFF_WHATSAPP_GROUP_URL" value="<?= e((string)($systemSettings['STAFF_WHATSAPP_GROUP_URL'] ?? '')) ?>" placeholder="https://chat.whatsapp.com/...">
                <small style="display:block;margin-top:0.35rem;color:#64748b">Paste the official WhatsApp invite link for employed staff. Leave blank to hide the staff chat button.</small>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-top:1rem">
            <button type="submit" class="btn-submit">Save Production Settings</button>
            <small style="color:#64748b">Secret fields stay hidden after saving. Type a new value only when replacing them.</small>
        </div>
    </form>
</div>

<?php elseif ($activeTab === 'tab-health-score'): ?>
<!-- =================================================================
     ERP SYSTEM HEALTH SCORE ASSESSMENT
     ================================================================= -->
<div class="tab-module-wrap">
    <div class="tab-module-title"><span>🛡️</span> Overall ERP System Health Score</div>
    <div class="tab-module-desc">Real-time evaluation of organizational &amp; system health across 14 operational dimensions.</div>

    <div style="background:linear-gradient(135deg, #0f172a, #1e293b);color:#ffffff;border-radius:12px;padding:2rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between">
        <div>
            <span style="background:#10b981;color:#fff;padding:0.3rem 0.8rem;border-radius:20px;font-size:0.8rem;font-weight:700;letter-spacing:1px">SYSTEM HEALTH OK</span>
            <h1 style="font-size:3.5rem;font-weight:800;margin:0.5rem 0 0 0"><?= $healthOverview['overall_score'] ?>%</h1>
            <small style="color:#94a3b8;font-size:1rem"><?= $healthOverview['grade'] ?> — Last Assessed: <?= $healthOverview['last_assessed'] ?></small>
        </div>
        <div style="max-width:400px;text-align:right">
            <p style="color:#cbd5e1;font-size:0.95rem"><?= e($healthOverview['ai_summary']) ?></p>
        </div>
    </div>

    <h4 style="margin:1.5rem 0 0.5rem 0;color:#0f172a">14 Operational Health Category Scores</h4>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem">
        <?php foreach ($healthOverview['categories'] as $cat): ?>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:1rem;border-radius:10px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem">
                    <strong><?= $cat['icon'] ?> <?= e($cat['name']) ?></strong>
                    <span style="font-weight:800;color:#2563eb"><?= $cat['score'] ?>%</span>
                </div>
                <div style="background:#e2e8f0;height:8px;border-radius:4px;overflow:hidden">
                    <div style="background:#10b981;height:100%;width:<?= $cat['score'] ?>%"></div>
                </div>
                <small style="color:#64748b;margin-top:0.35rem;display:block">Status: <?= e($cat['status']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<!-- =================================================================
     EXECUTIVE CONTROL DASHBOARD (DEFAULT OVERVIEW)
     ================================================================= -->
<div style="margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between">
    <div>
        <h1 style="font-size:1.8rem;font-weight:800;color:#0f172a;margin:0">360Management Multi-Location Control Center</h1>
        <p style="color:#64748b;margin:0.25rem 0 0 0">Unified ERP operations across beverage depots.</p>
    </div>
    <div style="background:#f1f5f9;border:1px solid #cbd5e1;padding:0.5rem 1rem;border-radius:8px;font-weight:700;color:#334155">
        🛡️ Health Score: <span style="color:#059669"><?= $healthOverview['overall_score'] ?>% (A+)</span>
    </div>
</div>

<!-- Grid of Quick Shortcuts to active beverage modules -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1rem;margin-bottom:2rem">
    <a href="<?= url('beverage_warehouse.php?tab=tab-dash') ?>" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1.25rem;text-decoration:none;color:#14532d;display:block">
        <div style="font-size:1.8rem">📦</div>
        <strong style="display:block;font-size:1.1rem;margin-top:0.5rem">Beverage Depot ERP</strong>
        <small style="color:#16a34a">Multi-zone stock, crates, FEFO picking, GRN</small>
    </a>
    <a href="<?= url('finance.php?tab=tab-dash-finance') ?>" style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:1.25rem;text-decoration:none;color:#0f172a;display:block">
        <div style="font-size:1.8rem">🏛️</div>
        <strong style="display:block;font-size:1.1rem;margin-top:0.5rem">Finance &amp; Accounting</strong>
        <small style="color:#64748b">CoA, Journal Vouchers, AR/AP, P&amp;L, Balance Sheet</small>
    </a>
    <a href="<?= url('dashboard.php?tab=tab-logistics&view=dispatch') ?>" style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:12px;padding:1.25rem;text-decoration:none;color:#134e4a;display:block">
        <div style="font-size:1.8rem">🚚</div>
        <strong style="display:block;font-size:1.1rem;margin-top:0.5rem">Logistics &amp; POD</strong>
        <small style="color:#0d9488">Dispatches, route planning, Proof of Delivery</small>
    </a>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('hrEmployeeSearch');
    const filterButtons = document.querySelectorAll('[data-hr-filter]');
    const rows = Array.from(document.querySelectorAll('[data-hr-row]'));
    const countLabel = document.getElementById('hrEmployeeCount');
    const emptyRow = document.getElementById('hrEmployeeEmptyRow');
    let activeFilter = 'all';

    function applyHrEmployeeFilter() {
        if (!rows.length) return;
        const query = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        let shown = 0;

        rows.forEach(function (row) {
            const matchesSearch = !query || (row.dataset.search || '').includes(query);
            const matchesFilter = activeFilter === 'all'
                || row.dataset.access === activeFilter
                || row.dataset.payroll === activeFilter;
            const visible = matchesSearch && matchesFilter;
            row.style.display = visible ? '' : 'none';
            if (visible) shown += 1;
        });

        if (countLabel) {
            countLabel.textContent = shown + (shown === 1 ? ' employee shown' : ' employees shown');
        }
        if (emptyRow) {
            emptyRow.style.display = shown === 0 ? '' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyHrEmployeeFilter);
    }

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeFilter = button.dataset.hrFilter || 'all';
            filterButtons.forEach(function (item) { item.classList.remove('active'); });
            button.classList.add('active');
            applyHrEmployeeFilter();
        });
    });

    document.querySelectorAll('[data-open-details]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            const target = document.getElementById(trigger.dataset.openDetails);
            if (!target) return;
            event.preventDefault();
            target.open = true;
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
});
</script>

<?php
render_footer(false, false);
