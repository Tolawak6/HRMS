<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

const HRMS_DUMMY_PASSWORD_HASH = '$2y$10$sgAqzllgAMZfGbeCnKMINuJkbkEn93YqOWK.Qa9rk6mMKq7IMQs.C';

/**
 * Load the signed-in user from the database on every request so role and
 * account-status changes take effect immediately.
 */
function current_user(): ?array
{
    static $cached = false;
    static $user = null;

    if ($cached) {
        return $user;
    }
    $cached = true;

    $uid = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($uid <= 0) {
        return null;
    }

    try {
        $user = db()->one(
            'SELECT u.*, d.name AS department_name, d.code AS department_code,
                    p.title AS position_title
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN positions p ON p.id = u.position_id
             WHERE u.id = ? LIMIT 1',
            [$uid]
        );
    } catch (Throwable $exception) {
        app_log('error', 'Unable to load current user', ['reason' => $exception->getMessage()]);
        throw $exception;
    }

    if ($user === null || (int) ($user['is_active'] ?? 0) !== 1 || !empty($user['archived_at'])) {
        clear_auth_session();
        $user = null;
        return null;
    }

    if (!in_array((string) $user['role'], ['admin', 'hr_staff', 'ess_staff'], true)) {
        clear_auth_session();
        $user = null;
        return null;
    }

    return $user;
}

function reset_current_user_cache(): void
{
    current_user();
    // The function's static cache cannot be reset directly; this helper exists
    // for tests that reload the process state between scenarios.
}

function clear_auth_session(): void
{
    unset(
        $_SESSION['uid'],
        $_SESSION['login_time'],
        $_SESSION['last_activity'],
        $_SESSION['last_regenerated'],
        $_SESSION['auth_fingerprint']
    );
}

function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        if (empty($_SESSION['_flash'])) {
            flash('info', 'Please sign in to continue.');
        }
        redirect('login.php');
    }

    if ((int) ($user['must_change_password'] ?? 0) === 1) {
        $allowed = ['password.php', 'logout.php'];
        if (!in_array(basename(current_route()), $allowed, true)) {
            flash('warning', 'You must set a new password before continuing.');
            redirect('account/password.php?required=1');
        }
    }

    return $user;
}

function require_roles(array $roles): array
{
    $user = require_auth();
    if (!in_array((string) $user['role'], $roles, true)) {
        app_log('warning', 'Access denied', [
            'user_id' => (int) $user['id'],
            'role' => (string) $user['role'],
            'route' => current_route(),
        ]);
        abort(403, 'Access denied', 'Your account does not have permission to view this page.');
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function is_hr(): bool
{
    return (current_user()['role'] ?? '') === 'hr_staff';
}

function is_employee(): bool
{
    return (current_user()['role'] ?? '') === 'ess_staff';
}

function can_manage_employees(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'hr_staff'], true);
}

function can_manage_organization(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'hr_staff'], true);
}

function can_manage_leave(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'hr_staff'], true);
}

function can_view_reports(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'hr_staff'], true);
}

function can_manage_accounts(): bool
{
    return is_admin();
}

function can_view_audit(): bool
{
    return is_admin();
}

function can_manage_leave_types(): bool
{
    return is_admin();
}

/**
 * Object-level authorization for employee records.
 */
function can_access_employee(array $employee): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    if (can_manage_employees()) {
        return true;
    }
    return (int) ($employee['id'] ?? 0) === (int) $user['id'];
}

function require_employee_access(array $employee): void
{
    if (!can_access_employee($employee)) {
        app_log('warning', 'Employee record access denied', [
            'user_id' => (int) (current_user()['id'] ?? 0),
            'employee_id' => (int) ($employee['id'] ?? 0),
        ]);
        abort(403, 'Access denied', 'You do not have permission to view this employee record.');
    }
}

/* ---------------------------------------------------------------------------
 * Sign in / sign out
 * ------------------------------------------------------------------------ */

function login_throttle_count(string $username, string $ip): int
{
    $window = (int) config('security.login_window_minutes', 15);
    return (int) db()->value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE successful = 0
           AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . $window . ' MINUTE)
           AND (username = ? OR ip_address = ?)',
        [$username, $ip],
        0
    );
}

function record_login_attempt(string $username, string $ip, bool $success): void
{
    try {
        db()->insert(
            'INSERT INTO login_attempts (username, ip_address, successful, attempted_at)
             VALUES (?, ?, ?, NOW())',
            [mb_substr($username, 0, 60), $ip, $success ? 1 : 0]
        );
    } catch (Throwable $exception) {
        app_log('error', 'Login attempt write failed', ['reason' => $exception->getMessage()]);
    }
}

