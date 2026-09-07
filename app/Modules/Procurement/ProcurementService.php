<?php

declare(strict_types=1);

namespace App\Modules\Procurement;

use App\Core\Database;

class ProcurementService
{
    private static array $vendorsMock = [];

    private static array $purchaseOrdersMock = [];

    public static function getVendors(): array
    {
        return $_SESSION['procurement_vendors'] ?? self::$vendorsMock;
    }

    public static function getPurchaseOrders(): array
    {
        return $_SESSION['procurement_orders'] ?? self::$purchaseOrdersMock;
    }

    public static function createRequisition(array $data): array
    {
        $poNumber = 'PO-' . date('Y') . '-' . rand(1000, 9999);
        $vendorName = trim((string)($data['vendor_name'] ?? ''));
        $items = trim((string)($data['items'] ?? ''));
        $totalAmount = (float)($data['total_amount'] ?? 0.0);

        $po = [
            'po_number' => $poNumber,
            'vendor_name' => $vendorName,
            'order_date' => date('Y-m-d'),
            'items' => $items,
            'total_amount' => $totalAmount,
            'matching_status' => 'Pending GRN & Supplier Invoice',
            'status' => 'Issued for Approval',
        ];

        $pos = self::getPurchaseOrders();
        array_unshift($pos, $po);
        $_SESSION['procurement_orders'] = $pos;

        return $po;
    }
}
