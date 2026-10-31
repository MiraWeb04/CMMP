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
            throw new RuntimeException('Email delivery is temporarily unavailable.');
        }

        require_once $autoload;

        $mail = null;
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = $config['encryption'] === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = static function (): void {
            };
            $mail->Timeout = 20;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
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
        } catch (Throwable $exception) {
            error_log('CMMP email delivery failed: ' . $exception->getMessage());
            throw new RuntimeException('Email delivery is temporarily unavailable.');
        } finally {
            if ($mail instanceof PHPMailer) {
                $mail->Password = '';
                $mail->Username = '';
                $mail->clearAllRecipients();
                $mail->smtpClose();
            }
        }
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
}
