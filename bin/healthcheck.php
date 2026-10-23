<?php
declare(strict_types=1);

/*
 * Deployment and data-integrity self-check.
 *
 * Usage: php bin/healthcheck.php
 * Exit code 0 = no failures (warnings allowed), 1 = at least one failure.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require_once BASE_PATH . '/database/Schema.php';

$failures = 0;
$warnings = 0;

function check(string $label, bool $ok, string $detail = '', bool $warnOnly = false): void
{
    global $failures, $warnings;
    if ($ok) {
        fwrite(STDOUT, "  [PASS] $label" . ($detail !== '' ? " — $detail" : '') . PHP_EOL);
        return;
    }
    if ($warnOnly) {
        $warnings++;
        fwrite(STDOUT, "  [WARN] $label" . ($detail !== '' ? " — $detail" : '') . PHP_EOL);
        return;
    }
    $failures++;
    fwrite(STDOUT, "  [FAIL] $label" . ($detail !== '' ? " — $detail" : '') . PHP_EOL);
}

fwrite(STDOUT, "ESS HRMS health check" . PHP_EOL);
fwrite(STDOUT, str_repeat('-', 60) . PHP_EOL);

fwrite(STDOUT, "Runtime" . PHP_EOL);
check('PHP version is 8.1 or newer', PHP_VERSION_ID >= 80100, PHP_VERSION);
foreach (['mysqli', 'mbstring', 'fileinfo', 'session', 'json'] as $extension) {
    check("Extension: $extension", extension_loaded($extension));
}
check(
    'Debug mode is off in production',
    !((string) config('env') === 'production' && (bool) config('debug')),
    (string) config('env')
);
check(
    'Timezone configured',
    (string) config('timezone') !== '',
    (string) config('timezone')
);

fwrite(STDOUT, PHP_EOL . 'Storage' . PHP_EOL);
foreach ([
    'storage root' => (string) config('storage.path'),
    'documents' => (string) config('storage.documents'),
    'photos' => (string) config('storage.photos'),
    'logs' => (string) config('storage.logs'),
] as $label => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
    check("$label directory exists", is_dir($path), $path);
    check("$label directory is writable", is_dir($path) && is_writable($path), $path);
}

fwrite(STDOUT, PHP_EOL . 'Database' . PHP_EOL);
try {
    $version = (string) db()->value('SELECT VERSION()', [], 'unknown');
    check('Database connection', true, 'MySQL/MariaDB ' . $version);

    $requiredTables = [
        'users', 'departments', 'positions', 'leave_types', 'leave_requests',
        'leave_balances', 'file_attachments', 'notifications', 'audit_logs',
        'login_attempts', 'schema_migrations',
    ];
    $missing = [];
    foreach ($requiredTables as $table) {
        if (!Schema::tableExists($table)) {
            $missing[] = $table;
        }
    }
    check('Required tables present', $missing === [], $missing === [] ? count($requiredTables) . ' tables' : 'missing: ' . implode(', ', $missing));

    if (Schema::tableExists('schema_migrations')) {
        $applied = (int) db()->value('SELECT COUNT(*) FROM schema_migrations', [], 0);
        check('At least one migration applied', $applied > 0, $applied . ' applied');
    }

    $admins = (int) db()->value(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND archived_at IS NULL",
        [],
        0
    );
    check('At least one active administrator', $admins > 0, $admins . ' active', true);

    if (Schema::tableExists('users')) {
        $duplicateEmails = Schema::duplicateGroups('users', 'email');
        check('No duplicate email addresses', $duplicateEmails === 0, $duplicateEmails . ' duplicate group(s)', true);
        $uniqueEmail = Schema::hasIndexOn('users', ['email'], true);
        check('Unique index on users.email', $uniqueEmail, 'enforced by database', true);
        $duplicateNumbers = (int) db()->value(
            "SELECT COUNT(*) FROM (SELECT employee_number FROM users WHERE employee_number IS NOT NULL
             GROUP BY employee_number HAVING COUNT(*) > 1) AS d",
            [],
            0
        );
        check('No duplicate employee numbers', $duplicateNumbers === 0, $duplicateNumbers . ' duplicate group(s)');
    }

    $orphanDocuments = (int) db()->value(
        'SELECT COUNT(*) FROM file_attachments fa
         LEFT JOIN users u ON u.id = fa.ess_staff_id WHERE u.id IS NULL',
        [],
        0
    );
    check('No orphaned document ownership rows', $orphanDocuments === 0, $orphanDocuments . ' orphan(s)');
} catch (Throwable $exception) {
    check('Database connection', false, 'unable to connect; check .env');
    app_log('error', 'Health check database failure', ['reason' => $exception->getMessage()]);
}

fwrite(STDOUT, PHP_EOL . str_repeat('-', 60) . PHP_EOL);
fwrite(STDOUT, "Result: " . ($failures === 0 ? 'OK' : 'FAILED') . " · $warnings warning(s)" . PHP_EOL);
exit($failures === 0 ? 0 : 1);
