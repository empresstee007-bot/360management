<?php

declare(strict_types=1);

namespace App\Modules\AiCore;

use App\Core\Database;
use App\Modules\HrPayroll\HrPayrollService;
use App\Modules\HrPayroll\GeoAttendanceService;
use App\Modules\Logistics\LogisticsService;
use App\Modules\Maintenance\MaintenanceService;
use App\Modules\Procurement\ProcurementService;
use App\Modules\PaymentRecon\PaymentReconService;

class CentralAiService
{
    public static function askCentralAi(string $prompt, string $companyId = 'beverage'): string
    {
        $p = strtolower(trim($prompt));
        $supervisorAnswer = AiSupervisorService::answerSupervisorPrompt($prompt, $companyId);
        if ($supervisorAnswer !== null) {
            return $supervisorAnswer;
        }

        if (str_contains($p, 'sales') || str_contains($p, 'revenue') || str_contains($p, 'today')) {
            return "📊 **360Management Central AI Analytics**: No real beverage sales have been recorded yet.\n\n" .
                "Add products, record POS sales, and complete cash drawer closing to build live analytics.";
        }

        if (str_contains($p, 'attendance') || str_contains($p, 'late') || str_contains($p, 'gps') || str_contains($p, 'clock')) {
            return "📍 **Geo-Attendance AI Auditor**: No real attendance logs have been captured yet.";
        }

        if (str_contains($p, 'payroll') || str_contains($p, 'salary') || str_contains($p, 'tax')) {
            return "💰 **HR & Payroll AI Advisor**: No real employee payroll data has been entered yet.";
        }

        if (str_contains($p, 'reorder') || str_contains($p, 'stock') || str_contains($p, 'inventory') || str_contains($p, 'crate')) {
            return "📦 **Inventory AI**: No real beverage inventory has been entered yet. Add products and stock levels to receive reorder guidance.";
        }

        if (str_contains($p, 'purchase') || str_contains($p, 'vendor') || str_contains($p, 'po')) {
            return "🛒 **Procurement AI Assistant**: No real vendors or purchase orders have been entered yet.";
        }

        if (str_contains($p, 'bank') || str_contains($p, 'alert') || str_contains($p, 'recon') || str_contains($p, 'payment')) {
            return "🏦 **Payment Recon AI**: No real bank alerts or transfer payments have been recorded yet.";
        }

        if (str_contains($p, 'health') || str_contains($p, 'score') || str_contains($p, 'system')) {
            $health = SystemHealthService::getHealthOverview();
            return "🛡️ **360Management System Health Score: {$health['overall_score']}% ({$health['grade']})**\n\n" .
                "{$health['ai_summary']}\n\n" .
                "Active modules (Sales, Inventory, HR, Payroll, Geo-Attendance, Logistics, Maintenance, Finance, Vendor, Procurement, Security) are synchronized across beverage operations.";
        }

        return "🤖 **360Management Central AI**: I am connected to the active Beverage ERP modules. I can assist you with sales forecasts, stock planning, payroll runs, GPS geo-fencing logs, procurement 3-way matching, and automated bank alert reconciliation.\n\nHow can I assist your operations today?";
    }
}
