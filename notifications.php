<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$user = require_auth();
$userId = (int) $user['id'];

if (is_post() && post_str('action') === 'mark_all') {
    db()->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

$openId = get_int('open');
if ($openId > 0) {
    $notification = db()->one('SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1', [$openId, $userId]);
    if ($notification !== null) {
        db()->execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$openId, $userId]);
        $link = trim((string) ($notification['link'] ?? ''));
        if ($link !== '' && !preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $link) && !str_contains($link, '..')) {
            redirect($link);
        }
    }
    redirect('notifications.php');
}

$page = max(1, get_int('page', 1));
$perPage = per_page_value(20);
$total = (int) db()->value('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId], 0);
$pagination = paginate($total, $perPage, $page);
$unread = unread_notification_count($userId);
$rows = db()->all(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT '
        . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    [$userId]
);

$pageTitle = 'Notifications';
$topbarTitle = 'My account';
$activeNav = 'notifications';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle"><?= $unread > 0 ? $unread . ' unread notification' . ($unread === 1 ? '' : 's') : 'You are up to date' ?></p>
    </div>
    <div class="page-actions">
        <?php if ($unread > 0): ?>
            <form method="post" action="<?= e(url('notifications.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all">
                <button class="btn btn-outline-secondary" type="submit"><?= icon('check2-all') ?> Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="panel" aria-labelledby="listHeading">
    <div class="panel-header"><div><h2 id="listHeading">Your notifications</h2><div class="meta">Account, leave and document updates</div></div></div>
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= icon('bell') ?></div>
            <h3>No notifications yet</h3>
            <p>Account, leave and document updates addressed to you will appear here.</p>
        </div>
    <?php else: ?>
        <div class="nav-notifications">
            <?php foreach ($rows as $note): ?>
                <?php
                $link = trim((string) ($note['link'] ?? ''));
                $safeLink = ($link !== '' && !preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $link) && !str_contains($link, '..'))
                    ? url('notifications.php?open=' . (int) $note['id'])
                    : null;
                ?>
                <div class="notification-item<?= (int) $note['is_read'] === 0 ? ' is-unread' : '' ?>">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold">
                                <?php if ((int) $note['is_read'] === 0): ?><span class="badge badge-soft-info me-1">New</span><?php endif; ?>
                                <?= e((string) $note['title']) ?>
                            </div>
                            <div class="text-muted small mt-1"><?= e((string) $note['message']) ?></div>
                            <div class="tiny text-muted-2 mt-1"><?= e(format_datetime((string) $note['created_at'])) ?> · <?= e(time_ago((string) $note['created_at'])) ?></div>
                        </div>
                        <?php if ($safeLink !== null): ?>
                            <a class="btn btn-sm btn-outline-secondary align-self-start" href="<?= e($safeLink) ?>">Open</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php include BASE_PATH . '/includes/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
