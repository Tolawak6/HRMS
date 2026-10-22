<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin']);
$filters = staff_filters();
$errors = [];
$editId = get_int('edit');
$editing = $editId > 0 ? staff_account_find($editId) : null;
if ($editId > 0 && $editing === null) {
    abort(404, 'Account not found', 'The requested staff account does not exist.');
}

$values = [
    'username' => (string) ($editing['username'] ?? ''),
    'first_name' => (string) ($editing['first_name'] ?? ''),
    'middle_name' => (string) ($editing['middle_name'] ?? ''),
    'last_name' => (string) ($editing['last_name'] ?? ''),
    'email' => (string) ($editing['email'] ?? ''),
    'role' => (string) ($editing['role'] ?? 'hr_staff'),
    'is_active' => (int) ($editing['is_active'] ?? 1),
];

if (is_post()) {
    $action = post_str('action');
    if ($action === 'save') {
        $values = [
            'username' => post_str('username'),
            'first_name' => post_str('first_name'),
            'middle_name' => post_str('middle_name'),
            'last_name' => post_str('last_name'),
            'email' => post_str('email'),
            'role' => post_str('role', 'hr_staff'),
            'is_active' => post_str('is_active') === '1' ? 1 : 0,
        ];
        $values['password'] = post_raw('password');
        $outcome = staff_account_save($editId > 0 ? $editId : null, $values, $actor);
        if ($outcome['ok']) {
            flash('success', $editId > 0 ? 'Staff account updated.' : 'Staff account created.');
            redirect('admin/users.php');
        }
        $errors = $outcome['errors'] ?? ['_form' => 'The staff account could not be saved.'];
    } elseif ($action === 'toggle') {
        $outcome = staff_account_toggle(post_int('id'), $actor);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Account status updated.' : (string) ($outcome['error'] ?? 'Unable to change status.'));
        redirect('admin/users.php');
    }
}

$result = staff_search($filters);
$rows = $result['rows'];
$pagination = $result['pagination'];

