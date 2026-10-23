<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

const EMPLOYEE_SORTS = [
    'name'             => ['u.last_name', 'u.first_name'],
    'employee_number'  => ['u.employee_number'],
    'hire_date'        => ['u.hire_date'],
    'department'       => ['d.name', 'u.last_name'],
    'created_at'       => ['u.created_at'],
];

function employee_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one(
        'SELECT u.*, d.name AS department_name, d.code AS department_code,
                p.title AS position_title, p.grade AS position_grade
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN positions p ON p.id = u.position_id
         WHERE u.id = ? AND u.role = ? LIMIT 1',
        [$id, 'ess_staff']
    );
}

function user_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one(
        'SELECT u.*, d.name AS department_name, p.title AS position_title
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN positions p ON p.id = u.position_id
         WHERE u.id = ? LIMIT 1',
        [$id]
    );
}

function employee_filters(): array
{
    $sort = get_str('sort', 'name');
    $dir = strtolower(get_str('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    $archived = get_str('archived', 'active');
    $status = get_str('status');
    $sex = get_str('sex');
    return [
        'q'          => get_str('q'),
        'department' => max(0, get_int('department')),
        'status'     => in_array($status, employment_statuses(), true) ? $status : '',
        'sex'        => in_array($sex, sex_options(), true) ? $sex : '',
        'archived'   => in_array($archived, ['active', 'archived', 'all'], true) ? $archived : 'active',
        'sort'       => array_key_exists($sort, EMPLOYEE_SORTS) ? $sort : 'name',
        'dir'        => $dir,
        'page'       => max(1, get_int('page', 1)),
        'per_page'   => per_page_value(),
    ];
}

/** @return array{0:string,1:array<int,mixed>} */
function employee_where(array $filters): array
{
    $where = ["u.role = 'ess_staff'"];
    $params = [];

    if ($filters['archived'] === 'active') {
        $where[] = 'u.archived_at IS NULL';
    } elseif ($filters['archived'] === 'archived') {
        $where[] = 'u.archived_at IS NOT NULL';
    }
    if ($filters['status'] !== '') {
        $where[] = 'u.employment_status = ?';
        $params[] = $filters['status'];
    }
    if ($filters['sex'] !== '') {
        $where[] = 'u.sex = ?';
        $params[] = $filters['sex'];
    }
    if ($filters['department'] > 0) {
        $where[] = 'u.department_id = ?';
        $params[] = $filters['department'];
    }
    if ($filters['q'] !== '') {
        $term = '%' . like_term(mb_substr($filters['q'], 0, 80)) . '%';
        $where[] = "(u.first_name LIKE ? ESCAPE '!'
            OR u.middle_name LIKE ? ESCAPE '!'
            OR u.last_name LIKE ? ESCAPE '!'
            OR u.employee_number LIKE ? ESCAPE '!'
            OR u.email LIKE ? ESCAPE '!'
            OR u.phone_number LIKE ? ESCAPE '!'
            OR u.username LIKE ? ESCAPE '!')";
        array_push($params, $term, $term, $term, $term, $term, $term, $term);
    }
    return [implode(' AND ', $where), $params];
}

function employee_search(array $filters): array
{
    [$whereSql, $params] = employee_where($filters);
    $total = (int) db()->value(
        "SELECT COUNT(*) FROM users u WHERE $whereSql",
        $params,
        0
    );
    $pagination = paginate($total, $filters['per_page'], $filters['page']);

    $sortColumns = EMPLOYEE_SORTS[$filters['sort']] ?? EMPLOYEE_SORTS['name'];
    $direction = $filters['dir'] === 'desc' ? 'DESC' : 'ASC';
    $order = implode(', ', array_map(static fn (string $col): string => $col . ' ' . $direction, $sortColumns));
    $order .= ', u.id ASC';

    $rows = db()->all(
        "SELECT u.id, u.username, u.employee_number, u.first_name, u.middle_name, u.last_name,
                u.sex, u.email, u.phone_number, u.employment_status, u.employment_type,
                u.hire_date, u.archived_at, u.is_active, u.department_id, u.photo_path, u.image,
                d.name AS department_name, p.title AS position_title,
                (SELECT COUNT(*) FROM file_attachments fa WHERE fa.ess_staff_id = u.id) AS document_count
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN positions p ON p.id = u.position_id
         WHERE $whereSql
         ORDER BY $order
         LIMIT " . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
        $params
    );

    return ['rows' => $rows, 'pagination' => $pagination, 'filters' => $filters];
}

function employee_export_rows(array $filters): array
{
    [$whereSql, $params] = employee_where($filters);
    return db()->all(
        "SELECT u.employee_number, u.first_name, u.middle_name, u.last_name, u.sex,
                u.employment_type, u.employment_status, u.hire_date,
                u.email, u.phone_number, d.name AS department_name, p.title AS position_title
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN positions p ON p.id = u.position_id
         WHERE $whereSql
         ORDER BY u.last_name ASC, u.first_name ASC
         LIMIT 10000",
        $params
    );
}

function employee_stats(): array
{
    $row = db()->one(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL) AS employees,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL AND employment_status = 'Active') AS active,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL AND employment_status = 'On Leave') AS on_leave,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NOT NULL) AS archived,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL AND YEAR(hire_date) = YEAR(CURDATE())) AS new_hires,
            (SELECT COUNT(*) FROM departments WHERE is_active = 1) AS departments,
            (SELECT COUNT(*) FROM positions WHERE is_active = 1) AS positions,
            (SELECT COUNT(*) FROM leave_requests WHERE status = 'pending') AS pending_leave,
            (SELECT COUNT(*) FROM file_attachments) AS documents"
    );
    return $row ?? [
        'employees' => 0, 'active' => 0, 'on_leave' => 0, 'archived' => 0,
        'new_hires' => 0, 'departments' => 0, 'positions' => 0,
        'pending_leave' => 0, 'documents' => 0,
    ];
}

