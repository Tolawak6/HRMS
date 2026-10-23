<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_auth();
$id = get_int('id');
$employee = employee_find($id);
if ($employee === null) {
    abort(404, 'Employee not found', 'The requested employee record does not exist.');
}
require_employee_access($employee);
$canManage = can_manage_employees();

if (is_post() && $canManage) {
    $action = post_str('action');
    if ($action === 'archive') {
        $outcome = employee_archive($id, post_str('reason'), $actor);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Employee archived. Pending leave requests were cancelled.' : (string) ($outcome['error'] ?? 'Unable to archive.'));
    } elseif ($action === 'restore') {
        $outcome = employee_restore($id, $actor);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Employee restored. A password change is required at next sign-in.' : (string) ($outcome['error'] ?? 'Unable to restore.'));
    }
    redirect('employees/view.php?id=' . $id);
}

$year = current_year();
$balances = leave_balance_summary($id, $year);
$leaveRequests = leave_requests_for_user($id, 10);
$documents = documents_for_employee($id, 10);
$isArchived = !empty($employee['archived_at']);
$photo = employee_photo_url($employee);

$pageTitle = person_name($employee);
$topbarTitle = 'Employee record';
$activeNav = 'employees';
include BASE_PATH . '/includes/layout/header.php';
?>
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb breadcrumb-ess">
        <li class="breadcrumb-item"><a href="<?= e(url('employees/index.php')) ?>">Employees</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e(person_name($employee)) ?></li>
    </ol>
</nav>

