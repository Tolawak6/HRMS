<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function leave_types_active(): array
{
    return db()->all('SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name ASC');
}

function leave_types_all(): array
{
    return db()->all(
        'SELECT lt.*,
            (SELECT COUNT(*) FROM leave_requests lr WHERE lr.leave_type_id = lt.id) AS request_count
         FROM leave_types lt ORDER BY lt.is_active DESC, lt.name ASC'
    );
}

function leave_type_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one('SELECT * FROM leave_types WHERE id = ? LIMIT 1', [$id]);
}

function leave_request_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one(
        'SELECT lr.*, u.first_name, u.middle_name, u.last_name, u.employee_number, u.username,
                lt.name AS leave_type_name, lt.code AS leave_type_code,
                reviewer.first_name AS reviewer_first, reviewer.last_name AS reviewer_last
         FROM leave_requests lr
         JOIN users u ON u.id = lr.user_id
         JOIN leave_types lt ON lt.id = lr.leave_type_id
         LEFT JOIN users reviewer ON reviewer.id = lr.reviewed_by
         WHERE lr.id = ? LIMIT 1',
        [$id]
    );
}

function leave_entitlement(int $userId, int $leaveTypeId, int $year): float
{
    $override = db()->value(
        'SELECT entitled_days FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = ?',
        [$userId, $leaveTypeId, $year]
    );
    if ($override !== null) {
        return (float) $override;
    }
    $default = db()->value('SELECT annual_entitlement FROM leave_types WHERE id = ?', [$leaveTypeId], 0);
    return (float) $default;
}

function leave_used_days(
    int $userId,
    int $leaveTypeId,
    int $year,
    array $statuses = ['approved'],
    ?int $excludeRequestId = null
): int {
    $statuses = array_values(array_intersect($statuses, annual_leave_statuses()));
    if ($statuses === []) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$userId, $leaveTypeId, $year], $statuses);
    $sql = "SELECT COALESCE(SUM(working_days), 0) FROM leave_requests
            WHERE user_id = ? AND leave_type_id = ? AND YEAR(start_date) = ?
              AND status IN ($placeholders)";
    if ($excludeRequestId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeRequestId;
    }
    return (int) db()->value($sql, $params, 0);
}

function leave_balance_summary(int $userId, int $year): array
{
    $summary = [];
    foreach (leave_types_active() as $type) {
        $entitlement = leave_entitlement($userId, (int) $type['id'], $year);
        $approved = leave_used_days($userId, (int) $type['id'], $year, ['approved']);
        $pending = leave_used_days($userId, (int) $type['id'], $year, ['pending']);
        $summary[] = [
            'type'        => $type,
            'entitlement' => $entitlement,
            'approved'    => $approved,
            'pending'     => $pending,
            'remaining'   => $entitlement - $approved - $pending,
        ];
    }
    return $summary;
}

/**
 * Database-dependent checks for a new or existing request.
 *
 * @return array<string,string>
 */
function leave_submission_errors(
    int $userId,
    int $leaveTypeId,
    string $startDate,
    string $endDate,
    ?int $excludeRequestId = null
): array {
    $errors = [];
    $type = leave_type_find($leaveTypeId);
    if ($type === null || (int) $type['is_active'] !== 1) {
        $errors['leave_type_id'] = 'Select an active leave type.';
        return $errors;
    }

    $overlapSql = "SELECT id FROM leave_requests
        WHERE user_id = ? AND status IN ('pending','approved')
          AND start_date <= ? AND end_date >= ?";
    $overlapParams = [$userId, $endDate, $startDate];
    if ($excludeRequestId !== null) {
        $overlapSql .= ' AND id <> ?';
        $overlapParams[] = $excludeRequestId;
    }
    if (db()->value($overlapSql . ' LIMIT 1', $overlapParams) !== null) {
        $errors['start_date'] = 'You already have a pending or approved request that overlaps these dates.';
        return $errors;
    }

    $year = (int) substr($startDate, 0, 4);
    $requested = working_days_between($startDate, $endDate);
    $entitlement = leave_entitlement($userId, $leaveTypeId, $year);
    $committed = leave_used_days($userId, $leaveTypeId, $year, ['approved', 'pending'], $excludeRequestId);
    $remaining = $entitlement - $committed;

    if ($entitlement <= 0) {
        $errors['leave_type_id'] = 'No entitlement is configured for this leave type. Contact Human Resources.';
    } elseif ($requested > $remaining) {
        $errors['end_date'] = 'This request exceeds the available balance of '
            . format_days(max(0, $remaining)) . ' working day(s) for ' . $year . '.';
    }
    return $errors;
}