$pageTitle = 'Staff accounts';
$topbarTitle = 'Administration';
$activeNav = 'staff-accounts';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Staff accounts</h1>
        <p class="page-subtitle">Administrator and HR staff access · <?= number_format((int) $pagination['total']) ?> account<?= (int) $pagination['total'] === 1 ? '' : 's' ?></p>
    </div>
    <div class="page-actions">
        <?php if ($editing !== null): ?>
            <a class="btn btn-quiet" href="<?= e(url('admin/users.php')) ?>"><?= icon('x-lg') ?> Cancel editing</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/audit.php')) ?>"><?= icon('journal-text') ?> Audit log</a>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="listHeading">
        <div class="panel-header">
            <div><h2 id="listHeading">Accounts</h2><div class="meta">Employee self-service accounts are managed from the employee records.</div></div>
        </div>
        <div class="panel-body pt-3">
            <form class="toolbar" method="get" action="<?= e(url('admin/users.php')) ?>" role="search">
                <div class="field field-grow">
                    <label for="q">Search</label>
                    <input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, username or email">
                </div>
                <div class="field">
                    <label for="role">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All roles</option>
                        <option value="admin" <?= selected($filters['role'], 'admin') ?>>Administrator</option>
                        <option value="hr_staff" <?= selected($filters['role'], 'hr_staff') ?>>HR staff</option>
                    </select>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <option value="active" <?= selected($filters['status'], 'active') ?>>Active</option>
                        <option value="inactive" <?= selected($filters['status'], 'inactive') ?>>Inactive</option>
                    </select>
                </div>
                <div class="field">
                    <label class="d-none d-sm-block">&nbsp;</label>
                    <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
                </div>
            </form>
        </div>
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('shield-lock') ?></div>
                <h3>No staff accounts match</h3>
                <p>Adjust the filters or create a new staff account.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last sign-in</th>
                            <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <span class="row-title"><?= e(person_name($row)) ?></span>
                                <div class="row-meta">@<?= e((string) $row['username']) ?><?= !empty($row['email']) ? ' · ' . e((string) $row['email']) : '' ?></div>
                            </td>
                            <td><span class="badge badge-role <?= e((string) $row['role']) ?>"><?= e(role_label((string) $row['role'])) ?></span></td>
                            <td>
                                <span class="badge badge-soft-<?= (int) $row['is_active'] === 1 ? 'success' : 'neutral' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
                                <?php if ((int) $row['id'] === (int) $actor['id']): ?><div class="row-meta">Your account</div><?php endif; ?>
                            </td>
                            <td class="text-nowrap text-muted"><?= e(format_datetime($row['last_login_at'] ?? null)) ?></td>
                            <td class="col-actions">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-quiet" href="<?= e(url('admin/users.php?edit=' . (int) $row['id'])) ?>"><?= icon('pencil') ?><span class="visually-hidden">Edit <?= e(person_name($row)) ?></span></a>
                                    <?php if ((int) $row['id'] !== (int) $actor['id']): ?>
                                        <form method="post" action="<?= e(url('admin/users.php')) ?>"
                                              data-confirm="<?= (int) $row['is_active'] === 1 ? 'Deactivate this staff account? The person will be signed out and unable to sign in.' : 'Reactivate this staff account?' ?>"
                                              data-confirm-title="<?= (int) $row['is_active'] === 1 ? 'Deactivate account' : 'Reactivate account' ?>"
                                              data-confirm-button="<?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>"
                                              data-confirm-variant="<?= (int) $row['is_active'] === 1 ? 'danger' : 'success' ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button>
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

    <aside class="panel" aria-labelledby="formHeading">
        <div class="panel-header">
            <div>
                <h2 id="formHeading"><?= $editing !== null ? 'Edit staff account' : 'New staff account' ?></h2>
                <div class="meta">Minimum 10-character password, changed at first sign-in.</div>
            </div>
        </div>
        <div class="panel-body">
            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>
            <form method="post" action="<?= e(url('admin/users.php' . ($editId > 0 ? '?edit=' . $editId : ''))) ?>" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <div class="mb-3">
                    <label class="form-label" for="username">Username <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'username') ?>" type="text" id="username" name="username"
                           value="<?= e($values['username']) ?>" maxlength="60" required autocapitalize="none" spellcheck="false">
                    <?php if (field_error($errors, 'username') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'username')) ?></div><?php endif; ?>
                </div>
                <div class="form-grid mb-3">
                    <div>
                        <label class="form-label" for="first_name">First name <span class="req">*</span></label>
                        <input class="form-control<?= invalid_class($errors, 'first_name') ?>" type="text" id="first_name" name="first_name"
                               value="<?= e($values['first_name']) ?>" maxlength="80" required>
                        <?php if (field_error($errors, 'first_name') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'first_name')) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label" for="last_name">Last name <span class="req">*</span></label>
                        <input class="form-control<?= invalid_class($errors, 'last_name') ?>" type="text" id="last_name" name="last_name"
                               value="<?= e($values['last_name']) ?>" maxlength="80" required>
                        <?php if (field_error($errors, 'last_name') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'last_name')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="middle_name">Middle name</label>
                    <input class="form-control" type="text" id="middle_name" name="middle_name" value="<?= e($values['middle_name']) ?>" maxlength="80">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control<?= invalid_class($errors, 'email') ?>" type="email" id="email" name="email"
                           value="<?= e($values['email']) ?>" maxlength="150">
                    <?php if (field_error($errors, 'email') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'email')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="role">Role <span class="req">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'role') ?>" id="role" name="role"
                            <?= $editing !== null && (int) $editing['id'] === (int) $actor['id'] ? 'disabled' : '' ?>>
                        <option value="hr_staff" <?= selected($values['role'], 'hr_staff') ?>>HR staff</option>
                        <option value="admin" <?= selected($values['role'], 'admin') ?>>Administrator</option>
                    </select>
                    <?php if ($editing !== null && (int) $editing['id'] === (int) $actor['id']): ?>
                        <input type="hidden" name="role" value="<?= e($values['role']) ?>">
                    <?php endif; ?>
                    <?php if (field_error($errors, 'role') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'role')) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">
                        <?= $editing !== null ? 'New temporary password' : 'Temporary password' ?>
                        <?php if ($editing === null): ?><span class="req">*</span><?php endif; ?>
                    </label>
                    <div class="position-relative">
                        <input class="form-control<?= invalid_class($errors, 'password') ?> pe-5" type="password" id="password" name="password"
                               autocomplete="new-password" <?= $editing === null ? 'required' : '' ?> minlength="10">
                        <button class="password-toggle" type="button" data-toggle-password="password"
                                aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
                    </div>
                    <div class="form-text"><?= $editing !== null ? 'Leave blank to keep the current password.' : 'Share it through a secure channel.' ?></div>
                    <?php if (field_error($errors, 'password') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'password')) ?></div><?php endif; ?>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= checked((int) $values['is_active'] === 1) ?>>
                    <label class="form-check-label" for="is_active">Account is active</label>
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <?php if ($editing !== null): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit"><?= icon($editing !== null ? 'check2' : 'person-plus') ?> <?= $editing !== null ? 'Save account' : 'Create account' ?></button>
                </div>
            </form>
        </div>
    </aside>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
