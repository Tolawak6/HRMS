<?php
defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

if (!isset($pagination) || !is_array($pagination)) {
    return;
}
$paginationPath = $paginationPath ?? current_route();
$page = (int) $pagination['page'];
$pages = (int) $pagination['pages'];
$total = (int) $pagination['total'];
if ($total === 0) {
    return;
}

$windowStart = max(1, $page - 2);
$windowEnd = min($pages, $page + 2);
$pageNumbers = [];
for ($i = $windowStart; $i <= $windowEnd; $i++) {
    $pageNumbers[] = $i;
}
if ($windowStart > 1) {
    array_unshift($pageNumbers, 1);
    if ($windowStart > 2) {
        array_splice($pageNumbers, 1, 0, ['…']);
    }
}
if ($windowEnd < $pages) {
    $pageNumbers[] = $pages;
    if ($windowEnd < $pages - 1) {
        array_splice($pageNumbers, count($pageNumbers) - 1, 0, ['…']);
    }
}
?>
<div class="pagination-bar">
    <div>Showing <strong><?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?></strong> of <strong><?= (int) $total ?></strong></div>
    <?php if ($pages > 1): ?>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= $page <= 1 ? '#' : e(query_url($paginationPath, ['page' => $page - 1])) ?>"
                       <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
                </li>
                <?php foreach ($pageNumbers as $number): ?>
                    <?php if ($number === '…'): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php else: ?>
                        <li class="page-item<?= (int) $number === $page ? ' active' : '' ?>">
                            <a class="page-link" href="<?= e(query_url($paginationPath, ['page' => (int) $number])) ?>"
                               <?= (int) $number === $page ? 'aria-current="page"' : '' ?>><?= (int) $number ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= $page >= $pages ? '#' : e(query_url($paginationPath, ['page' => $page + 1])) ?>"
                       <?= $page >= $pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
