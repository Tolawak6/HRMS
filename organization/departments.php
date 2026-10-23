<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin', 'hr_staff']);
$errors = [];
$editId = get_int('edit');
$editing = $editId > 0 ? department_find($editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Department not found', 'The requested department does not exist.');
}

$values = [
    'name' => (string) ($editing['name'] ?? ''),
    'code' => (string) ($editing['code'] ?? ''),
    'description' => (string) ($editing['description'] ?? ''),
];

if (is_post()) {
    $action = post_str('action');
    if ($action === 'save') {
        $values = [
            'name' => post_str('name'),
            'code' => post_str('code'),
            'description' => post_str('description'),
        ];
        $outcome = department_save($editId > 0 ? $editId : null, $values, $actor);
        if ($outcome['ok']) {
            flash('success', $editId > 0 ? 'Department updated.' : 'Department created.');
            redirect('organization/departments.php');
        }
        $errors = $outcome['errors'] ?? ['_form' => 'The department could not be saved.'];
    } elseif ($action === 'toggle') {
        $outcome = department_toggle(post_int('id'), $actor);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Department status updated.' : (string) ($outcome['error'] ?? 'Unable to change status.'));
        redirect('organization/departments.php');
    }
}

$departments = departments_all();
$activeCount = 0;
foreach ($departments as $department) {
    if ((int) $department['is_active'] === 1) {
        $activeCount++;
    }
}

$pageTitle = 'Departments';
$topbarTitle = 'Organisation';
$activeNav = 'departments';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Departments</h1>
        <p class="page-subtitle"><?= count($departments) ?> departments · <?= $activeCount ?> active</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('organization/positions.php')) ?>"><?= icon('briefcase') ?> Positions</a>
        <?php if ($editing !== null): ?>
            <a class="btn btn-quiet" href="<?= e(url('organization/departments.php')) ?>"><?= icon('x-lg') ?> Cancel editing</a>
        <?php endif; ?>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="listHeading">
        <div class="panel-header">
            <div>
                <h2 id="listHeading">All departments</h2>
                <div class="meta">Deactivating a department keeps its history but removes it from new assignments.</div>
            </div>
        </div>
        <?php if ($departments === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('building') ?></div>
                <h3>No departments yet</h3>
                <p>Create the first department using the form on this page.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead>
                        <tr>
                            <th scope="col">Department</th>
                            <th scope="col">Code</th>
                            <th scope="col" class="text-end">Employees</th>
                            <th scope="col" class="text-end">Positions</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($departments as $department): ?>
                        <tr>
                            <td>
                                <span class="row-title"><?= e((string) $department['name']) ?></span>
                                <?php if (!empty($department['description'])): ?>
                                    <div class="row-meta"><?= e((string) $department['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="chip"><?= e((string) $department['code']) ?></span></td>
                            <td class="text-end"><?= (int) $department['employee_count'] ?></td>
                            <td class="text-end"><?= (int) $department['position_count'] ?></td>
                            <td><span class="badge badge-soft-<?= (int) $department['is_active'] === 1 ? 'success' : 'neutral' ?>"><?= (int) $department['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td class="col-actions">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-quiet" href="<?= e(url('organization/departments.php?edit=' . (int) $department['id'])) ?>"><?= icon('pencil') ?><span class="visually-hidden">Edit <?= e((string) $department['name']) ?></span></a>
                                    <form method="post" action="<?= e(url('organization/departments.php')) ?>"
                                          data-confirm="<?= (int) $department['is_active'] === 1 ? 'Deactivate this department? It will no longer be offered for new employee records.' : 'Reactivate this department?' ?>"
                                          data-confirm-title="<?= (int) $department['is_active'] === 1 ? 'Deactivate department' : 'Reactivate department' ?>"
                                          data-confirm-button="<?= (int) $department['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>"
                                          data-confirm-variant="<?= (int) $department['is_active'] === 1 ? 'danger' : 'success' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $department['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $department['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button>
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
                <h2 id="formHeading"><?= $editing !== null ? 'Edit department' : 'New department' ?></h2>
                <div class="meta">Names and codes must be unique.</div>
            </div>
        </div>
        <div class="panel-body">
            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>
            <form method="post" action="<?= e(url('organization/departments.php' . ($editId > 0 ? '?edit=' . $editId : ''))) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <div class="mb-3">
                    <label class="form-label" for="name">Department name <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'name') ?>" type="text" id="name" name="name"
                           value="<?= e($values['name']) ?>" maxlength="120" required>
                    <?php if (field_error($errors, 'name') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'name')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="code">Code <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'code') ?>" type="text" id="code" name="code"
                           value="<?= e($values['code']) ?>" maxlength="20" required placeholder="e.g. HRM">
                    <div class="form-text">2–20 letters, numbers or dashes.</div>
                    <?php if (field_error($errors, 'code') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'code')) ?></div><?php endif; ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description"
                              rows="3" maxlength="255"><?= e($values['description']) ?></textarea>
                    <?php if (field_error($errors, 'description') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'description')) ?></div><?php endif; ?>
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <button class="btn btn-primary" type="submit"><?= icon($editing !== null ? 'check2' : 'plus-lg') ?> <?= $editing !== null ? 'Save changes' : 'Create department' ?></button>
                </div>
            </form>
        </div>
    </aside>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
