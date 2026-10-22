<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$departments = departments_all();
$positions = positions_all(false);
$errors = [];

$values = [
    'first_name' => '', 'middle_name' => '', 'last_name' => '', 'sex' => '',
    'employee_number' => '', 'department_id' => '', 'position_id' => '',
    'employment_type' => 'Permanent', 'employment_status' => 'Active',
    'hire_date' => '', 'date_of_birth' => '', 'national_id' => '',
    'phone_number' => '', 'email' => '', 'address' => '',
    'room_number' => '', 'shelf_number' => '',
    'emergency_contact_name' => '', 'emergency_contact_phone' => '',
    'highest_qualification' => '', 'work_location' => '',
    'username' => '', 'password' => '',
];

if (is_post()) {
    foreach (array_keys($values) as $field) {
        $values[$field] = post_str($field);
    }
    $values['password'] = post_raw('password');
    $values['department_id'] = (string) post_int('department_id');
    $values['position_id'] = (string) post_int('position_id');

    $result = employee_save(null, $values, $user);
    if ($result['ok']) {
        flash('success', 'Employee created. Share the temporary password through a secure channel; a change is required at first sign-in.');
        redirect('employees/view.php?id=' . (int) $result['id']);
    }
    $errors = $result['errors'] ?? ['_form' => 'The employee could not be created.'];
}

$pageTitle = 'Add employee';
$topbarTitle = 'HR Workspace';
$activeNav = 'employees';
$isEdit = false;
$formAction = url('employees/create.php');
include BASE_PATH . '/includes/layout/header.php';
?>
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb breadcrumb-ess">
        <li class="breadcrumb-item"><a href="<?= e(url('employees/index.php')) ?>">Employees</a></li>
        <li class="breadcrumb-item active" aria-current="page">Add employee</li>
    </ol>
</nav>
<div class="page-header">
    <div>
        <h1 class="page-title">Add employee</h1>
        <p class="page-subtitle">Create the personnel record and self-service account together.</p>
    </div>
</div>

<?php include BASE_PATH . '/includes/partials/errors.php'; ?>

<section class="panel">
    <div class="panel-body">
        <?php include BASE_PATH . '/includes/partials/employee_form.php'; ?>
    </div>
</section>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
