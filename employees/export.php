<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin', 'hr_staff']);
$filters = employee_filters();
$filters['per_page'] = 10000;
$filters['page'] = 1;
$rows = employee_export_rows($filters);

audit_log('report.export', 'report', null, 'Employee directory exported as CSV (' . count($rows) . ' rows)');

$filename = 'ess-employees-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit;
}
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Employee number', 'First name', 'Middle name', 'Last name', 'Sex', 'Employment type', 'Status', 'Hire date', 'Email', 'Phone', 'Department', 'Position'], ',', '"', '\\');
foreach ($rows as $row) {
    fputcsv($output, [
        csv_safe((string) ($row['employee_number'] ?? '')),
        csv_safe((string) ($row['first_name'] ?? '')),
        csv_safe((string) ($row['middle_name'] ?? '')),
        csv_safe((string) ($row['last_name'] ?? '')),
        csv_safe((string) ($row['sex'] ?? '')),
        csv_safe((string) ($row['employment_type'] ?? '')),
        csv_safe((string) ($row['employment_status'] ?? '')),
        csv_safe((string) ($row['hire_date'] ?? '')),
        csv_safe((string) ($row['email'] ?? '')),
        csv_safe((string) ($row['phone_number'] ?? '')),
        csv_safe((string) ($row['department_name'] ?? '')),
        csv_safe((string) ($row['position_title'] ?? '')),
    ], ',', '"', '\\');
}
fclose($output);
