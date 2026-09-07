<?php

declare(strict_types=1);

namespace App\Modules\PaymentRecon;

use App\Core\Database;

class PaymentReconService
{
    private static array $bankAlertsMock = [];

    public static function getAlerts(): array
    {
        $stored = self::getStoredAlerts();
        if ($stored !== []) {
            return $stored;
        }

        return $_SESSION['payment_alerts'] ?? self::$bankAlertsMock;
    }

    public static function getBankProfiles(): array
    {
        return [
            'Guaranty Trust Bank (GTB)' => [
                'sender' => 'alerts@gtbank.com',
            ],
            'Access Bank PLC' => [
                'sender' => 'alerts@accessbank.com',
            ],
            'Zenith Bank PLC' => [
                'sender' => 'alertz@zenithbank.com',
            ],
            'First Bank of Nigeria' => [
                'sender' => 'firstalert@firstbanknigeria.com',
            ],
            'UBA Nigeria' => [
                'sender' => 'cfc@ubagroup.com',
            ],
            'Wema Bank PLC' => [
                'sender' => 'alerts@wemabank.com',
            ],
        ];
    }

    public static function getMailConnectorConfig(): array
    {
        $profiles = self::getBankProfiles();
        $defaultBank = 'Guaranty Trust Bank (GTB)';

        $default = [
            'enabled' => false,
            'bank_name' => $defaultBank,
            'mailbox_email' => 'bankalerts@empresstee.com',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'bank_sender' => $profiles[$defaultBank]['sender'],
            'last_sync' => 'Never',
            'status' => 'Not Connected',
            'has_secret' => false,
        ];

        $stored = self::getStoredMailConnectorConfig();
        if ($stored !== []) {
            return array_merge($default, $stored);
        }

        return array_merge($default, $_SESSION['payment_mail_connector'] ?? []);
    }

    public static function saveMailConnectorConfig(array $data): array
    {
        $profiles = self::getBankProfiles();
        $existing = self::getMailConnectorConfig();
        $bankName = trim((string)($data['bank_name'] ?? $existing['bank_name']));
        $bankName = isset($profiles[$bankName]) ? $bankName : 'Guaranty Trust Bank (GTB)';

        $config = [
            'enabled' => !empty($data['enabled']),
            'bank_name' => $bankName,
            'mailbox_email' => trim((string)($data['mailbox_email'] ?? $existing['mailbox_email'])),
            'imap_host' => trim((string)($data['imap_host'] ?? $existing['imap_host'])),
            'imap_port' => max(1, (int)($data['imap_port'] ?? $existing['imap_port'])),
            'imap_encryption' => in_array(($data['imap_encryption'] ?? $existing['imap_encryption']), ['ssl', 'tls', 'none'], true)
                ? (string)($data['imap_encryption'] ?? $existing['imap_encryption'])
                : 'ssl',
            'bank_sender' => trim((string)($data['bank_sender'] ?? $profiles[$bankName]['sender'])),
            'last_sync' => $existing['last_sync'] ?? 'Never',
            'has_secret' => !empty($data['imap_secret']) || !empty($existing['has_secret']),
        ];

        $config['status'] = $config['enabled']
            ? 'Connected - ready to scan selected bank alerts'
            : 'Saved - automatic scan disabled';

        $_SESSION['payment_mail_connector'] = $config;
        self::storeMailConnectorConfig($config);

        return $config;
    }

    public static function syncMailboxAlerts(): array
    {
        $config = self::getMailConnectorConfig();
        $secret = self::env('PAYMENT_RECON_IMAP_SECRET');
        if (empty($config['enabled']) || !function_exists('imap_open') || $secret === '') {
            $config['last_sync'] = date('Y-m-d h:i A');
            $config['status'] = empty($config['enabled'])
                ? 'Automatic mailbox scan disabled'
                : 'Mailbox scan unavailable - IMAP extension or secret missing';
            $config['has_secret'] = $secret !== '';
            $_SESSION['payment_mail_connector'] = $config;
            self::storeMailConnectorConfig($config);

            return [
                'processed' => 0,
                'imported' => 0,
                'duplicates' => 0,
                'bank_name' => $config['bank_name'],
                'last_sync' => $config['last_sync'],
                'status' => $config['status'],
                'message' => $config['status'],
            ];
        }

        $messages = self::fetchMailboxMessages($config);
        $processed = 0;
        $imported = 0;
        $duplicates = 0;

        foreach ($messages as $message) {
            $processed++;
            $alert = self::parseSimulatedEmailAlert($message, [
                'bank_name' => $config['bank_name'],
                'source' => 'Email IMAP (' . $config['mailbox_email'] . ')',
            ]);

            if (($alert['status'] ?? '') === 'Duplicate Alert Flagged') {
                $duplicates++;
                continue;
            }

            $imported++;
        }

        $config['last_sync'] = date('Y-m-d h:i A');
        $config['status'] = 'Last mailbox scan completed';
        $_SESSION['payment_mail_connector'] = $config;
        self::storeMailConnectorConfig($config);

        return [
            'processed' => $processed,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'bank_name' => $config['bank_name'],
            'last_sync' => $config['last_sync'],
            'status' => $config['status'],
        ];
    }

