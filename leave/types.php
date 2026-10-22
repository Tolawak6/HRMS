<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin']);
$errors = [];
$editId = get_int('edit');
$editing = $editId > 0 ? leave_type_find($editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Leave type not found', 'The requested leave type does not exist.');
}

$values = [
    'name' => (string) ($editing['name'] ?? ''),
    'code' => (string) ($editing['code'] ?? ''),
    'annual_entitlement' => (string) ($editing['annual_entitlement'] ?? '20'),
    'is_paid' => (int) ($editing['is_paid'] ?? 1),
    'description' => (string) ($editing['description'] ?? ''),
];

if (is_post()) {
    $action = post_str('action');
    if ($action === 'save') {
        $values = [
            'name' => post_str('name'),
            'code' => post_str('code'),
            'annual_entitlement' => post_str('annual_entitlement', '0'),
            'is_paid' => post_str('is_paid') === '1' ? 1 : 0,
            'description' => post_str('description'),
        ];
        $outcome = leave_type_save($editId > 0 ? $editId : null, $values, $actor);
        if ($outcome['ok']) {
            flash('success', $editId > 0 ? 'Leave type updated.' : 'Leave type created.');
            redirect('leave/types.php');
        }
        $errors = $outcome['errors'] ?? ['_form' => 'The leave type could not be saved.'];
    } elseif ($action === 'toggle') {
        $outcome = leave_type_toggle(post_int('id'), $actor);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Leave type status updated.' : (string) ($outcome['error'] ?? 'Unable to change status.'));
        redirect('leave/types.php');
    }
}

$types = leave_types_all();
$pageTitle = 'Leave types';
$topbarTitle = 'Administration';
$activeNav = 'leave-types';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Leave types</h1>
        <p class="page-subtitle">Default annual entitlement per leave type. Employee-specific overrides are managed under Entitlements.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('leave/balances.php')) ?>"><?= icon('sliders') ?> Entitlements</a>
        <?php if ($editing !== null): ?>
            <a class="btn btn-quiet" href="<?= e(url('leave/types.php')) ?>"><?= icon('x-lg') ?> Cancel editing</a>
        <?php endif; ?>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="listHeading">
        <div class="panel-header">
            <div><h2 id="listHeading">Configured leave types</h2><div class="meta">Deactivating a type hides it from new requests.</div></div>
        </div>
        <?php if ($types === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('sliders') ?></div>
                <h3>No leave types yet</h3>
                <p>Create annual, sick and other leave types using the form on this page.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col">Code</th>
                            <th scope="col" class="text-end">Default days</th>
                            <th scope="col">Paid</th>
                            <th scope="col" class="text-end">Requests</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($types as $type): ?>
                        <tr>
                            <td>
                                <span class="row-title"><?= e((string) $type['name']) ?></span>
                                <?php if (!empty($type['description'])): ?><div class="row-meta"><?= e((string) $type['description']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="chip"><?= e((string) $type['code']) ?></span></td>
                            <td class="text-end"><?= e(format_days((float) $type['annual_entitlement'])) ?></td>
                            <td><?= (int) $type['is_paid'] === 1 ? 'Yes' : 'No' ?></td>
                            <td class="text-end"><?= leave_type_usage((int) $type['id']) ?></td>
                            <td><span class="badge badge-soft-<?= (int) $type['is_active'] === 1 ? 'success' : 'neutral' ?>"><?= (int) $type['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td class="col-actions">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/types.php?edit=' . (int) $type['id'])) ?>"><?= icon('pencil') ?><span class="visually-hidden">Edit <?= e((string) $type['name']) ?></span></a>
                                    <form method="post" action="<?= e(url('leave/types.php')) ?>"
                                          data-confirm="<?= (int) $type['is_active'] === 1 ? 'Deactivate this leave type? Existing requests are unaffected.' : 'Reactivate this leave type?' ?>"
                                          data-confirm-title="<?= (int) $type['is_active'] === 1 ? 'Deactivate leave type' : 'Reactivate leave type' ?>"
                                          data-confirm-button="<?= (int) $type['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>"
                                          data-confirm-variant="<?= (int) $type['is_active'] === 1 ? 'danger' : 'success' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $type['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $type['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button>
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
            <div><h2 id="formHeading"><?= $editing !== null ? 'Edit leave type' : 'New leave type' ?></h2><div class="meta">Names and codes must be unique.</div></div>
        </div>
        <div class="panel-body">
            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>
            <form method="post" action="<?= e(url('leave/types.php' . ($editId > 0 ? '?edit=' . $editId : ''))) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <div class="mb-3">
                    <label class="form-label" for="name">Name <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'name') ?>" type="text" id="name" name="name"
                           value="<?= e($values['name']) ?>" maxlength="80" required>
                    <?php if (field_error($errors, 'name') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'name')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="code">Code <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'code') ?>" type="text" id="code" name="code"
                           value="<?= e($values['code']) ?>" maxlength="20" required>
                    <?php if (field_error($errors, 'code') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'code')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="annual_entitlement">Default annual entitlement (days) <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'annual_entitlement') ?>" type="number" id="annual_entitlement"
                           name="annual_entitlement" value="<?= e($values['annual_entitlement']) ?>" min="0" max="365" step="0.5" required>
                    <?php if (field_error($errors, 'annual_entitlement') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'annual_entitlement')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3 form-check">
                    <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" value="1" <?= checked((int) $values['is_paid'] === 1) ?>>
                    <label class="form-check-label" for="is_paid">Paid leave</label>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description"
                              rows="3" maxlength="255"><?= e($values['description']) ?></textarea>
                    <?php if (field_error($errors, 'description') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'description')) ?></div><?php endif; ?>
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <button class="btn btn-primary" type="submit"><?= icon($editing !== null ? 'check2' : 'plus-lg') ?> <?= $editing !== null ? 'Save changes' : 'Create leave type' ?></button>
                </div>
            </form>
        </div>
    </aside>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
