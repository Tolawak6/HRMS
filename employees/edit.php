<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$id = get_int('id');
$employee = employee_find($id);
if ($employee === null || (string) $employee['role'] !== 'ess_staff') {
    abort(404, 'Employee not found', 'The requested employee record does not exist.');
}
if (!empty($employee['archived_at'])) {
    flash('warning', 'Restore this employee before editing the record.');
    redirect('employees/view.php?id=' . $id);
}

$departments = departments_all();
$positions = positions_all(false);
$errors = [];
$values = $employee;
$values['account_active'] = (int) ($employee['is_active'] ?? 1);
$values['password'] = '';

if (is_post()) {
    foreach ([
        'first_name', 'middle_name', 'last_name', 'sex', 'employee_number', 'employment_type',
        'employment_status', 'hire_date', 'date_of_birth', 'national_id', 'phone_number', 'email',
        'address', 'room_number', 'shelf_number', 'emergency_contact_name', 'emergency_contact_phone',
        'highest_qualification', 'work_location', 'username',
    ] as $field) {
        $values[$field] = post_str($field);
    }
    $values['password'] = post_raw('password');
    $values['department_id'] = (string) post_int('department_id');
    $values['position_id'] = (string) post_int('position_id');
    $values['account_active'] = post_str('account_active') === '1' ? 1 : 0;
    $values['id'] = $id;

    $result = employee_save($id, $values, $user);
    if ($result['ok']) {
        flash('success', 'Employee record updated.');
        redirect('employees/view.php?id=' . $id);
    }
    $errors = $result['errors'] ?? ['_form' => 'The employee could not be saved.'];
}

$pageTitle = 'Edit employee';
$topbarTitle = 'HR Workspace';
$activeNav = 'employees';
$isEdit = true;
$formAction = url('employees/edit.php?id=' . $id);
include BASE_PATH . '/includes/layout/header.php';
?>
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb breadcrumb-ess">
        <li class="breadcrumb-item"><a href="<?= e(url('employees/index.php')) ?>">Employees</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('employees/view.php?id=' . $id)) ?>"><?= e(person_name($employee)) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
    </ol>
</nav>
<div class="page-header">
    <div>
        <h1 class="page-title">Edit <?= e(person_name($employee)) ?></h1>
        <p class="page-subtitle">Changes are recorded in the audit log.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('employees/view.php?id=' . $id)) ?>">Cancel</a>
    </div>
</div>

<?php include BASE_PATH . '/includes/partials/errors.php'; ?>

<section class="panel">
    <div class="panel-body">
        <?php include BASE_PATH . '/includes/partials/employee_form.php'; ?>
    </div>
</section>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
