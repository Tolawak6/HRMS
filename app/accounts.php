<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function active_admin_count(): int
{
    return (int) db()->value(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND archived_at IS NULL",
        0
    );
}

function staff_account_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one(
        "SELECT * FROM users WHERE id = ? AND role IN ('admin','hr_staff') LIMIT 1",
        [$id]
    );
}

function staff_filters(): array
{
    $role = get_str('role');
    $status = get_str('status');
    return [
        'q'        => get_str('q'),
        'role'     => in_array($role, ['admin', 'hr_staff'], true) ? $role : '',
        'status'   => in_array($status, ['active', 'inactive'], true) ? $status : '',
        'page'     => max(1, get_int('page', 1)),
        'per_page' => per_page_value(),
    ];
}

function staff_search(array $filters): array
{
    $where = ["u.role IN ('admin','hr_staff')"];
    $params = [];
    if ($filters['role'] !== '') {
        $where[] = 'u.role = ?';
        $params[] = $filters['role'];
    }
    if ($filters['status'] === 'active') {
        $where[] = 'u.is_active = 1';
    } elseif ($filters['status'] === 'inactive') {
        $where[] = 'u.is_active = 0';
    }
    if ($filters['q'] !== '') {
        $term = '%' . like_term(mb_substr($filters['q'], 0, 80)) . '%';
        $where[] = "(u.username LIKE ? ESCAPE '!' OR u.first_name LIKE ? ESCAPE '!'
                     OR u.last_name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
        array_push($params, $term, $term, $term, $term);
    }
    $whereSql = implode(' AND ', $where);
    $total = (int) db()->value("SELECT COUNT(*) FROM users u WHERE $whereSql", $params, 0);
    $pagination = paginate($total, $filters['per_page'], $filters['page']);
    $rows = db()->all(
        "SELECT u.id, u.username, u.first_name, u.middle_name, u.last_name, u.email,
                u.role, u.is_active, u.last_login_at, u.created_at
         FROM users u WHERE $whereSql
         ORDER BY u.role ASC, u.last_name ASC, u.username ASC
         LIMIT " . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
        $params
    );
    return ['rows' => $rows, 'pagination' => $pagination, 'filters' => $filters];
}

/**
 * @return array{ok:bool,id?:int,errors?:array<string,string>}
 */
