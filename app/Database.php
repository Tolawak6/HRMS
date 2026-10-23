<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/**
 * Thin, prepared-statement-only wrapper around mysqli.
 */
final class Database
{
    private mysqli $conn;

    public function __construct(array $config)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conn = new mysqli(
                (string) ($config['host'] ?? '127.0.0.1'),
                (string) ($config['user'] ?? ''),
                (string) ($config['password'] ?? ''),
                (string) ($config['name'] ?? ''),
                (int) ($config['port'] ?? 3306),
                ($config['socket'] ?? '') !== '' ? (string) $config['socket'] : null
            );
            $this->conn->set_charset((string) ($config['charset'] ?? 'utf8mb4'));
            $offset = (new DateTimeImmutable('now'))->format('P');
            $this->conn->query("SET time_zone = '" . $this->conn->real_escape_string($offset) . "'");
            $this->conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (mysqli_sql_exception $exception) {
            app_log('error', 'Database connection failed', [
                'code' => $exception->getCode(),
                'host' => $config['host'] ?? '',
                'database' => $config['name'] ?? '',
            ]);
            throw new RuntimeException(
                'The database is not available. Check the configuration and try again.',
                0,
                $exception
            );
        }
    }

    public function raw(): mysqli
    {
        return $this->conn;
    }

    private function statement(string $sql, array $params): mysqli_stmt
    {
        try {
            $stmt = $this->conn->prepare($sql);
            if ($params !== []) {
                $types = '';
                foreach ($params as $value) {
                    if (is_int($value) || is_bool($value)) {
                        $types .= 'i';
                    } elseif (is_float($value)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
                $bindArgs = [$types];
                foreach ($params as $key => $unused) {
                    $bindArgs[] = &$params[$key];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindArgs);
            }
            $stmt->execute();
            return $stmt;
        } catch (mysqli_sql_exception $exception) {
            app_log('error', 'Database query failed', [
                'code' => $exception->getCode(),
                'sql' => preg_replace('/\s+/', ' ', mb_substr($sql, 0, 220)),
            ]);
            throw $exception;
        }
    }

    public function all(string $sql, array $params = []): array
    {
        $result = $this->statement($sql, $params)->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $result = $this->statement($sql, $params)->get_result();
        $row = $result->fetch_assoc();
        $result->free();
        return $row ?: null;
    }

    public function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $result = $this->statement($sql, $params)->get_result();
        $row = $result->fetch_row();
        $result->free();
        return $row === null || $row === false ? $default : ($row[0] ?? $default);
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->statement($sql, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    public function insert(string $sql, array $params = []): int
    {
        $stmt = $this->statement($sql, $params);
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function transaction(callable $callback): mixed
    {
        $this->conn->begin_transaction();
        try {
            $result = $callback($this);
            $this->conn->commit();
            return $result;
        } catch (Throwable $exception) {
            try {
                $this->conn->rollback();
            } catch (Throwable) {
                // The transaction may already have been rolled back by the server.
            }
            throw $exception;
        }
    }

    public function isDuplicateKey(Throwable $exception): bool
    {
        return $exception instanceof mysqli_sql_exception && (int) $exception->getCode() === 1062;
    }
}

function db(): Database
{
    static $database = null;
    if (!$database instanceof Database) {
        $database = new Database((array) config('db'));
    }
    return $database;
}