/**
 * @return array{ok:bool,id?:int,errors?:array<string,string>}
 */
function leave_request_create(int $userId, array $input): array
{
    $data = [
        'leave_type_id' => (int) ($input['leave_type_id'] ?? 0),
        'start_date'    => trim((string) ($input['start_date'] ?? '')),
        'end_date'      => trim((string) ($input['end_date'] ?? '')),
        'reason'        => trim((string) ($input['reason'] ?? '')),
    ];
    $errors = leave_input_errors($data);
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    try {
        $result = db()->transaction(function (Database $db) use ($userId, $data): array {
            $db->one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $dbErrors = leave_submission_errors(
                $userId,
                (int) $data['leave_type_id'],
                $data['start_date'],
                $data['end_date']
            );
            if ($dbErrors !== []) {
                return ['ok' => false, 'errors' => $dbErrors];
            }
            $days = working_days_between($data['start_date'], $data['end_date']);
            $id = $db->insert(
                'INSERT INTO leave_requests
                    (user_id, leave_type_id, start_date, end_date, working_days, reason, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $userId,
                    (int) $data['leave_type_id'],
                    $data['start_date'],
                    $data['end_date'],
                    $days,
                    $data['reason'] !== '' ? $data['reason'] : null,
                    'pending',
                ]
            );
            return ['ok' => true, 'id' => $id, 'days' => $days];
        });
    } catch (Throwable $exception) {
        app_log('error', 'Leave request failed', ['reason' => $exception->getMessage()]);
        return ['ok' => false, 'errors' => ['_form' => 'The leave request could not be submitted. Please try again.']];
    }

    if (!$result['ok']) {
        return $result;
    }

    $type = leave_type_find((int) $data['leave_type_id']);
    $employee = user_find($userId);
    audit_log('leave.request', 'leave_request', (int) $result['id'], 'Leave request submitted');
    notify_roles(
        ['admin', 'hr_staff'],
        'leave',
        'New leave request',
        person_name($employee ?? []) . ' requested ' . ($type['name'] ?? 'leave') . ' from '
            . format_date($data['start_date']) . ' to ' . format_date($data['end_date']) . '.',
        'leave/requests.php'
    );
    return ['ok' => true, 'id' => (int) $result['id']];
}