function staff_account_save(?int $id, array $input, array $actor): array
{
    $username = mb_strtolower(trim((string) ($input['username'] ?? '')));
    $firstName = trim((string) ($input['first_name'] ?? ''));
    $middleName = trim((string) ($input['middle_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $role = (string) ($input['role'] ?? 'hr_staff');
    $password = (string) ($input['password'] ?? '');
    $isActive = isset($input['is_active']) ? 1 : 0;
    $errors = [];

    if (!validate_username($username)) {
        $errors['username'] = 'Username must be 4–60 characters using letters, numbers, dot, dash or underscore.';
    }
    if ($firstName === '' || mb_strlen($firstName) > 80) {
        $errors['first_name'] = 'First name is required (maximum 80 characters).';
    }
    if (mb_strlen($middleName) > 80) {
        $errors['middle_name'] = 'Middle name must be 80 characters or fewer.';
    }
    if ($lastName === '' || mb_strlen($lastName) > 80) {
        $errors['last_name'] = 'Last name is required (maximum 80 characters).';
    }
    if ($email !== '' && !validate_email_address($email)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!in_array($role, ['admin', 'hr_staff'], true)) {
        $errors['role'] = 'Select a valid role.';
    }
    if ($id === null && $password === '') {
        $errors['password'] = 'A temporary password is required.';
    } elseif ($password !== '') {
        $passwordErrors = password_policy_errors($password, $username);
        if ($passwordErrors !== []) {
            $errors['password'] = implode(' ', $passwordErrors);
        }
    }

    $current = null;
    if ($id !== null) {
        $current = staff_account_find($id);
        if ($current === null) {
            $errors['_form'] = 'Staff account not found.';
        }
    }

    if ($errors === []) {
        $usernameCount = (int) db()->value(
            'SELECT COUNT(*) FROM users WHERE username = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$username, $id] : [$username],
            0
        );
        if ($usernameCount > 0) {
            $errors['username'] = 'This username is already in use.';
        }
        if ($email !== '') {
            $emailCount = (int) db()->value(
                'SELECT COUNT(*) FROM users WHERE email = ?' . ($id ? ' AND id <> ?' : ''),
                $id ? [$email, $id] : [$email],
                0
            );
            if ($emailCount > 0) {
                $errors['email'] = 'This email address is already in use.';
            }
        }
    }

    if ($current !== null) {
        if ((int) $current['id'] === (int) $actor['id']) {
            if ($role !== (string) $current['role']) {
                $errors['role'] = 'You cannot change your own role.';
            }
            if ($isActive !== 1) {
                $errors['is_active'] = 'You cannot deactivate your own account.';
            }
        }
        $wasActiveAdmin = (string) $current['role'] === 'admin' && (int) $current['is_active'] === 1;
        $willBeActiveAdmin = $role === 'admin' && $isActive === 1;
        if ($wasActiveAdmin && !$willBeActiveAdmin && active_admin_count() <= 1) {
            $errors['_form'] = 'At least one active administrator must remain.';
        }
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    if ($id === null) {
        $newId = db()->insert(
            "INSERT INTO users
                (username, password, role, first_name, middle_name, last_name, email,
                 must_change_password, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())",
            [
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $email !== '' ? $email : null,
                $isActive,
            ]
        );
        audit_log('account.create', 'account', $newId, ucfirst(str_replace('_', ' ', $role)) . ' account created');
        notify(
            $newId,
            'account',
            'Your staff account was created',
            'Your ESS HRMS account is ready. Set a new password when you first sign in.',
            'account/password.php'
        );
        return ['ok' => true, 'id' => $newId];
    }

    $passwordSql = '';
    $passwordParams = [];
    if ($password !== '') {
        $passwordSql = ', password = ?, must_change_password = 1';
        $passwordParams = [password_hash($password, PASSWORD_DEFAULT)];
    }
    $params = [
        $username,
        $firstName,
        $middleName !== '' ? $middleName : null,
        $lastName,
        $email !== '' ? $email : null,
        $role,
        $isActive,
    ];
    db()->execute(
        "UPDATE users SET username = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?,
            role = ?, is_active = ?, updated_at = NOW()$passwordSql
         WHERE id = ? AND role IN ('admin','hr_staff')",
        array_merge($params, $passwordParams, [$id])
    );
    audit_log('account.update', 'account', $id, 'Staff account updated');
    if ($password !== '') {
        audit_log('account.password_reset', 'account', $id, 'Temporary password set by administrator');
        notify(
            $id,
            'account',
            'Your password was reset',
            'An administrator set a temporary password for your account. Please sign in and change it.',
            'account/password.php'
        );
    }
    return ['ok' => true, 'id' => $id];
}

function staff_account_toggle(int $id, array $actor): array
{
    $account = staff_account_find($id);
    if ($account === null) {
        return ['ok' => false, 'error' => 'Staff account not found.'];
    }
    if ($id === (int) $actor['id']) {
        return ['ok' => false, 'error' => 'You cannot deactivate your own account.'];
    }
    $newStatus = (int) $account['is_active'] === 1 ? 0 : 1;
    if ($newStatus === 0 && (string) $account['role'] === 'admin' && active_admin_count() <= 1) {
        return ['ok' => false, 'error' => 'At least one active administrator must remain.'];
    }
    db()->execute('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $id]);
    audit_log('account.status', 'account', $id, $newStatus === 1 ? 'Staff account activated' : 'Staff account deactivated');
    return ['ok' => true];
}

function staff_account_reset_password(int $id, string $password, array $actor): array
{
    $account = staff_account_find($id);
    if ($account === null) {
        return ['ok' => false, 'error' => 'Staff account not found.'];
    }
    $errors = password_policy_errors($password, (string) $account['username']);
    if ($errors !== []) {
        return ['ok' => false, 'error' => implode(' ', $errors)];
    }
    db()->execute(
        'UPDATE users SET password = ?, must_change_password = 1, updated_at = NOW() WHERE id = ?',
        [password_hash($password, PASSWORD_DEFAULT), $id]
    );
    audit_log('account.password_reset', 'account', $id, 'Temporary password set by administrator');
    notify(
        $id,
        'account',
        'Your password was reset',
        'An administrator set a temporary password for your account. Please sign in and change it.',
        'account/password.php'
    );
    return ['ok' => true];
}
