<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$bySex = report_by_sex();
$byType = report_by_employment_type();
$byStatus = report_by_status();
$total = 0;
foreach ($byStatus as $row) {
    $total += (int) $row['total'];
}

$renderBars = static function (array $rows, int $total): void {
    foreach ($rows as $row) {
        $share = $total > 0 ? ((int) $row['total'] / $total) * 100 : 0;
        echo '<div class="bar-row">';
        echo '<div class="bar-top"><strong>' . e((string) $row['label']) . '</strong>';
        echo '<span>' . (int) $row['total'] . ' · ' . e((string) round($share)) . '%</span></div>';
        echo '<div class="progress-thin"><div class="bar" style="width: ' . e((string) round($share, 1)) . '%"></div></div>';
        echo '</div>';
    }
};

$pageTitle = 'Workforce profile';
$topbarTitle = 'Workforce reporting';
$activeNav = 'reports';
include BASE_PATH . '/includes/layout/header.php';
?>
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb breadcrumb-ess">
        <li class="breadcrumb-item"><a href="<?= e(url('reports/index.php')) ?>">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Workforce profile</li>
    </ol>
</nav>
<div class="page-header">
    <div>
        <h1 class="page-title">Workforce profile</h1>
        <p class="page-subtitle"><?= number_format($total) ?> non-archived employees</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-outline-secondary" type="button" data-print><?= icon('printer') ?> Print</button>
    </div>
</div>

<?php if ($total === 0): ?>
    <section class="panel">
        <div class="empty-state">
            <div class="empty-icon"><?= icon('people') ?></div>
            <h3>No employee data</h3>
            <p>Add employees to generate workforce distribution reports.</p>
            <a class="btn btn-primary" href="<?= e(url('employees/create.php')) ?>">Add employee</a>
        </div>
    </section>
<?php else: ?>
    <div class="content-grid content-grid-3">
        <section class="panel" aria-labelledby="sexHeading">
            <div class="panel-header"><div><h2 id="sexHeading">By sex</h2><div class="meta">Non-archived records</div></div></div>
            <div class="panel-body"><div class="bar-list"><?php $renderBars($bySex, $total); ?></div></div>
        </section>
        <section class="panel" aria-labelledby="typeHeading">
            <div class="panel-header"><div><h2 id="typeHeading">By employment type</h2><div class="meta">Permanent, contract, temporary, intern</div></div></div>
            <div class="panel-body"><div class="bar-list"><?php $renderBars($byType, $total); ?></div></div>
        </section>
        <section class="panel" aria-labelledby="statusHeading">
            <div class="panel-header"><div><h2 id="statusHeading">By employment status</h2><div class="meta">Includes on leave and suspended</div></div></div>
            <div class="panel-body"><div class="bar-list"><?php $renderBars($byStatus, $total); ?></div></div>
        </section>
    </div>

    <section class="panel" aria-labelledby="detailHeading">
        <div class="panel-header"><div><h2 id="detailHeading">Detailed counts</h2><div class="meta">Use these figures in narrative reporting</div></div></div>
        <div class="table-wrap">
            <table class="table-app">
                <thead>
                    <tr><th scope="col">Dimension</th><th scope="col">Category</th><th scope="col" class="text-end">Employees</th><th scope="col" class="text-end">Share</th></tr>
                </thead>
                <tbody>
                <?php foreach ([['Sex', $bySex], ['Employment type', $byType], ['Employment status', $byStatus]] as [$label, $rows]): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-muted"><?= e($label) ?></td>
                            <td><?= e((string) $row['label']) ?></td>
                            <td class="text-end"><?= (int) $row['total'] ?></td>
                            <td class="text-end"><?= $total > 0 ? e((string) round(((int) $row['total'] / $total) * 100, 1)) : '0' ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