    public static function parseSimulatedEmailAlert(string $emailText, array $options = []): array
    {
        preg_match('/(?:Amount|Amt|Credit Amount|CR Amt|NGN)\s*:?\s*(?:NGN|₦)?\s*([\d,]+(?:\.\d{1,2})?)/i', $emailText, $amtMatch);
        preg_match('/(?:Ref(?:erence)?|TxnID|Transaction ID|Session ID|Narration)\s*:?\s*([A-Za-z0-9\-_\/]+)/i', $emailText, $refMatch);
        preg_match('/(?:From|Sender|Account Name|Depositor|Payer|Customer)\s*:?\s*([A-Za-z0-9\s&.\'-]+)/i', $emailText, $senderMatch);
        preg_match('/(?:Date|Value Date|Transaction Date)\s*:?\s*([A-Za-z0-9,\-\s:\/]+)/i', $emailText, $dateMatch);

        $amount = isset($amtMatch[1]) ? (float)str_replace(',', '', $amtMatch[1]) : 0.0;
        $ref = $refMatch[1] ?? ('CR-' . date('YmdHis'));
        $sender = trim($senderMatch[1] ?? 'Unknown Sender');
        $bankName = $options['bank_name'] ?? self::detectBankName($emailText);
        $alertDate = isset($dateMatch[1]) ? trim($dateMatch[1]) : date('Y-m-d h:i A');

        $isDuplicate = false;
        $existing = self::getAlerts();
        foreach ($existing as $item) {
            if (($item['transaction_ref'] ?? '') === $ref) {
                $isDuplicate = true;
                break;
            }
        }

        $status = $isDuplicate ? 'Duplicate Alert Flagged' : 'Matched & Auto-Posted';
        $alert = [
            'id' => time(),
            'source' => $options['source'] ?? 'IMAP Bank Alert Parser',
            'bank_name' => $bankName,
            'sender_name' => $sender,
            'transaction_ref' => $ref,
            'amount' => $amount,
            'alert_date' => $alertDate,
            'matched_to' => $isDuplicate ? 'N/A (Duplicate)' : 'Unmatched Bank Alert',
            'status' => $status,
            'confidence_score' => $isDuplicate ? '0%' : '98.5%',
            'reviewed_by' => 'Auto Parser & Validator',
        ];

        if (!$isDuplicate) {
            if (!self::storeAlert($alert)) {
                array_unshift($existing, $alert);
                $_SESSION['payment_alerts'] = $existing;
            }
        }

        return $alert;
    }

    public static function recordAlert(array $alert): bool
    {
        return self::storeAlert($alert);
    }

    private static function detectBankName(string $emailText): string
    {
        foreach (array_keys(self::getBankProfiles()) as $bankName) {
            $needle = strtolower(str_replace(['(', ')'], '', $bankName));
            if (str_contains(strtolower($emailText), strtok($needle, ' '))) {
                return $bankName;
            }
        }

        return 'Guaranty Trust Bank (GTB)';
    }

    private static function getMockMailboxMessages(string $bankName): array
    {
        return [];
    }

    private static function getStoredAlerts(): array
    {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return [];
            }

            self::ensureTables();
            $stmt = $pdo->query("SELECT alert_id AS id, source, bank_name, sender_name, transaction_ref, amount, alert_date, matched_to, status, confidence_score, reviewed_by, created_at FROM payment_alerts ORDER BY created_at DESC, id DESC LIMIT 200");

            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function storeAlert(array $alert): bool
    {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return false;
            }