function leave_request_cancel(int $requestId, int $userId): array
{
    $request = leave_request_find($requestId);
    if ($request === null || (int) $request['user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'Leave request not found.'];
    }
    if ((string) $request['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'Only pending requests can be cancelled.'];
    }
    db()->execute(
        "UPDATE leave_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'",
        [$requestId, $userId]
    );
    audit_log('leave.cancel', 'leave_request', $requestId, 'Leave request cancelled by employee');
    notify_roles(
        ['admin', 'hr_staff'],
        'leave',
        'Leave request cancelled',
        person_name(user_find($userId) ?? []) . ' cancelled a pending leave request.',
        'leave/requests.php'
    );
    return ['ok' => true];
}

function leave_request_decide(int $requestId, string $decision, string $note, array $reviewer): array
{
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return ['ok' => false, 'error' => 'Invalid decision.'];
    }
    $note = trim($note);
    if (mb_strlen($note) > 500) {
        return ['ok' => false, 'error' => 'Decision note must be 500 characters or fewer.'];
    }
    if ($decision === 'reject' && $note === '') {
        return ['ok' => false, 'error' => 'A reason is required when rejecting a request.'];
    }

    try {
        $result = db()->transaction(function (Database $db) use ($requestId, $decision, $note, $reviewer): array {
            $request = $db->one('SELECT * FROM leave_requests WHERE id = ? FOR UPDATE', [$requestId]);
            if ($request === null) {
                return ['ok' => false, 'error' => 'Leave request not found.'];
            }
            if ((string) $request['status'] !== 'pending') {
                return ['ok' => false, 'error' => 'This request has already been reviewed.'];
            }
            if ($decision === 'approve') {
                $balanceErrors = leave_submission_errors(
                    (int) $request['user_id'],
                    (int) $request['leave_type_id'],
                    (string) $request['start_date'],
                    (string) $request['end_date'],
                    (int) $request['id']
                );
                // Overlap errors are expected here because this request itself
                // is pending; only balance issues block approval.
                if (isset($balanceErrors['end_date']) || isset($balanceErrors['leave_type_id'])) {
                    return ['ok' => false, 'error' => reset($balanceErrors)];
                }
            }
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $db->execute(
                'UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(),
                    review_note = ?, updated_at = NOW() WHERE id = ?',
                [$status, (int) $reviewer['id'], $note !== '' ? $note : null, $requestId]
            );
            return ['ok' => true, 'user_id' => (int) $request['user_id'], 'status' => $status];
        });
    } catch (Throwable $exception) {
        app_log('error', 'Leave decision failed', ['reason' => $exception->getMessage()]);
        return ['ok' => false, 'error' => 'The decision could not be saved. Please try again.'];
    }

    if (!$result['ok']) {
        return $result;
    }
    $action = $result['status'] === 'approved' ? 'leave.approve' : 'leave.reject';
    audit_log($action, 'leave_request', $requestId, $note !== '' ? 'Decision recorded with note' : 'Decision recorded');
    $request = leave_request_find($requestId);
    notify(
        (int) $result['user_id'],
        'leave',
        'Leave request ' . $result['status'],
        'Your ' . ($request['leave_type_name'] ?? 'leave') . ' request for '
            . format_date($request['start_date'] ?? '') . ' to ' . format_date($request['end_date'] ?? '')
            . ' was ' . $result['status'] . '.',
        'leave/index.php'
    );
    return ['ok' => true];
}

function leave_requests_for_user(int $userId, int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    return db()->all(
        'SELECT lr.*, lt.name AS leave_type_name
         FROM leave_requests lr JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE lr.user_id = ? ORDER BY lr.created_at DESC, lr.id DESC LIMIT ' . $limit,
        [$userId]
    );
}

function leave_filters(): array
{
    $status = get_str('status');
    return [
        'q'        => get_str('q'),
        'status'   => in_array($status, annual_leave_statuses(), true) ? $status : '',
        'type'     => max(0, get_int('type')),
        'year'     => max(2000, min(2100, get_int('year', current_year()))),
        'page'     => max(1, get_int('page', 1)),
        'per_page' => per_page_value(25),
    ];
}

function leave_search(array $filters): array
{
    $where = ['YEAR(lr.start_date) = ?'];
    $params = [$filters['year']];
    if ($filters['status'] !== '') {
        $where[] = 'lr.status = ?';
        $params[] = $filters['status'];
    }
    if ($filters['type'] > 0) {
        $where[] = 'lr.leave_type_id = ?';
        $params[] = $filters['type'];
    }
    if ($filters['q'] !== '') {
        $term = '%' . like_term(mb_substr($filters['q'], 0, 80)) . '%';
        $where[] = "(u.first_name LIKE ? ESCAPE '!' OR u.last_name LIKE ? ESCAPE '!'
                     OR u.employee_number LIKE ? ESCAPE '!' OR u.username LIKE ? ESCAPE '!')";
        array_push($params, $term, $term, $term, $term);
    }
    $whereSql = implode(' AND ', $where);
    $total = (int) db()->value(
        "SELECT COUNT(*) FROM leave_requests lr JOIN users u ON u.id = lr.user_id WHERE $whereSql",
        $params,
        0
    );
    $pagination = paginate($total, $filters['per_page'], $filters['page']);
    $rows = db()->all(
        "SELECT lr.*, u.first_name, u.middle_name, u.last_name, u.employee_number,
                lt.name AS leave_type_name,
                reviewer.first_name AS reviewer_first, reviewer.last_name AS reviewer_last
         FROM leave_requests lr
         JOIN users u ON u.id = lr.user_id
         JOIN leave_types lt ON lt.id = lr.leave_type_id
         LEFT JOIN users reviewer ON reviewer.id = lr.reviewed_by
         WHERE $whereSql
         ORDER BY CASE lr.status WHEN 'pending' THEN 0 ELSE 1 END, lr.start_date ASC, lr.id DESC
         LIMIT " . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
        $params
    );
    return ['rows' => $rows, 'pagination' => $pagination, 'filters' => $filters];
}

function leave_pending_count(): int
{
    return (int) db()->value("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'", [], 0);
}

