<?php
declare(strict_types=1);

/*
 * Create the first administrator account.
 *
 * Usage:
 *   php bin/create_admin.php --username=admin --first-name=Abebe --last-name=Kebede --email=admin@example.org
 *
 * The password is read from standard input (or an interactive hidden prompt)
 * so it never appears in shell history. No default credential ships with the
 * application; the account must change its password at first sign-in.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $argument, $matches)) {
        $options[$matches[1]] = $matches[2];
    }
}

$username = strtolower(trim((string) ($options['username'] ?? '')));
$firstName = trim((string) ($options['first-name'] ?? ''));
$lastName = trim((string) ($options['last-name'] ?? ''));
$email = trim((string) ($options['email'] ?? ''));

function prompt_line(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}

if ($username === '') {
    $username = prompt_line('Username: ');
}
if ($firstName === '') {
    $firstName = prompt_line('First name: ');
}
if ($lastName === '') {
    $lastName = prompt_line('Last name: ');
}
if ($email === '') {
    $email = prompt_line('Email (optional): ');
}

$username = strtolower($username);
$errors = [];
if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
    $errors[] = 'Username must be 3-60 characters: letters, numbers, dot, underscore or hyphen.';
}
if ($firstName === '' || mb_strlen($firstName) > 80) {
    $errors[] = 'First name is required (maximum 80 characters).';
}
if ($lastName === '' || mb_strlen($lastName) > 80) {
    $errors[] = 'Last name is required (maximum 80 characters).';
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Email address is not valid.';
}
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

if (db()->value('SELECT COUNT(*) FROM users WHERE username = ?', [$username], 0) > 0) {
    fwrite(STDERR, "An account with username \"$username\" already exists.\n");
    exit(1);
}

$isTty = function_exists('stream_isatty') && @stream_isatty(STDIN);
if ($isTty) {
    fwrite(STDOUT, "Password (input hidden): ");
    @shell_exec('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    @shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, PHP_EOL . "Confirm password: ");
    @shell_exec('stty -echo 2>/dev/null');
    $confirm = trim((string) fgets(STDIN));
    @shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, PHP_EOL);
} else {
    $password = trim((string) fgets(STDIN));
    $confirm = trim((string) fgets(STDIN));
}
if ($password !== $confirm) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}
$passwordErrors = password_policy_errors($password, $username);
if ($passwordErrors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $passwordErrors) . PHP_EOL);
    exit(1);
}

try {
    $id = db()->insert(
        "INSERT INTO users
            (username, password, role, first_name, last_name, email, employment_status,
             is_active, must_change_password, created_at, updated_at)
         VALUES (?, ?, 'admin', ?, ?, ?, 'Active', 1, 1, NOW(), NOW())",
        [
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $firstName,
            $lastName,
            $email !== '' ? $email : null,
        ]
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "Could not create the administrator account. Check the database configuration and try again.\n");
    app_log('error', 'Admin bootstrap failed', ['reason' => $exception->getMessage()]);
    exit(1);
}

audit_log('account.create', 'account', $id, 'Initial administrator account created from CLI');

fwrite(STDOUT, "Administrator \"$username\" created (id $id).\n");
fwrite(STDOUT, "The account must change its password at first sign-in.\n");
