<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin', 'hr_staff']);

if (!is_post()) {
    redirect('employees/index.php');
}

$id = post_int('id');
$employee = employee_find($id);
if ($employee === null || !empty($employee['archived_at'])) {
    abort(404, 'Employee not found', 'The employee record is missing or archived.');
}

$outcome = save_employee_photo($id, $_FILES['photo'] ?? [], $actor);
flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Profile photo updated.' : (string) ($outcome['error'] ?? 'The photo could not be uploaded.'));
redirect('employees/view.php?id=' . $id);