function leave_type_save(?int $id, array $input, array $actor): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $entitlement = (float) ($input['annual_entitlement'] ?? 0);
    $isPaid = isset($input['is_paid']) ? 1 : 0;
    $description = trim((string) ($input['description'] ?? ''));
    $errors = [];

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        $errors['name'] = 'Leave type name must be 2–80 characters.';
    }
    if (!preg_match('/^[A-Z0-9-]{2,20}$/', $code)) {
        $errors['code'] = 'Code must be 2–20 letters, numbers or dashes.';
    }
    if ($entitlement < 0 || $entitlement > 365) {
        $errors['annual_entitlement'] = 'Entitlement must be between 0 and 365 days.';
    }
    if (mb_strlen($description) > 255) {
        $errors['description'] = 'Description must be 255 characters or fewer.';
    }
    if ($errors === []) {
        if ($id !== null && leave_type_find($id) === null) {
            $errors['_form'] = 'Leave type not found.';
        }
        $nameCount = (int) db()->value(
            'SELECT COUNT(*) FROM leave_types WHERE name = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$name, $id] : [$name],
            0
        );
        if ($nameCount > 0) {
            $errors['name'] = 'A leave type with this name already exists.';
        }
        $codeCount = (int) db()->value(
            'SELECT COUNT(*) FROM leave_types WHERE code = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$code, $id] : [$code],
            0
        );
        if ($codeCount > 0) {
            $errors['code'] = 'A leave type with this code already exists.';
        }
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $params = [$name, $code, $entitlement, $isPaid, $description !== '' ? $description : null];
    if ($id === null) {
        $newId = db()->insert(
            'INSERT INTO leave_types (name, code, annual_entitlement, is_paid, description, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())',
            $params
        );
        audit_log('leave.type.create', 'leave_type', $newId, 'Leave type created');
        return ['ok' => true, 'id' => $newId];
    }
    $params[] = $id;
    db()->execute(
        'UPDATE leave_types SET name = ?, code = ?, annual_entitlement = ?, is_paid = ?, description = ?, updated_at = NOW()
         WHERE id = ?',
        $params
    );
    audit_log('leave.type.update', 'leave_type', $id, 'Leave type updated');
    return ['ok' => true, 'id' => $id];
}

function leave_type_toggle(int $id, array $actor): array
{
    $type = leave_type_find($id);
    if ($type === null) {
        return ['ok' => false, 'error' => 'Leave type not found.'];
    }
    $newStatus = (int) $type['is_active'] === 1 ? 0 : 1;
    db()->execute('UPDATE leave_types SET is_active = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $id]);
    audit_log('leave.type.status', 'leave_type', $id, $newStatus === 1 ? 'Leave type activated' : 'Leave type deactivated');
    return ['ok' => true];
}

function leave_balance_set(int $userId, int $leaveTypeId, int $year, ?float $days, string $note, array $actor): array
{
    $employee = employee_find($userId);
    if ($employee === null) {
        return ['ok' => false, 'error' => 'Employee record not found.'];
    }
    $type = leave_type_find($leaveTypeId);
    if ($type === null) {
        return ['ok' => false, 'error' => 'Leave type not found.'];
    }
    if ($year < 2000 || $year > 2100) {
        return ['ok' => false, 'error' => 'Select a valid year.'];
    }
    $note = trim($note);
    if (mb_strlen($note) > 255) {
        return ['ok' => false, 'error' => 'Note must be 255 characters or fewer.'];
    }

    if ($days === null) {
        db()->execute(
            'DELETE FROM leave_balances WHERE user_id = ? AND leave_type_id = ? AND year = ?',
            [$userId, $leaveTypeId, $year]
        );
    } else {
        if ($days < 0 || $days > 365) {
            return ['ok' => false, 'error' => 'Entitlement must be between 0 and 365 days.'];
        }
        db()->execute(
            'INSERT INTO leave_balances (user_id, leave_type_id, year, entitled_days, note, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE entitled_days = VALUES(entitled_days), note = VALUES(note), updated_at = NOW()',
            [$userId, $leaveTypeId, $year, $days, $note !== '' ? $note : null]
        );
    }
    audit_log('leave.balance.set', 'user', $userId, 'Leave entitlement adjusted for ' . $year);
    return ['ok' => true];
}

function leave_type_usage(int $leaveTypeId): int
{
    return (int) db()->value('SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = ?', [$leaveTypeId], 0);
}
