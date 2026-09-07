<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/UnifiedDataEngine.php';
require_once __DIR__ . '/../app/Modules/Notifications/NotificationService.php';
require_once __DIR__ . '/../app/Modules/Notifications/MailDeliveryService.php';
require_once __DIR__ . '/../app/Modules/BeverageWarehouse/BeverageWarehouseService.php';
require_once __DIR__ . '/../app/Modules/PaymentRecon/PaymentReconService.php';

use App\Core\Database;
use App\Core\UnifiedDataEngine;
use App\Modules\BeverageWarehouse\BeverageWarehouseService;
use App\Modules\Notifications\NotificationService;
use App\Modules\Notifications\MailDeliveryService;
use App\Modules\PaymentRecon\PaymentReconService;

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Authentication required']);
    exit;
}

$companyId = (string)($user['company_id'] ?? 'beverage');
$isPos = canonical_role((string)($user['role'] ?? '')) === 'pos';
$products = BeverageWarehouseService::getProducts();
$sales = UnifiedDataEngine::getSalesHistory();
$productFeed = array_map(static function (array $product): array {
    return [
        'sku' => (string)($product['sku'] ?? ''),
        'name' => (string)($product['name'] ?? ''),
        'category' => (string)($product['category'] ?? ''),
        'image' => (string)($product['image'] ?? ''),
        'stock_crates' => (int)($product['stock_crates'] ?? $product['stock_qty'] ?? $product['quantity'] ?? 0),
        'stock_bottles' => (int)($product['stock_bottles'] ?? 0),
        'selling_price' => (float)($product['selling_price'] ?? $product['price_per_unit'] ?? 0),
        'cost_price' => (float)($product['cost_price'] ?? 0),
    ];
}, $products);
$stockCrates = array_sum(array_map(static fn(array $p): int => (int)($p['stock_crates'] ?? $p['quantity'] ?? 0), $products));
$lowStock = count(array_filter($products, static function (array $product): bool {
    $available = (int)($product['stock_crates'] ?? $product['quantity'] ?? 0);
    $reorder = (int)($product['reorder_level'] ?? 10);
    return $available <= $reorder;
}));

$payload = [
    'ok' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'db_connected' => Database::isConnected(),
    'version' => [
        'products' => md5(json_encode($productFeed)),
        'sales' => md5(json_encode(array_slice($sales, 0, 25))),
        'notifications' => md5(json_encode(NotificationService::getNotifications($companyId))),
    ],
    'notifications' => [
        'unread' => NotificationService::unreadCount($companyId),
        'items' => array_slice(NotificationService::getNotifications($companyId), 0, 10),
    ],
    'inventory' => [
        'sku_count' => count($products),
        'available_stock' => $stockCrates,
        'low_stock' => $lowStock,
        'products' => $productFeed,
    ],
    'sales' => [
        'count' => count($sales),
        'recent' => array_slice($sales, 0, 10),
    ],
];

if (!$isPos) {
    $paymentAlerts = PaymentReconService::getAlerts();
    $payload['payment_recon'] = [
        'pending_alerts' => count(array_filter($paymentAlerts, static fn(array $alert): bool => strtolower((string)($alert['status'] ?? '')) !== 'matched')),
        'recent_alerts' => array_slice($paymentAlerts, 0, 10),
        'mail_connector' => PaymentReconService::getMailConnectorConfig(),
        'mail_delivery' => MailDeliveryService::getStatus(),
    ];
    $payload['version']['payment_recon'] = md5(json_encode(array_slice($paymentAlerts, 0, 25)));
}

echo json_encode($payload);
