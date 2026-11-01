<?php
declare(strict_types=1);

/**
 * Application wall-clock helpers.
 *
 * Announcement DATETIME columns already store naive Asia/Manila values written
 * by PHP and datetime-local forms. Those rows stay in that clock so existing
 * records remain comparable. Visibility checks must use PHP's application time
 * (`app_now_sql()`), not MySQL NOW(), because the database session may be UTC.
 * Display helpers format the stored wall-clock in Asia/Manila.
 */

require_once __DIR__ . '/../config/app.php';

function app_timezone(): DateTimeZone
{
    $name = getenv('APP_TIMEZONE') ?: (defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila');
    return new DateTimeZone($name);
}

function app_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', app_timezone());
}

function app_now_sql(): string
{
    return app_now()->format('Y-m-d H:i:s');
}

function utc_now_sql(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function parse_app_datetime(?string $value): ?DateTimeImmutable
{
    $raw = trim(str_replace('T', ' ', (string) $value));
    if ($raw === '') {
        return null;
    }

    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat('!' . $format, $raw, app_timezone());
        if ($parsed instanceof DateTimeImmutable) {
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0)) {
                return $parsed;
            }
        }
    }

    return null;
}

function preserve_app_datetime_seconds(?string $existing, ?string $incoming): ?string
{
    if ($existing === null || $existing === '' || $incoming === null || $incoming === '') {
        return $incoming;
    }

    $old = parse_app_datetime($existing);
    $new = parse_app_datetime($incoming);
    if (!$old instanceof DateTimeImmutable || !$new instanceof DateTimeImmutable) {
        return $incoming;
    }

    if ($old->format('Y-m-d H:i') === $new->format('Y-m-d H:i')) {
        return $old->format('Y-m-d H:i:s');
    }

    return $new->format('Y-m-d H:i:s');
}

function store_app_datetime(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    if ($value instanceof DateTimeInterface) {
        return (new DateTimeImmutable($value->format('Y-m-d H:i:s'), app_timezone()))->format('Y-m-d H:i:s');
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $parsed = parse_app_datetime($raw);
    if ($parsed === null) {
        throw new InvalidArgumentException('Enter a valid date and time.');
    }

    return $parsed->format('Y-m-d H:i:s');
}

function format_app_datetime(?string $value, string $pattern = 'M j, Y g:i A'): string
{
    $parsed = parse_app_datetime($value);
    return $parsed instanceof DateTimeImmutable ? $parsed->format($pattern) : '';
}

function datetime_local_value(?string $value): string
{
    $parsed = parse_app_datetime($value);
    return $parsed instanceof DateTimeImmutable ? $parsed->format('Y-m-d\TH:i') : '';
}
