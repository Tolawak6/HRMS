<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_auth();
$year = current_year();
$types = leave_types_active();
$balances = leave_balance_summary((int) $user['id'], $year);
$errors = [];
$values = [
    'leave_type_id' => (string) get_int('type'),
    'start_date' => '',
    'end_date' => '',
    'reason' => '',
];

if (is_post()) {
    $values = [
        'leave_type_id' => (string) post_int('leave_type_id'),
        'start_date' => post_str('start_date'),
        'end_date' => post_str('end_date'),
        'reason' => post_str('reason'),
    ];
    $outcome = leave_request_create((int) $user['id'], $values);
    if ($outcome['ok']) {
        flash('success', 'Leave request submitted. Human Resources will review it.');
        redirect('leave/index.php');
    }
    $errors = $outcome['errors'] ?? ['_form' => 'The leave request could not be submitted.'];
}

$remainingByType = [];
foreach ($balances as $balance) {
    $remainingByType[(int) $balance['type']['id']] = (float) $balance['remaining'];
}

$pageTitle = 'Request leave';
$topbarTitle = 'Self service';
$activeNav = 'leave';
include BASE_PATH . '/includes/layout/header.php';
?>
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb breadcrumb-ess">
        <li class="breadcrumb-item"><a href="<?= e(url('leave/index.php')) ?>">My leave</a></li>
        <li class="breadcrumb-item active" aria-current="page">New request</li>
    </ol>
</nav>
<div class="page-header">
    <div>
        <h1 class="page-title">Request leave</h1>
        <p class="page-subtitle">Working days exclude Saturdays and Sundays. Requests cannot overlap.</p>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel">
        <div class="panel-body">
            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>

            <?php if ($types === []): ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('calendar-x') ?></div>
                    <h3>No leave types available</h3>
                    <p>Contact Human Resources to configure leave types and entitlements.</p>
                </div>
            <?php else: ?>
                <form method="post" action="<?= e(url('leave/request.php')) ?>" novalidate data-leave-form>
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="span-2">
                            <label class="form-label" for="leave_type_id">Leave type <span class="req">*</span></label>
                            <select class="form-select<?= invalid_class($errors, 'leave_type_id') ?>" id="leave_type_id"
                                    name="leave_type_id" required>
                                <option value="">Select leave type…</option>
                                <?php foreach ($types as $type): ?>
                                    <?php $remaining = $remainingByType[(int) $type['id']] ?? 0; ?>
                                    <option value="<?= (int) $type['id'] ?>" <?= selected($values['leave_type_id'], (string) $type['id']) ?>>
                                        <?= e((string) $type['name']) ?> — <?= e(format_days($remaining)) ?> day(s) available
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (field_error($errors, 'leave_type_id') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'leave_type_id')) ?></div><?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="start_date">Start date <span class="req">*</span></label>
                            <input class="form-control<?= invalid_class($errors, 'start_date') ?>" type="date" id="start_date"
                                   name="start_date" value="<?= e($values['start_date']) ?>" min="<?= e(today()) ?>" required>
                            <?php if (field_error($errors, 'start_date') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'start_date')) ?></div><?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="end_date">End date <span class="req">*</span></label>
                            <input class="form-control<?= invalid_class($errors, 'end_date') ?>" type="date" id="end_date"
                                   name="end_date" value="<?= e($values['end_date']) ?>" min="<?= e(today()) ?>" required>
                            <?php if (field_error($errors, 'end_date') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'end_date')) ?></div><?php endif; ?>
                        </div>
                        <div class="span-2">
                            <div class="notice">
                                <?= icon('info-circle') ?>
                                <div>Duration: <strong data-working-days>—</strong>. Final duration is confirmed by the server.</div>
                            </div>
                        </div>
                        <div class="span-2">
                            <label class="form-label" for="reason">Reason</label>
                            <textarea class="form-control<?= invalid_class($errors, 'reason') ?>" id="reason" name="reason"
                                      rows="4" maxlength="1000" placeholder="Briefly explain the reason for this request"><?= e($values['reason']) ?></textarea>
                            <?php if (field_error($errors, 'reason') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'reason')) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <a class="btn btn-outline-secondary" href="<?= e(url('leave/index.php')) ?>">Cancel</a>
                        <span class="spacer"></span>
                        <button class="btn btn-primary" type="submit"><?= icon('send') ?> Submit request</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <aside class="panel" aria-labelledby="balanceHeading">
        <div class="panel-header">
            <div><h2 id="balanceHeading">Available balances</h2><div class="meta"><?= (int) $year ?></div></div>
        </div>
        <div class="panel-body">
            <?php if ($balances === []): ?>
                <p class="text-muted mb-0">No entitlements configured.</p>
            <?php else: ?>
                <div class="mini-list">
                    <?php foreach ($balances as $balance): ?>
                        <div class="row-line">
                            <span><?= e((string) $balance['type']['name']) ?></span>
                            <span><?= e(format_days((float) $balance['remaining'])) ?> / <?= e(format_days((float) $balance['entitlement'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="divider"></div>
            <p class="tiny text-muted-2 mb-0">
                Submit requests in advance and keep your contact details current. Approved leave updates your
                employment status when it begins.
            </p>
        </div>
    </aside>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
