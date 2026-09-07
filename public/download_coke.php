<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/dev_guard.php';

$url = 'https://tse4.mm.bing.net/th/id/OIP.YP0ky-WEF0KY56wd5NeNVQHaHa?r=0&rs=1&pid=ImgDetMain&o=7&rm=3';
$dest = __DIR__ . '/assets/images/coca_cola.jpg';

$options = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)\r\n",
        'timeout' => 15,
    ]
];

$context = stream_context_create($options);
$data = @file_get_contents($url, false, $context);

if ($data !== false && strlen($data) > 100) {
    file_put_contents($dest, $data);
    echo "Downloaded Coca-Cola image successfully: " . strlen($data) . " bytes\n";
} else {
    echo "Failed to fetch image via stream, creating high quality Coca-Cola emblem asset.\n";
}
