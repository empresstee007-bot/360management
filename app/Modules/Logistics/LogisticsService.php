<?php

declare(strict_types=1);

namespace App\Modules\Logistics;

use App\Core\UnifiedDataEngine;

class LogisticsService
{
    private static array $deliveriesMock = [];

    public static function getDeliveries(string $division = 'beverage'): array
    {
        $deliveries = $_SESSION['logistics_deliveries'] ?? self::$deliveriesMock;
        $division = 'beverage';

        return array_values(array_filter(array_map([self::class, 'normalizeDelivery'], $deliveries), static function (array $delivery) use ($division): bool {
            return ($delivery['division'] ?? 'beverage') === $division;
        }));
    }

    public static function createDispatch(array $data): array
    {
        $waybill = 'WAY-' . date('Y') . '-' . rand(1000, 9999);
        $division = 'beverage';
        $typedSupplier = trim((string)($data['supplier_other'] ?? ''));
        $supplier = $typedSupplier !== ''
            ? $typedSupplier
            : trim((string)($data['supplier'] ?? ''));
        $vehicleNo = trim((string)($data['vehicle_no'] ?? ''));
        $vehicle = trim((string)($data['vehicle'] ?? $vehicleNo));
        $vehicleNo = $vehicleNo !== '' ? $vehicleNo : $vehicle;
        $driver = trim((string)($data['driver_name'] ?? ''));
        $driverEmployeeCode = strtoupper(trim((string)($data['driver_employee_code'] ?? '')));
        $driverEmployee = null;
        if ($driverEmployeeCode !== '' && class_exists('App\Modules\HrPayroll\HrPayrollService')) {
            $driverEmployee = \App\Modules\HrPayroll\HrPayrollService::getEmployeeByCode($driverEmployeeCode);
            if ($driverEmployee && $driver === '') {
                $driver = (string)($driverEmployee['name'] ?? '');
            }
        }
        $destination = trim((string)($data['destination'] ?? ''));
        $items = trim((string)($data['items_summary'] ?? ''));
        $deliveryNumber = trim((string)($data['delivery_number'] ?? ''));
        $invoiceNumber = trim((string)($data['invoice_number'] ?? ''));
        $documentId = $deliveryNumber !== '' ? 'DN-' . preg_replace('/[^A-Z0-9-]+/i', '', strtoupper($deliveryNumber)) : $waybill;
        $documentType = trim((string)($data['document_type'] ?? 'Captured Delivery Note'));
        $requestedStatus = trim((string)($data['status'] ?? ''));
        $isReturnDocument = stripos($documentType, 'return') !== false || stripos($documentType, 'exception') !== false;

        $delivery = [
            'division' => $division,
            'document_type' => $documentType,
            'supplier' => $supplier,
            'waybill_number' => $documentId,
            'delivery_number' => $deliveryNumber,
            'delivery_date' => trim((string)($data['delivery_date'] ?? date('d.m.Y'))),
            'invoice_number' => $invoiceNumber,
            'invoice_date' => trim((string)($data['invoice_date'] ?? '')),
            'reference_number' => trim((string)($data['reference_number'] ?? '')),
            'sales_order_number' => trim((string)($data['sales_order_number'] ?? '')),
            'order_date' => trim((string)($data['order_date'] ?? '')),
            'customer_number' => trim((string)($data['customer_number'] ?? '')),
            'sold_to' => trim((string)($data['sold_to'] ?? '')),
            'ship_to' => trim((string)($data['ship_to'] ?? $destination)),
            'delivery_contact' => trim((string)($data['delivery_contact'] ?? '')),
            'carrier' => trim((string)($data['carrier'] ?? '')),
            'vehicle' => $vehicle,
            'vehicle_no' => $vehicleNo,
            'driver_name' => $driver,
            'driver_employee_code' => $driverEmployeeCode,
            'driver_license_number' => (string)($driverEmployee['driver_license_number'] ?? ''),
            'driver_license_expiry' => (string)($driverEmployee['driver_license_expiry'] ?? ''),
            'driver_assigned_vehicle' => (string)($driverEmployee['vehicle_assignment'] ?? ''),
            'destination' => $destination,
            'dispatch_time' => date('Y-m-d h:i A'),
            'items_summary' => $items,
            'shipping_conditions' => trim((string)($data['shipping_conditions'] ?? '')),
            'terms_of_delivery' => trim((string)($data['terms_of_delivery'] ?? '')),
            'gross_weight_kg' => (float)($data['gross_weight_kg'] ?? 0),
            'net_weight_kg' => (float)($data['net_weight_kg'] ?? 0),
            'volume_cl' => (float)($data['volume_cl'] ?? 0),
            'document_value' => (float)($data['document_value'] ?? 0),
            'currency' => 'NGN',
            'status' => $requestedStatus !== '' ? $requestedStatus : ($isReturnDocument ? 'Return / Document Review' : 'In Transit'),
            'pod_acknowledged' => false,
            'pod_evidence_status' => $isReturnDocument ? 'Return documents pending review and warehouse confirmation' : 'Awaiting receiving signature, stamp and OTP/POD confirmation',
            'otp_code' => trim((string)($data['otp_code'] ?? '')),
            'document_source' => 'Manual capture',
            'vehicle_operating_units' => 0,
            'mileage_km' => 0,
            'items' => [
                ['material' => 'MANUAL', 'description' => $items, 'batch' => '', 'quantity' => (float)($data['quantity'] ?? 0), 'unit' => trim((string)($data['unit'] ?? '')), 'unit_price' => 0, 'value' => (float)($data['document_value'] ?? 0)],
            ],
            'checks' => [
                $isReturnDocument ? 'Return / exception captured' : 'Delivery note captured',
                $driverEmployeeCode !== '' ? 'Driver employee linked' : 'Driver employee link pending',
                $invoiceNumber !== '' ? 'Invoice number linked' : 'Invoice number pending',
                $isReturnDocument ? 'Warehouse review pending' : 'POD confirmation pending',
            ],
        ];

        $deliveries = $_SESSION['logistics_deliveries'] ?? self::$deliveriesMock;
        array_unshift($deliveries, $delivery);
        $_SESSION['logistics_deliveries'] = $deliveries;

        if (class_exists(UnifiedDataEngine::class)) {
            UnifiedDataEngine::syncLogisticsDelivery($delivery, 'capture');
        }

        return $delivery;
    }

