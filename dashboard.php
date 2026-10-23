<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$user = require_auth();
$role = (string) $user['role'];
$isStaff = in_array($role, ['admin', 'hr_staff'], true);
$year = current_year();

$pageTitle = 'Dashboard';
$topbarTitle = $isStaff ? 'HR Workspace' : 'Self Service';
$activeNav = 'dashboard';

$greeting = (int) date('G') < 12 ? 'Good morning' : ((int) date('G') < 17 ? 'Good afternoon' : 'Good evening');
$firstName = trim((string) ($user['first_name'] ?? '')) ?: (string) $user['username'];

if ($isStaff) {
    $summary = report_summary($year);
    $distribution = report_by_department();
    $statusMix = report_by_status();
    $pending = db()->all(
        "SELECT lr.id, lr.start_date, lr.end_date, lr.working_days, lr.created_at,
                u.first_name, u.middle_name, u.last_name, u.employee_number,
                lt.name AS leave_type_name
         FROM leave_requests lr
         JOIN users u ON u.id = lr.user_id
         JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE lr.status = 'pending'
         ORDER BY lr.created_at ASC
         LIMIT 5"
    );
    $recentEmployees = db()->all(
        "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.employee_number,
                u.employment_status, u.hire_date, u.photo_path, u.image,
                d.name AS department_name, p.title AS position_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         LEFT JOIN positions p ON p.id = u.position_id
         WHERE u.role = 'ess_staff' AND u.archived_at IS NULL
         ORDER BY u.created_at DESC, u.id DESC
         LIMIT 6"
    );
    $recentActivity = can_view_audit()
        ? db()->all(
            "SELECT al.action, al.description, al.created_at, al.entity_type,
                    u.first_name, u.last_name, u.username
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT 6"
        )
        : [];
    $departmentMax = 0;
    foreach ($distribution as $row) {
        $departmentMax = max($departmentMax, (int) $row['headcount']);
    }
} else {
    $balances = leave_balance_summary((int) $user['id'], $year);
    $myLeave = leave_requests_for_user((int) $user['id'], 6);
    $documentCount = (int) db()->value(
        'SELECT COUNT(*) FROM file_attachments WHERE ess_staff_id = ?',
        [(int) $user['id']],
        0
    );
    $notifications = recent_notifications((int) $user['id'], 4);
    $department = null;
    if (!empty($user['department_id'])) {
        $department = db()->one('SELECT name, code FROM departments WHERE id = ? LIMIT 1', [(int) $user['department_id']]);
    }
    $position = null;
    if (!empty($user['position_id'])) {
        $position = db()->one('SELECT title FROM positions WHERE id = ? LIMIT 1', [(int) $user['position_id']]);
    }
    $totalBalance = 0.0;
    $usedBalance = 0.0;
    foreach ($balances as $balance) {
        $totalBalance += (float) $balance['entitlement'];
        $usedBalance += (float) $balance['approved'] + (float) $balance['pending'];
    }
}

include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($greeting) ?>, <?= e($firstName) ?></h1>
        <p class="page-subtitle">
            <?= e(date('l, j F Y')) ?> ·
            <?= $isStaff ? 'Workforce overview for ' . e((string) $year) : 'Your employment and leave at a glance' ?>
        </p>
    </div>
    <div class="page-actions">
        <?php if ($isStaff): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('reports/index.php')) ?>"><?= icon('bar-chart') ?> Reports</a>
            <a class="btn btn-primary" href="<?= e(url('employees/create.php')) ?>"><?= icon('person-plus') ?> Add employee</a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('profile.php')) ?>"><?= icon('person-vcard') ?> My profile</a>
            <a class="btn btn-primary" href="<?= e(url('leave/request.php')) ?>"><?= icon('calendar-plus') ?> Request leave</a>
        <?php endif; ?>
    </div>
</div>

<?php if ((int) ($user['must_change_password'] ?? 0) === 1): ?>
    <div class="alert alert-warning" role="alert">
        <?= icon('exclamation-triangle') ?>
        <div class="flex-grow-1">
            <strong>Temporary password in use.</strong> Set your own password to secure your account.
        </div>
        <a class="btn btn-sm btn-warning" href="<?= e(url('account/password.php')) ?>">Set password</a>
    </div>
<?php endif; ?>

