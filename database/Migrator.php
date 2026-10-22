<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

final class Migrator
{
    public static function ensureMigrationsTable(Database $db): void
    {
        $db->execute(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(190) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_schema_migrations (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<int,string> */
    public static function files(string $directory): array
    {
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        return array_map(static fn (string $file): string => basename($file), $files);
    }

    /** @return array<int,string> */
    public static function applied(Database $db): array
    {
        self::ensureMigrationsTable($db);
        return array_map(
            static fn (array $row): string => (string) $row['migration'],
            $db->all('SELECT migration FROM schema_migrations ORDER BY migration')
        );
    }

    /**
     * @return array{applied:array<int,string>,skipped:array<int,string>}
     */
    public static function run(Database $db, string $directory, bool $verbose = true): array
    {
        self::ensureMigrationsTable($db);
        $applied = self::applied($db);
        $result = ['applied' => [], 'skipped' => []];

        foreach (self::files($directory) as $file) {
            if (in_array($file, $applied, true)) {
                $result['skipped'][] = $file;
                continue;
            }
            $migration = require rtrim($directory, '/') . '/' . $file;
            if (!is_callable($migration)) {
                throw new RuntimeException('Migration did not return a callable: ' . $file);
            }
            if ($verbose) {
                fwrite(STDOUT, 'Applying ' . $file . ' ... ');
            }
            $migration($db);
            $db->execute('INSERT INTO schema_migrations (migration) VALUES (?)', [$file]);
            $result['applied'][] = $file;
            if ($verbose) {
                fwrite(STDOUT, 'done' . PHP_EOL);
            }
        }
        return $result;
    }
}
