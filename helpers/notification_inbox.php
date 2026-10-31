<?php
declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../models/Notification.php';

function notification_type_label(string $type): string
{
    $label = trim(str_replace('_', ' ', $type));
    return $label === '' ? 'Update' : ucwords(strtolower($label));
}

function notification_filters_from_request(): array
{
    $status = (string) ($_GET['status'] ?? 'all');
    if (!in_array($status, ['all', 'unread', 'read'], true)) {
        $status = 'all';
    }
    $period = (string) ($_GET['period'] ?? 'all');
    if (!in_array($period, ['all', 'today', 'week', 'month'], true)) {
        $period = 'all';
    }
    $since = '';
    if ($period === 'today') {
        $since = date('Y-m-d 00:00:00');
    } elseif ($period === 'week') {
        $since = date('Y-m-d 00:00:00', strtotime('-6 days'));
    } elseif ($period === 'month') {
        $since = date('Y-m-01 00:00:00');
    }

    return [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'status' => $status,
        'type' => trim((string) ($_GET['type'] ?? '')),
        'period' => $period,
        'since' => $since,
    ];
}

function notification_filter_query(array $filters): string
{
    $params = [];
    if ($filters['q'] !== '') {
        $params['q'] = $filters['q'];
    }
    if ($filters['status'] !== 'all') {
        $params['status'] = $filters['status'];
    }
    if ($filters['type'] !== '') {
        $params['type'] = $filters['type'];
    }
    if ($filters['period'] !== 'all') {
        $params['period'] = $filters['period'];
    }

    return http_build_query($params);
}

function notification_inbox_prepare(int $userId): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_valid_csrf_token();
        $action = (string) ($_POST['action'] ?? 'read');
        $notice = '';
        $noticeError = '';
        if ($action === 'read_all') {
            Notification::markAllRead($userId);
        } elseif ($action === 'delete') {
            try {
                Notification::deleteForUser((int) ($_POST['id'] ?? 0), $userId);
                $notice = 'deleted';
            } catch (InvalidArgumentException $exception) {
                $noticeError = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('CMMP notification delete failed: ' . $exception->getMessage());
                $noticeError = 'The notification could not be deleted.';
            }
        } else {
            Notification::markRead((int) ($_POST['id'] ?? 0), $userId);
        }
        $filters = [
            'q' => trim((string) ($_POST['q'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'all'),
            'type' => trim((string) ($_POST['type'] ?? '')),
            'period' => (string) ($_POST['period'] ?? 'all'),
        ];
        $query = notification_filter_query($filters + ['since' => '']);
        $location = basename((string) $_SERVER['SCRIPT_NAME']) . ($query === '' ? '' : '?' . $query);
        if ($notice !== '') {
            $location .= ($query === '' ? '?' : '&') . $notice . '=1';
        } elseif ($noticeError !== '') {
            $location .= ($query === '' ? '?' : '&') . 'delete_error=' . urlencode($noticeError);
        }
        header('Location: ' . $location);
        exit;
    }

    $filters = notification_filters_from_request();
    $types = Notification::typesForUser($userId);
    if ($filters['type'] !== '' && !in_array($filters['type'], $types, true)) {
        $filters['type'] = '';
    }

    return [
        'filters' => $filters,
        'types' => $types,
        'items' => Notification::filtered($userId, $filters),
        'unread' => Notification::unreadCount($userId),
    ];
}
