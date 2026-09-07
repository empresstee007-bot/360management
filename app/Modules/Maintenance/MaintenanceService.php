<?php

declare(strict_types=1);

namespace App\Modules\Maintenance;

use App\Core\Database;

class MaintenanceService
{
    private static array $assetsMock = [];

    private static array $workOrdersMock = [];

    public static function getAssets(): array
    {
        return $_SESSION['maintenance_assets'] ?? self::$assetsMock;
    }

    public static function getWorkOrders(): array
    {
        return $_SESSION['maintenance_work_orders'] ?? self::$workOrdersMock;
    }

    public static function createWorkOrder(array $data): array
    {
        $woCode = 'WO-' . date('Y') . '-' . rand(100, 999);
        $assetCode = trim((string)($data['asset_code'] ?? ''));
        $assetName = trim((string)($data['asset_name'] ?? 'Maintenance Asset'));
        $technician = trim((string)($data['technician'] ?? 'Lead Technician'));
        $type = trim((string)($data['type'] ?? 'Preventive Maintenance'));
        $description = trim((string)($data['description'] ?? 'Work order details'));
        $cost = (float)($data['cost_estimate'] ?? 50000.0);

        $wo = [
            'wo_code' => $woCode,
            'asset_code' => $assetCode,
            'asset_name' => $assetName,
            'technician' => $technician,
            'type' => $type,
            'description' => $description,
            'cost_estimate' => $cost,
            'status' => 'Logged & Dispatched',
            'logged_date' => date('Y-m-d'),
        ];

        $orders = self::getWorkOrders();
        array_unshift($orders, $wo);
        $_SESSION['maintenance_work_orders'] = $orders;

        return $wo;
    }
}
