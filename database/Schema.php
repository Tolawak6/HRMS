<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/**
 * Small schema-introspection helper used by migrations. All identifiers are
 * validated and quoted; no user input reaches these methods.
 */
final class Schema
{
    public static array $notes = [];

    public static function note(string $message): void
    {
        self::$notes[] = $message;
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '  ! ' . $message . PHP_EOL);
        }
    }

    private static function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Invalid SQL identifier.');
        }
        return '`' . $name . '`';
    }

    public static function tableExists(string $table): bool
    {
        return db()->value(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?',
            [$table],
            0
        ) > 0;
    }

    public static function columnExists(string $table, string $column): bool
    {
        return db()->value(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column],
            0
        ) > 0;
    }

    public static function columnInfo(string $table, string $column): ?array
    {
        return db()->one(
            'SELECT COLUMN_TYPE AS column_type, DATA_TYPE AS data_type,
                    CHARACTER_MAXIMUM_LENGTH AS max_length, IS_NULLABLE AS is_nullable,
                    COLUMN_DEFAULT AS column_default
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );
    }

    public static function userKeyType(): string
    {
        $info = self::columnInfo('users', 'id');
        $type = strtolower((string) ($info['column_type'] ?? 'int(11)'));
        $unsigned = str_contains($type, 'unsigned');
        if (str_starts_with($type, 'bigint')) {
            return 'BIGINT' . ($unsigned ? ' UNSIGNED' : '');
        }
        if (str_starts_with($type, 'smallint')) {
            return 'SMALLINT' . ($unsigned ? ' UNSIGNED' : '');
        }
        return 'INT' . ($unsigned ? ' UNSIGNED' : '');
    }

    public static function engine(string $table): string
    {
        return (string) db()->value(
            'SELECT ENGINE FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?',
            [$table],
            ''
        );
    }

    public static function addColumn(string $table, string $column, string $definition): bool
    {
        if (self::columnExists($table, $column)) {
            return false;
        }
        db()->execute(
            'ALTER TABLE ' . self::identifier($table)
            . ' ADD COLUMN ' . self::identifier($column) . ' ' . $definition
        );
        return true;
    }

    /**
     * Widen a VARCHAR/CHAR column without touching existing values.
     */
    public static function widenVarchar(string $table, string $column, int $length): bool
    {
        $info = self::columnInfo($table, $column);
        if ($info === null) {
            return false;
        }
        $dataType = strtolower((string) $info['data_type']);
        if (!in_array($dataType, ['varchar', 'char'], true)) {
            return false;
        }
        $currentLength = (int) ($info['max_length'] ?? 0);
        if ($currentLength >= $length) {
            return false;
        }
        $nullable = strtoupper((string) $info['is_nullable']) === 'YES';
        db()->execute(
            'ALTER TABLE ' . self::identifier($table)
            . ' MODIFY ' . self::identifier($column) . ' VARCHAR(' . $length . ') '
            . ($nullable ? 'NULL' : 'NOT NULL')
        );
        return true;
    }

    public static function hasIndexOn(string $table, array $columns, ?bool $unique = null): bool
    {
        $rows = db()->all(
            'SELECT INDEX_NAME AS name, NON_UNIQUE AS non_unique, SEQ_IN_INDEX AS seq, COLUMN_NAME AS column_name
             FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[(string) $row['name']][] = (string) $row['column_name'];
            $indexes[(string) $row['name'] . '::unique'] = ((int) $row['non_unique']) === 0;
        }
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            if (($indexes[$name] ?? []) === $columns) {
                $isUnique = (bool) ($indexes[$name . '::unique'] ?? false);
                if ($unique === null || $unique === $isUnique) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function indexExists(string $table, string $indexName): bool
    {
        return db()->value(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName],
            0
        ) > 0;
    }

    public static function addIndex(string $table, string $name, array $columns, bool $unique = false): bool
    {
        if (self::indexExists($table, $name) || self::hasIndexOn($table, $columns, $unique)) {
            return false;
        }
        $quotedColumns = implode(', ', array_map([self::class, 'identifier'], $columns));
        db()->execute(
            'ALTER TABLE ' . self::identifier($table)
            . ' ADD ' . ($unique ? 'UNIQUE ' : '') . 'KEY '
            . self::identifier($name) . ' (' . $quotedColumns . ')'
        );
        return true;
    }

    public static function foreignKeyExists(string $table, string $name): bool
    {
        return db()->value(
            'SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE() AND table_name = ?
               AND constraint_name = ? AND constraint_type = "FOREIGN KEY"',
            [$table, $name],
            0
        ) > 0;
    }

    public static function addForeignKey(
        string $table,
        string $name,
        string $column,
        string $referenceTable,
        string $onDelete = 'RESTRICT'
    ): bool {
        if (self::foreignKeyExists($table, $name)) {
            return false;
        }
        db()->execute(
            'ALTER TABLE ' . self::identifier($table)
            . ' ADD CONSTRAINT ' . self::identifier($name)
            . ' FOREIGN KEY (' . self::identifier($column) . ')'
            . ' REFERENCES ' . self::identifier($referenceTable) . ' (`id`)'
            . ' ON DELETE ' . $onDelete . ' ON UPDATE RESTRICT'
        );
        return true;
    }

    public static function duplicateGroups(string $table, string $column): int
    {
        $tableSql = self::identifier($table);
        $columnSql = self::identifier($column);
        return (int) db()->value(
            "SELECT COUNT(*) FROM (
                SELECT $columnSql FROM $tableSql
                WHERE $columnSql IS NOT NULL AND $columnSql <> ''
                GROUP BY $columnSql HAVING COUNT(*) > 1
             ) AS duplicates"
        );
    }
}
