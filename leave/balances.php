<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin', 'hr_staff']);
$year = max(2000, min(2100, get_int('year', current_year())));
$employeeId = get_int('user');
$employee = $employeeId > 0 ? employee_find($employeeId) : null;
$errors = [];

$employees = db()->all(
    "SELECT id, first_name, middle_name, last_name, employee_number
     FROM users WHERE role = 'ess_staff' AND archived_at IS NULL
     ORDER BY last_name ASC, first_name ASC LIMIT 1000"
);

$types = leave_types_active();
$balances = [];
$overrides = [];
if ($employee !== null) {
    $balances = leave_balance_summary($employeeId, $year);
    $rows = db()->all(
        'SELECT leave_type_id, entitled_days FROM leave_balances WHERE user_id = ? AND year = ?',
        [$employeeId, $year]
    );
    foreach ($rows as $row) {
        $overrides[(int) $row['leave_type_id']] = (float) $row['entitled_days'];
    }
}

if (is_post()) {
    $employeeId = post_int('user');
    $year = max(2000, min(2100, post_int('year', current_year())));
    $employee = employee_find($employeeId);
    if ($employee === null) {
        $errors['_form'] = 'Select a valid employee.';
    } else {
        $submitted = $_POST['entitlement'] ?? [];
        $changed = 0;
        foreach ($types as $type) {
            $typeId = (int) $type['id'];
            if (!is_array($submitted) || !array_key_exists((string) $typeId, $submitted)) {
                continue;
            }
            $raw = trim((string) $submitted[(string) $typeId]);
            $days = $raw === '' ? null : (float) $raw;
            $outcome = leave_balance_set($employeeId, $typeId, $year, $days, post_str('note'), $actor);
            if (!$outcome['ok']) {
                $errors['_form'] = (string) ($outcome['error'] ?? 'Unable to update an entitlement.');
                break;
            }
            $changed++;
        }
        if ($errors === []) {
            flash('success', number_format($changed) . ' entitlement value(s) saved for ' . person_name($employee) . ' · ' . $year . '.');
            redirect('leave/balances.php?user=' . $employeeId . '&year=' . $year);
        }
    }
    $balances = $employee !== null ? leave_balance_summary($employeeId, $year) : [];
    $rows = $employee !== null ? db()->all(
        'SELECT leave_type_id, entitled_days FROM leave_balances WHERE user_id = ? AND year = ?',
        [$employeeId, $year]
    ) : [];
    $overrides = [];
    foreach ($rows as $row) {
        $overrides[(int) $row['leave_type_id']] = (float) $row['entitled_days'];
    }
}

$pageTitle = 'Leave entitlements';
$topbarTitle = 'HR operations';
$activeNav = 'leave-requests';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Leave entitlements</h1>
        <p class="page-subtitle">Employee-specific entitlement overrides. Leave blank to use the leave type default.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('leave/types.php')) ?>"><?= icon('sliders') ?> Leave types</a>
    </div>
</div>

<section class="panel" aria-labelledby="pickerHeading">
    <div class="panel-header"><h2 id="pickerHeading" class="visually-hidden">Select employee</h2></div>
    <div class="panel-body pt-3">
        <form class="toolbar" method="get" action="<?= e(url('leave/balances.php')) ?>">
            <div class="field field-grow">
                <label for="user">Employee</label>
                <select class="form-select" id="user" name="user" required>
                    <option value="">Select an employee…</option>
                    <?php foreach ($employees as $option): ?>
                        <option value="<?= (int) $option['id'] ?>" <?= selected($employeeId, (int) $option['id']) ?>>
                            <?= e(person_name($option)) ?> · <?= e((string) ($option['employee_number'] ?? '—')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="year">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php for ($y = current_year() + 1; $y >= current_year() - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= selected($year, $y) ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="field">
                <label class="d-none d-sm-block">&nbsp;</label>
                <button class="btn btn-primary" type="submit"><?= icon('search') ?> Load</button>
            </div>
        </form>
    </div>
</section>

<?php if ($employee === null): ?>
    <section class="panel">
        <div class="empty-state">
            <div class="empty-icon"><?= icon('sliders') ?></div>
            <h3>Select an employee</h3>
            <p>Choose an employee above to review and adjust their leave entitlements for a year.</p>
        </div>
    </section>
<?php else: ?>
    <?php include BASE_PATH . '/includes/partials/errors.php'; ?>
    <div class="content-grid content-grid-main">
        <section class="panel" aria-labelledby="formHeading">
            <div class="panel-header">
                <div>
                    <h2 id="formHeading"><?= e(person_name($employee)) ?></h2>
                    <div class="meta"><?= e((string) ($employee['employee_number'] ?? '')) ?> · <?= (int) $year ?></div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('employees/view.php?id=' . $employeeId)) ?>">Open profile</a>
            </div>
            <div class="panel-body">
                <form method="post" action="<?= e(url('leave/balances.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user" value="<?= $employeeId ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">
                    <div class="table-wrap">
                        <table class="table-app">
                            <thead>
                                <tr>
                                    <th scope="col">Leave type</th>
                                    <th scope="col" class="text-end">Default</th>
                                    <th scope="col" class="text-end">Approved</th>
                                    <th scope="col" class="text-end">Pending</th>
                                    <th scope="col" style="min-width: 140px;">Override (days)</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($balances as $balance): ?>
                                <?php $typeId = (int) $balance['type']['id']; ?>
                                <tr>
                                    <td><?= e((string) $balance['type']['name']) ?></td>
                                    <td class="text-end"><?= e(format_days((float) $balance['type']['annual_entitlement'])) ?></td>
                                    <td class="text-end"><?= e(format_days((float) $balance['approved'])) ?></td>
                                    <td class="text-end"><?= e(format_days((float) $balance['pending'])) ?></td>
                                    <td>
                                        <label class="visually-hidden" for="entitlement-<?= $typeId ?>">Override days for <?= e((string) $balance['type']['name']) ?></label>
                                        <input class="form-control form-control-sm" type="number" id="entitlement-<?= $typeId ?>"
                                               name="entitlement[<?= $typeId ?>]" min="0" max="365" step="0.5"
                                               value="<?= array_key_exists($typeId, $overrides) ? e((string) $overrides[$typeId]) : '' ?>"
                                               placeholder="<?= e(format_days((float) $balance['type']['annual_entitlement'])) ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="note">Note (applies to changed rows)</label>
                        <input class="form-control" type="text" id="note" name="note" maxlength="255" placeholder="e.g. Adjustment approved by HR director">
                    </div>
                    <div class="form-actions">
                        <span class="spacer"></span>
                        <button class="btn btn-primary" type="submit"><?= icon('check2') ?> Save entitlements</button>
                    </div>
                </form>
            </div>
        </section>

        <aside class="panel" aria-labelledby="summaryHeading">
            <div class="panel-header"><div><h2 id="summaryHeading">Summary <?= (int) $year ?></h2><div class="meta">Working days</div></div></div>
            <div class="panel-body">
                <div class="mini-list">
                    <?php foreach ($balances as $balance): ?>
                        <div class="row-line">
                            <span><?= e((string) $balance['type']['name']) ?></span>
                            <span><?= e(format_days((float) $balance['remaining'])) ?> left</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="notice mt-3">
                    <?= icon('info-circle') ?>
                    <div>Clearing an override restores the leave type's default annual entitlement.</div>
                </div>
            </div>
        </aside>
    </div>
<?php endif; ?>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
