<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_auth();
$mustChange = (int) ($user['must_change_password'] ?? 0) === 1;
$required = get_str('required') === '1' || $mustChange;
$errors = [];

if (is_post()) {
    $current = post_raw('current_password');
    $new = post_raw('new_password');
    $confirm = post_raw('confirm_password');

    $policyErrors = password_policy_errors($new, (string) $user['username']);
    if (!$mustChange && $current === '') {
        $errors['current_password'] = 'Enter your current password.';
    }
    if ($new !== $confirm) {
        $errors['confirm_password'] = 'The new password and confirmation do not match.';
    }
    if ($current !== '' && $current === $new) {
        $errors['new_password'] = 'Choose a password different from your current one.';
    }
    foreach ($policyErrors as $policyError) {
        $errors['new_password'] = $errors['new_password'] ?? $policyError;
    }

    if ($errors === []) {
        $result = update_password((int) $user['id'], $new, !$mustChange, $current);
        if ($result['ok']) {
            flash('success', 'Your password has been updated.');
            redirect('dashboard.php');
        }
        $errors['_form'] = (string) ($result['error'] ?? 'The password could not be changed.');
    }
}

$pageTitle = 'Account security';
$topbarTitle = 'My account';
$activeNav = 'account';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Account security</h1>
        <p class="page-subtitle">Signed in as <strong><?= e((string) $user['username']) ?></strong> · <?= e(role_label((string) $user['role'])) ?></p>
    </div>
</div>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="passwordHeading">
        <div class="panel-header">
            <div>
                <h2 id="passwordHeading"><?= $mustChange ? 'Set a new password' : 'Change your password' ?></h2>
                <div class="meta">Minimum 10 characters with upper case, lower case, a number and a symbol</div>
            </div>
        </div>
        <div class="panel-body">
            <?php if ($required): ?>
                <div class="notice mb-3">
                    <?= icon('shield-exclamation') ?>
                    <div>
                        <strong>A new password is required.</strong>
                        <?= $mustChange ? 'Your account is using a temporary password issued by Human Resources.' : 'Please choose a new password to continue.' ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>

            <form method="post" action="<?= e(url('account/password.php')) ?>" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <?php if (!$mustChange): ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password <span class="req">*</span></label>
                        <input class="form-control<?= invalid_class($errors, 'current_password') ?>" type="password"
                               id="current_password" name="current_password" autocomplete="current-password" required>
                        <?php if (field_error($errors, 'current_password') !== ''): ?>
                            <div class="invalid-feedback d-block"><?= e(field_error($errors, 'current_password')) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="new_password">New password <span class="req">*</span></label>
                    <div class="position-relative">
                        <input class="form-control pe-5<?= invalid_class($errors, 'new_password') ?>" type="password"
                               id="new_password" name="new_password" autocomplete="new-password" required minlength="10">
                        <button class="password-toggle" type="button" data-toggle-password="new_password"
                                aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
                    </div>
                    <?php if (field_error($errors, 'new_password') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e(field_error($errors, 'new_password')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="confirm_password">Confirm new password <span class="req">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'confirm_password') ?>" type="password"
                           id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                    <?php if (field_error($errors, 'confirm_password') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e(field_error($errors, 'confirm_password')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <a class="btn btn-outline-secondary" href="<?= e(url('dashboard.php')) ?>">Cancel</a>
                    <button class="btn btn-primary" type="submit"><?= icon('key') ?> Update password</button>
                </div>
            </form>
        </div>
    </section>

    <aside class="panel" aria-labelledby="securityHeading">
        <div class="panel-header">
            <div><h2 id="securityHeading">Password guidance</h2></div>
        </div>
        <div class="panel-body">
            <ul class="stack-sm text-muted small ps-3 mb-3">
                <li>Use at least 10 characters.</li>
                <li>Mix upper case, lower case, numbers and symbols.</li>
                <li>Do not reuse your username or an old password.</li>
                <li>Never share your password or write it down.</li>
            </ul>
            <div class="notice">
                <?= icon('info-circle') ?>
                <div>Password changes and account activity are recorded in the audit log.</div>
            </div>
            <div class="mini-list mt-3">
                <div class="row-line"><span>Account</span><span>@<?= e((string) $user['username']) ?></span></div>
                <div class="row-line"><span>Role</span><span><?= e(role_label((string) $user['role'])) ?></span></div>
                <div class="row-line"><span>Last sign-in</span><span><?= e(format_datetime($user['last_login_at'] ?? null)) ?></span></div>
            </div>
        </div>
    </aside>
</div>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
