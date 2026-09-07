<?php

declare(strict_types=1);

namespace App\Core;

class AppSettingsService
{
    private const SECRET_KEYS = [
        'MAIL_SMTP_PASS',
        'PAYMENT_RECON_IMAP_SECRET',
        'GOOGLE_IMAGE_SEARCH_API_KEY',
        'HR_KYC_PROVN_API_KEY',
        'HR_KYC_PROVN_API_SECRET',
        'BEVERAGE_DIRECT_COST_PER_UNIT',
        'BEVERAGE_DIRECT_COST_PERCENT',
        'STAFF_WHATSAPP_GROUP_URL',
    ];

    private const ALLOWED_KEYS = [
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
        'BEVERAGE_DIRECT_COST_PER_UNIT',
        'BEVERAGE_DIRECT_COST_PERCENT',
        'STAFF_WHATSAPP_GROUP_URL',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (!in_array($key, self::ALLOWED_KEYS, true)) {
            return $default;
        }

        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return $default;
            }

            self::ensureTables();
            $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();

            return $value === false ? $default : (string)$value;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function all(): array
    {
        $values = array_fill_keys(self::ALLOWED_KEYS, '');

        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return $values;
            }

            self::ensureTables();
            $rows = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll() ?: [];
            foreach ($rows as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if (in_array($key, self::ALLOWED_KEYS, true)) {
                    $values[$key] = (string)($row['setting_value'] ?? '');
                }
            }
        } catch (\Throwable) {
            return $values;
        }

        return $values;
    }

    public static function save(array $data, string $userName = 'Admin'): array
    {
        $current = self::all();
        $updates = [
            'MAIL_ENABLED' => !empty($data['MAIL_ENABLED']) ? '1' : '0',
            'MAIL_FROM' => trim((string)($data['MAIL_FROM'] ?? '')),
            'MAIL_SMTP_HOST' => trim((string)($data['MAIL_SMTP_HOST'] ?? '')),
            'MAIL_SMTP_PORT' => (string)max(1, (int)($data['MAIL_SMTP_PORT'] ?? 465)),
            'MAIL_SMTP_ENCRYPTION' => in_array(($data['MAIL_SMTP_ENCRYPTION'] ?? 'ssl'), ['ssl', 'tls', 'none'], true) ? (string)$data['MAIL_SMTP_ENCRYPTION'] : 'ssl',
            'MAIL_SMTP_USER' => trim((string)($data['MAIL_SMTP_USER'] ?? '')),
            'PAYMENT_RECON_IMAP_USER' => trim((string)($data['PAYMENT_RECON_IMAP_USER'] ?? '')),
            'GOOGLE_IMAGE_SEARCH_CX' => trim((string)($data['GOOGLE_IMAGE_SEARCH_CX'] ?? '')),
            'HR_KYC_PROVN_BASE_URL' => trim((string)($data['HR_KYC_PROVN_BASE_URL'] ?? ($current['HR_KYC_PROVN_BASE_URL'] ?? ''))),
            'BEVERAGE_DIRECT_COST_PER_UNIT' => (string)max(0.0, (float)($data['BEVERAGE_DIRECT_COST_PER_UNIT'] ?? ($current['BEVERAGE_DIRECT_COST_PER_UNIT'] ?? 0))),
            'BEVERAGE_DIRECT_COST_PERCENT' => (string)max(0.0, (float)($data['BEVERAGE_DIRECT_COST_PERCENT'] ?? ($current['BEVERAGE_DIRECT_COST_PERCENT'] ?? 0))),
            'STAFF_WHATSAPP_GROUP_URL' => self::sanitizeWhatsAppUrl((string)($data['STAFF_WHATSAPP_GROUP_URL'] ?? ($current['STAFF_WHATSAPP_GROUP_URL'] ?? ''))),
        ];

        foreach (['MAIL_SMTP_PASS', 'PAYMENT_RECON_IMAP_SECRET', 'GOOGLE_IMAGE_SEARCH_API_KEY', 'HR_KYC_PROVN_API_KEY', 'HR_KYC_PROVN_API_SECRET'] as $secretKey) {
            $value = trim((string)($data[$secretKey] ?? ''));
            if ($value !== '') {
                $updates[$secretKey] = $value;
            }
        }

        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return ['ok' => false, 'message' => 'Database unavailable. Settings were not saved.'];
            }

            self::ensureTables();
            $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value, is_secret, updated_by, updated_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
            $audit = $pdo->prepare('INSERT INTO app_settings_audit (setting_key, old_value, new_value, updated_by, created_at) VALUES (?, ?, ?, ?, ?)');
            $now = date('Y-m-d H:i:s');

            foreach ($updates as $key => $value) {
                $oldValue = $current[$key] ?? '';
                $isSecret = in_array($key, self::SECRET_KEYS, true);
                $stmt->execute([$key, $value, $isSecret ? 1 : 0, $userName, $now]);

                if ($oldValue !== $value) {
                    $audit->execute([
                        $key,
                        $isSecret && $oldValue !== '' ? '[secret saved]' : $oldValue,
                        $isSecret && $value !== '' ? '[secret saved]' : $value,
                        $userName,
                        $now,
                    ]);
                }
            }

            return ['ok' => true, 'message' => 'Production settings saved.'];
        } catch (\Throwable $t) {
            return ['ok' => false, 'message' => 'Settings could not be saved: ' . $t->getMessage()];
        }
    }

    public static function secretIsSaved(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true) && self::get($key, '') !== '';
    }

    private static function sanitizeWhatsAppUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($scheme, ['https'], true)) {
            return '';
        }

        $allowedHosts = ['chat.whatsapp.com', 'wa.me', 'api.whatsapp.com', 'www.whatsapp.com'];
        return in_array($host, $allowedHosts, true) ? $value : '';
    }

    public static function ensureTables(): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(120) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            is_secret TINYINT(1) NOT NULL DEFAULT 0,
            updated_by VARCHAR(120) DEFAULT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(120) NOT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            updated_by VARCHAR(120) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_app_settings_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
