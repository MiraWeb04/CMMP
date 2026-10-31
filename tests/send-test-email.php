<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../services/MailService.php';

$config = mail_config();
$to = trim((string) ($argv[1] ?? $config['from_address'] ?? ''));

echo "SMTP host: " . ($config['host'] ?: '(empty)') . "\n";
echo "SMTP port: " . $config['port'] . "\n";
echo "Username: " . ($config['username'] !== '' ? $config['username'] : '(empty)') . "\n";
echo "From: " . ($config['from_address'] !== '' ? $config['from_address'] : '(empty)') . "\n";
$passwordLength = strlen((string) $config['password']);
echo "Password loaded: " . ($passwordLength > 0 ? 'yes (' . $passwordLength . ' chars' . ($passwordLength === 16 ? ', looks like an app password' : ', Gmail app passwords are 16 letters') . ')' : 'no') . "\n";
echo "Configured: " . (mail_is_configured($config) ? 'yes' : 'no') . "\n";
echo "PHPMailer: " . (is_file(__DIR__ . '/../vendor/autoload.php') ? 'installed' : 'missing — run composer install') . "\n";
echo "OpenSSL CA file: " . ((string) (ini_get('openssl.cafile') ?: ini_get('curl.cainfo') ?: 'not set')) . "\n";

if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Pass a destination email: php tests/send-test-email.php you@email.com\n");
    exit(1);
}

try {
    MailService::send(
        $to,
        'CMMP email test',
        'If you received this, CMMP can send mail from your configured Gmail SMTP account.'
    );
    echo "Sent a test email to {$to}. Check the inbox and spam folder.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Send failed: " . $exception->getMessage() . "\n");
    exit(1);
}