function department_distribution(int $limit = 6): array
{
    $limit = max(1, min(20, $limit));
    $rows = db()->all(
        "SELECT COALESCE(d.name, 'Unassigned') AS label, COUNT(*) AS total
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.role = 'ess_staff' AND u.archived_at IS NULL
         GROUP BY u.department_id, d.name
         ORDER BY total DESC, label ASC
         LIMIT $limit"
    );
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) $row['total']);
    }
    foreach ($rows as &$row) {
        $row['percent'] = $max > 0 ? (int) round(((int) $row['total'] / $max) * 100) : 0;
    }
    unset($row);
    return $rows;
}

function employee_unique_errors(array $data, ?int $excludeId = null): array
{
    $errors = [];
    $username = trim((string) ($data['username'] ?? ''));
    $employeeNumber = trim((string) ($data['employee_number'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));

    $sql = 'SELECT id FROM users WHERE username = ?' . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1';
    $params = [$username];
    if ($excludeId) {
        $params[] = $excludeId;
    }
    if (db()->value($sql, $params) !== null) {
        $errors['username'] = 'This username is already in use.';
    }

    $sql = "SELECT id FROM users WHERE role = 'ess_staff' AND employee_number = ?"
        . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1';
    $params = [$employeeNumber];
    if ($excludeId) {
        $params[] = $excludeId;
    }
    if (db()->value($sql, $params) !== null) {
        $errors['employee_number'] = 'This employee number is already assigned.';
    }

    if ($email !== '') {
        $sql = 'SELECT id FROM users WHERE email = ?' . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1';
        $params = [$email];
        if ($excludeId) {
            $params[] = $excludeId;
        }
        if (db()->value($sql, $params) !== null) {
            $errors['email'] = 'This email address is already in use.';
        }
    }
    return $errors;
}

function employee_department_position_errors(array $data, ?array $current = null): array
{
    $errors = [];
    $departmentId = (int) ($data['department_id'] ?? 0);
    $positionId = (int) ($data['position_id'] ?? 0);

    $department = db()->one('SELECT id, name, is_active FROM departments WHERE id = ? LIMIT 1', [$departmentId]);
    if ($department === null) {
        $errors['department_id'] = 'Select a valid department.';
    } elseif ((int) $department['is_active'] !== 1 && (int) ($current['department_id'] ?? 0) !== $departmentId) {
        $errors['department_id'] = 'The selected department is inactive.';
    }

    $position = db()->one(
        'SELECT id, title, department_id, is_active FROM positions WHERE id = ? LIMIT 1',
        [$positionId]
    );
    if ($position === null) {
        $errors['position_id'] = 'Select a valid position.';
    } else {
        if ((int) $position['is_active'] !== 1 && (int) ($current['position_id'] ?? 0) !== $positionId) {
            $errors['position_id'] = 'The selected position is inactive.';
        }
        if ($department !== null && !empty($position['department_id'])
            && (int) $position['department_id'] !== (int) $department['id']) {
            $errors['position_id'] = 'The selected position belongs to a different department.';
        }
    }
    return $errors;
}

function normalize_employee_input(array $input): array
{
    $fields = [
        'first_name', 'middle_name', 'last_name', 'sex', 'employee_number', 'department_id',
        'position_id', 'employment_type', 'employment_status', 'hire_date', 'date_of_birth',
        'national_id', 'phone_number', 'email', 'address', 'room_number', 'shelf_number',
        'emergency_contact_name', 'emergency_contact_phone', 'highest_qualification',
        'work_location', 'username', 'password',
    ];
    $normalized = [];
    foreach ($fields as $field) {
        $value = $input[$field] ?? '';
        $normalized[$field] = is_string($value) ? trim($value) : $value;
    }
    $normalized['account_active'] = array_key_exists('account_active', $input)
        ? (in_array((string) $input['account_active'], ['1', 'on', 'true'], true) ? 1 : 0)
        : null;
    return $normalized;
}

/**
 * @return array{ok:bool,id?:int,errors?:array<string,string>}
 */
function employee_save(?int $id, array $input, array $actor): array
{
    $data = normalize_employee_input($input);
    $isEdit = $id !== null;
    $current = $isEdit ? employee_find($id) : null;
    if ($isEdit && $current === null) {
        return ['ok' => false, 'errors' => ['_form' => 'Employee record not found.']];
    }
    if ($isEdit && !empty($current['archived_at'])) {
        return ['ok' => false, 'errors' => ['_form' => 'Restore this employee before editing the record.']];
    }

    $errors = employee_input_errors($data, !$isEdit || $data['password'] !== '');
    $errors += employee_department_position_errors($data, $current);
    $errors += employee_unique_errors($data, $id);
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    try {
        $result = db()->transaction(function (Database $db) use ($id, $isEdit, $current, $data): array {
            $accountActive = $data['account_active'];
            if ($accountActive === null) {
                $accountActive = $isEdit ? (int) ($current['is_active'] ?? 1) : 1;
            }

            if (!$isEdit) {
                $newId = $db->insert(
                    "INSERT INTO users
                        (username, password, role, first_name, middle_name, last_name, sex,
                         employee_number, department_id, position_id, employment_type, employment_status,
                         hire_date, date_of_birth, national_id, phone_number, email, address,
                         room_number, shelf_number, emergency_contact_name, emergency_contact_phone,
                         highest_qualification, work_location, must_change_password, is_active,
                         created_at, updated_at)
                     VALUES (?, ?, 'ess_staff', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())",
                    [
                        mb_strtolower($data['username']),
                        password_hash($data['password'], PASSWORD_DEFAULT),
                        $data['first_name'],
                        $data['middle_name'] !== '' ? $data['middle_name'] : null,
                        $data['last_name'],
                        $data['sex'],
                        $data['employee_number'],
                        (int) $data['department_id'],
                        (int) $data['position_id'],
                        $data['employment_type'],
                        $data['employment_status'],
                        $data['hire_date'],
                        $data['date_of_birth'] !== '' ? $data['date_of_birth'] : null,
                        $data['national_id'] !== '' ? $data['national_id'] : null,
                        $data['phone_number'] !== '' ? $data['phone_number'] : null,
                        $data['email'] !== '' ? $data['email'] : null,
                        $data['address'] !== '' ? $data['address'] : null,
                        $data['room_number'] !== '' ? $data['room_number'] : null,
                        $data['shelf_number'] !== '' ? $data['shelf_number'] : null,
                        $data['emergency_contact_name'] !== '' ? $data['emergency_contact_name'] : null,
                        $data['emergency_contact_phone'] !== '' ? $data['emergency_contact_phone'] : null,
                        $data['highest_qualification'] !== '' ? $data['highest_qualification'] : null,
                        $data['work_location'] !== '' ? $data['work_location'] : null,
                        $accountActive,
                    ]
                );
                return ['id' => $newId, 'created' => true, 'changed' => []];
            }

            $changed = [];
            $labels = employee_field_labels();
            foreach ([
                'first_name', 'middle_name', 'last_name', 'sex', 'employee_number',
                'department_id', 'position_id', 'employment_type', 'employment_status',
                'hire_date', 'date_of_birth', 'national_id', 'phone_number', 'email',
                'address', 'room_number', 'shelf_number', 'emergency_contact_name',
                'emergency_contact_phone', 'highest_qualification', 'work_location', 'username',
            ] as $field) {
                $old = (string) ($current[$field] ?? '');
                $new = (string) ($data[$field] ?? '');
                if ($field === 'username') {
                    $old = mb_strtolower($old);
                    $new = mb_strtolower($new);
                }
                if ($old !== $new) {
                    $changed[] = $labels[$field] ?? $field;
                }
            }

            $passwordSql = '';
            $passwordParams = [];
            if ($data['password'] !== '') {
                $passwordSql = ', password = ?, must_change_password = 1';
                $passwordParams = [password_hash($data['password'], PASSWORD_DEFAULT)];
                $changed[] = 'Password (reset)';
            }

            $db->execute(
                "UPDATE users SET
                    username = ?, first_name = ?, middle_name = ?, last_name = ?, sex = ?,
                    employee_number = ?, department_id = ?, position_id = ?, employment_type = ?,
                    employment_status = ?, hire_date = ?, date_of_birth = ?, national_id = ?,
                    phone_number = ?, email = ?, address = ?, room_number = ?, shelf_number = ?,
                    emergency_contact_name = ?, emergency_contact_phone = ?, highest_qualification = ?,
                    work_location = ?, is_active = ?, updated_at = NOW()$passwordSql
                 WHERE id = ? AND role = 'ess_staff'",
                array_merge([
                    mb_strtolower($data['username']),
                    $data['first_name'],
                    $data['middle_name'] !== '' ? $data['middle_name'] : null,
                    $data['last_name'],
                    $data['sex'],
                    $data['employee_number'],
                    (int) $data['department_id'],
                    (int) $data['position_id'],
                    $data['employment_type'],
                    $data['employment_status'],
                    $data['hire_date'],
                    $data['date_of_birth'] !== '' ? $data['date_of_birth'] : null,
                    $data['national_id'] !== '' ? $data['national_id'] : null,
                    $data['phone_number'] !== '' ? $data['phone_number'] : null,
                    $data['email'] !== '' ? $data['email'] : null,
                    $data['address'] !== '' ? $data['address'] : null,
                    $data['room_number'] !== '' ? $data['room_number'] : null,
                    $data['shelf_number'] !== '' ? $data['shelf_number'] : null,
                    $data['emergency_contact_name'] !== '' ? $data['emergency_contact_name'] : null,
                    $data['emergency_contact_phone'] !== '' ? $data['emergency_contact_phone'] : null,
                    $data['highest_qualification'] !== '' ? $data['highest_qualification'] : null,
                    $data['work_location'] !== '' ? $data['work_location'] : null,
                    $accountActive,
                ], $passwordParams, [$id])
            );

            if ((int) ($current['is_active'] ?? 1) !== $accountActive) {
                audit_log(
                    'account.status',
                    'account',
                    $id,
                    $accountActive === 1 ? 'Self-service account enabled' : 'Self-service account disabled'
                );
            }
            if ($data['password'] !== '') {
                audit_log('account.password_reset', 'account', $id, 'Temporary password set by staff');
            }
            return ['id' => $id, 'created' => false, 'changed' => $changed];
        });
    } catch (Throwable $exception) {
        if (db()->isDuplicateKey($exception)) {
            $errors = employee_unique_errors($data, $id);
            if ($errors === []) {
                $errors['_form'] = 'A unique value is already in use. Review the employee number, username and email.';
            }
            return ['ok' => false, 'errors' => $errors];
        }
        app_log('error', 'Employee save failed', ['reason' => $exception->getMessage()]);
        return ['ok' => false, 'errors' => ['_form' => 'The employee record could not be saved. Please try again.']];
    }

    if ($result['created']) {
        audit_log('employee.create', 'user', (int) $result['id'], 'Employee record and self-service account created');
        notify(
            (int) $result['id'],
            'account',
            'Welcome to the ESS HRMS',
            'Your self-service account is ready. Please sign in with your temporary password and set a new one.',
            'account/password.php'
        );
    } else {
        $changedText = $result['changed'] === [] ? 'No field values changed' : 'Changed: ' . implode(', ', $result['changed']);
        audit_log('employee.update', 'user', (int) $result['id'], $changedText);
        if ($data['password'] !== '') {
            notify(
                (int) $result['id'],
                'account',
                'Your password was reset',
                'An HR administrator set a temporary password for your account. Please sign in and change it.',
                'account/password.php'
            );
        }
    }

    return ['ok' => true, 'id' => (int) $result['id']];
}

function employee_field_labels(): array
{
    return [
        'first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name',
        'sex' => 'Sex', 'employee_number' => 'Employee number', 'department_id' => 'Department',
        'position_id' => 'Position', 'employment_type' => 'Employment type',
        'employment_status' => 'Employment status', 'hire_date' => 'Hire date',
        'date_of_birth' => 'Date of birth', 'national_id' => 'National ID',
        'phone_number' => 'Phone', 'email' => 'Email', 'address' => 'Address',
        'room_number' => 'Room number', 'shelf_number' => 'Shelf number',
        'emergency_contact_name' => 'Emergency contact', 'emergency_contact_phone' => 'Emergency phone',
        'highest_qualification' => 'Qualification', 'work_location' => 'Work location',
        'username' => 'Username',
    ];
}

function employee_archive(int $id, string $reason, array $actor): array
{
    $employee = employee_find($id);
    if ($employee === null) {
        return ['ok' => false, 'error' => 'Employee record not found.'];
    }
    if (!empty($employee['archived_at'])) {
        return ['ok' => false, 'error' => 'This employee is already archived.'];
    }
    $reason = trim($reason);
    if (mb_strlen($reason) > 255) {
        return ['ok' => false, 'error' => 'Archive reason must be 255 characters or fewer.'];
    }

    db()->transaction(function (Database $db) use ($id, $reason): void {
        $db->execute(
            'UPDATE users SET archived_at = NOW(), archive_reason = ?, is_active = 0, updated_at = NOW()
             WHERE id = ? AND role = ?',
            [$reason !== '' ? $reason : null, $id, 'ess_staff']
        );
        $db->execute(
            "UPDATE leave_requests SET status = 'cancelled', review_note = 'Cancelled because the employee was archived',
                updated_at = NOW()
             WHERE user_id = ? AND status = 'pending'",
            [$id]
        );
    });

    audit_log('employee.archive', 'user', $id, 'Employee archived' . ($reason !== '' ? ' with a recorded reason' : ''));
    return ['ok' => true];
}

function employee_restore(int $id, array $actor): array
{
    $employee = employee_find($id);
    if ($employee === null) {
        return ['ok' => false, 'error' => 'Employee record not found.'];
    }
    if (empty($employee['archived_at'])) {
        return ['ok' => false, 'error' => 'This employee is not archived.'];
    }
    db()->execute(
        'UPDATE users SET archived_at = NULL, archive_reason = NULL, is_active = 1,
            must_change_password = 1, updated_at = NOW() WHERE id = ? AND role = ?',
        [$id, 'ess_staff']
    );
    audit_log('employee.restore', 'user', $id, 'Employee restored; password change required');
    notify(
        $id,
        'account',
        'Your account has been restored',
        'Your ESS HRMS account is active again. Please sign in and set a new password.',
        'account/password.php'
    );
    return ['ok' => true];
}

function employee_photo_url(array $user): string
{
    $id = (int) ($user['id'] ?? 0);
    $photo = basename((string) ($user['photo_path'] ?? ''));
    if ($photo !== '' && preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i', $photo)) {
        $full = rtrim((string) config('storage.photos'), '/') . '/' . $photo;
        if (is_file($full)) {
            return url('photo.php?id=' . $id) . '&v=' . (int) filemtime($full);
        }
    }

    $legacy = (string) ($user['image'] ?? '');
    if ($legacy !== '') {
        $base = basename(str_replace('\\', '/', $legacy));
        if (preg_match('/\.(jpe?g|png|webp)$/i', $base) && is_file(BASE_PATH . '/uploads/images/' . $base)) {
            return url('photo.php?id=' . $id) . '&v=legacy';
        }
    }
    return url('assets/img/avatar.svg');
}

function save_employee_photo(int $userId, array $file, array $actor): array
{
    $employee = employee_find($userId);
    if ($employee === null) {
        return ['ok' => false, 'error' => 'Employee record not found.'];
    }
    if (empty($file) || !isset($file['error'])) {
        return ['ok' => false, 'error' => 'Choose a photo to upload.'];
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The photo could not be uploaded. Please try again.'];
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Photo must be smaller than 2 MB.'];
    }
    $original = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'error' => 'Photo must be a JPG, PNG or WebP image.'];
    }
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return ['ok' => false, 'error' => 'The selected file is not a valid image.'];
    }
    $info = @getimagesize((string) $file['tmp_name']);
    if ($info === false || (int) $info[0] > 6000 || (int) $info[1] > 6000) {
        return ['ok' => false, 'error' => 'The image is not valid or is too large.'];
    }

    $directory = rtrim((string) config('storage.photos'), '/');
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        return ['ok' => false, 'error' => 'Photo storage is not writable. Contact the system administrator.'];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $stored)) {
        return ['ok' => false, 'error' => 'The photo could not be saved.'];
    }

    $old = basename((string) ($employee['photo_path'] ?? ''));
    db()->execute('UPDATE users SET photo_path = ?, updated_at = NOW() WHERE id = ?', [$stored, $userId]);
    if ($old !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $old)) {
        $oldPath = $directory . '/' . $old;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
    audit_log('employee.photo', 'user', $userId, 'Employee photo updated');
    return ['ok' => true];
}
