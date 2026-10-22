<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once BASE_PATH . '/database/Schema.php';
require_once BASE_PATH . '/database/Migrator.php';

$options = getopt('', ['status', 'create-database', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/migrate.php [--status] [--create-database]\n\n";
    echo "  --status           Show applied and pending migrations.\n";
    echo "  --create-database  Create the configured database if it does not exist.\n";
    exit(0);
}

$dbConfig = (array) config('db');
$databaseName = (string) ($dbConfig['name'] ?? '');

try {
    if (isset($options['create-database'])) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
            throw new RuntimeException('The configured database name contains unsupported characters.');
        }
        $createConfig = $dbConfig;
        $createConfig['name'] = '';
        $bootstrapDb = new Database($createConfig);
        $bootstrapDb->raw()->query(
            'CREATE DATABASE IF NOT EXISTS `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $bootstrapDb->raw()->close();
        echo "Database `{$databaseName}` is available.\n";
    }

    $db = db();
    Migrator::ensureMigrationsTable($db);

    if (isset($options['status'])) {
        $files = Migrator::files(BASE_PATH . '/database/migrations');
        $applied = Migrator::applied($db);
        echo "Database: {$databaseName}\n\n";
        foreach ($files as $file) {
            echo (in_array($file, $applied, true) ? '  [applied] ' : '  [pending] ') . $file . "\n";
        }
        exit(0);
    }

    echo "Migrating database `{$databaseName}`...\n";
    $result = Migrator::run($db, BASE_PATH . '/database/migrations');
    echo "\n" . count($result['applied']) . ' migration(s) applied, ' . count($result['skipped']) . " already applied.\n";
    echo "No existing employee data was deleted.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "\nMigration failed: " . $exception->getMessage() . "\n");
    fwrite(STDERR, "Fix the issue and run the command again; migrations are resumable.\n");
    exit(1);
}
