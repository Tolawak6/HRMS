<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$filters = employee_filters();
$result = employee_search($filters);
$rows = $result['rows'];
$pagination = $result['pagination'];
$departments = departments_all();

if (is_post()) {
    $action = post_str('action');
    $employeeId = post_int('id');
    if ($action === 'restore') {
        $outcome = employee_restore($employeeId, $user);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Employee restored. A password change is required at next sign-in.' : (string) ($outcome['error'] ?? 'Unable to restore.'));
        redirect(query_url('employees/index.php', []));
    }
    if ($action === 'archive') {
        $outcome = employee_archive($employeeId, post_str('reason'), $user);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Employee archived.' : (string) ($outcome['error'] ?? 'Unable to archive.'));
        redirect(query_url('employees/index.php', []));
    }
}

$hasFilters = $filters['q'] !== '' || $filters['department'] > 0 || $filters['status'] !== ''
    || $filters['sex'] !== '' || $filters['archived'] !== 'active';

$sortLink = static function (string $key, string $label) use ($filters): string {
    $direction = ($filters['sort'] === $key && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $target = query_url('employees/index.php', ['sort' => $key, 'dir' => $direction]);
    $indicator = '';
    if ($filters['sort'] === $key) {
        $indicator = ' ' . icon($filters['dir'] === 'asc' ? 'arrow-up' : 'arrow-down', 'sort-icon');
    }
    $ariaSort = $filters['sort'] === $key ? ($filters['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<a href="' . e($target) . '" aria-label="Sort by ' . e($label) . '">' . e($label) . $indicator . '</a>';
};

$exportQuery = $_GET;
unset($exportQuery['page']);
$exportUrl = url('employees/export.php') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');

$pageTitle = 'Employees';
$topbarTitle = 'HR Workspace';
$activeNav = 'employees';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Employees</h1>
        <p class="page-subtitle"><?= number_format((int) $pagination['total']) ?> record<?= (int) $pagination['total'] === 1 ? '' : 's' ?> in the current view</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e($exportUrl) ?>"><?= icon('filetype-csv') ?> Export CSV</a>
        <a class="btn btn-primary" href="<?= e(url('employees/create.php')) ?>"><?= icon('person-plus') ?> Add employee</a>
    </div>
</div>

<section class="panel" aria-labelledby="filtersHeading">
    <div class="panel-header">
        <h2 id="filtersHeading" class="visually-hidden">Search and filters</h2>
    </div>
    <div class="panel-body pt-3">
        <form class="toolbar" method="get" action="<?= e(url('employees/index.php')) ?>" role="search">
            <div class="field field-grow">
                <label for="filterQ">Search</label>
                <input class="form-control" type="search" id="filterQ" name="q" value="<?= e($filters['q']) ?>"
                       placeholder="Name, employee number, username or email">
            </div>
            <div class="field">
                <label for="filterDepartment">Department</label>
                <select class="form-select" id="filterDepartment" name="department">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= selected($filters['department'], (int) $department['id']) ?>>
                            <?= e((string) $department['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="filterStatus">Status</label>
                <select class="form-select" id="filterStatus" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (employment_statuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected($filters['status'], $status) ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="filterSex">Sex</label>
                <select class="form-select" id="filterSex" name="sex">
                    <option value="">All</option>
                    <?php foreach (sex_options() as $option): ?>
                        <option value="<?= e($option) ?>" <?= selected($filters['sex'], $option) ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="filterArchived">Records</label>
                <select class="form-select" id="filterArchived" name="archived">
                    <option value="active" <?= selected($filters['archived'], 'active') ?>>Active records</option>
                    <option value="archived" <?= selected($filters['archived'], 'archived') ?>>Archived</option>
                    <option value="all" <?= selected($filters['archived'], 'all') ?>>All records</option>
                </select>
            </div>
            <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
            <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">
            <div class="field">
                <label class="d-none d-sm-block">&nbsp;</label>
                <button class="btn btn-primary" type="submit"><?= icon('search') ?> Apply</button>
            </div>
            <?php if ($hasFilters): ?>
                <div class="field">
                    <label class="d-none d-sm-block">&nbsp;</label>
                    <a class="btn btn-quiet" href="<?= e(url('employees/index.php')) ?>"><?= icon('x-lg') ?> Clear</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="panel" aria-labelledby="listHeading">
    <div class="panel-header">
        <div>
            <h2 id="listHeading">Employee directory</h2>
            <div class="meta">Role: self-service staff records</div>
        </div>
    </div>
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= icon($hasFilters ? 'search' : 'people') ?></div>
            <h3><?= $hasFilters ? 'No employees match these filters' : 'No employee records yet' ?></h3>
            <p><?= $hasFilters ? 'Try a different search term or clear the filters.' : 'Create the first employee record to build the directory.' ?></p>
            <?php if ($hasFilters): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('employees/index.php')) ?>">Clear filters</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('employees/create.php')) ?>"><?= icon('person-plus') ?> Add employee</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead>
                    <tr>
                        <th scope="col" aria-sort="<?= $filters['sort'] === 'name' ? ($filters['dir'] === 'asc' ? 'ascending' : 'descending') : 'none' ?>"><?= $sortLink('name', 'Employee') ?></th>
                        <th scope="col">Department / position</th>
                        <th scope="col">Employment</th>
                        <th scope="col">Status</th>
                        <th scope="col" aria-sort="<?= $filters['sort'] === 'hire_date' ? ($filters['dir'] === 'asc' ? 'ascending' : 'descending') : 'none' ?>"><?= $sortLink('hire_date', 'Hired') ?></th>
                        <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar avatar-sm">
                                    <img src="<?= e(employee_photo_url($row)) ?>" alt="" loading="lazy">
                                </span>
                                <div>
                                    <a class="row-title" href="<?= e(url('employees/view.php?id=' . (int) $row['id'])) ?>">
                                        <?= e(person_name($row)) ?>
                                    </a>
                                    <div class="row-meta">
                                        <?= e((string) ($row['employee_number'] ?? 'No number')) ?>
                                        <?php if (!empty($row['archived_at'])): ?>
                                            · <span class="text-danger">Archived</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?= e((string) ($row['department_name'] ?? 'Unassigned')) ?>
                            <div class="row-meta"><?= e((string) ($row['position_title'] ?? '—')) ?></div>
                        </td>
                        <td>
                            <?= e((string) ($row['employment_type'] ?? '—')) ?>
                            <div class="row-meta"><?= (int) $row['document_count'] ?> document<?= (int) $row['document_count'] === 1 ? '' : 's' ?></div>
                        </td>
                        <td><span class="badge badge-soft-<?= e(status_badge_class((string) $row['employment_status'])) ?>"><?= e((string) $row['employment_status']) ?></span></td>
                        <td class="text-nowrap"><?= e(format_date($row['hire_date'] ?? null)) ?></td>
                        <td class="col-actions">
                            <div class="table-actions">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('employees/view.php?id=' . (int) $row['id'])) ?>">View</a>
                                <?php if (empty($row['archived_at'])): ?>
                                    <a class="btn btn-sm btn-quiet" href="<?= e(url('employees/edit.php?id=' . (int) $row['id'])) ?>"><?= icon('pencil') ?><span class="visually-hidden">Edit <?= e(person_name($row)) ?></span></a>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('employees/index.php')) ?>"
                                          data-confirm="Restore this employee record and re-enable self-service access?"
                                          data-confirm-title="Restore employee" data-confirm-button="Restore"
                                          data-confirm-variant="success">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Restore</button>
                                    </form>
                                <?php endif; ?>
                            </div>
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