/**
 * @return array{ok:bool,error?:string,user?:array}
 */
function attempt_login(string $username, string $password): array
{
    $username = trim($username);
    $ip = client_ip();
    $maxAttempts = (int) config('security.login_max_attempts', 5);
    $window = (int) config('security.login_window_minutes', 15);

    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'Enter your username and password.'];
    }
    if (mb_strlen($username) > 60 || mb_strlen($password) > 200) {
        return ['ok' => false, 'error' => 'Invalid username or password.'];
    }

    try {
        if (login_throttle_count($username, $ip) >= $maxAttempts) {
            app_log('warning', 'Login throttled', ['ip' => $ip]);
            return [
                'ok' => false,
                'error' => 'Too many failed sign-in attempts. Please wait ' . $window . ' minutes and try again.',
            ];
        }

        $user = db()->one('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
        $hash = $user !== null ? (string) $user['password'] : HRMS_DUMMY_PASSWORD_HASH;
        $verified = password_verify($password, $hash);

        if ($user !== null && !$verified) {
            // Legacy installations stored plain-text passwords. Verify and
            // transparently upgrade them on the next successful sign-in.
            $info = password_get_info($hash);
            if (($info['algo'] ?? null) === null && hash_equals($hash, $password)) {
                $verified = true;
                db()->execute(
                    'UPDATE users SET password = ? WHERE id = ?',
                    [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]
                );
                audit_log('auth.password_upgraded', 'user', (int) $user['id'], 'Legacy password hash upgraded');
            }
        }

        if ($user === null || !$verified) {
            record_login_attempt($username, $ip, false);
            app_log('warning', 'Failed sign-in', ['ip' => $ip]);
            return ['ok' => false, 'error' => 'Invalid username or password.'];
        }

        if ((int) $user['is_active'] !== 1 || !empty($user['archived_at'])) {
            record_login_attempt($username, $ip, false);
            return ['ok' => false, 'error' => 'This account is inactive. Please contact Human Resources.'];
        }

        if (!in_array((string) $user['role'], ['admin', 'hr_staff', 'ess_staff'], true)) {
            record_login_attempt($username, $ip, false);
            return ['ok' => false, 'error' => 'This account cannot sign in. Please contact Human Resources.'];
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            db()->execute(
                'UPDATE users SET password = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]
            );
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regenerated'] = time();
        rotate_csrf();

        db()->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
        record_login_attempt($username, $ip, true);
        try {
            db()->execute(
                'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 DAY)'
            );
        } catch (Throwable) {
            // Housekeeping is best-effort.
        }
        audit_log('auth.login', 'user', (int) $user['id'], 'Signed in');

        $user['last_login_at'] = date('Y-m-d H:i:s');
        return ['ok' => true, 'user' => $user];
    } catch (Throwable $exception) {
        app_log('error', 'Sign-in failed unexpectedly', ['reason' => $exception->getMessage()]);
        return ['ok' => false, 'error' => 'Sign-in is temporarily unavailable. Please try again later.'];
    }
}

function logout_user(): void
{
    $uid = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($uid > 0) {
        audit_log('auth.logout', 'user', $uid, 'Signed out');
    }
    clear_auth_session();
    session_regenerate_id(true);
    rotate_csrf();
    flash('success', 'You have been signed out.');
}

function update_password(int $userId, string $newPassword, bool $requireCurrent = false, string $currentPassword = ''): array
{
    $user = db()->one('SELECT id, username, password FROM users WHERE id = ? LIMIT 1', [$userId]);
    if ($user === null) {
        return ['ok' => false, 'error' => 'Account not found.'];
    }

    if ($requireCurrent) {
        $hash = (string) $user['password'];
        $validCurrent = password_verify($currentPassword, $hash);
        if (!$validCurrent) {
            $info = password_get_info($hash);
            if (($info['algo'] ?? null) === null && hash_equals($hash, $currentPassword)) {
                $validCurrent = true;
            }
        }
        if (!$validCurrent) {
            return ['ok' => false, 'error' => 'Your current password is not correct.'];
        }
    }

    $errors = password_policy_errors($newPassword, (string) $user['username']);
    if ($errors !== []) {
        return ['ok' => false, 'error' => implode(' ', $errors)];
    }

    db()->execute(
        'UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?',
        [password_hash($newPassword, PASSWORD_DEFAULT), $userId]
    );
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
    audit_log('password.change', 'account', $userId, 'Password changed');
    return ['ok' => true];
}