    public static function confirmProofOfDelivery(string $waybillNumber): bool
    {
        $deliveries = $_SESSION['logistics_deliveries'] ?? self::$deliveriesMock;
        foreach ($deliveries as &$d) {
            if ($d['waybill_number'] === $waybillNumber) {
                $d['status'] = 'Delivered & POD Signed';
                $d['pod_acknowledged'] = true;
                $d['pod_evidence_status'] = 'Receiving signature and POD confirmation completed';
                $_SESSION['logistics_deliveries'] = $deliveries;
                if (class_exists(UnifiedDataEngine::class)) {
                    UnifiedDataEngine::syncLogisticsDelivery($d, 'pod_confirmed');
                }
                return true;
            }
        }
        return false;
    }

    public static function getMetrics(string $division = 'beverage'): array
    {
        $deliveries = self::getDeliveries($division);
        $total = count($deliveries);
        $podSigned = count(array_filter($deliveries, static fn (array $d): bool => !empty($d['pod_acknowledged'])));
        $inTransit = count(array_filter($deliveries, static fn (array $d): bool => empty($d['pod_acknowledged'])));
        $value = array_sum(array_map(static fn (array $d): float => (float)($d['document_value'] ?? 0), $deliveries));
        $exceptions = count(array_filter($deliveries, static fn (array $d): bool => stripos((string)($d['status'] ?? ''), 'review') !== false || stripos((string)($d['pod_evidence_status'] ?? ''), 'pending') !== false));

        return [
            'delivery_notes' => $total,
            'active_dispatches' => $inTransit,
            'pod_signed' => $podSigned,
            'value_in_transit' => $value,
            'pending_pod' => $inTransit,
            'document_exceptions' => $exceptions,
        ];
    }

    private static function normalizeDelivery(array $delivery): array
    {
        $defaults = [
            'division' => 'beverage',
            'document_type' => 'Waybill Dispatch',
            'supplier' => 'Empress Tee',
            'delivery_number' => $delivery['waybill_number'] ?? '',
            'delivery_date' => '',
            'invoice_number' => '',
            'invoice_date' => '',
            'reference_number' => '',
            'sales_order_number' => '',
            'order_date' => '',
            'customer_number' => '',
            'sold_to' => '',
            'ship_to' => $delivery['destination'] ?? '',
            'delivery_contact' => '',
            'carrier' => 'Internal Fleet',
            'vehicle_no' => $delivery['vehicle'] ?? '',
            'shipping_conditions' => '',
            'terms_of_delivery' => '',
            'gross_weight_kg' => 0,
            'net_weight_kg' => 0,
            'volume_cl' => 0,
            'document_value' => 0,
            'currency' => 'NGN',
            'pod_evidence_status' => empty($delivery['pod_acknowledged']) ? 'POD pending' : 'POD captured',
            'otp_code' => '',
            'document_source' => 'Legacy waybill',
            'items' => [
                ['material' => 'LEGACY', 'description' => $delivery['items_summary'] ?? '', 'batch' => '', 'quantity' => 0, 'unit' => '', 'unit_price' => 0, 'value' => 0],
            ],
            'checks' => empty($delivery['pod_acknowledged']) ? ['POD confirmation pending'] : ['POD confirmed'],
        ];

        return array_merge($defaults, $delivery);
    }
}
