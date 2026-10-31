<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public static function send(string $toAddress, string $subject, string $body, bool $html = false): void
    {
        $config = mail_config();
        if (!mail_is_configured($config)) {
            error_log('CMMP email delivery is not configured.');
            throw new RuntimeException('Email delivery is not configured.');
        }

        $toAddress = trim($toAddress);
        $subject = cmmp_single_line($subject, '');
        $body = str_replace("\0", '', $body);
        if (filter_var($toAddress, FILTER_VALIDATE_EMAIL) === false || $subject === '' || trim($body) === '') {
            throw new InvalidArgumentException('A valid email message is required.');
        }
        if (strlen($subject) > 255 || strlen($body) > 200000) {
            throw new InvalidArgumentException('A valid email message is required.');
        }

        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) {
            error_log('CMMP mail library is not installed.');
            throw new RuntimeException('Email delivery is temporarily unavailable. Run composer install.');
        }

        require_once $autoload;

        $lastError = 'Email delivery is temporarily unavailable.';
        foreach (self::transportProfiles($config) as $profile) {
            try {
                self::sendWithProfile($config, $profile, $toAddress, $subject, $body, $html);
                return;
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
                error_log('CMMP email delivery failed (' . $profile['port'] . '/' . $profile['encryption'] . '): ' . $lastError);
            }
        }

        if (stripos($lastError, 'authenticate') !== false) {
            throw new RuntimeException(
                'Gmail rejected the SMTP login. Turn on 2-Step Verification, create a new App Password, and put only that 16-letter password in MAIL_PASSWORD. Do not use your normal Gmail password.'
            );
        }

        throw new RuntimeException('Email delivery is temporarily unavailable.');
    }

    public static function trySend(string $toAddress, string $subject, string $body, bool $html = false): bool
    {
        try {
            if (!mail_is_configured()) {
                error_log('CMMP email skipped because SMTP is not configured.');
                return false;
            }
            self::send($toAddress, $subject, $body, $html);
            return true;
        } catch (Throwable $exception) {
            error_log('CMMP email notification skipped: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * @return list<array{port:int,encryption:string}>
     */
    private static function transportProfiles(array $config): array
    {
        $profiles = [[
            'port' => (int) $config['port'],
            'encryption' => (string) $config['encryption'],
        ]];

        if (strtolower((string) $config['host']) === 'smtp.gmail.com') {
            $profiles[] = ['port' => 465, 'encryption' => 'ssl'];
            $profiles[] = ['port' => 587, 'encryption' => 'tls'];
        }

        $unique = [];
        $seen = [];
        foreach ($profiles as $profile) {
            $key = $profile['port'] . ':' . $profile['encryption'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $profile;
        }

        return $unique;
    }

    /**
     * @param array{port:int,encryption:string} $profile
     */
    private static function sendWithProfile(
        array $config,
        array $profile,
        string $toAddress,
        string $subject,
        string $body,
        bool $html
    ): void {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $profile['port'];
            $mail->SMTPAuth = true;
            $mail->AuthType = 'LOGIN';
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = $profile['encryption'] === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
            $mail->SMTPDebug = 0;
            $mail->Timeout = 20;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $cafile = (string) (ini_get('openssl.cafile') ?: ini_get('curl.cainfo') ?: '');
            if ($cafile === '' || !is_file($cafile)) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }
            $mail->setFrom($config['from_address'], $config['from_name']);
            $mail->addAddress($toAddress);
            $mail->Subject = $subject;

            if ($html) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } else {
                $mail->isHTML(false);
                $mail->Body = $body;
            }

            $mail->send();
        } finally {
            $mail->Password = '';
            $mail->Username = '';
            $mail->clearAllRecipients();
            $mail->smtpClose();
        }
    }
}
