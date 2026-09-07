<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

class MailDeliveryService
{
    public static function getStatus(): array
    {
        $enabled = in_array(strtolower((string)\env_value('MAIL_ENABLED', '0')), ['1', 'true', 'yes', 'on'], true);
        $from = trim((string)\env_value('MAIL_FROM', ''));
        $smtpHost = trim((string)\env_value('MAIL_SMTP_HOST', ''));
        $smtpUser = trim((string)\env_value('MAIL_SMTP_USER', ''));
        $smtpPass = trim((string)\env_value('MAIL_SMTP_PASS', ''));
        $transport = $smtpHost !== '' ? 'smtp' : 'php_mail';
        $ready = $enabled && $from !== '' && (
            ($transport === 'smtp' && $smtpUser !== '' && $smtpPass !== '')
            || ($transport === 'php_mail' && function_exists('mail'))
        );

        return [
            'enabled' => $enabled,
            'from' => $from,
            'transport' => $transport,
            'ready' => $ready,
            'status' => $ready
                ? 'Ready'
                : 'Not configured',
        ];
    }

    public static function send(string $to, string $subject, string $body): array
    {
        $status = self::getStatus();
        if (!$status['ready']) {
            return [
                'sent' => false,
                'status' => $status['status'],
                'message' => 'Outbound email is not configured. Set MAIL_ENABLED=1 and MAIL_FROM.',
            ];
        }

        if ($status['transport'] === 'smtp') {
            return self::sendViaSmtp($to, $subject, $body, (string)$status['from']);
        }

        $headers = [
            'From: ' . $status['from'],
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));

        return [
            'sent' => $sent,
            'status' => $sent ? 'Sent' : 'Failed',
            'message' => $sent ? 'Email sent.' : 'PHP mail transport rejected the message.',
        ];
    }

    private static function sendViaSmtp(string $to, string $subject, string $body, string $from): array
    {
        $host = trim((string)\env_value('MAIL_SMTP_HOST', ''));
        $port = (int)\env_value('MAIL_SMTP_PORT', '465');
        $user = trim((string)\env_value('MAIL_SMTP_USER', ''));
        $pass = trim((string)\env_value('MAIL_SMTP_PASS', ''));
        $encryption = strtolower(trim((string)\env_value('MAIL_SMTP_ENCRYPTION', 'ssl')));
        $timeout = 15;
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout);

        if (!$socket) {
            return ['sent' => false, 'status' => 'Failed', 'message' => 'SMTP connection failed: ' . $errstr];
        }

        stream_set_timeout($socket, $timeout);

        $read = static function () use ($socket): string {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            return $response;
        };

        $send = static function (string $command, array $expected) use ($socket, $read): bool {
            fwrite($socket, $command . "\r\n");
            $response = $read();
            return in_array((int)substr($response, 0, 3), $expected, true);
        };

        $read();
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!$send('EHLO ' . $serverName, [250])) {
            fclose($socket);
            return ['sent' => false, 'status' => 'Failed', 'message' => 'SMTP server rejected EHLO.'];
        }

        if ($encryption === 'tls') {
            if (!$send('STARTTLS', [220]) || !@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['sent' => false, 'status' => 'Failed', 'message' => 'SMTP TLS negotiation failed.'];
            }
            if (!$send('EHLO ' . $serverName, [250])) {
                fclose($socket);
                return ['sent' => false, 'status' => 'Failed', 'message' => 'SMTP server rejected EHLO after TLS.'];
            }
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body);
        $message = str_replace("\n", "\r\n", $message);

        $commands = [
            ['AUTH LOGIN', [334]],
            [base64_encode($user), [334]],
            [base64_encode($pass), [235]],
            ['MAIL FROM:<' . $from . '>', [250]],
            ['RCPT TO:<' . $to . '>', [250, 251]],
            ['DATA', [354]],
            [$message . "\r\n.", [250]],
            ['QUIT', [221, 250]],
        ];

        foreach ($commands as [$command, $expected]) {
            if (!$send($command, $expected)) {
                fclose($socket);
                return ['sent' => false, 'status' => 'Failed', 'message' => 'SMTP server rejected the message.'];
            }
        }

        fclose($socket);
        return ['sent' => true, 'status' => 'Sent', 'message' => 'Email sent through SMTP.'];
    }
}
