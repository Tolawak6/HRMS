<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin', 'hr_staff']);
$filters = leave_filters();
$rejectId = get_int('reject');
$rejectTarget = $rejectId > 0 ? leave_request_find($rejectId) : null;
if ($rejectId > 0 && ($rejectTarget === null || $rejectTarget['status'] !== 'pending')) {
    flash('warning', 'Only pending leave requests can be rejected.');
    redirect('leave/requests.php');
}

if (is_post()) {
    $action = post_str('action');
    $requestId = post_int('id');
    if ($action === 'approve' || $action === 'reject') {
        $outcome = leave_request_decide($requestId, $action, post_str('note'), $actor);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok']
            ? ($action === 'approve' ? 'Leave request approved.' : 'Leave request rejected.')
            : (string) ($outcome['error'] ?? 'The request could not be updated.'));
        redirect('leave/requests.php?status=pending');
    }
}

$result = leave_search($filters);
$rows = $result['rows'];
$pagination = $result['pagination'];
$types = leave_types_all();
$stats = db()->one(
    "SELECT
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN status = 'approved' THEN working_days ELSE 0 END) AS approved_days
     FROM leave_requests WHERE YEAR(start_date) = ?",
    [$filters['year']]
) ?? ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'approved_days' => 0];

$pageTitle = 'Leave requests';
$topbarTitle = 'HR operations';
$activeNav = 'leave-requests';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Leave requests</h1>
        <p class="page-subtitle">Review, approve or reject requests for <?= (int) $filters['year'] ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('leave/balances.php')) ?>"><?= icon('sliders') ?> Entitlements</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon is-gold"><?= icon('hourglass') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format((int) $stats['pending']) ?></div>
            <div class="stat-label">Pending</div>
            <div class="stat-meta">Awaiting a decision</div>
        </div>
    </div>
    <div class="stat-card accent-green">
        <div class="stat-icon is-green"><?= icon('check2-circle') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format((int) $stats['approved']) ?></div>
            <div class="stat-label">Approved</div>
            <div class="stat-meta"><?= e(format_days((float) $stats['approved_days'])) ?> working days</div>
        </div>
    </div>
    <div class="stat-card accent-tan">
        <div class="stat-icon is-danger"><?= icon('x-circle') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format((int) $stats['rejected']) ?></div>
            <div class="stat-label">Rejected</div>
            <div class="stat-meta">With a recorded reason</div>
        </div>
    </div>
</div>

<?php if ($rejectTarget !== null): ?>
    <section class="panel" aria-labelledby="rejectHeading">
        <div class="panel-header">
            <div>
                <h2 id="rejectHeading">Reject leave request</h2>
                <div class="meta">
                    <?= e(person_name($rejectTarget)) ?> · <?= e((string) $rejectTarget['leave_type_name']) ?> ·
                    <?= e(format_date((string) $rejectTarget['start_date'])) ?> – <?= e(format_date((string) $rejectTarget['end_date'])) ?>
                </div>
            </div>
            <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/requests.php')) ?>">Cancel</a>
        </div>
        <div class="panel-body">
            <form method="post" action="<?= e(url('leave/requests.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?= (int) $rejectTarget['id'] ?>">
                <div class="mb-3">
                    <label class="form-label" for="note">Reason for rejection <span class="req">*</span></label>
                    <textarea class="form-control" id="note" name="note" rows="3" maxlength="500" required
                              placeholder="Explain the decision so the employee understands it"></textarea>
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <button class="btn btn-danger" type="submit"><?= icon('x-circle') ?> Reject request</button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="panel" aria-labelledby="filtersHeading">
    <div class="panel-header"><h2 id="filtersHeading" class="visually-hidden">Filters</h2></div>
    <div class="panel-body pt-3">
        <form class="toolbar" method="get" action="<?= e(url('leave/requests.php')) ?>" role="search">
            <div class="field field-grow">
                <label for="q">Search employee</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, employee number or username">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (annual_leave_statuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected($filters['status'], $status) ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="type">Leave type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All types</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int) $type['id'] ?>" <?= selected($filters['type'], (int) $type['id']) ?>><?= e((string) $type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="year">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php for ($y = current_year(); $y >= current_year() - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= selected($filters['year'], $y) ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="field">
                <label class="d-none d-sm-block">&nbsp;</label>
                <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
            </div>
        </form>
    </div>
</section>

<section class="panel" aria-labelledby="listHeading">
    <div class="panel-header">
        <div>
            <h2 id="listHeading">Requests</h2>
            <div class="meta"><?= number_format((int) $pagination['total']) ?> matching request<?= (int) $pagination['total'] === 1 ? '' : 's' ?></div>
        </div>
    </div>
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= icon('calendar2-week') ?></div>
            <h3>No leave requests match</h3>
            <p>Adjust the filters or wait for employees to submit requests.</p>
            <a class="btn btn-outline-secondary" href="<?= e(url('leave/requests.php')) ?>">Clear filters</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead>
                    <tr>
                        <th scope="col">Employee</th>
                        <th scope="col">Type</th>
                        <th scope="col">Period</th>
                        <th scope="col" class="text-end">Days</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <a class="row-title" href="<?= e(url('employees/view.php?id=' . (int) $row['user_id'])) ?>"><?= e(person_name($row)) ?></a>
                            <div class="row-meta"><?= e((string) ($row['employee_number'] ?? '—')) ?></div>
                        </td>
                        <td>
                            <?= e((string) $row['leave_type_name']) ?>
                            <?php if (!empty($row['reason'])): ?><div class="row-meta"><?= e((string) $row['reason']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?= e(format_date((string) $row['start_date'])) ?> – <?= e(format_date((string) $row['end_date'])) ?>
                            <div class="row-meta">Submitted <?= e(format_date((string) $row['created_at'])) ?></div>
                        </td>
                        <td class="text-end"><?= e(format_days((float) $row['working_days'])) ?></td>
                        <td>
                            <span class="badge badge-soft-<?= e(status_badge_class((string) $row['status'])) ?>"><?= e(ucfirst((string) $row['status'])) ?></span>
                            <?php if (!empty($row['review_note'])): ?><div class="row-meta"><?= e((string) $row['review_note']) ?></div><?php endif; ?>
                        </td>
                        <td class="col-actions">
                            <?php if ($row['status'] === 'pending'): ?>
                                <div class="table-actions">
                                    <form method="post" action="<?= e(url('leave/requests.php')) ?>"
                                          data-confirm="Approve this leave request?"
                                          data-confirm-title="Approve leave" data-confirm-button="Approve" data-confirm-variant="success">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                    </form>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(url('leave/requests.php?reject=' . (int) $row['id'])) ?>">Reject</a>
                                </div>
                            <?php else: ?>
                                <span class="text-muted tiny">
                                    <?= e(!empty($row['reviewer_first']) ? 'By ' . trim((string) $row['reviewer_first'] . ' ' . (string) ($row['reviewer_last'] ?? '')) : 'Reviewed') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php include BASE_PATH . '/includes/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
