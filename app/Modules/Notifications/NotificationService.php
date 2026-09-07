<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Core\Database;
use App\Modules\BeverageWarehouse\BeverageWarehouseService;
use App\Modules\HrPayroll\HrPayrollService;
use App\Modules\PaymentRecon\PaymentReconService;

class NotificationService
{
    public static function getNotifications(string $companyId = 'beverage'): array
    {
        $items = self::buildOperationalNotifications($companyId);
        $stored = self::getStoredNotifications($companyId);
        $manual = $_SESSION['app_notifications'][$companyId] ?? [];

        $items = array_merge($items, $stored, is_array($manual) ? $manual : []);
        usort($items, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

        return array_values($items);
    }

    public static function unreadCount(string $companyId = 'beverage'): int
    {
        return count(array_filter(self::getNotifications($companyId), static fn(array $item): bool => empty($item['read_at'])));
    }

    public static function push(string $title, string $message, string $type = 'info', string $companyId = 'beverage', ?string $url = null): void
    {
        $notice = [
            'id' => uniqid('ntf_', true),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null,
        ];

        if (self::storeNotification($companyId, $notice)) {
            return;
        }

        $_SESSION['app_notifications'][$companyId][] = $notice;
    }

    private static function getStoredNotifications(string $companyId): array
    {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return [];
            }

            self::ensureTables();
            $stmt = $pdo->prepare("SELECT notification_id AS id, type, title, message, url, created_at, read_at FROM app_notifications WHERE company_id = ? ORDER BY created_at DESC LIMIT 100");
            $stmt->execute([$companyId]);

            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function storeNotification(string $companyId, array $notice): bool
    {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return false;
            }

            self::ensureTables();
            $stmt = $pdo->prepare("INSERT INTO app_notifications (notification_id, company_id, type, title, message, url, created_at, read_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $notice['id'],
                $companyId,
                $notice['type'],
                $notice['title'],
                $notice['message'],
                $notice['url'],
                $notice['created_at'],
                $notice['read_at'],
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function ensureTables(): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS app_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id VARCHAR(80) NOT NULL UNIQUE,
            company_id VARCHAR(50) NOT NULL DEFAULT 'beverage',
            type VARCHAR(40) NOT NULL DEFAULT 'info',
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            url VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME DEFAULT NULL,
            INDEX idx_app_notifications_company_created (company_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function buildOperationalNotifications(string $companyId): array
    {
        $now = date('Y-m-d H:i:s');
        $notifications = [];

        if (class_exists(BeverageWarehouseService::class)) {
            $products = BeverageWarehouseService::getProducts();
            $lowStock = array_values(array_filter($products, static function (array $product): bool {
                $available = (int)($product['stock_crates'] ?? $product['quantity'] ?? 0);
                $reorder = (int)($product['reorder_level'] ?? 10);
                return $available <= $reorder;
            }));
            if ($lowStock) {
                $notifications[] = [
                    'id' => 'auto_low_stock',
                    'type' => 'warning',
                    'title' => 'Low stock needs attention',
                    'message' => count($lowStock) . ' product(s) are at or below reorder level.',
                    'url' => 'beverage_warehouse.php?tab=tab-inventory&detail=low-stock',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }

            $expiring = array_values(array_filter($products, static function (array $product): bool {
                $expiry = trim((string)($product['expiry_date'] ?? ''));
                if ($expiry === '' || strtotime($expiry) === false) {
                    return false;
                }
                $daysLeft = (int)floor((strtotime($expiry) - time()) / 86400);
                return $daysLeft <= 30;
            }));
            if ($expiring) {
                $notifications[] = [
                    'id' => 'auto_fefo_expiry',
                    'type' => 'danger',
                    'title' => 'FEFO expiry warning',
                    'message' => count($expiring) . ' product(s) expire within 30 days.',
                    'url' => 'beverage_warehouse.php?tab=tab-inventory&detail=fefo',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }
        }

        if (class_exists(PaymentReconService::class)) {
            $pendingAlerts = array_values(array_filter(PaymentReconService::getAlerts(), static function (array $alert): bool {
                return strtolower((string)($alert['status'] ?? '')) !== 'matched';
            }));
            if ($pendingAlerts) {
                $notifications[] = [
                    'id' => 'auto_payment_recon',
                    'type' => 'info',
                    'title' => 'Payment reconciliation pending',
                    'message' => count($pendingAlerts) . ' payment alert(s) still need matching.',
                    'url' => 'dashboard.php?tab=tab-payment-recon&company=beverage',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }
        }

        if (class_exists(HrPayrollService::class)) {
            $metrics = HrPayrollService::getDashboardMetrics();
            if ((int)($metrics['pending_kyc'] ?? 0) > 0) {
                $notifications[] = [
                    'id' => 'auto_hr_pending_kyc',
                    'type' => 'warning',
                    'title' => 'Employee KYC pending',
                    'message' => (int)$metrics['pending_kyc'] . ' employee(s) still need identity verification.',
                    'url' => 'dashboard.php?tab=tab-hr-payroll&view=kyc&company=beverage',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }

            if ((int)($metrics['pending_payroll_approvals'] ?? 0) > 0) {
                $notifications[] = [
                    'id' => 'auto_hr_payroll_approval',
                    'type' => 'info',
                    'title' => 'Payroll awaiting approval',
                    'message' => (int)$metrics['pending_payroll_approvals'] . ' payroll run(s) are not yet paid or closed.',
                    'url' => 'dashboard.php?tab=tab-hr-payroll&view=payroll&company=beverage',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }

            if ((int)($metrics['absent_today'] ?? 0) > 0 || (int)($metrics['late_today'] ?? 0) > 0) {
                $notifications[] = [
                    'id' => 'auto_hr_attendance_exception',
                    'type' => 'warning',
                    'title' => 'Attendance exceptions today',
                    'message' => (int)$metrics['absent_today'] . ' absent and ' . (int)$metrics['late_today'] . ' late employee(s) recorded today.',
                    'url' => 'dashboard.php?tab=tab-geo-attendance&company=beverage',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }

            if ((int)($metrics['pending_damage_investigations'] ?? 0) > 0) {
                $notifications[] = [
                    'id' => 'auto_hr_damage_review',
                    'type' => 'danger',
                    'title' => 'Damage report awaiting review',
                    'message' => (int)$metrics['pending_damage_investigations'] . ' employee accountability case(s) need review.',
                    'url' => 'dashboard.php?tab=tab-hr-payroll&view=damage&company=beverage',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }

            $varianceSessions = array_values(array_filter(HrPayrollService::getCashierSessions(), static fn(array $session): bool => abs((float)($session['variance'] ?? 0)) > 0.01));
            if ($varianceSessions) {
                $notifications[] = [
                    'id' => 'auto_hr_cash_variance',
                    'type' => 'warning',
                    'title' => 'Cash drawer variance recorded',
                    'message' => count($varianceSessions) . ' cashier session(s) have cash variance.',
                    'url' => 'beverage_pos.php?tab=recon',
                    'created_at' => $now,
                    'read_at' => null,
                ];
            }
        }

        return $notifications;
    }
}
