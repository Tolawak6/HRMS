<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function departments_all(bool $activeOnly = false): array
{
    $where = $activeOnly ? 'WHERE d.is_active = 1' : '';
    return db()->all(
        "SELECT d.*,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = 'ess_staff' AND u.archived_at IS NULL) AS employee_count,
            (SELECT COUNT(*) FROM positions p WHERE p.department_id = d.id) AS position_count
         FROM departments d
         $where
         ORDER BY d.is_active DESC, d.name ASC"
    );
}

function department_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one('SELECT * FROM departments WHERE id = ? LIMIT 1', [$id]);
}

function positions_all(bool $activeOnly = false, ?int $departmentId = null): array
{
    $where = [];
    $params = [];
    if ($activeOnly) {
        $where[] = 'p.is_active = 1';
    }
    if ($departmentId !== null && $departmentId > 0) {
        $where[] = 'p.department_id = ?';
        $params[] = $departmentId;
    }
    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    return db()->all(
        "SELECT p.*, d.name AS department_name,
            (SELECT COUNT(*) FROM users u WHERE u.position_id = p.id AND u.role = 'ess_staff' AND u.archived_at IS NULL) AS employee_count
         FROM positions p
         LEFT JOIN departments d ON d.id = p.department_id
         $whereSql
         ORDER BY p.is_active DESC, p.title ASC",
        $params
    );
}

function position_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one(
        'SELECT p.*, d.name AS department_name FROM positions p
         LEFT JOIN departments d ON d.id = p.department_id WHERE p.id = ? LIMIT 1',
        [$id]
    );
}

function department_save(?int $id, array $input, array $actor): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $description = trim((string) ($input['description'] ?? ''));
    $errors = [];

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['name'] = 'Department name must be 2–120 characters.';
    }
    if (!preg_match('/^[A-Z0-9-]{2,20}$/', $code)) {
        $errors['code'] = 'Code must be 2–20 letters, numbers or dashes.';
    }
    if (mb_strlen($description) > 255) {
        $errors['description'] = 'Description must be 255 characters or fewer.';
    }
    if ($errors === []) {
        $existing = $id !== null ? department_find($id) : null;
        $nameCount = (int) db()->value(
            'SELECT COUNT(*) FROM departments WHERE name = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$name, $id] : [$name],
            0
        );
        if ($nameCount > 0) {
            $errors['name'] = 'A department with this name already exists.';
        }
        $codeCount = (int) db()->value(
            'SELECT COUNT(*) FROM departments WHERE code = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$code, $id] : [$code],
            0
        );
        if ($codeCount > 0) {
            $errors['code'] = 'A department with this code already exists.';
        }
        if ($id !== null && $existing === null) {
            $errors['_form'] = 'Department not found.';
        }
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    if ($id === null) {
        $newId = db()->insert(
            'INSERT INTO departments (name, code, description, is_active, created_at, updated_at)
             VALUES (?, ?, ?, 1, NOW(), NOW())',
            [$name, $code, $description !== '' ? $description : null]
        );
        audit_log('department.create', 'department', $newId, 'Department created');
        return ['ok' => true, 'id' => $newId];
    }

    db()->execute(
        'UPDATE departments SET name = ?, code = ?, description = ?, updated_at = NOW() WHERE id = ?',
        [$name, $code, $description !== '' ? $description : null, $id]
    );
    audit_log('department.update', 'department', $id, 'Department details updated');
    return ['ok' => true, 'id' => $id];
}

function department_toggle(int $id, array $actor): array
{
    $department = department_find($id);
    if ($department === null) {
        return ['ok' => false, 'error' => 'Department not found.'];
    }
    $newStatus = (int) $department['is_active'] === 1 ? 0 : 1;
    db()->execute('UPDATE departments SET is_active = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $id]);
    audit_log('department.status', 'department', $id, $newStatus === 1 ? 'Department activated' : 'Department deactivated');
    return ['ok' => true];
}

function position_save(?int $id, array $input, array $actor): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $departmentId = (int) ($input['department_id'] ?? 0);
    $grade = trim((string) ($input['grade'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $errors = [];

    if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
        $errors['title'] = 'Position title must be 2–120 characters.';
    }
    if (!preg_match('/^[A-Z0-9-]{2,30}$/', $code)) {
        $errors['code'] = 'Code must be 2–30 letters, numbers or dashes.';
    }
    if (mb_strlen($grade) > 30) {
        $errors['grade'] = 'Grade must be 30 characters or fewer.';
    }
    if (mb_strlen($description) > 255) {
        $errors['description'] = 'Description must be 255 characters or fewer.';
    }
    if ($departmentId > 0) {
        $department = department_find($departmentId);
        if ($department === null) {
            $errors['department_id'] = 'Select a valid department.';
        } elseif ((int) $department['is_active'] !== 1) {
            $errors['department_id'] = 'The selected department is inactive.';
        }
    }
    if ($errors === []) {
        if ($id !== null && position_find($id) === null) {
            $errors['_form'] = 'Position not found.';
        }
        $codeCount = (int) db()->value(
            'SELECT COUNT(*) FROM positions WHERE code = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$code, $id] : [$code],
            0
        );
        if ($codeCount > 0) {
            $errors['code'] = 'A position with this code already exists.';
        }
        $titleCount = (int) db()->value(
            'SELECT COUNT(*) FROM positions WHERE title = ? AND (department_id <=> ?)' . ($id ? ' AND id <> ?' : ''),
            $id ? [$title, $departmentId > 0 ? $departmentId : null, $id] : [$title, $departmentId > 0 ? $departmentId : null],
            0
        );
        if ($titleCount > 0) {
            $errors['title'] = 'This position title already exists in the selected department.';
        }
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $params = [
        $title,
        $code,
        $departmentId > 0 ? $departmentId : null,
        $grade !== '' ? $grade : null,
        $description !== '' ? $description : null,
    ];

    if ($id === null) {
        $newId = db()->insert(
            'INSERT INTO positions (title, code, department_id, grade, description, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())',
            $params
        );
        audit_log('position.create', 'position', $newId, 'Position created');
        return ['ok' => true, 'id' => $newId];
    }

    $params[] = $id;
    db()->execute(
        'UPDATE positions SET title = ?, code = ?, department_id = ?, grade = ?, description = ?, updated_at = NOW()
         WHERE id = ?',
        $params
    );
    audit_log('position.update', 'position', $id, 'Position details updated');
    return ['ok' => true, 'id' => $id];
}

function position_toggle(int $id, array $actor): array
{
    $position = position_find($id);
    if ($position === null) {
        return ['ok' => false, 'error' => 'Position not found.'];
    }
    $newStatus = (int) $position['is_active'] === 1 ? 0 : 1;
    db()->execute('UPDATE positions SET is_active = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $id]);
    audit_log('position.status', 'position', $id, $newStatus === 1 ? 'Position activated' : 'Position deactivated');
    return ['ok' => true];
}
