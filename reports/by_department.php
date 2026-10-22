<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$rows = report_by_department();
$total = 0;
foreach ($rows as $row) {
    $total += (int) $row['headcount'];
}

$pageTitle = 'Department report';
$topbarTitle = 'Workforce reporting';
$activeNav = 'reports';
include BASE_PATH . '/includes/layout/header.php';
?>
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb breadcrumb-ess">
        <li class="breadcrumb-item"><a href="<?= e(url('reports/index.php')) ?>">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">By department</li>
    </ol>
</nav>
<div class="page-header">
    <div>
        <h1 class="page-title">Headcount by department</h1>
        <p class="page-subtitle"><?= number_format($total) ?> non-archived employees · <?= count($rows) ?> groups</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-outline-secondary" type="button" data-print><?= icon('printer') ?> Print</button>
    </div>
</div>

<section class="panel" aria-labelledby="tableHeading">
    <div class="panel-header"><div><h2 id="tableHeading">Distribution</h2><div class="meta">Active employees exclude those on leave or suspended</div></div></div>
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= icon('building') ?></div>
            <h3>No data available</h3>
            <p>Create employee records and assign them to departments.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead>
                    <tr>
                        <th scope="col">Department</th>
                        <th scope="col" class="text-end">Headcount</th>
                        <th scope="col" class="text-end">Active</th>
                        <th scope="col" class="text-end">On leave</th>
                        <th scope="col" class="text-end">Share</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $share = $total > 0 ? ((int) $row['headcount'] / $total) * 100 : 0; ?>
                    <tr>
                        <td><span class="row-title"><?= e((string) $row['label']) ?></span></td>
                        <td class="text-end"><?= (int) $row['headcount'] ?></td>
                        <td class="text-end"><?= (int) $row['active'] ?></td>
                        <td class="text-end"><?= (int) $row['on_leave'] ?></td>
                        <td class="text-end"><?= e((string) round($share)) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total</th>
                        <th class="text-end"><?= $total ?></th>
                        <th class="text-end"><?= array_sum(array_map(static fn ($r) => (int) $r['active'], $rows)) ?></th>
                        <th class="text-end"><?= array_sum(array_map(static fn ($r) => (int) $r['on_leave'], $rows)) ?></th>
                        <th class="text-end">100%</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
