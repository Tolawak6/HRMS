<?php
declare(strict_types=1);

/*
 * Development-only demonstration data.
 *
 * Usage: php bin/seed_demo.php --confirm
 *
 * The script refuses to run in production and never deletes existing data.
 * All people, numbers and contact details below are fictional.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

if ((string) config('env') === 'production') {
    fwrite(STDERR, "Refusing to seed demonstration data in production.\n");
    exit(1);
}
if (!in_array('--confirm', $argv, true)) {
    fwrite(STDERR, "This writes demonstration records to the configured database.\nRun again with --confirm to continue.\n");
    exit(1);
}

$existing = (int) db()->value(
    "SELECT COUNT(*) FROM users WHERE role = 'ess_staff' AND employee_number LIKE 'ESS-%'",
    [],
    0
);
if ($existing > 0) {
    fwrite(STDOUT, "Demonstration employees already exist ($existing records). Nothing to do.\n");
    exit(0);
}

$staffPassword = 'Ess!Demo' . random_int(1000, 9999) . '#Kx';

$departments = [
    ['HRM', 'Human Resource Management', 'Recruitment, personnel records and employee services.'],
    ['FIN', 'Finance and Procurement', 'Budget execution, accounting and procurement.'],
    ['SPD', 'Statistical Production', 'Survey design, data collection and statistical production.'],
    ['ITD', 'Information Technology', 'Systems, infrastructure and data security.'],
    ['CDD', 'Communication and Dissemination', 'Publications, outreach and user engagement.'],
    ['APD', 'Administration and Property', 'Facilities, transport and general services.'],
];

$positions = [
    ['HRM', 'HR Officer', 'P5'],
    ['HRM', 'HR Director', 'P7'],
    ['HRM', 'Records Officer', 'P4'],
    ['FIN', 'Accountant', 'P5'],
    ['FIN', 'Procurement Officer', 'P5'],
    ['SPD', 'Statistician', 'P5'],
    ['SPD', 'Senior Statistician', 'P7'],
    ['SPD', 'Survey Supervisor', 'P4'],
    ['ITD', 'Systems Administrator', 'P6'],
    ['ITD', 'Data Analyst', 'P5'],
    ['CDD', 'Communications Officer', 'P5'],
    ['APD', 'Office Administrator', 'P4'],
];

$leaveTypes = [
    ['ANN', 'Annual leave', 20, 'Paid annual leave entitlement.'],
    ['SICK', 'Sick leave', 30, 'Medical leave with supporting documentation.'],
    ['MAT', 'Maternity leave', 90, 'Maternity leave for eligible employees.'],
    ['PAT', 'Paternity leave', 10, 'Paternity leave for eligible employees.'],
    ['SPEC', 'Special leave', 5, 'Approved special circumstances.'],
];

// Fictional employees: [first, middle, last, sex, department code, position title, type, status, hire date, birth date]
$employees = [
    ['Selam', 'Alem', 'Bekele', 'Female', 'HRM', 'HR Officer', 'Permanent', 'Active', '2018-03-05', '1991-07-12'],
    ['Dawit', 'Tesfaye', 'Girma', 'Male', 'FIN', 'Accountant', 'Permanent', 'Active', '2016-09-19', '1987-02-03'],
    ['Hana', 'Mekonnen', 'Wolde', 'Female', 'SPD', 'Statistician', 'Permanent', 'Active', '2020-01-13', '1994-11-27'],
    ['Yosef', 'Kebede', 'Alemayehu', 'Male', 'ITD', 'Systems Administrator', 'Permanent', 'Active', '2019-06-24', '1990-05-09'],
    ['Marta', 'Girma', 'Tesfaye', 'Female', 'CDD', 'Communications Officer', 'Contract', 'Active', '2022-10-03', '1996-08-30'],
    ['Bereket', 'Assefa', 'Molla', 'Male', 'APD', 'Office Administrator', 'Permanent', 'Active', '2017-04-17', '1985-12-01'],
    ['Rahel', 'Desta', 'Haile', 'Female', 'SPD', 'Senior Statistician', 'Permanent', 'Active', '2014-02-10', '1982-03-21'],
    ['Kalkidan', 'Bekele', 'Seyoum', 'Female', 'FIN', 'Procurement Officer', 'Permanent', 'Active', '2021-08-16', '1993-09-14'],
    ['Nahom', 'Yohannes', 'Tadesse', 'Male', 'SPD', 'Survey Supervisor', 'Temporary', 'Active', '2024-02-05', '1998-06-18'],
    ['Eyerusalem', 'Mulugeta', 'Ayele', 'Female', 'HRM', 'Records Officer', 'Permanent', 'Active', '2023-05-22', '1997-01-25'],
    ['Samuel', 'Worku', 'Negash', 'Male', 'ITD', 'Data Analyst', 'Contract', 'Active', '2023-11-06', '1995-10-07'],
    ['Lidya', 'Haile', 'Gebre', 'Female', 'CDD', 'Communications Officer', 'Temporary', 'On Leave', '2022-03-14', '1994-04-02'],
    ['Amanuel', 'Fikru', 'Zewdie', 'Male', 'FIN', 'Accountant', 'Permanent', 'Active', '2015-07-01', '1986-11-11'],
    ['Tsion', 'Abebe', 'Kassa', 'Female', 'SPD', 'Statistician', 'Permanent', 'Active', '2019-09-09', '1992-12-19'],
    ['Henok', 'Mengistu', 'Desta', 'Male', 'APD', 'Office Administrator', 'Permanent', 'Suspended', '2020-06-15', '1989-08-05'],
    ['Bethlehem', 'Solomon', 'Girmay', 'Female', 'HRM', 'HR Director', 'Permanent', 'Active', '2012-01-09', '1979-05-16'],
    ['Yohannes', 'Gebre', 'Tsegaye', 'Male', 'SPD', 'Survey Supervisor', 'Contract', 'Active', '2025-01-20', '1999-03-08'],
    ['Sara', 'Mulatu', 'Endale', 'Female', 'FIN', 'Procurement Officer', 'Permanent', 'Active', '2021-12-01', '1993-07-23'],
    ['Abel', 'Tadesse', 'Bogale', 'Male', 'ITD', 'Data Analyst', 'Intern', 'Active', '2025-09-01', '2001-02-14'],
    ['Meron', 'Ayele', 'Hunde', 'Female', 'CDD', 'Communications Officer', 'Permanent', 'Resigned', '2018-08-13', '1990-01-30'],
];

try {
    db()->transaction(function (Database $db) use ($departments, $positions, $leaveTypes, $employees, $staffPassword): void {
        $departmentIds = [];
        foreach ($departments as [$code, $name, $description]) {
            $departmentIds[$code] = $db->insert(
                'INSERT INTO departments (code, name, description, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 1, NOW(), NOW())',
                [$code, $name, $description]
            );
        }

        $positionIds = [];
        foreach ($positions as [$departmentCode, $title, $grade]) {
            $positionIds[$title] = $db->insert(
                'INSERT INTO positions (department_id, code, title, grade, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
                [
                    $departmentIds[$departmentCode],
                    sprintf('%s-%02d', substr(preg_replace('/[^A-Z]/', '', strtoupper($title)), 0, 4), count($positionIds) + 1),
                    $title,
                    $grade,
                ]
            );
        }

        $leaveTypeIds = [];
        foreach ($leaveTypes as [$code, $name, $days, $notes]) {
            $leaveTypeIds[$code] = $db->insert(
                'INSERT INTO leave_types (code, name, annual_entitlement, is_paid, description, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, 1, NOW(), NOW())',
                [$code, $name, $days, $notes]
            );
        }

        $hash = password_hash($staffPassword, PASSWORD_DEFAULT);
        $employeeIds = [];
        $index = 0;
        $year = current_year();
        foreach ($employees as [$first, $middle, $last, $sex, $departmentCode, $positionTitle, $type, $status, $hireDate, $birthDate]) {
            $index++;
            $number = 'ESS-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT);
            $username = strtolower($first . '.' . $last) . $index;
            $employeeId = $db->insert(
                "INSERT INTO users
                    (username, password, role, first_name, middle_name, last_name, sex, employee_number,
                     department_id, position_id, employment_type, employment_status, hire_date, date_of_birth,
                     phone_number, email, address, room_number, shelf_number, highest_qualification,
                     work_location, must_change_password, is_active, created_at, updated_at)
                 VALUES (?, ?, 'ess_staff', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())",
                [
                    $username,
                    $hash,
                    $first,
                    $middle,
                    $last,
                    $sex,
                    $number,
                    $departmentIds[$departmentCode],
                    $positionIds[$positionTitle],
                    $type,
                    $status,
                    $hireDate,
                    $birthDate,
                    '+2519' . random_int(10000000, 99999999),
                    $username . '@ess.example',
                    'Addis Ababa, Ethiopia',
                    'B-' . random_int(100, 420),
                    'S-' . random_int(10, 90),
                    $index % 3 === 0 ? 'BA in related field' : 'MA in related field',
                    'Head Office, Addis Ababa',
                ]
            );
            $employeeIds[] = $employeeId;
        }

        // Annual and sick leave entitlements for the current year.
        foreach ($employeeIds as $employeeId) {
            $db->insert(
                'INSERT INTO leave_balances (user_id, leave_type_id, year, entitled_days, note, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                [$employeeId, $leaveTypeIds['ANN'], $year, 20, 'Standard annual entitlement']
            );
            $db->insert(
                'INSERT INTO leave_balances (user_id, leave_type_id, year, entitled_days, note, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                [$employeeId, $leaveTypeIds['SICK'], $year, 30, 'Standard sick leave entitlement']
            );
        }

        $requests = [
            [0, 'ANN', 12, 16, 'pending', 'Family event'],
            [1, 'ANN', 3, 7, 'approved', 'Annual rest'],
            [2, 'SICK', 20, 21, 'approved', 'Medical appointment'],
            [3, 'ANN', 26, 30, 'pending', 'Annual leave'],
            [4, 'PAT', 10, 12, 'approved', 'Child birth'],
            [5, 'ANN', 5, 9, 'rejected', 'Peak reporting period'],
            [6, 'ANN', 18, 22, 'pending', 'Annual leave'],
            [7, 'SPEC', 14, 14, 'pending', 'Official examination'],
            [11, 'MAT', 1, 60, 'approved', 'Maternity leave'],
            [13, 'SICK', 8, 9, 'approved', 'Influenza'],
        ];
        foreach ($requests as [$employeeIndex, $typeCode, $startOffset, $endOffset, $status, $reason]) {
            $employeeId = $employeeIds[$employeeIndex];
            $start = date('Y-m-d', strtotime('2026-01-05 +' . (int) $startOffset . ' days'));
            $end = date('Y-m-d', strtotime('2026-01-05 +' . (int) $endOffset . ' days'));
            $days = working_days_between($start, $end);
            $reviewedBy = $status === 'pending' ? null : 1;
            $reviewedAt = $status === 'pending' ? null : date('Y-m-d H:i:s', strtotime($start . ' -2 days'));
            $db->insert(
                'INSERT INTO leave_requests
                    (user_id, leave_type_id, start_date, end_date, working_days, reason, status,
                     reviewed_by, reviewed_at, review_note, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $employeeId,
                    $leaveTypeIds[$typeCode],
                    $start,
                    $end,
                    $days,
                    $reason,
                    $status,
                    $reviewedBy,
                    $reviewedAt,
                    $status === 'rejected' ? 'Declined due to operational requirements.' : null,
                ]
            );
        }

        // A small generated PDF in protected storage to exercise the document workflow.
        $relativeDir = 'uploads/documents/' . date('Y') . '/' . date('m');
        $directory = BASE_PATH . '/' . $relativeDir;
        if (!is_dir($directory)) {
            @mkdir($directory, 0750, true);
        }
        if (is_dir($directory)) {
            $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
                . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
                . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 120]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
                . "4 0 obj<</Length 68>>stream\nBT /F1 12 Tf 24 70 Td (ESS HRMS demonstration document) Tj ET\nendstream endobj\n"
                . "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
                . "trailer<</Root 1 0 R>>\n%%EOF";
            $stored = bin2hex(random_bytes(16)) . '.pdf';
            if (@file_put_contents($directory . '/' . $stored, $pdf) !== false) {
                $db->insert(
                    'INSERT INTO file_attachments
                        (ess_staff_id, file_name, file_path, uploaded_at, uploaded_by, stored_name, mime_type, file_size, description)
                     VALUES (?, ?, ?, NOW(), 1, ?, ?, ?, ?)',
                    [
                        $employeeIds[0],
                        'employment-verification.pdf',
                        $relativeDir . '/' . $stored,
                        $stored,
                        'application/pdf',
                        strlen($pdf),
                        'Demonstration employment verification letter',
                    ]
                );
            }
        }

        $db->insert(
            'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
             VALUES (1, "system", ?, ?, ?, 0, NOW())',
            ['Demonstration data loaded', 'Sample employee and leave records were created for testing.', 'employees/index.php']
        );
    });
} catch (Throwable $exception) {
    fwrite(STDERR, "Seeding failed. No partial data was committed.\n");
    app_log('error', 'Demo seeding failed', ['reason' => $exception->getMessage()]);
    exit(1);
}

fwrite(STDOUT, "Demonstration data created.\n");
fwrite(STDOUT, "  Departments: 6 · Positions: " . count($positions) . " · Employees: " . count($employees) . "\n");
fwrite(STDOUT, "  Shared temporary employee password: $staffPassword\n");
fwrite(STDOUT, "  Every seeded employee must change this password at first sign-in.\n");