<?php if ($isStaff): ?>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon"><?= icon('people') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= number_format((int) $summary['headcount']) ?></div>
                <div class="stat-label">Employees on record</div>
                <div class="stat-meta"><?= number_format((int) $summary['archived']) ?> archived</div>
            </div>
        </div>
        <div class="stat-card accent-green">
            <div class="stat-icon is-green"><?= icon('person-check') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= number_format((int) $summary['active']) ?></div>
                <div class="stat-label">Active employees</div>
                <div class="stat-meta"><?= number_format((int) $summary['on_leave']) ?> currently on leave</div>
            </div>
        </div>
        <div class="stat-card accent-tan">
            <div class="stat-icon is-gold"><?= icon('person-plus') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= number_format((int) $summary['new_hires']) ?></div>
                <div class="stat-label">New hires in <?= (int) $year ?></div>
                <div class="stat-meta">Based on hire date</div>
            </div>
        </div>
        <div class="stat-card accent-danger">
            <div class="stat-icon is-danger"><?= icon('calendar2-week') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= number_format(leave_pending_count()) ?></div>
                <div class="stat-label">Leave requests awaiting review</div>
                <div class="stat-meta"><?= number_format((int) $summary['approved_leave_requests']) ?> approved this year</div>
            </div>
        </div>
    </div>

    <div class="content-grid content-grid-main">
        <section class="panel" aria-labelledby="deptHeading">
            <div class="panel-header">
                <div>
                    <h2 id="deptHeading">Headcount by department</h2>
                    <div class="meta">Active employees only</div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('reports/by_department.php')) ?>">View report</a>
            </div>
            <div class="panel-body">
                <?php if ($distribution === []): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('building') ?></div>
                        <h3>No department data yet</h3>
                        <p>Create departments and assign employees to see the distribution.</p>
                        <a class="btn btn-sm btn-primary" href="<?= e(url('organization/departments.php')) ?>">Manage departments</a>
                    </div>
                <?php else: ?>
                    <div class="bar-list">
                        <?php foreach (array_slice($distribution, 0, 6) as $row): ?>
                            <?php $pct = $departmentMax > 0 ? ((int) $row['headcount'] / $departmentMax) * 100 : 0; ?>
                            <div class="bar-row">
                                <div class="bar-top">
                                    <strong><?= e((string) $row['label']) ?></strong>
                                    <span><?= number_format((int) $row['headcount']) ?> employee<?= (int) $row['headcount'] === 1 ? '' : 's' ?></span>
                                </div>
                                <div class="progress-thin" role="img"
                                     aria-label="<?= e((string) $row['label']) ?>: <?= (int) $row['headcount'] ?> employees">
                                    <div class="bar" style="width: <?= e((string) round($pct, 1)) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel" aria-labelledby="statusHeading">
            <div class="panel-header">
                <div>
                    <h2 id="statusHeading">Employment status</h2>
                    <div class="meta">All active records</div>
                </div>
            </div>
            <div class="panel-body">
                <?php $statusTotal = 0; foreach ($statusMix as $s) { $statusTotal += (int) $s['total']; } ?>
                <?php if ($statusTotal === 0): ?>
                    <p class="text-muted mb-0">No employee records available.</p>
                <?php else: ?>
                    <div class="bar-list">
                        <?php foreach ($statusMix as $s): ?>
                            <?php $share = $statusTotal > 0 ? ((int) $s['total'] / $statusTotal) * 100 : 0; ?>
                            <div class="bar-row">
                                <div class="bar-top">
                                    <strong><?= e((string) $s['label']) ?></strong>
                                    <span><?= (int) $s['total'] ?> · <?= e((string) round($share)) ?>%</span>
                                </div>
                                <div class="progress-thin">
                                    <div class="bar<?= $s['label'] === 'On Leave' ? ' is-gold' : (((string) $s['label']) === 'Active' ? ' is-green' : '') ?>"
                                         style="width: <?= e((string) round($share, 1)) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="content-grid">
        <section class="panel" aria-labelledby="pendingHeading">
            <div class="panel-header">
                <div>
                    <h2 id="pendingHeading">Pending leave requests</h2>
                    <div class="meta">Oldest first · <?= count($pending) ?> shown</div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/requests.php?status=pending')) ?>">Review all</a>
            </div>
            <?php if ($pending === []): ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('check2-circle') ?></div>
                    <h3>No pending requests</h3>
                    <p>Every leave request has been reviewed.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table-app">
                        <thead>
                            <tr>
                                <th scope="col">Employee</th>
                                <th scope="col">Type</th>
                                <th scope="col">Dates</th>
                                <th scope="col" class="text-end">Days</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending as $row): ?>
                            <tr>
                                <td>
                                    <a class="row-title" href="<?= e(url('employees/view.php?id=' . (int) $row['id'])) ?>">
                                        <?= e(person_name($row)) ?>
                                    </a>
                                    <div class="row-meta"><?= e((string) ($row['employee_number'] ?? '')) ?></div>
                                </td>
                                <td><?= e((string) $row['leave_type_name']) ?></td>
                                <td class="text-nowrap"><?= e(format_date((string) $row['start_date'])) ?> – <?= e(format_date((string) $row['end_date'])) ?></td>
                                <td class="text-end"><?= e(format_days((float) $row['working_days'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="recentHeading">
            <div class="panel-header">
                <div>
                    <h2 id="recentHeading">Recently added employees</h2>
                    <div class="meta">Latest personnel records</div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('employees/index.php')) ?>">All employees</a>
            </div>
            <?php if ($recentEmployees === []): ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('person-plus') ?></div>
                    <h3>No employees yet</h3>
                    <p>Add the first employee record to get started.</p>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('employees/create.php')) ?>">Add employee</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table-app">
                        <thead>
                            <tr>
                                <th scope="col">Employee</th>
                                <th scope="col">Department</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentEmployees as $row): ?>
                            <tr>
                                <td>
                                    <div class="person-cell">
                                        <img class="avatar avatar-xs" src="<?= e(employee_photo_url($row)) ?>" alt="" loading="lazy">
                                        <div>
                                            <a class="row-title" href="<?= e(url('employees/view.php?id=' . (int) $row['id'])) ?>">
                                                <?= e(person_name($row)) ?>
                                            </a>
                                            <div class="row-meta"><?= e((string) ($row['position_name'] ?? 'No position assigned')) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e((string) ($row['department_name'] ?? 'Unassigned')) ?></td>
                                <td><span class="badge badge-soft-<?= e(status_badge_class((string) $row['employment_status'])) ?>"><?= e((string) $row['employment_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($recentActivity !== []): ?>
        <section class="panel" aria-labelledby="activityHeading">
            <div class="panel-header">
                <div>
                    <h2 id="activityHeading">Recent system activity</h2>
                    <div class="meta">Security-relevant events</div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('admin/audit.php')) ?>">Full audit log</a>
            </div>
            <div class="table-wrap">
                <table class="table-app">
                    <thead>
                        <tr><th scope="col">Event</th><th scope="col">Actor</th><th scope="col">Detail</th><th scope="col">When</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentActivity as $entry): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(audit_action_label((string) $entry['action'])) ?></td>
                            <td><?= e(trim((string) ($entry['first_name'] ?? '') . ' ' . (string) ($entry['last_name'] ?? '')) ?: (string) ($entry['username'] ?? 'System')) ?></td>
                            <td class="text-muted"><?= e((string) ($entry['description'] ?? '—')) ?></td>
                            <td class="text-nowrap text-muted"><?= e(time_ago((string) $entry['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

<?php else: ?>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon"><?= icon('calendar2-check') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= e(format_days($totalBalance)) ?></div>
                <div class="stat-label">Leave entitlement in <?= (int) $year ?></div>
                <div class="stat-meta">Across <?= count($balances) ?> leave type<?= count($balances) === 1 ? '' : 's' ?></div>
            </div>
        </div>
        <div class="stat-card accent-green">
            <div class="stat-icon is-green"><?= icon('hourglass-split') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= e(format_days($usedBalance)) ?></div>
                <div class="stat-label">Leave used</div>
                <div class="stat-meta">Approved and pending days</div>
            </div>
        </div>
        <div class="stat-card accent-tan">
            <div class="stat-icon is-gold"><?= icon('folder2-open') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= number_format($documentCount) ?></div>
                <div class="stat-label">Documents on file</div>
                <div class="stat-meta">Managed by Human Resources</div>
            </div>
        </div>
        <div class="stat-card accent-danger">
            <div class="stat-icon is-neutral"><?= icon('person-badge') ?></div>
            <div class="stat-body">
                <div class="stat-value"><?= e((string) ($user['employee_number'] ?? '—')) ?></div>
                <div class="stat-label">Employee number</div>
                <div class="stat-meta"><?= e((string) ($position->title ?? 'No position assigned')) ?></div>
            </div>
        </div>
    </div>

    <div class="content-grid content-grid-main">
        <section class="panel" aria-labelledby="balanceHeading">
            <div class="panel-header">
                <div>
                    <h2 id="balanceHeading">Leave balances <?= (int) $year ?></h2>
                    <div class="meta">Entitlement, approved and pending days</div>
                </div>
                <a class="btn btn-sm btn-primary" href="<?= e(url('leave/request.php')) ?>"><?= icon('plus-lg') ?> New request</a>
            </div>
            <div class="panel-body">
                <?php if ($balances === []): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('calendar2-week') ?></div>
                        <h3>No leave types available</h3>
                        <p>Human Resources has not configured leave entitlements yet.</p>
                    </div>
                <?php else: ?>
                    <div class="bar-list">
                        <?php foreach ($balances as $balance): ?>
                            <?php
                            $entitled = (float) $balance['entitlement'];
                            $used = (float) $balance['approved'] + (float) $balance['pending'];
                            $remaining = (float) $balance['remaining'];
                            $pct = $entitled > 0 ? min(100, ($used / $entitled) * 100) : 0;
                            ?>
                            <div class="bar-row">
                                <div class="bar-top">
                                    <strong><?= e((string) $balance['type']['name']) ?></strong>
                                    <span><?= e(format_days($remaining)) ?> remaining of <?= e(format_days($entitled)) ?></span>
                                </div>
                                <div class="progress-thin" role="img"
                                     aria-label="<?= e((string) $balance['type']['name']) ?>: <?= e(format_days($used)) ?> of <?= e(format_days($entitled)) ?> days used">
                                    <div class="bar is-gold" style="width: <?= e((string) round($pct, 1)) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel" aria-labelledby="employmentHeading">
            <div class="panel-header">
                <div>
                    <h2 id="employmentHeading">My employment</h2>
                    <div class="meta">Record maintained by Human Resources</div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('profile.php')) ?>">Open profile</a>
            </div>
            <div class="panel-body">
                <dl class="detail-grid">
                    <div class="detail-item">
                        <dt class="dt">Department</dt>
                        <dd class="dd"><?= e((string) ($department->name ?? 'Not assigned')) ?></dd>
                    </div>
                    <div class="detail-item">
                        <dt class="dt">Position</dt>
                        <dd class="dd"><?= e((string) ($position->title ?? 'Not assigned')) ?></dd>
                    </div>
                    <div class="detail-item">
                        <dt class="dt">Employment type</dt>
                        <dd class="dd"><?= e((string) ($user['employment_type'] ?? '—')) ?></dd>
                    </div>
                    <div class="detail-item">
                        <dt class="dt">Status</dt>
                        <dd class="dd"><span class="badge badge-soft-<?= e(status_badge_class((string) $user['employment_status'])) ?>"><?= e((string) $user['employment_status']) ?></span></dd>
                    </div>
                    <div class="detail-item">
                        <dt class="dt">Hire date</dt>
                        <dd class="dd"><?= e(format_date($user['hire_date'] ?? null)) ?></dd>
                    </div>
                    <div class="detail-item">
                        <dt class="dt">Work location</dt>
                        <dd class="dd"><?= e((string) ($user['work_location'] ?? '—')) ?></dd>
                    </div>
                </dl>
                <?php if ((int) ($user['must_change_password'] ?? 0) === 1): ?>
                    <div class="notice mt-3">
                        <?= icon('shield-exclamation') ?>
                        <div>
                            <strong>Temporary password.</strong>
                            Change it now to protect your account.
                            <a href="<?= e(url('account/password.php')) ?>">Change password</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="content-grid">
        <section class="panel" aria-labelledby="myLeaveHeading">
            <div class="panel-header">
                <div>
                    <h2 id="myLeaveHeading">My leave requests</h2>
                    <div class="meta">Most recent submissions</div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/index.php')) ?>">Leave history</a>
            </div>
            <?php if ($myLeave === []): ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('calendar-plus') ?></div>
                    <h3>No leave requests yet</h3>
                    <p>Submit a request and track its approval here.</p>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('leave/request.php')) ?>">Request leave</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table-app">
                        <thead>
                            <tr><th scope="col">Type</th><th scope="col">Dates</th><th scope="col" class="text-end">Days</th><th scope="col">Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($myLeave as $row): ?>
                            <tr>
                                <td><?= e((string) $row['leave_type_name']) ?></td>
                                <td class="text-nowrap"><?= e(format_date((string) $row['start_date'])) ?> – <?= e(format_date((string) $row['end_date'])) ?></td>
                                <td class="text-end"><?= e(format_days((float) $row['working_days'])) ?></td>
                                <td><span class="badge badge-soft-<?= e(status_badge_class((string) $row['status'])) ?>"><?= e(ucfirst((string) $row['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="notifHeading">
            <div class="panel-header">
                <div>
                    <h2 id="notifHeading">Notifications</h2>
                    <div class="meta">Latest updates for you</div>
                </div>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('notifications.php')) ?>">View all</a>
            </div>
            <div class="panel-body">
                <?php if ($notifications === []): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><?= icon('bell') ?></div>
                        <h3>Nothing new</h3>
                        <p>You will see account and leave updates here.</p>
                    </div>
                <?php else: ?>
                    <div class="nav-notifications">
                        <?php foreach ($notifications as $note): ?>
                            <div class="notification-item<?= (int) $note['is_read'] === 0 ? ' is-unread' : '' ?>">
                                <div class="fw-semibold"><?= e((string) $note['title']) ?></div>
                                <div class="text-muted small"><?= e((string) $note['message']) ?></div>
                                <div class="tiny text-muted-2 mt-1"><?= e(time_ago((string) $note['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
