<?php
declare(strict_types=1);

/*
 * Single application bootstrap.
 * Every HTTP entry point must require this file before producing output.
 */

if (defined('HRMS_BOOTSTRAPPED')) {
    return;
}
define('HRMS_BOOTSTRAPPED', true);
define('BASE_PATH', dirname(__DIR__));

/* ---------------------------------------------------------------------------
 * Environment loading (small .env parser; no external dependencies)
 * ------------------------------------------------------------------------ */
function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || !preg_match('/^[A-Z0-9_]+$/i', $key)) {
            continue;
        }
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($key) === false && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return $value;
}

load_env_file(BASE_PATH . '/.env');

$GLOBALS['hrms_config'] = require BASE_PATH . '/config/app.php';

function config(string $key, mixed $default = null): mixed
{
    $config = $GLOBALS['hrms_config'] ?? [];
    $segments = explode('.', $key);
    foreach ($segments as $segment) {
        if (!is_array($config) || !array_key_exists($segment, $config)) {
            return $default;
        }
        $config = $config[$segment];
    }
    return $config;
}

date_default_timezone_set((string) config('timezone', 'Africa/Addis_Ababa'));

/* ---------------------------------------------------------------------------
 * Error handling and logging
 * ------------------------------------------------------------------------ */
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');
ini_set('log_errors', '1');

$logDir = (string) config('storage.logs');
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

function app_log(string $level, string $message, array $context = []): void
{
    $logDir = (string) config('storage.logs');
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    $safeContext = [];
    foreach ($context as $key => $value) {
        // Never persist passwords, tokens or full personal records in logs.
        if (preg_match('/pass|token|secret|authorization|cookie/i', (string) $key)) {
            continue;
        }
        if (is_scalar($value)) {
            $safeContext[$key] = mb_substr((string) $value, 0, 300);
        }
    }
    $line = json_encode([
        'time'    => date('c'),
        'level'   => strtoupper($level),
        'message' => mb_substr($message, 0, 1000),
        'context' => $safeContext,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($logDir . '/app.log', ($line ?: '{}') . PHP_EOL, FILE_APPEND | LOCK_EX);
    } else {
        error_log('[HRMS] ' . ($line ?: $message));
    }
}

/* ---------------------------------------------------------------------------
 * Base URL detection (works at web root or in an Apache sub-directory)
 * ------------------------------------------------------------------------ */
function detect_base_path(): string
{
    $appRoot = realpath(BASE_PATH);
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($appRoot === false || $docRoot === false) {
        return '';
    }
    $appRoot = str_replace('\\', '/', $appRoot);
    $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
    if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $base = substr($appRoot, strlen($docRoot));
        return rtrim('/' . ltrim($base, '/'), '/');
    }
    return '';
}
define('APP_BASE_PATH', detect_base_path());

require_once BASE_PATH . '/app/helpers.php';
require_once BASE_PATH . '/app/Database.php';
require_once BASE_PATH . '/app/validation.php';
require_once BASE_PATH . '/app/audit.php';
require_once BASE_PATH . '/app/notifications.php';
require_once BASE_PATH . '/app/auth.php';
require_once BASE_PATH . '/app/employees.php';
require_once BASE_PATH . '/app/organization.php';
require_once BASE_PATH . '/app/documents.php';
require_once BASE_PATH . '/app/leave.php';
require_once BASE_PATH . '/app/accounts.php';
require_once BASE_PATH . '/app/reports.php';

/* ---------------------------------------------------------------------------
 * HTTP security headers
 * ------------------------------------------------------------------------ */
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');

    $isProduction = config('env') === 'production';
    if ($isProduction) {
        // The preview environment embeds the app in an iframe; production must not.
        header('X-Frame-Options: DENY');
    }

    $csp = "default-src 'self'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self'; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'";
    if ($isProduction) {
        $csp .= "; frame-ancestors 'none'";
    }
    header('Content-Security-Policy: ' . $csp);

    if ($isProduction && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* ---------------------------------------------------------------------------
 * Session (hardened cookies, idle + absolute timeouts, CSRF token)
 * ------------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $cookiePath = APP_BASE_PATH !== '' ? APP_BASE_PATH . '/' : '/';
    session_name((string) config('session.name', 'ess_hrms_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    if (PHP_VERSION_ID < 80500) {
        // Deprecated (and ignored) from PHP 8.5, where the ID format is fixed.
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
    }
    session_start();
}

$idleSeconds = (int) config('session.idle_minutes', 30) * 60;
$maxSeconds = (int) config('session.max_hours', 8) * 3600;
if (isset($_SESSION['uid'])) {
    $lastActivity = (int) ($_SESSION['last_activity'] ?? time());
    $loginTime = (int) ($_SESSION['login_time'] ?? time());
    if ((time() - $lastActivity) > $idleSeconds || (time() - $loginTime) > $maxSeconds) {
        app_log('info', 'Session expired', ['user_id' => $_SESSION['uid']]);
        if (function_exists('audit_log')) {
            audit_log('auth.session_expired', 'user', (int) $_SESSION['uid'], 'Session expired');
        }
        clear_auth_session();
        flash('warning', 'Your session expired. Please sign in again.');
    } else {
        $_SESSION['last_activity'] = time();
        $regenerateEvery = (int) config('session.regenerate_every', 900);
        if ((time() - (int) ($_SESSION['last_regenerated'] ?? time())) > $regenerateEvery) {
            session_regenerate_id(true);
            $_SESSION['last_regenerated'] = time();
        }
    }
}

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Central CSRF enforcement for every POST request. */
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        app_log('warning', 'CSRF validation failed', [
            'path' => parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
        ]);
        $target = isset($_SESSION['uid']) ? 'dashboard.php' : 'login.php';
        flash('error', 'Your session expired or the form was not valid. Please try again.');
        redirect($target);
    }
}
