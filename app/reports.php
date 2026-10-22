<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function report_summary(int $year): array
{
    return db()->one(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL) AS headcount,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL AND employment_status = 'Active') AS active,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL AND employment_status = 'On Leave') AS on_leave,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NULL AND YEAR(hire_date) = ?) AS new_hires,
            (SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND archived_at IS NOT NULL) AS archived,
            (SELECT COUNT(*) FROM leave_requests WHERE YEAR(start_date) = ? AND status = 'approved') AS approved_leave_requests,
            (SELECT COUNT(*) FROM leave_requests WHERE YEAR(start_date) = ? AND status = 'pending') AS pending_leave_requests",
        [$year, $year, $year]
    ) ?? [
        'headcount' => 0, 'active' => 0, 'on_leave' => 0, 'new_hires' => 0,
        'archived' => 0, 'approved_leave_requests' => 0, 'pending_leave_requests' => 0,
    ];
}

function report_by_department(): array
{
    return db()->all(
        "SELECT COALESCE(d.name, 'Unassigned') AS label,
                COUNT(*) AS headcount,
                SUM(CASE WHEN u.employment_status = 'Active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN u.employment_status = 'On Leave' THEN 1 ELSE 0 END) AS on_leave
         FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.role = 'ess_staff' AND u.archived_at IS NULL
         GROUP BY u.department_id, d.name
         ORDER BY headcount DESC, label ASC"
    );
}

function report_by_sex(): array
{
    return db()->all(
        "SELECT COALESCE(NULLIF(u.sex, ''), 'Not recorded') AS label, COUNT(*) AS total
         FROM users u
         WHERE u.role = 'ess_staff' AND u.archived_at IS NULL
         GROUP BY u.sex ORDER BY total DESC"
    );
}

function report_by_employment_type(): array
{
    return db()->all(
        "SELECT COALESCE(NULLIF(u.employment_type, ''), 'Not recorded') AS label, COUNT(*) AS total
         FROM users u
         WHERE u.role = 'ess_staff' AND u.archived_at IS NULL
         GROUP BY u.employment_type ORDER BY total DESC"
    );
}

function report_by_status(): array
{
    return db()->all(
        "SELECT u.employment_status AS label, COUNT(*) AS total
         FROM users u
         WHERE u.role = 'ess_staff' AND u.archived_at IS NULL
         GROUP BY u.employment_status ORDER BY total DESC"
    );
}

function report_leave_by_type(int $year): array
{
    return db()->all(
        "SELECT lt.name AS label,
                COUNT(lr.id) AS requests,
                COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.working_days ELSE 0 END), 0) AS approved_days,
                COALESCE(SUM(CASE WHEN lr.status = 'pending' THEN lr.working_days ELSE 0 END), 0) AS pending_days
         FROM leave_types lt
         LEFT JOIN leave_requests lr ON lr.leave_type_id = lt.id AND YEAR(lr.start_date) = ?
         GROUP BY lt.id, lt.name
         ORDER BY approved_days DESC, lt.name ASC",
        [$year]
    );
}

function report_leave_by_status(int $year): array
{
    return db()->all(
        'SELECT lr.status AS label, COUNT(*) AS total, COALESCE(SUM(lr.working_days), 0) AS days
         FROM leave_requests lr WHERE YEAR(lr.start_date) = ?
         GROUP BY lr.status ORDER BY total DESC',
        [$year]
    );
}

function report_hires_by_year(int $fromYear, int $toYear): array
{
    $fromYear = max(1990, min(2100, $fromYear));
    $toYear = max($fromYear, min(2100, $toYear));
    $rows = db()->all(
        "SELECT YEAR(hire_date) AS year, COUNT(*) AS total
         FROM users
         WHERE role = 'ess_staff' AND hire_date IS NOT NULL AND YEAR(hire_date) BETWEEN ? AND ?
         GROUP BY YEAR(hire_date) ORDER BY year ASC",
        [$fromYear, $toYear]
    );
    $indexed = [];
    foreach ($rows as $row) {
        $indexed[(int) $row['year']] = (int) $row['total'];
    }
    $result = [];
    for ($year = $fromYear; $year <= $toYear; $year++) {
        $result[] = ['year' => $year, 'total' => $indexed[$year] ?? 0];
    }
    return $result;
}

function report_leave_export_rows(int $year, string $status = ''): array
{
    $where = ['YEAR(lr.start_date) = ?'];
    $params = [$year];
    if (in_array($status, annual_leave_statuses(), true)) {
        $where[] = 'lr.status = ?';
        $params[] = $status;
    }
    $whereSql = implode(' AND ', $where);
    return db()->all(
        "SELECT lr.id, u.employee_number, u.first_name, u.middle_name, u.last_name,
                lt.name AS leave_type_name, lr.start_date, lr.end_date, lr.working_days,
                lr.status, lr.created_at, lr.reviewed_at,
                CONCAT(COALESCE(reviewer.first_name, ''), ' ', COALESCE(reviewer.last_name, '')) AS reviewer_name
         FROM leave_requests lr
         JOIN users u ON u.id = lr.user_id
         JOIN leave_types lt ON lt.id = lr.leave_type_id
         LEFT JOIN users reviewer ON reviewer.id = lr.reviewed_by
         WHERE $whereSql
         ORDER BY lr.start_date ASC, lr.id ASC
         LIMIT 10000",
        $params
    );
}
