<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function sex_options(): array
{
    return ['Male', 'Female'];
}

function employment_types(): array
{
    return ['Permanent', 'Contract', 'Temporary', 'Intern'];
}

function employment_statuses(): array
{
    return ['Active', 'On Leave', 'Suspended', 'Retired', 'Resigned'];
}

function annual_leave_statuses(): array
{
    return ['pending', 'approved', 'rejected', 'cancelled'];
}

function validate_iso_date(?string $date): bool
{
    if ($date === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function validate_email_address(string $email): bool
{
    return $email !== ''
        && mb_strlen($email) <= 150
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_phone_number(string $phone): bool
{
    if ($phone === '' || mb_strlen($phone) > 30) {
        return false;
    }
    if (!preg_match('/^\+?[0-9 ()\-]+$/', $phone)) {
        return false;
    }
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    return strlen($digits) >= 7 && strlen($digits) <= 15;
}

function validate_username(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9._-]{4,60}$/', $username);
}

function password_policy_errors(string $password, string $username = ''): array
{
    $errors = [];
    $min = (int) config('security.password_min_length', 10);
    if (mb_strlen($password) < $min) {
        $errors[] = 'Password must be at least ' . $min . ' characters long.';
    }
    if (strlen($password) > 72) {
        $errors[] = 'Password must not be longer than 72 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must include at least one letter and one number.';
    }
    $weak = ['password', 'passw0rd', '12345678', '123456789', 'qwerty', 'admin123', 'changeme', 'welcome1'];
    if (in_array(strtolower($password), $weak, true)) {
        $errors[] = 'Choose a less common password.';
    }
    if ($username !== '' && mb_strlen($username) >= 4 && stripos($password, $username) !== false) {
        $errors[] = 'Password must not contain the username.';
    }
    return $errors;
}

/**
 * Validate employee form input. Database-dependent checks (uniqueness) are
 * performed by the employee repository inside a transaction.
 *
 * @return array<string,string>
 */
function employee_input_errors(array $input, bool $requirePassword = true): array
{
    $errors = [];

    $firstName = trim((string) ($input['first_name'] ?? ''));
    $middleName = trim((string) ($input['middle_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    $sex = (string) ($input['sex'] ?? '');
    $employeeNumber = trim((string) ($input['employee_number'] ?? ''));
    $employmentType = (string) ($input['employment_type'] ?? '');
    $employmentStatus = (string) ($input['employment_status'] ?? '');
    $hireDate = (string) ($input['hire_date'] ?? '');
    $dob = (string) ($input['date_of_birth'] ?? '');
    $email = trim((string) ($input['email'] ?? ''));
    $phone = trim((string) ($input['phone_number'] ?? ''));
    $emergencyPhone = trim((string) ($input['emergency_contact_phone'] ?? ''));
    $nationalId = trim((string) ($input['national_id'] ?? ''));
    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($firstName === '' || mb_strlen($firstName) > 80) {
        $errors['first_name'] = 'First name is required (maximum 80 characters).';
    }
    if (mb_strlen($middleName) > 80) {
        $errors['middle_name'] = 'Middle name must be 80 characters or fewer.';
    }
    if ($lastName === '' || mb_strlen($lastName) > 80) {
        $errors['last_name'] = 'Last name is required (maximum 80 characters).';
    }
    if (!in_array($sex, sex_options(), true)) {
        $errors['sex'] = 'Select a valid sex.';
    }
    if (!preg_match('/^[A-Za-z0-9._\/-]{2,30}$/', $employeeNumber)) {
        $errors['employee_number'] = 'Employee number must be 2–30 characters (letters, numbers, dot, dash, slash).';
    }
    if (!in_array($employmentType, employment_types(), true)) {
        $errors['employment_type'] = 'Select a valid employment type.';
    }
    if (!in_array($employmentStatus, employment_statuses(), true)) {
        $errors['employment_status'] = 'Select a valid employment status.';
    }
    if (!validate_iso_date($hireDate)) {
        $errors['hire_date'] = 'Enter a valid hire date.';
    } elseif ($hireDate > today()) {
        $errors['hire_date'] = 'Hire date cannot be in the future.';
    }
    if ($dob !== '') {
        if (!validate_iso_date($dob)) {
            $errors['date_of_birth'] = 'Enter a valid date of birth.';
        } else {
            $birth = new DateTimeImmutable($dob);
            $age = $birth->diff(new DateTimeImmutable(today()))->y;
            if ($birth > new DateTimeImmutable(today())) {
                $errors['date_of_birth'] = 'Date of birth cannot be in the future.';
            } elseif ($age < 18) {
                $errors['date_of_birth'] = 'Employee must be at least 18 years old.';
            } elseif ($age > 100) {
                $errors['date_of_birth'] = 'Enter a realistic date of birth.';
            }
            if (validate_iso_date($hireDate) && $dob >= $hireDate) {
                $errors['date_of_birth'] = 'Date of birth must be before the hire date.';
            }
        }
    }
    if ($email !== '' && !validate_email_address($email)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($phone !== '' && !validate_phone_number($phone)) {
        $errors['phone_number'] = 'Enter a valid phone number (7–15 digits).';
    }
    if ($emergencyPhone !== '' && !validate_phone_number($emergencyPhone)) {
        $errors['emergency_contact_phone'] = 'Enter a valid emergency contact number.';
    }
    if ($nationalId !== '' && !preg_match('/^[A-Za-z0-9-]{3,40}$/', $nationalId)) {
        $errors['national_id'] = 'National ID may contain letters, numbers and dashes (3–40 characters).';
    }
    if (mb_strlen((string) ($input['address'] ?? '')) > 255) {
        $errors['address'] = 'Address must be 255 characters or fewer.';
    }
    if (mb_strlen((string) ($input['work_location'] ?? '')) > 120) {
        $errors['work_location'] = 'Work location must be 120 characters or fewer.';
    }
    if (mb_strlen((string) ($input['highest_qualification'] ?? '')) > 120) {
        $errors['highest_qualification'] = 'Qualification must be 120 characters or fewer.';
    }
    if (mb_strlen((string) ($input['emergency_contact_name'] ?? '')) > 120) {
        $errors['emergency_contact_name'] = 'Emergency contact name must be 120 characters or fewer.';
    }
    if (mb_strlen((string) ($input['room_number'] ?? '')) > 30) {
        $errors['room_number'] = 'Room number must be 30 characters or fewer.';
    }
    if (mb_strlen((string) ($input['shelf_number'] ?? '')) > 30) {
        $errors['shelf_number'] = 'Shelf number must be 30 characters or fewer.';
    }

    if (!validate_username($username)) {
        $errors['username'] = 'Username must be 4–60 characters using letters, numbers, dot, dash or underscore.';
    }
    if ($requirePassword && $password === '') {
        $errors['password'] = 'A temporary password is required.';
    } elseif ($password !== '') {
        $passwordErrors = password_policy_errors($password, $username);
        if ($passwordErrors !== []) {
            $errors['password'] = implode(' ', $passwordErrors);
        }
    }

    return $errors;
}

/**
 * Validation that does not require the database.
 *
 * @return array<string,string>
 */
function leave_input_errors(array $input): array
{
    $errors = [];
    $typeId = (int) ($input['leave_type_id'] ?? 0);
    $start = (string) ($input['start_date'] ?? '');
    $end = (string) ($input['end_date'] ?? '');
    $reason = trim((string) ($input['reason'] ?? ''));

    if ($typeId <= 0) {
        $errors['leave_type_id'] = 'Select a leave type.';
    }
    if (!validate_iso_date($start)) {
        $errors['start_date'] = 'Enter a valid start date.';
    }
    if (!validate_iso_date($end)) {
        $errors['end_date'] = 'Enter a valid end date.';
    }
    if (validate_iso_date($start) && validate_iso_date($end)) {
        if ($end < $start) {
            $errors['end_date'] = 'End date must be on or after the start date.';
        } elseif (substr($start, 0, 4) !== substr($end, 0, 4)) {
            $errors['end_date'] = 'A leave request cannot span two calendar years. Please split it.';
        } elseif ($start < today()) {
            $errors['start_date'] = 'Leave cannot start in the past.';
        } elseif (working_days_between($start, $end) < 1) {
            $errors['end_date'] = 'The selected dates contain no working days.';
        }
    }
    if ($reason !== '' && mb_strlen($reason) > 1000) {
        $errors['reason'] = 'Reason must be 1,000 characters or fewer.';
    }
    return $errors;
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? (string) $errors[$field] : '';
}

function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}
