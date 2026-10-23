<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function notify(int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $safeLink = null;
        if ($link !== null && $link !== '' && !preg_match('#^(https?:)?//#i', $link)) {
            $safeLink = mb_substr(ltrim($link, '/'), 0, 255);
        }
        db()->insert(
            'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())',
            [$userId, mb_substr($type, 0, 40), mb_substr($title, 0, 120), mb_substr($message, 0, 500), $safeLink]
        );
    } catch (Throwable $exception) {
        app_log('error', 'Notification write failed', ['reason' => $exception->getMessage()]);
    }
}

function notify_roles(array $roles, string $type, string $title, string $message, ?string $link = null): void
{
    $roles = array_values(array_intersect($roles, ['admin', 'hr_staff', 'ess_staff']));
    if ($roles === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    try {
        $rows = db()->all(
            "SELECT id FROM users WHERE role IN ($placeholders) AND is_active = 1 AND archived_at IS NULL",
            $roles
        );
        foreach ($rows as $row) {
            notify((int) $row['id'], $type, $title, $message, $link);
        }
    } catch (Throwable $exception) {
        app_log('error', 'Role notification failed', ['reason' => $exception->getMessage()]);
    }
}

function unread_notification_count(int $userId): int
{
    try {
        return (int) db()->value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId],
            0
        );
    } catch (Throwable) {
        return 0;
    }
}

function recent_notifications(int $userId, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    return db()->all(
        'SELECT id, type, title, message, link, is_read, created_at
         FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit,
        [$userId]
    );
}
