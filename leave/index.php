<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_auth();
$year = current_year();
$balances = leave_balance_summary((int) $user['id'], $year);
$requests = leave_requests_for_user((int) $user['id'], 50);

if (is_post()) {
    $action = post_str('action');
    if ($action === 'cancel') {
        $outcome = leave_request_cancel(post_int('id'), (int) $user['id']);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Leave request cancelled.' : (string) ($outcome['error'] ?? 'Unable to cancel the request.'));
        redirect('leave/index.php');
    }
}

$totalEntitlement = 0.0;
$totalRemaining = 0.0;
$pendingCount = 0;
foreach ($balances as $balance) {
    $totalEntitlement += (float) $balance['entitlement'];
    $totalRemaining += (float) $balance['remaining'];
}
foreach ($requests as $request) {
    if ($request['status'] === 'pending') {
        $pendingCount++;
    }
}

$pageTitle = 'My leave';
$topbarTitle = 'Self service';
$activeNav = 'leave';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">My leave</h1>
        <p class="page-subtitle">Balances for <?= (int) $year ?> and the status of your requests</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(url('leave/request.php')) ?>"><?= icon('calendar-plus') ?> Request leave</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><?= icon('calendar2-range') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= e(format_days($totalEntitlement)) ?></div>
            <div class="stat-label">Total entitlement</div>
            <div class="stat-meta">All leave types, <?= (int) $year ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon is-green"><?= icon('check2-circle') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= e(format_days($totalRemaining)) ?></div>
            <div class="stat-label">Available balance</div>
            <div class="stat-meta">Excludes pending requests</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon is-gold"><?= icon('hourglass') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($pendingCount) ?></div>
            <div class="stat-label">Pending requests</div>
            <div class="stat-meta">Awaiting Human Resources review</div>
        </div>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="requestsHeading">
        <div class="panel-header">
            <div>
                <h2 id="requestsHeading">My leave requests</h2>
                <div class="meta"><?= count($requests) ?> most recent request<?= count($requests) === 1 ? '' : 's' ?></div>
            </div>
        </div>
        <?php if ($requests === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('calendar2-week') ?></div>
                <h3>No leave requests yet</h3>
                <p>Submit a request and track its approval status here.</p>
                <a class="btn btn-primary" href="<?= e(url('leave/request.php')) ?>"><?= icon('calendar-plus') ?> Request leave</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col">Period</th>
                            <th scope="col" class="text-end">Days</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <span class="row-title"><?= e((string) $request['leave_type_name']) ?></span>
                                <?php if (!empty($request['review_note'])): ?>
                                    <div class="row-meta">Note: <?= e((string) $request['review_note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= e(format_date((string) $request['start_date'])) ?> – <?= e(format_date((string) $request['end_date'])) ?></td>
                            <td class="text-end"><?= e(format_days((float) $request['working_days'])) ?></td>
                            <td><span class="badge badge-soft-<?= e(status_badge_class((string) $request['status'])) ?>"><?= e(ucfirst((string) $request['status'])) ?></span></td>
                            <td class="col-actions">
                                <?php if ($request['status'] === 'pending' && (strtotime((string) $request['start_date']) >= strtotime(today()))): ?>
                                    <form method="post" action="<?= e(url('leave/index.php')) ?>"
                                          data-confirm="Cancel this leave request?"
                                          data-confirm-title="Cancel leave request" data-confirm-button="Cancel request">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                                        <button class="btn btn-sm btn-quiet" type="submit">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="balancesHeading">
        <div class="panel-header">
            <div><h2 id="balancesHeading">Balances <?= (int) $year ?></h2><div class="meta">Working days</div></div>
        </div>
        <div class="panel-body">
            <?php if ($balances === []): ?>
                <p class="text-muted mb-0">No leave types are configured.</p>
            <?php else: ?>
                <div class="bar-list">
                    <?php foreach ($balances as $balance): ?>
                        <?php
                        $entitled = (float) $balance['entitlement'];
                        $used = (float) $balance['approved'] + (float) $balance['pending'];
                        $pct = $entitled > 0 ? min(100, ($used / $entitled) * 100) : 0;
                        ?>
                        <div class="bar-row">
                            <div class="bar-top">
                                <strong><?= e((string) $balance['type']['name']) ?></strong>
                                <span><?= e(format_days((float) $balance['remaining'])) ?> left</span>
                            </div>
                            <div class="progress-thin">
                                <div class="bar is-gold" style="width: <?= e((string) round($pct, 1)) ?>%"></div>
                            </div>
                            <div class="tiny text-muted-2 mt-1">
                                <?= e(format_days((float) $balance['approved'])) ?> approved ·
                                <?= e(format_days((float) $balance['pending'])) ?> pending ·
                                <?= e(format_days($entitled)) ?> entitled
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
