<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI === 'cli') {
    return;
}

$user = current_user();
$role = $user ? canonical_role((string)($user['role'] ?? '')) : '';
$allowBrowserUtility = !app_is_live()
    && $role === 'admin'
    && in_array(strtolower((string) env_value('APP_ALLOW_PUBLIC_UTILITIES', '0')), ['1', 'true', 'yes', 'on'], true);

if ($allowBrowserUtility) {
    return;
}

http_response_code($user ? 403 : 401);
exit($user ? 'Developer utility access is disabled.' : 'Authentication required.');
