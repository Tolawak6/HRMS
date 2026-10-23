<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$year = max(2000, min(2100, get_int('year', current_year())));
$summary = report_summary($year);
$byDepartment = report_by_department();
$leaveByType = report_leave_by_type($year);
$hires = report_hires_by_year($year - 5, $year);

$departmentTotal = 0;
foreach ($byDepartment as $row) {
    $departmentTotal += (int) $row['headcount'];
}
$hireMax = 0;
foreach ($hires as $row) {
    $hireMax = max($hireMax, (int) $row['total']);
}

$pageTitle = 'Reports';
$topbarTitle = 'Workforce reporting';
$activeNav = 'reports';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Workforce reports</h1>
        <p class="page-subtitle">Figures calculated directly from current employee and leave records for <?= (int) $year ?></p>
    </div>
    <div class="page-actions">
        <form class="d-flex gap-2" method="get" action="<?= e(url('reports/index.php')) ?>">
            <label class="visually-hidden" for="year">Report year</label>
            <select class="form-select" id="year" name="year">
                <?php for ($y = current_year(); $y >= current_year() - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= selected($year, $y) ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-outline-secondary" type="submit">Apply</button>
        </form>
        <button class="btn btn-outline-secondary" type="button" data-print><?= icon('printer') ?> Print</button>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><?= icon('people') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format((int) $summary['headcount']) ?></div>
            <div class="stat-label">Employees on record</div>
            <div class="stat-meta">Non-archived records</div>
        </div>
    </div>
    <div class="stat-card accent-green">
        <div class="stat-icon is-green"><?= icon('person-check') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format((int) $summary['active']) ?></div>
            <div class="stat-label">Active</div>
            <div class="stat-meta"><?= number_format((int) $summary['on_leave']) ?> on leave</div>
        </div>
    </div>
    <div class="stat-card accent-tan">
        <div class="stat-icon is-gold"><?= icon('person-plus') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format((int) $summary['new_hires']) ?></div>
            <div class="stat-label">New hires in <?= (int) $year ?></div>
            <div class="stat-meta">By hire date</div>
        </div>
    </div>
    <div class="stat-card accent-danger">
        <div class="stat-icon is-neutral"><?= icon('archive') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format((int) $summary['archived']) ?></div>
            <div class="stat-label">Archived records</div>
            <div class="stat-meta">Retained for history</div>
        </div>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="deptHeading">
        <div class="panel-header">
            <div><h2 id="deptHeading">Headcount by department</h2><div class="meta"><?= number_format($departmentTotal) ?> employees</div></div>
            <a class="btn btn-sm btn-quiet" href="<?= e(url('reports/by_department.php')) ?>">Detailed report</a>
        </div>
        <div class="panel-body">
            <div class="bar-list">
                <?php foreach ($byDepartment as $row): ?>
                    <?php $pct = $departmentTotal > 0 ? ((int) $row['headcount'] / $departmentTotal) * 100 : 0; ?>
                    <div class="bar-row">
                        <div class="bar-top">
                            <strong><?= e((string) $row['label']) ?></strong>
                            <span><?= (int) $row['headcount'] ?> · <?= e((string) round($pct)) ?>%</span>
                        </div>
                        <div class="progress-thin">
                            <div class="bar" style="width: <?= e((string) round($pct, 1)) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($byDepartment === []): ?><p class="text-muted mb-0">No employee data.</p><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel" aria-labelledby="leaveHeading">
        <div class="panel-header">
            <div><h2 id="leaveHeading">Leave in <?= (int) $year ?></h2><div class="meta">Approved and pending working days</div></div>
            <a class="btn btn-sm btn-quiet" href="<?= e(url('reports/leave.php?year=' . $year)) ?>">Leave report</a>
        </div>
        <div class="panel-body">
            <div class="mini-list">
                <?php foreach ($leaveByType as $row): ?>
                    <div class="row-line">
                        <span><?= e((string) $row['label']) ?></span>
                        <span>
                            <?= e(format_days((float) $row['approved_days'])) ?> approved
                            <?php if ((float) $row['pending_days'] > 0): ?>· <?= e(format_days((float) $row['pending_days'])) ?> pending<?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="divider"></div>
            <div class="mini-list">
                <div class="row-line"><span>Approved requests</span><span><?= number_format((int) $summary['approved_leave_requests']) ?></span></div>
                <div class="row-line"><span>Pending requests</span><span><?= number_format((int) $summary['pending_leave_requests']) ?></span></div>
            </div>
        </div>
    </section>
</div>

<section class="panel" aria-labelledby="hiresHeading">
    <div class="panel-header">
        <div><h2 id="hiresHeading">Hires by year</h2><div class="meta"><?= (int) ($year - 5) ?> – <?= (int) $year ?></div></div>
    </div>
    <div class="panel-body">
        <div class="bar-list">
            <?php foreach ($hires as $row): ?>
                <?php $pct = $hireMax > 0 ? ((int) $row['total'] / $hireMax) * 100 : 0; ?>
                <div class="bar-row">
                    <div class="bar-top">
                        <strong><?= (int) $row['year'] ?></strong>
                        <span><?= (int) $row['total'] ?> hire<?= (int) $row['total'] === 1 ? '' : 's' ?></span>
                    </div>
                    <div class="progress-thin">
                        <div class="bar is-green" style="width: <?= e((string) round($pct, 1)) ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="content-grid content-grid-3">
    <a class="panel text-decoration-none" href="<?= e(url('reports/by_department.php')) ?>">
        <div class="panel-body">
            <div class="stat-icon mb-3"><?= icon('building') ?></div>
            <h2 class="h6 fw-bold mb-1">Department report</h2>
            <p class="text-muted small mb-0">Headcount, active and on-leave totals per department.</p>
        </div>
    </a>
    <a class="panel text-decoration-none" href="<?= e(url('reports/demographics.php')) ?>">
        <div class="panel-body">
            <div class="stat-icon is-gold mb-3"><?= icon('people') ?></div>
            <h2 class="h6 fw-bold mb-1">Workforce profile</h2>
            <p class="text-muted small mb-0">Sex, employment type and employment status distribution.</p>
        </div>
    </a>
    <a class="panel text-decoration-none" href="<?= e(url('reports/leave.php?year=' . $year)) ?>">
        <div class="panel-body">
            <div class="stat-icon is-green mb-3"><?= icon('calendar2-week') ?></div>
            <h2 class="h6 fw-bold mb-1">Leave report</h2>
            <p class="text-muted small mb-0">Leave usage by type and status with CSV export.</p>
        </div>
    </a>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