<?php if ($isArchived): ?>
    <div class="alert alert-warning" role="alert">
        <?= icon('archive') ?>
        <div class="flex-grow-1">
            <strong>Archived record.</strong>
            Archived on <?= e(format_datetime($employee['archived_at'])) ?>
            <?php if (!empty($employee['archive_reason'])): ?>· Reason: <?= e((string) $employee['archive_reason']) ?><?php endif; ?>
            · Sign-in is disabled and the record is read-only until restored.
        </div>
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('employees/view.php?id=' . $id)) ?>"
                  data-confirm="Restore this employee and re-enable self-service access?"
                  data-confirm-title="Restore employee" data-confirm-button="Restore" data-confirm-variant="success">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore">
                <button class="btn btn-sm btn-warning" type="submit">Restore record</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-body">
        <div class="profile-header">
            <div class="profile-identity">
                <span class="avatar avatar-xl">
                    <img src="<?= e($photo) ?>" alt="Profile photo of <?= e(person_name($employee)) ?>">
                </span>
                <div>
                    <h1 class="page-title mb-1"><?= e(person_name($employee)) ?></h1>
                    <div class="text-muted">
                        <?= e((string) ($employee['position_title'] ?? 'No position assigned')) ?>
                        <?php if (!empty($employee['department_name'])): ?>
                            · <?= e((string) $employee['department_name']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge badge-soft-<?= e(status_badge_class((string) $employee['employment_status'])) ?>"><?= e((string) $employee['employment_status']) ?></span>
                        <span class="chip"><?= icon('person-badge') ?><?= e((string) ($employee['employee_number'] ?? 'No number')) ?></span>
                        <span class="chip"><?= icon('briefcase') ?><?= e((string) ($employee['employment_type'] ?? '—')) ?></span>
                    </div>
                </div>
            </div>
            <?php if ($canManage): ?>
                <div class="page-actions">
                    <a class="btn btn-outline-secondary" href="<?= e(url('documents/index.php?employee=' . $id)) ?>"><?= icon('folder2-open') ?> Documents</a>
                    <?php if (!$isArchived): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(url('employees/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?> Edit</a>
                        <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#archiveModal">
                            <?= icon('archive') ?> Archive
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="employmentHeading">
        <div class="panel-header">
            <h2 id="employmentHeading">Employment details</h2>
        </div>
        <div class="panel-body">
            <dl class="detail-grid">
                <div class="detail-item"><dt class="dt">Department</dt><dd class="dd"><?= e((string) ($employee['department_name'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Position</dt><dd class="dd"><?= e((string) ($employee['position_title'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Employment type</dt><dd class="dd"><?= e((string) ($employee['employment_type'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Employment status</dt><dd class="dd"><?= e((string) $employee['employment_status']) ?></dd></div>
                <div class="detail-item"><dt class="dt">Hire date</dt><dd class="dd"><?= e(format_date($employee['hire_date'] ?? null)) ?></dd></div>
                <div class="detail-item"><dt class="dt">Work location</dt><dd class="dd"><?= e((string) ($employee['work_location'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Highest qualification</dt><dd class="dd"><?= e((string) ($employee['highest_qualification'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Room / shelf</dt><dd class="dd"><?= e(trim((string) ($employee['room_number'] ?? '') . ' / ' . (string) ($employee['shelf_number'] ?? ''), ' /')) ?: '—' ?></dd></div>
            </dl>
        </div>
    </section>

    <section class="panel" aria-labelledby="accountHeading">
        <div class="panel-header">
            <h2 id="accountHeading">Account</h2>
        </div>
        <div class="panel-body">
            <div class="mini-list">
                <div class="row-line"><span>Username</span><span>@<?= e((string) $employee['username']) ?></span></div>
                <div class="row-line"><span>Role</span><span><?= e(role_label((string) $employee['role'])) ?></span></div>
                <div class="row-line"><span>Sign-in</span><span><?= (int) $employee['is_active'] === 1 ? 'Enabled' : 'Disabled' ?></span></div>
                <div class="row-line"><span>Password change required</span><span><?= (int) $employee['must_change_password'] === 1 ? 'Yes' : 'No' ?></span></div>
                <div class="row-line"><span>Last sign-in</span><span><?= e(format_datetime($employee['last_login_at'] ?? null)) ?></span></div>
                <div class="row-line"><span>Record created</span><span><?= e(format_datetime($employee['created_at'] ?? null)) ?></span></div>
            </div>
            <?php if ($canManage && !$isArchived): ?>
                <div class="divider"></div>
                <form method="post" action="<?= e(url('employees/photo.php')) ?>" enctype="multipart/form-data" class="stack-sm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <label class="form-label" for="photo">Profile photo</label>
                    <input class="form-control" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required>
                    <div class="form-text">JPG, PNG or WebP · maximum 2 MB.</div>
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= icon('camera') ?> Upload photo</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="content-grid">
    <section class="panel" aria-labelledby="contactHeading">
        <div class="panel-header">
            <h2 id="contactHeading">Contact &amp; personal</h2>
        </div>
        <div class="panel-body">
            <dl class="detail-grid">
                <div class="detail-item"><dt class="dt">Email</dt><dd class="dd"><?= e((string) ($employee['email'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Phone</dt><dd class="dd"><?= e((string) ($employee['phone_number'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Address</dt><dd class="dd"><?= e((string) ($employee['address'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Sex</dt><dd class="dd"><?= e((string) ($employee['sex'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Date of birth</dt><dd class="dd"><?= e(format_date($employee['date_of_birth'] ?? null)) ?></dd></div>
                <div class="detail-item"><dt class="dt">National ID</dt><dd class="dd"><?= e((string) ($employee['national_id'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Emergency contact</dt><dd class="dd"><?= e((string) ($employee['emergency_contact_name'] ?? '—')) ?></dd></div>
                <div class="detail-item"><dt class="dt">Emergency phone</dt><dd class="dd"><?= e((string) ($employee['emergency_contact_phone'] ?? '—')) ?></dd></div>
            </dl>
        </div>
    </section>

    <section class="panel" aria-labelledby="balancesHeading">
        <div class="panel-header">
            <div>
                <h2 id="balancesHeading">Leave balances <?= (int) $year ?></h2>
                <div class="meta">Entitlement vs. approved and pending days</div>
            </div>
            <?php if ($canManage && !$isArchived): ?>
                <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/balances.php?user=' . $id)) ?>">Manage</a>
            <?php endif; ?>
        </div>
        <?php if ($balances === []): ?>
            <div class="panel-body"><p class="text-muted mb-0">No leave types are configured.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead><tr><th scope="col">Type</th><th scope="col" class="text-end">Entitled</th><th scope="col" class="text-end">Used</th><th scope="col" class="text-end">Remaining</th></tr></thead>
                    <tbody>
                    <?php foreach ($balances as $balance): ?>
                        <tr>
                            <td><?= e((string) $balance['type']['name']) ?></td>
                            <td class="text-end"><?= e(format_days((float) $balance['entitlement'])) ?></td>
                            <td class="text-end"><?= e(format_days((float) $balance['approved'] + (float) $balance['pending'])) ?></td>
                            <td class="text-end"><strong><?= e(format_days((float) $balance['remaining'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="panel" aria-labelledby="leaveHeading">
    <div class="panel-header">
        <div><h2 id="leaveHeading">Leave history</h2><div class="meta">Most recent requests</div></div>
        <?php if ($canManage && !$isArchived): ?>
            <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/requests.php?q=' . rawurlencode((string) ($employee['employee_number'] ?? '')))) ?>">All leave requests</a>
        <?php endif; ?>
    </div>
    <?php if ($leaveRequests === []): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= icon('calendar2-week') ?></div>
            <h3>No leave requests</h3>
            <p>Leave requests submitted by this employee will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead><tr><th scope="col">Type</th><th scope="col">Dates</th><th scope="col" class="text-end">Days</th><th scope="col">Status</th><th scope="col">Reviewed</th></tr></thead>
                <tbody>
                <?php foreach ($leaveRequests as $request): ?>
                    <tr>
                        <td><?= e((string) $request['leave_type_name']) ?></td>
                        <td class="text-nowrap"><?= e(format_date((string) $request['start_date'])) ?> – <?= e(format_date((string) $request['end_date'])) ?></td>
                        <td class="text-end"><?= e(format_days((float) $request['working_days'])) ?></td>
                        <td><span class="badge badge-soft-<?= e(status_badge_class((string) $request['status'])) ?>"><?= e(ucfirst((string) $request['status'])) ?></span></td>
                        <td class="text-nowrap text-muted"><?= e(format_datetime($request['reviewed_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel" aria-labelledby="documentsHeading">
    <div class="panel-header">
        <div><h2 id="documentsHeading">Documents</h2><div class="meta">Personnel file attachments</div></div>
        <?php if ($canManage): ?>
            <a class="btn btn-sm btn-quiet" href="<?= e(url('documents/index.php?employee=' . $id)) ?>">Manage documents</a>
        <?php endif; ?>
    </div>
    <?php if ($documents === []): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= icon('folder2-open') ?></div>
            <h3>No documents</h3>
            <p>Uploaded certificates, contracts and letters will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead><tr><th scope="col">File</th><th scope="col">Description</th><th scope="col" class="text-end">Size</th><th scope="col">Uploaded</th><th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td><span class="d-inline-flex align-items-center gap-2"><?= icon(document_icon((string) $document['file_name'])) ?><?= e((string) $document['file_name']) ?></span></td>
                        <td class="text-muted"><?= e((string) ($document['description'] ?? '—')) ?></td>
                        <td class="text-end text-nowrap"><?= e(human_file_size((int) ($document['file_size'] ?? 0))) ?></td>
                        <td class="text-nowrap text-muted"><?= e(format_date((string) $document['uploaded_at'])) ?></td>
                        <td class="col-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('documents/download.php?id=' . (int) $document['id'])) ?>">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($canManage && !$isArchived): ?>
<div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(url('employees/view.php?id=' . $id)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="archive">
            <div class="modal-header">
                <h2 class="modal-title" id="archiveTitle">Archive employee</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">
                    Archiving disables self-service access for <strong><?= e(person_name($employee)) ?></strong> and cancels
                    pending leave requests. The record and its history are preserved and can be restored.
                </p>
                <label class="form-label" for="archiveReason">Reason</label>
                <textarea class="form-control" id="archiveReason" name="reason" rows="3" maxlength="255"
                          placeholder="e.g. End of contract, resignation accepted"></textarea>
                <div class="form-text">Recorded in the audit log. Optional but recommended.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><?= icon('archive') ?> Archive employee</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
