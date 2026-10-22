<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_roles(['admin', 'hr_staff']);
$type = get_str('type', 'leave');
$year = max(2000, min(2100, get_int('year', current_year())));
$status = get_str('status');
if (!in_array($status, annual_leave_statuses(), true)) {
    $status = '';
}

if ($type !== 'leave') {
    abort(404, 'Unknown report', 'The requested export type is not available.');
}

$rows = report_leave_export_rows($year, $status);
audit_log('report.export', 'report', null, 'Leave report exported as CSV (' . count($rows) . ' rows, ' . $year . ')');

$filename = 'ess-leave-' . $year . ($status !== '' ? '-' . $status : '') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit;
}
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Request ID', 'Employee number', 'First name', 'Middle name', 'Last name', 'Leave type', 'Start date', 'End date', 'Working days', 'Status', 'Submitted', 'Reviewed', 'Reviewer'], ',', '"', '\\');
foreach ($rows as $row) {
    fputcsv($output, [
        (int) $row['id'],
        csv_safe((string) ($row['employee_number'] ?? '')),
        csv_safe((string) ($row['first_name'] ?? '')),
        csv_safe((string) ($row['middle_name'] ?? '')),
        csv_safe((string) ($row['last_name'] ?? '')),
        csv_safe((string) $row['leave_type_name']),
        csv_safe((string) $row['start_date']),
        csv_safe((string) $row['end_date']),
        csv_safe((string) $row['working_days']),
        csv_safe((string) $row['status']),
        csv_safe((string) $row['created_at']),
        csv_safe((string) ($row['reviewed_at'] ?? '')),
        csv_safe(trim((string) ($row['reviewer_name'] ?? ''))),
    ], ',', '"', '\\');
}
fclose($output);
