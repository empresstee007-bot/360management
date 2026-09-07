<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $appEnvironment = strtolower((string)(getenv('APP_ENV') ?: 'local'));
    $sessionSecure = !in_array($appEnvironment, ['local', 'development', 'dev', 'test'], true)
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

load_env_file(dirname(__DIR__) . '/.env');

// PSR-4 Autoloader for App\ namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    static $resolving = false;
    $runtimeSettingKeys = [
        'MAIL_ENABLED',
        'MAIL_FROM',
        'MAIL_SMTP_HOST',
        'MAIL_SMTP_PORT',
        'MAIL_SMTP_ENCRYPTION',
        'MAIL_SMTP_USER',
        'MAIL_SMTP_PASS',
        'PAYMENT_RECON_IMAP_USER',
        'PAYMENT_RECON_IMAP_SECRET',
        'GOOGLE_IMAGE_SEARCH_API_KEY',
        'GOOGLE_IMAGE_SEARCH_CX',
        'HR_KYC_PROVN_BASE_URL',
        'HR_KYC_PROVN_API_KEY',
        'HR_KYC_PROVN_API_SECRET',
    ];

    if (!$resolving && in_array($key, $runtimeSettingKeys, true) && class_exists('App\\Core\\AppSettingsService')) {
        $resolving = true;
        try {
            $stored = \App\Core\AppSettingsService::get($key);
            if ($stored !== null && $stored !== '') {
                return $stored;
            }
        } catch (Throwable) {
            // Keep environment reads resilient during early bootstrap and migrations.
        } finally {
            $resolving = false;
        }
    }

    return $default;
}

function app_env(): string
{
    return strtolower((string) env_value('APP_ENV', 'local'));
}

function app_is_live(): bool
{
    return in_array(app_env(), ['production', 'prod', 'live'], true);
}

function app_requires_database(): bool
{
    $default = app_is_live() ? '1' : '0';
    $value = strtolower((string) env_value('APP_REQUIRE_DATABASE', $default));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function config(string $key, mixed $default = null): mixed
{
    $config = [
        'app_name' => env_value('APP_NAME', '360Management ERP'),
        'app_tagline' => env_value('APP_TAGLINE', 'Smart AI-Powered ERP for Beverage Depot Operations'),
        'base_url' => env_value('APP_BASE_URL', 'http://localhost/360Management/public'),
        'db_host' => env_value('DB_HOST', '127.0.0.1'),
        'db_name' => env_value('DB_NAME', '360management_db'),
        'db_user' => env_value('DB_USER', 'root'),
        'db_pass' => env_value('DB_PASS', ''),
        'db_port' => (int) env_value('DB_PORT', '3306'),
    ];

    return $config[$key] ?? $default;
}

function url(string $path = ''): string
{
    $baseUrl = rtrim(config('base_url'), '/');
    $path = ltrim($path, '/');
    return $path !== '' ? "{$baseUrl}/{$path}" : $baseUrl;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash_message'] = $message;
        return null;
    }
    $msg = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    return $msg;
}

function current_user(): ?array
{
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}

function require_authenticated_user(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function canonical_role(string $role): string
{
    $role = strtolower(trim($role));
    if (in_array($role, ['super_admin', 'system_admin', 'admin'], true)) {
        return 'admin';
    }
    if (in_array($role, ['branch_manager', 'operations_manager', 'manager'], true)) {
        return 'manager';
    }
    if (in_array($role, ['cashier', 'pos'], true)) {
        return 'pos';
    }
    return 'staff';
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function default_dashboard_path(array $user): string
{
    $role = canonical_role($user['role'] ?? '');

    if ($role === 'pos') {
        return 'beverage_pos.php';
    }
    if ($role === 'staff') {
        return 'employee_self_service.php';
    }

    return 'beverage_warehouse.php';
}

function beverage_product_image_src(?string $image): string
{
    $image = trim((string)$image);
    if ($image === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $image) || str_starts_with($image, 'data:') || str_starts_with($image, '/')) {
        return $image;
    }

    return url($image);
}

function beverage_product_image_fallback_src(): string
{
    return url('assets/images/beverage_pack_placeholder.svg');
}

// Auto-initialize Cross-Module Data Engine after helpers are available.
if (class_exists('App\Core\UnifiedDataEngine')) {
    \App\Core\UnifiedDataEngine::initSession();
}
