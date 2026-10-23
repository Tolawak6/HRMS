<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$year = max(2000, min(2100, get_int('year', current_year())));
$byType = report_leave_by_type($year);
$byStatus = report_leave_by_status($year);
$totalRequests = 0;
$totalDays = 0.0;
foreach ($byStatus as $row) {
    $totalRequests += (int) $row['total'];
    $totalDays += (float) $row['days'];
}
$maxApproved = 0;
foreach ($byType as $row) {
    $maxApproved = max($maxApproved, (float) $row['approved_days']);
}

$pageTitle = 'Leave report';
$topbarTitle = 'Workforce reporting';
$activeNav = 'reports';
include BASE_PATH . '/includes/layout/header.php';
?>
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb breadcrumb-ess">
        <li class="breadcrumb-item"><a href="<?= e(url('reports/index.php')) ?>">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Leave</li>
    </ol>
</nav>
<div class="page-header">
    <div>
        <h1 class="page-title">Leave report <?= (int) $year ?></h1>
        <p class="page-subtitle"><?= number_format($totalRequests) ?> requests · <?= e(format_days($totalDays)) ?> working days recorded</p>
    </div>
    <div class="page-actions">
        <form class="d-flex gap-2" method="get" action="<?= e(url('reports/leave.php')) ?>">
            <label class="visually-hidden" for="year">Report year</label>
            <select class="form-select" id="year" name="year">
                <?php for ($y = current_year(); $y >= current_year() - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= selected($year, $y) ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-outline-secondary" type="submit">Apply</button>
        </form>
        <a class="btn btn-outline-secondary" href="<?= e(url('reports/export.php?type=leave&year=' . $year)) ?>"><?= icon('filetype-csv') ?> Export CSV</a>
        <button class="btn btn-outline-secondary" type="button" data-print><?= icon('printer') ?> Print</button>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="typeHeading">
        <div class="panel-header"><div><h2 id="typeHeading">Leave by type</h2><div class="meta">Approved working days in <?= (int) $year ?></div></div></div>
        <div class="panel-body">
            <div class="bar-list">
                <?php foreach ($byType as $row): ?>
                    <?php $pct = $maxApproved > 0 ? ((float) $row['approved_days'] / $maxApproved) * 100 : 0; ?>
                    <div class="bar-row">
                        <div class="bar-top">
                            <strong><?= e((string) $row['label']) ?></strong>
                            <span><?= (int) $row['requests'] ?> request(s) · <?= e(format_days((float) $row['approved_days'])) ?> approved</span>
                        </div>
                        <div class="progress-thin">
                            <div class="bar is-green" style="width: <?= e((string) round($pct, 1)) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="panel" aria-labelledby="statusHeading">
        <div class="panel-header"><div><h2 id="statusHeading">Requests by status</h2><div class="meta">All submissions starting in <?= (int) $year ?></div></div></div>
        <div class="panel-body">
            <?php if ($byStatus === []): ?>
                <p class="text-muted mb-0">No leave requests recorded for this year.</p>
            <?php else: ?>
                <div class="mini-list">
                    <?php foreach ($byStatus as $row): ?>
                        <div class="row-line">
                            <span><span class="badge badge-soft-<?= e(status_badge_class((string) $row['label'])) ?>"><?= e(ucfirst((string) $row['label'])) ?></span></span>
                            <span><?= (int) $row['total'] ?> · <?= e(format_days((float) $row['days'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="divider"></div>
                <div class="mini-list">
                    <div class="row-line"><span>Total requests</span><span><?= number_format($totalRequests) ?></span></div>
                    <div class="row-line"><span>Total working days</span><span><?= e(format_days($totalDays)) ?></span></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel" aria-labelledby="detailHeading">
    <div class="panel-header">
        <div><h2 id="detailHeading">Detail by type</h2><div class="meta">Includes inactive leave types with historical requests</div></div>
        <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/requests.php?year=' . $year)) ?>">Open requests</a>
    </div>
    <div class="table-wrap">
        <table class="table-app">
            <thead>
                <tr><th scope="col">Leave type</th><th scope="col" class="text-end">Requests</th><th scope="col" class="text-end">Approved days</th><th scope="col" class="text-end">Pending days</th></tr>
            </thead>
            <tbody>
            <?php foreach ($byType as $row): ?>
                <tr>
                    <td><?= e((string) $row['label']) ?></td>
                    <td class="text-end"><?= (int) $row['requests'] ?></td>
                    <td class="text-end"><?= e(format_days((float) $row['approved_days'])) ?></td>
                    <td class="text-end"><?= e(format_days((float) $row['pending_days'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
