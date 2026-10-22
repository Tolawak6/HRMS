<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin', 'hr_staff']);
$errors = [];
$editId = get_int('edit');
$editing = $editId > 0 ? position_find($editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Position not found', 'The requested position does not exist.');
}

$departments = departments_all();
$departmentFilter = max(0, get_int('department'));

$values = [
    'title' => (string) ($editing['title'] ?? ''),
    'code' => (string) ($editing['code'] ?? ''),
    'department_id' => (string) ($editing['department_id'] ?? ''),
    'grade' => (string) ($editing['grade'] ?? ''),
    'description' => (string) ($editing['description'] ?? ''),
];

if (is_post()) {
    $action = post_str('action');
    if ($action === 'save') {
        $values = [
            'title' => post_str('title'),
            'code' => post_str('code'),
            'department_id' => (string) post_int('department_id'),
            'grade' => post_str('grade'),
            'description' => post_str('description'),
        ];
        $outcome = position_save($editId > 0 ? $editId : null, $values, $actor);
        if ($outcome['ok']) {
            flash('success', $editId > 0 ? 'Position updated.' : 'Position created.');
            redirect('organization/positions.php');
        }
        $errors = $outcome['errors'] ?? ['_form' => 'The position could not be saved.'];
    } elseif ($action === 'toggle') {
        $outcome = position_toggle(post_int('id'), $actor);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Position status updated.' : (string) ($outcome['error'] ?? 'Unable to change status.'));
        redirect('organization/positions.php');
    }
}

$positions = positions_all(false, $departmentFilter > 0 ? $departmentFilter : null);

$pageTitle = 'Positions';
$topbarTitle = 'Organisation';
$activeNav = 'positions';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Positions</h1>
        <p class="page-subtitle"><?= count($positions) ?> position<?= count($positions) === 1 ? '' : 's' ?> in the current view</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('organization/departments.php')) ?>"><?= icon('building') ?> Departments</a>
        <?php if ($editing !== null): ?>
            <a class="btn btn-quiet" href="<?= e(url('organization/positions.php')) ?>"><?= icon('x-lg') ?> Cancel editing</a>
        <?php endif; ?>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="listHeading">
        <div class="panel-header">
            <div>
                <h2 id="listHeading">Position catalogue</h2>
                <div class="meta">Titles are unique within a department.</div>
            </div>
        </div>
        <div class="panel-body pt-3">
            <form class="toolbar" method="get" action="<?= e(url('organization/positions.php')) ?>">
                <div class="field">
                    <label for="deptFilter">Department</label>
                    <select class="form-select" id="deptFilter" name="department">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department['id'] ?>" <?= selected($departmentFilter, (int) $department['id']) ?>>
                                <?= e((string) $department['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="d-none d-sm-block">&nbsp;</label>
                    <button class="btn btn-primary" type="submit"><?= icon('filter') ?> Filter</button>
                </div>
                <?php if ($departmentFilter > 0): ?>
                    <div class="field">
                        <label class="d-none d-sm-block">&nbsp;</label>
                        <a class="btn btn-quiet" href="<?= e(url('organization/positions.php')) ?>"><?= icon('x-lg') ?> Clear</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php if ($positions === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('briefcase') ?></div>
                <h3>No positions found</h3>
                <p><?= $departmentFilter > 0 ? 'No positions exist for this department yet.' : 'Create the first position using the form on this page.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead>
                        <tr>
                            <th scope="col">Position</th>
                            <th scope="col">Department</th>
                            <th scope="col">Grade</th>
                            <th scope="col" class="text-end">Employees</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($positions as $position): ?>
                        <tr>
                            <td>
                                <span class="row-title"><?= e((string) $position['title']) ?></span>
                                <div class="row-meta"><?= e((string) $position['code']) ?></div>
                            </td>
                            <td><?= e((string) ($position['department_name'] ?? 'Unassigned')) ?></td>
                            <td><?= e((string) ($position['grade'] ?? '—')) ?></td>
                            <td class="text-end"><?= (int) $position['employee_count'] ?></td>
                            <td><span class="badge badge-soft-<?= (int) $position['is_active'] === 1 ? 'success' : 'neutral' ?>"><?= (int) $position['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td class="col-actions">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-quiet" href="<?= e(url('organization/positions.php?edit=' . (int) $position['id'])) ?>"><?= icon('pencil') ?><span class="visually-hidden">Edit <?= e((string) $position['title']) ?></span></a>
                                    <form method="post" action="<?= e(url('organization/positions.php')) ?>"
                                          data-confirm="<?= (int) $position['is_active'] === 1 ? 'Deactivate this position? Existing employee records are not changed.' : 'Reactivate this position?' ?>"
                                          data-confirm-title="<?= (int) $position['is_active'] === 1 ? 'Deactivate position' : 'Reactivate position' ?>"
                                          data-confirm-button="<?= (int) $position['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>"
                                          data-confirm-variant="<?= (int) $position['is_active'] === 1 ? 'danger' : 'success' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $position['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $position['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel" aria-labelledby="formHeading">
        <div class="panel-header">
            <div>
                <h2 id="formHeading"><?= $editing !== null ? 'Edit position' : 'New position' ?></h2>
                <div class="meta">Codes must be unique.</div>
            </div>
        </div>
        <div class="panel-body">
            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>
            <form method="post" action="<?= e(url('organization/positions.php' . ($editId > 0 ? '?edit=' . $editId : ''))) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <div class="mb-3">
                    <label class="form-label" for="title">Position title <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'title') ?>" type="text" id="title" name="title"
                           value="<?= e($values['title']) ?>" maxlength="120" required>
                    <?php if (field_error($errors, 'title') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'title')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="code">Code <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'code') ?>" type="text" id="code" name="code"
                           value="<?= e($values['code']) ?>" maxlength="30" required>
                    <?php if (field_error($errors, 'code') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'code')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="department_id">Department</label>
                    <select class="form-select<?= invalid_class($errors, 'department_id') ?>" id="department_id" name="department_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department['id'] ?>" <?= selected($values['department_id'], (string) $department['id']) ?>
                                    <?= (int) $department['is_active'] === 0 ? 'disabled' : '' ?>>
                                <?= e((string) $department['name']) ?><?= (int) $department['is_active'] === 0 ? ' (inactive)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (field_error($errors, 'department_id') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'department_id')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="grade">Grade</label>
                    <input class="form-control<?= invalid_class($errors, 'grade') ?>" type="text" id="grade" name="grade"
                           value="<?= e($values['grade']) ?>" maxlength="30" placeholder="e.g. P5">
                    <?php if (field_error($errors, 'grade') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'grade')) ?></div><?php endif; ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description"
                              rows="3" maxlength="255"><?= e($values['description']) ?></textarea>
                    <?php if (field_error($errors, 'description') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'description')) ?></div><?php endif; ?>
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <button class="btn btn-primary" type="submit"><?= icon($editing !== null ? 'check2' : 'plus-lg') ?> <?= $editing !== null ? 'Save changes' : 'Create position' ?></button>
                </div>
            </form>
        </div>
    </aside>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
