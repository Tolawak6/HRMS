<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$identifier = '';
$errors = [];

if (is_post()) {
    $identifier = post_str('username');
    $password = post_raw('password');
    $result = attempt_login($identifier, $password);
    if ($result['ok']) {
        if ((int) ($result['user']['must_change_password'] ?? 0) === 1) {
            flash('warning', 'Your account uses a temporary password. Please set a new password to continue.');
            redirect('account/password.php');
        }
        redirect('dashboard.php');
    }
    $errors['_form'] = (string) ($result['error'] ?? 'Unable to sign in.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff sign in · ESS HRMS</title>
<link rel="icon" href="<?= e(url('images/logo.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-brand" aria-label="About this system">
        <div class="auth-brand-inner">
            <img src="<?= e(url('images/logo.png')) ?>" alt="Ethiopian Statistical Service emblem" class="auth-logo">
            <p class="auth-kicker">Ethiopian Statistical Service · HRMS</p>
            <h1>One record for every employee.</h1>
            <p>
                Centralized personnel records, organizational structure, leave administration,
                document management and workforce reporting — for authorized ESS staff.
            </p>
            <ul class="auth-features">
                <li><?= icon('shield-check') ?><span>Role-based access with a complete audit trail</span></li>
                <li><?= icon('people') ?><span>Employee profiles, departments and positions in one place</span></li>
                <li><?= icon('calendar2-check') ?><span>Leave requests, balances and approvals</span></li>
                <li><?= icon('bar-chart') ?><span>Workforce reports for evidence-based decisions</span></li>
            </ul>
        </div>
        <p class="auth-brand-footer">Authorized personnel only. Sign-in attempts and account activity are recorded.</p>
    </section>

    <section class="auth-main">
        <div class="auth-card">
            <div class="auth-mobile-brand">
                <img src="<?= e(url('images/logo.png')) ?>" alt="">
                <div>
                    <div class="fw-semibold small">Ethiopian Statistical Service</div>
                    <div class="tiny text-muted-2">Human Resource Management System</div>
                </div>
            </div>
            <h2>Sign in</h2>
            <p class="auth-sub">Use the account issued to you by Human Resources.</p>

            <?php include BASE_PATH . '/includes/partials/flash.php'; ?>
            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" novalidate autocomplete="on">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control<?= invalid_class($errors, 'username') ?>" type="text" id="username"
                           name="username" value="<?= e($identifier) ?>" maxlength="60" required
                           autocomplete="username" autocapitalize="none" spellcheck="false" autofocus>
                    <?php if (field_error($errors, 'username') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e(field_error($errors, 'username')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="password">Password</label>
                    <div class="position-relative">
                        <input class="form-control pe-5<?= invalid_class($errors, 'password') ?>" type="password"
                               id="password" name="password" required autocomplete="current-password" maxlength="200">
                        <button class="password-toggle" type="button" data-toggle-password="password"
                                aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
                    </div>
                    <?php if (field_error($errors, 'password') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e(field_error($errors, 'password')) ?></div>
                    <?php endif; ?>
                </div>
                <p class="tiny text-muted-2 mb-4">
                    Forgot your password? Contact Human Resources to have a temporary password issued.
                </p>
                <button class="btn btn-primary btn-lg w-100" type="submit"><?= icon('box-arrow-in-right') ?> Sign in</button>
            </form>

            <div class="auth-links">
                <a href="<?= e(url('home.php')) ?>"><?= icon('arrow-left') ?> Public website</a>
                <a href="<?= e(url('about_us.php')) ?>">About ESS</a>
            </div>
            <div class="auth-security">
                <?= icon('lock') ?>
                <span>This system is monitored. Do not share your credentials; every account action is logged.</span>
            </div>
        </div>
    </section>
</main>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