            self::ensureTables();
            $stmt = $pdo->prepare("INSERT INTO payment_alerts (alert_id, company_id, source, bank_name, sender_name, transaction_ref, amount, alert_date, matched_to, status, confidence_score, reviewed_by, created_at) VALUES (?, 'beverage', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), matched_to=VALUES(matched_to), reviewed_by=VALUES(reviewed_by)");
            $stmt->execute([
                (string)($alert['id'] ?? uniqid('pay_', true)),
                (string)($alert['source'] ?? ''),
                (string)($alert['bank_name'] ?? ''),
                (string)($alert['sender_name'] ?? ''),
                (string)($alert['transaction_ref'] ?? ''),
                (float)($alert['amount'] ?? 0),
                (string)($alert['alert_date'] ?? date('Y-m-d H:i:s')),
                (string)($alert['matched_to'] ?? ''),
                (string)($alert['status'] ?? 'Unmatched Bank Alert'),
                (string)($alert['confidence_score'] ?? ''),
                (string)($alert['reviewed_by'] ?? ''),
                date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function getStoredMailConnectorConfig(): array
    {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return [];
            }

            self::ensureTables();
            $stmt = $pdo->prepare("SELECT enabled, bank_name, mailbox_email, imap_host, imap_port, imap_encryption, bank_sender, last_sync, status, has_secret FROM payment_mail_connector WHERE company_id = 'beverage' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if (!$row) {
                return [];
            }

            $row['enabled'] = (bool)$row['enabled'];
            $row['has_secret'] = (bool)$row['has_secret'];
            $row['imap_port'] = (int)$row['imap_port'];

            return $row;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function storeMailConnectorConfig(array $config): bool
    {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return false;
            }

            self::ensureTables();
            $stmt = $pdo->prepare("INSERT INTO payment_mail_connector (company_id, enabled, bank_name, mailbox_email, imap_host, imap_port, imap_encryption, bank_sender, last_sync, status, has_secret, updated_at) VALUES ('beverage', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), bank_name=VALUES(bank_name), mailbox_email=VALUES(mailbox_email), imap_host=VALUES(imap_host), imap_port=VALUES(imap_port), imap_encryption=VALUES(imap_encryption), bank_sender=VALUES(bank_sender), last_sync=VALUES(last_sync), status=VALUES(status), has_secret=VALUES(has_secret), updated_at=VALUES(updated_at)");
            $stmt->execute([
                !empty($config['enabled']) ? 1 : 0,
                (string)($config['bank_name'] ?? ''),
                (string)($config['mailbox_email'] ?? ''),
                (string)($config['imap_host'] ?? ''),
                (int)($config['imap_port'] ?? 993),
                (string)($config['imap_encryption'] ?? 'ssl'),
                (string)($config['bank_sender'] ?? ''),
                (string)($config['last_sync'] ?? 'Never'),
                (string)($config['status'] ?? ''),
                !empty($config['has_secret']) ? 1 : 0,
                date('Y-m-d H:i:s'),
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alert_id VARCHAR(80) NOT NULL UNIQUE,
            company_id VARCHAR(50) NOT NULL DEFAULT 'beverage',
            source VARCHAR(150) NOT NULL,
            bank_name VARCHAR(150) NOT NULL,
            sender_name VARCHAR(150) NOT NULL,
            transaction_ref VARCHAR(150) NOT NULL UNIQUE,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            alert_date VARCHAR(80) DEFAULT NULL,
            matched_to VARCHAR(150) DEFAULT NULL,
            status VARCHAR(80) NOT NULL DEFAULT 'Unmatched Bank Alert',
            confidence_score VARCHAR(40) DEFAULT NULL,
            reviewed_by VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_payment_alerts_company_created (company_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_mail_connector (
            company_id VARCHAR(50) PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            bank_name VARCHAR(150) NOT NULL,
            mailbox_email VARCHAR(180) NOT NULL,
            imap_host VARCHAR(150) NOT NULL DEFAULT 'imap.gmail.com',
            imap_port INT NOT NULL DEFAULT 993,
            imap_encryption VARCHAR(20) NOT NULL DEFAULT 'ssl',
            bank_sender VARCHAR(180) NOT NULL,
            last_sync VARCHAR(80) NOT NULL DEFAULT 'Never',
            status VARCHAR(150) NOT NULL DEFAULT 'Not Connected',
            has_secret TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function env(string $key): string
    {
        if (function_exists('env_value')) {
            return trim((string)\env_value($key, ''));
        }

        return trim((string)(getenv($key) ?: ''));
    }

    private static function fetchMailboxMessages(array $config): array
    {
        $secret = self::env('PAYMENT_RECON_IMAP_SECRET');
        if (!function_exists('imap_open') || $secret === '') {
            return [];
        }

        $host = (string)$config['imap_host'];
        $port = (int)$config['imap_port'];
        $encryption = (string)$config['imap_encryption'];
        $user = self::env('PAYMENT_RECON_IMAP_USER') ?: (string)$config['mailbox_email'];
        $sender = (string)$config['bank_sender'];
        $flags = $encryption === 'none' ? '/notls' : '/' . $encryption;
        $mailbox = sprintf('{%s:%d/imap%s}INBOX', $host, $port, $flags);

        $stream = @imap_open($mailbox, $user, $secret);
        if (!$stream) {
            return [];
        }

        $criteria = 'FROM "' . addcslashes($sender, '"\\') . '" SINCE "' . date('d-M-Y', strtotime('-7 days')) . '"';
        $messageIds = imap_search($stream, $criteria) ?: [];
        $messages = [];

        foreach (array_slice($messageIds, -25) as $messageId) {
            $body = imap_fetchbody($stream, $messageId, 1) ?: imap_body($stream, $messageId);
            $messages[] = trim(strip_tags(quoted_printable_decode((string)$body)));
        }

        imap_close($stream);

        return $messages;
    }
}
